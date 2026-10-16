<?php

namespace Tests\Feature\Realtime;

use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushSubscribeTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = 'https://fcm.googleapis.com/fcm/send/test123';

    public function test_subscribe_persists_push_subscription(): void
    {
        $ctx = $this->createWorkspaceContext();
        $user = $ctx['user'];

        $response = $this->actingAs($user)
            ->postJson(route('client.push.subscribe'), [
                'endpoint' => $this->endpoint,
                'p256dh' => 'fake_p256dh_key',
                'auth' => 'fake_auth_key',
                'ua' => 'Mozilla/5.0',
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $this->endpoint,
        ]);
    }

    public function test_unsubscribe_deletes_push_subscription(): void
    {
        $ctx = $this->createWorkspaceContext();
        $user = $ctx['user'];

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $this->endpoint,
            'p256dh_key' => 'fake_p256dh_key',
            'auth_key' => 'fake_auth_key',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('client.push.unsubscribe'), [
                'endpoint' => $this->endpoint,
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => $this->endpoint,
        ]);
    }

    public function test_subscribe_requires_authentication(): void
    {
        $response = $this->postJson(route('client.push.subscribe'), [
            'endpoint' => $this->endpoint,
            'p256dh' => 'fake',
            'auth' => 'fake',
        ]);

        $response->assertUnauthorized();
    }
}
