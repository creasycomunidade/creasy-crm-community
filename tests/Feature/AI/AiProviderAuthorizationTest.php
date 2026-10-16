<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_workspace_administrator_can_view_or_change_provider_secrets(): void
    {
        $context = $this->createWorkspaceContext();
        $member = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'workspace_id' => $context['workspace']->id,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->get(route('client.ai.providers.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->put(route('client.ai.providers.update', 'gemini'), [
                'api_key' => 'secret-that-must-not-be-saved',
                'enabled' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ai_provider_configs', [
            'workspace_id' => $context['workspace']->id,
            'provider' => 'gemini',
        ]);
    }
}
