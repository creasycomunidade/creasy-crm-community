<?php

namespace Tests\Feature\Ecommerce;

use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Ecommerce\Models\EcommerceStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductSourceFirstUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_is_changed_locally_only_after_woocommerce_accepts_it(): void
    {
        $context = $this->createWorkspaceContext();
        [$store, $product] = $this->wooProduct($context['workspace']->id);

        Http::fake([
            'loja.teste/wp-json/wc/v3/products/42' => Http::response([
                'id' => 42,
                'name' => 'Armação atualizada',
            ], 200),
        ]);

        $this->actingAs($context['user'])
            ->put(route('client.ecommerce.products.update', $product), [
                'name' => 'Armação atualizada',
                'price' => '599.90',
                'inventory_quantity' => 3,
                'status' => 'publish',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ecommerce_products', [
            'id' => $product->id,
            'name' => 'Armação atualizada',
            'price' => '599.90',
            'inventory_quantity' => 3,
            'status' => 'publish',
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/wp-json/wc/v3/products/42')
            && $request['stock_quantity'] === 3);
    }

    public function test_local_product_remains_unchanged_when_source_rejects_update(): void
    {
        $context = $this->createWorkspaceContext();
        [, $product] = $this->wooProduct($context['workspace']->id);

        Http::fake([
            'loja.teste/wp-json/wc/v3/products/42' => Http::response([
                'message' => 'Sem permissão para editar.',
            ], 403),
        ]);

        $this->actingAs($context['user'])
            ->from(route('client.ecommerce.products.index'))
            ->put(route('client.ecommerce.products.update', $product), [
                'name' => 'Nome indevido',
                'price' => '1.00',
                'inventory_quantity' => 999,
                'status' => 'publish',
            ])
            ->assertRedirect(route('client.ecommerce.products.index'))
            ->assertSessionHas('error', 'Sem permissão para editar.');

        $this->assertDatabaseHas('ecommerce_products', [
            'id' => $product->id,
            'name' => 'Armação original',
            'price' => '499.90',
            'inventory_quantity' => 7,
        ]);
    }

    /**
     * @return array{EcommerceStore, EcommerceProduct}
     */
    private function wooProduct(int $workspaceId): array
    {
        $store = EcommerceStore::create([
            'workspace_id' => $workspaceId,
            'platform' => 'woocommerce',
            'name' => 'Loja teste',
            'domain' => 'https://loja.teste',
            'credentials' => ['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test'],
            'status' => 'connected',
        ]);
        $product = EcommerceProduct::create([
            'workspace_id' => $workspaceId,
            'store_id' => $store->id,
            'external_id' => '42',
            'platform' => 'woocommerce',
            'name' => 'Armação original',
            'sku' => 'ARM-42',
            'price' => 499.90,
            'inventory_quantity' => 7,
            'status' => 'publish',
            'raw' => ['id' => 42],
        ]);

        return [$store, $product];
    }
}
