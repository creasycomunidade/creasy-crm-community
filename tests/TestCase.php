<?php

namespace Tests;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\ClientSubscription;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create an AdminUser with the SUPER_ADMIN role and all permissions,
     * so RBAC middleware passes in feature tests.
     */
    protected function createSuperAdmin(array $attrs = []): AdminUser
    {
        $admin = AdminUser::factory()->create(array_merge(['status' => AdminUser::STATUS_ACTIVE], $attrs));

        // Ensure SUPER_ADMIN role exists
        $role = Role::firstOrCreate(
            ['key' => Role::KEY_SUPER_ADMIN],
            ['name' => 'Super Admin', 'description' => 'Full access']
        );

        // Attach common permissions so permission middleware passes
        $permKeys = [
            'view_settings', 'manage_settings',
            'view_clients', 'manage_clients',
            'view_plans', 'manage_plans', 'create_plans', 'delete_plans',
            'view_subscriptions', 'manage_subscriptions',
            'view_payment_gateways', 'manage_payment_gateways',
            'view_admins', 'create_admins', 'update_admins', 'delete_admins',
            'view_admin_roles', 'manage_admin_roles',
            'view_email_settings', 'manage_email_settings',
            'view_currencies', 'view_languages',
        ];

        foreach ($permKeys as $key) {
            $perm = Permission::firstOrCreate(
                ['key' => $key],
                ['name' => ucwords(str_replace('_', ' ', $key)), 'category' => 'general']
            );
            if (! $role->permissions->contains('key', $key)) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        $admin->roles()->syncWithoutDetaching([$role->id]);
        $admin->unsetRelation('roles');

        return $admin;
    }

    /**
     * Create a Client, User, and Workspace for feature tests.
     * Returns ['user' => User, 'workspace' => Workspace, 'client' => Client].
     */
    protected function createWorkspaceContext(array $clientAttrs = [], array $userAttrs = [], array $workspaceAttrs = []): array
    {
        $client = Client::create(array_merge([
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'status' => Client::STATUS_ACTIVE,
        ], $clientAttrs));

        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ], $userAttrs));

        $user->refresh();
        $workspace = $client->workspaces()->orderBy('id')->first();
        if ($workspace && $workspaceAttrs !== []) {
            $workspace->update($workspaceAttrs);
        }

        return ['user' => $user, 'workspace' => $workspace, 'client' => $client];
    }

    /**
     * Attach a Plan to a Client via an active ClientSubscription.
     */
    protected function attachPlanToClient(Client $client, Plan $plan): ClientSubscription
    {
        return ClientSubscription::create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'status' => ClientSubscription::STATUS_ACTIVE,
        ]);
    }
}
