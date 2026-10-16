<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Concerns\FlushesWebhookResponse;
use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Whatsapp\Jobs\ProcessInboundMessageJob;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    use FlushesWebhookResponse;
    /**
     * A stable, system-wide verify token derived from app credentials.
     * Used by the global endpoint so all embedded-signup WABAs share one callback URL.
     */
    private function globalVerifyToken(): ?string
    {
        $meta = CredentialResolver::system()->meta();
        if (! $meta?->appId() || ! $meta->appSecret()) {
            return null;
        }
        return hash('sha256', $meta->appId() . $meta->appSecret() . 'wh_global_verify');
    }

    /** GET /webhooks/whatsapp/global — Meta challenge verification for the global endpoint */
    public function verifyGlobal(Request $request): Response
    {
        $expectedToken = $this->globalVerifyToken();

        if (! $expectedToken) {
            abort(403, 'Meta credentials not configured');
        }

        if ($request->input('hub_mode') === 'subscribe'
            && hash_equals($expectedToken, (string) $request->input('hub_verify_token', ''))) {
            return response($request->input('hub_challenge', ''), 200);
        }

        abort(400);
    }

    /** POST /webhooks/whatsapp/global — receives events for all embedded-signup WABAs */
    public function receiveGlobal(Request $request): JsonResponse
    {
        $meta      = CredentialResolver::system()->meta();
        $appSecret = $meta?->appSecret();

        if ($appSecret) {
            $this->verifyHmacSignature($request, $appSecret);
        } elseif (app()->environment('production')) {
            Log::critical('whatsapp.webhook.global.no_secret', ['ip' => $request->ip()]);
            abort(401, 'App secret not configured');
        } else {
            Log::warning('whatsapp.webhook.global.unsigned', ['ip' => $request->ip()]);
        }

        $idempotency = app(WebhookIdempotencyService::class);
        $newEntries  = [];
        foreach ($request->input('entry', []) as $entry) {
            $eventKey = $this->entryEventKey($entry);
            if ($eventKey === null || $idempotency->isNewEvent('whatsapp_global', $eventKey)) {
                $newEntries[] = $entry;
            }
        }

        if (empty($newEntries)) {
            return response()->json(['status' => 'ok']);
        }

        $payload = array_merge($request->all(), ['entry' => $newEntries]);

        Log::info('whatsapp.webhook.global.received', [
            'entry_count'  => count($newEntries),
            'waba_ids'     => collect($newEntries)->pluck('id')->all(),
            'has_messages' => collect($newEntries)->contains(
                fn ($e) => collect($e['changes'] ?? [])->contains(
                    fn ($c) => ! empty($c['value']['messages'] ?? [])
                )
            ),
            'has_statuses' => collect($newEntries)->contains(
                fn ($e) => collect($e['changes'] ?? [])->contains(
                    fn ($c) => ! empty($c['value']['statuses'] ?? [])
                )
            ),
        ]);

        return $this->flushWebhookOkThen(
            fn () => ProcessInboundMessageJob::dispatch($payload, '')->onQueue('whatsapp')
        );
    }

    public function verify(Request $request, string $token): Response
    {
        $waba = WhatsappBusinessAccount::findByWebhookToken($token);

        if (! $waba) {
            abort(403, 'Invalid verify token');
        }

        if ($request->input('hub_mode') === 'subscribe'
            && hash_equals($token, (string) $request->input('hub_verify_token', ''))) {
            return response($request->input('hub_challenge', ''), 200);
        }

        abort(400);
    }

    public function receive(Request $request, string $token): JsonResponse
    {
        $waba = WhatsappBusinessAccount::findByWebhookToken($token);

        if (! $waba) {
            Log::warning('whatsapp.webhook.unknown_token', [
                'ip'             => $request->ip(),
                'received_token' => substr($token, 0, 12) . '…',
                'token_hash'     => hash('sha256', $token),
                'hint'           => 'Token hash does not match any webhook_verify_token_hash in whatsapp_business_accounts. Run: php artisan tinker --execute="DB::table(\'whatsapp_business_accounts\')->get([\'waba_id\',\'webhook_verify_token_hash\',\'status\'])->each(fn(\$r)=>print_r((array)\$r));"',
            ]);
            abort(403, 'Invalid verify token');
        }

        // Resolve app secret: WABA-level override first, then system credential.
        $appSecret = ($waba->credentials ?? [])['app_secret_override'] ?? null;
        if (! $appSecret) {
            $appSecret = CredentialResolver::system()->meta()?->appSecret();
        }

        if ($appSecret) {
            $this->verifyHmacSignature($request, $appSecret);
        } elseif (app()->environment('production')) {
            Log::critical('whatsapp.webhook.no_secret', ['workspace_id' => $waba->workspace_id]);
            abort(401, 'App secret not configured');
        } else {
            Log::warning('whatsapp.webhook.unsigned', ['workspace_id' => $waba->workspace_id]);
        }

        // Deduplicate at the entry level before dispatching any jobs.
        // insertOrIgnore is atomic — only one concurrent request gets affected=1 per event key.
        $idempotency = app(WebhookIdempotencyService::class);
        $newEntries  = [];
        foreach ($request->input('entry', []) as $entry) {
            $eventKey = $this->entryEventKey($entry);
            if ($eventKey === null || $idempotency->isNewEvent('whatsapp', $eventKey)) {
                $newEntries[] = $entry;
            }
        }

        if (empty($newEntries)) {
            return response()->json(['status' => 'ok']);
        }

        $payload = array_merge($request->all(), ['entry' => $newEntries]);

        Log::info('whatsapp.webhook.received', [
            'workspace_id' => $waba->workspace_id,
            'waba_id'      => $waba->waba_id,
            'entry_count'  => count($newEntries),
            'has_messages' => collect($newEntries)->contains(fn ($e) => ! empty(data_get($e, 'changes.0.value.messages'))),
            'has_statuses' => collect($newEntries)->contains(fn ($e) => ! empty(data_get($e, 'changes.0.value.statuses'))),
        ]);

        return $this->flushWebhookOkThen(
            fn () => ProcessInboundMessageJob::dispatch($payload, $token)->onQueue('whatsapp')
        );
    }

    /**
     * Build a stable idempotency key for a webhook entry from the actual events
     * it carries (message ids, status transitions).
     *
     * WhatsApp sets `entry.id` to the WABA id — which is identical for every
     * webhook from that account — so it must NEVER be used as the dedup key, or
     * every webhook after the first would be discarded as a "duplicate" and no
     * inbound message would ever be processed.
     *
     * Returns null when the entry carries no identifiable event, so the caller
     * processes it (fail-open) rather than dropping it.
     *
     * @param  array<string, mixed>  $entry
     */
    private function entryEventKey(array $entry): ?string
    {
        $parts = [];

        foreach ($entry['changes'] ?? [] as $change) {
            $value = $change['value'] ?? [];

            foreach ($value['messages'] ?? [] as $message) {
                if (! empty($message['id'])) {
                    $parts[] = 'm:'.$message['id'];
                }
            }

            foreach ($value['statuses'] ?? [] as $status) {
                if (! empty($status['id'])) {
                    // A message moves sent → delivered → read; key on id + status
                    // so each transition is processed but re-deliveries dedupe.
                    $parts[] = 's:'.$status['id'].':'.($status['status'] ?? '');
                }
            }
        }

        if ($parts !== []) {
            sort($parts);

            return hash('sha256', implode('|', $parts));
        }

        // Non-message events (template / account / quality updates): hash the
        // change payload so identical re-deliveries dedupe but distinct events
        // do not collide. entry.id is included only as a namespace prefix.
        $blob = json_encode($entry['changes'] ?? [], JSON_UNESCAPED_UNICODE);
        if ($blob === false || $blob === '[]' || $blob === 'null') {
            return null;
        }

        return ($entry['id'] ?? 'waba').':'.hash('sha256', $blob);
    }

    /**
     * Verify the X-Hub-Signature-256 header using timing-safe comparison.
     * Aborts with 401 on mismatch.
     */
    private function verifyHmacSignature(Request $request, string $appSecret): void
    {
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        $received = $request->header('X-Hub-Signature-256', '');

        if (! hash_equals($expected, $received)) {
            Log::warning('whatsapp.webhook.signature_mismatch', [
                'ip'       => $request->ip(),
                'path'     => $request->path(),
                'object'   => $request->input('object'),
                'body_len' => strlen($request->getContent()),
                'expected' => substr($expected, 0, 20) . '…',
                'received' => substr($received, 0, 20) . '…',
            ]);
            abort(401, 'Invalid signature');
        }
    }
}
