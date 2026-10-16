<?php

namespace Tests\Feature\Polish;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_webhook_endpoint_exists(): void
    {
        // Simply confirm the endpoint is registered and returns something
        // (actual Stripe signature verification is tested in unit tests)
        $response = $this->postJson('/webhooks/stripe', []);

        // Unauthenticated webhook calls get 400 (bad signature) not 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_paypal_webhook_endpoint_exists(): void
    {
        $response = $this->postJson('/webhooks/paypal', []);

        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_paddle_webhook_endpoint_exists(): void
    {
        $response = $this->postJson('/webhooks/paddle', []);

        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
