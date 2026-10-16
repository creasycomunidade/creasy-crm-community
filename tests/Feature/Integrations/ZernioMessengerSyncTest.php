<?php

namespace Tests\Feature\Integrations;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\ZernioWorkspaceManager;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioMessengerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_facebook_account_is_available_as_messenger_immediately_after_sync(): void
    {
        $context = $this->createWorkspaceContext();
        IntegrationConfig::create([
            'provider' => 'zernio',
            'label' => 'Conexão central',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['api_key' => 'test-key', 'base_url' => 'https://zernio.test/api/v1'],
        ]);
        $connection = ZernioWorkspaceConnection::create([
            'workspace_id' => $context['workspace']->id,
            'profile_id' => 'profile-test',
            'profile_name' => 'Empresa teste',
            'status' => 'connected',
        ]);
        Http::fake([
            'zernio.test/api/v1/accounts*' => Http::response(['accounts' => [[
                '_id' => 'facebook-account-1',
                'platform' => 'facebook',
                'displayName' => 'Página Aurora',
                'isActive' => true,
            ]]]),
        ]);

        app(ZernioWorkspaceManager::class)->syncAccounts($connection);

        $account = ChannelAccount::where('workspace_id', $context['workspace']->id)
            ->where('channel', 'messenger')
            ->where('external_account_id', 'facebook-account-1')
            ->firstOrFail();
        $this->assertSame('zernio', $account->provider);
        $this->assertSame('Página Aurora', $account->display_name);
        $this->assertSame('active', $account->status);
        $this->assertSame('profile-test', $account->meta_json['zernio_profile_id']);
    }

    public function test_resync_preserves_channel_operations_and_marks_missing_account_inactive(): void
    {
        $context = $this->createWorkspaceContext();
        IntegrationConfig::create([
            'provider' => 'zernio', 'label' => 'Central', 'mode' => 'live', 'enabled' => true,
            'credentials' => ['api_key' => 'test-key', 'base_url' => 'https://zernio.test/api/v1'],
        ]);
        $connection = ZernioWorkspaceConnection::create([
            'workspace_id' => $context['workspace']->id, 'profile_id' => 'profile-test',
            'profile_name' => 'Empresa teste', 'status' => 'connected',
        ]);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'messenger', 'provider' => 'zernio',
            'external_account_id' => 'facebook-account-1', 'display_name' => 'Página',
            'status' => 'active', 'credentials' => [],
            'meta_json' => ['operations' => ['ai_chatbot_id' => 9]],
        ]);
        Http::fake(['zernio.test/api/v1/accounts*' => Http::response(['accounts' => []])]);

        app(ZernioWorkspaceManager::class)->syncAccounts($connection);

        $account = ChannelAccount::where('external_account_id', 'facebook-account-1')->firstOrFail();
        $this->assertSame('inactive', $account->status);
        $this->assertSame(9, $account->meta_json['operations']['ai_chatbot_id']);
    }
}
