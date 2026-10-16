<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Modules\Broadcasting\Models\Campaign;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignCompletedNotification extends QueuedNotification
{
    use Queueable;

    public function __construct(public readonly Campaign $campaign) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        if ($this->isEnabled($notifiable, 'mail')) {
            $channels[] = 'mail';
        }

        if ($this->isEnabled($notifiable, 'one_signal')) {
            $channels[] = OneSignalChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'campaign_completed',
            'campaign_id' => $this->campaign->id,
            'name' => $this->campaign->name,
            'sent' => $this->campaign->sent_count ?? 0,
            'failed' => $this->campaign->failed_count ?? 0,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Campanha \"{$this->campaign->name}\" concluída")
            ->line("O envio da campanha \"{$this->campaign->name}\" foi concluído.")
            ->line("Enviadas: {$this->campaign->sent_count}; falhas: {$this->campaign->failed_count}");
    }

    public function toOneSignal(object $notifiable): array
    {
        return [
            'title' => 'Campanha concluída',
            'body' => "\"{$this->campaign->name}\" — Enviadas: {$this->campaign->sent_count}; falhas: {$this->campaign->failed_count}",
        ];
    }

    private function isEnabled(object $notifiable, string $channel): bool
    {
        $pref = NotificationPreference::where('user_id', $notifiable->id)
            ->where('event', 'campaign_completed')
            ->where('channel', $channel)
            ->first();

        return $pref === null || $pref->enabled;
    }
}
