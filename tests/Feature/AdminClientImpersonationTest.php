<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_enter_the_client_dashboard(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $admin = AdminUser::factory()->create();
        $admin->roles()->attach(Role::where('key', Role::KEY_SUPER_ADMIN)->firstOrFail());

        $client = Client::create([
            'name' => 'Cliente de teste',
            'status' => Client::STATUS_ACTIVE,
            'base_currency' => 'BRL',
            'currency_symbol' => 'R$',
            'currency_position' => 'before',
        ]);

        $clientUser = User::factory()->create([
            'name' => 'Usuário cliente',
            'role' => User::ROLE_CLIENT,
            'client_id' => $client->id,
            'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ])->fresh();

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(route('admin.clients.impersonate', $client));

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('impersonating', true);
        $response->assertSessionHas('impersonated_client_id', $client->id);
        $response->assertSessionHas('current_workspace_id', $clientUser->workspace_id);
        $this->assertAuthenticatedAs($clientUser, 'web');
    }
}
