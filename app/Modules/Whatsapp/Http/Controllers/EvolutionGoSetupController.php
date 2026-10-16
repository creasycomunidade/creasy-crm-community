<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Services\EvolutionGoClient;
use App\Modules\Whatsapp\Services\EvolutionGoConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EvolutionGoSetupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'risk_acknowledged' => ['accepted'],
        ], [
            'risk_acknowledged.accepted' => 'Confirme que você entende o risco de bloqueio do número ao usar uma API não oficial.',
        ]);

        [$workspaceId] = $this->authoriseWorkspaceAdmin($request);
        $system = IntegrationConfig::forProvider('evolution_go');
        $systemCredentials = $system?->credentials ?? [];
        if (! $system?->enabled || empty($systemCredentials['base_url']) || empty($systemCredentials['global_api_key'])) {
            return back()->with('error', 'A conexão de WhatsApp por QR Code ainda não foi configurada pelo administrador.');
        }

        $limit = $request->user()->effectiveSubscription()?->plan?->limitValue('whatsapp_accounts');
        if ($limit !== null) {
            $current = ChannelAccount::where('workspace_id', $workspaceId)
                ->where('channel', 'whatsapp')
                ->count();
            if ($current >= (int) $limit) {
                return back()->with('error', 'O limite de contas do WhatsApp do seu plano foi atingido.');
            }
        }

        $baseUrl = (string) $systemCredentials['base_url'];
        $globalClient = new EvolutionGoClient($baseUrl, (string) $systemCredentials['global_api_key']);
        $instanceName = 'creasy-crm-w'.$workspaceId.'-'.Str::lower(Str::random(8));
        $instanceToken = Str::random(64);
        $webhookToken = bin2hex(random_bytes(32));

        try {
            $created = $globalClient->createInstance($instanceName, $instanceToken);
            $instanceId = (string) (
                data_get($created, 'data.instanceId')
                ?? data_get($created, 'data.id')
                ?? data_get($created, 'instanceId')
                ?? ''
            );
            if ($instanceId === '') {
                throw new \RuntimeException('O serviço de conexão não retornou o identificador da instância.');
            }

            $externalId = hash('sha256', rtrim($baseUrl, '/')).':'.$instanceId;
            $account = DB::transaction(function () use (
                $workspaceId,
                $validated,
                $instanceName,
                $instanceId,
                $instanceToken,
                $externalId,
                $webhookToken,
            ): ChannelAccount {
                return ChannelAccount::create([
                    'workspace_id' => $workspaceId,
                    'channel' => 'whatsapp',
                    'provider' => 'evolution_go',
                    'external_account_id' => $externalId,
                    'webhook_token_hash' => hash('sha256', $webhookToken),
                    'display_name' => mb_substr($validated['name'], 0, 128),
                    'credentials' => [
                        'instance_token' => $instanceToken,
                        'instance_id' => $instanceId,
                    ],
                    'meta_json' => [
                        'instance_name' => $instanceName,
                        'connection_status' => 'created',
                        'risk_acknowledged_at' => now()->toIso8601String(),
                        'connection_options' => $this->defaultConnectionOptions(),
                        'advanced_settings' => $this->defaultAdvancedSettings(),
                    ],
                    'status' => 'inactive',
                ]);
            });

            try {
                $instanceClient = EvolutionGoClient::forAccount($account);
                $instanceClient->connect(
                    route('webhooks.evolution-go.receive', ['token' => $webhookToken]),
                    $this->connectionOptions($account),
                );
                $account->update([
                    'meta_json' => array_merge($account->meta_json ?? [], ['connection_status' => 'connecting']),
                ]);
            } catch (\Throwable $e) {
                $account->update([
                    'status' => 'error',
                    'meta_json' => array_merge($account->meta_json ?? [], [
                        'connection_status' => 'error',
                        'last_error' => mb_substr($e->getMessage(), 0, 500),
                    ]),
                ]);

                return back()->with('error', 'A instância foi criada, mas não iniciou a conexão: '.$e->getMessage());
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Não foi possível criar a conexão de WhatsApp: '.$e->getMessage());
        }

        return back()->with('success', 'Conexão criada. Leia o QR Code para conectar o WhatsApp.');
    }

    public function status(Request $request, ChannelAccount $channelAccount): JsonResponse
    {
        $this->authoriseAccount($request, $channelAccount);

        try {
            $response = EvolutionGoClient::forAccount($channelAccount)->status();
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $socketConnected = $data['Connected'] ?? $data['connected'] ?? null;
            $loggedIn = $data['LoggedIn'] ?? $data['loggedIn'] ?? null;
            $connected = $loggedIn !== null
                ? filter_var($loggedIn, FILTER_VALIDATE_BOOL)
                : ($socketConnected !== null
                    ? filter_var($socketConnected, FILTER_VALIDATE_BOOL)
                    : (($data['status'] ?? null) === 'open'));
            $status = $connected ? 'open' : (string) ($data['status'] ?? 'connecting');
            $channelAccount->update([
                'status' => $connected ? 'active' : 'inactive',
                'meta_json' => array_merge($channelAccount->meta_json ?? [], [
                    'connection_status' => $status,
                    'profile_name' => $data['Name'] ?? $data['name'] ?? null,
                    'last_checked_at' => now()->toIso8601String(),
                ]),
            ]);

            return response()->json(['connected' => $connected, 'status' => $status]);
        } catch (\Throwable $e) {
            $channelAccount->update([
                'status' => 'error',
                'meta_json' => array_merge($channelAccount->meta_json ?? [], [
                    'connection_status' => 'error',
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                ]),
            ]);

            return response()->json(['connected' => false, 'status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function qr(Request $request, ChannelAccount $channelAccount): JsonResponse
    {
        $this->authoriseAccount($request, $channelAccount);

        try {
            $response = EvolutionGoClient::forAccount($channelAccount)->qrCode();
            // Releases of Evolution GO have returned this payload both at the
            // response root and below `data`. They have also alternated
            // between a complete data URI and bare image base64.
            $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
            $qrCandidates = [
                $data['qrcode'] ?? null,
                $data['Qrcode'] ?? null,
                $data['qrCode'] ?? null,
                $data['base64'] ?? null,
                $data['code'] ?? null,
                $data['Code'] ?? null,
            ];
            $qrImage = null;
            foreach ($qrCandidates as $candidate) {
                $qrImage = $this->normaliseQrImage($candidate);
                if ($qrImage !== null) {
                    break;
                }
            }

            $code = $data['passkeyCode']
                ?? $data['pairingCode']
                ?? $data['pairing_code']
                ?? $data['code']
                ?? $data['Code']
                ?? null;
            if ($this->normaliseQrImage($code) !== null) {
                $code = null;
            }

            return response()->json([
                'qrcode' => $qrImage,
                'code' => is_scalar($code) ? (string) $code : null,
                'passkey_stage' => $data['passkeyStage'] ?? $data['passkey_stage'] ?? null,
                'passkey_open_url' => $data['passkeyOpenUrl'] ?? $data['passkey_open_url'] ?? null,
                'passkey_code' => $data['passkeyCode'] ?? $data['passkey_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reconnect(Request $request, ChannelAccount $channelAccount): RedirectResponse|JsonResponse
    {
        $this->authoriseAccount($request, $channelAccount);
        $webhookToken = bin2hex(random_bytes(32));

        try {
            $channelAccount = $this->replaceGatewayInstance($channelAccount);
            $client = EvolutionGoClient::forAccount($channelAccount);
            $client->connect(
                route('webhooks.evolution-go.receive', ['token' => $webhookToken]),
                $this->connectionOptions($channelAccount),
            );
            $channelAccount->update([
                'webhook_token_hash' => hash('sha256', $webhookToken),
                'status' => 'inactive',
                'meta_json' => array_merge($channelAccount->meta_json ?? [], [
                    'connection_status' => 'connecting',
                    'last_error' => null,
                ]),
            ]);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível gerar um novo QR Code. O serviço de conexão está indisponível no momento. Tente novamente em alguns instantes.',
                ], 422);
            }

            return back()->with('error', 'Não foi possível reconectar: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Novo QR Code solicitado. Leia o código para reconectar o WhatsApp.',
            ]);
        }

        return back()->with('success', 'Reconexão iniciada. Leia o novo QR Code.');
    }

    /**
     * Evolution GO can retain a disconnected runtime pointer that makes
     * /connect return success without ever starting a QR cycle. Replacing only
     * the hidden gateway instance clears that state while preserving the
     * CREASY CRM channel, conversations and messages.
     */
    private function replaceGatewayInstance(ChannelAccount $account): ChannelAccount
    {
        $system = IntegrationConfig::forProvider('evolution_go');
        $systemCredentials = $system?->credentials ?? [];
        $baseUrl = (string) ($systemCredentials['base_url'] ?? '');
        $globalApiKey = (string) ($systemCredentials['global_api_key'] ?? '');
        $accountCredentials = $account->credentials ?? [];
        $oldInstanceId = (string) ($accountCredentials['instance_id'] ?? '');

        if (! $system?->enabled || $baseUrl === '' || $globalApiKey === '') {
            throw new \RuntimeException('A configuração interna da conexão de WhatsApp está incompleta.');
        }

        $globalClient = new EvolutionGoClient($baseUrl, $globalApiKey);
        if ($oldInstanceId !== '') {
            try {
                $globalClient->deleteInstance($oldInstanceId);
            } catch (\Throwable $exception) {
                $missingInstance = $exception instanceof EvolutionGoConnectionException
                    && $exception->reason === 'disconnected';
                if (! $missingInstance && ! str_contains(Str::lower($exception->getMessage()), 'not found')) {
                    throw $exception;
                }
            }
        }

        $instanceName = 'creasy-crm-w'.$account->workspace_id.'-'.Str::lower(Str::random(8));
        $instanceToken = Str::random(64);
        $created = $globalClient->createInstance($instanceName, $instanceToken);
        $instanceId = (string) (
            data_get($created, 'data.instanceId')
            ?? data_get($created, 'data.id')
            ?? data_get($created, 'instanceId')
            ?? ''
        );
        if ($instanceId === '') {
            throw new \RuntimeException('O serviço de conexão não retornou o identificador da nova instância.');
        }

        $account->update([
            'external_account_id' => hash('sha256', rtrim($baseUrl, '/')).':'.$instanceId,
            'credentials' => array_merge($accountCredentials, [
                'instance_token' => $instanceToken,
                'instance_id' => $instanceId,
            ]),
            'meta_json' => array_merge($account->meta_json ?? [], ['instance_name' => $instanceName]),
        ]);

        return $account->fresh();
    }

    public function updateSettings(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $this->authoriseAccount($request, $channelAccount);
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(EvolutionGoClient::WEBHOOK_EVENTS)],
            'rabbitmq' => ['required', Rule::in(['enabled', 'disabled'])],
            'websocket' => ['required', Rule::in(['enabled', 'disabled'])],
            'nats' => ['required', Rule::in(['enabled', 'disabled'])],
            'always_online' => ['required', 'boolean'],
            'reject_call' => ['required', 'boolean'],
            'reject_call_message' => ['nullable', 'string', 'max:250'],
            'read_messages' => ['required', 'boolean'],
            'ignore_status' => ['required', 'boolean'],
        ]);

        $connectionOptions = [
            'subscribe' => array_values(array_unique($validated['events'])),
            'rabbitmq' => $validated['rabbitmq'],
            'websocket' => $validated['websocket'],
            'nats' => $validated['nats'],
        ];
        $advancedSettings = [
            'always_online' => $validated['always_online'],
            'reject_call' => $validated['reject_call'],
            'reject_call_message' => $validated['reject_call_message'] ?? '',
            'read_messages' => $validated['read_messages'],
            'ignore_groups' => true,
            'ignore_status' => $validated['ignore_status'],
        ];
        $instanceId = (string) (($channelAccount->credentials ?? [])['instance_id'] ?? '');
        abort_if($instanceId === '', 422, 'A instância não possui um identificador válido.');

        $webhookToken = bin2hex(random_bytes(32));
        $client = EvolutionGoClient::forAccount($channelAccount);

        try {
            $client->connect(
                route('webhooks.evolution-go.receive', ['token' => $webhookToken]),
                $connectionOptions,
            );

            // O endpoint de conexão já passou a usar o novo token. Persista-o
            // antes da configuração avançada para que nenhum webhook seja perdido.
            $channelAccount->update([
                'webhook_token_hash' => hash('sha256', $webhookToken),
                'meta_json' => array_merge($channelAccount->meta_json ?? [], [
                    'connection_options' => $connectionOptions,
                    'settings_updated_at' => now()->toIso8601String(),
                ]),
            ]);

            $client->updateAdvancedSettings($instanceId, $advancedSettings);
            $channelAccount->update([
                'meta_json' => array_merge($channelAccount->fresh()->meta_json ?? [], [
                    'advanced_settings' => $advancedSettings,
                ]),
            ]);
        } catch (\Throwable $exception) {
            return back()->with('error', 'Não foi possível salvar todas as opções da conexão: '.$exception->getMessage());
        }

        return back()->with('success', 'Opções da conexão não oficial atualizadas.');
    }

    public function destroy(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $this->authoriseAccount($request, $channelAccount);
        $instanceId = (string) (($channelAccount->credentials ?? [])['instance_id'] ?? '');

        try {
            try {
                EvolutionGoClient::forAccount($channelAccount)->disconnect();
            } catch (\Throwable) {
                // The instance may already be disconnected. Deletion below is authoritative.
            }

            $system = EvolutionGoClient::system();
            if ($system && $instanceId !== '') {
                $system->deleteInstance($instanceId);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'O serviço de WhatsApp recusou a exclusão da conexão: '.$e->getMessage());
        }

        $channelAccount->delete();

        return back()->with('success', 'Conexão de WhatsApp desconectada e removida.');
    }

    /** @return array{0:int,1:\App\Models\Workspace} */
    private function authoriseWorkspaceAdmin(Request $request): array
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $workspace = $request->user()->accessibleWorkspaces()->firstWhere('id', $workspaceId);
        abort_unless($workspace && $workspace->canManageMembers($request->user()), 403);

        return [$workspaceId, $workspace];
    }

    private function authoriseAccount(Request $request, ChannelAccount $account): void
    {
        abort_unless($account->channel === 'whatsapp' && $account->provider === 'evolution_go', 404);
        $workspace = $request->user()->accessibleWorkspaces()->firstWhere('id', $account->workspace_id);
        abort_unless($workspace && $workspace->canManageMembers($request->user()), 403);
    }

    /** @return array<string, mixed> */
    private function connectionOptions(ChannelAccount $account): array
    {
        return array_merge(
            $this->defaultConnectionOptions(),
            is_array(($account->meta_json ?? [])['connection_options'] ?? null)
                ? $account->meta_json['connection_options']
                : [],
        );
    }

    /** @return array<string, mixed> */
    private function defaultConnectionOptions(): array
    {
        return [
            'subscribe' => ['MESSAGE', 'READ_RECEIPT', 'CONNECTION', 'QRCODE'],
            'rabbitmq' => 'disabled',
            'websocket' => 'disabled',
            'nats' => 'disabled',
        ];
    }

    /** @return array<string, mixed> */
    private function defaultAdvancedSettings(): array
    {
        return [
            'always_online' => false,
            'reject_call' => false,
            'reject_call_message' => '',
            'read_messages' => false,
            'ignore_groups' => true,
            'ignore_status' => false,
        ];
    }

    private function normaliseQrImage(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return null;
        }

        $mime = match (true) {
            str_starts_with($decoded, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($decoded, "\xff\xd8\xff") => 'image/jpeg',
            str_starts_with($decoded, 'GIF87a'), str_starts_with($decoded, 'GIF89a') => 'image/gif',
            str_starts_with($decoded, 'RIFF') && substr($decoded, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };

        return $mime ? "data:{$mime};base64,{$value}" : null;
    }
}
