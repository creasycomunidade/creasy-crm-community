<?php

namespace App\Notifications;

use App\Models\User;
use App\Modules\Shared\Models\Conversation;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ConversationAssignedNotification extends QueuedNotification
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', OneSignalChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'conversation_assigned',
            'conversation_id' => $this->conversation->id,
            'assigned_by' => $this->assignedBy?->name,
            'contact_name' => $this->conversation->contact?->name ?? 'Unknown',
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toOneSignal(object $notifiable): array
    {
        $by = $this->assignedBy?->name ?? 'Someone';
        $contact = $this->conversation->contact?->name ?? 'Unknown';

        return [
            'title' => 'Conversation assigned to you',
            'body' => "{$by} assigned a conversation with {$contact}",
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }
}
