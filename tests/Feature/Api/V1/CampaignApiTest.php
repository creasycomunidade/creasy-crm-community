<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Broadcasting\Models\Campaign;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/campaigns')->assertStatus(401);
    }

    public function test_wrong_scope_returns_403(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/campaigns')->assertStatus(403);
    }

    public function test_list_campaigns_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        Campaign::factory()->count(2)->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->getJson('/api/v1/campaigns')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_create_campaign_returns_201(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CAMPAIGNS_WRITE])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/campaigns', [
                'name' => 'API Campaign',
                'channel' => 'sms',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'API Campaign')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_create_campaign_validation_fails_with_422(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/campaigns', ['name' => 'Missing channel'])
            ->assertStatus(422);
    }

    public function test_show_campaign_with_fresh_stats(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $campaign = Campaign::factory()->create(['workspace_id' => $workspace->id]);

        $res = $this->withToken($token)
            ->getJson("/api/v1/campaigns/{$campaign->id}")
            ->assertOk();

        $this->assertArrayHasKey('stats', $res->json('data'));
    }

    public function test_launch_campaign_returns_ok(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CAMPAIGNS_WRITE])->plainTextToken;
        $campaign = Campaign::factory()->create(['workspace_id' => $workspace->id, 'status' => 'draft']);

        $this->withToken($token)
            ->postJson("/api/v1/campaigns/{$campaign->id}/launch")
            ->assertOk()
            ->assertJsonPath('status', 'queued');
    }

    public function test_recipients_returns_paginated_list(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $campaign = Campaign::factory()->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->getJson("/api/v1/campaigns/{$campaign->id}/recipients")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
