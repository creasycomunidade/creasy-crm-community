<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Modules\Shared\Models\Conversation;
use App\Notifications\Channels\OneSignalChannel;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInNoteNotification extends QueuedNotification
{
    use Queueable;

    public function __construct(
        public readonly User $mentionedBy,
        public readonly Conversation $conversation,
        public readonly string $noteBody,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->isEnabled($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        if ($this->isEnabled($notifiable, 'web_push')) {
            $channels[] = WebPushChannel::class;
        }

        if ($this->isEnabled($notifiable, 'one_signal')) {
            $channels[] = OneSignalChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'conversation_id' => $this->conversation->id,
            'mentioned_by' => $this->mentionedBy->name,
            'snippet' => mb_substr($this->noteBody, 0, 120),
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->mentionedBy->name.' mencionou você em uma nota')
            ->line($this->mentionedBy->name.' mencionou você em uma nota da conversa.')
            ->line('"'.mb_substr($this->noteBody, 0, 200).'"')
            ->action('Ver conversa', route('client.inbox.show', $this->conversation));
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => '@menção de '.$this->mentionedBy->name,
            'body' => mb_substr($this->noteBody, 0, 100),
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }

    public function toOneSignal(object $notifiable): array
    {
        return $this->toWebPush($notifiable);
    }

    private function isEnabled(object $notifiable, string $channel): bool
    {
        $pref = NotificationPreference::where('user_id', $notifiable->id)
            ->where('event', 'mention')
            ->where('channel', $channel)
            ->first();

        return $pref === null || $pref->enabled;
    }
}
