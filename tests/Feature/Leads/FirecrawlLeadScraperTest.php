<?php

namespace Tests\Feature\Leads;

use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Integrations\Services\Credentials\GenericCredentials;
use App\Modules\Leads\Models\LeadScrapeJob;
use App\Modules\Leads\Services\FirecrawlLeadScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirecrawlLeadScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_searches_with_firecrawl_and_keeps_results_isolated_by_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $resolver = Mockery::mock(CredentialResolver::class);
        $resolver->shouldReceive('firecrawl')
            ->twice()
            ->andReturn(new GenericCredentials([
                'api_key' => 'fc-test',
                'base_url' => 'https://api.firecrawl.dev',
            ]));

        Http::fake([
            'https://api.firecrawl.dev/v2/search' => Http::response([
                'success' => true,
                'data' => [
                    'web' => [[
                        'url' => 'https://otica-exemplo.com.br/contato',
                        'title' => 'Ótica Exemplo | Florianópolis',
                        'description' => 'Atendimento em Florianópolis',
                        'markdown' => "Telefone (48) 99999-1234\nE-mail: contato@otica-exemplo.com.br",
                    ]],
                ],
                'creditsUsed' => 2,
            ]),
        ]);

        $scraper = new FirecrawlLeadScraper($resolver);
        foreach ([$first, $second] as $context) {
            $job = LeadScrapeJob::create([
                'workspace_id' => $context['workspace']->id,
                'source' => 'firecrawl',
                'keyword' => 'óticas',
                'location' => 'Florianópolis, SC',
                'result_limit' => 10,
                'status' => 'pending',
            ]);
            $scraper->run($job);
            $this->assertSame('done', $job->fresh()->status);
        }

        $this->assertDatabaseCount('leads', 2);
        foreach ([$first, $second] as $context) {
            $this->assertDatabaseHas('leads', [
                'workspace_id' => $context['workspace']->id,
                'source_provider' => 'firecrawl',
                'name' => 'Ótica Exemplo',
                'phone' => '+5548999991234',
                'email' => 'contato@otica-exemplo.com.br',
            ]);
        }

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.firecrawl.dev/v2/search'
            && $request->hasHeader('Authorization', 'Bearer fc-test')
            && data_get($request->data(), 'country') === 'BR'
            && data_get($request->data(), 'limit') === 10
        );
    }

    public function test_it_records_firecrawl_rate_limit_errors_without_leaking_the_key(): void
    {
        $context = $this->createWorkspaceContext();
        $resolver = Mockery::mock(CredentialResolver::class);
        $resolver->shouldReceive('firecrawl')
            ->once()
            ->andReturn(new GenericCredentials(['api_key' => 'fc-super-secret']));
        Http::fake(['*' => Http::response(['success' => false], 429)]);

        $job = LeadScrapeJob::create([
            'workspace_id' => $context['workspace']->id,
            'source' => 'firecrawl',
            'keyword' => 'clínicas',
            'location' => 'São Paulo, SP',
            'status' => 'pending',
        ]);

        (new FirecrawlLeadScraper($resolver))->run($job);

        $failed = $job->fresh();
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('limite', $failed->error);
        $this->assertStringNotContainsString('fc-super-secret', $failed->error);
    }
}
