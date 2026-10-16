<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Modules\Appointments\Jobs\SendAppointmentChannelNotificationJob;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Appointments\Models\AppointmentSetting;
use App\Modules\Appointments\Services\AppointmentAvailabilityService;
use App\Modules\Shared\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicAppointmentController extends Controller
{
    public function show(Request $request, string $token, ?string $resourceUuid = null): Response
    {
        $settings = $this->settings($token);
        $workspace = Workspace::findOrFail($settings->workspace_id);
        $resourceQuery = AppointmentResource::query()
            ->where('workspace_id', $settings->workspace_id)
            ->where('active', true)
            ->with(['services' => fn ($query) => $query->where('active', true)->select('appointment_services.id', 'uuid', 'name')])
            ->orderBy('name');

        $selectedResource = null;
        if ($resourceUuid) {
            $selectedResource = (clone $resourceQuery)->where('uuid', $resourceUuid)->firstOrFail();
        }

        $resources = $selectedResource
            ? collect([$selectedResource])
            : $resourceQuery->where('booking_enabled', true)->get();

        return Inertia::render('Appointments/PublicBooking', [
            'workspace' => ['name' => $workspace->name],
            'resources' => $resources->map(fn (AppointmentResource $resource) => [
                'uuid' => $resource->uuid,
                'name' => $resource->name,
                'position' => $resource->position,
                'booking_enabled' => $resource->booking_enabled,
                'service_uuids' => $resource->services->pluck('uuid')->values(),
            ])->values(),
            'services' => AppointmentService::query()
                ->where('workspace_id', $settings->workspace_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(['uuid', 'name', 'description', 'duration_minutes', 'price', 'currency']),
            'selectedResourceUuid' => $selectedResource?->uuid,
            'bookingHorizonDays' => $settings->booking_horizon_days,
            'publicToken' => $token,
            'embedded' => $request->boolean('embed'),
        ]);
    }

    public function slots(Request $request, string $token, AppointmentAvailabilityService $availability): JsonResponse
    {
        $settings = $this->settings($token);
        $data = $request->validate([
            'resource_uuid' => ['required', 'uuid'],
            'service_uuid' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $resource = AppointmentResource::where('workspace_id', $settings->workspace_id)
            ->where('active', true)->where('booking_enabled', true)->where('uuid', $data['resource_uuid'])->firstOrFail();
        $service = AppointmentService::where('workspace_id', $settings->workspace_id)
            ->where('active', true)->where('uuid', $data['service_uuid'])->firstOrFail();
        abort_if($resource->services()->exists() && ! $resource->services()->whereKey($service->id)->exists(), 422, 'Este profissional não realiza o serviço selecionado.');

        return response()->json([
            'slots' => $availability->slots($resource, $service, Carbon::parse($data['date'], $resource->timezone)),
        ]);
    }

    public function store(
        Request $request,
        string $token,
        AppointmentAvailabilityService $availability,
        ContactService $contacts,
    ): JsonResponse {
        $settings = $this->settings($token);
        $data = $request->validate([
            'resource_uuid' => ['required', 'uuid'],
            'service_uuid' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:180'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'],
        ]);
        $workspaceId = (int) $settings->workspace_id;
        $resource = AppointmentResource::where('workspace_id', $workspaceId)
            ->where('active', true)->where('booking_enabled', true)->where('uuid', $data['resource_uuid'])->firstOrFail();
        $service = AppointmentService::where('workspace_id', $workspaceId)
            ->where('active', true)->where('uuid', $data['service_uuid'])->firstOrFail();
        abort_if($resource->services()->exists() && ! $resource->services()->whereKey($service->id)->exists(), 422, 'Este profissional não realiza o serviço selecionado.');

        $start = Carbon::parse($data['date'].' '.$data['time'], $resource->timezone)->utc();
        abort_unless($start->isFuture(), 422, 'Escolha um horário futuro.');
        abort_if($start->gt(now($resource->timezone)->addDays($settings->booking_horizon_days)->utc()), 422, "Agendamentos podem ser criados com até {$settings->booking_horizon_days} dias de antecedência.");
        $end = $start->copy()->addMinutes($service->duration_minutes + $service->buffer_minutes);
        $phone = $this->normalisePhone($data['customer_phone']);
        $names = preg_split('/\s+/u', trim($data['customer_name']), 2) ?: [];
        $contact = $contacts->upsert($workspaceId, [
            'phone_e164' => $phone,
            'email' => $data['customer_email'] ?? null,
            'first_name' => $names[0] ?? $data['customer_name'],
            'last_name' => $names[1] ?? null,
            'source' => 'agenda_publica',
        ], false);

        $appointment = DB::transaction(function () use ($workspaceId, $resource, $service, $data, $start, $end, $contact, $phone, $availability): Appointment {
            AppointmentResource::whereKey($resource->id)->lockForUpdate()->firstOrFail();
            $resource->refresh();
            $availability->ensureAvailable($resource, $start, $end);

            return Appointment::create([
                'workspace_id' => $workspaceId,
                'service_id' => $service->id,
                'resource_id' => $resource->id,
                'contact_id' => $contact->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $phone,
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $resource->timezone,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'source' => 'public_booking',
            ]);
        });
        SendAppointmentChannelNotificationJob::dispatch($appointment->id, 'confirmation')->afterCommit();

        return response()->json([
            'message' => 'Agendamento realizado com sucesso.',
            'reference' => $appointment->reference,
            'starts_at' => $appointment->starts_at->timezone($resource->timezone)->format('d/m/Y H:i'),
        ], 201);
    }

    private function settings(string $token): AppointmentSetting
    {
        abort_unless(strlen($token) === 48, 404);

        return AppointmentSetting::where('public_token', $token)->firstOrFail();
    }

    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        abort_unless(strlen($digits) >= 10, 422, 'Informe um telefone válido com DDD.');
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        return '+'.$digits;
    }
}
