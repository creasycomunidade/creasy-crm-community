<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Notifications\Channels\OneSignalChannel;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends QueuedNotification
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly Conversation $conversation,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Email is intentionally not offered for new-message notifications: an
        // email per inbound message is too noisy. Use web push / OneSignal instead.

        // Use exactly one browser-push transport. Sending through OneSignal and
        // native Web Push together creates duplicate OS notifications.
        if ($this->isEnabled($notifiable, 'web_push')) {
            $channels[] = app(OneSignalChannel::class)->isConfigured()
                ? OneSignalChannel::class
                : WebPushChannel::class;
        }

        return $channels;
    }

    /**
     * A recovered queue must not flood users with browser notifications for
     * conversations they received hours ago. Current messages keep their
     * normal database, broadcast and push delivery.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $this->message->created_at
            || $this->message->created_at->greaterThanOrEqualTo(now()->subMinutes(10));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_message',
            'message_id' => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'contact_name' => $this->conversation->contact?->full_name ?? 'Contato',
            'snippet' => mb_substr((string) $this->message->body, 0, 120),
            'url' => route('client.inbox.show', $this->conversation),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Safety net only — via() never returns the 'mail' channel (new-message
     * emails are intentionally disabled as too noisy). This method exists so a
     * stale 'mail' job left in the queue from before that change drains without
     * fataling the worker (queued notifications bake the channel in at dispatch
     * time). It can be removed once no legacy 'mail' jobs remain.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova mensagem de '.($this->conversation->contact?->name ?? 'um contato'))
            ->line('Você recebeu uma nova mensagem na caixa de entrada.')
            ->action('Ver conversa', route('client.inbox.show', $this->conversation));
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Nova mensagem',
            'body' => $this->conversation->contact?->full_name ?? 'Um contato enviou uma mensagem',
            'url' => route('client.inbox.show', $this->conversation),
            'tag' => 'respondesk-conversation-'.$this->conversation->id,
        ];
    }

    public function toOneSignal(object $notifiable): array
    {
        $contact = $this->conversation->contact;
        $name    = trim(implode(' ', array_filter([$contact?->first_name, $contact?->last_name])));
        $channel = ucfirst($this->conversation->channelAccount?->channel ?? 'mensagem');
        $snippet = mb_substr((string) $this->message->body, 0, 100);

        return [
            'title' => $name ?: 'Nova mensagem',
            'body'  => $snippet ?: "Nova mensagem de {$channel}",
            'url'   => route('client.inbox.show', $this->conversation),
            // Extra data so the service worker can collapse duplicate notifications
            // for the same conversation.
            'conversation_id' => $this->conversation->id,
        ];
    }

    private function isEnabled(object $notifiable, string $channel): bool
    {
        $pref = NotificationPreference::where('user_id', $notifiable->id)
            ->where('event', 'new_message')
            ->where('channel', $channel)
            ->first();

        return $pref === null || $pref->enabled;
    }
}
