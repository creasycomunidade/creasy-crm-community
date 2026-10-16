<?php

namespace App\Listeners;

use App\Events\ConversationAssigned;
use App\Notifications\ConversationAssignedNotification;

class SendConversationAssignedNotification
{
    public function handle(ConversationAssigned $event): void
    {
        if (! $event->assignedTo) {
            return;
        }

        $event->assignedTo->notify(
            new ConversationAssignedNotification($event->conversation, null),
        );
    }
}
