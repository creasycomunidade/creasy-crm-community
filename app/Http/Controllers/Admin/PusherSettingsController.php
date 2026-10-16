<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Pusher\Pusher;

class PusherSettingsController extends Controller
{
    private const KEYS = ['pusher_app_id', 'pusher_app_key', 'pusher_app_secret', 'pusher_app_cluster'];
    private const LABELS = [
        'pusher_app_id' => 'App ID',
        'pusher_app_key' => 'App Key',
        'pusher_app_secret' => 'App Secret',
        'pusher_app_cluster' => 'Cluster',
    ];

    public function index(): Response
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $model = SystemSetting::where('key', $key)->first();
            $resolved = $this->resolvedValue($key);
            $settings[$key] = $key === 'pusher_app_secret' && filled($resolved)
                ? '••••••••'
                : ($model?->value ?: $resolved);
        }

        $settings['pusher_enabled'] = SystemSetting::get('pusher_enabled', 'false');
        $missingFields = $this->missingFields();
        $configured = $missingFields === [];

        return Inertia::render('Admin/PusherSettings/Index', [
            'settings'   => $settings,
            'configured' => $configured,
            'missingFields' => $missingFields,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pusher_app_id'      => ['nullable', 'string', 'max:64'],
            'pusher_app_key'     => ['nullable', 'string', 'max:128'],
            'pusher_app_secret'  => ['nullable', 'string', 'max:255'],
            'pusher_app_cluster' => ['nullable', 'string', 'max:32'],
            'pusher_enabled'     => ['nullable', 'string', 'in:true,false'],
        ]);

        $secrets = ['pusher_app_secret'];
        if (($validated['pusher_enabled'] ?? 'false') === 'true') {
            $candidate = [];
            foreach (self::KEYS as $key) {
                $submitted = $validated[$key] ?? null;
                $isMask = $key === 'pusher_app_secret' && preg_match('/^•+$/u', (string) $submitted);
                $candidate[$key] = filled($submitted) && ! $isMask
                    ? trim((string) $submitted)
                    : $this->resolvedValue($key);
            }

            $missing = array_keys(array_filter($candidate, fn ($value) => blank($value)));
            if ($missing !== []) {
                return back()->withErrors([
                    'pusher_enabled' => 'Preencha antes de ativar: '
                        .implode(', ', array_map(fn ($key) => self::LABELS[$key], $missing)).'.',
                ])->withInput();
            }
        }

        foreach ($validated as $key => $value) {
            if ($value === null) {
                continue;
            }
            $isSecret = in_array($key, $secrets, true);
            // Skip masked placeholder — keep existing secret
            if ($isSecret && preg_match('/^•+$/', (string) $value)) {
                continue;
            }
            SystemSetting::set($key, $value, $isSecret, 'pusher');
        }

        return back()->with('success', 'Configurações do Pusher salvas.');
    }

    public function test(Request $request)
    {
        $key     = SystemSetting::get('pusher_app_key');
        $secret  = SystemSetting::get('pusher_app_secret');
        $appId   = SystemSetting::get('pusher_app_id');
        $cluster = SystemSetting::get('pusher_app_cluster', 'mt1');

        if (! $key || ! $secret || ! $appId) {
            return response()->json(['success' => false, 'message' => 'As credenciais do Pusher ainda não estão completas.'], 422);
        }

        try {
            $pusher = new Pusher($key, $secret, $appId, [
                'cluster' => $cluster,
                'useTLS'  => true,
            ]);

            // Trigger a test event on a private channel to verify credentials
            $pusher->trigger('test-channel', 'test-event', ['message' => 'Connection test']);

            return response()->json(['success' => true, 'message' => 'Conexão com o Pusher realizada com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Falha na conexão: '.$e->getMessage()], 422);
        }
    }

    private function missingFields(): array
    {
        return array_values(array_map(
            fn ($key) => self::LABELS[$key],
            array_filter(self::KEYS, fn ($key) => blank($this->resolvedValue($key))),
        ));
    }

    private function resolvedValue(string $key): ?string
    {
        $envKey = match ($key) {
            'pusher_app_id' => 'PUSHER_APP_ID',
            'pusher_app_key' => 'PUSHER_APP_KEY',
            'pusher_app_secret' => 'PUSHER_APP_SECRET',
            'pusher_app_cluster' => 'PUSHER_APP_CLUSTER',
        };

        return SystemSetting::get($key) ?: env($envKey);
    }
}
