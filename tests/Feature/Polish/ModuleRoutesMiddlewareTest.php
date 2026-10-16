<?php

namespace Tests\Feature\Polish;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleRoutesMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_user_cannot_access_module_routes(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/painel/ai/knowledge-bases')
            ->assertRedirect();
    }

    public function test_unauthenticated_user_is_redirected_from_module_routes(): void
    {
        $this->get('/painel/ai/knowledge-bases')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_client_can_access_module_routes(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();

        $this->actingAs($user)
            ->get('/painel/ai/knowledge-bases')
            ->assertOk();
    }

    public function test_demo_mode_blocks_post_on_module_routes(): void
    {
        config(['app.demo_mode' => true]);

        ['user' => $user] = $this->createWorkspaceContext();

        $this->actingAs($user)
            ->post('/painel/automations', ['name' => 'test'])
            ->assertRedirect();
    }

    public function test_client_scope_middleware_populates_current_client_id(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();

        // The client-app middleware group includes EnsureClientScope.
        // If current_client_id is missing (e.g. user.client_id is null), the middleware aborts.
        // A successful 200 response proves the scope was populated correctly.
        $this->actingAs($user)
            ->get('/painel/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contacts/Index'));
    }
}
