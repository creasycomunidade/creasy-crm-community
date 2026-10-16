<?php

namespace App\Notifications;

use App\Notifications\Channels\OneSignalChannel;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class QueuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Keep user-facing notifications independent from campaign, integration,
     * social publishing and AI workloads that share the default queue.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
            'database' => 'notifications',
            'broadcast' => 'notifications',
            OneSignalChannel::class => 'notifications',
            WebPushChannel::class => 'notifications',
        ];
    }
}
