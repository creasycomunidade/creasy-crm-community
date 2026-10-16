<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that one workspace cannot access another workspace's resources.
 */
class MultiTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithWorkspace(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->update(['workspace_id' => $workspace->id]);
        $user->refresh();

        return [$user, $workspace];
    }

    #[Test]
    public function workspace_a_cannot_see_workspace_b_contacts(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        Contact::factory()->create([
            'workspace_id' => $workspaceB->id,
            'first_name' => 'WorkspaceB',
            'last_name' => 'UniqueContactName',
            'phone_e164' => '+8801999999999',
        ]);

        // Compute the actual Inertia asset version from the Vite manifest
        $inertiaVersion = file_exists(public_path('build/manifest.json'))
            ? hash_file('xxh128', public_path('build/manifest.json'))
            : '';

        $response = $this->actingAs($userA)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $inertiaVersion])
            ->get('/painel/contacts');

        $response->assertStatus(200);
        $response->assertJsonMissing(['first_name' => 'WorkspaceB']);
    }

    #[Test]
    public function workspace_a_cannot_delete_workspace_b_contact(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        $contactB = Contact::factory()->create([
            'workspace_id' => $workspaceB->id,
            'first_name' => 'B',
            'last_name' => 'Contact',
            'phone_e164' => '+8801888888888',
        ]);

        $response = $this->actingAs($userA)->delete("/painel/contacts/{$contactB->id}");
        $response->assertNotFound();
        $this->assertDatabaseHas('contacts', ['id' => $contactB->id]);
    }

    #[Test]
    public function workspace_a_cannot_edit_workspace_b_chatbot(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        $chatbot = AiChatbot::create([
            'workspace_id' => $workspaceB->id,
            'name' => 'B Chatbot',
        ]);

        $response = $this->actingAs($userA)->put("/painel/ai/chatbots/{$chatbot->uuid}", ['name' => 'Hacked']);
        $response->assertStatus(403);
        $this->assertDatabaseHas('ai_chatbots', ['id' => $chatbot->id, 'name' => 'B Chatbot']);
    }
}
