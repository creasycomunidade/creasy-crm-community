<?php

namespace App\Notifications;

use App\Models\NotificationPreference;
use App\Modules\Automation\Models\AutomationRun;
use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutomationFailedNotification extends QueuedNotification
{
    use Queueable;

    public function __construct(
        public readonly AutomationRun $run,
        public readonly string $errorMessage,
    ) {}

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
            'type' => 'automation_failed',
            'run_id' => $this->run->id,
            'automation' => $this->run->automation?->name,
            'error' => $this->errorMessage,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->run->automation?->name ?? 'Desconhecida';

        return (new MailMessage)
            ->subject("Falha na automação \"{$name}\"")
            ->line('A execução da automação falhou com o seguinte erro:')
            ->line($this->errorMessage);
    }

    public function toOneSignal(object $notifiable): array
    {
        $name = $this->run->automation?->name ?? 'Desconhecida';

        return [
            'title' => 'Falha na automação',
            'body' => "\"{$name}\" — {$this->errorMessage}",
        ];
    }

    private function isEnabled(object $notifiable, string $channel): bool
    {
        $pref = NotificationPreference::where('user_id', $notifiable->id)
            ->where('event', 'automation_failed')
            ->where('channel', $channel)
            ->first();

        return $pref === null || $pref->enabled;
    }
}
