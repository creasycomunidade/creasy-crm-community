<?php

namespace App\Listeners;

use App\Events\CampaignCompleted;
use App\Models\User;
use App\Notifications\CampaignCompletedNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class SendCampaignCompletedNotification
{
    public function handle(CampaignCompleted $event): void
    {
        $campaign = $event->campaign;
        if (! Cache::add("notif_campaign_completed:{$campaign->id}", 1, 300)) {
            return;
        }

        $workspaceId = $campaign->workspace_id;

        if (! $workspaceId) {
            return;
        }

        $recipients = User::forWorkspace($workspaceId)->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new CampaignCompletedNotification($campaign));
    }
}
