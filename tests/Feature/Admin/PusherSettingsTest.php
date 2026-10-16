<?php

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PusherSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_secret_is_encrypted_and_can_be_read_again(): void
    {
        SystemSetting::set('pusher_app_secret', 'segredo-real', true, 'pusher');

        $setting = SystemSetting::where('key', 'pusher_app_secret')->firstOrFail();

        $this->assertTrue($setting->is_secret);
        $this->assertNotSame('segredo-real', $setting->getRawOriginal('value'));
        $this->assertSame('segredo-real', SystemSetting::get('pusher_app_secret'));
    }

    public function test_pusher_cannot_be_enabled_with_incomplete_credentials(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.pusher-settings.update'), [
                'pusher_enabled' => 'true',
                'pusher_app_id' => '2180649',
                'pusher_app_key' => 'public-key',
                'pusher_app_secret' => '',
                'pusher_app_cluster' => 'sa1',
            ])
            ->assertSessionHasErrors('pusher_enabled');

        $this->assertNull(SystemSetting::where('key', 'pusher_enabled')->first());
    }

    public function test_complete_credentials_are_reported_as_configured_and_secret_remains_masked(): void
    {
        $admin = $this->createSuperAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.pusher-settings.update'), [
                'pusher_enabled' => 'true',
                'pusher_app_id' => '2180649',
                'pusher_app_key' => 'public-key',
                'pusher_app_secret' => 'private-secret',
                'pusher_app_cluster' => 'sa1',
            ])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.pusher-settings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('configured', true)
                ->where('missingFields', [])
                ->where('settings.pusher_enabled', 'true')
                ->where('settings.pusher_app_secret', '••••••••'));
    }
}
