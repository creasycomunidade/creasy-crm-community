<?php

namespace Tests\Feature\FazOS;

use App\Modules\FazOS\Models\FazOSContactLink;
use App\Modules\FazOS\Models\FazOSCustomer;
use App\Modules\FazOS\Models\FazOSServiceOrder;
use App\Modules\FazOS\Services\FazOSConversationData;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FazOSTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_data_never_returns_another_workspace_customer_or_order(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $contact = Contact::create([
            'workspace_id' => $first['workspace']->id,
            'first_name' => 'Paulo',
            'phone_e164' => '+5511999999999',
        ]);
        $firstConnection = $this->connection($first['workspace']->id, 'Empresa A');
        $secondConnection = $this->connection($second['workspace']->id, 'Empresa B');
        $firstCustomer = $this->customer($first['workspace']->id, $firstConnection->id, 'Cliente A');
        $secondCustomer = $this->customer($second['workspace']->id, $secondConnection->id, 'Cliente B');

        FazOSServiceOrder::create([
            'workspace_id' => $first['workspace']->id,
            'connection_id' => $firstConnection->id,
            'fazos_customer_id' => $firstCustomer->id,
            'external_id' => 'os-a',
            'number' => 'A-100',
            'payload' => ['issue_description' => 'Tela quebrada'],
            'synced_at' => now(),
        ]);
        FazOSServiceOrder::create([
            'workspace_id' => $second['workspace']->id,
            'connection_id' => $secondConnection->id,
            'fazos_customer_id' => $secondCustomer->id,
            'external_id' => 'os-b',
            'number' => 'B-999',
            'payload' => ['issue_description' => 'Dado sigiloso'],
            'synced_at' => now(),
        ]);
        FazOSContactLink::create([
            'workspace_id' => $first['workspace']->id,
            'connection_id' => $firstConnection->id,
            'contact_id' => $contact->id,
            'fazos_customer_id' => $firstCustomer->id,
            'link_type' => 'manual',
            'linked_at' => now(),
        ]);

        $result = app(FazOSConversationData::class)->forContact($first['workspace']->id, $contact);

        $this->assertCount(1, $result['links']);
        $this->assertSame('Cliente A', $result['links'][0]['customer']['name']);
        $this->assertSame('A-100', $result['links'][0]['customer']['service_orders'][0]['number']);
        $this->assertSame(
            'https://fazos.example/admin/ordens_servicos/repair_job_detail/os-a',
            $result['links'][0]['customer']['service_orders'][0]['url'],
        );
        $this->assertStringNotContainsString('Cliente B', json_encode($result));
        $this->assertStringNotContainsString('B-999', json_encode($result));
    }

    public function test_user_cannot_query_fazos_data_from_another_workspace_conversation(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $contact = Contact::create(['workspace_id' => $first['workspace']->id, 'phone_e164' => '+5511888888888']);
        $account = ChannelAccount::create([
            'workspace_id' => $first['workspace']->id,
            'channel' => 'whatsapp',
            'status' => 'active',
            'display_name' => 'Canal privado',
            'credentials' => [],
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $first['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'external_thread_id' => 'private-thread',
        ]);

        $this->actingAs($second['user'])
            ->getJson(route('client.inbox.fazos.data', $conversation))
            ->assertForbidden();
    }

    private function connection(int $workspaceId, string $name): WorkspaceIntegration
    {
        return WorkspaceIntegration::create([
            'workspace_id' => $workspaceId,
            'provider' => 'fazos',
            'name' => $name,
            'credentials' => ['url' => 'https://fazos.example', 'token' => 'secret'],
            'status' => 'connected',
        ]);
    }

    private function customer(int $workspaceId, int $connectionId, string $name): FazOSCustomer
    {
        return FazOSCustomer::create([
            'workspace_id' => $workspaceId,
            'connection_id' => $connectionId,
            'external_id' => (string) $connectionId,
            'name' => $name,
            'payload' => ['name' => $name],
            'synced_at' => now(),
        ]);
    }
}
