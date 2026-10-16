<?php

namespace App\Notifications;

use App\Modules\Shared\Models\Conversation;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ConversationHandoverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $reason = 'user_request',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', OneSignalChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $contact = $this->conversation->contact;
        $name = $contact ? trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) : 'Unknown';

        return [
            'type' => 'handover',
            'conversation_id' => $this->conversation->id,
            'contact_name' => $name ?: ($contact?->phone_e164 ?? 'Unknown'),
            'reason' => $this->reason,
            'message' => "AI handed over conversation with {$name} to a human agent.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toOneSignal(object $notifiable): array
    {
        $contact = $this->conversation->contact;
        $name = $contact ? trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) : 'Unknown';
        $name = $name ?: ($contact?->phone_e164 ?? 'Unknown');

        return [
            'title' => 'AI handover — action needed',
            'body' => "Conversation with {$name} needs human attention",
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }
}
