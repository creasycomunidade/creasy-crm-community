<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Jobs\RunHeartbeatJob;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiAgentTask;
use App\Modules\AI\Models\AiGoal;
use App\Modules\AI\Models\AiHeartbeat;
use App\Modules\AI\Models\AiHeartbeatRun;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Models\AiSkill;
use App\Modules\AI\Services\AgentCompletion;
use App\Modules\AI\Services\AgentGoalContext;
use App\Modules\AI\Services\AgentSkillContext;
use App\Modules\AI\Services\HeartbeatService;
use App\Modules\AI\Services\Llm\LlmResponse;
use App\Modules\AI\Services\LlmGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HeartbeatOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function heartbeat(array $changes = []): AiHeartbeat
    {
        $context = $this->createWorkspaceContext();
        $agent = AiAgent::create(['workspace_id' => $context['workspace']->id, 'slug' => 'test-agent', 'name' => 'Monitor',
            'description' => 'Monitoramento', 'persona' => 'Monitor', 'soul' => 'Objetivo', 'enabled' => true, 'layer' => 'business', 'permission_mode' => 'read_only', 'system_prompt' => 'Analise dados verificados.']);

        return AiHeartbeat::create([...[
            'workspace_id' => $context['workspace']->id, 'created_by' => $context['user']->id, 'agent_id' => $agent->id,
            'name' => 'Acompanhamento', 'instructions' => 'Verifique atrasos.', 'enabled' => true, 'interval_minutes' => 60,
            'timezone' => 'UTC', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'daily_run_limit' => 12, 'max_output_tokens' => 1024,
            'notification_policy' => 'changes', 'next_run_at' => now()->subMinute(),
        ], ...$changes]);
    }

    private function completion(string $status = 'attention', int $times = 1): void
    {
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->times($times)->andReturn(
            new LlmResponse(json_encode(['status' => $status, 'summary' => 'Há uma tarefa atrasada.']), 120, 40, 'test-model', 100)
        );
    }

    public function test_run_records_usage_and_skips_unchanged_data_without_a_second_model_call(): void
    {
        $h = $this->heartbeat();
        $this->completion();
        $service = app(HeartbeatService::class);
        $first = $service->run($h->id);
        $this->assertSame('attention', $first->status);
        $this->assertTrue($first->attention);
        $this->assertSame(120, $first->input_tokens);
        $this->travel(61)->minutes();
        $second = $service->run($h->id);
        $this->assertSame('unchanged', $second->status);
        $this->assertFalse($second->attention);
        $this->assertSame(0, $second->input_tokens);
        $this->assertNull($h->fresh()->lease_token);
    }

    public function test_analytical_routine_runs_again_even_when_snapshot_has_not_changed(): void
    {
        $h = $this->heartbeat(['execution_mode'=>'routine']);
        $this->completion('ok', 2);
        $service = app(HeartbeatService::class);
        $this->assertSame('ok', $service->run($h->id)->status);
        $this->travel(61)->minutes();
        $this->assertSame('ok', $service->run($h->id)->status);
    }

    public function test_authorized_task_action_does_not_duplicate_an_open_follow_up(): void
    {
        $h = $this->heartbeat(['action_mode'=>'create_task']);
        $this->completion('attention', 2);
        $service = app(HeartbeatService::class);
        $service->run($h->id, true);
        $service->run($h->id, true);
        $this->assertSame(1, AiAgentTask::where('workspace_id',$h->workspace_id)->count());
        $task = AiAgentTask::where('workspace_id',$h->workspace_id)->first();
        $this->assertSame('backlog',$task->status);
        $this->assertSame($h->uuid,$task->metadata['heartbeat_uuid']);
    }

    public function test_external_read_failure_is_recorded_without_exposing_credentials(): void
    {
        $h=$this->heartbeat();
        $c=\App\Modules\Integrations\Models\WorkspaceIntegration::create(['workspace_id'=>$h->workspace_id,'provider'=>'todoist','name'=>'Tarefas','credentials'=>['api_key'=>'private-key'],'status'=>'connected']);
        $h->update(['connection_uuid'=>$c->uuid]);
        \Illuminate\Support\Facades\Http::fake(['*'=>\Illuminate\Support\Facades\Http::response([],401)]);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $run=app(HeartbeatService::class)->run($h->id,true);
        $this->assertSame('failed',$run->status);
        $this->assertStringNotContainsString('private-key',$run->summary);
    }

    public function test_disabled_and_future_scheduled_heartbeats_do_not_call_model(): void
    {
        $h = $this->heartbeat(['enabled' => false]);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $service = app(HeartbeatService::class);
        $this->assertNull($service->run($h->id));
        $h->update(['enabled' => true, 'next_run_at' => now()->addDay()]);
        $this->assertNull($service->run($h->id));
    }

    public function test_lease_prevents_concurrent_manual_execution(): void
    {
        $h = $this->heartbeat(['lease_until' => now()->addMinutes(5), 'lease_token' => 'existing']);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertNull(app(HeartbeatService::class)->run($h->id, true));
        $this->assertDatabaseCount('ai_heartbeat_runs', 0);
    }

    public function test_daily_limit_also_applies_to_manual_runs(): void
    {
        $h = $this->heartbeat(['daily_run_limit' => 1]);
        $this->completion('ok');
        $service = app(HeartbeatService::class);
        $service->run($h->id, true);
        $this->assertNull($service->run($h->id, true));
        $this->assertSame('limited', $h->fresh()->last_status);
        $this->assertDatabaseCount('ai_heartbeat_runs', 1);
    }

    public function test_overnight_windows_use_the_starting_weekday(): void
    {
        $h = $this->heartbeat(['weekdays' => [1], 'active_from' => '22:00', 'active_until' => '06:00']);
        $service = app(HeartbeatService::class);
        $this->assertTrue($service->insideWindow($h, Carbon::parse('2026-09-21 23:00:00', 'UTC')));
        $this->assertTrue($service->insideWindow($h, Carbon::parse('2026-09-22 02:00:00', 'UTC')));
        $this->assertFalse($service->insideWindow($h, Carbon::parse('2026-09-22 06:00:00', 'UTC')));
        $this->assertFalse($service->insideWindow($h, Carbon::parse('2026-09-22 23:00:00', 'UTC')));
    }

    public function test_scheduled_run_outside_window_is_postponed_without_tokens(): void
    {
        $h = $this->heartbeat(['weekdays' => [1], 'active_from' => '09:00', 'active_until' => '17:00']);
        $this->travelTo(Carbon::parse('2026-09-23 02:00:00', 'UTC')->timezone(config('app.timezone')));
        $h->update(['next_run_at' => now()->subMinute()]);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertNull(app(HeartbeatService::class)->run($h->id));
        $this->assertTrue($h->fresh()->next_run_at->isFuture(), json_encode([$h->fresh()->toArray(), now()->toIso8601String()]));
    }

    public function test_three_failures_pause_heartbeat_and_hide_provider_secrets(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->times(3)->andThrow(new \RuntimeException('api-key-PRIVATE'));
        for ($i = 0; $i < 3; $i++) {
            app(HeartbeatService::class)->run($h->id, true);
        }
        $this->assertFalse($h->fresh()->enabled);
        $this->assertEquals(3, $h->fresh()->failure_count);
        $this->assertStringNotContainsString('PRIVATE', $h->fresh()->last_summary);
        $this->assertDatabaseCount('ai_heartbeat_runs', 3);
        $this->assertSame(3, AiHeartbeatRun::where('attention', true)->count());
    }

    public function test_invalid_model_json_is_a_failure_but_usage_is_preserved(): void
    {
        $h = $this->heartbeat();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn(new LlmResponse('Invalid JSON', 70, 20, 'test', 10));
        $run = app(HeartbeatService::class)->run($h->id, true);
        $this->assertSame('failed', $run->status);
        $this->assertSame(70, $run->input_tokens);
    }

    public function test_removed_responsible_user_cannot_continue_monitoring_workspace(): void
    {
        $h = $this->heartbeat();
        $user = $h->creator;
        $user->update(['status' => 'inactive']);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertNull(app(HeartbeatService::class)->run($h->id, true));
        $this->assertFalse($h->fresh()->enabled);
    }

    public function test_snapshot_excludes_other_workspaces_and_measures_linked_tasks(): void
    {
        $h = $this->heartbeat();
        $other = $this->heartbeat();
        $goal = AiGoal::create(['workspace_id' => $h->workspace_id, 'title' => 'Entregas', 'metric' => 'completed_tasks', 'target_value' => 3, 'unit' => 'tarefas']);
        $h->update(['goal_id' => $goal->id]);
        AiAgentTask::create(['workspace_id' => $h->workspace_id, 'goal_id' => $goal->id, 'title' => 'Tarefa local', 'status' => 'completed']);
        AiAgentTask::create(['workspace_id' => $other->workspace_id, 'title' => 'SEGREDO DE OUTRA EMPRESA', 'status' => 'failed']);
        $snapshot = app(HeartbeatService::class)->snapshot($h->fresh(['goal', 'agent']));
        $this->assertEquals(1, $snapshot['goal']['current']);
        $this->assertStringNotContainsString('SEGREDO', json_encode($snapshot));
    }

    public function test_failure_only_policy_does_not_highlight_successful_attention_result(): void
    {
        $h = $this->heartbeat(['notification_policy' => 'failures']);
        $this->completion();
        $this->assertFalse(app(HeartbeatService::class)->run($h->id, true)->attention);
    }

    public function test_foreign_workspace_cannot_mutate_heartbeat(): void
    {
        $h = $this->heartbeat();
        $other = $this->createWorkspaceContext();
        $this->actingAs($other['user'])->patchJson(route('client.ai.operations.heartbeats.update', $h->uuid), ['enabled' => false])->assertNotFound();
        $this->assertTrue($h->fresh()->enabled);
    }

    public function test_foreign_agent_cannot_be_attached_to_a_heartbeat(): void
    {
        $h = $this->heartbeat();
        $other = $this->heartbeat();
        $this->actingAs($h->creator)->patchJson(route('client.ai.operations.heartbeats.update', $h->uuid), ['agent_uuid' => $other->agent->uuid])->assertUnprocessable();
        $this->assertEquals($h->agent_id, $h->fresh()->agent_id);
    }

    public function test_manual_dispatch_uses_isolated_queue(): void
    {
        Queue::fake();
        $h = $this->heartbeat();
        $this->actingAs($h->creator)->post(route('client.ai.operations.heartbeats.run', $h->uuid))->assertRedirect();
        Queue::assertPushed(RunHeartbeatJob::class, fn ($job) => $job->heartbeatId === $h->id && $job->manual && $job->queue === 'ai-heartbeats' && $job->connection === 'ai-heartbeats');
    }

    public function test_goal_creation_links_tasks_and_does_not_move_tasks_from_other_goals(): void
    {
        $h = $this->heartbeat();
        $task = AiAgentTask::create(['workspace_id' => $h->workspace_id, 'title' => 'Entrega', 'status' => 'completed']);
        $payload = ['title' => 'Concluir entregas', 'status' => 'active', 'metric' => 'completed_tasks', 'target_value' => 2, 'current_value' => 0, 'unit' => 'tarefas', 'task_uuids' => [$task->uuid]];
        $this->actingAs($h->creator)->post(route('client.ai.operations.goals.store'), $payload)->assertRedirect();
        $goal = AiGoal::firstOrFail();
        $this->assertEquals(1, $goal->measuredValue());
        $this->postJson(route('client.ai.operations.goals.store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('ai_goals', 1);
        $this->delete(route('client.ai.operations.goals.destroy', $goal->uuid))->assertRedirect();
        $this->assertNull($task->fresh()->goal_id);
    }

    public function test_execution_settings_are_validated_and_used_by_shared_completion(): void
    {
        $h = $this->heartbeat();
        $this->actingAs($h->creator)->putJson(route('client.ai.operations.agents.settings', $h->agent->uuid), ['temperature' => 2, 'max_output_tokens' => 999999])->assertUnprocessable();
        $this->put(route('client.ai.operations.agents.settings', $h->agent->uuid), ['temperature' => 0.2, 'max_output_tokens' => 512])->assertRedirect();
        $this->mock(LlmGateway::class)->shouldReceive('chat')->once()->withArgs(fn ($wid, $messages, $options) => $wid === $h->workspace_id && $options['max_tokens'] === 512 && $options['temperature'] === 0.2)
            ->andReturn(new LlmResponse('ok', 1, 1, 'test', 1));
        app(AgentCompletion::class)->chat($h->agent->fresh(), [], 2048);
    }

    public function test_operations_page_does_not_expose_provider_credentials(): void
    {
        $h = $this->heartbeat();
        AiProviderConfig::create(['workspace_id' => $h->workspace_id, 'provider' => 'openai', 'enabled' => true, 'credentials' => ['api_key' => 'PRIVATE-CREDENTIAL']]);
        $this->actingAs($h->creator)->get(route('client.ai.operations.index'))->assertOk()->assertDontSee('PRIVATE-CREDENTIAL')
            ->assertInertia(fn ($page) => $page->component('AI/Operations/Index')->where('setupRequired', false));
    }

    public function test_skill_matching_handles_accents_and_preserves_assigned_priority_beyond_thirty_skills(): void
    {
        $h = $this->heartbeat();
        for ($i = 0; $i < 35; $i++) {
            AiSkill::create(['workspace_id' => $h->workspace_id, 'name' => 'Auto '.$i, 'slug' => 'auto-'.$i, 'instructions' => 'Instruções', 'enabled' => true, 'auto_activate' => true, 'trigger_terms' => ['teste']]);
        }
        $assigned = AiSkill::create(['workspace_id' => $h->workspace_id, 'name' => 'Z Atribuída', 'slug' => 'assigned', 'instructions' => 'Prioridade', 'enabled' => true, 'auto_activate' => false]);
        $h->agent->assignedSkills()->attach($assigned);
        $selected = app(AgentSkillContext::class)->selected($h->agent, 'teste');
        $this->assertEquals($assigned->id, $selected->first()->id);
        $this->assertCount(6, $selected);
        $skill = AiSkill::create(['workspace_id' => $h->workspace_id, 'name' => 'Cobrança', 'slug' => 'cobranca', 'instructions' => 'Cobre corretamente', 'enabled' => true, 'auto_activate' => true, 'trigger_terms' => ['cobrança']]);
        $this->assertTrue(app(AgentSkillContext::class)->selected($h->agent, 'Verificar cobranca')->contains('id', $skill->id));
    }

    public function test_staff_cannot_access_operations_or_start_a_heartbeat(): void
    {
        $h = $this->heartbeat();
        $staff = User::factory()->create(['client_id' => $h->creator->client_id, 'workspace_id' => $h->workspace_id, 'client_role' => 'staff', 'status' => 'active']);
        Workspace::find($h->workspace_id)->members()->syncWithoutDetaching([$staff->id => ['role' => 'member']]);
        $this->actingAs($staff)->get(route('client.ai.operations.index'))->assertForbidden();
        $this->post(route('client.ai.operations.heartbeats.run', $h->uuid))->assertForbidden();
    }

    public function test_interrupted_runs_are_recovered_and_three_interruptions_pause_monitoring(): void
    {
        $h = $this->heartbeat(['failure_count' => 2, 'lease_until' => now()->subMinute()]);
        $run = $h->runs()->create(['workspace_id' => $h->workspace_id, 'status' => 'running']);
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertNull(app(HeartbeatService::class)->run($h->id));
        $this->assertFalse($h->fresh()->enabled);
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertTrue($run->fresh()->attention);
    }

    public function test_goal_context_respects_agent_and_workspace_scope(): void
    {
        $h = $this->heartbeat();
        $other = $this->heartbeat();
        AiGoal::create(['workspace_id' => $h->workspace_id, 'agent_id' => $h->agent_id, 'title' => 'Meta permitida']);
        AiGoal::create(['workspace_id' => $other->workspace_id, 'agent_id' => $other->agent_id, 'title' => 'Meta privada']);
        $context = app(AgentGoalContext::class)->build($h->agent);
        $this->assertStringContainsString('Meta permitida', $context);
        $this->assertStringNotContainsString('Meta privada', $context);
    }
}
