<?php

namespace App\Listeners;

use App\Events\AutomationFailed;
use App\Models\User;
use App\Notifications\AutomationFailedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendAutomationFailedNotification
{
    public function handle(AutomationFailed $event): void
    {
        if (! Cache::add("notif_automation_failed:{$event->run->id}", 1, 300)) {
            return;
        }

        $workspaceId = $event->run->automation->workspace_id ?? null;

        if (! $workspaceId) {
            return;
        }

        $recipients = User::forWorkspace($workspaceId)->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new AutomationFailedNotification($event->run, $event->errorMessage));
    }
}
