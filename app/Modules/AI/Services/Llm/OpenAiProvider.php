<?php

namespace App\Modules\AI\Services\Llm;

use Illuminate\Support\Facades\Http;

class OpenAiProvider implements LlmProviderInterface
{
    private const BASE = 'https://api.openai.com/v1';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $chatModel = 'gpt-4o-mini',
        private readonly string $embedModel = 'text-embedding-3-small',
        private readonly ?string $organization = null,
    ) {}

    public function chat(array $messages, array $opts = []): LlmResponse
    {
        $start = microtime(true);
        $headers = ['Authorization' => 'Bearer '.$this->apiKey];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        $timeout = ($opts['max_tokens'] ?? 1024) > 4096 ? 180 : 60;
        $resp = Http::withHeaders($headers)->retry(2, 500)->timeout($timeout)->post(self::BASE.'/chat/completions', [
            'model' => $opts['model'] ?? $this->chatModel,
            'messages' => $messages,
            'max_tokens' => $opts['max_tokens'] ?? 1024,
            'temperature' => $opts['temperature'] ?? 0.7,
        ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('OpenAI chat failed: '.$resp->body());
        }

        $json = $resp->json();
        $latency = (int) ((microtime(true) - $start) * 1000);

        return new LlmResponse(
            content: $json['choices'][0]['message']['content'] ?? '',
            promptTokens: $json['usage']['prompt_tokens'] ?? 0,
            completionTokens: $json['usage']['completion_tokens'] ?? 0,
            model: $json['model'] ?? $this->chatModel,
            latencyMs: $latency,
            finishReason: $json['choices'][0]['finish_reason'] ?? null,
        );
    }

    public function embed(array $texts): array
    {
        $resp = Http::withToken($this->apiKey)->retry(2, 500)->timeout(30)->post(self::BASE.'/embeddings', [
            'model' => $this->embedModel,
            'input' => $texts,
        ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('OpenAI embed failed: '.$resp->body());
        }

        return array_column($resp->json()['data'] ?? [], 'embedding');
    }

    public function transcribe(string $bytes, string $mimeType, array $opts = []): LlmResponse
    {
        $start = microtime(true);
        $model = $opts['model'] ?? 'whisper-1';
        $extension = match (strtolower(trim(explode(';', $mimeType, 2)[0]))) {
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/aac' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            default => 'ogg',
        };
        $headers = ['Authorization' => 'Bearer '.$this->apiKey];
        if ($this->organization) {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        $resp = Http::withHeaders($headers)
            ->attach('file', $bytes, 'audio.'.$extension)
            ->timeout(120)
            ->retry(1, 500)
            ->post(self::BASE.'/audio/transcriptions', [
                'model' => $model,
                'language' => $opts['language'] ?? 'pt',
                'response_format' => 'json',
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('OpenAI transcription failed: '.$resp->body());
        }

        return new LlmResponse(
            content: trim((string) $resp->json('text', '')),
            promptTokens: 0,
            completionTokens: (int) ceil(mb_strlen((string) $resp->json('text', '')) / 4),
            model: $model,
            latencyMs: (int) ((microtime(true) - $start) * 1000),
        );
    }
}
