<?php

namespace Tests\Feature\ProductionHardening;

use App\Events\MessageReceived;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Automation engine: wait-node resume + trigger listener.
 */
class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makeAutomationWithWait(int $workspaceId, int $contactId): Automation
    {
        return Automation::create([
            'workspace_id' => $workspaceId,
            'name' => 'Test Wait Automation',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'nodes' => [
                ['id' => 'n_trigger', 'type' => 'trigger', 'data' => []],
                ['id' => 'n_wait',    'type' => 'wait',    'data' => ['amount' => 5, 'unit' => 'minutes']],
                ['id' => 'n_tag',     'type' => 'add_tag', 'data' => ['tag' => 'waited']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trigger', 'target' => 'n_wait'],
                ['id' => 'e2', 'source' => 'n_wait',    'target' => 'n_tag'],
            ],
        ]);
    }

    public function test_wait_node_suspends_run_and_schedules_wakeup(): void
    {
        Queue::fake();

        $data = $this->createWorkspaceContext();
        $workspace = $data['workspace'];
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $automation = $this->makeAutomationWithWait($workspace->id, $contact->id);

        $engine = app(AutomationEngine::class);
        $engine->triggerForContact($automation, $contact->id);

        // Initial dispatch should be on 'automation' queue
        Queue::assertPushedOn('automation', ExecuteAutomationRunJob::class);

        // Get the created run and execute it directly (synchronously, queue is still faked)
        $run = AutomationRun::where('automation_id', $automation->id)->first();
        $this->assertNotNull($run, 'AutomationRun should be created by triggerForContact');

        $engine->executeRun($run->fresh());

        // Run should be 'waiting' after hitting the wait node
        $this->assertDatabaseHas('automation_runs', [
            'id' => $run->id,
            'status' => 'waiting',
            'resume_node_id' => 'n_tag',
        ]);

        // Wakeup job should have been dispatched with a delay (total 2 jobs on automation queue)
        Queue::assertPushedOn('automation', ExecuteAutomationRunJob::class);
    }

    public function test_trigger_listener_fires_automation_on_message_received(): void
    {
        Queue::fake();

        $data = $this->createWorkspaceContext();
        $workspace = $data['workspace'];
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $channel = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'display_name' => 'WA',
            'status' => 'active',
        ]);
        $conv = Conversation::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $channel->id,
            'status' => 'open',
        ]);

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Trigger Test',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'nodes' => [['id' => 'n_trigger', 'type' => 'trigger', 'data' => []]],
            'edges' => [],
        ]);

        $message = Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hello',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        MessageReceived::dispatch($message);

        // AutomationTriggerListener should have dispatched ExecuteAutomationRunJob
        Queue::assertPushedOn('automation', ExecuteAutomationRunJob::class);

        $this->assertDatabaseHas('automation_runs', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
        ]);
    }

    public function test_trigger_listener_fires_for_telegram_messages_too(): void
    {
        Queue::fake();

        $data = $this->createWorkspaceContext();
        $workspace = $data['workspace'];
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $channel = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'telegram',
            'provider' => 'telegram_bot',
            'display_name' => 'Bot Telegram',
            'status' => 'active',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $channel->id,
            'external_thread_id' => '123456',
            'status' => 'open',
        ]);
        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Gatilho Telegram',
            'status' => 'active',
            'trigger_type' => 'message.received',
            'trigger_config' => ['keywords' => []],
            'nodes' => [['id' => 'n_trigger', 'type' => 'trigger', 'data' => []]],
            'edges' => [],
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'telegram',
            'type' => 'text',
            'body' => 'Olá pelo Telegram',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        MessageReceived::dispatch($message);

        Queue::assertPushedOn('automation', ExecuteAutomationRunJob::class);
        $run = AutomationRun::where('automation_id', $automation->id)->firstOrFail();
        $this->assertSame('telegram', $run->context['message_channel']);
    }
}
