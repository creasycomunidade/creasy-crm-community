<?php

namespace Tests\Unit;

use App\Modules\Ecommerce\Services\Clients\ShopifyClient;
use App\Modules\Ecommerce\Services\Credentials\StoreCredentials;
use App\Modules\Ecommerce\Services\PayloadNormalizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyClientProductsTest extends TestCase
{
    private function client(): ShopifyClient
    {
        return new ShopifyClient('demo-shop.myshopify.com', new StoreCredentials(['access_token' => 'shpat_x']));
    }

    private function gqlBody(array $edges, bool $hasNext, ?string $endCursor): array
    {
        return ['data' => ['products' => [
            'edges' => $edges,
            'pageInfo' => ['hasNextPage' => $hasNext, 'endCursor' => $endCursor],
        ]]];
    }

    public function test_fetch_products_uses_graphql_paginates_and_reshapes(): void
    {
        $node1 = ['node' => [
            'id' => 'gid://shopify/Product/111',
            'title' => 'Widget',
            'status' => 'ACTIVE',
            'totalInventory' => 4,
            'featuredImage' => ['url' => 'https://cdn/widget.png'],
            'variants' => ['edges' => [['node' => ['sku' => 'W-1', 'price' => '9.99']]]],
        ]];
        $node2 = ['node' => [
            'id' => 'gid://shopify/Product/222',
            'title' => 'Gadget',
            'status' => 'DRAFT',
            'totalInventory' => 0,
            'featuredImage' => null,
            'variants' => ['edges' => [['node' => ['sku' => 'G-1', 'price' => '19.99']]]],
        ]];

        Http::fakeSequence('demo-shop.myshopify.com/admin/api/*/graphql.json')
            ->push($this->gqlBody([$node1], true, 'CURSOR_1'), 200)
            ->push($this->gqlBody([$node2], false, null), 200);

        $client = $this->client();

        $p1 = $client->fetchProducts();
        $this->assertCount(1, $p1['products']);
        $this->assertSame('CURSOR_1', $p1['next'], 'should surface the GraphQL endCursor when hasNextPage');

        $p2 = $client->fetchProducts($p1['next']);
        $this->assertCount(1, $p2['products']);
        $this->assertNull($p2['next'], 'should terminate when hasNextPage is false');

        // Reshaped payload must satisfy the existing normalizer (shared with webhooks).
        $normalizer = new PayloadNormalizer;
        $mapped = $normalizer->mapProduct('shopify', $p1['products'][0]);
        $this->assertSame('111', $mapped['external_id'], 'GID must reduce to the numeric id the webhook also uses');
        $this->assertSame('Widget', $mapped['name']);
        $this->assertSame('W-1', $mapped['sku']);
        $this->assertSame(9.99, $mapped['price']);
        $this->assertSame(4, $mapped['inventory_quantity'], 'totalInventory must flow through as inventory');
        $this->assertSame('active', $mapped['status'], 'status must be lowercased to match REST/webhook shape');
        $this->assertSame('https://cdn/widget.png', $mapped['image_url']);
    }

    public function test_fetch_products_throws_on_graphql_errors_so_job_retries(): void
    {
        Http::fake([
            '*/graphql.json' => Http::response([
                'errors' => [['message' => 'Access denied for products field']],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->client()->fetchProducts();
    }
}
