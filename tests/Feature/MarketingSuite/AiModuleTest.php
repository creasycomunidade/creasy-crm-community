<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiModuleTest extends TestCase
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
    public function creating_knowledge_base_succeeds(): void
    {
        [$user] = $this->createUserWithWorkspace();

        $response = $this->actingAs($user)->post('/painel/ai/knowledge-bases', [
            'name' => 'Product FAQ',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('ai_knowledge_bases', ['name' => 'Product FAQ', 'workspace_id' => $user->workspace_id]);
    }

    #[Test]
    public function adding_document_dispatches_index_job(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->createUserWithWorkspace();

        // Create the KB via the API so it's visible to subsequent requests
        $this->actingAs($user)->post('/painel/ai/knowledge-bases', ['name' => 'Doc Test KB']);

        $kb = AiKnowledgeBase::where('name', 'Doc Test KB')->first();
        $this->assertNotNull($kb, 'KB should have been created via API');

        $response = $this->actingAs($user)->post("/painel/ai/knowledge-bases/{$kb->uuid}/documents", [
            'source_type' => 'text',
            'source_ref' => 'This is a test document with some content about our product.',
            'title' => 'Test Document',
        ]);

        $response->assertStatus(302);
        Queue::assertPushed(IndexDocumentJob::class);
    }

    #[Test]
    public function creating_chatbot_stores_in_database(): void
    {
        [$user] = $this->createUserWithWorkspace();

        $response = $this->actingAs($user)->post('/painel/ai/chatbots', [
            'name' => 'Support Bot',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('ai_chatbots', ['name' => 'Support Bot', 'workspace_id' => $user->workspace_id]);
    }

    #[Test]
    public function workspace_scoping_prevents_cross_workspace_kb_access(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        // UserB creates a KB via API
        $this->actingAs($userB)->post('/painel/ai/knowledge-bases', ['name' => 'WS-B KB']);
        $kb = AiKnowledgeBase::where('name', 'WS-B KB')->first();
        $this->assertNotNull($kb, 'KB for workspace B should exist');

        // UserA tries to add a document to UserB's KB — should be forbidden
        $response = $this->actingAs($userA)->post("/painel/ai/knowledge-bases/{$kb->uuid}/documents", [
            'source_type' => 'text',
            'source_ref' => 'Attempting cross-workspace injection.',
        ]);

        $response->assertStatus(403);
    }
}
