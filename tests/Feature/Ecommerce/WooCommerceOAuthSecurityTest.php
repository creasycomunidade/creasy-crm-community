<?php

namespace Tests\Feature\Ecommerce;

use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Ecommerce\Services\StoreConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class WooCommerceOAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_uses_a_one_time_secret_instead_of_a_bare_store_uuid(): void
    {
        $context = $this->createWorkspaceContext();

        $response = $this->actingAs($context['user'])->get(route('client.ecommerce.oauth.connect', [
            'platform' => 'woocommerce',
            'store_url' => 'https://8.8.8.8',
        ]));

        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $store = EcommerceStore::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertStringStartsWith($store->uuid.'.', $query['user_id']);
        $this->assertNotSame($store->uuid, $query['user_id']);
        $this->assertSame(
            hash('sha256', substr($query['user_id'], strlen($store->uuid) + 1)),
            $store->external_meta['woo_oauth_callback_token_hash']
        );
    }

    public function test_callback_rejects_a_bare_uuid_and_does_not_replace_credentials(): void
    {
        $context = $this->createWorkspaceContext();
        $store = EcommerceStore::create([
            'workspace_id' => $context['workspace']->id,
            'platform' => 'woocommerce',
            'name' => 'Loja',
            'domain' => 'https://loja.example',
            'credentials' => ['consumer_key' => 'ck_original', 'consumer_secret' => 'cs_original'],
            'status' => 'connected',
            'external_meta' => ['woo_oauth_callback_token_hash' => hash('sha256', 'valid-token')],
        ]);

        $this->postJson(route('webhooks.ecommerce.woo_auth'), [
            'user_id' => $store->uuid,
            'consumer_key' => 'ck_attacker',
            'consumer_secret' => 'cs_attacker',
        ])->assertStatus(400);

        $this->assertSame('ck_original', $store->fresh()->credentials['consumer_key']);
    }

    public function test_valid_callback_token_is_consumed_after_one_use(): void
    {
        $context = $this->createWorkspaceContext();
        $token = 'one-time-token-with-enough-entropy-for-test';
        $store = EcommerceStore::create([
            'workspace_id' => $context['workspace']->id,
            'platform' => 'woocommerce',
            'name' => 'Loja',
            'domain' => 'https://loja.example',
            'status' => 'pending',
            'external_meta' => ['woo_oauth_callback_token_hash' => hash('sha256', $token)],
        ]);

        $this->mock(StoreConnector::class, function (MockInterface $mock) use ($store): void {
            $mock->shouldReceive('connect')->once()->andReturn([
                'ok' => true,
                'message' => 'ok',
                'store' => $store,
            ]);
        });

        $payload = [
            'user_id' => $store->uuid.'.'.$token,
            'consumer_key' => 'ck_valid',
            'consumer_secret' => 'cs_valid',
        ];
        $this->postJson(route('webhooks.ecommerce.woo_auth'), $payload)->assertOk();
        $this->assertArrayNotHasKey('woo_oauth_callback_token_hash', $store->fresh()->external_meta ?? []);

        $this->postJson(route('webhooks.ecommerce.woo_auth'), $payload)->assertStatus(400);
    }
}
