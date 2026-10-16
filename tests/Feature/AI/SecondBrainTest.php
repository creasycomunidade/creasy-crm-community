<?php

namespace Tests\Feature\AI;

use App\Models\Workspace;
use App\Modules\AI\Models\AiMemoryNode;
use App\Modules\AI\Models\AiMemoryEdge;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiAgentRun;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Services\SecondBrainService;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecondBrainTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_is_relevant_and_strictly_isolated_by_workspace(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();
        AiMemoryNode::create([
            'workspace_id' => $workspaceA->id, 'node_type' => 'preference',
            'title' => 'Cliente prefere contato pelo Telegram', 'summary' => 'Não ligar pela manhã.',
            'content' => 'Preferência privada da empresa A', 'keywords' => ['telegram', 'contato'],
            'source_type' => 'manual', 'importance' => 5,
        ]);
        AiMemoryNode::create([
            'workspace_id' => $workspaceB->id, 'node_type' => 'decision',
            'title' => 'Segredo da empresa B no Telegram', 'content' => 'Nunca pode vazar.',
            'keywords' => ['telegram'], 'source_type' => 'manual', 'importance' => 5,
        ]);

        $results = app(SecondBrainService::class)->search($workspaceA->id, 'telegram', 10);

        $this->assertCount(1, $results);
        $this->assertSame('Cliente prefere contato pelo Telegram', $results->first()->title);
        $this->assertStringNotContainsString('Segredo', app(SecondBrainService::class)->context($workspaceA->id, 'telegram'));
    }

    #[Test]
    public function sync_builds_contact_conversation_nodes_and_their_relation(): void
    {
        $workspace = Workspace::factory()->create();
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id, 'channel' => 'telegram', 'provider' => 'telegram',
            'display_name' => 'Bot comercial', 'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id, 'first_name' => 'Luciana', 'last_name' => 'Silva',
        ]);
        Conversation::create([
            'workspace_id' => $workspace->id, 'channel_account_id' => $account->id,
            'contact_id' => $contact->id, 'external_thread_id' => 'tg-123', 'last_message_at' => now(),
        ]);

        $counts = app(SecondBrainService::class)->syncWorkspace($workspace->id);

        $this->assertSame(1, $counts['contacts']);
        $this->assertSame(1, $counts['conversations']);
        $person = AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'contact')->firstOrFail();
        $conversation = AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'conversation')->firstOrFail();
        $this->assertDatabaseHas('ai_memory_edges', [
            'workspace_id' => $workspace->id,
            'from_node_id' => $person->id,
            'to_node_id' => $conversation->id,
            'relation' => 'possui_conversa',
        ]);
    }

    #[Test]
    public function sync_imports_full_conversations_knowledge_chunks_and_ai_outputs(): void
    {
        $workspace = Workspace::factory()->create();
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id, 'channel' => 'instagram', 'provider' => 'meta',
            'display_name' => 'Instagram comercial', 'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id, 'first_name' => 'Maria']);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id, 'channel_account_id' => $account->id,
            'contact_id' => $contact->id, 'external_thread_id' => 'ig-123', 'last_message_at' => now(),
        ]);
        Message::create([
            'conversation_id' => $conversation->id, 'direction' => 'in', 'channel' => 'instagram',
            'type' => 'text', 'body' => 'Quero conhecer o plano empresarial', 'status' => 'read', 'sent_at' => now()->subMinute(),
        ]);
        Message::create([
            'conversation_id' => $conversation->id, 'direction' => 'out', 'channel' => 'instagram',
            'type' => 'text', 'body' => 'O plano empresarial inclui automações', 'sent_by' => 'bot', 'status' => 'sent', 'sent_at' => now(),
        ]);
        Message::create([
            'conversation_id' => $conversation->id, 'direction' => 'in', 'channel' => 'instagram',
            'type' => 'unsupported', 'body' => 'Mensagem não suportada', 'status' => 'delivered', 'sent_at' => now()->addSecond(),
            'payload' => ['evolution_go' => ['Message' => ['pollCreationMessage' => ['name' => 'Qual produto você prefere?']]]],
        ]);
        $base = AiKnowledgeBase::create([
            'workspace_id' => $workspace->id, 'name' => 'Produtos e planos', 'status' => 'active',
        ]);
        $document = AiKbDocument::create([
            'kb_id' => $base->id, 'source_type' => 'text', 'title' => 'Plano empresarial', 'status' => 'indexed',
        ]);
        AiKbChunk::create([
            'kb_id' => $base->id, 'document_id' => $document->id, 'ord' => 0,
            'content' => 'O plano empresarial oferece automações ilimitadas e atendimento omnichannel.', 'tokens' => 12,
        ]);
        $agent = AiAgent::create([
            'workspace_id' => $workspace->id, 'slug' => 'redator', 'name' => 'Redator IA',
            'layer' => 'specialist', 'description' => 'Cria propostas comerciais',
            'persona' => 'Redator comercial', 'system_prompt' => 'Crie conteúdos claros.', 'enabled' => true,
        ]);
        AiAgentRun::create([
            'workspace_id' => $workspace->id, 'agent_id' => $agent->id, 'input' => 'Crie uma proposta empresarial',
            'output' => 'Proposta personalizada com automações e atendimento.', 'status' => 'completed',
        ]);

        app(SecondBrainService::class)->syncWorkspace($workspace->id);

        $conversationNode = AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'conversation')->firstOrFail();
        $documentNode = AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'knowledge_document')->firstOrFail();
        $aiNode = AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'ai_run')->firstOrFail();
        $this->assertStringContainsString('Quero conhecer', $conversationNode->content);
        $this->assertStringContainsString('inclui automações', $conversationNode->content);
        $this->assertStringContainsString('Qual produto você prefere?', $conversationNode->content);
        $this->assertStringContainsString('CONTEXTO DA CONVERSA', $conversationNode->content);
        $this->assertStringNotContainsString('unsupported', $conversationNode->content);
        $this->assertStringNotContainsString('Mensagem não suportada', $conversationNode->content);
        $this->assertStringContainsString('atendimento omnichannel', $documentNode->content);
        $this->assertStringContainsString('Proposta personalizada', $aiNode->content);
        $this->assertDatabaseHas('ai_memory_nodes', ['workspace_id' => $workspace->id, 'node_type' => 'knowledge_base']);
        $this->assertDatabaseHas('ai_memory_nodes', ['workspace_id' => $workspace->id, 'node_type' => 'topic']);
    }

    #[Test]
    public function private_memory_content_is_encrypted_at_rest(): void
    {
        $workspace = Workspace::factory()->create();
        $node = AiMemoryNode::create([
            'workspace_id' => $workspace->id, 'node_type' => 'rule', 'title' => 'Regra de preço',
            'content' => 'Nunca oferecer desconto maior que 10%', 'source_type' => 'manual', 'importance' => 5,
        ]);

        $raw = DB::table('ai_memory_nodes')->where('id', $node->id)->value('content');
        $this->assertNotSame('Nunca oferecer desconto maior que 10%', $raw);
        $this->assertSame('Nunca oferecer desconto maior que 10%', $node->fresh()->content);
    }

    #[Test]
    public function the_module_page_is_available_but_cross_workspace_mutation_is_forbidden(): void
    {
        $context = $this->createWorkspaceContext();
        $foreignWorkspace = Workspace::factory()->create();
        $foreignNode = AiMemoryNode::create([
            'workspace_id' => $foreignWorkspace->id, 'node_type' => 'insight',
            'title' => 'Memória externa', 'source_type' => 'manual', 'importance' => 3,
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->get(route('client.ai.second-brain.index'))
            ->assertOk();

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.ai.second-brain.nodes.destroy', $foreignNode->uuid))
            ->assertForbidden();
        $this->assertDatabaseHas('ai_memory_nodes', ['id' => $foreignNode->id]);
    }

    #[Test]
    public function a_node_exposes_all_its_explained_relations_without_leaking_another_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        $origin = AiMemoryNode::create([
            'workspace_id' => $context['workspace']->id, 'node_type' => 'conversation',
            'title' => 'Conversa com Maria', 'source_type' => 'conversation', 'source_id' => '10', 'importance' => 4,
        ]);
        $topic = AiMemoryNode::create([
            'workspace_id' => $context['workspace']->id, 'node_type' => 'topic',
            'title' => 'Plano empresarial', 'source_type' => 'topic', 'source_id' => 'plano-empresarial', 'importance' => 4,
        ]);
        AiMemoryEdge::create([
            'workspace_id' => $context['workspace']->id, 'from_node_id' => $origin->id,
            'to_node_id' => $topic->id, 'relation' => 'aborda', 'weight' => 0.72,
            'automatic' => true, 'evidence' => 'Assunto identificado na conversa.',
        ]);

        $agentContext = app(SecondBrainService::class)->context($context['workspace']->id, 'Maria');
        $this->assertStringContainsString('CONEXÕES ÚTEIS ENTRE AS MEMÓRIAS', $agentContext);
        $this->assertStringContainsString('Assunto identificado na conversa.', $agentContext);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->getJson(route('client.ai.second-brain.nodes.relations', $origin->uuid))
            ->assertOk()
            ->assertJsonPath('data.0.related_node.title', 'Plano empresarial')
            ->assertJsonPath('data.0.evidence', 'Assunto identificado na conversa.')
            ->assertJsonPath('data.0.automatic', true);

        $foreignWorkspace = Workspace::factory()->create();
        $foreignNode = AiMemoryNode::create([
            'workspace_id' => $foreignWorkspace->id, 'node_type' => 'topic',
            'title' => 'Tema privado', 'source_type' => 'topic', 'source_id' => 'privado', 'importance' => 5,
        ]);
        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->getJson(route('client.ai.second-brain.nodes.relations', $foreignNode->uuid))
            ->assertForbidden();
    }
}
