<?php

namespace Tests\Feature\ProductionHardening;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\Billing\StripeGateway;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Webhook HMAC signature tests — valid/invalid/missing.
 */
class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeWaba(): array
    {
        $user = $this->createWorkspaceContext();
        $waba = WhatsappBusinessAccount::factory()->create([
            'workspace_id' => $user['workspace']->id,
            'waba_id' => 'WABA_SIG_TEST',
            'webhook_verify_token' => 'test-verify-sig',
            'credentials' => ['app_secret_override' => 'secret123'],
            'status' => 'active',
        ]);
        ChannelAccount::create([
            'workspace_id' => $user['workspace']->id,
            'channel' => 'whatsapp',
            'display_name' => 'Test',
            'phone_number_id' => 'PH_001',
            'business_account_id' => $waba->waba_id,
            'status' => 'active',
        ]);

        return [$waba, $user];
    }

    public function test_whatsapp_webhook_accepts_valid_hmac_signature(): void
    {
        [$waba, $user] = $this->makeWaba();
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);
        $sig = 'sha256='.hash_hmac('sha256', $payload, 'secret123');

        $response = $this->withHeaders(['X-Hub-Signature-256' => $sig])
            ->postJson('/webhooks/whatsapp/test-verify-sig', json_decode($payload, true));

        $response->assertStatus(200);
    }

    public function test_whatsapp_webhook_rejects_invalid_hmac_signature(): void
    {
        [$waba, $user] = $this->makeWaba();
        $payload = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);

        $response = $this->withHeaders(['X-Hub-Signature-256' => 'sha256=invalid'])
            ->postJson('/webhooks/whatsapp/test-verify-sig', json_decode($payload, true));

        $response->assertStatus(401);
    }

    public function test_stripe_webhook_rejects_missing_secret_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        // Gateway with no webhookSecret (empty string = not configured)
        $gateway = new StripeGateway('sk_test', '', 'http://success', 'http://cancel');
        $request = Request::create('/webhooks/stripe', 'POST', [], [], [], [], json_encode(['type' => 'test']));
        $response = $gateway->handleWebhook($request);

        $this->assertEquals(401, $response->getStatusCode());

        app()->detectEnvironment(fn () => 'testing');
    }

    public function test_webhook_idempotency_allows_first_delivery_and_dedupes_second(): void
    {
        $service = app(WebhookIdempotencyService::class);

        $this->assertTrue($service->isNewEvent('test_provider', 'evt_001'));
        $this->assertFalse($service->isNewEvent('test_provider', 'evt_001'));
        $this->assertTrue($service->isNewEvent('test_provider', 'evt_002'));
    }

    public function test_global_whatsapp_webhook_requires_signature_in_production(): void
    {
        IntegrationConfig::create([
            'provider' => 'meta_app',
            'label' => 'Meta',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['app_id' => 'prod_app', 'app_secret' => ''],
        ]);

        app()->detectEnvironment(fn () => 'production');

        $this->postJson('/webhooks/whatsapp/global', ['entry' => []])
            ->assertUnauthorized();

        app()->detectEnvironment(fn () => 'testing');
    }
}
