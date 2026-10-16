<?php

namespace Tests\Feature\Polish;

use App\Models\Plan;
use App\Modules\Broadcasting\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforce_limit_redirects_with_upgrade_flash(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'client' => $client] = $this->createWorkspaceContext();

        $plan = Plan::factory()->create(['limits' => ['campaigns_per_month' => 0]]);
        $this->attachPlanToClient($client, $plan);

        // Create a real campaign so route model binding resolves
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->post(route('client.campaigns.launch', $campaign->uuid));

        // The EnforceLimit middleware redirects to /billing with flash data
        $response->assertRedirect();
        $response->assertSessionHas('upgrade_required', true);
        $response->assertSessionHas('upgrade_reason');
    }

    public function test_flash_upgrade_required_is_passed_to_inertia_props(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();

        // Manually flash the session with upgrade data
        session()->flash('upgrade_required', true);
        session()->flash('upgrade_reason', 'You have reached your campaigns_per_month limit.');

        $response = $this->actingAs($user)
            ->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('flash.upgrade_required', true)
            ->where('flash.upgrade_reason', fn ($v) => str_contains($v, 'campaigns'))
        );
    }
}
