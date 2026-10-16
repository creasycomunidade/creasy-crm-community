<?php

namespace App\Services\I18n;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Read/write translations from resources/js/locales/{code}.json.
 * JSON files use nested structure (e.g. { "common": { "save": "Save" } }).
 * API and admin use flat keys (e.g. "common.save" => "Save").
 */
class I18nFileService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = resource_path('js/locales');
    }

    public function path(string $code): string
    {
        return $this->basePath.'/'.preg_replace('/[^a-z0-9_-]/i', '', $code).'.json';
    }

    /** Check if a locale file exists. */
    public function exists(string $code): bool
    {
        return File::exists($this->path($code));
    }

    /**
     * Flatten nested array to dot keys.
     * ["common" => ["save" => "Save"]] => ["common.save" => "Save"]
     */
    public static function flatten(array $nested, string $prefix = ''): array
    {
        $out = [];
        foreach ($nested as $k => $v) {
            $key = $prefix === '' ? $k : $prefix.'.'.$k;
            if (is_array($v) && self::isAssoc($v)) {
                $out = array_merge($out, self::flatten($v, $key));
            } elseif (is_array($v)) {
                // Non-associative (list) values are not valid translation strings.
                // Skip them so malformed keys can never reach the UI as a non-string
                // leaf (which crashes the React translations table).
                continue;
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /**
     * Unflatten dot keys to nested array.
     * ["common.save" => "Save"] => ["common" => ["save" => "Save"]]
     */
    public static function unflatten(array $flat): array
    {
        $out = [];
        foreach ($flat as $key => $value) {
            $parts = explode('.', $key);
            $ref = &$out;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $ref[$part] = $value;
                } else {
                    if (! isset($ref[$part]) || ! is_array($ref[$part])) {
                        $ref[$part] = [];
                    }
                    $ref = &$ref[$part];
                }
            }
        }

        return $out;
    }

    private static function isAssoc(array $a): bool
    {
        if ($a === []) {
            return true;
        }

        return array_keys($a) !== range(0, count($a) - 1);
    }

    private function cacheVersion(): string
    {
        return Cache::get('i18n_version', '0');
    }

    /** Read locale file and return flat key => value. */
    public function getFlatDictionary(string $code): array
    {
        $version = $this->cacheVersion();
        $cacheKey = 'i18n:file:'.$code.':'.$version;

        return Cache::remember($cacheKey, 3600, function () use ($code) {
            $path = $this->path($code);
            if (! File::exists($path)) {
                return [];
            }
            $content = File::get($path);
            $decoded = json_decode($content, true);

            return is_array($decoded) ? self::flatten($decoded) : [];
        });
    }

    /** Write flat dictionary to locale file (converts to nested JSON). */
    public function putFlatDictionary(string $code, array $flat): bool
    {
        $path = $this->path($code);
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $nested = self::unflatten($flat);
        $json = json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $written = File::put($path, $json);
        if ($written !== false) {
            $this->invalidateCache($code);
        }

        return $written !== false;
    }

    /** Get all flat keys from English file (source of truth for key list). */
    public function allKeys(): array
    {
        $flat = $this->getFlatDictionary('en');

        return array_keys($flat);
    }

    /** Get groups (first segment of keys) from en. */
    public function groups(): array
    {
        $keys = $this->allKeys();
        $groups = [];
        foreach ($keys as $k) {
            if (str_contains($k, '.')) {
                $groups[] = explode('.', $k)[0];
            } else {
                $groups[] = 'app';
            }
        }

        return array_values(array_unique($groups));
    }

    /** Create a new locale file (copy from en or empty). */
    public function createLocaleFile(string $code, bool $copyFromEn = true): bool
    {
        $path = $this->path($code);
        if (File::exists($path)) {
            return true;
        }
        if ($copyFromEn) {
            $en = $this->getFlatDictionary('en');

            return $this->putFlatDictionary($code, $en);
        }

        return $this->putFlatDictionary($code, []);
    }

    public function invalidateCache(?string $code = null): void
    {
        Cache::put('i18n_version', (string) now()->timestamp, 86400 * 365);
    }
}
