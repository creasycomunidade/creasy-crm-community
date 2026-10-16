<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role'              => 'client',
            'email_verified_at' => now(),
        ]);
    }

    private function adminUser(): AdminUser
    {
        return $this->createSuperAdmin();
    }

    public function test_user_can_view_support_tickets_list(): void
    {
        $user = $this->clientUser();
        $this->actingAs($user)
            ->get(route('client.support.index'))
            ->assertOk();
    }

    public function test_user_can_create_support_ticket(): void
    {
        $user = $this->clientUser();

        $this->actingAs($user)
            ->post(route('client.support.store'), [
                'subject'  => 'Help needed',
                'message'  => 'I have a question about billing.',
                'priority' => 'normal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Help needed',
            'email'   => $user->email,
        ]);
    }

    public function test_admin_can_view_support_inbox(): void
    {
        SupportTicket::factory()->count(3)->create();

        $this->actingAs($this->adminUser(), 'admin')
            ->get(route('admin.support.index'))
            ->assertOk();
    }

    public function test_admin_can_update_ticket_status(): void
    {
        $ticket = SupportTicket::factory()->create(['status' => 'open']);
        $admin  = $this->adminUser();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support.status', $ticket), ['status' => 'closed'])
            ->assertRedirect();

        $this->assertEquals('closed', $ticket->fresh()->status);
    }

    public function test_guest_cannot_create_ticket_via_client_area(): void
    {
        $this->get(route('client.support.index'))
            ->assertRedirect(route('login'));
    }
}
