<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_list_webhook_endpoints(): void
    {
        $user = $this->clientUser();
        WebhookEndpoint::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('client.webhooks.index'))
            ->assertOk();
    }

    public function test_user_can_create_webhook_endpoint(): void
    {
        $user = $this->clientUser();

        $this->actingAs($user)
            ->post(route('client.webhooks.store'), [
                'url' => 'https://93.184.216.34/webhook',
                'events' => ['subscription.created'],
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_endpoints', [
            'user_id' => $user->id,
            'url' => 'https://93.184.216.34/webhook',
        ]);
    }

    public function test_user_can_delete_own_endpoint(): void
    {
        $user = $this->clientUser();
        $endpoint = WebhookEndpoint::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('client.webhooks.destroy', $endpoint))
            ->assertRedirect();

        $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
    }

    public function test_user_cannot_delete_other_users_endpoint(): void
    {
        $user = $this->clientUser();
        $otherUser = $this->clientUser();
        $endpoint = WebhookEndpoint::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->delete(route('client.webhooks.destroy', $endpoint))
            ->assertForbidden();
    }

    public function test_user_can_rotate_endpoint_secret(): void
    {
        $user = $this->clientUser();
        $endpoint = WebhookEndpoint::factory()->create(['user_id' => $user->id]);
        $oldSecret = $endpoint->secret;

        $this->actingAs($user)
            ->postJson(route('client.webhooks.rotate-secret', $endpoint))
            ->assertOk()
            ->assertJsonStructure(['secret']);

        $this->assertNotEquals($oldSecret, $endpoint->fresh()->secret);
    }

    public function test_guest_cannot_access_webhooks(): void
    {
        $this->get(route('client.webhooks.index'))
            ->assertRedirect(route('login'));
    }
}
