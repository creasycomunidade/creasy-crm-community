<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_dashboard_returns_plan_and_usage(): void
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);

        $response = $this->actingAs($user)->get('/painel/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('client/Dashboard')
            ->has('usage')
            ->where('usage.team_members_count', fn ($v) => is_int($v))
            ->has('workspacesCount')
        );
    }

    public function test_demo_mode_blocks_client_post(): void
    {
        config(['app.demo_mode' => true]);
        $user = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($user)->put(route('client.settings.update'), [
            'locale' => 'en',
            'display_currency' => 'USD',
            'theme' => 'light',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
