<?php

namespace App\Modules\Appointments\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentAvailability;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Appointments\Models\AppointmentSetting;
use Illuminate\Support\Carbon;

class AppointmentAvailabilityService
{
    public function ensureAvailable(AppointmentResource $resource, Carbon $start, Carbon $end, ?int $ignoreAppointmentId = null): void
    {
        abort_unless($resource->booking_enabled || $ignoreAppointmentId, 422, 'A agenda deste profissional ou recurso está fechada para novos agendamentos.');

        $localStart = $start->copy()->timezone($resource->timezone);
        $localEnd = $end->copy()->timezone($resource->timezone);
        $windows = AppointmentAvailability::query()
            ->where('workspace_id', $resource->workspace_id)
            ->where('resource_id', $resource->id)
            ->where('weekday', $localStart->dayOfWeek)
            ->get();

        if ($windows->isNotEmpty()) {
            $insideWindow = $windows->contains(fn (AppointmentAvailability $window) => $localStart->format('H:i:s') >= $window->starts_at
                && $localEnd->format('H:i:s') <= $window->ends_at
            );
            abort_unless($insideWindow, 422, 'O horário está fora da disponibilidade do profissional ou recurso.');
        }

        $conflict = Appointment::query()
            ->where('resource_id', $resource->id)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->exists();

        abort_if($conflict, 422, 'Este horário já está ocupado para o profissional ou recurso selecionado.');
    }

    /** @return list<string> */
    public function slots(AppointmentResource $resource, AppointmentService $service, Carbon $date): array
    {
        if (! $resource->booking_enabled) {
            return [];
        }

        $settings = AppointmentSetting::forWorkspace((int) $resource->workspace_id);
        $interval = max(5, (int) $settings->slot_interval_minutes);
        $duration = max(5, $service->duration_minutes + $service->buffer_minutes);
        $localDate = $date->copy()->timezone($resource->timezone)->startOfDay();

        if ($localDate->lt(now($resource->timezone)->startOfDay())) {
            return [];
        }

        $windows = AppointmentAvailability::query()
            ->where('workspace_id', $resource->workspace_id)
            ->where('resource_id', $resource->id)
            ->where('weekday', $localDate->dayOfWeek)
            ->orderBy('starts_at')
            ->get();
        if ($windows->isEmpty()) {
            return [];
        }

        $appointments = Appointment::query()
            ->where('resource_id', $resource->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$localDate->copy()->utc(), $localDate->copy()->endOfDay()->utc()])
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        foreach ($windows as $window) {
            $cursor = Carbon::parse($localDate->format('Y-m-d').' '.$window->starts_at, $resource->timezone);
            $windowEnd = Carbon::parse($localDate->format('Y-m-d').' '.$window->ends_at, $resource->timezone);
            while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                $slotEnd = $cursor->copy()->addMinutes($duration);
                $busy = $appointments->contains(fn (Appointment $appointment) => $appointment->starts_at->lt($slotEnd->copy()->utc())
                    && $appointment->ends_at->gt($cursor->copy()->utc())
                );
                if (! $busy && $cursor->isFuture()) {
                    $slots[] = $cursor->format('H:i');
                }
                $cursor->addMinutes($interval);
            }
        }

        return array_values(array_unique($slots));
    }
}
