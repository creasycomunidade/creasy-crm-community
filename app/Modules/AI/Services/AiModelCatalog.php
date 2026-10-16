<?php

namespace App\Modules\AI\Services;

class AiModelCatalog
{
    private const PROVIDERS = [
        'openai' => [
            'label' => 'OpenAI',
            'models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'],
        ],
        'anthropic' => [
            'label' => 'Anthropic',
            'models' => ['claude-3-haiku-20240307', 'claude-3-5-haiku-latest', 'claude-3-5-sonnet-latest'],
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'models' => ['gemini-3.5-flash-lite', 'gemini-3.5-flash', 'gemini-3.6-flash', 'gemini-2.5-flash'],
        ],
    ];

    public static function all(): array
    {
        return self::PROVIDERS;
    }

    public static function models(string $provider): array
    {
        return self::PROVIDERS[$provider]['models'] ?? [];
    }

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? ucfirst($provider);
    }
}
