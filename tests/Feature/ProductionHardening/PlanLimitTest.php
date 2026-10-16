<?php

namespace Tests\Feature\ProductionHardening;

use App\Models\Plan;
use App\Modules\Broadcasting\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Plan-limit enforcement tests.
 */
class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_launch_returns_redirect_when_limit_exhausted(): void
    {
        Queue::fake();

        $data = $this->createWorkspaceContext();
        $user = $data['user'];
        $workspace = $data['workspace'];
        $client = $data['client'];

        $plan = Plan::factory()->create(['limits' => ['campaigns_per_month' => 0]]);
        $this->attachPlanToClient($client, $plan);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'status' => 'draft',
            'channel' => 'sms',
        ]);

        $response = $this->actingAs($user)
            ->post("/painel/broadcasts/campaigns/{$campaign->uuid}/launch");

        $response->assertRedirect('/billing');
        $response->assertSessionHas('upgrade_required');
    }

    public function test_lead_scrape_returns_redirect_when_limit_exhausted(): void
    {
        Queue::fake();

        $data = $this->createWorkspaceContext();
        $user = $data['user'];
        $workspace = $data['workspace'];
        $client = $data['client'];

        $plan = Plan::factory()->create(['limits' => ['lead_credits_per_month' => 0]]);
        $this->attachPlanToClient($client, $plan);

        $response = $this->actingAs($user)->post('/painel/leads/scrape', [
            'query' => 'restaurants',
            'location' => 'Dhaka',
        ]);

        $response->assertRedirect('/billing');
        $response->assertSessionHas('upgrade_required');
    }
}
