<?php

namespace App\Modules\AI\Services\Llm;

use Illuminate\Support\Facades\Http;

class GeminiProvider implements LlmProviderInterface
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $chatModel = 'gemini-3.5-flash-lite',
        private readonly string $embedModel = 'gemini-embedding-001',
    ) {}

    public function chat(array $messages, array $opts = []): LlmResponse
    {
        $start = microtime(true);
        $model = $opts['model'] ?? $this->chatModel;

        // Extract system instruction separately; remaining turns mapped to user/model
        $systemInstruction = null;
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $m['content']]]];
            } else {
                $contents[] = [
                    'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $m['content']]],
                ];
            }
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => $opts['max_tokens'] ?? 1024],
        ];
        if ($systemInstruction) {
            $body['systemInstruction'] = $systemInstruction;
        }

        $timeout = ($opts['max_tokens'] ?? 1024) > 4096 ? 180 : 60;
        $resp = Http::retry(2, 500)->timeout($timeout)
            ->post(self::BASE."/models/{$model}:generateContent?key={$this->apiKey}", $body);

        if (! $resp->successful()) {
            throw new \RuntimeException('Gemini chat failed: '.$resp->body());
        }

        $json = $resp->json();
        $latency = (int) ((microtime(true) - $start) * 1000);
        $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $meta = $json['usageMetadata'] ?? [];

        return new LlmResponse(
            content: $content,
            promptTokens: $meta['promptTokenCount'] ?? 0,
            completionTokens: $meta['candidatesTokenCount'] ?? 0,
            model: $model,
            latencyMs: $latency,
            finishReason: $json['candidates'][0]['finishReason'] ?? null,
        );
    }

    public function embed(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $requests = array_map(fn ($text) => [
            'model' => 'models/'.$this->embedModel,
            'content' => ['parts' => [['text' => $text]]],
        ], $texts);

        $resp = Http::retry(2, 500)->timeout(60)->post(
            self::BASE."/models/{$this->embedModel}:batchEmbedContents?key={$this->apiKey}",
            ['requests' => $requests]
        );

        if (! $resp->successful()) {
            throw new \RuntimeException('Gemini batch embed failed: '.$resp->body());
        }

        return array_map(
            fn ($e) => $e['values'] ?? [],
            $resp->json('embeddings', [])
        );
    }

    public function transcribe(string $bytes, string $mimeType, array $opts = []): LlmResponse
    {
        $start = microtime(true);
        $model = $opts['model'] ?? $this->chatModel;
        $mimeType = strtolower(trim(explode(';', $mimeType, 2)[0])) ?: 'audio/ogg';
        $resp = Http::retry(2, 500)->timeout(120)
            ->post(self::BASE."/models/{$model}:generateContent?key={$this->apiKey}", [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Transcreva fielmente este áudio em português do Brasil. Retorne somente a transcrição, sem comentários.'],
                        ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($bytes)]],
                    ],
                ]],
                'generationConfig' => ['maxOutputTokens' => 4096, 'temperature' => 0],
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('Gemini transcription failed: '.$resp->body());
        }

        $json = $resp->json();
        $meta = $json['usageMetadata'] ?? [];

        return new LlmResponse(
            content: trim((string) ($json['candidates'][0]['content']['parts'][0]['text'] ?? '')),
            promptTokens: (int) ($meta['promptTokenCount'] ?? 0),
            completionTokens: (int) ($meta['candidatesTokenCount'] ?? 0),
            model: $model,
            latencyMs: (int) ((microtime(true) - $start) * 1000),
        );
    }
}
