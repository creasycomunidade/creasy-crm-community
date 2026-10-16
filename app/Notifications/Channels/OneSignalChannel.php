<?php

namespace App\Notifications\Channels;

use App\Services\OneSignalService;
use Illuminate\Notifications\Notification;

class OneSignalChannel
{
    public function __construct(private OneSignalService $service) {}

    public function isConfigured(): bool
    {
        return $this->service->isConfigured();
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toOneSignal')) {
            return;
        }

        if (! $this->service->isConfigured()) {
            return;
        }

        $data           = $notification->toOneSignal($notifiable);
        $title          = $data['title'] ?? 'Notification';
        $body           = $data['body'] ?? '';
        $url            = $data['url'] ?? null;
        $conversationId = $data['conversation_id'] ?? null;

        $this->service->sendToUser($notifiable->id, $title, $body, $url, $conversationId);
    }
}
