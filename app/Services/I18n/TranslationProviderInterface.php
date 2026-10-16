<?php

namespace App\Services\I18n;

interface TranslationProviderInterface
{
    /**
     * Translate text from source locale to target locale.
     * Return null if translation is not available (caller will fallback to source text).
     */
    public function translate(string $text, string $sourceLocale, string $targetLocale): ?string;
}
