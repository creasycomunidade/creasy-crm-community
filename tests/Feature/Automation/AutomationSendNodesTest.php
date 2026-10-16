<?php

namespace Tests\Feature\Automation;

use App\Mail\AutomationEmail;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AutomationSendNodesTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();
    }

    public function test_send_email_node_queues_mail(): void
    {
        Mail::fake();

        $workspace = $this->ctx['workspace'];
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'test@example.com',
            'first_name' => 'Alice',
        ]);

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Email Node',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'email-1',   'type' => 'send_email', 'position' => ['x' => 0, 'y' => 100], 'data' => [
                    'subject' => 'Hello {{contact.first_name}}',
                    'body' => 'Hi {{contact.first_name}}, welcome!',
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger-1', 'target' => 'email-1'],
            ],
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'context' => [],
            'started_at' => now(),
        ]);

        (new ExecuteAutomationRunJob($run->id))->handle(app(AutomationEngine::class));

        Mail::assertQueued(AutomationEmail::class, function ($mail) {
            return $mail->emailSubject === 'Hello Alice';
        });

        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }

    public function test_send_email_node_skipped_if_no_email(): void
    {
        Mail::fake();

        $workspace = $this->ctx['workspace'];
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => null,
        ]);

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Email skip test',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'email-1',   'type' => 'send_email', 'position' => ['x' => 0, 'y' => 100], 'data' => ['subject' => 'Hi', 'body' => 'Hello']],
            ],
            'edges' => [['id' => 'e1', 'source' => 'trigger-1', 'target' => 'email-1']],
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'context' => [],
            'started_at' => now(),
        ]);

        (new ExecuteAutomationRunJob($run->id))->handle(app(AutomationEngine::class));

        Mail::assertNothingQueued();
        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }

    public function test_webhook_node_calls_url(): void
    {
        Http::fake(['https://example.com/hook' => Http::response(['ok' => true], 200)]);

        $workspace = $this->ctx['workspace'];
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Webhook test',
            'status' => 'active',
            'trigger_type' => 'contact.created',
            'nodes' => [
                ['id' => 'trigger-1', 'type' => 'trigger',  'position' => ['x' => 0, 'y' => 0],   'data' => []],
                ['id' => 'wh-1',      'type' => 'webhook',  'position' => ['x' => 0, 'y' => 100], 'data' => ['url' => 'https://example.com/hook', 'method' => 'POST']],
            ],
            'edges' => [['id' => 'e1', 'source' => 'trigger-1', 'target' => 'wh-1']],
        ]);

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'context' => [],
            'started_at' => now(),
        ]);

        (new ExecuteAutomationRunJob($run->id))->handle(app(AutomationEngine::class));

        Http::assertSent(fn ($req) => $req->url() === 'https://example.com/hook');
        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }
}
