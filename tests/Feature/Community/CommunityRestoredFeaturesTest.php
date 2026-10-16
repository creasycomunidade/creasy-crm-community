<?php

namespace Tests\Feature\Community;

use App\Events\ContactCreated;
use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookEndpoint;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunityRestoredFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_overview_instead_of_redirecting_to_inbox(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('client/Reports/Inbox/Index')
                ->where('isDashboard', true)->has('channelMix')->has('conversationsOverTime'));
    }

    public function test_removed_integrations_are_not_listed_or_editable(): void
    {
        $admin = $this->createSuperAdmin();
        $permission = \App\Models\Permission::firstOrCreate(['key' => 'manage_integrations'], ['name' => 'Manage integrations', 'category' => 'settings']);
        $admin->roles()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/integrations')->assertOk()->assertInertia(function ($page) {
            $page->component('Admin/Integrations/Index');
        });
        foreach (['oauth_linkedin', 'oauth_twitter', 'oauth_youtube', 'oauth_tiktok',
            'oauth_shopify', 'oauth_bigcommerce', 'google_places', 'firecrawl', 'google_workspace', 'zernio'] as $provider) {
            $this->assertNotContains($provider, \App\Modules\Integrations\Models\IntegrationConfig::PROVIDERS);
            $this->get('/admin/integrations/'.$provider)->assertNotFound();
        }
    }

    public function test_restored_pages_render_without_paid_modules_or_subscription(): void
    {
        $context = $this->createWorkspaceContext();
        $automation = Automation::create(['workspace_id' => $context['workspace']->id, 'name' => 'Fluxo', 'status' => 'draft']);
        foreach (['client.ai.chatbots.index', 'client.ai.providers.index', 'client.ai.knowledge-bases.index', 'client.automations.index', 'client.webhooks.index', 'client.inbox.scheduled-messages.index'] as $route) {
            $this->actingAs($context['user'])->get(route($route))->assertOk();
        }
        $this->get(route('client.automations.edit', $automation))->assertOk();
        $this->assertNull($context['client']->effectivePlan());
    }

    public function test_chatbot_configuration_is_scoped_to_its_workspace(): void
    {
        $one = $this->createWorkspaceContext();
        $two = $this->createWorkspaceContext();
        $bot = AiChatbot::create(['workspace_id' => $one['workspace']->id, 'name' => 'Atendimento', 'enabled' => true]);
        $account = ChannelAccount::create(['workspace_id' => $one['workspace']->id, 'channel' => 'telegram', 'provider' => 'telegram', 'display_name' => 'Suporte', 'status' => 'active']);
        $this->actingAs($one['user'])->patch(route('client.inbox.setup.assign-chatbot', $account), ['chatbot_id' => $bot->id])->assertSessionHasNoErrors();
        $this->assertEquals($bot->id, $account->fresh()->meta_json['ai_chatbot_id']);
        $this->actingAs($two['user'])->patch(route('client.inbox.setup.assign-chatbot', $account), ['chatbot_id' => $bot->id])->assertForbidden();
    }

    public function test_builder_webhook_trigger_queues_exactly_one_run(): void
    {
        Bus::fake([ExecuteAutomationRunJob::class]);
        $context = $this->createWorkspaceContext();
        $flow = Automation::create(['workspace_id' => $context['workspace']->id, 'name' => 'Entrada', 'status' => 'active', 'trigger_type' => 'webhook.received', 'trigger_token' => 'community-test-token', 'nodes' => [], 'edges' => []]);
        $this->postJson('/webhooks/automation/community-test-token', ['example' => 'value'])->assertStatus(202);
        $this->assertEquals(1, $flow->runs()->count());
        Bus::assertDispatchedTimes(ExecuteAutomationRunJob::class, 1);
    }

    public function test_contact_event_delivers_outbound_webhook_once(): void
    {
        Bus::fake([DispatchWebhookJob::class]);
        $context = $this->createWorkspaceContext();
        WebhookEndpoint::create(['user_id' => $context['user']->id, 'url' => 'https://example.com/events', 'secret' => 'test-secret', 'events' => ['contact.created'], 'enabled' => true]);
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        ContactCreated::dispatch($contact);
        Bus::assertDispatchedTimes(DispatchWebhookJob::class, 1);
    }

    public function test_chatbot_replies_with_owner_credentials_without_a_plan(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Olá, posso ajudar.']]], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5], 'model' => 'gpt-4o-mini'])]);
        $context = $this->createWorkspaceContext();
        AiProviderConfig::create(['workspace_id' => $context['workspace']->id, 'provider' => 'openai', 'credentials' => ['api_key' => 'test-only'], 'enabled' => true, 'default_model_chat' => 'gpt-4o-mini']);
        $bot = AiChatbot::create(['workspace_id' => $context['workspace']->id, 'name' => 'Atendimento', 'enabled' => true]);
        $result = app(ChatbotRunner::class)->runForApi($bot, 'Olá', $context['workspace']->id);
        $this->assertSame('Olá, posso ajudar.', $result['reply']);
        $this->assertDatabaseHas('ai_runs', ['chatbot_id' => $bot->id, 'status' => 'ok']);
    }
}

