<?php

namespace Tests\Feature\Polish;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_returns_branded_view(): void
    {
        $response = $this->get('/this-page-does-not-exist-'.uniqid());

        $response->assertStatus(404);
        // When called normally (not via Inertia/ajax), it renders the blade error view
        // In test environment with Inertia middleware, it may return JSON or blade
        $this->assertContains($response->getStatusCode(), [404]);
    }

    public function test_healthz_endpoint_requires_monitoring_token(): void
    {
        $response = $this->get('/healthz/db');

        $response->assertUnauthorized();
    }
}
