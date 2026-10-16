<?php

namespace App\Modules\Integrations\Services;

use App\Models\Workspace;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\Broadcasting\Models\SmsProviderConfig;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Services\Credentials\CredentialValueObject;
use App\Modules\Integrations\Services\Credentials\GenericCredentials;
use App\Modules\Integrations\Services\Credentials\LlmCredentials;
use App\Modules\Integrations\Services\Credentials\MetaCredentials;
use App\Modules\Integrations\Services\Credentials\OAuthClientCredentials;
use App\Modules\Integrations\Services\Credentials\SmsCredentials;

/**
 * Resolves credentials for a given provider.
 * Lookup order: workspace override -> system default -> null.
 *
 * Drivers must NEVER call config() or env() directly — only use this service.
 *
 * Usage:
 *   CredentialResolver::system()->meta()
 *   CredentialResolver::system()->oauth('linkedin')
 *   CredentialResolver::for($workspace)->llm('openai')
 *   CredentialResolver::for($workspace)->sms('twilio')
 */
class CredentialResolver
{
    public function __construct(private readonly ?Workspace $workspace = null) {}

    public static function system(): static
    {
        return new static(null);
    }

    public static function for(Workspace $workspace): static
    {
        return new static($workspace);
    }

    // ─── System-level providers ──────────────────────────────────────────────

    public function meta(): ?MetaCredentials
    {
        return $this->resolve('meta_app', MetaCredentials::class);
    }

    public function oauth(string $network): ?OAuthClientCredentials
    {
        // Facebook and Instagram share the same Meta App credentials (App ID / App Secret).
        // Resolve from meta_app so there is a single place to configure them.
        if ($network === 'facebook' || $network === 'instagram') {
            $config = IntegrationConfig::forProvider('meta_app');
            if (! $config || ! $config->enabled) {
                return null;
            }
            $creds = $config->credentials ?? [];
            if (empty($creds['app_id']) || empty($creds['app_secret'])) {
                return null;
            }

            // Map meta_app keys → OAuthClientCredentials keys
            return new OAuthClientCredentials([
                'client_id' => $creds['app_id'],
                'client_secret' => $creds['app_secret'],
            ]);
        }

        return $this->resolve('oauth_'.$network, OAuthClientCredentials::class);
    }

    /** @param string $provider  openai|anthropic|gemini */
    public function llm(string $provider): ?LlmCredentials
    {
        // Check workspace override table first (ai_provider_configs loaded via model)
        if ($this->workspace) {
            $override = $this->workspaceLlmConfig($provider);
            if ($override) {
                return new LlmCredentials($override);
            }
        }

        return $this->resolve('llm_'.$provider.'_default', LlmCredentials::class);
    }

    /** @param string $provider  twilio|nexmo|messagebird|smsbd|reve */
    public function sms(string $provider): ?SmsCredentials
    {
        // Check workspace override table first (sms_provider_configs)
        if ($this->workspace) {
            $override = $this->workspaceSmsConfig($provider);
            if ($override) {
                return new SmsCredentials($override);
            }
        }

        return $this->resolve('sms_'.$provider.'_default', SmsCredentials::class);
    }

    public function googlePlaces(): ?GenericCredentials
    {
        return $this->resolve('google_places', GenericCredentials::class);
    }

    public function firecrawl(): ?GenericCredentials
    {
        return $this->resolve('firecrawl', GenericCredentials::class);
    }

    /** Google Workspace OAuth creds (Sheets / Docs / Calendar / Meet). */
    public function google(): ?GenericCredentials
    {
        return $this->resolve('google_workspace', GenericCredentials::class);
    }

    public function qdrant(): ?GenericCredentials
    {
        return $this->resolve('qdrant', GenericCredentials::class);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function resolve(string $provider, string $voClass): ?CredentialValueObject
    {
        $config = IntegrationConfig::forProvider($provider);
        if (! $config || ! $config->enabled) {
            return null;
        }
        $creds = $config->credentials ?? [];
        if (empty($creds)) {
            return null;
        }

        return new $voClass($creds);
    }

    private function workspaceLlmConfig(string $provider): ?array
    {
        // Lazy resolution to avoid hard coupling to AI module at boot
        if (! class_exists('App\\Modules\\AI\\Models\\AiProviderConfig')) {
            return null;
        }
        $model = AiProviderConfig::where('workspace_id', $this->workspace->id)
            ->where('provider', $provider)
            ->where('enabled', true)
            ->first();

        $creds = $model?->credentials;

        return ! empty($creds['api_key']) ? $creds : null;
    }

    private function workspaceSmsConfig(string $provider): ?array
    {
        if (! class_exists('App\\Modules\\Broadcasting\\Models\\SmsProviderConfig')) {
            return null;
        }
        $model = SmsProviderConfig::where('workspace_id', $this->workspace->id)
            ->where('provider', $provider)
            ->first();

        return $model?->credentials;
    }
}
