<?php

namespace App\Notifications;

use App\Notifications\Channels\OneSignalChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(private string $downloadUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database', OneSignalChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('mail.workspace.export-ready', [
                'name' => $notifiable->name ?? 'there',
                'downloadUrl' => $this->downloadUrl,
            ])
            ->subject('Sua exportação de dados está pronta');
    }

    public function toOneSignal(object $notifiable): array
    {
        return [
            'title' => 'Exportação pronta',
            'body' => 'A exportação de dados do seu espaço está pronta para download.',
            'url' => $this->downloadUrl,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'workspace_export_ready',
            'download_url' => $this->downloadUrl,
            'message' => 'A exportação de dados do seu espaço está pronta para download.',
        ];
    }
}
