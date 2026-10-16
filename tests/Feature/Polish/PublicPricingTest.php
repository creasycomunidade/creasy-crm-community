<?php

namespace Tests\Feature\Polish;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_route_is_registered(): void
    {
        // The pricing route must exist (not 404) — it may redirect in test env due to Inertia
        $response = $this->get(route('pricing'));

        $this->assertNotEquals(404, $response->getStatusCode(), 'Pricing route should be registered');
        $this->assertNotEquals(500, $response->getStatusCode(), 'Pricing route should not throw');
    }

    public function test_pricing_page_accessible_while_authenticated(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $user = $ctx['user'];

        $response = $this->actingAs($user)->get(route('pricing'));

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_register_with_plan_redirects(): void
    {
        $plan = Plan::factory()->create(['slug' => 'pro', 'monthly_price_cents' => 2900]);

        $response = $this->post(route('register'), [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'plan_id' => $plan->id,
            'cycle' => 'monthly',
        ]);

        $response->assertRedirect();
    }
}
