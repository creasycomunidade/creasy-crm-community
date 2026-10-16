<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    private string $appId;

    private string $restApiKey;

    private const API_URL = 'https://onesignal.com/api/v1/notifications';

    public function __construct()
    {
        $this->appId = (string) config('services.onesignal.app_id', '');
        $this->restApiKey = (string) config('services.onesignal.rest_api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->restApiKey !== '';
    }

    /**
     * Send a push notification to a user identified by their Laravel user ID.
     */
    public function sendToUser(int|string $userId, string $title, string $body, ?string $url = null, int|string|null $conversationId = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $payload = [
            'app_id'                        => $this->appId,
            'include_external_user_ids'     => [(string) $userId],
            'channel_for_external_user_ids' => 'push',
            'headings'                      => ['en' => $title],
            'contents'                      => ['en' => $body],
            'ios_badgeType'                 => 'Increase',
            'ios_badgeCount'                => 1,
            'chrome_web_icon'               => $this->notificationIconUrl(),
            'chrome_web_badge'              => $this->notificationIconUrl(),
            'firefox_icon'                  => $this->notificationIconUrl(),
        ];

        if ($url) {
            $payload['url'] = $url;
        }

        // Collapse multiple notifications for the same conversation into one.
        if ($conversationId !== null) {
            $payload['collapse_id']             = "conversation-{$conversationId}";
            $payload['web_push_topic']          = "conversation-{$conversationId}";
            $payload['data']                    = ['conversation_id' => $conversationId, 'url' => $url];
        }

        $response = Http::withToken($this->restApiKey)
            ->post(self::API_URL, $payload);

        if (! $response->successful()) {
            Log::warning('OneSignal notification failed', [
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }
    }

    /**
     * Send to multiple users at once.
     *
     * @param  array<int|string>  $userIds
     */
    public function sendToUsers(array $userIds, string $title, string $body, ?string $url = null): void
    {
        if (! $this->isConfigured() || empty($userIds)) {
            return;
        }

        $payload = [
            'app_id' => $this->appId,
            'include_external_user_ids' => array_map('strval', $userIds),
            'channel_for_external_user_ids' => 'push',
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
            'chrome_web_icon' => $this->notificationIconUrl(),
            'chrome_web_badge' => $this->notificationIconUrl(),
            'firefox_icon' => $this->notificationIconUrl(),
        ];

        if ($url) {
            $payload['url'] = $url;
        }

        $response = Http::withToken($this->restApiKey)
            ->post(self::API_URL, $payload);

        if (! $response->successful()) {
            Log::warning('OneSignal batch notification failed', [
                'user_ids' => $userIds,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function notificationIconUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/creasy-community-icon.svg';
    }
}
