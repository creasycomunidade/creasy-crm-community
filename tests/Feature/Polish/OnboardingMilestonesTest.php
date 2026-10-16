<?php

namespace Tests\Feature\Polish;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingMilestonesTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    public function test_connect_first_channel_auto_detected_after_channel_account_created(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();

        $service = $this->makeService();
        $progress = $service->getProgress($user);

        $step = collect($progress['steps'])->firstWhere('key', 'connect_first_channel');
        $this->assertFalse($step['completed'], 'Should not be completed before channel exists');

        // Create a channel account
        ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'meta',
            'display_name' => 'Test Channel',
            'status' => 'active',
        ]);

        $progress2 = $service->getProgress($user->refresh());
        $step2 = collect($progress2['steps'])->firstWhere('key', 'connect_first_channel');
        $this->assertTrue($step2['completed'], 'Should be completed after channel account exists');
    }

    public function test_import_first_contacts_auto_detected(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();

        $service = $this->makeService();
        $progress = $service->getProgress($user);

        $step = collect($progress['steps'])->firstWhere('key', 'import_first_contacts');
        $this->assertFalse($step['completed']);

        Contact::create([
            'workspace_id' => $workspace->id,
            'phone_e164' => '+8801700000001',
        ]);

        $progress2 = $service->getProgress($user->refresh());
        $step2 = collect($progress2['steps'])->firstWhere('key', 'import_first_contacts');
        $this->assertTrue($step2['completed']);
    }

    public function test_train_first_chatbot_auto_detected(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();

        $service = $this->makeService();
        $progress = $service->getProgress($user);

        $step = collect($progress['steps'])->firstWhere('key', 'train_first_chatbot');
        $this->assertFalse($step['completed']);

        AiChatbot::create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Bot',
            'enabled' => true,
            'tone' => 'professional',
            'max_context_chunks' => 5,
        ]);

        $progress2 = $service->getProgress($user->refresh());
        $step2 = collect($progress2['steps'])->firstWhere('key', 'train_first_chatbot');
        $this->assertTrue($step2['completed']);
    }

    public function test_next_step_is_first_incomplete_step(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $user->update(['email_verified_at' => now()]);

        $service = $this->makeService();
        $progress = $service->getProgress($user->refresh());

        // verify_email should be complete; next should be choose_plan or connect_first_channel
        $this->assertNotNull($progress['next_step']);
        $this->assertArrayHasKey('key', $progress['next_step']);
    }

    public function test_onboarding_wizard_page_returns_product_milestones(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();

        $this->actingAs($user)
            ->get(route('client.onboarding.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('progress.steps')
                ->has('progress.next_step')
            );
    }
}
