<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\Client;
use App\Models\ClientSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Models\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_request_when_under_limit(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['campaigns_per_month' => 5],
        ]);
        $user = $this->clientUserWithPlan($plan);

        $response = $this->actingAs($user)->post('/painel/broadcasts/campaigns', [
            'name' => 'Test Campaign',
            'channel' => 'whatsapp',
            'body' => 'Hello {{name}}',
            'segment_ids' => [],
        ]);

        $this->assertNotEquals(402, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_request_when_at_limit(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['campaigns_per_month' => 2],
        ]);
        $user = $this->clientUserWithPlan($plan);

        $period = (int) now()->format('Ym');
        UsageMeter::create([
            'workspace_id' => $user->workspace_id,
            'metric' => 'campaigns_per_month',
            'period' => $period,
            'value' => 2,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/painel/broadcasts/campaigns', [
                'name' => 'Test Campaign',
                'channel' => 'whatsapp',
                'body' => 'Hello',
            ]);

        $this->assertContains($response->getStatusCode(), [402, 302]);
    }

    #[Test]
    public function null_limit_means_unlimited(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['campaigns_per_month' => null],
        ]);
        $user = $this->clientUserWithPlan($plan);

        $period = (int) now()->format('Ym');
        UsageMeter::create([
            'workspace_id' => $user->workspace_id,
            'metric' => 'campaigns_per_month',
            'period' => $period,
            'value' => 99999,
        ]);

        $response = $this->actingAs($user)->post('/painel/broadcasts/campaigns', [
            'name' => 'Test Campaign',
            'channel' => 'whatsapp',
            'body' => 'Hello',
        ]);

        $this->assertNotEquals(402, $response->getStatusCode());
    }

    private function clientUserWithPlan(Plan $plan): User
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ]);

        ClientSubscription::create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => 'client',
            'client_id' => $client->id,
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::factory()->create([
            'owner_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $user->update(['workspace_id' => $workspace->id]);
        $user->refresh();

        return $user;
    }
}
