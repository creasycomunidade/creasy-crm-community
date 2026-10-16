<?php

namespace Tests\Feature\Integrations;

use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\WorkspaceConnectionTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorkspaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_are_encrypted_and_asaas_connection_can_be_tested(): void
    {
        $context = $this->createWorkspaceContext();
        $connection = WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'asaas',
            'name' => 'Asaas principal',
            'credentials' => ['api_key' => 'segredo-asaas', 'environment' => 'sandbox'],
        ]);
        $raw = DB::table('workspace_integration_connections')->where('id', $connection->id)->value('credentials');
        $this->assertStringNotContainsString('segredo-asaas', $raw);

        Http::fake([
            'api-sandbox.asaas.com/v3/myAccount' => Http::response(['name' => 'Empresa Exemplo'], 200),
        ]);
        $result = app(WorkspaceConnectionTester::class)->test($connection->fresh());
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Empresa Exemplo', $result['message']);
    }

    public function test_one_workspace_cannot_test_another_workspace_connection(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $connection = WorkspaceIntegration::create([
            'workspace_id' => $first['workspace']->id,
            'provider' => 'wordpress',
            'name' => 'Site',
            'credentials' => ['url' => 'https://site.test', 'username' => 'api', 'application_password' => 'secret'],
        ]);

        $this->actingAs($second['user'])
            ->post(route('client.integrations.test', $connection))
            ->assertForbidden();
    }

    public function test_fazos_uses_the_native_respondesk_bridge_endpoint(): void
    {
        $context = $this->createWorkspaceContext();
        $connection = WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'fazos',
            'name' => 'FazOS principal',
            'credentials' => ['url' => 'https://example.com', 'token' => 'fazos_'.str_repeat('a', 64)],
        ]);

        Http::fake([
            'https://example.com/respondesk_bridge/api/status' => Http::response([
                'data' => ['tenant' => ['name' => 'Empresa Teste']],
            ]),
        ]);

        $result = app(WorkspaceConnectionTester::class)->test($connection);

        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Empresa Teste', $result['message']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/respondesk_bridge/api/status'
            && $request->hasHeader('authtoken', 'fazos_'.str_repeat('a', 64)));
    }

    public function test_fazos_accepts_the_complete_api_url_copied_from_fazos(): void
    {
        $context = $this->createWorkspaceContext();
        $connection = WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'fazos',
            'name' => 'FazOS com URL completa',
            'credentials' => [
                'url' => 'https://example.com/respondesk_bridge/api/',
                'token' => 'fazos_'.str_repeat('b', 64),
            ],
        ]);

        Http::fake([
            'https://example.com/respondesk_bridge/api/status' => Http::response([
                'data' => ['tenant' => ['host' => 'example.com']],
            ]),
        ]);

        $result = app(WorkspaceConnectionTester::class)->test($connection);

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/respondesk_bridge/api/status');
    }
}
