<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(): User
    {
        return User::factory()->create([
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_can_view_onboarding_wizard(): void
    {
        $user = $this->clientUser();
        $this->actingAs($user)
            ->get(route('client.onboarding.show'))
            ->assertOk();
    }

    public function test_user_can_complete_a_step(): void
    {
        $user = $this->clientUser();

        $this->actingAs($user)
            ->postJson(route('client.onboarding.complete'), ['step' => 'connect_first_channel'])
            ->assertOk();

        $this->assertDatabaseHas('onboarding_steps', [
            'user_id' => $user->id,
            'step' => 'connect_first_channel',
        ]);
    }

    public function test_guest_cannot_view_onboarding(): void
    {
        $this->get(route('client.onboarding.show'))
            ->assertRedirect(route('login'));
    }
}
