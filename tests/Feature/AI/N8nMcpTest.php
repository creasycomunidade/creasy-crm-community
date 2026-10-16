<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiMcpServer;
use App\Modules\AI\Models\AiMcpToolCall;
use App\Modules\AI\Services\AgentCatalog;
use App\Modules\AI\Services\McpClient;
use App\Modules\AI\Services\McpToolContext;
use App\Modules\AI\Services\N8nMcpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class N8nMcpTest extends TestCase
{
    use RefreshDatabase;

    private function connection(string $mode = 'approval_required'): array
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)->where('slug', 'oracle')->firstOrFail();
        $agent->update(['enabled' => true, 'permission_mode' => 'approval_required']);
        $server = AiMcpServer::create([
            'workspace_id' => $context['workspace']->id, 'connection_type' => 'n8n',
            'name' => 'n8n', 'endpoint_url' => 'https://93.184.216.34',
            'auth_type' => 'header', 'credentials' => ['api_key' => 'private-n8n-key', 'webhook_token' => 'private-webhook-key'],
            'configuration' => ['ai_webhook_url' => 'https://93.184.216.34/webhook/ai'],
            'permission_mode' => $mode, 'enabled' => true, 'status' => 'connected',
        ]);
        $server->agents()->sync([$agent->id]);
        $server->update(['tools_cache' => app(N8nMcpServer::class)->tools($server)]);

        return [$context, $agent, $server];
    }

    private function token(array $context, array $abilities = ['mcp:n8n:read']): string
    {
        return $context['user']->createToken('MCP test', $abilities)->plainTextToken;
    }

    public function test_admin_can_save_native_connection_and_secrets_are_never_returned(): void
    {
        [$context, $agent] = $this->connection();
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->actingAs($context['user'])->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.ai.mcp-servers.store'), [
                'connection_type' => 'n8n', 'name' => 'New n8n', 'endpoint_url' => 'https://93.184.216.34',
                'api_key' => 'new-secret-key', 'webhook_token' => 'new-webhook-secret',
                'ai_webhook_url' => 'https://93.184.216.34/webhook/ai',
                'permission_mode' => 'approval_required', 'enabled' => true, 'agent_uuids' => [$agent->uuid],
            ])->assertSessionHasNoErrors()->assertRedirect();
        $server = AiMcpServer::where('name', 'New n8n')->firstOrFail();
        $this->assertSame('connected', $server->status);
        $this->assertCount(9, $server->tools_cache);
        $this->assertStringNotContainsString('new-secret-key', DB::table('ai_mcp_servers')->where('id', $server->id)->value('credentials'));
        $this->assertArrayNotHasKey('credentials', $server->toArray());
        Http::assertSent(fn ($request) => $request->hasHeader('X-N8N-API-KEY', 'new-secret-key'));
    }

    public function test_edit_preserves_key_and_can_remove_webhook_token(): void
    {
        [$context, $agent, $server] = $this->connection();
        $this->actingAs($context['user'])->withSession(['current_workspace_id' => $context['workspace']->id])
            ->put(route('client.ai.mcp-servers.update', $server), [
                'connection_type' => 'n8n', 'name' => 'Updated n8n', 'endpoint_url' => $server->endpoint_url,
                'api_key' => '', 'webhook_token' => '', 'clear_webhook_token' => true,
                'permission_mode' => 'read_only', 'enabled' => true, 'agent_uuids' => [$agent->uuid],
            ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('private-n8n-key', $server->fresh()->credentials['api_key']);
        $this->assertNull($server->fresh()->credentials['webhook_token']);
        $this->assertSame('pending', $server->fresh()->status);
    }

    public function test_n8n_requires_https_key_and_explicit_agents(): void
    {
        [$context] = $this->connection();
        Http::fake();
        $this->actingAs($context['user'])->post(route('client.ai.mcp-servers.store'), [
            'connection_type' => 'n8n', 'name' => 'Invalid n8n', 'endpoint_url' => 'http://127.0.0.1',
            'permission_mode' => 'read_only', 'enabled' => true, 'agent_uuids' => [],
        ])->assertSessionHasErrors(['endpoint_url', 'api_key', 'agent_uuids']);
        Http::assertNothingSent();
    }

    public function test_sync_filters_write_tools_and_rejects_html_login_page(): void
    {
        [, , $server] = $this->connection('read_only');
        Http::fakeSequence()->push(['data' => []])->push('<html>Login</html>');
        $this->assertCount(4, app(McpClient::class)->syncTools($server));
        try {
            app(McpClient::class)->syncTools($server);
            $this->fail('HTML must not count as a successful API connection.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('API n8n', $error->getMessage());
        }
        $this->assertSame('error', $server->fresh()->status);
    }

    public function test_ai_workflow_receives_scoped_memory_and_separate_auth_and_is_audited(): void
    {
        [$context, $agent, $server] = $this->connection();
        Http::fake(['*' => Http::response(['output' => 'Olá', 'secret' => 'private-n8n-key'])]);
        $result = app(McpClient::class)->callTool($server, $agent, $context['user'], 'n8n_run_ai', [
            'message' => 'Preciso de ajuda', 'conversation_id' => 'conversation-42',
        ]);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Olá', $result['text']);
        $this->assertStringNotContainsString('private-n8n-key', $result['text']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://93.184.216.34/webhook/ai'
            && $request->hasHeader('Authorization', 'Bearer private-webhook-key')
            && ! $request->hasHeader('X-N8N-API-KEY')
            && $request['sessionId'] === 'respondesk:'.$server->workspace_id.':conversation-42'
            && $request['chatInput'] === 'Preciso de ajuda');
        $call = AiMcpToolCall::firstOrFail();
        $this->assertSame('completed', $call->status);
        $this->assertSame($agent->id, $call->agent_id);
        $this->assertStringNotContainsString('private-n8n-key', $call->result_summary);
    }

    public function test_agent_read_only_permission_blocks_workflow_execution(): void
    {
        [$context, $agent, $server] = $this->connection();
        $agent->update(['permission_mode' => 'read_only']);
        Http::fake();
        try {
            app(McpClient::class)->callTool($server, $agent, $context['user'], 'n8n_run_ai', ['message' => 'Oi', 'conversation_id' => '1']);
            $this->fail('Read-only agent cannot invoke a workflow.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_cross_workspace_and_unassigned_agents_cannot_call(): void
    {
        [$context, , $server] = $this->connection();
        [$other, $otherAgent] = $this->connection();
        Http::fake();
        foreach ([[$otherAgent, $other['user']], [$otherAgent, $context['user']]] as [$agent, $user]) {
            try {
                app(McpClient::class)->callTool($server, $agent, $user, 'n8n_list_workflows', []);
                $this->fail('Cross-workspace call accepted.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
        $unassigned = AiAgent::where('workspace_id', $context['workspace']->id)->where('slug', '!=', 'oracle')->firstOrFail();
        $unassigned->update(['enabled' => true]);
        try {
            app(McpClient::class)->callTool($server, $unassigned, $context['user'], 'n8n_list_workflows', []);
            $this->fail('Unassigned agent accepted.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_api_operations_use_expected_methods_and_paths(): void
    {
        [, , $server] = $this->connection();
        $server->update(['endpoint_url' => 'https://93.184.216.34/api/v1/']);
        $workflow = ['name' => 'Example', 'nodes' => [], 'connections' => [], 'settings' => []];
        Http::fake(['*' => Http::response(['id' => 'abc'])]);
        foreach ([
            ['n8n_list_workflows', ['cursor' => 'next', 'limit' => 5], 'GET', '/workflows?cursor=next&limit=5'],
            ['n8n_get_workflow', ['id' => 'abc'], 'GET', '/workflows/abc'],
            ['n8n_list_executions', ['workflowId' => 'abc'], 'GET', '/executions?workflowId=abc&limit=20&includeData=false'],
            ['n8n_get_execution', ['id' => '123'], 'GET', '/executions/123?includeData=true'],
            ['n8n_create_workflow', ['workflow' => $workflow], 'POST', '/workflows'],
            ['n8n_update_workflow', ['id' => 'abc', 'workflow' => $workflow], 'PUT', '/workflows/abc'],
            ['n8n_activate_workflow', ['id' => 'abc'], 'POST', '/workflows/abc/activate'],
            ['n8n_deactivate_workflow', ['id' => 'abc'], 'POST', '/workflows/abc/deactivate'],
        ] as [$name, $args, $method, $path]) {
            app(N8nMcpServer::class)->call($server, $name, $args);
            Http::assertSent(fn ($request) => $request->method() === $method && $request->url() === 'https://93.184.216.34/api/v1'.$path);
        }
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->body(), '"settings":{}'));
        Http::assertSentCount(8);
    }

    public function test_invalid_ids_and_unknown_arguments_never_reach_n8n(): void
    {
        [, , $server] = $this->connection();
        Http::fake();
        foreach ([
            ['n8n_get_workflow', ['id' => '../credentials']],
            ['n8n_list_workflows', ['url' => 'https://127.0.0.1']],
            ['n8n_list_workflows', ['limit' => 1000]],
            ['n8n_run_ai', ['message' => 'Hi']],
        ] as [$name, $args]) {
            try {
                app(N8nMcpServer::class)->call($server, $name, $args);
                $this->fail('Invalid arguments accepted.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        Http::assertNothingSent();
    }

    public function test_failed_write_is_not_retried_and_upstream_body_is_not_leaked(): void
    {
        [$context, $agent, $server] = $this->connection();
        Http::fake(['*' => Http::response(['error' => 'private-n8n-key'], 503)]);
        try {
            app(McpClient::class)->callTool($server, $agent, $context['user'], 'n8n_activate_workflow', ['id' => 'abc']);
            $this->fail('Failure must propagate.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('503', $error->getMessage());
            $this->assertStringNotContainsString('private-n8n-key', $error->getMessage());
        }
        Http::assertSentCount(1);
        $this->assertSame('failed', AiMcpToolCall::firstOrFail()->status);
    }

    public function test_n8n_tools_are_discoverable_even_for_short_requests(): void
    {
        [, $agent] = $this->connection();
        $this->assertStringContainsString('n8n_run_ai', app(McpToolContext::class)->build($agent, 'n8n'));
        $agent->update(['permission_mode' => 'read_only']);
        $this->assertStringNotContainsString('n8n_run_ai', app(McpToolContext::class)->build($agent, 'n8n'));
    }

    public function test_http_mcp_initialization_listing_and_read_call(): void
    {
        [$context, , $server] = $this->connection();
        $url = route('api.ai.n8n-mcp', $server);
        $this->withToken($this->token($context))->postJson($url, [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25'],
        ])->assertOk()->assertJsonPath('result.serverInfo.name', 'respondesk-n8n');
        $this->postJson($url, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertNoContent(202);
        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])
            ->assertOk()->assertJsonCount(4, 'result.tools');
        Http::fake(['*' => Http::response(['data' => [['id' => 'abc', 'name' => 'IA']]])]);
        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'n8n_list_workflows']])
            ->assertOk()->assertJsonPath('result.isError', false);
        $this->assertNull(AiMcpToolCall::firstOrFail()->agent_id);
        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'n8n_activate_workflow', 'arguments' => ['id' => 'abc']]])
            ->assertJsonPath('error.code', -32602);
        Http::assertSentCount(1);
    }

    public function test_http_write_requires_scope_and_respects_connection_mode(): void
    {
        [$context, , $server] = $this->connection();
        Http::fake(['*' => Http::response(['id' => 'abc', 'active' => true])]);
        $url = route('api.ai.n8n-mcp', $server);
        $call = ['jsonrpc' => '2.0', 'id' => 'write', 'method' => 'tools/call', 'params' => ['name' => 'n8n_activate_workflow', 'arguments' => ['id' => 'abc']]];
        $this->withToken($this->token($context, ['mcp:n8n:read', 'mcp:n8n:write']))->postJson($url, $call)
            ->assertOk()->assertJsonPath('result.isError', false);
        $server->update(['permission_mode' => 'read_only']);
        $this->postJson($url, $call)->assertJsonPath('error.code', -32602);
        Http::assertSentCount(1);
    }

    public function test_http_mcp_rejects_wrong_workspace_origin_and_disabled_connection(): void
    {
        [$context, , $server] = $this->connection();
        [$other] = $this->connection();
        $url = route('api.ai.n8n-mcp', $server);
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $this->withToken($this->token($other))->postJson($url, $payload)->assertForbidden();
        // Reset the auth guard between different bearer identities in the same test.
        app('auth')->forgetGuards();
        $this->withToken($this->token($context))->withHeader('Origin', 'https://evil.example')->postJson($url, $payload)->assertForbidden();
        $this->withHeader('Origin', rtrim(config('app.url'), '/'))->postJson($url, $payload)->assertOk();
        $server->update(['enabled' => false]);
        $this->postJson($url, $payload)->assertNotFound();
    }

    public function test_http_mcp_protocol_errors_and_no_sse(): void
    {
        [$context, , $server] = $this->connection();
        $url = route('api.ai.n8n-mcp', $server);
        $this->withToken($this->token($context))->getJson($url)->assertStatus(405);
        $this->postJson($url, [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']])->assertStatus(400)->assertJsonPath('error.code', -32600);
        $this->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'unknown'])->assertJsonPath('error.code', -32601);
        $this->withHeader('MCP-Protocol-Version', 'invalid')->postJson($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertStatus(400);
    }

    public function test_validation_does_not_flash_secrets_to_the_session(): void
    {
        [$context] = $this->connection();
        $this->actingAs($context['user'])->post(route('client.ai.mcp-servers.store'), [
            'connection_type' => 'n8n', 'name' => '', 'endpoint_url' => 'https://93.184.216.34',
            'api_key' => 'must-not-be-flashed', 'webhook_token' => 'also-private',
            'permission_mode' => 'read_only', 'enabled' => true, 'agent_uuids' => [],
        ])->assertSessionHasErrors(['name'])
            ->assertSessionMissing('_old_input.api_key')
            ->assertSessionMissing('_old_input.webhook_token');
    }

    public function test_workflow_objects_are_preserved_and_redacted_edits_are_rejected(): void
    {
        [, , $server] = $this->connection();
        $workflow = ['name' => 'Example', 'nodes' => [['parameters' => []]], 'connections' => [], 'settings' => []];
        Http::fake(['*' => Http::response(['id' => 'abc'])]);
        app(N8nMcpServer::class)->call($server, 'n8n_create_workflow', ['workflow' => $workflow]);
        Http::assertSent(fn ($request) => str_contains($request->body(), '"parameters":{}'));
        $workflow['nodes'][0]['parameters'] = ['headers' => '[redigido]'];
        try {
            app(N8nMcpServer::class)->call($server, 'n8n_update_workflow', ['id' => 'abc', 'workflow' => $workflow]);
            $this->fail('Redacted workflow was accepted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('workflow', $error->errors());
        }
        Http::assertSentCount(1);
    }
}
