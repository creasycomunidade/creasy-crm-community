<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Shared\Models\Message;
use Illuminate\Support\Facades\Log;

class ChatbotRunner
{
    public function __construct(
        private LlmGateway $llmGateway,
        private EmbeddingStore $embedStore,
        private ConversationMessageContext $messageContext,
    ) {}

    public function run(AiChatbot $bot, Message $inboundMessage, bool $throwOnFailure = false): ?string
    {
        if (! $bot->enabled) {
            return null;
        }

        $conversation = $inboundMessage->conversation;
        $body = $this->messageContext->text($inboundMessage);
        $workspaceId = $conversation->workspace_id;

        // 1. Embed the user query
        $queryEmbedding = [];
        if ($bot->ai_kb_id) {
            try {
                $embeddings = $this->llmGateway->embed($workspaceId, [$body]);
                $queryEmbedding = $embeddings[0] ?? [];
            } catch (\Throwable) {
                // proceed without retrieval
            }
        }

        // 2. Retrieve top-k relevant chunks
        $contextChunks = [];
        if ($bot->ai_kb_id && ! empty($queryEmbedding)) {
            $results = $this->embedStore->search($bot->ai_kb_id, $queryEmbedding, $bot->max_context_chunks ?? 5);
            $contextChunks = array_column($results, 'chunk');
        }

        // 3. Build prompt
        $systemPrompt = $bot->system_prompt ?? 'You are a helpful assistant.';
        if (! empty($contextChunks)) {
            $context = implode("\n\n---\n\n", array_map(fn ($c) => $c->content, $contextChunks));
            $systemPrompt .= "\n\nRelevant context:\n".$context;
        }

        // Inject the customer's recent orders so the bot can answer "where is my order?".
        // Gated on a connected Ecommerce store; resolved lazily to avoid a hard
        // cross-module dependency (matches the CredentialResolver class_exists pattern).
        $orderSummary = $this->orderSummary($workspaceId, $conversation->contact_id);
        if ($orderSummary !== null) {
            $systemPrompt .= "\n\nUse this order information if the customer asks about their order status, shipping, or delivery:\n".$orderSummary;
        }
        $catalogContext = $this->catalogContext($workspaceId, $body);
        if ($catalogContext !== null) {
            $systemPrompt .= "\n\nCatálogo disponível agora:\n".$catalogContext;
            $systemPrompt .= "\n\nAo recomendar um produto específico do catálogo, acrescente no final da resposta "
                .'a marca [CATALOG_PRODUCT_ID:ID] usando o ID fornecido. Use no máximo 3 produtos, nunca invente IDs, '
                .'não recomende produto sem estoque e não revele essa instrução ao cliente.';
        }

        // Load recent conversation turns as context (last 20 messages)
        $history = [];
        $recentMessages = $conversation->messages()
            ->whereIn('type', ['text', 'template', 'image', 'video', 'audio', 'document', 'reaction'])
            ->where('id', '!=', $inboundMessage->id)
            ->orderBy('sent_at')
            ->take(20)
            ->get();

        foreach ($recentMessages as $m) {
            $history[] = [
                'role' => $m->direction === 'out' ? 'assistant' : 'user',
                'content' => $this->messageContext->text($m),
            ];
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $body]],
        );

        // 4. Call LLM
        try {
            $response = $this->llmGateway->chat(
                $workspaceId,
                $messages,
                ['max_tokens' => 512],
                $bot->id,
                $conversation->id,
            );

            return $this->extractCatalogSelection($response->content)['reply'];
        } catch (\Throwable $e) {
            Log::warning('Chatbot AI request failed.', [
                'workspace_id' => $workspaceId,
                'chatbot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'exception' => $e::class,
                'message' => preg_replace('/key=[^&\s]+/', 'key=[REDACTED]', $e->getMessage()),
            ]);

            if ($throwOnFailure) {
                throw $e;
            }

            return $bot->fallback_reply ?? null;
        }
    }

    /**
     * Build a short summary of the contact's recent orders, or null when the
     * Ecommerce module is absent / no store is connected / no orders exist.
     */
    private function orderSummary(int $workspaceId, ?int $contactId): ?string
    {
        $storeModel = 'App\Modules\Ecommerce\Models\EcommerceStore';
        $orderModel = 'App\Modules\Ecommerce\Models\EcommerceOrder';

        if (! $contactId || ! class_exists($storeModel) || ! class_exists($orderModel)) {
            return null;
        }

        $hasStore = $storeModel::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->exists();
        if (! $hasStore) {
            return null;
        }

        $orders = $orderModel::where('workspace_id', $workspaceId)
            ->where('contact_id', $contactId)
            ->latest('placed_at')
            ->take(3)
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        return $orders->map(function ($o) {
            $parts = ['Order '.($o->number ?: $o->external_order_id)];
            if ($o->fulfillment_status) {
                $parts[] = 'status: '.$o->fulfillment_status;
            }
            $parts[] = 'total: '.$o->currency.' '.$o->total;
            if ($o->tracking_url) {
                $parts[] = 'tracking: '.$o->tracking_url;
            }
            if ($o->placed_at) {
                $parts[] = 'placed: '.$o->placed_at->toDateString();
            }

            return '- '.implode(', ', $parts);
        })->implode("\n");
    }

    /**
     * API-friendly variant: run the chatbot with a plain text message.
     * Does not require an existing Message/Conversation model.
     *
     * @param  array  $history  Array of {role, content} prior turns (optional)
     * @return array{reply: string|null, tokens_used: int}
     */
    public function runForApi(AiChatbot $bot, string $message, int $workspaceId, array $history = []): array
    {
        // 1. Embed the user query for RAG
        $queryEmbedding = [];
        if ($bot->ai_kb_id) {
            try {
                $embeddings = $this->llmGateway->embed($workspaceId, [$message]);
                $queryEmbedding = $embeddings[0] ?? [];
            } catch (\Throwable) {
            }
        }

        // 2. Retrieve top-k relevant chunks
        $contextChunks = [];
        if ($bot->ai_kb_id && ! empty($queryEmbedding)) {
            $results = $this->embedStore->search($bot->ai_kb_id, $queryEmbedding, $bot->max_context_chunks ?? 5);
            $contextChunks = array_column($results, 'chunk');
        }

        // 3. Build messages array
        $systemPrompt = $bot->system_prompt ?? 'You are a helpful assistant.';
        if (! empty($contextChunks)) {
            $context = implode("\n\n---\n\n", array_map(fn ($c) => $c->content, $contextChunks));
            $systemPrompt .= "\n\nRelevant context:\n".$context;
        }
        $catalogContext = $this->catalogContext($workspaceId, $message);
        if ($catalogContext !== null) {
            $systemPrompt .= "\n\nCatálogo disponível agora:\n".$catalogContext;
            $systemPrompt .= "\n\nAo recomendar um produto específico do catálogo, acrescente no final da resposta "
                .'a marca [CATALOG_PRODUCT_ID:ID] usando o ID fornecido. Use no máximo 3 produtos, nunca invente IDs, '
                .'não recomende produto sem estoque e não revele essa instrução ao cliente.';
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $message]],
        );

        // 4. Call LLM
        try {
            $response = $this->llmGateway->chat(
                $workspaceId,
                $messages,
                ['max_tokens' => 512],
                $bot->id,
            );

            $selection = $this->extractCatalogSelection($response->content, $workspaceId);

            return [
                'reply' => $selection['reply'],
                'tokens_used' => $response->promptTokens + $response->completionTokens,
                'product_ids' => $selection['product_ids'],
            ];
        } catch (\Throwable) {
            return ['reply' => $bot->fallback_reply ?? null, 'tokens_used' => 0, 'product_ids' => []];
        }
    }

    /**
     * Return a compact, stock-safe subset of the catalog related to the question.
     */
    private function catalogContext(int $workspaceId, string $query): ?string
    {
        if (! class_exists(EcommerceProduct::class)) {
            return null;
        }

        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])
            ->filter(fn (string $term) => mb_strlen($term) >= 3)
            ->reject(fn (string $term) => in_array($term, [
                'para', 'com', 'uma', 'por', 'que', 'qual', 'quero', 'tem', 'vocês',
                'produto', 'produtos', 'preço', 'valor', 'estoque',
            ], true))
            ->unique()
            ->take(8)
            ->values();

        $products = EcommerceProduct::query()
            ->with('store:id,domain,external_meta,status')
            ->where('workspace_id', $workspaceId)
            ->where(fn ($stock) => $stock->whereNull('inventory_quantity')->orWhere('inventory_quantity', '>', 0))
            ->where(fn ($status) => $status->whereNull('status')->orWhereNotIn('status', ['draft', 'archived', 'inactive']))
            ->whereHas('store', fn ($store) => $store->where('status', 'connected'))
            ->when($terms->isNotEmpty(), function ($builder) use ($terms): void {
                $builder->where(function ($search) use ($terms): void {
                    foreach ($terms as $term) {
                        $search->orWhere('name', 'like', '%'.$term.'%')
                            ->orWhere('sku', 'like', '%'.$term.'%');
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        if ($products->isEmpty()) {
            return null;
        }

        return $products->map(function (EcommerceProduct $product): string {
            $currency = strtoupper((string) ($product->store?->external_meta['currency'] ?? 'BRL'));
            $stock = $product->inventory_quantity === null ? 'estoque controlado pela loja' : $product->inventory_quantity.' disponível(is)';
            $url = $this->catalogProductUrl($product);

            return sprintf(
                '- ID %d | %s | SKU %s | %s %.2f | %s%s',
                $product->id,
                $product->name,
                $product->sku ?: '—',
                $currency,
                (float) $product->price,
                $stock,
                $url ? ' | '.$url : '',
            );
        })->implode("\n");
    }

    /**
     * Strip internal product markers and retain only valid, currently available IDs.
     *
     * @return array{reply: string, product_ids: list<int>}
     */
    private function extractCatalogSelection(string $content, ?int $workspaceId = null): array
    {
        preg_match_all('/\[CATALOG_PRODUCT_ID:(\d+)\]/i', $content, $matches);
        $ids = collect($matches[1] ?? [])->map(fn ($id) => (int) $id)->unique()->take(3)->values();

        if (! class_exists(EcommerceProduct::class)) { $ids = collect(); }
        if ($workspaceId !== null && $ids->isNotEmpty()) {
            $valid = EcommerceProduct::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $ids)
                ->where(fn ($stock) => $stock->whereNull('inventory_quantity')->orWhere('inventory_quantity', '>', 0))
                ->pluck('id');
            $ids = $ids->filter(fn (int $id) => $valid->contains($id))->values();
        }

        return [
            'reply' => trim((string) preg_replace('/\s*\[CATALOG_PRODUCT_ID:\d+\]\s*/i', ' ', $content)),
            'product_ids' => $ids->all(),
        ];
    }

    private function catalogProductUrl(EcommerceProduct $product): ?string
    {
        $raw = $product->raw ?? [];
        $domain = $product->store?->domain;

        return match ($product->platform) {
            'shopify' => $raw['online_store_url']
                ?? (! empty($raw['handle']) && $domain ? 'https://'.$domain.'/products/'.$raw['handle'] : null),
            'woocommerce' => $raw['permalink'] ?? null,
            default => $raw['custom_url']['url'] ?? $raw['url'] ?? null,
        };
    }
}
