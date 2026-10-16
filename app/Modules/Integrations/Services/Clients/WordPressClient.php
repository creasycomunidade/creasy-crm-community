<?php

namespace App\Modules\Integrations\Services\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Support\PublicHttpUrl;

class WordPressClient
{
    public function __construct(private readonly array $credentials) {}

    private function http(): PendingRequest
    {
        $base = rtrim((string) ($this->credentials['url'] ?? ''), '/').'/wp-json/wp/v2';

        return Http::timeout(25)
            ->withOptions(PublicHttpUrl::requestOptions($base))
            ->withBasicAuth($this->credentials['username'] ?? '', $this->credentials['application_password'] ?? '')
            ->acceptJson()
            ->baseUrl($base);
    }

    public function list(string $type): array
    {
        $response = $this->http()->get('/'.$type, ['context' => 'edit', 'per_page' => 50, 'status' => 'any']);
        if (! $response->successful()) {
            throw new \RuntimeException($response->json('message') ?: 'O WordPress recusou a listagem.');
        }

        return collect($response->json())->map(fn ($item) => [
            'id' => $item['id'],
            'type' => $item['type'] ?? rtrim($type, 's'),
            'title' => $item['title']['raw'] ?? $item['title']['rendered'] ?? '',
            'content' => $item['content']['raw'] ?? $item['content']['rendered'] ?? '',
            'status' => $item['status'] ?? 'draft',
            'link' => $item['link'] ?? null,
            'modified' => $item['modified'] ?? null,
        ])->all();
    }

    public function save(string $type, ?int $id, array $data): array
    {
        $payload = ['title' => $data['title'], 'content' => $data['content'], 'status' => $data['status']];
        $response = $id
            ? $this->http()->post("/{$type}/{$id}", $payload)
            : $this->http()->post("/{$type}", $payload);
        if (! $response->successful()) {
            throw new \RuntimeException($response->json('message') ?: 'O WordPress recusou a alteração.');
        }

        return $response->json();
    }
}
