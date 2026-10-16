<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Jobs\SendAppointmentChannelNotificationJob;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Appointments\Models\AppointmentSetting;
use App\Modules\Appointments\Services\AppointmentAvailabilityService;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Services\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        try {
            $month = Carbon::createFromFormat('Y-m', (string) $request->query('month', now()->format('Y-m')), 'America/Sao_Paulo')->startOfMonth();
        } catch (\Throwable) {
            $month = now('America/Sao_Paulo')->startOfMonth();
        }
        $calendarStart = $month->copy()->startOfWeek()->utc();
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek()->utc();

        return Inertia::render('Appointments/Index', [
            'services' => AppointmentService::where('workspace_id', $wid)->orderBy('name')->get(),
            'resources' => AppointmentResource::where('workspace_id', $wid)->orderBy('name')->get(),
            'appointments' => Appointment::where('workspace_id', $wid)
                ->with(['service:id,uuid,name,color', 'resource:id,uuid,name'])
                ->whereBetween('starts_at', [$calendarStart, $calendarEnd])->orderBy('starts_at')->limit(500)->get(),
            'calendarMonth' => $month->format('Y-m'),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        AppointmentService::create(array_merge($data, ['workspace_id' => $this->workspaceId($request), 'currency' => 'BRL', 'active' => true]));

        return back()->with('success', 'Serviço criado.');
    }

    public function storeResource(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        AppointmentResource::create(array_merge($data, ['workspace_id' => $this->workspaceId($request), 'timezone' => 'America/Sao_Paulo', 'active' => true]));

        return back()->with('success', 'Profissional ou recurso criado.');
    }

    public function store(Request $request, AppointmentAvailabilityService $availability, ContactService $contacts): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $data = $request->validate([
            'service_uuid' => ['required', 'uuid'],
            'resource_uuid' => ['required', 'uuid'],
            'contact_uuid' => ['nullable', 'uuid'],
            'customer_name' => ['required', 'string', 'max:180'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'starts_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_reminder_minutes' => ['nullable', 'integer', 'in:0,60,120,1440,2880'],
            'professional_reminder_minutes' => ['nullable', 'integer', 'in:0,60,120,1440,2880'],
            'contact_uuid' => ['nullable', 'uuid'],
        ]);
        $service = AppointmentService::where('workspace_id', $wid)->where('active', true)->where('uuid', $data['service_uuid'])->firstOrFail();
        $resource = AppointmentResource::where('workspace_id', $wid)->where('active', true)->where('uuid', $data['resource_uuid'])->firstOrFail();
        abort_if($resource->services()->exists() && ! $resource->services()->whereKey($service->id)->exists(), 422, 'Este profissional não realiza o serviço selecionado.');
        $start = Carbon::parse($data['starts_at'], $resource->timezone)->utc();
        $settings = AppointmentSetting::forWorkspace($wid);
        abort_if($start->gt(now($resource->timezone)->addDays($settings->booking_horizon_days)->utc()), 422, "Agendamentos podem ser criados com até {$settings->booking_horizon_days} dias de antecedência.");
        $end = $start->copy()->addMinutes($service->duration_minutes + $service->buffer_minutes);
        $phone = $this->normalisePhone($data['customer_phone'] ?? null);
        $contactId = ! empty($data['contact_uuid'])
            ? Contact::where('workspace_id', $wid)->where('uuid', $data['contact_uuid'])->value('id')
            : null;
        if (! $contactId && ($phone || ! empty($data['customer_email']))) {
            $names = preg_split('/\s+/u', trim($data['customer_name']), 2) ?: [];
            $contactId = $contacts->upsert($wid, [
                'phone_e164' => $phone,
                'email' => $data['customer_email'] ?? null,
                'first_name' => $names[0] ?? $data['customer_name'],
                'last_name' => $names[1] ?? null,
                'source' => 'agenda',
            ], false)->id;
        }

        $appointment = DB::transaction(function () use ($wid, $service, $resource, $data, $start, $end, $contactId, $phone, $availability): Appointment {
            $availability->ensureAvailable($resource, $start, $end);

            return Appointment::create([
                'workspace_id' => $wid,
                'service_id' => $service->id,
                'resource_id' => $resource->id,
                'contact_id' => $contactId,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $phone,
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $resource->timezone,
                'notes' => $data['notes'] ?? null,
                'status' => 'scheduled',
                'source' => 'panel',
                'customer_reminder_at' => ! empty($data['customer_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['customer_reminder_minutes']) : null,
                'professional_reminder_at' => ! empty($data['professional_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['professional_reminder_minutes']) : null,
            ]);
        });
        SendAppointmentChannelNotificationJob::dispatch($appointment->id, 'confirmation')->afterCommit();

        return back()->with('success', 'Agendamento criado.');
    }

    public function update(Request $request, Appointment $appointment, AppointmentAvailabilityService $availability): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        abort_unless((int) $appointment->workspace_id === $wid, 403);

        $data = $request->validate([
            'service_uuid' => ['required', 'uuid'],
            'resource_uuid' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:180'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'starts_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_reminder_minutes' => ['nullable', 'integer', 'in:0,60,120,1440,2880'],
            'professional_reminder_minutes' => ['nullable', 'integer', 'in:0,60,120,1440,2880'],
        ]);

        $service = AppointmentService::where('workspace_id', $wid)
            ->where('active', true)->where('uuid', $data['service_uuid'])->firstOrFail();
        $resource = AppointmentResource::where('workspace_id', $wid)
            ->where('active', true)->where('uuid', $data['resource_uuid'])->firstOrFail();
        $contactId = ! empty($data['contact_uuid'])
            ? Contact::where('workspace_id', $wid)->where('uuid', $data['contact_uuid'])->value('id')
            : null;
        abort_if(! empty($data['contact_uuid']) && ! $contactId, 422, 'O cliente selecionado não pertence a esta empresa.');
        abort_if($resource->services()->exists() && ! $resource->services()->whereKey($service->id)->exists(), 422, 'Este profissional não realiza o serviço selecionado.');
        $start = Carbon::parse($data['starts_at'], $resource->timezone)->utc();
        $end = $start->copy()->addMinutes($service->duration_minutes + $service->buffer_minutes);
        $wasRescheduled = ! $appointment->starts_at->equalTo($start);

        DB::transaction(function () use ($appointment, $service, $resource, $contactId, $data, $start, $end, $availability): void {
            $availability->ensureAvailable($resource, $start, $end, $appointment->id);
            $appointment->update([
                'service_id' => $service->id,
                'resource_id' => $resource->id,
                'contact_id' => $contactId,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $this->normalisePhone($data['customer_phone'] ?? null),
                'starts_at' => $start,
                'ends_at' => $end,
                'timezone' => $resource->timezone,
                'notes' => $data['notes'] ?? null,
                'customer_reminder_at' => ! empty($data['customer_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['customer_reminder_minutes']) : null,
                'professional_reminder_at' => ! empty($data['professional_reminder_minutes']) ? $start->copy()->subMinutes((int) $data['professional_reminder_minutes']) : null,
                'customer_reminder_sent_at' => null,
                'professional_reminder_sent_at' => null,
            ]);
        });
        if ($wasRescheduled) {
            SendAppointmentChannelNotificationJob::dispatch($appointment->id, 'rescheduled')->afterCommit();
        }

        return back()->with('success', 'Agendamento atualizado.');
    }

    public function status(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($appointment->workspace_id === $this->workspaceId($request), 403);
        $data = $request->validate([
            'status' => ['required', 'in:scheduled,confirmed,completed,cancelled,no_show'],
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $appointment->update($data);
        if ($data['status'] === 'cancelled') {
            SendAppointmentChannelNotificationJob::dispatch($appointment->id, 'cancelled')->afterCommit();
        }

        return back()->with('success', 'Status do agendamento atualizado.');
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        return '+'.$digits;
    }
}
