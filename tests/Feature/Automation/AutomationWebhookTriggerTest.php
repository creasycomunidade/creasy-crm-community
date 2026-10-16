<?php

namespace Tests\Feature\Automation;

use App\Events\AutomationWebhookReceived;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AutomationWebhookTriggerTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();
    }

    public function test_post_to_webhook_fires_event_and_queues_job(): void
    {
        Bus::fake([ExecuteAutomationRunJob::class]);

        $workspace = $this->ctx['workspace'];
        $token = 'test-trigger-token-abc123';

        Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Webhook Automation',
            'status' => 'active',
            'trigger_type' => 'webhook',
            'trigger_token' => $token,
            'nodes' => [],
            'edges' => [],
        ]);

        $response = $this->postJson("/webhooks/automation/{$token}", [
            'foo' => 'bar',
        ]);

        $response->assertStatus(202);
    }

    public function test_post_to_unknown_token_returns_404(): void
    {
        $response = $this->postJson('/webhooks/automation/nonexistent-token', []);
        $response->assertStatus(404);
    }

    public function test_post_to_inactive_automation_returns_404(): void
    {
        $workspace = $this->ctx['workspace'];
        $token = 'inactive-token-xyz';

        Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Paused Automation',
            'status' => 'paused',
            'trigger_type' => 'webhook',
            'trigger_token' => $token,
            'nodes' => [],
            'edges' => [],
        ]);

        $response = $this->postJson("/webhooks/automation/{$token}", []);
        $response->assertStatus(404);
    }

    public function test_webhook_resolves_contact_by_email(): void
    {
        Bus::fake([ExecuteAutomationRunJob::class]);
        Event::fake([AutomationWebhookReceived::class]);

        $workspace = $this->ctx['workspace'];
        $token = 'token-with-contact';
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'email' => 'john@example.com',
        ]);

        Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Webhook with Contact',
            'status' => 'active',
            'trigger_type' => 'webhook',
            'trigger_token' => $token,
            'nodes' => [],
            'edges' => [],
        ]);

        $this->postJson("/webhooks/automation/{$token}", ['email' => 'john@example.com']);

        Event::assertDispatched(AutomationWebhookReceived::class, function ($event) use ($contact) {
            return $event->contactId === $contact->id;
        });
    }
}
