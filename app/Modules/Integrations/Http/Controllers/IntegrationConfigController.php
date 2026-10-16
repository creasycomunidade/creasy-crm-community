<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\IntegrationAuditLog;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Services\ConnectionTester;
use App\Services\StorageManager;
use App\Support\PublicHttpUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationConfigController extends Controller
{
    public function index(): Response
    {
        $configs = IntegrationConfig::whereIn('provider', IntegrationConfig::PROVIDERS)->get()->keyBy('provider');

        $grouped = [];
        foreach (IntegrationConfig::PROVIDERS as $provider) {
            $config = $configs->get($provider);
            $category = IntegrationConfig::CATEGORIES[$provider] ?? 'Other';
            $grouped[$category][] = [
                'provider' => $provider,
                'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
                'category' => $category,
                'enabled' => $config?->enabled ?? false,
                'is_default' => $config?->is_default ?? false,
                'mode' => $config?->mode ?? 'live',
                'configured' => $config?->isConfigured() ?? false,
                'last_test_status' => $config?->last_test_status ?? 'untested',
                'last_test_message' => $config?->last_test_message,
                'last_tested_at' => $config?->last_tested_at?->toISOString(),
            ];
        }

        return Inertia::render('Admin/Integrations/Index', [
            'grouped' => $grouped,
        ]);
    }

    public function edit(string $provider): Response
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider) ?? new IntegrationConfig([
            'provider' => $provider,
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'mode' => 'live',
            'enabled' => false,
        ]);

        return Inertia::render('Admin/Integrations/Edit', [
            'provider' => $provider,
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'category' => IntegrationConfig::CATEGORIES[$provider] ?? 'Other',
            'fields' => IntegrationConfig::FIELDS[$provider] ?? [],
            // OAuth redirect/callback URL the admin must register in the platform's app settings.
            'callbackUrl' => match ($provider) {
                'oauth_shopify' => route('client.ecommerce.oauth.shopify.callback'),
                'oauth_bigcommerce' => route('client.ecommerce.oauth.bigcommerce.callback'),
                'google_workspace' => route('client.ai.google-drive.callback'),
                default => null,
            },
            'config' => [
                'enabled' => $config->enabled ?? false,
                'mode' => $config->mode ?? 'live',
                'last_test_status' => $config->last_test_status ?? 'untested',
                'last_test_message' => $config->last_test_message,
                'last_tested_at' => $config->last_tested_at?->toISOString(),
                'credentials' => $config->exists ? $config->maskedCredentials() : [],
            ],
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $fields = IntegrationConfig::FIELDS[$provider] ?? [];
        $rules = ['enabled' => ['required', 'boolean'], 'mode' => ['required', 'in:test,live']];
        foreach ($fields as $f) {
            $rules['credentials.'.$f['key']] = [$f['required'] ? 'nullable' : 'nullable', 'string', 'max:1024'];
        }
        if (in_array($provider, ['evolution_go', 'waha', 'evolution_api'], true)) {
            $rules['credentials.base_url'][] = PublicHttpUrl::rule();
        }
        if ($provider === 'firecrawl' && filled(data_get($request->all(), 'credentials.base_url'))) {
            $rules['credentials.base_url'][] = PublicHttpUrl::rule();
        }

        $validated = $request->validate($rules);

        $config = IntegrationConfig::firstOrNew(['provider' => $provider, 'mode' => $validated['mode']]);

        // Merge credentials: skip masked values (••••xxxx) to preserve existing
        $existing = $config->credentials ?? [];
        $incoming = $validated['credentials'] ?? [];
        $merged = $existing;
        $changedKeys = [];

        foreach ($incoming as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (preg_match('/^•+/', (string) $v)) {
                continue; // keep existing
            }
            $merged[$k] = $v;
            $changedKeys[] = $k;
        }

        // Zernio asks the webhook owner to choose the HMAC secret; it is not
        // supplied on the API Keys screen. Generate it once on our server and
        // keep it encrypted together with the central API credentials.
        if ($provider === 'zernio' && empty($merged['webhook_secret'])) {
            $merged['webhook_secret'] = Str::random(64);
            $changedKeys[] = 'webhook_secret_generated';
        }

        $wasEnabled = $config->enabled ?? false;
        $config->fill([
            'label' => IntegrationConfig::LABELS[$provider] ?? $provider,
            'enabled' => (bool) $validated['enabled'],
            'mode' => $validated['mode'],
            'credentials' => $merged,
            'updated_by_admin_id' => auth('admin')->id(),
        ])->save();

        $this->auditLog($request, $config, $config->wasRecentlyCreated ? 'create' : 'update', $changedKeys);
        if ($wasEnabled !== $config->enabled) {
            $this->auditLog($request, $config, $config->enabled ? 'enable' : 'disable', []);
        }

        if (str_starts_with($provider, 'storage_')) {
            app(StorageManager::class)->clearCache();
        }

        return back()->with('success', 'Integração salva.');
    }

    public function test(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return response()->json(['ok' => false, 'message' => 'Integração ainda não configurada.']);
        }

        $result = app(ConnectionTester::class)->test($config);
        $this->auditLog($request, $config, 'test', []);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function toggle(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return back()->with('error', 'Configure as credenciais antes de ativar.');
        }

        $updates = ['enabled' => ! $config->enabled];
        // If disabling a storage that was the default, clear its default flag
        if ($config->enabled && ($config->is_default ?? false) && str_starts_with($provider, 'storage_')) {
            $updates['is_default'] = false;
        }
        $config->update($updates);
        $this->auditLog($request, $config, $config->enabled ? 'enable' : 'disable', []);

        if (str_starts_with($provider, 'storage_')) {
            app(StorageManager::class)->clearCache();
        }

        return back()->with('success', 'Integração '.($config->enabled ? 'ativada' : 'desativada').'.');
    }

    public function setDefault(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::STORAGE_PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config || ! $config->enabled) {
            return back()->with('error', 'Somente um provedor de armazenamento ativado pode ser definido como padrão.');
        }

        // Clear is_default on all other storage providers
        IntegrationConfig::whereIn('provider', IntegrationConfig::STORAGE_PROVIDERS)
            ->where('provider', '!=', $provider)
            ->update(['is_default' => false]);

        $config->update(['is_default' => true]);
        $this->auditLog($request, $config, 'update', ['is_default']);

        app(StorageManager::class)->clearCache();

        return back()->with('success', IntegrationConfig::LABELS[$provider].' definido como armazenamento padrão.');
    }

    public function rotate(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, IntegrationConfig::PROVIDERS, true), 404);

        $config = IntegrationConfig::forProvider($provider);
        if (! $config) {
            return back()->with('error', 'Integração não configurada.');
        }

        $secret = bin2hex(random_bytes(32));
        $config->update(['webhook_secret' => $secret, 'updated_by_admin_id' => auth('admin')->id()]);
        $this->auditLog($request, $config, 'rotate', ['webhook_secret']);

        return back()->with('success', 'Segredo do webhook rotacionado.');
    }

    public function auditLogIndex(Request $request): Response
    {
        $logs = IntegrationAuditLog::with('admin')
            ->latest('created_at')
            ->paginate(50);

        return Inertia::render('Admin/Integrations/AuditLog', ['logs' => $logs]);
    }

    private function auditLog(Request $request, IntegrationConfig $config, string $action, array $changedKeys): void
    {
        IntegrationAuditLog::create([
            'admin_user_id' => auth('admin')->id(),
            'integration_config_id' => $config->id,
            'provider' => $config->provider,
            'action' => $action,
            'diff_json' => $changedKeys,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 512),
        ]);
    }
}
