<?php

namespace Tests\Feature\Polish;

use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_mark_step_returns_false_for_invalid_step(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $user = $ctx['user'];

        $service = app(OnboardingService::class);
        $result = $service->markStep($user, 'nonexistent_step');

        $this->assertFalse($result);
    }

    public function test_onboarding_mark_step_verify_email_when_verified(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $user = $ctx['user'];

        $service = app(OnboardingService::class);
        $result = $service->markStep($user, 'verify_email');

        $this->assertTrue($result);
        $this->assertDatabaseHas('onboarding_steps', ['user_id' => $user->id, 'step' => 'verify_email', 'completed' => true]);
    }

    public function test_onboarding_mark_step_fails_when_condition_not_met(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => null]);
        $user = $ctx['user'];

        $service = app(OnboardingService::class);
        $result = $service->markStep($user, 'verify_email');

        $this->assertFalse($result);
    }
}
