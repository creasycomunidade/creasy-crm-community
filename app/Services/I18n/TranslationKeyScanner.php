<?php

namespace App\Services\I18n;

use Illuminate\Support\Str;

class TranslationKeyScanner
{
    /**
     * Discover translation keys from JS/TS/JSX/TSX and Blade files.
     * Returns list of keys (e.g. "common.save", "nav.dashboard") with optional group.
     */
    public function discoverKeys(): array
    {
        $keys = [];
        $base = base_path();

        $jsDir = $base.'/resources/js';
        $jsExt = ['jsx', 'tsx', 'js', 'ts'];
        $this->globRecursive($jsDir, $jsExt, function ($path) use (&$keys) {
            $content = @file_get_contents($path);
            if ($content) {
                $this->extractKeysFromJs($content, $keys);
            }
        });

        $viewsDir = $base.'/resources/views';
        $this->globRecursive($viewsDir, ['php'], function ($path) use (&$keys) {
            if (! str_ends_with($path, '.blade.php')) {
                return;
            }
            $content = @file_get_contents($path);
            if ($content) {
                $this->extractKeysFromBlade($content, $keys);
            }
        });

        return array_values(array_unique($keys));
    }

    private function globRecursive(string $dir, array $extensions, callable $callback): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $extensions, true)) {
                $callback($path);
            }
        }
    }

    /**
     * Extract keys from JS: t('key'), t("key"), i18n.t('key'), __('key').
     */
    private function extractKeysFromJs(string $content, array &$keys): void
    {
        // t('...') or t("...") or t(`...`)
        if (preg_match_all('/\bt\s*\(\s*[\'"`]([^\'"`]+)[\'"`]\s*\)/u', $content, $m)) {
            foreach ($m[1] as $key) {
                $keys[] = trim($key);
            }
        }
        // i18n.t('...')
        if (preg_match_all('/i18n\.t\s*\(\s*[\'"`]([^\'"`]+)[\'"`]\s*\)/u', $content, $m)) {
            foreach ($m[1] as $key) {
                $keys[] = trim($key);
            }
        }
        // useTranslation then t('...') - same pattern as t('...')
        // Nested keys in JSON: "common.save" - we also want to support keys with dots
        // Keys that look like "group.key"
        if (preg_match_all('/[\'"`]([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)+)[\'"`]\s*(?:\)|,|\s)/u', $content, $m)) {
            foreach ($m[1] as $key) {
                if (Str::contains($key, '.') && strlen($key) > 2 && strlen($key) < 120) {
                    $keys[] = trim($key);
                }
            }
        }
    }

    private function extractKeysFromBlade(string $content, array &$keys): void
    {
        // @lang('...') or __('...')
        if (preg_match_all('/@lang\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/u', $content, $m)) {
            foreach ($m[1] as $key) {
                $keys[] = trim($key);
            }
        }
        if (preg_match_all('/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/u', $content, $m)) {
            foreach ($m[1] as $key) {
                $keys[] = trim($key);
            }
        }
    }

    /**
     * Convert a key like "common.save" to group "common" and key "save"; "dashboard" to group "app", key "dashboard".
     */
    public function keyToGroupAndKey(string $flatKey): array
    {
        if (Str::contains($flatKey, '.')) {
            $parts = explode('.', $flatKey);
            $key = array_pop($parts);
            $group = implode('.', $parts) ?: 'app';

            return [$group, $key];
        }

        return ['app', $flatKey];
    }

    /**
     * Generate a human-readable English label from key (e.g. "sidebar.dashboard" -> "Dashboard").
     */
    public function keyToDefaultEnglish(string $flatKey): string
    {
        [$group, $key] = $this->keyToGroupAndKey($flatKey);
        $keyPart = $key;
        $keyPart = str_replace(['_', '-'], ' ', $keyPart);

        return Str::title($keyPart);
    }
}
