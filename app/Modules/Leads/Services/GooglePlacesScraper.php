<?php

namespace App\Modules\Leads\Services;

use App\Modules\Broadcasting\Models\UsageMeter;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Models\LeadScrapeJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Scrapes Google Places API (Text Search / Nearby Search) and populates the leads table.
 * Uses the system-level Google Places API key from CredentialResolver (workspace → system).
 */
class GooglePlacesScraper
{
    private const PLACES_URL = 'https://maps.googleapis.com/maps/api/place/textsearch/json';

    private const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    public function __construct(private readonly CredentialResolver $credentials) {}

    public function run(LeadScrapeJob $job): void
    {
        $job->update(['status' => 'running', 'started_at' => now()]);

        try {
            $creds = $this->credentials->googlePlaces();
            $apiKey = $creds?->apiKey();
            if (! $apiKey) {
                throw new \RuntimeException('A chave da Google Places API não está configurada pelo administrador.');
            }

            $count = 0;
            $deadline = microtime(true) + 35;
            $limit = max(1, min((int) ($job->result_limit ?: 20), 50));
            $query = "{$job->keyword} in {$job->location}";
            $pageToken = null;

            do {
                $params = ['query' => $query, 'key' => $apiKey];
                if ($pageToken) {
                    $params['pagetoken'] = $pageToken;
                }

                $res = Http::timeout(30)->retry(2, 500)->get(self::PLACES_URL, $params)->throw()->json();
                $this->assertGoogleStatus($res, 'pesquisa');
                $places = $res['results'] ?? [];

                foreach ($places as $place) {
                    if ($count >= $limit) {
                        break;
                    }
                    $count += $this->upsertPlace($job->workspace_id, $place, $apiKey, $deadline);
                }

                $pageToken = $count < $limit ? ($res['next_page_token'] ?? null) : null;
                if ($pageToken) {
                    sleep(2); // Google requires a short delay before using next_page_token
                }
            } while ($pageToken);

            $job->update(['status' => 'done', 'leads_found' => $count, 'completed_at' => now()]);
            if ($count > 0) {
                UsageMeter::track($job->workspace_id, 'lead_credits', $count);
            }
        } catch (\Throwable $e) {
            Log::error('GooglePlacesScraper error: '.$e->getMessage());
            $job->update(['status' => 'failed', 'error' => $e->getMessage(), 'completed_at' => now()]);
        }
    }

    private function upsertPlace(int $workspaceId, array $place, string $apiKey, float $deadline): int
    {
        $placeId = $place['place_id'] ?? null;
        if (! $placeId) {
            return 0;
        }

        // Fetch details for phone number
        $detailsResponse = Http::timeout(30)->retry(2, 500)->get(self::DETAILS_URL, [
            'place_id' => $placeId,
            'fields' => 'formatted_phone_number,international_phone_number,website,url',
            'key' => $apiKey,
        ])->throw()->json();
        $this->assertGoogleStatus($detailsResponse, 'detalhes do local');
        $details = $detailsResponse['result'] ?? [];

        $geometry = $place['geometry']['location'] ?? [];
        $extractor = app(LeadContactExtractor::class);
        $phone = $extractor->phone((string) ($details['international_phone_number'] ?? $details['formatted_phone_number'] ?? ''));
        $channels = ['phones' => $phone ? [$phone] : []];
        if (! empty($details['website'])) {
            $channels = $extractor->merge($channels, $extractor->website($details['website'], $deadline));
        }
        if (! empty($details['url'])) {
            $channels['links'][] = $details['url'];
        }

        $lead = Lead::firstOrNew(['workspace_id' => $workspaceId, 'google_place_id' => $placeId]);
        $lead->fill([
            'source_provider' => 'google_places',
            'source_id' => $placeId,
            'name' => $place['name'] ?? null,
            ...$extractor->attributes($lead, $channels),
            'website' => $details['website'] ?? null,
            'address' => $place['formatted_address'] ?? null,
            'category' => implode(', ', array_slice($place['types'] ?? [], 0, 3)),
            'rating' => $place['rating'] ?? null,
            'review_count' => $place['user_ratings_total'] ?? 0,
            'lat' => $geometry['lat'] ?? null,
            'lng' => $geometry['lng'] ?? null,
        ])->save();

        return 1;
    }

    private function assertGoogleStatus(array $response, string $operation): void
    {
        $status = (string) ($response['status'] ?? '');
        if (in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            return;
        }

        $message = trim((string) ($response['error_message'] ?? ''));
        throw new \RuntimeException(
            'Google Places recusou a '.$operation.' ('.($status ?: 'resposta inválida').')'
            .($message !== '' ? ': '.$message : '.')
        );
    }
}
