<?php

namespace App\Notifications\Channels;

use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(private WebPushService $service) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $data = $notification->toWebPush($notifiable);
        $title = $data['title'] ?? 'Notification';
        $body = $data['body'] ?? '';
        $url = $data['url'] ?? null;
        $tag = $data['tag'] ?? null;

        $this->service->sendToUser($notifiable->id, $title, $body, $url, $tag);
    }
}
