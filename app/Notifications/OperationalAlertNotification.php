<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationalAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $severity = 'error',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("[CREASY CRM] {$this->title}");
        if ($this->severity === 'error') {
            $mail->error();
        }

        return $mail
            ->line($this->message)
            ->line('Horário: '.now()->format('d/m/Y H:i:s T'))
            ->action('Abrir administração', url('/admin'))
            ->line('Este alerta não inclui conteúdo de clientes nem credenciais.');
    }
}
