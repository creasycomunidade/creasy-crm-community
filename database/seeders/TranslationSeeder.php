<?php

namespace Database\Seeders;

use App\Models\Locale;
use App\Services\I18n\DefaultTranslations;
use App\Services\I18n\FallbackTranslationProvider;
use App\Services\I18n\I18nFileService;
use App\Services\I18n\TranslationKeyScanner;
use App\Services\I18n\TranslationProviderInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class TranslationSeeder extends Seeder
{
    /**
     * Ensure 4 locales exist and JSON files in resources/js/locales/ are populated.
     * Uses DefaultTranslations + discovered keys; other locales get translated or English fallback.
     */
    public function run(): void
    {
        $this->call(LocaleSeeder::class);

        $i18nFiles = app(I18nFileService::class);
        $scanner = new TranslationKeyScanner;
        $discoveredKeys = $scanner->discoverKeys();
        $defaultMap = DefaultTranslations::all();
        $allKeys = array_values(array_unique(array_merge($discoveredKeys, array_keys($defaultMap))));

        $provider = $this->getTranslationProvider();
        $enCode = 'en';
        $localeCodes = Locale::pluck('code')->all();

        $enFlat = $i18nFiles->getFlatDictionary($enCode);
        foreach ($allKeys as $flatKey) {
            $enFlat[$flatKey] = $defaultMap[$flatKey] ?? $enFlat[$flatKey] ?? $scanner->keyToDefaultEnglish($flatKey);
        }
        $i18nFiles->putFlatDictionary($enCode, $enFlat);

        foreach ($localeCodes as $code) {
            if ($code === $enCode) {
                continue;
            }
            $flat = $i18nFiles->getFlatDictionary($code);
            foreach ($allKeys as $flatKey) {
                $enVal = $enFlat[$flatKey] ?? '';
                if (($flat[$flatKey] ?? '') === '' || $flat[$flatKey] === $enVal) {
                    $translated = $provider->translate($enVal, $enCode, $code);
                    $flat[$flatKey] = $translated ?? $enVal;
                }
            }
            $i18nFiles->putFlatDictionary($code, $flat);
        }

        $i18nFiles->invalidateCache();
    }

    private function getTranslationProvider(): TranslationProviderInterface
    {
        if (App::bound(TranslationProviderInterface::class)) {
            return App::make(TranslationProviderInterface::class);
        }

        return new FallbackTranslationProvider;
    }
}
