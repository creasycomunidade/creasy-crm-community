<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\ZernioApiException;
use App\Modules\Integrations\Services\ZernioClient;
use App\Modules\Integrations\Services\ZernioWorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZernioWorkspaceController extends Controller
{
    private const PLATFORMS = [
        'facebook', 'instagram', 'linkedin', 'twitter', 'tiktok', 'youtube', 'threads',
        'reddit', 'pinterest', 'bluesky', 'googlebusiness', 'telegram', 'snapchat',
        'discord', 'slack', 'whatsapp',
    ];

    private const AD_PLATFORMS = ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter', 'pinterest', 'googleads'];

    public function provision(Request $request, ZernioWorkspaceManager $manager): RedirectResponse
    {
        $workspace = Workspace::findOrFail($this->workspaceId($request));

        try {
            $connection = $manager->provision($workspace);
            $count = $manager->syncAccounts($connection);

            return back()->with('success', "Conexão da empresa preparada. {$count} conta(s) sincronizada(s).");
        } catch (\Throwable $exception) {
            return back()->with('error', $this->publicMessage($exception));
        }
    }

    public function sync(Request $request, ZernioWorkspaceManager $manager): RedirectResponse
    {
        try {
            $connection = $manager->connectionForWorkspace($this->workspaceId($request));
            $count = $manager->syncAccounts($connection);

            return back()->with('success', "Conexão sincronizada: {$count} conta(s) encontrada(s).");
        } catch (\Throwable $exception) {
            ZernioWorkspaceConnection::where('workspace_id', $this->workspaceId($request))->update([
                'status' => 'error', 'last_error' => $exception->getMessage(),
            ]);

            return back()->with('error', $this->publicMessage($exception));
        }
    }

    public function connect(Request $request, string $platform, ZernioWorkspaceManager $manager): RedirectResponse
    {
        abort_unless(in_array($platform, self::PLATFORMS, true), 404);

        try {
            $workspace = Workspace::findOrFail($this->workspaceId($request));
            $this->ensureConnectionCapacity($workspace);
            $connection = $manager->provision($workspace);
            $result = app(ZernioClient::class)->connectUrl(
                $platform,
                $connection->profile_id,
                route('client.integrations.zernio.callback', ['return_to' => $this->returnTo($request)]),
            );
            $url = (string) ($result['authUrl'] ?? '');
            abort_if($url === '', 502, 'O serviço de conexão não devolveu a URL de autorização.');

            return redirect()->away($url);
        } catch (\Throwable $exception) {
            return redirect()->route($this->returnRoute($this->returnTo($request)))->with('error', $this->publicMessage($exception));
        }
    }

    public function connectAds(Request $request, string $platform, ZernioWorkspaceManager $manager): RedirectResponse
    {
        abort_unless(in_array($platform, self::AD_PLATFORMS, true), 404);

        try {
            $workspace = Workspace::findOrFail($this->workspaceId($request));
            $this->ensureConnectionCapacity($workspace);
            $connection = $manager->provision($workspace);
            $result = app(ZernioClient::class)->connectUrl(
                $platform,
                $connection->profile_id,
                route('client.integrations.zernio.callback', ['return_to' => $this->returnTo($request)]),
                true,
            );
            $url = (string) ($result['authUrl'] ?? '');
            abort_if($url === '', 502, 'O serviço de conexão não devolveu a URL de autorização de anúncios.');

            return redirect()->away($url);
        } catch (\Throwable $exception) {
            return redirect()->route($this->returnRoute($this->returnTo($request)))->with('error', $this->publicMessage($exception));
        }
    }

    public function callback(Request $request, ZernioWorkspaceManager $manager): RedirectResponse
    {
        $returnRoute = $this->returnRoute($request->string('return_to')->value());
        if ($request->filled('error')) {
            return redirect()->route($returnRoute)->with('error', 'Não foi possível concluir a conexão: '.$this->publicMessage($request->string('error')->value()));
        }

        try {
            $connection = $manager->connectionForWorkspace($this->workspaceId($request));
            $manager->syncAccounts($connection);

            return redirect()->route($returnRoute)->with('success', 'Conta conectada à empresa e sincronizada com o CREASY CRM.');
        } catch (\Throwable $exception) {
            return redirect()->route($returnRoute)->with('error', $this->publicMessage($exception));
        }
    }

    private function returnTo(Request $request): string
    {
        $returnTo = $request->string('return_to')->value();

        return in_array($returnTo, ['channels', 'social', 'ads', 'integrations'], true) ? $returnTo : 'integrations';
    }

    private function returnRoute(string $returnTo): string
    {
        return match ($returnTo) {
            'channels' => 'client.inbox.setup',
            'social' => 'client.social.accounts.index',
            'ads' => 'client.ads.index',
            default => 'client.integrations.index',
        };
    }

    private function publicMessage(\Throwable|string $error): string
    {
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;
        if (($error instanceof ZernioApiException && $error->status === 402)
            || preg_match('/HTTP\s*402|PAYMENT_REQUIRED|free_tier_exceeded/i', $message)) {
            return 'A conta central de canais atingiu o limite gratuito e precisa de um método de pagamento ativo. Avise o administrador do CREASY CRM para regularizar a cobrança e tente novamente.';
        }

        return (string) preg_replace('/zernio/i', 'serviço de conexão', $message);
    }

    private function ensureConnectionCapacity(Workspace $workspace): void
    {
        $limit = $workspace->client?->effectivePlan()?->limitValue('unified_connections');
        if ($limit === null) {
            return;
        }

        $workspaceIds = $workspace->client?->workspaces()->pluck('id')->all() ?? [$workspace->id];
        $current = ZernioAccountMapping::whereIn('workspace_id', $workspaceIds)
            ->where('status', 'connected')
            ->count();

        if ($current >= (int) $limit) {
            throw new \RuntimeException("Limite de conexões do plano atingido ({$current}/".(int) $limit.').');
        }
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->session()->get('current_workspace_id') ?? $request->user()->workspace_id);
    }
}
