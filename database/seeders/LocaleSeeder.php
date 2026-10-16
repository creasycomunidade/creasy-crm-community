<?php

namespace Database\Seeders;

use App\Models\Locale;
use Illuminate\Database\Seeder;

class LocaleSeeder extends Seeder
{
    /**
     * Seed default 15 languages: Portuguese (Brazil) (default), English, Bangla, Arabic (RTL), Hindi, French,
     * Spanish, Portuguese, German, Chinese, Russian, Japanese, Korean, Italian, Turkish, Indonesian.
     * Idempotent: updateOrCreate by code.
     */
    public function run(): void
    {
        $defaultCode = 'pt';

        $locales = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'bn',
                'name' => 'Bangla',
                'native_name' => 'বাংলা',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'ar',
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'hi',
                'name' => 'Hindi',
                'native_name' => 'हिन्दी',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 4,
            ],
            [
                'code' => 'fr',
                'name' => 'French',
                'native_name' => 'Français',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 5,
            ],
            [
                'code' => 'es',
                'name' => 'Spanish',
                'native_name' => 'Español',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 6,
            ],
            [
                'code' => 'pt',
                'name' => 'Portuguese (Brazil)',
                'native_name' => 'Português (Brasil)',
                'flag' => '🇧🇷',
                'enabled' => true,
                'is_default' => true,
                'is_rtl' => false,
                'sort_order' => 7,
            ],
            [
                'code' => 'de',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 8,
            ],
            [
                'code' => 'zh',
                'name' => 'Chinese',
                'native_name' => '中文',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 9,
            ],
            [
                'code' => 'ru',
                'name' => 'Russian',
                'native_name' => 'Русский',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 10,
            ],
            [
                'code' => 'ja',
                'name' => 'Japanese',
                'native_name' => '日本語',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 11,
            ],
            [
                'code' => 'ko',
                'name' => 'Korean',
                'native_name' => '한국어',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 12,
            ],
            [
                'code' => 'it',
                'name' => 'Italian',
                'native_name' => 'Italiano',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 13,
            ],
            [
                'code' => 'tr',
                'name' => 'Turkish',
                'native_name' => 'Türkçe',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 14,
            ],
            [
                'code' => 'id',
                'name' => 'Indonesian',
                'native_name' => 'Bahasa Indonesia',
                'flag' => null,
                'enabled' => true,
                'is_default' => false,
                'is_rtl' => false,
                'sort_order' => 15,
            ],
        ];

        // Ensure only one default
        Locale::where('is_default', true)->update(['is_default' => false]);

        foreach ($locales as $row) {
            Locale::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
