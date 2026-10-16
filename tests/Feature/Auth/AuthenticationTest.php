<?php

namespace Tests\Feature\Auth;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('client.dashboard', absolute: false));
    }

    public function test_admins_can_authenticate_using_the_unified_login_screen(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        // Admin guard is used, not the web guard.
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admin_login_ignores_a_client_intended_url(): void
    {
        // Regression: url.intended is shared across guards. A leftover client
        // (web-guard) intended URL must not be replayed for an admin — doing so
        // sent them to a route they can't access, bouncing them back to /login.
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);

        $response = $this->withSession(['url.intended' => url('/painel/contacts')])
            ->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin, 'admin');
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admin_login_honors_an_admin_intended_url(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);

        $response = $this->withSession(['url.intended' => url('/admin/clients')])
            ->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin, 'admin');
        $response->assertRedirect(url('/admin/clients'));
    }

    public function test_inactive_admin_cannot_authenticate_via_login_screen(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_INACTIVE]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertGuest('web');
    }

    public function test_admin_login_url_redirects_to_unified_login(): void
    {
        $this->get(route('admin.login'))->assertRedirect(route('login'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
