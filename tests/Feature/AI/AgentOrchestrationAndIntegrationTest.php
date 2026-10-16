<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Services\AgentIntegrationContextService;
use App\Modules\AI\Services\AgentSpecialistRouter;
use App\Modules\FazOS\Models\FazOSCustomer;
use App\Modules\FazOS\Models\FazOSServiceOrder;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentOrchestrationAndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_maestro_routes_financial_requests_to_financial_specialist(): void
    {
        $context = $this->createWorkspaceContext();
        $maestro = $this->agent($context['workspace']->id, 'oracle', 'Maestro');
        $financial = $this->agent($context['workspace']->id, 'flux-finance', 'Especialista Financeiro');

        $selected = app(AgentSpecialistRouter::class)->route(
            $maestro,
            'Verifique as faturas vencidas no Asaas.',
        );

        $this->assertSame($financial->id, $selected?->id);
    }

    public function test_maestro_routes_integrations_to_their_business_specialists(): void
    {
        $context = $this->createWorkspaceContext();
        $maestro = $this->agent($context['workspace']->id, 'oracle', 'Maestro');
        $sales = $this->agent($context['workspace']->id, 'nex-sales', 'Consultor de Vendas');
        $marketing = $this->agent($context['workspace']->id, 'mako-marketing', 'Especialista em Marketing');
        $customerSuccess = $this->agent($context['workspace']->id, 'zara-cs', 'Sucesso do Cliente');

        $this->assertSame($sales->id, app(AgentSpecialistRouter::class)->route($maestro, 'Consulte os pedidos no Bling.')?->id);
        $this->assertSame($marketing->id, app(AgentSpecialistRouter::class)->route($maestro, 'Consulte os posts do WordPress.')?->id);
        $this->assertSame($customerSuccess->id, app(AgentSpecialistRouter::class)->route($maestro, 'Consulte a OS no FazOS.')?->id);
    }

    public function test_asaas_connection_is_available_to_agents_for_live_read_only_queries(): void
    {
        $context = $this->createWorkspaceContext();
        WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'asaas',
            'name' => 'Asaas principal',
            'credentials' => ['api_key' => 'segredo', 'environment' => 'sandbox'],
            'status' => 'connected',
        ]);
        Http::fake([
            'api-sandbox.asaas.com/v3/payments*' => Http::response([
                'data' => [[
                    'id' => 'pay_123', 'status' => 'OVERDUE', 'dueDate' => '2026-08-20',
                    'value' => 250.5, 'description' => 'Mensalidade agosto', 'customer' => 'cus_123',
                ]],
            ]),
            'api-sandbox.asaas.com/v3/customers/cus_123' => Http::response(['id' => 'cus_123', 'name' => 'Empresa Exemplo']),
        ]);

        $result = app(AgentIntegrationContextService::class)->context(
            $context['workspace']->id,
            'Quais cobranças vencidas existem no Asaas?',
        );

        $this->assertStringContainsString('CONSULTA EM TEMPO REAL', $result);
        $this->assertStringContainsString('Empresa Exemplo', $result);
        $this->assertStringContainsString('R$ 250,50', $result);
        $this->assertStringNotContainsString('pay_123', $result);
        $this->assertStringNotContainsString('cus_123', $result);
        $this->assertStringNotContainsString('segredo', $result);
    }

    public function test_bling_and_omie_records_are_available_without_exposing_credentials(): void
    {
        $context = $this->createWorkspaceContext();
        WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id, 'provider' => 'bling', 'name' => 'Bling',
            'credentials' => ['access_token' => 'token-bling-secreto'], 'status' => 'connected',
        ]);
        WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id, 'provider' => 'omie', 'name' => 'Omie',
            'credentials' => ['app_key' => 'key-omie-secreta', 'app_secret' => 'secret-omie-secreto'], 'status' => 'connected',
        ]);
        Http::fake([
            'www.bling.com.br/Api/v3/pedidos/vendas*' => Http::response([
                'data' => [['id' => 77, 'numero' => 1201, 'situacao' => ['valor' => 9]]],
            ]),
            'app.omie.com.br/api/v1/produtos/pedido/*' => Http::response([
                'pedido_venda_produto' => [['cabecalho' => ['numero_pedido' => 'OM-99'], 'total_pedido' => ['valor_total_pedido' => 840]]],
            ]),
        ]);

        $result = app(AgentIntegrationContextService::class)->context(
            $context['workspace']->id,
            'Liste os pedidos de venda no Bling e no Omie.',
        );

        $this->assertStringContainsString('BLING — PEDIDOS DE VENDA', $result);
        $this->assertStringContainsString('1201', $result);
        $this->assertStringContainsString('OMIE — PEDIDOS', $result);
        $this->assertStringContainsString('OM-99', $result);
        $this->assertStringNotContainsString('token-bling-secreto', $result);
        $this->assertStringNotContainsString('secret-omie-secreto', $result);
    }

    public function test_wordpress_content_can_be_consulted_read_only(): void
    {
        $context = $this->createWorkspaceContext();
        WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id, 'provider' => 'wordpress', 'name' => 'Site',
            'credentials' => ['url' => 'https://1.1.1.1', 'username' => 'editor', 'application_password' => 'senha-secreta'],
            'status' => 'connected',
        ]);
        Http::fake([
            '1.1.1.1/wp-json/wp/v2/posts*' => Http::response([[
                'id' => 12, 'type' => 'post', 'title' => ['raw' => 'Campanha de inverno'],
                'content' => ['raw' => 'Condições comerciais da campanha.'], 'status' => 'publish',
                'link' => 'https://site.test/inverno', 'modified' => '2026-08-27T10:00:00',
            ]]),
            '1.1.1.1/wp-json/wp/v2/pages*' => Http::response([]),
        ]);

        $result = app(AgentIntegrationContextService::class)->context(
            $context['workspace']->id,
            'O que há no WordPress sobre "campanha"?',
        );

        $this->assertStringContainsString('Campanha de inverno', $result);
        $this->assertStringContainsString('Condições comerciais', $result);
        $this->assertStringNotContainsString('senha-secreta', $result);
    }

    public function test_google_drive_search_and_content_are_available_to_agents(): void
    {
        $context = $this->createWorkspaceContext();
        WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id, 'provider' => 'google_drive', 'name' => 'Drive',
            'credentials' => ['access_token' => 'google-secreto'], 'status' => 'connected',
        ]);
        $drive = $this->mock(GoogleDriveService::class);
        $drive->shouldReceive('listFiles')->once()->withArgs(fn ($connection, $search) => $connection->workspace_id === $context['workspace']->id && $search === 'proposta')
            ->andReturn(['files' => [[
                'id' => 'file-1', 'name' => 'Proposta comercial', 'mimeType' => 'text/plain',
                'modifiedTime' => '2026-08-27T12:00:00Z', 'webViewLink' => 'https://drive.test/file-1',
            ]], 'next_page_token' => null]);
        $drive->shouldReceive('extractText')->once()->andReturn('Plano anual no valor de R$ 12.000.');

        $result = app(AgentIntegrationContextService::class)->context(
            $context['workspace']->id,
            'Leia no Google Drive o arquivo "proposta" e resuma o conteúdo.',
        );

        $this->assertStringContainsString('Proposta comercial', $result);
        $this->assertStringContainsString('Plano anual', $result);
        $this->assertStringNotContainsString('google-secreto', $result);
    }

    public function test_fazos_query_is_strictly_isolated_by_workspace_and_connection(): void
    {
        $current = $this->createWorkspaceContext();
        $other = $this->createWorkspaceContext();
        $currentConnection = WorkspaceIntegration::create([
            'workspace_id' => $current['workspace']->id, 'provider' => 'fazos', 'name' => 'FazOS atual',
            'credentials' => ['url' => 'https://fazos.test', 'token' => 'token-atual'], 'status' => 'connected',
        ]);
        $otherConnection = WorkspaceIntegration::create([
            'workspace_id' => $other['workspace']->id, 'provider' => 'fazos', 'name' => 'FazOS outra',
            'credentials' => ['url' => 'https://outro.test', 'token' => 'token-outro'], 'status' => 'connected',
        ]);
        $customer = FazOSCustomer::create([
            'workspace_id' => $current['workspace']->id, 'connection_id' => $currentConnection->id,
            'external_id' => 'cli-1', 'name' => 'Cliente Correto', 'phone' => '5511999999999',
            'payload' => [], 'synced_at' => now(),
        ]);
        FazOSServiceOrder::create([
            'workspace_id' => $current['workspace']->id, 'connection_id' => $currentConnection->id,
            'fazos_customer_id' => $customer->id, 'external_id' => 'os-1', 'number' => 'OS-100', 'status' => 'Em reparo',
            'payload' => [], 'synced_at' => now(),
        ]);
        $otherCustomer = FazOSCustomer::create([
            'workspace_id' => $other['workspace']->id, 'connection_id' => $otherConnection->id,
            'external_id' => 'cli-2', 'name' => 'Cliente de Outra Empresa', 'phone' => '5511888888888',
            'payload' => [], 'synced_at' => now(),
        ]);
        FazOSServiceOrder::create([
            'workspace_id' => $other['workspace']->id, 'connection_id' => $otherConnection->id,
            'fazos_customer_id' => $otherCustomer->id, 'external_id' => 'os-2', 'number' => 'OS-SECRETA', 'status' => 'Aberta',
            'payload' => [], 'synced_at' => now(),
        ]);

        $result = app(AgentIntegrationContextService::class)->context(
            $current['workspace']->id,
            'Mostre as ordens de serviço no FazOS.',
        );

        $this->assertStringContainsString('OS-100', $result);
        $this->assertStringContainsString('Cliente Correto', $result);
        $this->assertStringNotContainsString('OS-SECRETA', $result);
        $this->assertStringNotContainsString('Cliente de Outra Empresa', $result);
        $this->assertStringNotContainsString('token-atual', $result);
    }

    private function agent(int $workspaceId, string $slug, string $name): AiAgent
    {
        return AiAgent::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'slug' => $slug,
            'name' => $name,
            'layer' => 'business',
            'description' => 'Teste',
            'persona' => 'Teste',
            'system_prompt' => 'Teste',
            'enabled' => true,
        ]);
    }
}
