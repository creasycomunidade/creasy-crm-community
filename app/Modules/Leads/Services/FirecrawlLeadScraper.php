<?php

namespace App\Modules\Leads\Services;

use App\Modules\Broadcasting\Models\UsageMeter;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadScrapeJob;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirecrawlLeadScraper
{
    private const DEFAULT_BASE_URL = 'https://api.firecrawl.dev';

    public function __construct(private readonly CredentialResolver $credentials) {}

    public function run(LeadScrapeJob $job): void
    {
        $job->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $credentials = $this->credentials->firecrawl();
            $apiKey = $credentials?->apiKey();
            $baseUrl = rtrim((string) ($credentials?->get('base_url', self::DEFAULT_BASE_URL) ?: self::DEFAULT_BASE_URL), '/');
            if (! $apiKey) {
                throw new \RuntimeException('A integração Firecrawl não está configurada ou ativada pelo administrador.');
            }

            $limit = max(1, min((int) ($job->result_limit ?: 20), 50));
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(90)
                ->retry(2, 1000, throw: false)
                ->post($baseUrl.'/v2/search', [
                    'query' => trim("{$job->keyword} {$job->location} contato WhatsApp email telefone"),
                    'limit' => $limit,
                    'sources' => [['type' => 'web']],
                    'country' => 'BR',
                    'location' => trim($job->location.', Brasil'),
                    'timeout' => 60000,
                    'ignoreInvalidURLs' => true,
                    'scrapeOptions' => [
                        'formats' => [['type' => 'markdown'], ['type' => 'links']],
                        'onlyMainContent' => false,
                        'removeBase64Images' => true,
                        'blockAds' => true,
                    ],
                ]);

            $this->assertSuccessful($response);

            $count = 0;
            $deadline = microtime(true) + 35;
            foreach ((array) $response->json('data.web', []) as $result) {
                $count += $this->upsertResult((int) $job->workspace_id, (array) $result, $job, $deadline);
            }

            $job->update([
                'status' => 'done',
                'leads_found' => $count,
                'completed_at' => now(),
            ]);
            if ($count > 0) {
                UsageMeter::track($job->workspace_id, 'lead_credits', $count);
            }
        } catch (\Throwable $e) {
            Log::error('FirecrawlLeadScraper error', [
                'job_id' => $job->id,
                'workspace_id' => $job->workspace_id,
                'message' => $e->getMessage(),
            ]);
            $job->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 2000, ''),
                'completed_at' => now(),
            ]);
        }
    }

    private function upsertResult(int $workspaceId, array $result, LeadScrapeJob $job, float $deadline): int
    {
        $url = trim((string) ($result['url'] ?? data_get($result, 'metadata.sourceURL', '')));
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return 0;
        }

        $content = implode("\n", array_filter([
            $result['description'] ?? null,
            $result['markdown'] ?? null,
            $result['html'] ?? null,
            data_get($result, 'metadata.description'),
        ]));
        $title = trim(strip_tags((string) ($result['title'] ?? data_get($result, 'metadata.title', ''))));
        $name = $this->businessName($title, $url);
        $extractor = app(LeadContactExtractor::class);
        $channels = $extractor->extract($content, (array) ($result['links'] ?? []));
        if (empty($channels['whatsapp']) || empty($channels['emails'])) {
            $channels = $extractor->merge($channels, $extractor->website($url, $deadline));
        }
        $sourceId = hash('sha256', Str::lower($url));

        $lead = Lead::firstOrNew(
            [
                'workspace_id' => $workspaceId,
                'source_provider' => 'firecrawl',
                'source_id' => $sourceId,
            ]
        );
        $lead->fill([
            'name' => $name,
            ...$extractor->attributes($lead, $channels),
            'website' => Str::limit($url, 512, ''),
            'address' => $this->addressSnippet($content, $job->location),
            'city' => Str::limit($job->location, 128, ''),
            'country' => 'Brasil',
            'category' => Str::limit($job->keyword, 128, ''),
        ])->save();

        return 1;
    }

    private function businessName(string $title, string $url): string
    {
        $candidate = preg_split('/\s+[|–—-]\s+/u', $title)[0] ?? '';
        if (trim($candidate) !== '') {
            return Str::limit(trim($candidate), 256, '');
        }

        $host = preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST));

        return Str::limit($host !== '' ? $host : 'Empresa encontrada na web', 256, '');
    }

    private function addressSnippet(string $content, string $location): ?string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if ($plain === '') {
            return $location !== '' ? Str::limit($location, 512, '') : null;
        }

        $position = mb_stripos($plain, $location);
        if ($position === false) {
            return $location !== '' ? Str::limit($location, 512, '') : null;
        }

        return Str::limit(mb_substr($plain, max(0, $position - 80), 260), 512, '');
    }

    private function assertSuccessful(Response $response): void
    {
        if ($response->successful() && $response->json('success') === true) {
            return;
        }

        $message = trim((string) ($response->json('error') ?? $response->json('message') ?? ''));
        $friendly = match ($response->status()) {
            401 => 'A chave da API Firecrawl é inválida ou foi revogada.',
            402 => 'A conta Firecrawl não possui créditos suficientes para esta pesquisa.',
            408 => 'A pesquisa do Firecrawl excedeu o tempo limite.',
            429 => 'O limite de requisições ou concorrência do Firecrawl foi atingido. Tente novamente em alguns minutos.',
            default => $message !== '' ? $message : 'O Firecrawl recusou a pesquisa (HTTP '.$response->status().').',
        };

        throw new \RuntimeException($friendly);
    }
}
