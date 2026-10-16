<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Whatsapp\Jobs\TemplateSyncJob;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class WhatsappEmbeddedSignupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:2048'],
            'waba_id'          => ['required', 'string', 'max:64'],
            'phone_number_id'  => ['nullable', 'string', 'max:64'],
        ]);

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $meta = CredentialResolver::system()->meta();
        if (! $meta || ! $meta->appId() || ! $meta->appSecret()) {
            return response()->json(['message' => 'As credenciais do aplicativo Meta não estão configuradas. Solicite ao administrador a configuração em Admin → Integrações → Aplicativo Meta.'], 422);
        }

        $redirectUri = rtrim((string) config('app.url'), '/');

        // Exchange the short-lived auth code for an access token
        $tokenParams = [
            'client_id'     => $meta->appId(),
            'client_secret' => $meta->appSecret(),
            'code'          => $validated['code'],
        ];
        if ($redirectUri !== '') {
            $tokenParams['redirect_uri'] = $redirectUri;
        }

        $tokenRes = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', $tokenParams);

        // Some Meta app configs reject redirect_uri on embedded-signup codes — retry without it.
        if ((! $tokenRes->successful() || empty($tokenRes->json('access_token'))) && isset($tokenParams['redirect_uri'])) {
            unset($tokenParams['redirect_uri']);
            $tokenRes = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', $tokenParams);
        }

        if (! $tokenRes->successful() || empty($tokenRes->json('access_token'))) {
            Log::warning('WhatsApp embedded signup: code exchange failed', [
                'workspace_id' => $workspaceId,
                'response'     => $tokenRes->json(),
            ]);

            return response()->json([
                'message' => 'Failed to exchange authorization code: ' . ($tokenRes->json('error.message') ?? 'unknown error'),
            ], 422);
        }

        $shortToken = $tokenRes->json('access_token');

        // Exchange short-lived token for a long-lived token
        $longTokenRes = Http::get('https://graph.facebook.com/v20.0/oauth/access_token', [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $meta->appId(),
            'client_secret'     => $meta->appSecret(),
            'fb_exchange_token' => $shortToken,
        ]);

        $accessToken = $longTokenRes->successful() && $longTokenRes->json('access_token')
            ? $longTokenRes->json('access_token')
            : $shortToken;

        // Fetch WABA details from Meta
        $wabaRes = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/v20.0/{$validated['waba_id']}", [
                'fields' => 'id,name,currency,timezone_id',
            ]);

        if (! $wabaRes->successful()) {
            Log::warning('WhatsApp embedded signup: WABA fetch failed', [
                'workspace_id' => $workspaceId,
                'waba_id'      => $validated['waba_id'],
                'response'     => $wabaRes->json(),
            ]);

            return response()->json([
                'message' => 'Connected but could not fetch WABA details: ' . ($wabaRes->json('error.message') ?? 'unknown error'),
            ], 422);
        }

        $wabaData = $wabaRes->json();

        if (WhatsappBusinessAccount::where('waba_id', $validated['waba_id'])
            ->where('workspace_id', '!=', $workspaceId)
            ->exists()) {
            return response()->json(['message' => 'Esta conta do WhatsApp Business já está conectada a outro espaço de trabalho.'], 409);
        }

        $existing = WhatsappBusinessAccount::where('waba_id', $validated['waba_id'])
            ->where('workspace_id', $workspaceId)
            ->first();

        $verifyToken = $existing?->webhook_verify_token ?? Str::random(48);

        $waba = WhatsappBusinessAccount::updateOrCreate(
            ['waba_id' => $validated['waba_id'], 'workspace_id' => $workspaceId],
            [
                'credentials' => [
                    'system_user_token' => $accessToken,
                    'access_token'      => $accessToken,
                    'token_source'      => 'embedded_signup',
                ],
                'webhook_verify_token' => $verifyToken,
                'status'               => 'active',
                'meta_json'            => array_merge($existing?->meta_json ?? [], [
                    'display_name'  => $wabaData['name'] ?? $validated['waba_id'],
                    'currency'      => $wabaData['currency'] ?? null,
                    'timezone_id'   => $wabaData['timezone_id'] ?? null,
                    'connected_via' => 'embedded_signup',
                ]),
            ]
        );

        // Subscribe the app to this WABA for webhooks and register callback URL
        $webhookError = $this->subscribeWabaWebhooks($validated['waba_id'], $accessToken, $waba->webhook_verify_token, $meta);

        // Sync phone numbers (try user token, then app token, then admin system user)
        $phoneCount = 0;
        $syncError  = null;
        try {
            $phoneCount = $this->syncPhoneNumbers($waba->fresh(), $accessToken, $meta);
        } catch (\Throwable $e) {
            $syncError = $e->getMessage();
            Log::warning('WhatsApp embedded signup: phone sync failed', [
                'workspace_id' => $workspaceId,
                'waba_id'      => $validated['waba_id'],
                'error'        => $e->getMessage(),
            ]);
        }

        if (! empty($validated['phone_number_id'])) {
            try {
                $details = CloudApiClient::fetchPhoneNumberDetails($validated['phone_number_id'], $accessToken);
                $this->attachPhoneNumber($waba->fresh(), $validated['phone_number_id'], $details ?? ['id' => $validated['phone_number_id']]);
                $this->registerNumber($validated['phone_number_id'], $accessToken, $validated['waba_id']);
                $phoneCount = max($phoneCount, 1);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp embedded signup: session phone attach failed', [
                    'phone_number_id' => $validated['phone_number_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($phoneCount > 0) {
            TemplateSyncJob::dispatch($waba->id)->onQueue('whatsapp');
        }

        $warnings = array_filter([$webhookError, $syncError, $phoneCount === 0 ? 'No phone numbers were synced. Use Sync from Meta on Channel Setup or reconnect.' : null]);

        return response()->json([
            'success'         => true,
            'waba_id'         => $validated['waba_id'],
            'name'            => $wabaData['name'] ?? $validated['waba_id'],
            'phone_count'     => $phoneCount,
            'sync_error'      => $syncError,
            'webhook_warning' => $warnings !== [] ? implode(' ', $warnings) : null,
        ]);
    }

    public function reregisterWebhook(Request $request, \App\Modules\Whatsapp\Models\WhatsappBusinessAccount $waba): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        if ($waba->workspace_id !== $workspaceId) {
            abort(403);
        }

        $meta = CredentialResolver::system()->meta();
        if (! $meta || ! $meta->appId() || ! $meta->appSecret()) {
            return response()->json(['message' => 'As credenciais do aplicativo Meta não estão configuradas.'], 422);
        }

        $token = $waba->accessToken() ?? $meta->systemUserToken();
        if (! $token) {
            return response()->json(['message' => 'Não há token de acesso disponível para esta conta do WhatsApp Business.'], 422);
        }

        $webhookError = $this->subscribeWabaWebhooks($waba->waba_id, $token, $waba->webhook_verify_token, $meta);

        if ($webhookError) {
            return response()->json(['success' => false, 'message' => $webhookError], 422);
        }

        return response()->json(['success' => true, 'message' => 'Webhook re-registered with Meta.']);
    }

    private function subscribeWabaWebhooks(string $wabaId, string $userToken, string $verifyToken, \App\Modules\Integrations\Services\Credentials\MetaCredentials $meta): ?string
    {
        $appId     = $meta->appId();
        $appSecret = $meta->appSecret();
        // App Access Token: {app_id}|{app_secret} — must be passed as query param, not Bearer header
        $appToken  = $appId . '|' . $appSecret;

        // Step 1: Subscribe our Meta App to this WABA's events.
        // Use the App Access Token (more reliable than the short-lived user token).
        try {
            $subRes = Http::post("https://graph.facebook.com/v20.0/{$wabaId}/subscribed_apps", [
                'access_token' => $appToken,
            ]);

            if (! $subRes->successful()) {
                $fallback = Http::post("https://graph.facebook.com/v20.0/{$wabaId}/subscribed_apps", [
                    'access_token' => $userToken,
                ]);
                if (! $fallback->successful()) {
                    Log::warning('WhatsApp embedded signup: subscribed_apps failed', [
                        'waba_id' => $wabaId,
                        'app_response' => $subRes->json(),
                        'user_response' => $fallback->json(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp embedded signup: subscribed_apps call failed', [
                'waba_id' => $wabaId,
                'error'   => $e->getMessage(),
            ]);
        }

        // Step 2: Register the global webhook callback URL with Meta.
        // /{app_id}/subscriptions sets ONE URL for the entire Meta App; all WABAs share it.
        // We use the stable global endpoint so multiple embedded-signup WABAs don't overwrite each other.
        // The App Access Token must be passed as access_token body param (Bearer header not accepted here).
        try {
            $callbackUrl  = route('webhooks.whatsapp.global.receive');
            $globalVerify = hash('sha256', $appId . $appSecret . 'wh_global_verify');

            $res = Http::post("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
                'access_token' => $appToken,
                'object'       => 'whatsapp_business_account',
                'callback_url' => $callbackUrl,
                'verify_token' => $globalVerify,
                'fields'       => 'messages,message_template_status_update,phone_number_name_update,phone_number_quality_update,account_update',
            ]);

            if (! $res->successful()) {
                $errMsg  = $res->json('error.message') ?? 'unknown error';
                $errCode = $res->json('error.code') ?? $res->status();
                Log::warning('WhatsApp embedded signup: app subscription registration failed', [
                    'waba_id'      => $wabaId,
                    'callback_url' => $callbackUrl,
                    'http_status'  => $res->status(),
                    'error_code'   => $errCode,
                    'error_msg'    => $errMsg,
                    'full_response' => $res->json(),
                    'hint' => match (true) {
                        $errCode == 10  => 'App lacks whatsapp_business_management permission or is not approved for this WABA',
                        $errCode == 100 => 'Callback URL could not be verified — Meta sent GET challenge to callback_url and did not get hub.challenge back. Check the URL is publicly accessible.',
                        $errCode == 190 => 'App Access Token invalid — check App ID and App Secret in Admin → Integrations → Meta App',
                        $errCode == 200 => 'App permission error — ensure the app has webhook subscriptions permission',
                        default         => 'Check developers.facebook.com/docs/graph-api/webhooks for error code ' . $errCode,
                    },
                ]);
                return "Webhook registration with Meta failed (code {$errCode}): {$errMsg}. Check laravel.log for details.";
            }

            Log::info('WhatsApp embedded signup: global webhook registered', [
                'waba_id'      => $wabaId,
                'callback_url' => $callbackUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp embedded signup: app subscription registration exception', [
                'waba_id' => $wabaId,
                'error'   => $e->getMessage(),
            ]);
            return "Webhook registration with Meta failed: {$e->getMessage()}. Messages will not be received until this is resolved.";
        }

        return null;
    }

    private function syncPhoneNumbers(
        WhatsappBusinessAccount $waba,
        string $userToken,
        \App\Modules\Integrations\Services\Credentials\MetaCredentials $meta,
    ): int {
        $tokens = array_values(array_unique(array_filter([
            $userToken,
            $meta->appId().'|'.$meta->appSecret(),
            $meta->systemUserToken(),
        ])));

        $rows = [];
        $lastError = null;

        foreach ($tokens as $token) {
            try {
                $rows = CloudApiClient::fetchWabaPhoneNumbers($waba->waba_id, $token);
                if ($rows !== []) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        if ($rows === [] && $lastError) {
            throw new \RuntimeException($lastError);
        }

        $syncToken = $userToken;
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $details = CloudApiClient::fetchPhoneNumberDetails((string) $row['id'], $syncToken);
            if (is_array($details)) {
                $row = array_merge($row, $details);
            }

            $this->attachPhoneNumber($waba, (string) $row['id'], $row);
            $this->registerNumber((string) $row['id'], $syncToken, $waba->waba_id);
            $count++;
        }

        return $count;
    }

    /**
     * Register a phone number with the Cloud API so it leaves the PENDING state
     * and becomes CONNECTED (active). New numbers added via Embedded Signup are
     * created PENDING and stay that way until registered. Best-effort: a number
     * that is already registered returns an error we simply log.
     */
    private function registerNumber(string $phoneNumberId, string $accessToken, string $wabaId): void
    {
        try {
            $result = CloudApiClient::registerPhoneNumber($phoneNumberId, $accessToken);

            if (! $result['success']) {
                Log::info('WhatsApp embedded signup: phone register skipped/failed', [
                    'waba_id'         => $wabaId,
                    'phone_number_id' => $phoneNumberId,
                    'status'          => $result['status'],
                    'response'        => $result['response'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp embedded signup: phone register exception', [
                'waba_id'         => $wabaId,
                'phone_number_id' => $phoneNumberId,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    private function attachPhoneNumber(WhatsappBusinessAccount $waba, string $phoneNumberId, array $metaRow): void
    {
        $throughput = is_array($metaRow['throughput'] ?? null) ? $metaRow['throughput'] : [];
        $tier = $metaRow['messaging_limit_tier'] ?? ($throughput['level'] ?? null);

        // Only the Meta-sourced descriptive fields, with nulls filtered out so a
        // detail-less attach (right after embedded signup, before Meta has
        // propagated the new number node) never overwrites values an earlier
        // phone_numbers-edge sync already populated. The waba link is always set.
        $details = array_filter([
            'display_phone'            => $metaRow['display_phone_number'] ?? null,
            'verified_name'            => $metaRow['verified_name'] ?? null,
            'quality_rating'           => $metaRow['quality_rating'] ?? null,
            'messaging_limit_tier'     => is_string($tier) ? $tier : null,
            'code_verification_status' => $metaRow['code_verification_status'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        \App\Modules\Whatsapp\Models\WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneNumberId],
            array_merge(['waba_id_fk' => $waba->id], $details),
        );

        $this->upsertChannelAccount($waba, $phoneNumberId, $metaRow);
    }

    /**
     * Upsert the inbox-facing ChannelAccount without clobbering an existing
     * display name when this attach carries no usable label.
     *
     * @param  array<string, mixed>  $metaRow
     */
    private function upsertChannelAccount(WhatsappBusinessAccount $waba, string $phoneNumberId, array $metaRow): void
    {
        $conflict = ChannelAccount::where('channel', 'whatsapp')
            ->where('external_account_id', $phoneNumberId)
            ->where('workspace_id', '!=', $waba->workspace_id)
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages([
                'whatsapp' => __('This WhatsApp number is already connected to another workspace.'),
            ]);
        }

        $account = ChannelAccount::firstOrNew([
            'workspace_id'    => $waba->workspace_id,
            'phone_number_id' => $phoneNumberId,
        ]);

        $account->channel             = 'whatsapp';
        $account->provider            = 'meta';
        $account->business_account_id = $waba->waba_id;
        $account->external_account_id  = $phoneNumberId;
        $account->status              = 'active';

        $label = $metaRow['verified_name'] ?? $metaRow['display_phone_number'] ?? null;
        if ($label !== null && $label !== '') {
            $account->display_name = mb_substr((string) $label, 0, 128);
        } elseif (! $account->exists) {
            $account->display_name = 'WhatsApp';
        }

        $account->save();
    }
}
