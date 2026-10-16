<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role'              => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_create_api_token(): void
    {
        $user = $this->clientUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/tokens', ['name' => 'My Token']);

        $response->assertSuccessful()
                 ->assertJsonStructure(['token', 'name', 'id']);
    }

    public function test_authenticated_panel_session_can_create_first_api_token(): void
    {
        $user = $this->clientUser();

        $response = $this->actingAs($user)
            ->postJson(route('client.api-tokens.store'), [
                'name' => 'n8n',
                'abilities' => ['*'],
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'name', 'id', 'abilities'])
            ->assertJsonPath('name', 'n8n');

        $this->assertCount(1, $user->fresh()->tokens);
    }

    public function test_panel_session_cannot_revoke_another_users_token(): void
    {
        $user = $this->clientUser();
        $other = $this->clientUser();
        $token = $other->createToken('Outro usuário');

        $this->actingAs($user)
            ->deleteJson(route('client.api-tokens.destroy', $token->accessToken->id))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_can_list_api_tokens(): void
    {
        $user = $this->clientUser();
        $user->createToken('Token A');
        $user->createToken('Token B');

        $this->actingAs($user)
            ->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_revoke_token(): void
    {
        $user  = $this->clientUser();
        $token = $user->createToken('Revokeable');

        $this->actingAs($user)
            ->deleteJson('/api/v1/tokens/'.$token->accessToken->id)
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_unauthenticated_cannot_access_api(): void
    {
        $this->getJson('/api/v1/me')
             ->assertUnauthorized();
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user  = $this->clientUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->getJson('/api/v1/me')
             ->assertOk()
             ->assertJsonPath('email', $user->email);
    }
}
