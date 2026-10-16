<?php

namespace Tests\Feature\Community;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CommunitySaasRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_routes_are_not_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression(
                '~^(admin/(plans|subscriptions|payments|payment-gateways|coupons|tax-rates)|painel/(subscription|billing|pricing|checkout|coupon)|webhooks/(stripe|paypal|paddle|mercadopago)|pricing)(/|$)~',
                $route->uri()
            );
        }
        $this->assertFalse(class_exists(\App\Services\Billing\BillingGatewayRegistry::class));
        $this->assertFalse(class_exists(\App\Models\Plan::class));
    }

    public function test_core_pages_and_search_do_not_query_commercial_tables(): void
    {
        $admin = $this->createSuperAdmin();
        ['user' => $user] = $this->createWorkspaceContext();
        $queries = [];
        DB::listen(function ($event) use (&$queries) { $queries[] = $event->sql; });
        foreach (['/admin/dashboard', '/admin/clients', '/admin/search?q=teste'] as $url) {
            $this->actingAs($admin, 'admin')->get($url)->assertOk();
        }
        foreach (['/painel/inbox', '/painel/search?q=team'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('~\b(plans|subscriptions|client_subscriptions|payment_transactions|payment_gateway_configs|coupons|tax_rates|billing_events)\b~i', $query);
        }
    }

    public function test_registration_ignores_legacy_paid_plan_parameters(): void
    {
        $this->post('/register', [
            'name' => 'Community Owner', 'email' => 'community-owner@example.test',
            'password' => 'CommunityTest123!', 'password_confirmation' => 'CommunityTest123!',
            'agree_terms' => true, 'plan_id' => 999999, 'cycle' => 'year',
        ])->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard', absolute: false));
        $this->assertAuthenticated();
        foreach (['plans', 'subscriptions', 'client_subscriptions', 'payment_transactions', 'payment_gateway_configs', 'coupons', 'tax_rates', 'billing_events'] as $table) {
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable($table), $table);
        }
    }
}
