<?php

namespace App\Services;

use App\Notifications\OperationalAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class OperationalAlertService
{
    public function check(string $key, bool $healthy, string $title, string $message): void
    {
        $stateKey = "ops:check:{$key}";
        $previous = Cache::get($stateKey);
        Cache::put($stateKey, $healthy ? 'healthy' : 'failed', now()->addDays(30));

        if (! $healthy) {
            $cooldown = max(5, (int) config('operations.alert_cooldown_minutes', 60));
            if (Cache::add("ops:alert-cooldown:{$key}", true, now()->addMinutes($cooldown))) {
                $this->send($title, $message, 'error');
            }
        } elseif ($previous === 'failed') {
            Cache::forget("ops:alert-cooldown:{$key}");
            $this->send('RECUPERADO — '.$title, 'O componente voltou a operar normalmente.', 'success');
        }
    }

    private function send(string $title, string $message, string $severity): void
    {
        $emails = collect(config('operations.alert_emails', []))->filter()->unique()->take(10);
        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(
                new OperationalAlertNotification($title, $message, $severity),
            );
        }
    }
}
