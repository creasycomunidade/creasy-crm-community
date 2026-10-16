<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Services\WhatsappGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WhatsappGatewaySetupController extends Controller
{
    public function store(Request $request)
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $this->authorise($request, $workspaceId);
        $data = $request->validate([
            'provider' => ['required', Rule::in(WhatsappGatewayClient::PROVIDERS)],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $config = IntegrationConfig::forProvider($data['provider']);
        abort_unless($config?->enabled && $config->isConfigured(), 422, 'Configure e habilite o gateway em Integrações administrativas.');

        return Cache::lock('whatsapp-gateway-create:'.$workspaceId, 60)->block(5, function () use ($request, $workspaceId, $data, $config) {
            $limit = $request->user()->effectiveSubscription()?->plan?->limitValue('whatsapp_accounts');
            abort_if($limit !== null && ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'whatsapp')->count() >= (int) $limit,
                422, 'O limite de contas WhatsApp do plano foi atingido.');
            $instance = 'creasy-crm-w'.$workspaceId.'-'.Str::lower(Str::random(12));
            $baseUrl = rtrim($config->credentials['base_url'], '/');
            $externalId = hash('sha256', $baseUrl).':'.$instance;
            abort_if(ChannelAccount::where('provider', $data['provider'])->where('external_account_id', $externalId)->exists(),
                422, 'Esta sessão já está vinculada. Tente criar uma nova conexão.');
            $token = bin2hex(random_bytes(32));
            $account = ChannelAccount::create([
                'workspace_id' => $workspaceId, 'channel' => 'whatsapp', 'provider' => $data['provider'],
                'display_name' => $data['name'], 'external_account_id' => $externalId,
                'webhook_token_hash' => hash('sha256', $token), 'status' => 'inactive',
                'credentials' => ['base_url' => $baseUrl, 'api_key' => $config->credentials['api_key'], 'instance' => $instance],
                'meta_json' => ['instance_name' => $instance, 'connection_status' => 'creating'],
            ]);
            try {
                WhatsappGatewayClient::forAccount($account)->create(route('webhooks.whatsapp-gateway.receive', ['token' => $token]));
                $account->update(['meta_json' => ['instance_name' => $instance, 'connection_status' => 'connecting']]);
            } catch (\Throwable $e) {
                // Keep the reservation: a timed-out request may have created the remote instance.
                $account->update(['status' => 'error']);

                return back()->with('error', 'Não foi possível confirmar a criação. Atualize o status antes de tentar novamente.');
            }

            return back()->with('success', 'Conexão criada. Abra o QR Code para vincular o WhatsApp.');
        });
    }

    public function status(Request $request, ChannelAccount $channelAccount)
    {
        $this->authoriseAccount($request, $channelAccount);
        try {
            $state = WhatsappGatewayClient::forAccount($channelAccount)->status();
            $active = in_array($state, ['WORKING', 'open'], true);
            $channelAccount->update(['status' => $active ? 'active' : 'inactive',
                'meta_json' => array_merge($channelAccount->meta_json ?? [], ['connection_status' => $state])]);

            return response()->json(['status' => $channelAccount->status, 'connection_status' => $state]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Não foi possível consultar o gateway. Verifique a configuração e tente novamente.'], 502);
        }
    }

    public function qr(Request $request, ChannelAccount $channelAccount)
    {
        $this->authoriseAccount($request, $channelAccount);
        try {
            $qr = WhatsappGatewayClient::forAccount($channelAccount)->qr();

            return response()->json(['qr' => $qr, 'message' => $qr ? null : 'QR Code indisponível. Atualize o status ou reconecte.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'QR Code indisponível. Atualize o status ou reconecte.'], 502);
        }
    }

    public function reconnect(Request $request, ChannelAccount $channelAccount)
    {
        $this->authoriseAccount($request, $channelAccount);
        try {
            WhatsappGatewayClient::forAccount($channelAccount)->restart();
            $channelAccount->update(['status' => 'inactive']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível reiniciar a conexão. Verifique o gateway.');
        }

        return back()->with('success', 'Reconexão iniciada. Atualize o status ou abra o QR Code.');
    }

    public function destroy(Request $request, ChannelAccount $channelAccount)
    {
        $this->authoriseAccount($request, $channelAccount);
        try {
            WhatsappGatewayClient::forAccount($channelAccount)->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'O gateway não confirmou a remoção. A conta foi preservada para tentar novamente.');
        }
        $channelAccount->delete();

        return back()->with('success', 'Conexão removida.');
    }

    private function authorise(Request $request, int $workspaceId): void
    {
        $workspace = $request->user()->accessibleWorkspaces()->firstWhere('id', $workspaceId);
        abort_unless($workspace && $workspace->canManageMembers($request->user()), 403);
    }

    private function authoriseAccount(Request $request, ChannelAccount $account): void
    {
        abort_unless($account->channel === 'whatsapp' && in_array($account->provider, WhatsappGatewayClient::PROVIDERS, true), 404);
        $this->authorise($request, (int) $account->workspace_id);
    }
}
