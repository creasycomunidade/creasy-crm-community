<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiGoal;
use App\Modules\AI\Models\AiHeartbeat;
use App\Modules\AI\Models\AiMission;
use App\Modules\AI\Services\AgentIntegrationReader;
use App\Modules\AI\Services\AgentOperationsActions;
use App\Modules\AI\Services\WorkspaceResourceCatalog;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\WorkspaceConnectionTester;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkspaceResourceCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_catalog_install_is_isolated_idempotent_and_preserves_personalization(): void
    {
        $a = $this->createWorkspaceContext();
        $b = $this->createWorkspaceContext();
        $service = app(WorkspaceResourceCatalog::class);
        $item = $service->install($a['workspace']->id, 'agent:atlas-project');
        $agent = AiAgent::where('uuid', $item->metadata['agent_uuid'])->firstOrFail();
        $agent->update(['name' => 'Meu especialista']);
        $this->assertSame($item->id, $service->install($a['workspace']->id, 'agent:atlas-project')->id);
        $this->assertSame('Meu especialista', $agent->fresh()->name);
        $this->assertFalse($agent->enabled);
        $service->install($b['workspace']->id, 'agent:atlas-project');
        $this->assertDatabaseCount('ai_library_items', 2);
        $this->actingAs($b['user'])->get(route('client.ai.library.item', $item))->assertNotFound();
    }

    public function test_template_creates_a_separate_document_and_preserves_template(): void
    {
        $a = $this->createWorkspaceContext();
        $item = app(WorkspaceResourceCatalog::class)->install($a['workspace']->id, 'template:morning-briefing');
        $this->actingAs($a['user'])->post(route('client.ai.library.document', $item), ['name' => 'Meu relatório', 'content' => '# Relatório preenchido'])->assertRedirect();
        $this->assertDatabaseHas('workspace_files', ['workspace_id' => $a['workspace']->id, 'name' => 'Meu relatório.md', 'content' => '# Relatório preenchido']);
        $this->assertNotSame('# Relatório preenchido', $item->fresh()->content);
    }

    public function test_project_cannot_link_to_another_workspace_mission(): void
    {
        $a = $this->createWorkspaceContext();
        $b = $this->createWorkspaceContext();
        $mission = AiMission::create(['workspace_id' => $b['workspace']->id, 'title' => 'Privada', 'status' => 'active']);
        $this->actingAs($a['user'])->post(route('client.ai.library.structure', 'projects'), ['title' => 'Projeto', 'status' => 'active', 'mission_id' => $mission->id])->assertSessionHasErrors('mission_id');
        $this->assertDatabaseCount('ai_projects', 0);
    }

    public function test_agent_can_create_paused_heartbeat_but_cannot_assign_foreign_goal(): void
    {
        $a = $this->createWorkspaceContext();
        $b = $this->createWorkspaceContext();
        $item = app(WorkspaceResourceCatalog::class)->install($a['workspace']->id, 'agent:atlas-project');
        $agent = AiAgent::where('uuid', $item->metadata['agent_uuid'])->firstOrFail();
        $agent->update(['enabled' => true]);
        $action = ['type' => 'create_heartbeat', 'name' => 'Revisar metas', 'instructions' => 'Revise prazos', 'interval_minutes' => 60];
        $result = app(AgentOperationsActions::class)->execute($agent, $a['user'], 'Crie um heartbeat de metas', $action);
        $this->assertTrue($result['ok']);
        $this->assertFalse(AiHeartbeat::first()->enabled);
        $goal = AiGoal::create(['workspace_id' => $b['workspace']->id, 'title' => 'Privada', 'target_value' => 10]);
        $this->expectException(ValidationException::class);
        app(AgentOperationsActions::class)->execute($agent, $a['user'], 'Crie um heartbeat', $action + ['goal_uuid' => $goal->uuid]);
    }

    public function test_catalog_routine_is_paused_and_uses_real_queue_record(): void
    {
        $a = $this->createWorkspaceContext();
        $item = app(WorkspaceResourceCatalog::class)->install($a['workspace']->id, 'routine:good-morning', $a['user']->id);
        $hb = AiHeartbeat::where('uuid', $item->metadata['heartbeat_uuid'])->firstOrFail();
        $this->assertSame('routine', $hb->execution_mode);
        $this->assertFalse($hb->enabled);
        $this->assertSame(1, $hb->daily_run_limit);
    }

    public function test_new_connection_checks_do_not_expose_provider_errors_or_keys(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.todoist.com/*' => Http::response(['error' => 'sensitive-provider-text'], 401)]);
        $c = new WorkspaceIntegration(['provider' => 'todoist', 'credentials' => ['api_key' => 'private-token']]);
        $result = app(WorkspaceConnectionTester::class)->test($c);
        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('private-token', json_encode($result));
        $this->assertStringNotContainsString('sensitive-provider-text', json_encode($result));
    }

    public function test_all_catalog_entries_have_unique_keys_and_fit_database_limits(): void
    {
        $entries = app(WorkspaceResourceCatalog::class)->entries();
        $this->assertCount(count($entries), array_unique(array_column($entries, 'key')));
        foreach ($entries as $entry) {
            $this->assertLessThanOrEqual(180, strlen($entry['key']));
            $this->assertNotEmpty($entry['content']);
            if ($entry['kind'] === 'agent') {
                $this->assertLessThan(60000, strlen($entry['content']));
            }
        }
    }

    public function test_native_reader_scopes_connection_and_returns_only_selected_fields(): void
    {
        $a = $this->createWorkspaceContext();
        $b = $this->createWorkspaceContext();
        $item = app(WorkspaceResourceCatalog::class)->install($a['workspace']->id, 'agent:atlas-project');
        $agent = AiAgent::where('uuid', $item->metadata['agent_uuid'])->firstOrFail();
        $connection = WorkspaceIntegration::create(['workspace_id' => $b['workspace']->id, 'provider' => 'todoist', 'name' => 'Privado', 'credentials' => ['api_key' => 'key'], 'status' => 'connected']);
        Http::preventStrayRequests();
        $this->expectException(ModelNotFoundException::class);
        app(AgentIntegrationReader::class)->execute($agent,$a['user'],['connection_uuid' => $connection->uuid]);
    }
}
