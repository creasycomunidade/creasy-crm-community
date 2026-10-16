<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_portuguese_legal_pages_are_published_and_public(): void
    {
        $this->seed(CmsPageSeeder::class);

        foreach (['privacy', 'terms', 'cookies'] as $slug) {
            $response = $this->get('/p/'.$slug);
            $response->assertOk();
            $response->assertSee('30 de agosto de 2026');
        }

        $this->assertStringContainsString('LGPD', CmsPage::where('slug', 'privacy')->value('content'));
        $this->assertStringContainsString('Estes Termos regulam', CmsPage::where('slug', 'terms')->value('content'));
        $this->assertStringContainsString('Cookies', CmsPage::where('slug', 'cookies')->value('content'));
    }
}
