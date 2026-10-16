<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiMcpServer;
use App\Modules\AI\Models\AiMcpToolCall;
use App\Modules\AI\Models\AiSkill;
use App\Modules\AI\Services\AgentActionExecutor;
use App\Modules\AI\Services\AgentCatalog;
use App\Modules\AI\Services\AgentSkillCatalog;
use App\Modules\AI\Services\AgentSkillContext;
use App\Modules\AI\Services\McpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_capabilities_page_creates_default_ui_designer_skill(): void
    {
        $context = $this->createWorkspaceContext();

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->get(route('client.ai.capabilities.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AI/Capabilities/Index')
                ->where('skills', fn ($skills) => collect($skills)->contains('slug', 'designer-ui')));

        $skill = AiSkill::where('workspace_id', $context['workspace']->id)->where('slug', 'designer-ui')->firstOrFail();
        $this->assertTrue($skill->auto_activate);
        $this->assertTrue($skill->is_system);
        $this->assertContains('layout', $skill->trigger_terms);
    }

    public function test_custom_skill_is_isolated_and_only_loaded_when_relevant(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($first['workspace']->id);
        $agent = AiAgent::where('workspace_id', $first['workspace']->id)->where('slug', 'oracle')->firstOrFail();
        $skill = AiSkill::create([
            'workspace_id' => $first['workspace']->id,
            'name' => 'Propostas premium',
            'slug' => 'propostas-premium',
            'category' => 'sales',
            'description' => 'Padroniza propostas.',
            'instructions' => 'Toda proposta deve conter escopo, prazo, preço e próximos passos.',
            'trigger_terms' => ['proposta'],
            'auto_activate' => true,
            'enabled' => true,
        ]);

        $service = app(AgentSkillContext::class);
        $this->assertStringContainsString('SKILL: Propostas premium', $service->build($agent, 'Crie uma proposta comercial.'));
        $this->assertStringNotContainsString('Propostas premium', $service->build($agent, 'Resuma esta conversa.'));
        $this->assertDatabaseMissing('ai_skills', ['workspace_id' => $second['workspace']->id, 'slug' => $skill->slug]);
    }

    public function test_mcp_credentials_are_encrypted_and_tools_are_discovered(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        Http::fakeSequence()
            ->push([
                'jsonrpc' => '2.0', 'id' => 'init',
                'result' => ['protocolVersion' => '2025-11-25', 'capabilities' => ['tools' => []], 'serverInfo' => ['name' => 'Fake', 'version' => '1']],
            ], 200, ['MCP-Session-Id' => 'test-session'])
            ->push('', 202)
            ->push([
                'jsonrpc' => '2.0', 'id' => 'list',
                'result' => ['tools' => [[
                    'name' => 'list_orders',
                    'description' => 'Lista pedidos recentes.',
                    'inputSchema' => ['type' => 'object', 'properties' => []],
                    'annotations' => ['readOnlyHint' => true],
                ]]],
            ], 200);
        $server = AiMcpServer::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'ERP',
            'endpoint_url' => 'https://93.184.216.34/mcp',
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'segredo-super-secreto'],
            'permission_mode' => 'read_only',
            'status' => 'pending',
            'enabled' => true,
        ]);

        $raw = DB::table('ai_mcp_servers')->where('id', $server->id)->value('credentials');
        $this->assertStringNotContainsString('segredo-super-secreto', (string) $raw);
        $tools = app(McpClient::class)->syncTools($server);

        $this->assertSame('list_orders', $tools[0]['name']);
        $this->assertSame('connected', $server->fresh()->status);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer segredo-super-secreto'));
    }

    public function test_workspace_cannot_update_another_company_skill(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        app(AgentSkillCatalog::class)->ensureForWorkspace($first['workspace']->id);
        $skill = AiSkill::where('workspace_id', $first['workspace']->id)->firstOrFail();

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->put(route('client.ai.skills.update', $skill), [
                'name' => 'Invadida', 'category' => 'custom', 'description' => '',
                'instructions' => 'Não deve salvar.', 'trigger_terms' => [], 'agent_uuids' => [],
                'auto_activate' => false, 'enabled' => true,
            ])->assertForbidden();

        $this->assertNotSame('Invadida', $skill->fresh()->name);
    }

    public function test_agent_can_execute_an_authorized_read_only_mcp_tool(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)->where('slug', 'oracle')->firstOrFail();
        $server = AiMcpServer::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'ERP',
            'endpoint_url' => 'https://93.184.216.34/mcp',
            'auth_type' => 'none',
            'permission_mode' => 'read_only',
            'protocol_version' => '2025-11-25',
            'status' => 'connected',
            'enabled' => true,
            'tools_cache' => [[
                'name' => 'list_orders',
                'description' => 'Lista pedidos recentes.',
                'inputSchema' => ['type' => 'object'],
                'annotations' => ['readOnlyHint' => true],
            ]],
        ]);
        Http::fakeSequence()
            ->push(['jsonrpc' => '2.0', 'id' => 'init', 'result' => ['protocolVersion' => '2025-11-25']], 200, ['MCP-Session-Id' => 'call-session'])
            ->push('', 202)
            ->push([
                'jsonrpc' => '2.0', 'id' => 'call',
                'result' => ['content' => [['type' => 'text', 'text' => '3 pedidos encontrados.']]],
            ], 200);
        $reply = "Vou consultar.\n```respondesk-actions\n".json_encode(['actions' => [[
            'type' => 'mcp_tool',
            'server_uuid' => $server->uuid,
            'tool' => 'list_orders',
            'arguments' => [],
        ]]])."\n```";

        $result = app(AgentActionExecutor::class)->process($agent, $context['user'], 'Liste os pedidos recentes.', $reply);

        $this->assertTrue($result['results'][0]['ok']);
        $this->assertStringContainsString('3 pedidos encontrados', $result['reply']);
        $this->assertSame('completed', AiMcpToolCall::firstOrFail()->status);
        Http::assertSentCount(3);
    }
}
