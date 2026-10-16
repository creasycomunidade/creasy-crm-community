<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Inbox\Services\MessengerDriver;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\WhatsappDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Exercises each automation node end-to-end through the real AutomationEngine.
 * The WhatsApp channel driver is faked so we can assert the exact outbound
 * Message type/payload the engine builds (i.e. the Graph API shape).
 */
class AutomationNodeBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private $workspace;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $ctx = $this->createWorkspaceContext();
        $this->workspace = $ctx['workspace'];
        $this->client = $ctx['client'];

        $this->contact = Contact::factory()->create([
            'workspace_id' => $this->workspace->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'phone_e164' => '+15550000001',
        ]);

        ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'whatsapp',
            'status' => 'active',
            'display_name' => 'Test WA',
            'phone_number_id' => '1234567890',
        ]);

        // Fake every channel driver so sends succeed without hitting Meta.
        foreach ([WhatsappDriver::class, MessengerDriver::class, InstagramDriver::class] as $driverClass) {
            $driver = Mockery::mock(ChannelDriverInterface::class);
            $driver->shouldReceive('send')->andReturn('provider.msg.id');
            $this->app->instance($driverClass, $driver);
        }
    }

    private function makeWhatsappAccount(string $name, string $phoneNumberId): ChannelAccount
    {
        return ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'whatsapp',
            'status' => 'active',
            'display_name' => $name,
            'phone_number_id' => $phoneNumberId,
        ]);
    }

    private function makeConversation(ChannelAccount $account, string $thread, string $channel): Conversation
    {
        return Conversation::create([
            'workspace_id' => $this->workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $this->contact->id,
            'external_thread_id' => $thread,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function runNodes(array $nodes, array $edges, array $context = []): AutomationRun
    {
        $automation = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Test',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => array_merge(
                [['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []]],
                $nodes,
            ),
            'edges' => $edges,
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $this->contact->id,
            'status' => 'pending',
            'context' => $context,
            'started_at' => now(),
        ]);

        (new ExecuteAutomationRunJob($run->id))->handle(app(AutomationEngine::class));

        return $run->fresh();
    }

    private function runSingleNode(string $type, array $data, array $context = []): AutomationRun
    {
        return $this->runNodes(
            [['id' => 'n1', 'type' => $type, 'position' => ['x' => 0, 'y' => 100], 'data' => $data]],
            [['id' => 'e1', 'source' => 'trigger-1', 'target' => 'n1']],
            $context,
        );
    }

    private function lastOutbound(): ?Message
    {
        return Message::where('direction', 'out')->latest('id')->first();
    }

    // ─── SEND ─────────────────────────────────────────────────────────────────

    public function test_send_template_builds_template_payload(): void
    {
        $run = $this->runSingleNode('send_template', [
            'template_name' => 'welcome', 'language' => 'en_US', 'variables' => "Alice\nGold",
        ]);

        $this->assertEquals('completed', $run->status);
        $msg = $this->lastOutbound();
        $this->assertEquals('template', $msg->type);
        $this->assertEquals('welcome', $msg->payload['template']['name']);
        $this->assertEquals('en_US', $msg->payload['template']['language']);
        $this->assertEquals('Alice', $msg->payload['template']['components'][0]['parameters'][0]['text']);
    }

    public function test_send_template_preserves_positional_variables(): void
    {
        // An array keeps blanks so {{1}}/{{2}}/{{3}} stay aligned even if {{2}} is empty.
        $run = $this->runSingleNode('send_template', [
            'template_name' => 'welcome', 'language' => 'en', 'variables' => ['Alice', '', 'Gold'],
        ]);

        $this->assertEquals('completed', $run->status);
        $params = $this->lastOutbound()->payload['template']['components'][0]['parameters'];
        $this->assertCount(3, $params);
        $this->assertEquals('Alice', $params[0]['text']);
        $this->assertEquals('', $params[1]['text']);
        $this->assertEquals('Gold', $params[2]['text']);
    }

    public function test_send_media_builds_image_payload(): void
    {
        $run = $this->runSingleNode('send_media', [
            'media_type' => 'image', 'link' => 'https://cdn.test/a.jpg', 'caption' => 'Hi {{contact.first_name}}',
        ]);

        $this->assertEquals('completed', $run->status);
        $msg = $this->lastOutbound();
        $this->assertEquals('image', $msg->type);
        $this->assertEquals('https://cdn.test/a.jpg', $msg->payload['link']);
        $this->assertEquals('Hi Alice', $msg->payload['caption']);
    }

    public function test_send_sequence_sends_each_step(): void
    {
        $run = $this->runSingleNode('send_sequence', [
            'steps' => [
                ['kind' => 'text', 'body' => 'First {{contact.first_name}}'],
                ['kind' => 'media', 'media_type' => 'image', 'link' => 'https://cdn.test/b.jpg', 'caption' => 'pic'],
            ],
        ]);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals(2, Message::where('direction', 'out')->count());
        $this->assertEquals('text', Message::where('direction', 'out')->orderBy('id')->first()->type);
        $this->assertEquals('image', $this->lastOutbound()->type);
    }

    public function test_quick_replies_builds_button_interactive(): void
    {
        $run = $this->runSingleNode('quick_replies', [
            'body' => 'Pick one', 'buttons' => ['Yes', 'No'],
        ]);

        $this->assertEquals('completed', $run->status);
        $interactive = $this->lastOutbound()->payload['interactive'];
        $this->assertEquals('interactive', $this->lastOutbound()->type);
        $this->assertEquals('button', $interactive['type']);
        $this->assertCount(2, $interactive['action']['buttons']);
        $this->assertEquals('Yes', $interactive['action']['buttons'][0]['reply']['title']);
    }

    public function test_list_message_builds_list_interactive(): void
    {
        $run = $this->runSingleNode('list_message', [
            'body' => 'Menu', 'button_label' => 'Open', 'section_title' => 'Items',
            'rows' => "Pizza|Cheesy\nPasta",
        ]);

        $this->assertEquals('completed', $run->status);
        $interactive = $this->lastOutbound()->payload['interactive'];
        $this->assertEquals('list', $interactive['type']);
        $this->assertEquals('Open', $interactive['action']['button']);
        $rows = $interactive['action']['sections'][0]['rows'];
        $this->assertCount(2, $rows);
        $this->assertEquals('Pizza', $rows[0]['title']);
        $this->assertEquals('Cheesy', $rows[0]['description']);
    }

    // ─── LISTEN ───────────────────────────────────────────────────────────────

    public function test_ask_question_waits_then_resumes_with_reply(): void
    {
        $run = $this->runNodes(
            [
                ['id' => 'q1', 'type' => 'ask_question', 'position' => ['x' => 0, 'y' => 100], 'data' => ['question' => 'Your email?', 'variable' => 'email']],
                ['id' => 't1', 'type' => 'add_tag', 'position' => ['x' => 0, 'y' => 200], 'data' => ['tag' => 'replied']],
            ],
            [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'q1'],
                ['id' => 'e2', 'source' => 'q1', 'target' => 't1'],
            ],
        );

        // Parked waiting for a reply.
        $this->assertEquals('waiting', $run->status);
        $this->assertTrue($run->context['_awaiting_reply']);
        $this->assertFalse($this->contact->fresh()->tags()->where('name', 'replied')->exists());

        // Inbound reply resumes the run (sync queue runs it to completion).
        app(AutomationEngine::class)->resumeAwaitingReplies($this->workspace->id, $this->contact->id, 'me@x.com');

        $run = $run->fresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals('me@x.com', $run->context['email']);
        $this->assertArrayNotHasKey('_awaiting_reply', $run->context);
        $this->assertTrue($this->contact->fresh()->tags()->where('name', 'replied')->exists());
    }

    // ─── LOGIC ────────────────────────────────────────────────────────────────

    public function test_condition_routes_true_branch(): void
    {
        $run = $this->runNodes(
            [
                ['id' => 'c1', 'type' => 'condition', 'position' => ['x' => 0, 'y' => 100], 'data' => ['field' => 'contact.name', 'operator' => 'equals', 'value' => 'Alice Smith']],
                ['id' => 'yes', 'type' => 'add_tag', 'position' => ['x' => 0, 'y' => 200], 'data' => ['tag' => 'matched']],
                ['id' => 'no', 'type' => 'add_tag', 'position' => ['x' => 200, 'y' => 200], 'data' => ['tag' => 'unmatched']],
            ],
            [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'c1'],
                ['id' => 'e2', 'source' => 'c1', 'target' => 'yes', 'sourceHandle' => 'true'],
                ['id' => 'e3', 'source' => 'c1', 'target' => 'no', 'sourceHandle' => 'false'],
            ],
        );

        $this->assertEquals('completed', $run->status);
        $this->assertTrue($this->contact->fresh()->tags()->where('name', 'matched')->exists());
        $this->assertFalse($this->contact->fresh()->tags()->where('name', 'unmatched')->exists());
    }

    public function test_run_subflow_triggers_target_automation(): void
    {
        $sub = Automation::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Sub',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 's1', 'type' => 'add_tag', 'position' => ['x' => 0, 'y' => 100], 'data' => ['tag' => 'from_subflow']],
            ],
            'edges' => [['id' => 'e1', 'source' => 'trigger-1', 'target' => 's1']],
        ]);

        $run = $this->runSingleNode('run_subflow', ['automation_uuid' => $sub->uuid]);

        $this->assertEquals('completed', $run->status);
        $this->assertTrue($this->contact->fresh()->tags()->where('name', 'from_subflow')->exists());
        $this->assertEquals(1, AutomationRun::where('automation_id', $sub->id)->count());
    }

    // ─── CONTACT ──────────────────────────────────────────────────────────────

    public function test_update_contact_maps_friendly_fields(): void
    {
        $this->runSingleNode('update_contact', ['field' => 'name', 'value' => 'John Doe']);
        $c = $this->contact->fresh();
        $this->assertEquals('John', $c->first_name);
        $this->assertEquals('Doe', $c->last_name);

        $this->runSingleNode('update_contact', ['field' => 'phone', 'value' => '+15559998888']);
        $this->assertEquals('+15559998888', $this->contact->fresh()->phone_e164);

        $this->runSingleNode('update_contact', ['field' => 'notes', 'value' => 'VIP client']);
        $this->assertEquals('VIP client', $this->contact->fresh()->custom_fields['notes']);
    }

    public function test_assign_agent_assigns_conversation(): void
    {
        $account = ChannelAccount::where('workspace_id', $this->workspace->id)->first();
        $conversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $this->contact->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        $agent = User::factory()->create([
            'client_id' => $this->client->id,
            'workspace_id' => $this->workspace->id,
            'role' => User::ROLE_CLIENT,
            'status' => User::STATUS_ACTIVE,
        ]);
        $agent->workspaces()->attach($this->workspace->id, ['role' => 'agent']);

        $run = $this->runSingleNode('assign_agent', ['user_id' => $agent->id]);

        $this->assertEquals('completed', $run->status);
        $conversation->refresh();
        $this->assertEquals($agent->id, $conversation->assigned_user_id);
        $this->assertEquals('human', $conversation->assigned_to);
    }

    // ─── ENGAGE ───────────────────────────────────────────────────────────────

    public function test_cta_button_builds_cta_url_interactive(): void
    {
        $run = $this->runSingleNode('cta_button', ['body' => 'Visit us', 'display_text' => 'Open', 'url' => 'https://shop.test']);

        $this->assertEquals('completed', $run->status);
        $interactive = $this->lastOutbound()->payload['interactive'];
        $this->assertEquals('cta_url', $interactive['type']);
        $this->assertEquals('https://shop.test', $interactive['action']['parameters']['url']);
    }

    public function test_send_location_builds_location_payload(): void
    {
        $run = $this->runSingleNode('send_location', ['latitude' => '37.42', 'longitude' => '-122.08', 'name' => 'HQ']);

        $this->assertEquals('completed', $run->status);
        $msg = $this->lastOutbound();
        $this->assertEquals('location', $msg->type);
        $this->assertEquals(37.42, $msg->payload['location']['latitude']);
        $this->assertEquals('HQ', $msg->payload['location']['name']);
    }

    public function test_send_poll_uses_buttons_then_list(): void
    {
        $this->runSingleNode('send_poll', ['question' => 'Color?', 'options' => "Red\nBlue"]);
        $this->assertEquals('button', $this->lastOutbound()->payload['interactive']['type']);

        $this->runSingleNode('send_poll', ['question' => 'Color?', 'options' => "Red\nBlue\nGreen\nPink"]);
        $this->assertEquals('list', $this->lastOutbound()->payload['interactive']['type']);
    }

    public function test_whatsapp_form_builds_flow_interactive(): void
    {
        $run = $this->runSingleNode('whatsapp_form', ['flow_id' => '99', 'body' => 'Open', 'flow_cta' => 'Start']);

        $this->assertEquals('completed', $run->status);
        $params = $this->lastOutbound()->payload['interactive']['action']['parameters'];
        $this->assertEquals('flow', $this->lastOutbound()->payload['interactive']['type']);
        $this->assertEquals('99', $params['flow_id']);
        $this->assertEquals('Start', $params['flow_cta']);
    }

    // ─── COMMERCE ─────────────────────────────────────────────────────────────

    public function test_whatsapp_catalog_builds_catalog_interactive(): void
    {
        $run = $this->runSingleNode('whatsapp_catalog', ['body' => 'Shop now']);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('catalog_message', $this->lastOutbound()->payload['interactive']['type']);
    }

    // ─── INTEGRATIONS ─────────────────────────────────────────────────────────

    public function test_google_sheets_fails_clearly_when_not_configured(): void
    {
        $run = $this->runSingleNode('google_sheets', ['mode' => 'append', 'spreadsheet_id' => 'abc', 'range' => 'A:B', 'values' => "x\ny"]);

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('não está configurada', $run->error);
    }

    public function test_google_sheets_appends_row_when_configured(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'sheets.googleapis.com/*' => Http::response(['updates' => ['updatedRange' => 'Sheet1!A1:B1', 'updatedCells' => 2]]),
        ]);

        IntegrationConfig::create([
            'provider' => 'google_workspace',
            'label' => 'Google',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'secret', 'refresh_token' => 'rt'],
        ]);

        $run = $this->runSingleNode('google_sheets', [
            'mode' => 'append', 'spreadsheet_id' => 'sheet123', 'range' => 'Sheet1!A:B', 'values' => "{{contact.first_name}}\n{{contact.email}}",
        ]);

        $this->assertEquals('completed', $run->status);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sheet123') && str_contains($req->url(), ':append'));
    }

    public function test_google_forms_fails_clearly_when_not_configured(): void
    {
        $run = $this->runSingleNode('google_forms', ['mode' => 'send_link', 'form_id' => 'f1']);

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('não está configurada', $run->error);
    }

    public function test_google_forms_shares_link_when_configured(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'forms.googleapis.com/v1/forms/*/responses' => Http::response(['responses' => []]),
            'forms.googleapis.com/*' => Http::response(['info' => ['title' => 'Survey'], 'responderUri' => 'https://forms.gle/abc123']),
        ]);
        $this->configureGoogle();

        $run = $this->runSingleNode('google_forms', ['mode' => 'send_link', 'form_id' => 'form123', 'send_link' => true]);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('https://forms.gle/abc123', $run->context['form_url']);
        $this->assertStringContainsString('https://forms.gle/abc123', $this->lastOutbound()->body);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'forms.googleapis.com/v1/forms/form123'));
    }

    public function test_google_forms_reads_latest_response(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'forms.googleapis.com/v1/forms/*/responses' => Http::response(['responses' => [
                ['responseId' => 'old', 'lastSubmittedTime' => '2026-01-01T00:00:00Z', 'answers' => ['q1' => ['textAnswers' => ['answers' => [['value' => 'Red']]]]]],
                ['responseId' => 'new', 'lastSubmittedTime' => '2026-02-01T00:00:00Z', 'answers' => ['q1' => ['textAnswers' => ['answers' => [['value' => 'Blue']]]]]],
            ]]),
        ]);
        $this->configureGoogle();

        $run = $this->runSingleNode('google_forms', ['mode' => 'read_response', 'form_id' => 'form123', 'result_var' => 'survey']);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('new', $run->context['survey_id']);
        $this->assertStringContainsString('Blue', $run->context['survey_json']);
    }

    private function configureGoogle(): void
    {
        IntegrationConfig::create([
            'provider' => 'google_workspace',
            'label' => 'Google',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['client_id' => 'cid', 'client_secret' => 'secret', 'refresh_token' => 'rt'],
        ]);
    }

    // ─── WEBHOOK (regression for JSON-string payload + headers) ────────────────

    public function test_webhook_sends_decoded_json_body_and_headers(): void
    {
        Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

        $run = $this->runSingleNode('webhook', [
            'url' => 'https://example.com/hook',
            'method' => 'POST',
            'headers' => '{"X-Token":"abc"}',
            'payload' => '{"name":"{{contact.first_name}}"}',
        ]);

        $this->assertEquals('completed', $run->status);
        Http::assertSent(fn ($req) => $req->url() === 'https://example.com/hook'
            && $req->hasHeader('X-Token', 'abc')
            && $req['name'] === 'Alice'
            && isset($req['context']));
    }

    // ─── MULTI-ACCOUNT / MULTI-CHANNEL / WORKSPACE ─────────────────────────────

    public function test_whatsapp_send_uses_contacts_existing_account_not_first(): void
    {
        // setUp already made WhatsApp account "A". Add "B" and give the contact a thread on B.
        $accountB = $this->makeWhatsappAccount('WA B', '2222222222');
        $convB = $this->makeConversation($accountB, $this->contact->phone_e164, 'whatsapp');

        $run = $this->runSingleNode('send_whatsapp', ['body' => 'Hi {{contact.first_name}}']);

        $this->assertEquals('completed', $run->status);
        // Message must go out on the contact's existing account (B), not the first active (A).
        $this->assertEquals($convB->id, $this->lastOutbound()->conversation_id);
        $this->assertEquals($accountB->id, $this->lastOutbound()->conversation->channel_account_id);
    }

    public function test_messenger_sends_within_existing_thread(): void
    {
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id, 'channel' => 'messenger', 'provider' => 'messenger',
            'status' => 'active', 'display_name' => 'Page', 'credentials' => ['page_access_token' => 'tok'],
        ]);
        $conv = $this->makeConversation($account, 'PSID-123', 'messenger');

        $run = $this->runSingleNode('send_whatsapp', ['body' => 'Hello on Messenger', 'channel' => 'messenger']);

        $this->assertEquals('completed', $run->status);
        $msg = $this->lastOutbound();
        $this->assertEquals('messenger', $msg->channel);
        $this->assertEquals($conv->id, $msg->conversation_id);
    }

    public function test_instagram_sends_within_existing_thread(): void
    {
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id, 'channel' => 'instagram', 'provider' => 'instagram',
            'status' => 'active', 'display_name' => 'IG', 'credentials' => ['access_token' => 'tok', 'instagram_account_id' => 'ig1'],
        ]);
        $conv = $this->makeConversation($account, 'IGSID-9', 'instagram');

        $run = $this->runSingleNode('send_whatsapp', ['body' => 'Hello on IG', 'channel' => 'instagram']);

        $this->assertEquals('completed', $run->status);
        $msg = $this->lastOutbound();
        $this->assertEquals('instagram', $msg->channel);
        $this->assertEquals($conv->id, $msg->conversation_id);
    }

    public function test_messenger_skips_when_no_existing_thread(): void
    {
        ChannelAccount::create([
            'workspace_id' => $this->workspace->id, 'channel' => 'messenger', 'provider' => 'messenger',
            'status' => 'active', 'display_name' => 'Page', 'credentials' => ['page_access_token' => 'tok'],
        ]);
        // No conversation for the contact on Messenger.

        $run = $this->runSingleNode('send_whatsapp', ['body' => 'Hi', 'channel' => 'messenger']);

        // Soft skip — the run completes but nothing is sent on Messenger.
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(0, Message::where('channel', 'messenger')->count());
    }

    public function test_send_respects_workspace_scope(): void
    {
        // setUp's WhatsApp account belongs to this workspace; make a different workspace
        // with its own active account and ensure it is NOT used.
        $other = $this->createWorkspaceContext();
        ChannelAccount::create([
            'workspace_id' => $other['workspace']->id, 'channel' => 'whatsapp', 'provider' => 'whatsapp',
            'status' => 'active', 'display_name' => 'Other WA', 'phone_number_id' => '9999999999',
        ]);

        // Deactivate THIS workspace's only WhatsApp account → no valid account here.
        ChannelAccount::where('workspace_id', $this->workspace->id)->update(['status' => 'inactive']);

        $run = $this->runSingleNode('send_whatsapp', ['body' => 'Hi']);

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('No active whatsapp channel account', $run->error);
        $this->assertEquals(0, Message::where('channel', 'whatsapp')->count());
    }

    public function test_sms_fails_clearly_without_provider(): void
    {
        $run = $this->runSingleNode('send_sms', ['body' => 'Hi via SMS']);

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('módulo não incluído', $run->error);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
