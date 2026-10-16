<?php

namespace App\Modules\Appointments\Jobs;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Services\AppointmentChannelNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAppointmentChannelNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $appointmentId, public readonly string $event) {}

    public function handle(AppointmentChannelNotifier $notifier): void
    {
        $appointment = Appointment::find($this->appointmentId);
        if ($appointment) {
            $notifier->send($appointment, $this->event);
        }
    }
}
