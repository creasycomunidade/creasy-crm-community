<?php

namespace App\Modules\AI\Services\Llm;

class LlmResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly string $model,
        public readonly int $latencyMs,
        public readonly ?string $finishReason = null,
    ) {}

    public function wasTruncated(): bool
    {
        return in_array(strtolower((string) $this->finishReason), ['max_tokens', 'length'], true);
    }
}
