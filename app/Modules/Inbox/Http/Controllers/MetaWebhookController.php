<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Concerns\FlushesWebhookResponse;
use App\Http\Controllers\Controller;
use App\Modules\Inbox\Jobs\ProcessInboundInboxMessageJob;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    use FlushesWebhookResponse;

    public function verify(Request $request, string $token): Response
    {
        $meta = CredentialResolver::system()->meta();
        $verifyToken = $meta?->verifyToken() ?? '';

        if ($verifyToken !== ''
            && hash_equals($verifyToken, (string) $token)
            && $request->input('hub_mode') === 'subscribe') {
            return response($request->input('hub_challenge', ''), 200);
        }

        abort(403);
    }

    public function receive(Request $request, string $token): JsonResponse
    {
        $meta = CredentialResolver::system()->meta();
        $verifyToken = $meta?->verifyToken() ?? '';

        if ($verifyToken === '' || ! hash_equals($verifyToken, (string) $token)) {
            Log::warning('meta.webhook.invalid_token', ['ip' => $request->ip()]);
            abort(403);
        }

        $appSecret = $meta->appSecret();
        if ($appSecret) {
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
            if (! hash_equals($expected, $request->header('X-Hub-Signature-256', ''))) {
                Log::warning('meta.webhook.signature_mismatch', ['ip' => $request->ip()]);
                abort(401, 'Invalid signature');
            }
        } elseif (app()->environment('production')) {
            Log::critical('meta.webhook.no_secret', ['ip' => $request->ip()]);
            abort(401, 'App secret not configured');
        } else {
            Log::warning('meta.webhook.unsigned', ['ip' => $request->ip()]);
        }

        $object = $request->input('object', '');

        Log::info('meta.webhook.received', [
            'object'      => $object,
            'entry_count' => count($request->input('entry', [])),
            'entry_ids'   => collect($request->input('entry', []))->pluck('id')->filter()->values()->all(),
        ]);

        if (! in_array($object, ['instagram', 'page'], true)) {
            Log::info('meta.webhook.ignored_object', ['object' => $object]);

            return response()->json(['status' => 'ok']);
        }

        // Deduplicate per actual event, NOT on entry.id. For instagram/page,
        // entry.id is the account/page id — identical for every webhook from that
        // account — so keying on it would discard every message after the first.
        // (Same trap the WhatsApp controller documents in entryEventKey().)
        $idempotency = app(WebhookIdempotencyService::class);
        $newEntries  = [];
        foreach ($request->input('entry', []) as $entry) {
            $eventKey = $this->entryEventKey($entry);
            if ($eventKey === null || $idempotency->isNewEvent('meta_' . $object, $eventKey)) {
                $newEntries[] = $entry;
            }
        }

        if (empty($newEntries)) {
            Log::info('meta.webhook.all_entries_duplicate', ['object' => $object]);

            return response()->json(['status' => 'ok']);
        }

        Log::info('meta.webhook.dispatching', [
            'object'          => $object,
            'new_entry_count' => count($newEntries),
        ]);

        $payload = array_merge($request->all(), ['entry' => $newEntries]);

        return $this->flushWebhookOkThen(function () use ($payload, $object): void {
            ProcessInboundInboxMessageJob::dispatch($payload, $object)->onQueue('whatsapp');
            if (collect($payload['entry'] ?? [])->contains(fn ($entry) => ! empty($entry['changes']))) {
            }
        });
    }

    /**
     * Build a stable idempotency key for an instagram/page webhook entry from the
     * events it actually carries (message / reaction / read ids) rather than from
     * entry.id, which is the account/page id and therefore constant across every
     * webhook for that account.
     *
     * Returns null when no identifiable event is present so the caller processes
     * the entry (fail-open) instead of silently dropping it.
     *
     * @param  array<string, mixed>  $entry
     */
    private function entryEventKey(array $entry): ?string
    {
        $ids = [];

        foreach ($entry['messaging'] ?? [] as $event) {
            $mid = $event['message']['mid']
                ?? $event['reaction']['mid']
                ?? $event['read']['mid']
                ?? $event['postback']['mid']
                ?? null;
            if ($mid) {
                $ids[] = $mid;
            }
        }

        if ($ids !== []) {
            sort($ids);

            return hash('sha256', implode('|', $ids));
        }

        // No message-level id (e.g. changes-based events): hash the event-bearing
        // payload so exact re-deliveries dedupe but distinct events do not collide.
        // entry.id is deliberately excluded as a dedup discriminator.
        $blob = json_encode([
            'messaging' => $entry['messaging'] ?? [],
            'changes'   => $entry['changes'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        if ($blob === false || $blob === '{"messaging":[],"changes":[]}') {
            return null;
        }

        return hash('sha256', $blob);
    }
}
