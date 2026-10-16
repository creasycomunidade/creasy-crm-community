<?php

namespace App\Mail;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly array $stats,
        public readonly string $period,
        public readonly string $dashboardUrl,
        public readonly string $settingsUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->workspace->name}] Relatório semanal de desempenho — {$this->period}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.weekly-digest',
            with: [
                'workspace' => $this->workspace,
                'stats' => $this->stats,
                'period' => $this->period,
                'dashboardUrl' => $this->dashboardUrl,
                'settingsUrl' => $this->settingsUrl,
            ],
        );
    }
}
