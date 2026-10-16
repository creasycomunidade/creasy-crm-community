<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deduplicates inbound webhooks by (provider, event_id).
 * Returns true if this event has not been seen before (should be processed),
 * false if it was already recorded (duplicate, return 200 and skip).
 */
class WebhookIdempotencyService
{
    public function isNewEvent(string $provider, string $eventId): bool
    {
        try {
            $affected = DB::table('inbound_webhook_events')->insertOrIgnore([
                'provider' => $provider,
                'event_id' => $eventId,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $affected > 0;
        } catch (\Throwable $e) {
            // Fail open: process the event rather than silently drop it when the DB is unavailable.
            Log::error('WebhookIdempotencyService DB error — processing anyway', [
                'provider' => $provider,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Release a previously-recorded event so it can be processed again.
     *
     * Call this when a handler throws AFTER the event was marked seen, so the gateway's
     * automatic retry is allowed to reprocess it instead of being silently deduped away.
     */
    public function release(string $provider, string $eventId): void
    {
        try {
            DB::table('inbound_webhook_events')
                ->where('provider', $provider)
                ->where('event_id', $eventId)
                ->delete();
        } catch (\Throwable $e) {
            Log::error('WebhookIdempotencyService release failed', [
                'provider' => $provider,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Prune records older than the given number of days (call from scheduler). */
    public function prune(int $days = 30): int
    {
        return DB::table('inbound_webhook_events')
            ->where('received_at', '<', now()->subDays($days))
            ->delete();
    }
}
