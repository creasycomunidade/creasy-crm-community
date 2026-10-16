<?php

namespace Tests\Feature\Meta;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetaInboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_ID = 'test_meta_app_id';

    private const APP_SECRET = 'test_meta_app_secret';

    private function seedMetaIntegration(array $extra = []): IntegrationConfig
    {
        return IntegrationConfig::create([
            'provider' => 'meta_app',
            'label' => 'Meta App',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => array_merge([
                'app_id' => self::APP_ID,
                'app_secret' => self::APP_SECRET,
                'verify_token' => 'meta-verify-token-xyz',
            ], $extra),
        ]);
    }

    private function globalVerifyToken(): string
    {
        return hash('sha256', self::APP_ID.self::APP_SECRET.'wh_global_verify');
    }

    private function signPayload(string $payload): string
    {
        return 'sha256='.hash_hmac('sha256', $payload, self::APP_SECRET);
    }

    #[Test]
    public function global_whatsapp_webhook_verifies_hub_challenge(): void
    {
        $this->seedMetaIntegration();

        $qs = http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->globalVerifyToken(),
            'hub_challenge' => 'challenge_global_99',
        ]);

        $this->get("/webhooks/whatsapp/global?{$qs}")
            ->assertOk()
            ->assertSee('challenge_global_99');
    }

    #[Test]
    public function global_whatsapp_webhook_rejects_invalid_signature(): void
    {
        $this->seedMetaIntegration();

        $this->withHeaders(['X-Hub-Signature-256' => 'sha256=bad'])
            ->postJson('/webhooks/whatsapp/global', ['object' => 'whatsapp_business_account', 'entry' => []])
            ->assertUnauthorized();
    }

    #[Test]
    public function global_whatsapp_webhook_accepts_valid_signature(): void
    {
        $this->seedMetaIntegration();
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);

        $this->withHeaders(['X-Hub-Signature-256' => $this->signPayload($payload)])
            ->postJson('/webhooks/whatsapp/global', json_decode($payload, true))
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    #[Test]
    public function meta_instagram_webhook_verifies_and_accepts_signature(): void
    {
        $this->seedMetaIntegration();
        $token = 'meta-verify-token-xyz';

        $qs = http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $token,
            'hub_challenge' => 'ig_challenge',
        ]);
        $this->get("/webhooks/meta/{$token}?{$qs}")
            ->assertOk()
            ->assertSee('ig_challenge');

        $payload = json_encode([
            'object' => 'instagram',
            'entry' => [['id' => '123', 'messaging' => []]],
        ]);

        $this->withHeaders(['X-Hub-Signature-256' => $this->signPayload($payload)])
            ->postJson("/webhooks/meta/{$token}", json_decode($payload, true))
            ->assertOk();
    }

    #[Test]
    public function meta_webhook_rejects_wrong_verify_token_in_url(): void
    {
        $this->seedMetaIntegration();

        $this->get('/webhooks/meta/wrong-token?hub_mode=subscribe&hub_verify_token=wrong')
            ->assertForbidden();
    }

    #[Test]
    public function per_waba_token_lookup_uses_hash_not_full_table_scan(): void
    {
        $user = $this->createWorkspaceContext();
        $token = 'unique-per-waba-token-abc';

        WhatsappBusinessAccount::factory()->create([
            'workspace_id' => $user['workspace']->id,
            'webhook_verify_token' => $token,
            'status' => 'active',
        ]);

        $waba = WhatsappBusinessAccount::findByWebhookToken($token);
        $this->assertNotNull($waba);
        $this->assertEquals(
            WhatsappBusinessAccount::hashWebhookToken($token),
            $waba->webhook_verify_token_hash
        );
    }

    #[Test]
    public function whatsapp_driver_updates_template_status_from_webhook(): void
    {
        $user = $this->createWorkspaceContext();
        $wabaId = 'WABA_TPL_TEST';

        WhatsappBusinessAccount::factory()->create([
            'workspace_id' => $user['workspace']->id,
            'waba_id' => $wabaId,
        ]);

        WhatsappTemplate::create([
            'workspace_id' => $user['workspace']->id,
            'waba_id' => $wabaId,
            'name' => 'hello_world',
            'language' => 'en',
            'status' => 'PENDING',
        ]);

        $payload = [
            'entry' => [[
                'id' => $wabaId,
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => [
                        'event' => 'APPROVED',
                        'message_template_name' => 'hello_world',
                        'message_template_language' => 'en',
                    ],
                ]],
            ]],
        ];

        app(\App\Modules\Whatsapp\Services\WhatsappDriver::class)->processWebhookPayload($payload);

        $this->assertDatabaseHas('whatsapp_templates', [
            'waba_id' => $wabaId,
            'name' => 'hello_world',
            'status' => 'APPROVED',
        ]);
    }

    #[Test]
    public function global_webhook_dedupes_duplicate_message_ids(): void
    {
        $this->seedMetaIntegration();
        $entryId = 'entry_dup_test_1';
        $body = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $entryId,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.duplicate-test',
                            'from' => '5511999999999',
                            'type' => 'text',
                            'text' => ['body' => 'Olá'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $payload = json_encode($body);
        $headers = ['X-Hub-Signature-256' => $this->signPayload($payload)];

        $this->withHeaders($headers)->postJson('/webhooks/whatsapp/global', $body)->assertOk();
        $this->withHeaders($headers)->postJson('/webhooks/whatsapp/global', $body)->assertOk();

        $this->assertDatabaseCount('inbound_webhook_events', 2);
        $this->assertDatabaseHas('inbound_webhook_events', [
            'provider' => 'whatsapp_global',
        ]);
        $this->assertDatabaseHas('inbound_webhook_events', [
            'provider' => 'whatsapp_msg',
            'event_id' => 'wamid.duplicate-test',
        ]);
    }
}
