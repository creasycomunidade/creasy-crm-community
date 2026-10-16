<?php

namespace App\Modules\Appointments\Jobs;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentChannelNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAppointmentRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(AppointmentChannelNotifier $channelNotifier): void
    {
        Appointment::query()
            ->with(['service:id,name', 'resource:id,name,email'])
            ->whereNotIn('status', ['completed', 'cancelled', 'no_show'])
            ->where('starts_at', '>', now())
            ->where(function ($query) {
                $query->where(function ($customer) {
                    $customer->whereNotNull('customer_reminder_at')
                        ->whereNull('customer_reminder_sent_at')
                        ->where('customer_reminder_at', '<=', now());
                })->orWhere(function ($professional) {
                    $professional->whereNotNull('professional_reminder_at')
                        ->whereNull('professional_reminder_sent_at')
                        ->where('professional_reminder_at', '<=', now());
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($channelNotifier): void {
                foreach ($appointments as $appointment) {
                    $this->sendCustomerReminder($appointment, $channelNotifier);
                    $this->sendProfessionalReminder($appointment);
                }
            });
    }

    private function sendCustomerReminder(Appointment $appointment, AppointmentChannelNotifier $channelNotifier): void
    {
        if (! $appointment->customer_reminder_at || $appointment->customer_reminder_sent_at || $appointment->customer_reminder_at->isFuture()) {
            return;
        }
        if ($appointment->customer_email) {
            $when = $appointment->starts_at->timezone($appointment->timezone)->format('d/m/Y \à\s H:i');
            Mail::raw(
                "Olá, {$appointment->customer_name}.\n\nLembrete: seu atendimento de {$appointment->service?->name} está marcado para {$when}.\n\nSe precisar reagendar, entre em contato com a empresa.",
                fn ($message) => $message->to($appointment->customer_email)->subject('Lembrete do seu atendimento')
            );
        }
        $channelNotifier->send($appointment, 'reminder');
        $appointment->forceFill(['customer_reminder_sent_at' => now()])->save();
    }

    private function sendProfessionalReminder(Appointment $appointment): void
    {
        if (! $appointment->professional_reminder_at || $appointment->professional_reminder_sent_at || $appointment->professional_reminder_at->isFuture()) {
            return;
        }
        if ($appointment->resource?->email) {
            $when = $appointment->starts_at->timezone($appointment->timezone)->format('d/m/Y \à\s H:i');
            Mail::raw(
                "Olá, {$appointment->resource->name}.\n\nVocê tem um atendimento de {$appointment->service?->name} com {$appointment->customer_name} em {$when}.",
                fn ($message) => $message->to($appointment->resource->email)->subject('Próximo atendimento agendado')
            );
        }
        $appointment->forceFill(['professional_reminder_sent_at' => now()])->save();
    }
}
