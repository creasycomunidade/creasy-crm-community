<?php

namespace Tests\Feature;

use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_cannot_be_connected_to_a_second_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();

        ChannelAccount::create([
            'workspace_id' => $first['workspace']->id,
            'channel' => 'telegram',
            'provider' => 'telegram',
            'external_account_id' => '123456',
            'display_name' => '@existing_bot',
            'credentials' => ['bot_token' => '123456:existing_token_value_long_enough'],
            'status' => 'active',
        ]);

        Http::fake([
            'https://api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => ['id' => 123456, 'username' => 'existing_bot'],
            ]),
        ]);

        $response = $this->actingAs($second['user'])->post(route('client.telegram.store'), [
            'bot_token' => '123456:new_token_value_long_enough',
        ]);

        $response->assertSessionHas('error', 'Este bot do Telegram já está conectado a outro espaço de trabalho.');
        $this->assertDatabaseCount('channel_accounts', 1);
    }
}
