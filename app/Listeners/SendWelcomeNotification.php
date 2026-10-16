<?php

namespace App\Listeners;

use App\Notifications\UserWelcomeNotification;
use App\Services\Mail\MailService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class SendWelcomeNotification
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        try {
            app(MailService::class)->sendWithTemplate('welcome', $user->email, [
                'app_name'  => config('app.name'),
                'user_name' => $user->name,
                'login_url' => route('login'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SendWelcomeNotification: mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        try {
            $user->notify(new UserWelcomeNotification);
        } catch (\Throwable $e) {
            Log::warning('SendWelcomeNotification: in-app notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
