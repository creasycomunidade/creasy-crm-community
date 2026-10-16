<?php

namespace Tests\Feature\Polish;

use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInviteUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_team_page_with_invitations(): void
    {
        $ctx = $this->createWorkspaceContext([], ['client_role' => 'administrator', 'email_verified_at' => now()]);
        $user = $ctx['user'];

        Invitation::create([
            'client_id' => $user->client_id,
            'email' => 'invited@example.com',
            'client_role' => 'staff',
            'token' => 'abc123',
            'invited_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get(route('client.team.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('client/Team/Index')
            ->has('invitations', 1)
        );
    }

    public function test_admin_can_send_invitation(): void
    {
        $ctx = $this->createWorkspaceContext([], ['client_role' => 'administrator', 'email_verified_at' => now()]);
        $user = $ctx['user'];

        $response = $this->actingAs($user)->post(route('client.invitations.store'), [
            'email' => 'newuser@example.com',
            'client_role' => 'staff',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invitations', ['email' => 'newuser@example.com']);
    }

    public function test_admin_can_revoke_invitation(): void
    {
        $ctx = $this->createWorkspaceContext([], ['client_role' => 'administrator', 'email_verified_at' => now()]);
        $user = $ctx['user'];

        $invitation = Invitation::create([
            'client_id' => $user->client_id,
            'email' => 'rev@example.com',
            'client_role' => 'staff',
            'token' => 'tokxyz',
            'invited_by' => $user->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)
            ->delete(route('client.invitations.destroy', $invitation))
            ->assertRedirect();

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }

    public function test_staff_cannot_send_invitation(): void
    {
        $ctx = $this->createWorkspaceContext([], ['client_role' => 'staff', 'email_verified_at' => now()]);
        $user = $ctx['user'];

        $this->actingAs($user)
            ->post(route('client.invitations.store'), ['email' => 'x@x.com', 'client_role' => 'staff'])
            ->assertStatus(403);
    }
}
