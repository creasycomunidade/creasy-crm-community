<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Jobs\RunScheduledAgentTasksJob;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiAgentTask;
use App\Modules\AI\Services\AgentCatalog;
use App\Modules\AI\Services\AgentRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentTaskKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_and_move_a_task_in_its_workspace(): void
    {
        $context = $this->createWorkspaceContext();

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.ai.agent-tasks.store'), [
                'title' => 'Preparar contrato do Cliente A',
                'description' => 'Criar o rascunho usando o modelo comercial.',
                'agent_uuid' => null,
                'status' => 'backlog',
                'priority' => 'high',
                'progress' => 0,
                'due_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect();

        $task = AiAgentTask::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertSame('backlog', $task->status);
        $this->assertSame('high', $task->priority);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->patch(route('client.ai.agent-tasks.update', $task), ['status' => 'completed'])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_from_another_workspace_is_forbidden(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $task = AiAgentTask::create([
            'workspace_id' => $first['workspace']->id,
            'created_by' => $first['user']->id,
            'title' => 'Tarefa privada',
            'status' => 'backlog',
            'priority' => 'normal',
        ]);

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->patch(route('client.ai.agent-tasks.update', $task), ['status' => 'review'])
            ->assertForbidden();

        $this->assertSame('backlog', $task->fresh()->status);
    }

    public function test_recurring_task_without_selected_agent_is_assigned_to_maestro(): void
    {
        $context = $this->createWorkspaceContext();

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.ai.agent-tasks.store'), [
                'title' => 'Relatório das tarefas',
                'description' => 'Envie as tarefas para o WhatsApp 5547988222565.',
                'agent_uuid' => null,
                'status' => 'backlog',
                'priority' => 'high',
                'recurrence_enabled' => true,
                'recurrence_unit' => 'day',
                'recurrence_interval' => 1,
                'next_run_at' => now()->addMinute()->toIso8601String(),
            ])
            ->assertRedirect();

        $task = AiAgentTask::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertSame('oracle', $task->agent?->slug);
        $this->assertTrue($task->recurrence_enabled);
    }

    public function test_due_recurring_task_falls_back_to_maestro_and_schedules_next_run(): void
    {
        $context = $this->createWorkspaceContext();
        $task = AiAgentTask::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'title' => 'Relatório das tarefas',
            'description' => 'Envie as tarefas para o WhatsApp 5547988222565.',
            'status' => 'backlog',
            'priority' => 'high',
            'recurrence_enabled' => true,
            'recurrence_unit' => 'day',
            'recurrence_interval' => 1,
            'next_run_at' => now()->subMinute(),
        ]);
        $runner = Mockery::mock(AgentRunner::class);
        $runner->shouldReceive('run')
            ->once()
            ->withArgs(fn (AiAgent $agent, $user, string $instruction) => $agent->slug === 'oracle'
                && $user->is($context['user'])
                && str_contains($instruction, '5547988222565'))
            ->andReturn(['reply' => 'Relatório enviado.', 'action_results' => [['ok' => true]]]);

        (new RunScheduledAgentTasksJob)->handle($runner, app(AgentCatalog::class));

        $task->refresh();
        $this->assertSame('oracle', $task->agent?->slug);
        $this->assertSame('review', $task->status);
        $this->assertSame(1, $task->recurrence_run_count);
        $this->assertTrue($task->recurrence_enabled);
        $this->assertTrue($task->next_run_at->isFuture());
    }
}
