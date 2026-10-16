<?php

namespace App\Modules\AI\Services;

use App\Modules\AI\Models\AiRun;
use App\Modules\AI\Services\Llm\LlmManager;
use App\Modules\AI\Services\Llm\LlmResponse;
use App\Modules\Broadcasting\Models\UsageMeter;
use Illuminate\Support\Facades\Log;

class LlmGateway
{
    public function chat(
        int $workspaceId,
        array $messages,
        array $opts = [],
        ?int $chatbotId = null,
        ?int $conversationId = null,
    ): LlmResponse {
        $usesPlatformProvider = ! LlmManager::hasWorkspaceChatCredentials($workspaceId);
        if ($usesPlatformProvider) {
            app(PlatformAiLimit::class)->assertAvailable($workspaceId);
        }

        $provider = LlmManager::forWorkspace($workspaceId);
        $response = $provider->chat($messages, $opts);

        $this->record($workspaceId, $response, $chatbotId, $conversationId, $usesPlatformProvider);

        return $response;
    }

    public function chatWithWorkspaceProvider(
        int $workspaceId,
        string $providerName,
        ?string $model,
        array $messages,
        array $opts = [],
        ?int $chatbotId = null,
        ?int $conversationId = null,
    ): LlmResponse {
        $provider = LlmManager::forWorkspaceProvider($workspaceId, $providerName, $model);
        $response = $provider->chat($messages, $opts);
        $this->record($workspaceId, $response, $chatbotId, $conversationId, false);

        return $response;
    }

    public function embed(int $workspaceId, array $texts): array
    {
        $usesPlatformProvider = ! LlmManager::hasWorkspaceEmbeddingCredentials($workspaceId);
        if ($usesPlatformProvider) {
            app(PlatformAiLimit::class)->assertAvailable($workspaceId);
        }

        // Use embed-specific provider (skips Anthropic which has no embedding support)
        $provider = LlmManager::forWorkspaceEmbed($workspaceId);
        $embeddings = $provider->embed($texts);
        $tokenEstimate = array_sum(array_map(fn ($t) => (int) ceil(strlen($t) / 4), $texts));
        UsageMeter::track($workspaceId, 'ai_tokens', $tokenEstimate);
        if ($usesPlatformProvider) {
            app(PlatformAiLimit::class)->record($workspaceId, $tokenEstimate);
        }

        AiRun::create([
            'chatbot_id' => null,
            'conversation_id' => null,
            'prompt_tokens' => $tokenEstimate,
            'completion_tokens' => 0,
            'cost_cents' => 0,
            'latency_ms' => 0,
            'model' => 'embed',
            'status' => 'ok',
        ]);

        return $embeddings;
    }

    public function transcribe(int $workspaceId, string $bytes, string $mimeType, array $opts = [], ?int $conversationId = null): string
    {
        $usesPlatformProvider = ! LlmManager::hasWorkspaceEmbeddingCredentials($workspaceId);
        if ($usesPlatformProvider) {
            app(PlatformAiLimit::class)->assertAvailable($workspaceId);
        }

        $response = LlmManager::forWorkspaceTranscription($workspaceId)
            ->transcribe($bytes, $mimeType, $opts);
        $this->record($workspaceId, $response, null, $conversationId, $usesPlatformProvider);

        return trim($response->content);
    }

    private function record(
        int $workspaceId,
        LlmResponse $response,
        ?int $chatbotId,
        ?int $conversationId,
        bool $usesPlatformProvider,
    ): void {
        $totalTokens = $response->promptTokens + $response->completionTokens;
        UsageMeter::track($workspaceId, 'ai_tokens', $totalTokens);
        if ($usesPlatformProvider) {
            app(PlatformAiLimit::class)->record($workspaceId, $totalTokens);
        }

        AiRun::create([
            'chatbot_id' => $chatbotId,
            'conversation_id' => $conversationId,
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'cost_cents' => 0,
            'latency_ms' => $response->latencyMs,
            'model' => $response->model,
            'status' => 'ok',
        ]);

        Log::channel('json')->info('llm.chat', [
            'workspace_id' => $workspaceId,
            'chatbot_id' => $chatbotId,
            'model' => $response->model,
            'provider_source' => $usesPlatformProvider ? 'platform' : 'workspace',
            'prompt_tokens' => $response->promptTokens,
            'completion_tokens' => $response->completionTokens,
            'finish_reason' => $response->finishReason,
            'latency_ms' => $response->latencyMs,
        ]);
    }
}
