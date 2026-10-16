<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\ClientSetting;
use App\Models\User;
use App\Services\WorkspaceLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_client_administrator_can_create_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        $staff = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->assertTrue($context['user']->can('create', \App\Models\Workspace::class));
        $this->assertFalse($staff->can('create', \App\Models\Workspace::class));
    }

    public function test_workspace_limit_is_counted_across_the_whole_client(): void
    {
        $context = $this->createWorkspaceContext();
        $plan = Plan::factory()->create(['limits' => ['workspaces' => 1]]);
        $this->attachPlanToClient($context['client'], $plan);

        $limits = app(WorkspaceLimitService::class);

        $this->assertSame(1, $limits->countFor($context['user']));
        $this->assertFalse($limits->canCreate($context['user']));
    }

    public function test_client_can_have_an_explicit_unlimited_workspace_override(): void
    {
        $context = $this->createWorkspaceContext();
        $plan = Plan::factory()->create(['limits' => ['workspaces' => 1]]);
        $this->attachPlanToClient($context['client'], $plan);
        ClientSetting::set($context['client']->id, 'workspace_limit_override', 'unlimited');

        $limits = app(WorkspaceLimitService::class);

        $this->assertNull($limits->limitFor($context['user']));
        $this->assertTrue($limits->canCreate($context['user']));
    }

    public function test_new_staff_is_not_automatically_added_to_existing_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        $staff = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->assertCount(0, $staff->accessibleWorkspaces());
        $this->assertFalse($context['workspace']->isAccessibleBy($staff));
    }

    public function test_workspace_administrator_can_manage_members_without_global_client_admin_access(): void
    {
        $context = $this->createWorkspaceContext();
        $workspaceAdmin = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $context['workspace']->members()->attach($workspaceAdmin->id, ['role' => 'administrator']);

        $this->assertTrue($context['workspace']->canManageMembers($workspaceAdmin));
        $this->assertFalse($workspaceAdmin->isClientAdministrator());
        $this->assertFalse($workspaceAdmin->can('create', \App\Models\Workspace::class));
    }
}
