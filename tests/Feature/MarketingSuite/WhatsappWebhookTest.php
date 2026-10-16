<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $verifyToken = 'test-verify-token';

    private string $phoneNumberId = 'PHONE_ID';

    private function makeWaba(): WhatsappBusinessAccount
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $waba = WhatsappBusinessAccount::factory()->create([
            'workspace_id' => $workspace->id,
            'webhook_verify_token' => $this->verifyToken,
            'status' => 'active',
        ]);

        // Create the ChannelAccount so inbound messages can be scoped to this workspace
        ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'display_name' => 'Test WA',
            'phone_number_id' => $this->phoneNumberId,
            'business_account_id' => $waba->waba_id,
            'status' => 'active',
        ]);

        return $waba;
    }

    #[Test]
    public function it_handles_hub_challenge_verification(): void
    {
        $this->makeWaba();

        $qs = http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'abc123',
        ]);
        $response = $this->get("/webhooks/whatsapp/{$this->verifyToken}?{$qs}");

        $response->assertStatus(200);
        $response->assertSee('abc123');
    }

    #[Test]
    public function it_rejects_invalid_verify_token(): void
    {
        $this->makeWaba();

        $qs = http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'abc123',
        ]);
        $response = $this->get("/webhooks/whatsapp/wrong-token?{$qs}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_creates_conversation_and_message_on_inbound(): void
    {
        $waba = $this->makeWaba();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $waba->waba_id,
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '+1555000000', 'phone_number_id' => 'PHONE_ID'],
                        'contacts' => [['profile' => ['name' => 'Alice'], 'wa_id' => '8801900000001']],
                        'messages' => [[
                            'from' => '8801900000001',
                            'id' => 'wamid.TEST123',
                            'timestamp' => now()->timestamp,
                            'text' => ['body' => 'Hello!'],
                            'type' => 'text',
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];

        $response = $this->postJson("/webhooks/whatsapp/{$this->verifyToken}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('contacts', [
            'phone_e164' => '+8801900000001',
            'workspace_id' => $waba->workspace_id,
        ]);
        $this->assertDatabaseHas('messages', ['provider_message_id' => 'wamid.TEST123']);
    }
}
