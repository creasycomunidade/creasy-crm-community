<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Translation extends Model
{
    protected $fillable = ['group', 'key', 'locale_code', 'value'];

    /** Invalidate i18n cache so next request gets fresh data. */
    public static function invalidateCache(): void
    {
        Cache::put('i18n_version', (string) now()->timestamp, 86400 * 365);
    }

    /** Get cache version (bump on any translation/locale change). */
    public static function cacheVersion(): string
    {
        $v = Cache::get('i18n_version');
        if ($v) {
            return $v;
        }
        self::invalidateCache();

        return Cache::get('i18n_version', '0');
    }

    /** Build flat key for frontend: "group.key" or "key" when group is "app". */
    public function getFlatKeyAttribute(): string
    {
        return $this->group === 'app' ? $this->key : $this->group.'.'.$this->key;
    }

    /** Get all translations for a locale as flat key => value (for JSON API). */
    public static function dictionaryForLocale(string $localeCode, string $group = 'app'): array
    {
        $version = self::cacheVersion();
        $cacheKey = "i18n:{$localeCode}:{$version}";

        return Cache::remember($cacheKey, 3600, function () use ($localeCode, $group) {
            $rows = self::where('locale_code', $localeCode)
                ->where('group', $group)
                ->get(['key', 'value']);

            $out = [];
            foreach ($rows as $row) {
                $out[$row->key] = $row->value ?? '';
            }

            return $out;
        });
    }

    /** Get all groups merged for a locale (key => value, keys may be "group.key"). */
    public static function fullDictionaryForLocale(string $localeCode): array
    {
        $version = self::cacheVersion();
        $cacheKey = "i18n:full:{$localeCode}:{$version}";

        return Cache::remember($cacheKey, 3600, function () use ($localeCode) {
            $rows = self::where('locale_code', $localeCode)->get(['group', 'key', 'value']);
            $out = [];
            foreach ($rows as $row) {
                $flat = $row->group === 'app' ? $row->key : $row->group.'.'.$row->key;
                $out[$flat] = $row->value ?? '';
            }

            return $out;
        });
    }
}
