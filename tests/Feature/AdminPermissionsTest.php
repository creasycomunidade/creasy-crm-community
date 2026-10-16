<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('admin.login', [], false));
    }

    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('admin.dashboard'));
        // Web-guard user accessing admin routes gets redirected to admin login
        $response->assertRedirect();
    }

    public function test_admin_user_can_access_admin_dashboard(): void
    {
        $admin = AdminUser::factory()->create(['status' => AdminUser::STATUS_ACTIVE]);
        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertOk();
    }
}
