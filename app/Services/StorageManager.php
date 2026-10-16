<?php

namespace App\Services;

use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for file storage configuration.
 *
 * ALL credentials live encrypted in the `integration_configs` DB table.
 * No .env keys are read here — zero fallback to the environment.
 *
 * Admins configure a storage provider via Admin › Integrations › Storage.
 * The resolved disk config and directory prefix are cached (60 s) so there
 * is at most one DB read per minute per process instead of one per upload.
 */
class StorageManager
{
    private const CACHE_KEY    = 'storage_manager_resolved';
    private const CACHE_TTL    = 60; // seconds

    /**
     * Returns the active filesystem disk instance.
     * Falls back to the 'public' local disk when no cloud provider is enabled.
     */
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Returns the Laravel disk name for the active provider.
     * For cloud providers this also injects the DB credentials into the
     * runtime config before returning, ensuring the disk is ready to use.
     */
    public function diskName(): string
    {
        $resolved = $this->resolved();

        if ($resolved['provider'] === null || $resolved['provider'] === 'storage_local') {
            return 'public';
        }

        $diskName = IntegrationConfig::STORAGE_DISK_MAP[$resolved['provider']] ?? 'public';

        $this->injectDiskConfig($diskName, $resolved['disk_config']);

        return $diskName;
    }

    /**
     * Returns the directory prefix (with trailing slash) to prepend to paths,
     * or an empty string for local storage / providers with no prefix set.
     */
    public function directoryPrefix(): string
    {
        return $this->resolved()['directory_prefix'];
    }

    /**
     * Prepend the active directory prefix to an arbitrary storage path.
     */
    public function prefixedPath(string $path): string
    {
        $prefix = $this->directoryPrefix();

        return $prefix !== '' ? $prefix.ltrim($path, '/') : $path;
    }

    /**
     * Returns the active provider slug or null if nothing is enabled.
     */
    public function activeProvider(): ?string
    {
        return $this->resolved()['provider'];
    }

    /**
     * Clears the in-process and persistent cache.
     * Must be called whenever the admin saves or toggles a storage integration.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ensures credentials are injected into runtime config for the given disk name.
     * Use this when rendering URLs for media records that may live on any disk.
     */
    public function ensureDiskReady(string $diskName): void
    {
        if ($diskName === 'public') {
            return;
        }

        $providerSlug = array_search($diskName, IntegrationConfig::STORAGE_DISK_MAP, true);
        if (! $providerSlug) {
            return;
        }

        $config = IntegrationConfig::forProvider($providerSlug);
        if (! $config) {
            return;
        }

        $diskConfig = $this->buildDiskConfig($providerSlug, $config->credentials ?? []);
        $this->injectDiskConfig($diskName, $diskConfig);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolves and caches the full storage configuration from the DB.
     *
     * Returns:
     *   [
     *     'provider'         => 'storage_do' | 'storage_local' | null,
     *     'disk_config'      => [...],  // ready-to-inject Laravel disk array
     *     'directory_prefix' => 'uploads/' | '',
     *   ]
     */
    private function resolved(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            // Prefer the admin-designated default provider; fall back to first enabled
            $defaultConfig = IntegrationConfig::whereIn('provider', IntegrationConfig::STORAGE_PROVIDERS)
                ->where('mode', 'live')
                ->where('enabled', true)
                ->where('is_default', true)
                ->first();

            $orderedProviders = $defaultConfig
                ? array_merge([$defaultConfig->provider], array_diff(IntegrationConfig::STORAGE_PROVIDERS, [$defaultConfig->provider]))
                : IntegrationConfig::STORAGE_PROVIDERS;

            foreach ($orderedProviders as $provider) {
                $config = IntegrationConfig::forProvider($provider);

                if (! $config || ! $config->enabled) {
                    continue;
                }

                if ($provider === 'storage_local') {
                    return [
                        'provider'         => 'storage_local',
                        'disk_config'      => [],
                        'directory_prefix' => '',
                    ];
                }

                $creds = $config->credentials ?? [];

                $diskConfig = $this->buildDiskConfig($provider, $creds);

                $prefix = trim($creds['directory_prefix'] ?? '', '/');

                return [
                    'provider'         => $provider,
                    'disk_config'      => $diskConfig,
                    'directory_prefix' => $prefix !== '' ? $prefix.'/' : '',
                ];
            }

            // Nothing enabled → use local public disk
            return [
                'provider'         => null,
                'disk_config'      => [],
                'directory_prefix' => '',
            ];
        });
    }

    /**
     * Builds a complete Laravel disk config array from DB credentials.
     * No .env reads — only what the admin entered in the integrations panel.
     */
    private function buildDiskConfig(string $provider, array $creds): array
    {
        return match ($provider) {
            'storage_s3' => [
                'driver'                  => 's3',
                'key'                     => $creds['key']    ?? null,
                'secret'                  => $creds['secret'] ?? null,
                'region'                  => $creds['region'] ?? 'us-east-1',
                'bucket'                  => $creds['bucket'] ?? null,
                'url'                     => $creds['url']    ?? null,
                'endpoint'                => null,
                'use_path_style_endpoint' => false,
                'throw'                   => false,
            ],
            'storage_do' => [
                'driver'                  => 's3',
                'key'                     => $creds['key']      ?? null,
                'secret'                  => $creds['secret']   ?? null,
                'region'                  => $creds['region']   ?? 'nyc3',
                'bucket'                  => $creds['bucket']   ?? null,
                'url'                     => $creds['url']      ?? null,
                'endpoint'                => $creds['endpoint'] ?? 'https://nyc3.digitaloceanspaces.com',
                'use_path_style_endpoint' => false,
                'throw'                   => false,
                'visibility'              => 'public',
                'options'                 => ['ACL' => 'public-read'],
            ],
            'storage_wasabi' => [
                'driver'                  => 's3',
                'key'                     => $creds['key']      ?? null,
                'secret'                  => $creds['secret']   ?? null,
                'region'                  => $creds['region']   ?? 'us-east-1',
                'bucket'                  => $creds['bucket']   ?? null,
                'url'                     => $creds['url']      ?? null,
                'endpoint'                => $creds['endpoint'] ?? 'https://s3.wasabisys.com',
                'use_path_style_endpoint' => false,
                'throw'                   => false,
            ],
            default => [],
        };
    }

    /**
     * Push the DB-sourced credentials into Laravel's runtime config and
     * forget any previously resolved disk instance so it is rebuilt fresh.
     */
    private function injectDiskConfig(string $diskName, array $diskConfig): void
    {
        if (empty($diskConfig)) {
            return;
        }

        Config::set("filesystems.disks.{$diskName}", $diskConfig);
        Storage::forgetDisk($diskName);
    }
}
