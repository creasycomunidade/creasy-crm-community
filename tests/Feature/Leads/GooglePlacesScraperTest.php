<?php

namespace Tests\Feature\Leads;

use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Integrations\Services\Credentials\GenericCredentials;
use App\Modules\Leads\Models\LeadScrapeJob;
use App\Modules\Leads\Services\GooglePlacesScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class GooglePlacesScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_supported_credential_method_and_isolates_places_by_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $resolver = Mockery::mock(CredentialResolver::class);
        $resolver->shouldReceive('googlePlaces')
            ->twice()
            ->andReturn(new GenericCredentials(['api_key' => 'test-key']));

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/details/')) {
                return Http::response([
                    'status' => 'OK',
                    'result' => ['formatted_phone_number' => '+55 48 99999-0000'],
                ]);
            }

            return Http::response([
                'status' => 'OK',
                'results' => [[
                    'place_id' => 'same-google-place',
                    'name' => 'Ótica Teste',
                    'formatted_address' => 'Florianópolis, SC',
                    'types' => ['store'],
                    'geometry' => ['location' => ['lat' => -27.5, 'lng' => -48.5]],
                ]],
            ]);
        });

        $scraper = new GooglePlacesScraper($resolver);
        foreach ([$first, $second] as $context) {
            $job = LeadScrapeJob::create([
                'workspace_id' => $context['workspace']->id,
                'keyword' => 'óticas',
                'location' => 'Florianópolis',
                'status' => 'pending',
            ]);
            $scraper->run($job);
            $this->assertSame('done', $job->fresh()->status);
        }

        $this->assertDatabaseCount('leads', 2);
        $this->assertDatabaseHas('leads', [
            'workspace_id' => $first['workspace']->id,
            'google_place_id' => 'same-google-place',
        ]);
        $this->assertDatabaseHas('leads', [
            'workspace_id' => $second['workspace']->id,
            'google_place_id' => 'same-google-place',
        ]);
    }

    public function test_it_records_google_api_configuration_errors(): void
    {
        $context = $this->createWorkspaceContext();
        $resolver = Mockery::mock(CredentialResolver::class);
        $resolver->shouldReceive('googlePlaces')
            ->once()
            ->andReturn(new GenericCredentials(['api_key' => 'test-key']));
        Http::fake([
            '*' => Http::response([
                'status' => 'REQUEST_DENIED',
                'error_message' => 'API not enabled',
            ]),
        ]);

        $job = LeadScrapeJob::create([
            'workspace_id' => $context['workspace']->id,
            'keyword' => 'óticas',
            'location' => 'Florianópolis',
            'status' => 'pending',
        ]);

        (new GooglePlacesScraper($resolver))->run($job);

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertStringContainsString('REQUEST_DENIED', $job->fresh()->error);
    }
}
