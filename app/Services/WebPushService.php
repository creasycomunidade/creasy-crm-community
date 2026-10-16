<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private ?WebPush $webPush = null;

    /** Whether VAPID is configured and valid — web push is skipped when false. */
    private bool $enabled = false;

    public function __construct()
    {
        $publicKey = config('webpush.vapid_public_key');
        $privateKey = config('webpush.vapid_private_key');

        // No keys configured — web push simply isn't set up. Stay silent so we
        // don't spam logs on every notification; other channels still work.
        if (empty($publicKey) || empty($privateKey)) {
            return;
        }

        // Keys are present but may be malformed (e.g. a truncated or placeholder
        // VAPID key). The WebPush constructor validates and throws — catch it so
        // a misconfiguration can never break the notification pipeline.
        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
            $this->enabled = true;
        } catch (\Throwable $e) {
            Log::warning('Web push disabled: invalid VAPID configuration. Regenerate keys with `php artisan webpush:vapid` and set VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a push notification to all subscriptions of the given user.
     * No-op when VAPID is not configured or invalid.
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        ?string $url = null,
        ?string $tag = null,
    ): void
    {
        if (! $this->enabled || ! $this->webPush) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh_key,
                'authToken' => $sub->auth_key,
            ]);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'tag' => $tag,
            ]);

            $this->webPush->queueNotification($subscription, $payload);
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getRequest()->getUri())->delete();
            }
        }
    }
}
