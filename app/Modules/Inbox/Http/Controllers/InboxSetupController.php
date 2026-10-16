<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InboxSetupController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authoriseChannelManagement($request);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        // WhatsApp WABAs
        $wabas = WhatsappBusinessAccount::where('workspace_id', $workspaceId)
            ->with('phoneNumbers')
            ->get();

        $whatsappChannelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->whereNotNull('phone_number_id')
            ->get(['id', 'phone_number_id', 'display_name', 'status', 'meta_json', 'business_account_id']);

        $webhookTokensByWaba = [];
        $channelAccountPhoneIdsByWaba = [];
        $channelAccountsByWaba = [];
        foreach ($wabas as $waba) {
            $webhookTokensByWaba[$waba->id] = $waba->makeVisible('webhook_verify_token')->webhook_verify_token;
            $accounts = $whatsappChannelAccounts->where('business_account_id', $waba->waba_id)->values();
            $channelAccountPhoneIdsByWaba[$waba->id] = $accounts->pluck('phone_number_id')->all();
            $channelAccountsByWaba[$waba->id] = $accounts->map(fn ($a) => [
                'id' => $a->id,
                'phone_number_id' => $a->phone_number_id,
                'display_name' => $a->display_name,
                'status' => $a->status,
                'ai_chatbot_id' => $a->meta_json['ai_chatbot_id'] ?? null,
                'meta_json' => $a->meta_json,
            ])->all();
        }

        // Instagram / Messenger
        $instagramAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'instagram')
            ->get(['id', 'display_name', 'status', 'meta_json', 'created_at'])
            ->map(fn ($a) => array_merge($a->toArray(), ['ai_chatbot_id' => $a->meta_json['ai_chatbot_id'] ?? null]));

        $messengerAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'messenger')
            ->get(['id', 'display_name', 'status', 'meta_json', 'created_at'])
            ->map(fn ($a) => array_merge($a->toArray(), ['ai_chatbot_id' => $a->meta_json['ai_chatbot_id'] ?? null]));

        $telegramAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'telegram')
            ->get(['id', 'display_name', 'status', 'meta_json', 'created_at'])
            ->map(fn ($a) => array_merge($a->toArray(), ['ai_chatbot_id' => $a->meta_json['ai_chatbot_id'] ?? null]));

        $evolutionGoAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('provider', 'evolution_go')
            ->get(['id', 'display_name', 'status', 'meta_json', 'created_at'])
            ->map(fn ($a) => array_merge($a->toArray(), [
                'ai_chatbot_id' => $a->meta_json['ai_chatbot_id'] ?? null,
                'connection_status' => $a->meta_json['connection_status'] ?? $a->status,
                'connection_options' => $a->meta_json['connection_options'] ?? [
                    'subscribe' => ['MESSAGE', 'READ_RECEIPT', 'CONNECTION', 'QRCODE'],
                    'rabbitmq' => 'disabled',
                    'websocket' => 'disabled',
                    'nats' => 'disabled',
                ],
                'advanced_settings' => $a->meta_json['advanced_settings'] ?? [
                    'always_online' => false,
                    'reject_call' => false,
                    'reject_call_message' => '',
                    'read_messages' => false,
                    'ignore_groups' => true,
                    'ignore_status' => false,
                ],
            ]));

        $evolutionGoConfig = IntegrationConfig::forProvider('evolution_go');
        $zernioConfig = null;
        $zernioConnection = null;

        $chatbots = AiChatbot::where('workspace_id', $workspaceId)->where('enabled', true)->get(['id', 'name']);

        $meta = CredentialResolver::system()->meta();
        $metaWebhookUrl = $meta ? url('/webhooks/meta/'.$meta->verifyToken()) : null;

        $metaCreds = CredentialResolver::system()->meta();

        return Inertia::render('Inbox/Setup', [
            'wabas' => $wabas,
            'whatsappWebhookUrl' => url('/webhooks/whatsapp'),
            'whatsappWebhookGlobalUrl' => route('webhooks.whatsapp.global.receive'),
            'webhookTokensByWaba' => $webhookTokensByWaba,
            'channelAccountPhoneIdsByWaba' => $channelAccountPhoneIdsByWaba,
            'channelAccountsByWaba' => $channelAccountsByWaba,
            'instagramAccounts' => $instagramAccounts,
            'messengerAccounts' => $messengerAccounts,
            'telegramAccounts' => $telegramAccounts,
            'gatewayAccounts' => ChannelAccount::where('workspace_id', $workspaceId)->where('channel', 'whatsapp')->whereIn('provider', ['waha', 'evolution_api'])->get(['id', 'provider', 'display_name', 'status', 'meta_json']),
            'gatewayConfigured' => collect(['waha', 'evolution_api'])->mapWithKeys(function ($provider) {
                $config = IntegrationConfig::forProvider($provider);
                return [$provider => $config?->enabled === true && $config->isConfigured()];
            }),
            'evolutionGoAccounts' => $evolutionGoAccounts,
            'evolutionGoConfigured' => $evolutionGoConfig?->enabled === true
                && $evolutionGoConfig->isConfigured(),
            'zernio' => [
                'available' => $zernioConfig?->enabled === true && $zernioConfig->isConfigured(),
                'connected' => (bool) $zernioConnection,
                'profile_name' => $zernioConnection?->profile_name,
                'status' => $zernioConnection?->status,
                'last_synced_at' => $zernioConnection?->last_synced_at?->toISOString(),
                'last_error' => $zernioConnection?->last_error,
                'accounts' => $zernioConnection?->accounts
                    ->filter(fn ($account) => (bool) data_get($account->capabilities, 'messages'))
                    ->map(fn ($account) => [
                        'id' => $account->account_id,
                        'platform' => $account->platform,
                        'display_name' => $account->display_name,
                        'username' => $account->username,
                        'status' => $account->status,
                    ])->values() ?? [],
            ],
            'chatbots' => $chatbots,
            'humanAgents' => User::forWorkspace((int) $workspaceId)
                ->where('status', User::STATUS_ACTIVE)
                ->where('client_role', User::CLIENT_ROLE_STAFF)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'client_role']),
            'metaWebhookUrl' => $metaWebhookUrl,
            'metaAppId' => $metaCreds?->appId() ?: null,
            'metaConfigIdWhatsapp' => $metaCreds?->configIdWhatsapp() ?: null,
            'metaConfigIdSocial' => $metaCreds?->configIdSocial() ?: null,
        ]);
    }

    public function updateOperations(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $this->authoriseChannelManagement($request);
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        abort_unless((int) $channelAccount->workspace_id === $workspaceId, 403);

        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
            'business_hours' => ['required', 'array'],
            'business_hours.*.enabled' => ['required', 'boolean'],
            'business_hours.*.start' => ['required_if:business_hours.*.enabled,true', 'nullable', 'date_format:H:i'],
            'business_hours.*.end' => ['required_if:business_hours.*.enabled,true', 'nullable', 'date_format:H:i'],
            'human_access_mode' => ['required', 'in:selected'],
            'allowed_user_ids' => ['nullable', 'array'],
            'allowed_user_ids.*' => ['integer'],
            'outside_hours_enabled' => ['required', 'boolean'],
            'outside_hours_message' => ['nullable', 'string', 'max:2000'],
            'outside_hours_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:72'],
        ]);

        $userIds = User::forWorkspace($workspaceId)
            ->where('status', User::STATUS_ACTIVE)
            ->whereIn('id', $data['allowed_user_ids'] ?? [])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($data['outside_hours_enabled'] && trim((string) ($data['outside_hours_message'] ?? '')) === '') {
            return back()->withErrors(['outside_hours_message' => 'Informe a mensagem enviada fora do horário.']);
        }

        $meta = $channelAccount->meta_json ?? [];
        $meta['operations'] = [
            'timezone' => $data['timezone'],
            'business_hours' => $data['business_hours'],
            'human_access_mode' => 'selected',
            'allowed_user_ids' => $userIds,
            'outside_hours_enabled' => $data['outside_hours_enabled'],
            'outside_hours_message' => trim((string) ($data['outside_hours_message'] ?? '')),
            'outside_hours_cooldown_hours' => (int) ($data['outside_hours_cooldown_hours'] ?? 12),
        ];
        $channelAccount->update(['meta_json' => $meta]);

        return back()->with('success', 'Horários, resposta automática e acesso da equipe atualizados.');
    }

    private function authoriseChannelManagement(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isClientAdministrator()), 403);
    }

    public function embeddedSignupInstagram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
        ]);

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        if (! CredentialResolver::system()->meta()?->appId()) {
            return response()->json(['message' => 'As credenciais do aplicativo Meta não estão configuradas. Solicite ao administrador a configuração em Admin → Integrações → Aplicativo Meta.'], 422);
        }

        // Ensure the Meta App delivers `instagram` webhook events to our endpoint.
        // Without this app-level subscription Meta has no callback URL for the
        // instagram object, so inbound Instagram messages never reach the server.
        $this->registerInstagramAppWebhook();

        $accessToken = $this->exchangeCodeForToken($validated['code']);
        if (! $accessToken) {
            return response()->json(['message' => 'Não foi possível concluir a autorização com a Meta.'], 422);
        }

        $longToken = $this->exchangeForLongLivedToken($accessToken);

        // Fetch pages the user manages with Instagram accounts connected
        $pagesRes = Http::withToken($longToken)
            ->get('https://graph.facebook.com/v20.0/me/accounts', [
                'fields' => 'id,name,access_token,instagram_business_account{id,name,username}',
                'limit' => 50,
            ]);

        if (! $pagesRes->successful()) {
            Log::warning('Instagram embedded signup: pages fetch failed', [
                'workspace_id' => $workspaceId,
                'response' => $pagesRes->json(),
            ]);

            return response()->json(['message' => 'Não foi possível consultar suas páginas do Facebook: '.($pagesRes->json('error.message') ?? 'erro desconhecido')], 422);
        }

        $pages = $pagesRes->json('data', []);
        $connected = 0;

        Log::info('Instagram embedded signup: pages fetched', [
            'workspace_id' => $workspaceId,
            'page_count' => count($pages),
            'pages' => collect($pages)->map(fn ($p) => [
                'id' => $p['id'] ?? null,
                'name' => $p['name'] ?? null,
                'has_instagram' => isset($p['instagram_business_account']['id']),
            ])->all(),
        ]);

        foreach ($pages as $page) {
            $igAccount = $page['instagram_business_account'] ?? null;
            if (! $igAccount || empty($igAccount['id'])) {
                continue;
            }

            $pageToken = $page['access_token'] ?? $longToken;
            $pageId = (string) ($page['id'] ?? '');
            $igId = (string) $igAccount['id'];
            $name = $igAccount['username'] ?? $igAccount['name'] ?? $page['name'] ?? $igId;

            // Subscribe the Page to Instagram messaging webhooks. Without this Meta
            // never delivers inbound Instagram messages to our /webhooks/meta endpoint.
            $this->subscribePageToInstagram($pageId, $pageToken);

            // Persisted shape mirrors InboxDemoSeeder + what InstagramDriver expects:
            // - credentials.instagram_account_id / access_token  → used by send()
            // - meta_json.instagram_page_id (= IG account id, matches webhook entry.id)
            // - meta_json.instagram_account_id / facebook_page_id → diagnostics + lookup
            $credentials = ['access_token' => $pageToken, 'instagram_account_id' => $igId];
            $metaJson = [
                'instagram_page_id' => $igId,
                'instagram_account_id' => $igId,
                'facebook_page_id' => $pageId,
            ];

            $existing = ChannelAccount::where('channel', 'instagram')
                ->where('external_account_id', $igId)
                ->first();
            if ($existing && (int) $existing->workspace_id !== (int) $workspaceId) {
                return response()->json(['message' => 'Esta conta do Instagram já está conectada a outro espaço de trabalho.'], 422);
            }
            $alreadyExists = $existing !== null;

            if ($alreadyExists) {
                // Update the token on re-connect (preserve any extra meta_json keys
                // such as an assigned ai_chatbot_id by merging rather than replacing).
                $existing?->update([
                    'credentials' => $credentials,
                    'meta_json' => array_merge($existing->meta_json ?? [], $metaJson),
                    'status' => 'active',
                    'external_account_id' => $igId,
                ]);
            } else {
                ChannelAccount::create([
                    'workspace_id' => $workspaceId,
                    'channel' => 'instagram',
                    'provider' => 'meta',
                    'display_name' => mb_substr((string) $name, 0, 128),
                    'credentials' => $credentials,
                    'meta_json' => $metaJson,
                    'status' => 'active',
                    'external_account_id' => $igId,
                ]);
            }

            Log::info('Instagram embedded signup: account connected', [
                'workspace_id' => $workspaceId,
                'facebook_page_id' => $pageId,
                'instagram_account_id' => $igId,
                'reconnect' => $alreadyExists,
            ]);

            $connected++;
        }

        if ($connected === 0) {
            $pageCount = count($pages);
            $message = $pageCount === 0
                ? 'Nenhuma página do Facebook foi retornada. Confirme o acesso às páginas durante a autorização e a permissão pages_show_list na configuração Social do aplicativo Meta.'
                : 'Nenhuma conta comercial do Instagram foi encontrada nas '.$pageCount.' página(s) autorizada(s). Vincule uma conta profissional do Instagram à página no Meta Business Suite e confirme a permissão instagram_basic.';

            return response()->json(['message' => $message], 422);
        }

        return response()->json(['success' => true, 'connected' => $connected]);
    }

    public function embeddedSignupMessenger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
        ]);

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        if (! CredentialResolver::system()->meta()?->appId()) {
            return response()->json(['message' => 'As credenciais do aplicativo Meta não estão configuradas. Solicite ao administrador a configuração em Admin → Integrações → Aplicativo Meta.'], 422);
        }

        // Ensure the Meta App delivers `page` (Messenger) webhook events to our
        // endpoint. Without this app-level subscription Meta has no callback URL for
        // the page object, so inbound Messenger messages never reach the server.
        $this->registerMessengerAppWebhook();

        $accessToken = $this->exchangeCodeForToken($validated['code']);
        if (! $accessToken) {
            return response()->json(['message' => 'Não foi possível concluir a autorização com a Meta.'], 422);
        }

        $longToken = $this->exchangeForLongLivedToken($accessToken);

        $pagesRes = Http::withToken($longToken)
            ->get('https://graph.facebook.com/v20.0/me/accounts', [
                'fields' => 'id,name,access_token',
                'limit' => 50,
            ]);

        if (! $pagesRes->successful()) {
            Log::warning('Messenger embedded signup: pages fetch failed', [
                'workspace_id' => $workspaceId,
                'response' => $pagesRes->json(),
            ]);

            return response()->json(['message' => 'Não foi possível consultar suas páginas do Facebook: '.($pagesRes->json('error.message') ?? 'erro desconhecido')], 422);
        }

        $pages = $pagesRes->json('data', []);
        $connected = 0;

        Log::info('Messenger embedded signup: pages fetched', [
            'workspace_id' => $workspaceId,
            'page_count' => count($pages),
            'pages' => collect($pages)->map(fn ($p) => [
                'id' => $p['id'] ?? null,
                'name' => $p['name'] ?? null,
            ])->all(),
        ]);

        foreach ($pages as $page) {
            $pageId = (string) ($page['id'] ?? '');
            $pageName = $page['name'] ?? $pageId;
            $pageToken = $page['access_token'] ?? null;

            if (! $pageId) {
                continue;
            }

            // A PAGE access token is mandatory: it is what authorises send() and the
            // User Profile API (name/picture). If /me/accounts didn't include one,
            // fetch it explicitly. Never fall back to the user token — a user token
            // cannot resolve page-scoped PSIDs and yields Graph error 100.
            if (! $pageToken) {
                $tokenRes = Http::withToken($longToken)
                    ->get("https://graph.facebook.com/v20.0/{$pageId}", ['fields' => 'access_token']);
                $pageToken = $tokenRes->json('access_token');
            }

            if (! $pageToken) {
                Log::warning('Messenger embedded signup: no page access token — page skipped', [
                    'workspace_id' => $workspaceId,
                    'page_id' => $pageId,
                    'page_name' => $pageName,
                ]);

                continue;
            }

            // Subscribe the page to Messenger webhooks
            $this->subscribePageToMessenger($pageId, $pageToken);

            $existing = ChannelAccount::where('channel', 'messenger')
                ->where('external_account_id', $pageId)
                ->first();
            if ($existing && (int) $existing->workspace_id !== (int) $workspaceId) {
                return response()->json(['message' => 'Esta página do Messenger já está conectada a outro espaço de trabalho.'], 422);
            }
            $alreadyExists = $existing !== null;

            if ($existing) {
                // Update through the model instance (NOT the query builder) so the
                // `encrypted:array` cast runs and the page token is stored encrypted.
                // A query-builder update() bypasses casts and corrupts credentials,
                // which then fail to decrypt — breaking send() and the profile fetch.
                // Merge meta_json so an assigned ai_chatbot_id is preserved.
                $existing->update([
                    'credentials' => ['page_access_token' => $pageToken],
                    'meta_json' => array_merge($existing->meta_json ?? [], ['page_id' => $pageId]),
                    'status' => 'active',
                    'external_account_id' => $pageId,
                ]);
            } else {
                ChannelAccount::create([
                    'workspace_id' => $workspaceId,
                    'channel' => 'messenger',
                    'provider' => 'meta',
                    'display_name' => mb_substr((string) $pageName, 0, 128),
                    'credentials' => ['page_access_token' => $pageToken],
                    'meta_json' => ['page_id' => $pageId],
                    'status' => 'active',
                    'external_account_id' => $pageId,
                ]);
            }

            Log::info('Messenger embedded signup: account connected', [
                'workspace_id' => $workspaceId,
                'page_id' => $pageId,
                'reconnect' => $alreadyExists,
            ]);

            $connected++;
        }

        if ($connected === 0) {
            return response()->json([
                'message' => 'Nenhuma página do Facebook foi encontrada. Confirme se você administra ao menos uma página.',
            ], 422);
        }

        return response()->json(['success' => true, 'connected' => $connected]);
    }

    private function exchangeCodeForToken(string $code): ?string
    {
        $meta = CredentialResolver::system()->meta();
        if (! $meta?->appId() || ! $meta?->appSecret()) {
            return null;
        }

        $res = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', [
            'client_id' => $meta->appId(),
            'client_secret' => $meta->appSecret(),
            'code' => $code,
            'redirect_uri' => '',
        ]);

        if (! $res->successful() || ! $res->json('access_token')) {
            Log::warning('Meta embedded signup: code exchange failed', [
                'response' => $res->json(),
            ]);

            return null;
        }

        return $res->json('access_token');
    }

    private function exchangeForLongLivedToken(string $shortToken): string
    {
        $meta = CredentialResolver::system()->meta();
        if (! $meta?->appId() || ! $meta?->appSecret()) {
            return $shortToken;
        }

        $res = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $meta->appId(),
            'client_secret' => $meta->appSecret(),
            'fb_exchange_token' => $shortToken,
        ]);

        return ($res->successful() && $res->json('access_token')) ? $res->json('access_token') : $shortToken;
    }

    /**
     * Register the app-level `page` (Messenger) webhook subscription so Meta knows
     * which callback URL to deliver Messenger events to. Uses the App Access Token
     * ({app_id}|{app_secret}) and points at our /webhooks/meta/{verify_token}
     * endpoint. Idempotent: re-registering the same URL/fields is a no-op on Meta.
     * Mirrors registerInstagramAppWebhook() — without it inbound Messenger messages
     * never reach the server (no app-level callback for the page object).
     */
    private function registerMessengerAppWebhook(): void
    {
        $meta = CredentialResolver::system()->meta();
        $appId = $meta?->appId();
        $appSecret = $meta?->appSecret();
        $verifyToken = $meta?->verifyToken();

        if (! $appId || ! $appSecret || ! $verifyToken) {
            Log::warning('Messenger embedded signup: cannot register app webhook — missing app id/secret/verify token', [
                'has_app_id' => (bool) $appId,
                'has_app_secret' => (bool) $appSecret,
                'has_verify_token' => (bool) $verifyToken,
            ]);

            return;
        }

        $callbackUrl = route('webhooks.meta.receive', ['token' => $verifyToken]);

        try {
            $res = Http::post("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
                'access_token' => $appId.'|'.$appSecret,
                'object' => 'page',
                'callback_url' => $callbackUrl,
                'verify_token' => $verifyToken,
                'fields' => 'messages,messaging_postbacks,messaging_optins,message_deliveries,message_reads',
            ]);

            if (! $res->successful()) {
                Log::warning('Messenger embedded signup: app webhook registration failed', [
                    'callback_url' => $callbackUrl,
                    'status' => $res->status(),
                    'response' => $res->json(),
                ]);

                return;
            }

            Log::info('Messenger embedded signup: app webhook registered', [
                'callback_url' => $callbackUrl,
                'response' => $res->json(),
            ]);

            // Read back what Meta actually stored so we can confirm the page object
            // has our callback URL and is marked active.
            $check = Http::get("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
                'access_token' => $appId.'|'.$appSecret,
            ]);
            Log::info('Messenger embedded signup: app subscriptions snapshot', [
                'status' => $check->status(),
                'response' => $check->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Messenger embedded signup: app webhook registration exception', [
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function subscribePageToMessenger(string $pageId, string $pageToken): void
    {
        if ($pageId === '') {
            Log::warning('Messenger embedded signup: cannot subscribe — empty page id');

            return;
        }

        try {
            $res = Http::withToken($pageToken)
                ->post("https://graph.facebook.com/v20.0/{$pageId}/subscribed_apps", [
                    'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,message_deliveries,message_reads',
                ]);

            if (! $res->successful()) {
                Log::warning('Messenger embedded signup: page subscription failed', [
                    'page_id' => $pageId,
                    'status' => $res->status(),
                    'response' => $res->json(),
                ]);

                return;
            }

            Log::info('Messenger embedded signup: page subscribed for messaging', [
                'page_id' => $pageId,
                'response' => $res->json(),
            ]);

            // Read back the page's subscribed_apps so we can confirm which fields
            // (must include "messages") are actually active for our app.
            $check = Http::withToken($pageToken)
                ->get("https://graph.facebook.com/v20.0/{$pageId}/subscribed_apps");
            Log::info('Messenger embedded signup: page subscribed_apps snapshot', [
                'page_id' => $pageId,
                'status' => $check->status(),
                'response' => $check->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Messenger embedded signup: page subscription exception', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the app-level `instagram` webhook subscription so Meta knows which
     * callback URL to deliver Instagram events to. Uses the App Access Token
     * ({app_id}|{app_secret}) and points at our /webhooks/meta/{verify_token}
     * endpoint. Idempotent: re-registering the same URL/fields is a no-op on Meta.
     */
    private function registerInstagramAppWebhook(): void
    {
        $meta = CredentialResolver::system()->meta();
        $appId = $meta?->appId();
        $appSecret = $meta?->appSecret();
        $verifyToken = $meta?->verifyToken();

        if (! $appId || ! $appSecret || ! $verifyToken) {
            Log::warning('Instagram embedded signup: cannot register app webhook — missing app id/secret/verify token', [
                'has_app_id' => (bool) $appId,
                'has_app_secret' => (bool) $appSecret,
                'has_verify_token' => (bool) $verifyToken,
            ]);

            return;
        }

        $callbackUrl = route('webhooks.meta.receive', ['token' => $verifyToken]);

        try {
            $res = Http::post("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
                'access_token' => $appId.'|'.$appSecret,
                'object' => 'instagram',
                'callback_url' => $callbackUrl,
                'verify_token' => $verifyToken,
                'fields' => 'messages,messaging_postbacks,message_reactions',
            ]);

            if (! $res->successful()) {
                Log::warning('Instagram embedded signup: app webhook registration failed', [
                    'callback_url' => $callbackUrl,
                    'status' => $res->status(),
                    'response' => $res->json(),
                ]);

                return;
            }

            Log::info('Instagram embedded signup: app webhook registered', [
                'callback_url' => $callbackUrl,
                'response' => $res->json(),
            ]);

            // Read back what Meta actually stored so we can confirm the instagram
            // object has our callback URL and is marked active.
            $check = Http::get("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
                'access_token' => $appId.'|'.$appSecret,
            ]);
            Log::info('Instagram embedded signup: app subscriptions snapshot', [
                'status' => $check->status(),
                'response' => $check->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram embedded signup: app webhook registration exception', [
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Subscribe the Facebook Page (linked to an Instagram professional account) to
     * Instagram messaging webhooks. This is required for Meta to deliver inbound
     * Instagram messages — the connect flow previously skipped it, so no messages
     * ever reached /webhooks/meta.
     */
    private function subscribePageToInstagram(string $pageId, string $pageToken): void
    {
        if ($pageId === '') {
            Log::warning('Instagram embedded signup: cannot subscribe — empty page id');

            return;
        }

        try {
            $res = Http::withToken($pageToken)
                ->post("https://graph.facebook.com/v20.0/{$pageId}/subscribed_apps", [
                    'subscribed_fields' => 'messages,messaging_postbacks,message_reactions,message_reads',
                ]);

            if (! $res->successful()) {
                Log::warning('Instagram embedded signup: page subscription failed', [
                    'page_id' => $pageId,
                    'status' => $res->status(),
                    'response' => $res->json(),
                ]);

                return;
            }

            Log::info('Instagram embedded signup: page subscribed for messaging', [
                'page_id' => $pageId,
                'response' => $res->json(),
            ]);

            // Read back the page's subscribed_apps so we can confirm which fields
            // (must include "messages") are actually active for our app.
            $check = Http::withToken($pageToken)
                ->get("https://graph.facebook.com/v20.0/{$pageId}/subscribed_apps");
            Log::info('Instagram embedded signup: page subscribed_apps snapshot', [
                'page_id' => $pageId,
                'status' => $check->status(),
                'response' => $check->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Instagram embedded signup: page subscription exception', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function assignChatbot(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $channelAccount->workspace_id === (int) $workspaceId, 403);

        $validated = $request->validate([
            'chatbot_id' => ['nullable', 'integer'],
        ]);

        $chatbotId = $validated['chatbot_id'] ?? null;

        if ($chatbotId !== null) {
            $exists = AiChatbot::where('id', $chatbotId)
                ->where('workspace_id', $workspaceId)
                ->where('enabled', true)
                ->exists();
            abort_unless($exists, 422, 'Chatbot not found or not enabled.');
        }

        $meta = $channelAccount->meta_json ?? [];
        if ($chatbotId === null) {
            unset($meta['ai_chatbot_id']);
        } else {
            $meta['ai_chatbot_id'] = $chatbotId;
        }
        $channelAccount->update(['meta_json' => $meta]);

        $label = $chatbotId ? 'Chatbot assigned.' : 'Chatbot removed.';

        return back()->with('success', $label);
    }

    public function destroy(Request $request, ChannelAccount $channelAccount): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        abort_unless((int) $channelAccount->workspace_id === (int) $workspaceId, 403);
        abort_unless(in_array($channelAccount->channel, ['instagram', 'messenger'], true), 403);

        $channelAccount->delete();

        return back()->with('success', 'Conta desconectada.');
    }
}
