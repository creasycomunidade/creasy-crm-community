<?php

namespace Tests\Feature\AI;

use App\Models\WorkspaceFile;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiHeartbeat;
use App\Modules\AI\Services\AgentCompletion;
use App\Modules\AI\Services\HeartbeatService;
use App\Modules\AI\Services\Llm\LlmResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeartbeatExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function heartbeat(): AiHeartbeat
    {
        $c = $this->createWorkspaceContext();
        $agent = AiAgent::create(['workspace_id' => $c['workspace']->id, 'slug' => 'executor', 'name' => 'Executor',
            'description' => 'Execução', 'persona' => 'Executor', 'soul' => 'Objetivo', 'enabled' => true, 'layer' => 'business', 'permission_mode' => 'approval_required', 'system_prompt' => 'Execute o objetivo.']);

        return AiHeartbeat::create(['workspace_id' => $c['workspace']->id, 'created_by' => $c['user']->id, 'agent_id' => $agent->id,
            'name' => 'Execução', 'instructions' => 'Crie um arquivo de plano e depois registre a conclusão.', 'enabled' => true,
            'action_mode' => 'execute', 'interval_minutes' => 60, 'timezone' => 'UTC', 'daily_run_limit' => 12,
            'max_output_tokens' => 1024, 'notification_policy' => 'changes', 'next_run_at' => now()->subMinute()]);
    }

    private function reply(array $data): LlmResponse
    {
        return new LlmResponse(json_encode($data), 30, 10, 'test-model', 20);
    }

    public function test_executes_records_verifies_and_pauses_at_completion(): void
    {
        $h = $this->heartbeat();
        $calls = 0;
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->twice()->andReturnUsing(function ($agent, $messages) use ($h, &$calls) {
            if (++$calls === 1) return $this->reply(['status' => 'action', 'summary' => 'Criar plano', 'action' => ['type' => 'create_workspace_file', 'name' => 'plano.md', 'content' => '# Plano\nEtapas definidas.']]);
            $this->assertDatabaseHas('workspace_files', ['workspace_id' => $h->workspace_id, 'name' => 'plano.md']);
            $steps = $h->fresh()->execution_state['steps'];
            $this->assertStringContainsString('plano.md', $messages[0]['content']);
            return $this->reply(['status' => 'completed', 'summary' => 'Plano criado e conferido.', 'evidence' => array_keys($steps)]);
        });
        $run = app(HeartbeatService::class)->run($h->id);
        $this->assertSame('completed', $run->status);
        $this->assertFalse($h->fresh()->enabled);
        $this->assertCount(1, $run->execution_log);
        $this->assertSame(60, $run->input_tokens);
        $this->assertSame('completed', $h->fresh()->execution_state['status']);
    }

    public function test_repeated_action_is_not_executed_twice(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->twice()->andReturn($this->reply([
            'status' => 'action', 'summary' => 'Criar plano', 'action' => ['type' => 'create_workspace_file', 'name' => 'plano.md', 'content' => 'Plano'],
        ]));
        $run = app(HeartbeatService::class)->run($h->id);
        $this->assertSame('attention', $run->status);
        $this->assertFalse($h->fresh()->enabled);
        $this->assertSame(1, WorkspaceFile::where('workspace_id', $h->workspace_id)->count());
    }

    public function test_external_or_destructive_action_stops_without_execution(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->reply([
            'status' => 'action', 'summary' => 'Cobrar cliente', 'action' => ['type' => 'send_message', 'body' => 'Cobrança'],
        ]));
        $run = app(HeartbeatService::class)->run($h->id);
        $this->assertSame('attention', $run->status);
        $this->assertFalse($h->fresh()->enabled);
        $this->assertDatabaseCount('messages', 0);
        $this->assertEmpty($run->execution_log);
    }

    public function test_cannot_claim_completion_without_successful_evidence(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->reply([
            'status' => 'completed', 'summary' => 'Concluído', 'evidence' => ['inventado'],
        ]));
        $this->assertSame('attention', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertFalse($h->fresh()->enabled);
    }

    public function test_revocation_during_model_call_prevents_mutation(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturnUsing(function () use ($h) {
            $h->agent->update(['permission_mode' => 'read_only']);
            return $this->reply(['status' => 'action', 'summary' => 'Criar plano', 'action' => ['type' => 'create_workspace_file', 'name' => 'plano.md', 'content' => 'Plano']]);
        });
        $this->assertSame('failed', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertSame(0, WorkspaceFile::where('workspace_id', $h->workspace_id)->count());
    }

    public function test_cross_workspace_target_is_rejected(): void
    {
        $h = $this->heartbeat();
        $other = $this->createWorkspaceContext();
        $file = WorkspaceFile::create(['workspace_id' => $other['workspace']->id, 'created_by' => $other['user']->id, 'name' => 'privado.md', 'content' => 'Original', 'extension' => 'md', 'mime_type' => 'text/markdown', 'size' => 8]);
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->reply([
            'status' => 'action', 'summary' => 'Editar plano', 'action' => ['type' => 'update_workspace_file', 'file_uuid' => $file->uuid, 'content' => 'Alterado'],
        ]));
        $this->assertSame('failed', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertSame('Original', $file->fresh()->content);
        $this->assertEmpty($h->fresh()->execution_state);
    }
    public function test_continues_across_cycles_with_persisted_progress_and_daily_limit(): void
    {
        $h = $this->heartbeat();
        $calls = 0;
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->times(4)->andReturnUsing(function ($agent, $messages) use ($h, &$calls) {
            ++$calls;
            if ($calls <= 3) return $this->reply(['status' => 'action', 'summary' => 'Criar etapa '.$calls,
                'action' => ['type' => 'create_agent_task', 'title' => 'Etapa '.$calls]]);
            $this->assertStringContainsString('Etapa 3', $messages[1]['content']);
            return $this->reply(['status' => 'completed', 'summary' => 'Três etapas registradas.', 'evidence' => array_keys($h->fresh()->execution_state['steps'])]);
        });
        $service = app(HeartbeatService::class);
        $first = $service->run($h->id);
        $this->assertSame('ok', $first->status);
        $this->assertTrue($h->fresh()->enabled);
        $this->assertCount(3, $first->execution_log);
        $h->update(['daily_run_limit' => 1]);
        $this->assertNull($service->run($h->id, true));
        $h->update(['daily_run_limit' => 12, 'next_run_at' => now()]);
        $this->travel(6)->minutes();
        $this->assertSame('completed', $service->run($h->id)->status);
        $this->assertDatabaseCount('ai_agent_tasks', 3);
        $h->update(['enabled' => true]);
        $this->assertSame('completed', $service->run($h->id, true)->status);
        $this->assertDatabaseCount('ai_agent_tasks', 3);
    }

    public function test_pausing_while_model_is_running_is_not_undone_by_error_handler(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturnUsing(function () use ($h) {
            $h->update(['enabled' => false]);
            return $this->reply(['status' => 'action', 'summary' => 'Criar plano', 'action' => ['type' => 'create_workspace_file', 'name' => 'plano.md', 'content' => 'Plano']]);
        });
        $this->assertSame('failed', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertFalse($h->fresh()->enabled);
        $this->assertSame(0, WorkspaceFile::where('workspace_id', $h->workspace_id)->count());
    }

    public function test_action_and_journal_roll_back_together(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->reply([
            'status' => 'action', 'summary' => 'Criar plano', 'action' => ['type' => 'create_workspace_file', 'name' => 'plano.md', 'content' => 'Plano'],
        ]));
        $this->mock(\App\Modules\AI\Services\AgentWorkspaceActionService::class, function ($mock) use ($h) {
            $mock->shouldReceive('context')->once()->andReturn('Contexto');
            $mock->shouldReceive('execute')->once()->andReturnUsing(function () use ($h) {
                WorkspaceFile::create(['workspace_id' => $h->workspace_id, 'created_by' => $h->created_by, 'name' => 'parcial.md', 'content' => 'Parcial', 'extension' => 'md', 'mime_type' => 'text/markdown', 'size' => 7]);
                throw new \RuntimeException('Interrupted write');
            });
        });
        $this->assertSame('failed', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertSame(0, WorkspaceFile::where('workspace_id', $h->workspace_id)->count());
        $this->assertEmpty($h->fresh()->execution_state);
    }

    public function test_read_only_agent_never_calls_model_or_executes_actions(): void
    {
        $h = $this->heartbeat();
        $h->agent->update(['permission_mode' => 'read_only']);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertSame('failed', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertEmpty($h->fresh()->execution_state);
    }

    public function test_contact_consent_cannot_be_changed_autonomously(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->reply([
            'status' => 'action', 'summary' => 'Cadastrar', 'action' => ['type' => 'create_contact', 'first_name' => 'Teste', 'opt_in_whatsapp' => true],
        ]));
        $this->assertSame('attention', app(HeartbeatService::class)->run($h->id)->status);
        $this->assertDatabaseCount('contacts', 0);
    }

}
