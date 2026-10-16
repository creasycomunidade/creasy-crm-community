<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ads\Models\AdConnection;
use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\WorkspaceConnectionTester;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Social\Models\SocialAccount;
use App\Support\PublicHttpUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $social = SocialAccount::where('workspace_id', $wid)->where('active', true)->pluck('network')->unique()->values();
        $channels = ChannelAccount::where('workspace_id', $wid)
            ->get(['channel', 'provider', 'status'])
            ->groupBy('channel')
            ->map(fn ($items) => [
                'connected' => $items->whereIn('status', ['active', 'connected'])->count(),
                'total' => $items->count(),
                'providers' => $items->pluck('provider')->filter()->unique()->values(),
            ]);
        $ads = AdConnection::where('workspace_id', $wid)
            ->get(['provider', 'status'])
            ->groupBy('provider')
            ->map(fn ($items) => [
                'connected' => $items->where('status', 'connected')->count(),
                'total' => $items->count(),
            ]);
        $stores = EcommerceStore::where('workspace_id', $wid)
            ->get(['platform', 'status'])
            ->groupBy('platform')
            ->map(fn ($items) => [
                'connected' => $items->whereIn('status', ['active', 'connected'])->count(),
                'total' => $items->count(),
            ]);
        $googleDrive = WorkspaceIntegration::where('workspace_id', $wid)
            ->where('provider', 'google_drive')
            ->first();
        $zernio = ZernioWorkspaceConnection::with(['accounts' => fn ($query) => $query->orderBy('platform')->orderBy('display_name')])
            ->where('workspace_id', $wid)
            ->first();
        $zernioConfig = IntegrationConfig::forProvider('zernio');

        return Inertia::render('Integrations/Index', [
            'connections' => WorkspaceIntegration::where('workspace_id', $wid)->latest()->get(),
            'available' => [
                'instagram' => $social->contains('instagram'),
                'linkedin' => $social->contains('linkedin'),
                'google_workspace' => IntegrationConfig::forProvider('google_workspace')?->enabled === true,
                'google_drive_connected' => $googleDrive?->status === 'connected',
                'google_drive_email' => $googleDrive?->settings['email'] ?? null,
            ],
            'channelStatus' => $channels,
            'adStatus' => $ads,
            'storeStatus' => $stores,
            'zernio' => [
                'available' => $zernioConfig?->enabled === true && $zernioConfig?->isConfigured(),
                'connected' => (bool) $zernio,
                'profile_name' => $zernio?->profile_name,
                'status' => $zernio?->status,
                'last_synced_at' => $zernio?->last_synced_at?->toISOString(),
                'last_error' => $zernio?->last_error,
                'accounts' => $zernio?->accounts->map(fn ($account) => [
                    'id' => $account->account_id,
                    'platform' => $account->platform,
                    'username' => $account->username,
                    'display_name' => $account->display_name,
                    'status' => $account->status,
                    'capabilities' => $account->capabilities,
                ])->values() ?? [],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(\App\Models\Workspace::find($this->workspaceId($request))?->canManageMembers($request->user()), 403);
        $data = $request->validate([
            'provider' => ['required', 'in:asaas,bling,omie,wordpress,fazos,stripe,todoist,discord,telegram,fathom'],
            'name' => ['required', 'string', 'max:160'],
            'credentials' => ['required', 'array'],
            'credentials.api_key' => ['nullable', 'string', 'max:4096'],
            'credentials.access_token' => ['nullable', 'string', 'max:4096'],
            'credentials.app_key' => ['nullable', 'string', 'max:4096'],
            'credentials.app_secret' => ['nullable', 'string', 'max:4096'],
            'credentials.url' => ['nullable', 'url', 'max:2048', PublicHttpUrl::rule()],
            'credentials.username' => ['nullable', 'string', 'max:255'],
            'credentials.application_password' => ['nullable', 'string', 'max:4096'],
            'credentials.token' => ['nullable', 'string', 'max:8192'],
            'credentials.environment' => ['nullable', 'in:production,sandbox'],
        ]);
        $required = [
            'fathom' => ['api_key'], 'stripe' => ['api_key'], 'todoist' => ['api_key'], 'discord' => ['api_key'], 'telegram' => ['api_key'],
            'asaas' => ['api_key'],
            'bling' => ['access_token'],
            'omie' => ['app_key', 'app_secret'],
            'wordpress' => ['url', 'username', 'application_password'],
            'fazos' => ['url', 'token'],
        ][$data['provider']];
        foreach ($required as $key) {
            abort_if(empty($data['credentials'][$key]), 422, "Informe o campo {$key}.");
        }
        WorkspaceIntegration::updateOrCreate(
            ['workspace_id' => $this->workspaceId($request), 'provider' => $data['provider'], 'name' => $data['name']],
            ['credentials' => $data['credentials'], 'status' => 'pending', 'last_error' => null],
        );

        return back()->with('success', 'Integração salva com credenciais criptografadas.');
    }

    public function test(Request $request, WorkspaceIntegration $connection, WorkspaceConnectionTester $tester): RedirectResponse
    {
        abort_unless(\App\Models\Workspace::find($this->workspaceId($request))?->canManageMembers($request->user()), 403);
        abort_unless($connection->workspace_id === $this->workspaceId($request), 403);
        $result = $tester->test($connection);
        $connection->update([
            'status' => $result['ok'] ? 'connected' : 'error',
            'last_tested_at' => now(),
            'last_error' => $result['ok'] ? null : $result['message'],
        ]);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, WorkspaceIntegration $connection): RedirectResponse
    {
        abort_unless(\App\Models\Workspace::find($this->workspaceId($request))?->canManageMembers($request->user()), 403);
        abort_unless($connection->workspace_id === $this->workspaceId($request), 403);
        $connection->delete();

        return back()->with('success', 'Integração removida.');
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
