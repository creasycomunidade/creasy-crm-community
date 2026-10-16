<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AiKbChunk;
use App\Modules\Integrations\Services\CredentialResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vector storage and similarity search.
 *
 * Uses Qdrant when QDRANT_URL is set in env; otherwise falls back to
 * MySQL JSON storage with in-PHP cosine similarity (suitable for ~50k chunks).
 */
class EmbeddingStore
{
    private const QDRANT_COLLECTION = 'kb_chunks';

    private ?array $resolvedQdrantConfig = null;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function storeEmbedding(AiKbChunk $chunk, array $embedding): void
    {
        // Always persist to MySQL so chunks remain queryable without Qdrant
        $chunk->update(['embedding' => json_encode($embedding)]);

        if ($this->qdrantEnabled()) {
            $this->qdrantUpsert($chunk, $embedding);
        }
    }

    /** Find top-k most similar chunks to the query embedding. */
    public function search(int $kbId, array $queryEmbedding, int $topK = 5): array
    {
        if ($this->qdrantEnabled()) {
            $results = $this->qdrantSearch($kbId, $queryEmbedding, $topK);
            if (! empty($results)) {
                return $results;
            }
            // Fall through to MySQL if Qdrant returns nothing (e.g. collection empty)
        }

        return $this->mysqlSearch($kbId, $queryEmbedding, $topK);
    }

    // -------------------------------------------------------------------------
    // Qdrant
    // -------------------------------------------------------------------------

    private function qdrantEnabled(): bool
    {
        return $this->qdrantConfig()['url'] !== '';
    }

    private function qdrantClient(): \Illuminate\Http\Client\PendingRequest
    {
        $config = $this->qdrantConfig();
        $client = Http::baseUrl($config['url'])
            ->timeout(10)
            ->retry(2, 300);

        $apiKey = $config['api_key'];
        if ($apiKey) {
            $client = $client->withHeaders(['api-key' => $apiKey]);
        }

        return $client;
    }

    /**
     * Prefer the encrypted integration configured in the admin panel.
     * Environment values remain as a backwards-compatible fallback.
     *
     * @return array{url: string, api_key: string}
     */
    private function qdrantConfig(): array
    {
        if ($this->resolvedQdrantConfig !== null) {
            return $this->resolvedQdrantConfig;
        }

        $credentials = CredentialResolver::system()->qdrant();
        $url = trim((string) ($credentials?->url() ?? ''));
        $apiKey = (string) ($credentials?->apiKey() ?? '');

        if ($url === '') {
            $url = trim((string) config('services.qdrant.url', ''));
            $apiKey = (string) config('services.qdrant.api_key', '');
        }

        return $this->resolvedQdrantConfig = [
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
        ];
    }

    private function qdrantUpsert(AiKbChunk $chunk, array $embedding): void
    {
        try {
            $this->ensureQdrantCollection(count($embedding));

            $this->qdrantClient()->put('/collections/'.self::QDRANT_COLLECTION.'/points', [
                'points' => [[
                    'id' => $chunk->id,
                    'vector' => $embedding,
                    'payload' => [
                        'kb_id' => $chunk->kb_id,
                        'document_id' => $chunk->document_id,
                        'chunk_id' => $chunk->id,
                    ],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Qdrant upsert failed, embedding stored in MySQL only', ['error' => $e->getMessage()]);
        }
    }

    private function qdrantSearch(int $kbId, array $queryEmbedding, int $topK): array
    {
        try {
            $resp = $this->qdrantClient()->post('/collections/'.self::QDRANT_COLLECTION.'/points/search', [
                'vector' => $queryEmbedding,
                'limit' => $topK,
                'filter' => [
                    'must' => [['key' => 'kb_id', 'match' => ['value' => $kbId]]],
                ],
                'with_payload' => true,
            ]);

            if (! $resp->successful()) {
                return [];
            }

            $chunkIds = array_column($resp->json('result', []), 'id');
            if (empty($chunkIds)) {
                return [];
            }

            $chunks = AiKbChunk::whereIn('id', $chunkIds)->get()->keyBy('id');
            $results = [];
            foreach ($resp->json('result', []) as $hit) {
                $chunk = $chunks->get($hit['id']);
                if ($chunk) {
                    $results[] = ['chunk' => $chunk, 'score' => $hit['score']];
                }
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Qdrant search failed, falling back to MySQL', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function ensureQdrantCollection(int $dimensions): void
    {
        $client = $this->qdrantClient();
        $check = $client->get('/collections/'.self::QDRANT_COLLECTION);
        if ($check->successful()) {
            return;
        }

        $client->put('/collections/'.self::QDRANT_COLLECTION, [
            'vectors' => ['size' => $dimensions, 'distance' => 'Cosine'],
        ]);
    }

    // -------------------------------------------------------------------------
    // MySQL fallback
    // -------------------------------------------------------------------------

    private function mysqlSearch(int $kbId, array $queryEmbedding, int $topK): array
    {
        $chunks = AiKbChunk::where('kb_id', $kbId)
            ->whereNotNull('embedding')
            ->get();

        return $chunks->map(function (AiKbChunk $chunk) use ($queryEmbedding) {
            return [
                'chunk' => $chunk,
                'score' => $this->cosine($queryEmbedding, $this->unpackEmbedding($chunk->embedding ?? '')),
            ];
        })->sortByDesc('score')->take($topK)->values()->toArray();
    }

    private function unpackEmbedding(string $json): array
    {
        if (empty($json)) {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function cosine(array $a, array $b): float
    {
        if (count($a) !== count($b) || empty($a)) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $v) {
            $dot += $v * $b[$i];
            $na += $v * $v;
            $nb += $b[$i] * $b[$i];
        }
        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
