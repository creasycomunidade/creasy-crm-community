<?php

namespace Tests\Feature\Ecommerce;

use App\Modules\Ecommerce\Jobs\ProcessEcommerceWebhookJob;
use App\Modules\Ecommerce\Jobs\RegisterStoreWebhooksJob;
use App\Modules\Ecommerce\Jobs\SyncStoreCustomersJob;
use App\Modules\Ecommerce\Jobs\SyncStoreProductsJob;
use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Ecommerce\Services\Clients\MedusaClient;
use App\Modules\Ecommerce\Services\Credentials\StoreCredentials;
use App\Modules\Ecommerce\Services\PayloadNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MedusaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_can_connect_a_medusa_store_with_an_encrypted_secret_key(): void
    {
        $context = $this->createWorkspaceContext();
        Queue::fake();
        Http::fake([
            'https://8.8.8.8/admin/stores*' => Http::response([
                'stores' => [['id' => 'store_01', 'name' => 'Aurora Medusa']],
                'count' => 1,
                'limit' => 1,
                'offset' => 0,
            ]),
        ]);

        $this->actingAs($context['user'])->post(route('client.ecommerce.stores.store'), [
            'platform' => 'medusa',
            'name' => 'Loja Aurora',
            'domain' => 'https://8.8.8.8',
            'credentials' => ['secret_api_key' => 'sk_medusa_test'],
        ])->assertSessionHasNoErrors();

        $store = EcommerceStore::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertSame('medusa', $store->platform);
        $this->assertSame('connected', $store->status);
        $this->assertSame('sk_medusa_test', $store->credentials['secret_api_key']);
        $this->assertSame('store_01', $store->external_meta['medusa_store_id']);
        Queue::assertPushed(RegisterStoreWebhooksJob::class);
        Queue::assertPushed(SyncStoreCustomersJob::class, fn ($job) => $job->queue === 'ecommerce');
        Queue::assertPushed(SyncStoreProductsJob::class, fn ($job) => $job->queue === 'ecommerce');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Basic sk_medusa_test'));
    }

    public function test_medusa_client_paginates_and_normalizes_orders_and_products(): void
    {
        Http::fake([
            'https://8.8.8.8/admin/customers*' => Http::response([
                'customers' => [['id' => 'cus_1', 'email' => 'cliente@example.com']],
                'count' => 101,
                'limit' => 100,
                'offset' => 0,
            ]),
        ]);
        $client = new MedusaClient('https://8.8.8.8', new StoreCredentials(['secret_api_key' => 'sk_test']));
        $page = $client->fetchCustomers();
        $this->assertSame('100', $page['next']);
        $this->assertSame('cus_1', $page['customers'][0]['id']);

        $normalizer = app(PayloadNormalizer::class);
        $product = $normalizer->mapProduct('medusa', [
            'id' => 'prod_1',
            'title' => 'Notebook',
            'status' => 'published',
            'thumbnail' => 'https://cdn.example.com/notebook.jpg',
            'variants' => [[
                'sku' => 'NOTE-1',
                'inventory_quantity' => 7,
                'prices' => [['amount' => 3499.90]],
            ]],
        ]);
        $this->assertSame('medusa', $product['platform']);
        $this->assertSame(3499.90, $product['price']);
        $this->assertSame(7, $product['inventory_quantity']);

        $event = $normalizer->normalize('medusa', 'order.placed', [
            'id' => 'order_1',
            'display_id' => 1042,
            'email' => 'cliente@example.com',
            'currency_code' => 'brl',
            'total' => 3499.90,
            'created_at' => '2026-08-31T12:00:00Z',
            'shipping_address' => ['first_name' => 'João', 'last_name' => 'Silva', 'phone' => '+5547999999999'],
            'items' => [['title' => 'Notebook', 'quantity' => 1, 'unit_price' => 3499.90]],
        ], 'Loja Aurora');
        $this->assertSame('order.placed', $event['event_type']);
        $this->assertSame('#1042', $event['order']['number']);
        $this->assertSame('+5547999999999', $event['contact']['phone_e164']);
    }

    public function test_medusa_uses_zero_install_automatic_sync(): void
    {
        $owner = $this->createWorkspaceContext();
        $store = EcommerceStore::create([
            'workspace_id' => $owner['workspace']->id,
            'platform' => 'medusa',
            'name' => 'Loja Medusa',
            'domain' => 'https://8.8.8.8',
            'credentials' => ['secret_api_key' => 'sk_secret'],
            'webhook_secret' => str_repeat('a', 40),
            'status' => 'connected',
        ]);

        (new RegisterStoreWebhooksJob($store->id))->handle();

        $store->refresh();
        $this->assertTrue($store->external_meta['webhooks_registered']);
        $this->assertSame('automatic_sync', $store->external_meta['webhook_setup']);
        $this->assertSame(5, $store->external_meta['sync_interval_minutes']);
        $this->assertFalse(Route::has('client.ecommerce.stores.medusa-subscriber'));
    }

    public function test_medusa_explains_when_storefront_url_is_used(): void
    {
        Http::fake([
            'https://8.8.8.8/admin/stores*' => Http::response(
                '<html><title>Loja</title></html>',
                404,
                ['Content-Type' => 'text/html; charset=utf-8'],
            ),
        ]);

        $client = new MedusaClient('https://8.8.8.8', new StoreCredentials(['secret_api_key' => 'sk_test']));
        $result = $client->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('site/vitrine', $result['message']);
        $this->assertStringContainsString('/admin/stores', $result['message']);
    }

    public function test_medusa_webhook_requires_a_valid_signature_and_dispatches_once(): void
    {
        Queue::fake();
        $context = $this->createWorkspaceContext();
        $secret = str_repeat('b', 40);
        $store = EcommerceStore::create([
            'workspace_id' => $context['workspace']->id,
            'platform' => 'medusa',
            'name' => 'Loja Medusa',
            'domain' => 'https://8.8.8.8',
            'credentials' => ['secret_api_key' => 'sk_secret'],
            'webhook_secret' => $secret,
            'status' => 'connected',
        ]);
        $payload = json_encode([
            'event' => 'order.placed',
            'event_id' => 'evt_01',
            'data' => ['id' => 'order_01'],
        ], JSON_THROW_ON_ERROR);
        $url = route('webhooks.ecommerce.medusa', ['store' => $store, 'token' => $secret]);

        $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RESPONDESK_SIGNATURE' => 'invalid',
        ], $payload)->assertUnauthorized();

        $signature = hash_hmac('sha256', $payload, $secret);
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RESPONDESK_SIGNATURE' => $signature,
        ];
        $this->call('POST', $url, [], [], [], $server, $payload)->assertOk();
        $this->call('POST', $url, [], [], [], $server, $payload)->assertOk()->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(ProcessEcommerceWebhookJob::class, 1);
    }
}
