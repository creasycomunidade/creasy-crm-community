<?php

namespace App\Modules\AI\Services\Llm;

use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LlmProviderInterface
{
    private const BASE = 'https://api.anthropic.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $chatModel = 'claude-3-haiku-20240307',
    ) {}

    public function chat(array $messages, array $opts = []): LlmResponse
    {
        $start = microtime(true);

        // Anthropic separates the system turn from the conversation turns
        $system = null;
        $turns = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = $m['content'];
            } else {
                $turns[] = ['role' => $m['role'], 'content' => $m['content']];
            }
        }

        $body = [
            'model' => $opts['model'] ?? $this->chatModel,
            'max_tokens' => $opts['max_tokens'] ?? 1024,
            'messages' => $turns,
        ];
        if ($system !== null) {
            $body['system'] = $system;
        }

        $timeout = ($opts['max_tokens'] ?? 1024) > 4096 ? 180 : 60;
        $resp = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->retry(2, 500)->timeout($timeout)->post(self::BASE.'/messages', $body);

        if (! $resp->successful()) {
            throw new \RuntimeException('Anthropic chat failed: '.$resp->body());
        }

        $json = $resp->json();
        $latency = (int) ((microtime(true) - $start) * 1000);
        $content = $json['content'][0]['text'] ?? '';

        return new LlmResponse(
            content: $content,
            promptTokens: $json['usage']['input_tokens'] ?? 0,
            completionTokens: $json['usage']['output_tokens'] ?? 0,
            model: $json['model'] ?? $this->chatModel,
            latencyMs: $latency,
            finishReason: $json['stop_reason'] ?? null,
        );
    }

    public function embed(array $texts): array
    {
        throw new \RuntimeException('Anthropic does not support embeddings natively. Use OpenAI or Gemini.');
    }

    public function transcribe(string $bytes, string $mimeType, array $opts = []): LlmResponse
    {
        throw new \RuntimeException('Anthropic does not support audio transcription. Use OpenAI or Gemini.');
    }
}
