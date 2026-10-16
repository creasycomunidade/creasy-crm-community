<?php

namespace App\Services\I18n;

/**
 * No external API: return null so caller uses source (English) as fallback.
 */
class FallbackTranslationProvider implements TranslationProviderInterface
{
    public function translate(string $text, string $sourceLocale, string $targetLocale): ?string
    {
        return null;
    }
}
