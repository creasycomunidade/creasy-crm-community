<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role'              => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_list_notifications(): void
    {
        $user = $this->clientUser();

        $user->notifications()->create([
            'id'   => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => json_encode(['message' => 'Hello!']),
        ]);

        $this->actingAs($user)
            ->get(route('client.notifications.index'))
            ->assertOk();
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = $this->clientUser();

        $notification = $user->notifications()->create([
            'id'   => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => json_encode(['message' => 'Hello!']),
        ]);

        $this->actingAs($user)
            ->postJson(route('client.notifications.read', $notification->id))
            ->assertOk();

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $user = $this->clientUser();

        for ($i = 0; $i < 3; $i++) {
            $user->notifications()->create([
                'id'   => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\TestNotification',
                'data' => json_encode(['message' => "Notification {$i}"]),
            ]);
        }

        $this->actingAs($user)
            ->post(route('client.notifications.read-all'))
            ->assertRedirect();

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->get(route('client.notifications.index'))
            ->assertRedirect(route('login'));
    }
}
