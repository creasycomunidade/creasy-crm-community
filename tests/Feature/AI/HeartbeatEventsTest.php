<?php

namespace Tests\Feature\AI;

use App\Models\InternalNote;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiAgentActionConfirmation;
use App\Modules\AI\Models\AiAgentTask;
use App\Modules\AI\Models\AiGoal;
use App\Modules\AI\Models\AiHeartbeat;
use App\Modules\AI\Services\AgentCompletion;
use App\Modules\AI\Services\AgentOperationsActions;
use App\Modules\AI\Services\HeartbeatEvents;
use App\Modules\AI\Services\HeartbeatService;
use App\Modules\AI\Services\Llm\LlmResponse;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HeartbeatEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-23 12:00:00',config('app.timezone')));
    }

    private function context(): array
    {
        $c = $this->createWorkspaceContext();
        $c['agent'] = AiAgent::create(['workspace_id' => $c['workspace']->id, 'slug' => 'monitor', 'description' => 'Monitor', 'persona' => 'Monitor', 'soul' => 'Analise', 'name' => 'Monitor', 'enabled' => true, 'layer' => 'business', 'permission_mode' => 'approval_required', 'system_prompt' => 'Analise', 'execution_settings' => ['heartbeat_autonomy' => true]]);
        $c['hb'] = AiHeartbeat::create(['workspace_id' => $c['workspace']->id, 'agent_id' => $c['agent']->id, 'created_by' => $c['user']->id, 'name' => 'Monitor', 'instructions' => 'Verifique', 'enabled' => true, 'interval_minutes' => 60, 'timezone' => 'UTC', 'daily_run_limit' => 2, 'max_output_tokens' => 1024, 'next_run_at' => now()->addHour(), 'wake_triggers' => HeartbeatEvents::KINDS]);

        return $c;
    }

    private function emit(array $c, int $id = 1): void
    {
        app(HeartbeatEvents::class)->emit($c['workspace']->id, 'new_task', ['id' => $id], $c['agent']->id);
    }

    private function response(): LlmResponse
    {
        return new LlmResponse('{"status":"ok","summary":"Verificado"}', 10, 10, 'test', 10);
    }

    public function test_event_followed_by_unchanged_schedule_does_not_charge_model_again(): void
    {
        $a=$this->context();$this->emit($a);$this->travel(31)->seconds();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->response());
        $this->assertSame('ok',app(HeartbeatService::class)->run($a['hb']->id)->status);
        $this->travel(61)->minutes();
        $this->assertSame('unchanged',app(HeartbeatService::class)->run($a['hb']->id)->status);
    }

    public function test_agent_disabled_during_analysis_cannot_create_follow_up_task(): void
    {
        $a=$this->context();$a['hb']->update(['action_mode'=>'create_task','next_run_at'=>now()]);
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturnUsing(function()use($a){
            $a['agent']->update(['enabled'=>false]);
            return new LlmResponse('{"status":"attention","summary":"Revisar"}',10,10,'test',10);
        });
        $this->assertSame('failed',app(HeartbeatService::class)->run($a['hb']->id)->status);
        $this->assertDatabaseCount('ai_agent_tasks',0);
    }

    public function test_expired_execution_cannot_overwrite_new_lease_or_schedule(): void
    {
        $a=$this->context();$this->emit($a);$this->travel(31)->seconds();$next=now()->addHours(3);
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturnUsing(function()use($a,$next){
            $a['hb']->update(['lease_token'=>'replacement','lease_until'=>now()->addMinutes(10),'next_run_at'=>$next,'last_status'=>'running']);
            return $this->response();
        });
        $this->assertSame('failed',app(HeartbeatService::class)->run($a['hb']->id)->status);
        $this->assertSame('replacement',$a['hb']->fresh()->lease_token);
        $this->assertTrue($a['hb']->fresh()->next_run_at->eq($next));
        $this->assertDatabaseCount('ai_heartbeat_events',1);
    }

    public function test_removing_a_trigger_discards_its_pending_events(): void
    {
        $this->withoutVite();$a=$this->context();$this->emit($a);
        $this->actingAs($a['user'])->patch(route('client.ai.operations.heartbeats.update',$a['hb']),['wake_triggers'=>[]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ai_heartbeat_events',0);
    }

    public function test_update_rechecks_lease_instead_of_trusting_stale_route_model(): void
    {
        $a=$this->context();$stale=$a['hb'];
        AiHeartbeat::whereKey($stale->id)->update(['lease_token'=>'active','lease_until'=>now()->addMinutes(10)]);
        $request=\Illuminate\Http\Request::create('/','PATCH',['name'=>'Must not change']);$request->setUserResolver(fn()=>$a['user']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Modules\AI\Http\Controllers\AiOperationsController::class)->updateHeartbeat($request,$stale);
    }

    public function test_events_are_scoped_grouped_and_do_not_reactivate_paused_heartbeats(): void
    {
        $a = $this->context();
        $b = $this->context();
        $this->emit($a);
        $this->emit($a, 2);
        $this->assertDatabaseCount('ai_heartbeat_events', 1);
        $this->assertSame(2, json_decode(DB::table('ai_heartbeat_events')->value('payload'), true)['id']);
        $this->assertTrue($a['hb']->fresh()->next_run_at->lte(now()->addSeconds(31)));
        $this->assertTrue($b['hb']->fresh()->next_run_at->gt(now()->addMinutes(50)));
        $a['hb']->update(['enabled' => false]);
        $this->emit($a, 3);
        $this->assertSame(2, json_decode(DB::table('ai_heartbeat_events')->value('payload'), true)['id']);
    }

    public function test_event_burst_keeps_bounded_samples_and_total_count(): void
    {
        $a = $this->context();
        for ($i = 1; $i <= 12; $i++) {
            $this->emit($a, $i);
        }
        $payload = json_decode(DB::table('ai_heartbeat_events')->value('payload'), true);
        $this->assertSame(12, $payload['batch_count']);
        $this->assertCount(5, $payload['recent_samples']);
        $this->assertSame(8, $payload['recent_samples'][0]['id']);
    }

    public function test_event_run_records_reason_and_consumes_only_captured_versions(): void
    {
        $a = $this->context();
        $this->emit($a);
        $this->travel(31)->seconds();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturnUsing(function () use ($a) {
            $this->emit($a, 2);

            return $this->response();
        });
        $run = app(HeartbeatService::class)->run($a['hb']->id);
        $this->assertSame('event', $run->trigger);
        $this->assertSame(['new_task'], $run->trigger_context);
        $this->assertDatabaseCount('ai_heartbeat_events', 1);
        $this->assertSame(2, json_decode(DB::table('ai_heartbeat_events')->value('payload'), true)['id']);
        $this->assertTrue($a['hb']->fresh()->next_run_at->lte(now()->addMinutes(5)));
    }

    public function test_success_acknowledges_event_and_failure_keeps_it_for_retry(): void
    {
        $a = $this->context();
        $this->emit($a);
        $this->travel(31)->seconds();
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andThrow(new \RuntimeException('private'));
        $run = app(HeartbeatService::class)->run($a['hb']->id);
        $this->assertSame('failed', $run->status);
        $this->assertDatabaseCount('ai_heartbeat_events', 1);
        $due = $a['hb']->fresh()->next_run_at;
        $this->emit($a, 2);
        $this->assertTrue($a['hb']->fresh()->next_run_at->eq($due));
        $this->mock(AgentCompletion::class)->shouldReceive('chat')->once()->andReturn($this->response());
        app(HeartbeatService::class)->run($a['hb']->id, true);
        $this->assertDatabaseCount('ai_heartbeat_events', 0);
    }

    public function test_event_does_not_bypass_daily_limit_or_active_window(): void
    {
        $a = $this->context();
        $a['hb']->update(['daily_run_limit' => 1]);
        $a['hb']->runs()->create(['workspace_id' => $a['workspace']->id, 'trigger' => 'manual', 'status' => 'ok']);
        $this->emit($a);
        $this->travel(31)->seconds();
        $this->mock(AgentCompletion::class)->shouldNotReceive('chat');
        $this->assertNull(app(HeartbeatService::class)->run($a['hb']->id));
        $due = $a['hb']->fresh()->next_run_at;
        $this->emit($a, 2);
        $this->assertTrue($a['hb']->fresh()->next_run_at->eq($due));
        $b = $this->context();
        $b['hb']->update(['weekdays' => [now()->addDay()->dayOfWeekIso]]);
        $this->emit($b);
        $this->travel(31)->seconds();
        $this->assertNull(app(HeartbeatService::class)->run($b['hb']->id));
        $this->assertCount(1, app(HeartbeatEvents::class)->pending($b['hb']));
    }

    public function test_real_task_and_approval_observers_emit_and_rollback_does_not(): void
    {
        $a = $this->context();
        DB::beginTransaction();
        AiAgentTask::create(['workspace_id' => $a['workspace']->id, 'agent_id' => $a['agent']->id, 'title' => 'Rollback', 'status' => 'backlog']);
        DB::rollBack();
        $this->assertDatabaseCount('ai_heartbeat_events', 0);
        $t = AiAgentTask::create(['workspace_id' => $a['workspace']->id, 'agent_id' => $a['agent']->id, 'title' => 'Teste', 'status' => 'awaiting_approval']);
        $this->assertDatabaseHas('ai_heartbeat_events', ['kind' => 'new_task']);
        $t->update(['status' => 'queued']);
        $this->assertDatabaseHas('ai_heartbeat_events', ['kind' => 'approval_decision']);
        $confirmation = AiAgentActionConfirmation::create(['workspace_id' => $a['workspace']->id, 'agent_id' => $a['agent']->id, 'user_id' => $a['user']->id, 'payload' => ['secret' => 'never copy'], 'summary' => 'Confirme', 'status' => 'pending', 'expires_at' => now()->addMinutes(30)]);
        $confirmation->update(['status' => 'cancelled']);
        $this->assertStringNotContainsString('secret', DB::table('ai_heartbeat_events')->where('kind', 'approval_decision')->value('payload'));
    }

    public function test_conversation_inbound_message_and_note_mentions_emit_real_events(): void
    {
        $a = $this->context();
        $wid = $a['workspace']->id;
        $account = ChannelAccount::create(['workspace_id' => $wid, 'channel' => 'whatsapp', 'provider' => 'evolution', 'credentials' => [], 'display_name' => 'Teste', 'status' => 'active']);
        $contact = Contact::factory()->create(['workspace_id' => $wid]);
        $c = Conversation::create(['workspace_id' => $wid, 'channel_account_id' => $account->id, 'contact_id' => $contact->id, 'status' => 'open']);
        $this->assertDatabaseHas('ai_heartbeat_events', ['kind' => 'new_conversation']);
        Message::create(['conversation_id' => $c->id, 'direction' => 'inbound', 'channel' => 'whatsapp', 'type' => 'text', 'body' => 'Preciso de atendimento']);
        $this->assertDatabaseHas('ai_heartbeat_events', ['kind' => 'inbound_message']);
        InternalNote::create(['conversation_id' => $c->id, 'user_id' => $a['user']->id, 'body' => '@Monitor verifique', 'mentioned_user_ids' => [$a['user']->id]]);
        $this->assertDatabaseHas('ai_heartbeat_events', ['kind' => 'mention']);
        $version = DB::table('ai_heartbeat_events')->where('kind', 'inbound_message')->value('version');
        Message::create(['conversation_id' => $c->id, 'direction' => 'outbound', 'channel' => 'whatsapp', 'type' => 'text', 'body' => 'Resposta']);
        $this->assertSame($version, DB::table('ai_heartbeat_events')->where('kind', 'inbound_message')->value('version'));
    }

    public function test_admin_can_configure_triggers_and_autonomy_but_other_workspace_cannot(): void
    {
        $this->withoutVite();
        $a = $this->context();
        $b = $this->context();
        $this->actingAs($a['user'])->patch(route('client.ai.operations.heartbeats.update', $a['hb']), ['wake_triggers' => ['mention', 'new_conversation']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['mention', 'new_conversation'], $a['hb']->fresh()->wake_triggers);
        $this->actingAs($a['user'])->put(route('client.ai.operations.agents.settings', $a['agent']), ['heartbeat_autonomy' => true, 'temperature' => 0.3, 'max_output_tokens' => 1024])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($b['user'])->patch(route('client.ai.operations.heartbeats.update', $a['hb']), ['wake_triggers' => []])->assertNotFound();
    }

    public function test_heartbeat_generated_tasks_do_not_trigger_feedback_loop(): void
    {
        $a = $this->context();
        AiAgentTask::create(['workspace_id' => $a['workspace']->id, 'agent_id' => $a['agent']->id, 'title' => 'Revisar', 'status' => 'backlog', 'metadata' => ['heartbeat_uuid' => $a['hb']->uuid]]);
        $this->assertDatabaseCount('ai_heartbeat_events', 0);
    }

    public function test_autonomous_creation_is_active_idempotent_and_can_be_revoked(): void
    {
        $a = $this->context();
        $action = ['type' => 'create_heartbeat', 'name' => 'Novo', 'instructions' => 'Analise riscos', 'interval_minutes' => 60, 'wake_triggers' => ['mention']];
        $svc = app(AgentOperationsActions::class);
        $r = $svc->execute($a['agent'], $a['user'], 'Organize nosso trabalho', $action);
        $h = AiHeartbeat::where('uuid', $r['heartbeat_uuid'])->firstOrFail();
        $this->assertTrue($h->enabled);
        $this->assertSame($h->uuid, $svc->execute($a['agent'], $a['user'], 'Organize', $action)['heartbeat_uuid']);
        $a['agent']->update(['execution_settings' => ['heartbeat_autonomy' => false]]);
        $this->expectException(HttpException::class);
        $svc->execute($a['agent'], $a['user'], 'Organize', $action);
    }

    public function test_autonomy_does_not_allow_foreign_workspace_or_read_only_actor(): void
    {
        $a = $this->context();
        $b = $this->context();
        $this->expectException(HttpException::class);
        app(AgentOperationsActions::class)->execute($a['agent'], $b['user'], 'Crie heartbeat', ['type' => 'create_heartbeat']);
    }

    public function test_autonomous_agent_creates_goal_monitor_without_chat_and_does_not_duplicate(): void
    {
        $a = $this->context();
        $goal = AiGoal::create(['workspace_id' => $a['workspace']->id, 'created_by' => $a['user']->id, 'agent_id' => $a['agent']->id, 'title' => 'Meta', 'status' => 'active', 'target_value' => 10]);
        $h = AiHeartbeat::where('goal_id', $goal->id)->firstOrFail();
        $this->assertTrue($h->enabled);
        $goal->update(['current_value' => 2]);
        $this->assertSame(1, AiHeartbeat::where('goal_id', $goal->id)->count());
        $a['agent']->update(['permission_mode' => 'read_only']);
        $g = AiGoal::create(['workspace_id' => $a['workspace']->id, 'created_by' => $a['user']->id, 'agent_id' => $a['agent']->id, 'title' => 'Outra', 'status' => 'active', 'target_value' => 10]);
        $this->assertFalse(AiHeartbeat::where('goal_id', $g->id)->exists());
    }
}

