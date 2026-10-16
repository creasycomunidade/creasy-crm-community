<?php

namespace App\Console\Commands;

use App\Models\Locale;
use App\Services\I18n\I18nFileService;
use App\Services\I18n\TranslationKeyScanner;
use Illuminate\Console\Command;

class I18nScanCommand extends Command
{
    protected $signature = 'i18n:scan
                            {--sync : Insert/update missing keys in JSON files for all locales}
                            {--locale= : Only sync this locale (default: all)}';

    protected $description = 'Scan codebase for translation keys (t("...")) and optionally sync to resources/js/locales/*.json';

    public function handle(): int
    {
        $scanner = new TranslationKeyScanner;
        $keys = $scanner->discoverKeys();

        $this->info('Discovered '.count($keys).' translation keys.');

        if ($this->option('sync')) {
            $i18nFiles = app(I18nFileService::class);
            $localeCode = $this->option('locale');
            $locales = $localeCode
                ? (Locale::where('code', $localeCode)->pluck('code')->all() ?: [$localeCode])
                : Locale::pluck('code')->all();

            if (empty($locales)) {
                $locales = ['en'];
                $this->warn('No locales in DB; syncing to en only. Run php artisan db:seed --class=LocaleSeeder first for more.');
            }

            $enCode = 'en';
            $enFlat = $i18nFiles->getFlatDictionary($enCode);

            foreach ($keys as $flatKey) {
                $enFlat[$flatKey] = $enFlat[$flatKey] ?? $scanner->keyToDefaultEnglish($flatKey);
            }
            $i18nFiles->putFlatDictionary($enCode, $enFlat);

            foreach ($locales as $code) {
                if ($code === $enCode) {
                    continue;
                }
                $flat = $i18nFiles->getFlatDictionary($code);
                foreach ($keys as $flatKey) {
                    if (($flat[$flatKey] ?? '') === '') {
                        $flat[$flatKey] = $enFlat[$flatKey] ?? $scanner->keyToDefaultEnglish($flatKey);
                    }
                }
                $i18nFiles->putFlatDictionary($code, $flat);
            }

            $i18nFiles->invalidateCache();
            $this->info('Synced '.count($keys).' keys to JSON for '.count($locales).' locale(s).');
        } else {
            $this->line(implode("\n", array_slice($keys, 0, 50)));
            if (count($keys) > 50) {
                $this->line('... and '.(count($keys) - 50).' more. Use --sync to sync to JSON files.');
            }
        }

        return self::SUCCESS;
    }
}
