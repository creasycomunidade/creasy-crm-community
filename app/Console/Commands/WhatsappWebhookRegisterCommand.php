<?php

namespace App\Console\Commands;

use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatsappWebhookRegisterCommand extends Command
{
    protected $signature = 'whatsapp:register-webhook
                            {--waba= : Specific WABA ID to register (optional, registers all if omitted)}
                            {--dry-run : Show what would be sent without calling Meta}';

    protected $description = 'Register the global WhatsApp webhook callback URL with Meta for all embedded-signup WABAs';

    public function handle(): int
    {
        $meta = CredentialResolver::system()->meta();

        if (! $meta || ! $meta->appId() || ! $meta->appSecret()) {
            $this->error('Meta App credentials not configured. Go to Admin → Integrations → Meta App.');
            return self::FAILURE;
        }

        $appId       = $meta->appId();
        $appSecret   = $meta->appSecret();
        $appToken    = $appId . '|' . $appSecret;
        $callbackUrl = route('webhooks.whatsapp.global.receive');
        $verifyToken = hash('sha256', $appId . $appSecret . 'wh_global_verify');

        $this->info('');
        $this->line('  <fg=cyan>App ID:</> ' . $appId);
        $this->line('  <fg=cyan>Callback URL:</> ' . $callbackUrl);
        $this->line('  <fg=cyan>Verify Token:</> ' . substr($verifyToken, 0, 16) . '…');
        $this->info('');

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Would POST to: https://graph.facebook.com/v20.0/' . $appId . '/subscriptions');
            $this->warn('[dry-run] With fields: messages, message_template_status_update, phone_number_name_update, phone_number_quality_update, account_update');
            return self::SUCCESS;
        }

        // ── Step 1: Register global callback URL at app level ──────────────────
        $this->line('<fg=yellow>Step 1:</> Registering global callback URL with Meta App…');

        $res = Http::post("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
            'access_token' => $appToken,
            'object'       => 'whatsapp_business_account',
            'callback_url' => $callbackUrl,
            'verify_token' => $verifyToken,
            'fields'       => 'messages,message_template_status_update,phone_number_name_update,phone_number_quality_update,account_update',
        ]);

        if (! $res->successful()) {
            $this->error('FAILED. Meta returned HTTP ' . $res->status());
            $this->error('Response: ' . $res->body());
            $this->info('');
            $this->line('<fg=yellow>Common causes:</>');
            $this->line('  • App is in Development mode and the WABA is not a test WABA');
            $this->line('  • App Secret is incorrect');
            $this->line('  • Callback URL is not publicly reachable by Meta');
            $this->line('  • The verify token check at GET ' . $callbackUrl . ' returned non-200');
            $this->info('');
            $this->line('Test manually: curl "' . $callbackUrl . '?hub.mode=subscribe&hub.verify_token=' . $verifyToken . '&hub.challenge=TESTCHALLENGE"');
            return self::FAILURE;
        }

        $this->info('✓ Global callback URL registered with Meta.');

        // ── Step 2: Subscribe each WABA to the app ────────────────────────────
        $query = WhatsappBusinessAccount::where('status', 'active');
        if ($wabaId = $this->option('waba')) {
            $query->where('waba_id', $wabaId);
        }

        $wabas = $query->get();

        if ($wabas->isEmpty()) {
            $this->warn('No active WABAs found in database. Connect a WABA first via Channel Setup.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('<fg=yellow>Step 2:</> Subscribing ' . $wabas->count() . ' WABA(s) to the app…');

        foreach ($wabas as $waba) {
            $this->line('  WABA ' . $waba->waba_id . '…');

            $token = $waba->accessToken() ?? $meta->systemUserToken();

            if (! $token) {
                $this->warn('  ⚠ No access token for WABA ' . $waba->waba_id . ' — skipping subscribed_apps call');
                continue;
            }

            // Try app token first, fall back to WABA user token
            $subRes = Http::post("https://graph.facebook.com/v20.0/{$waba->waba_id}/subscribed_apps", [
                'access_token' => $appToken,
            ]);

            if (! $subRes->successful()) {
                $subRes = Http::post("https://graph.facebook.com/v20.0/{$waba->waba_id}/subscribed_apps", [
                    'access_token' => $token,
                ]);
            }

            if ($subRes->successful()) {
                $this->info('  ✓ WABA ' . $waba->waba_id . ' subscribed.');
            } else {
                $this->error('  ✗ WABA ' . $waba->waba_id . ' subscription failed: ' . $subRes->body());
            }
        }

        // ── Step 3: Verify the subscription was recorded ──────────────────────
        $this->line('');
        $this->line('<fg=yellow>Step 3:</> Verifying subscription in Meta…');

        $checkRes = Http::get("https://graph.facebook.com/v20.0/{$appId}/subscriptions", [
            'access_token' => $appToken,
        ]);

        if ($checkRes->successful()) {
            $subs = collect($checkRes->json('data', []))
                ->where('object', 'whatsapp_business_account');

            if ($subs->isEmpty()) {
                $this->warn('  ⚠ No whatsapp_business_account subscriptions found in Meta. The POST may have failed silently.');
            } else {
                foreach ($subs as $sub) {
                    // Meta reports subscription state as an `active` boolean, not a
                    // `status` string. Fall back to `status` for older API shapes.
                    $active = ($sub['active'] ?? null) === true || ($sub['status'] ?? '') === 'active';
                    $fields = array_column($sub['fields'] ?? [], 'name');
                    $hasMessages = in_array('messages', $fields, true);

                    $status = $active ? '<fg=green>active</>' : '<fg=red>' . ($sub['status'] ?? 'inactive') . '</>';
                    $this->line('  Status: ' . $status);
                    $this->line('  Callback: ' . ($sub['callback_url'] ?? 'unknown'));
                    $this->line('  Fields: ' . implode(', ', $fields));
                    $this->line('  Inbound (messages): ' . ($hasMessages
                        ? '<fg=green>subscribed</>'
                        : '<fg=red>MISSING — inbound webhooks will not arrive</>'));
                }
            }
        } else {
            $this->warn('  Could not verify subscription: ' . $checkRes->body());
        }

        $this->info('');
        $this->info('Done. Test the endpoint:');
        $this->line('  curl "' . $callbackUrl . '?hub.mode=subscribe&hub.verify_token=' . $verifyToken . '&hub.challenge=HELLO"');
        $this->line('  Expected response: HELLO');

        return self::SUCCESS;
    }
}
