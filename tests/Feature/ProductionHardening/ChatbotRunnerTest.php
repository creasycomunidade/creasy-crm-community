<?php

namespace Tests\Feature\ProductionHardening;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\AI\Services\EmbeddingStore;
use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ChatbotRunner end-to-end test with Http::fake for OpenAI.
 * Verifies that context chunks from the KB are included in the prompt.
 */
class ChatbotRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_runner_includes_kb_context_in_prompt(): void
    {
        $data = $this->createWorkspaceContext();
        $workspace = $data['workspace'];

        // Seed: KB + document + chunk with a known embedding
        $kb = AiKnowledgeBase::create([
            'workspace_id' => $workspace->id,
            'name' => 'Test KB',
            'embedding_model' => 'text-embedding-3-small',
            'dimensions' => 3,
            'status' => 'active',
        ]);
        $doc = AiKbDocument::create([
            'kb_id' => $kb->id,
            'title' => 'FAQ',
            'source_type' => 'text',
            'source_ref' => 'Our refund policy is 30 days.',
            'status' => 'indexed',
        ]);
        $chunk = AiKbChunk::create([
            'kb_id' => $kb->id,
            'document_id' => $doc->id,
            'ord' => 0,
            'content' => 'Our refund policy is 30 days.',
            'tokens' => 8,
            'embedding' => null,
        ]);

        // Manually store a small embedding that matches anything
        $embeddingStore = app(EmbeddingStore::class);
        $embeddingStore->storeEmbedding($chunk, [0.1, 0.2, 0.3]);

        $chatbot = AiChatbot::create([
            'workspace_id' => $workspace->id,
            'name' => 'Support Bot',
            'ai_kb_id' => $kb->id,
            'system_prompt' => 'You are a helpful assistant.',
            'max_context_chunks' => 3,
            'enabled' => true,
            'channels' => ['whatsapp'],
        ]);

        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $conv = Conversation::create([
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
        $message = new Message;
        $message->body = 'What is your refund policy?';
        $message->direction = 'in';
        $message->channel = 'playground';
        $message->setRelation('conversation', $conv);

        $capturedSystemPrompt = null;

        // Fake both embedding and chat OpenAI calls using URL-keyed closures
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => [0.1, 0.2, 0.3]]],
            ], 200),
            'api.openai.com/v1/chat/completions' => function ($request) use (&$capturedSystemPrompt) {
                $body = json_decode($request->body(), true);
                $capturedSystemPrompt = collect($body['messages'] ?? [])->firstWhere('role', 'system')['content'] ?? '';

                return Http::response([
                    'choices' => [['message' => ['content' => 'Our refund policy is 30 days.']]],
                    'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20],
                    'model' => 'gpt-4o-mini',
                ], 200);
            },
        ]);

        // Add LLM workspace credential via AiProviderConfig
        AiProviderConfig::create([
            'workspace_id' => $workspace->id,
            'provider' => 'openai',
            'credentials' => ['api_key' => 'sk-test'],
            'default_model_chat' => 'gpt-4o-mini',
            'default_model_embed' => 'text-embedding-3-small',
            'enabled' => true,
        ]);

        $runner = app(ChatbotRunner::class);
        $reply = $runner->run($chatbot, $message);

        $this->assertNotNull($reply, 'ChatbotRunner should return a reply');
        $this->assertStringContainsString('refund', strtolower($reply));
        // Assert that context chunks were included in the system prompt sent to OpenAI
        $this->assertNotNull($capturedSystemPrompt, 'System prompt should have been captured');
        $this->assertStringContainsString('refund policy is 30 days', $capturedSystemPrompt);
    }

    public function test_chatbot_can_select_only_an_available_catalog_product(): void
    {
        $data = $this->createWorkspaceContext();
        $workspace = $data['workspace'];
        $store = EcommerceStore::create([
            'workspace_id' => $workspace->id,
            'name' => 'Loja',
            'platform' => 'woocommerce',
            'domain' => 'https://loja.example.com',
            'status' => 'connected',
            'credentials' => ['consumer_key' => 'ck_test', 'consumer_secret' => 'cs_test'],
            'external_meta' => ['currency' => 'BRL'],
        ]);
        $available = EcommerceProduct::create([
            'workspace_id' => $workspace->id,
            'store_id' => $store->id,
            'external_id' => '10',
            'platform' => 'woocommerce',
            'name' => 'Óculos Aurora',
            'sku' => 'AUR-10',
            'price' => 499.90,
            'inventory_quantity' => 2,
            'status' => 'publish',
            'raw' => ['permalink' => 'https://loja.example.com/produto/aurora'],
        ]);
        $unavailable = EcommerceProduct::create([
            'workspace_id' => $workspace->id,
            'store_id' => $store->id,
            'external_id' => '11',
            'platform' => 'woocommerce',
            'name' => 'Óculos Esgotado',
            'sku' => 'OFF-11',
            'price' => 199.90,
            'inventory_quantity' => 0,
            'status' => 'publish',
        ]);
        $chatbot = AiChatbot::create([
            'workspace_id' => $workspace->id,
            'name' => 'Vendedor',
            'system_prompt' => 'Ajude o cliente.',
            'enabled' => true,
            'channels' => ['whatsapp'],
        ]);
        AiProviderConfig::create([
            'workspace_id' => $workspace->id,
            'provider' => 'openai',
            'credentials' => ['api_key' => 'sk-test'],
            'default_model_chat' => 'gpt-4o-mini',
            'enabled' => true,
        ]);

        $capturedPrompt = '';
        Http::fake([
            'api.openai.com/v1/chat/completions' => function ($request) use (&$capturedPrompt, $available, $unavailable) {
                $body = json_decode($request->body(), true);
                $capturedPrompt = $body['messages'][0]['content'] ?? '';

                return Http::response([
                    'choices' => [[
                        'message' => ['content' => "Tenho uma ótima opção. [CATALOG_PRODUCT_ID:{$available->id}] [CATALOG_PRODUCT_ID:{$unavailable->id}]"],
                    ]],
                    'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 10],
                    'model' => 'gpt-4o-mini',
                ]);
            },
        ]);

        $result = app(ChatbotRunner::class)->runForApi($chatbot, 'Quero óculos Aurora', $workspace->id);

        $this->assertSame([$available->id], $result['product_ids']);
        $this->assertStringNotContainsString('CATALOG_PRODUCT_ID', $result['reply']);
        $this->assertStringContainsString('Óculos Aurora', $capturedPrompt);
        $this->assertStringNotContainsString('Óculos Esgotado', $capturedPrompt);
    }
}
