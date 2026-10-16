<?php

namespace App\Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentAvailability;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Appointments\Models\AppointmentSetting;
use App\Modules\Appointments\Services\AppointmentAvailabilityService;
use App\Modules\Inbox\Services\ChannelAccountAccess;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentWorkspaceController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $timezone = AppointmentSetting::forWorkspace($workspaceId)->timezone;
        $todayStart = now($timezone)->startOfDay()->utc();
        $todayEnd = now($timezone)->endOfDay()->utc();
        $monthStart = now($timezone)->startOfMonth()->utc();
        $monthEnd = now($timezone)->endOfMonth()->utc();
        $base = Appointment::where('workspace_id', $workspaceId);
        $upcoming = (clone $base)->where('starts_at', '>=', now())->whereNotIn('status', ['completed', 'cancelled', 'no_show']);

        return Inertia::render('Appointments/Dashboard', [
            'stats' => [
                'today' => (clone $base)->whereBetween('starts_at', [$todayStart, $todayEnd])->count(),
                'upcoming' => (clone $upcoming)->count(),
                'confirmed' => (clone $base)->whereBetween('starts_at', [$monthStart, $monthEnd])->where('status', 'confirmed')->count(),
                'completed' => (clone $base)->whereBetween('starts_at', [$monthStart, $monthEnd])->where('status', 'completed')->count(),
                'cancelled' => (clone $base)->whereBetween('starts_at', [$monthStart, $monthEnd])->whereIn('status', ['cancelled', 'no_show'])->count(),
            ],
            'todayAppointments' => (clone $base)->with(['service:id,uuid,name,color', 'resource:id,uuid,name'])
                ->whereBetween('starts_at', [$todayStart, $todayEnd])->orderBy('starts_at')->get(),
            'nextAppointments' => (clone $upcoming)->with(['service:id,uuid,name,color', 'resource:id,uuid,name'])
                ->orderBy('starts_at')->limit(8)->get(),
            'setup' => [
                'services' => AppointmentService::where('workspace_id', $workspaceId)->where('active', true)->count(),
                'resources' => AppointmentResource::where('workspace_id', $workspaceId)->where('active', true)->count(),
                'resources_with_hours' => AppointmentResource::where('workspace_id', $workspaceId)->whereHas('availabilities')->count(),
                'channel_connected' => (bool) AppointmentSetting::forWorkspace($workspaceId)->channel_account_id,
            ],
        ]);
    }

    public function calendar(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $settings = AppointmentSetting::forWorkspace($workspaceId);
        $view = in_array($request->query('view'), ['month', 'week', 'day'], true) ? $request->query('view') : 'month';
        try {
            $referenceDate = $request->filled('date')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->query('date'), $settings->timezone)->startOfDay()
                : Carbon::createFromFormat('Y-m', (string) $request->query('month', now($settings->timezone)->format('Y-m')), $settings->timezone)->startOfMonth();
        } catch (\Throwable) {
            $referenceDate = now($settings->timezone)->startOfDay();
        }
        [$periodStart, $periodEnd] = match ($view) {
            'day' => [$referenceDate->copy()->startOfDay(), $referenceDate->copy()->endOfDay()],
            'week' => [$referenceDate->copy()->startOfWeek(Carbon::SUNDAY), $referenceDate->copy()->endOfWeek(Carbon::SATURDAY)],
            default => [
                $referenceDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
                $referenceDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY)->addDays(41)->endOfDay(),
            ],
        };

        return Inertia::render('Appointments/Calendar', [
            'services' => AppointmentService::where('workspace_id', $workspaceId)->where('active', true)->with('resources:id,uuid,name')->orderBy('name')->get(),
            'resources' => AppointmentResource::where('workspace_id', $workspaceId)->where('active', true)->with('services:id,uuid,name')->orderBy('name')->get(['id', 'uuid', 'name', 'position', 'booking_enabled']),
            'appointments' => Appointment::where('workspace_id', $workspaceId)
                ->with(['service:id,uuid,name,color,duration_minutes', 'resource:id,uuid,name', 'contact:id,uuid,first_name,last_name,phone_e164,email'])
                ->whereBetween('starts_at', [$periodStart->copy()->utc(), $periodEnd->copy()->utc()])
                ->orderBy('starts_at')->limit(1000)->get(),
            'calendarMonth' => $referenceDate->format('Y-m'),
            'calendarDate' => $referenceDate->format('Y-m-d'),
            'calendarView' => $view,
            'timezone' => $settings->timezone,
        ]);
    }

    public function appointments(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $query = Appointment::where('workspace_id', $workspaceId)
            ->with(['service:id,uuid,name,color', 'resource:id,uuid,name'])
            ->when($request->search, fn ($builder, $search) => $builder->where(fn ($nested) => $nested
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")))
            ->when($request->status, fn ($builder, $status) => $builder->where('status', $status))
            ->when($request->resource, fn ($builder, $uuid) => $builder->whereHas('resource', fn ($resource) => $resource->where('uuid', $uuid)))
            ->when($request->service, fn ($builder, $uuid) => $builder->whereHas('service', fn ($service) => $service->where('uuid', $uuid)))
            ->when($request->from, fn ($builder, $from) => $builder->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($request->to, fn ($builder, $to) => $builder->where('starts_at', '<=', Carbon::parse($to)->endOfDay()));

        return Inertia::render('Appointments/List', [
            'appointments' => $query->orderByDesc('starts_at')->paginate(30)->withQueryString(),
            'services' => AppointmentService::where('workspace_id', $workspaceId)->where('active', true)->orderBy('name')->get(['uuid', 'name']),
            'resources' => AppointmentResource::where('workspace_id', $workspaceId)->where('active', true)->with('services:id,uuid,name')->orderBy('name')->get(['id', 'uuid', 'name']),
            'filters' => $request->only('search', 'status', 'resource', 'service', 'from', 'to'),
        ]);
    }

    public function clients(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $summary = Appointment::where('workspace_id', $workspaceId)
            ->whereNotNull('contact_id')
            ->selectRaw('contact_id, count(*) as appointments_count, max(starts_at) as last_appointment_at')
            ->groupBy('contact_id');
        $contacts = Contact::query()
            ->where('contacts.workspace_id', $workspaceId)
            ->joinSub($summary, 'appointment_summary', fn ($join) => $join->on('contacts.id', '=', 'appointment_summary.contact_id'))
            ->when($request->search, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone_e164', 'like', "%{$search}%")))
            ->orderByDesc('last_appointment_at')
            ->paginate(30, ['contacts.*', 'appointment_summary.appointments_count', 'appointment_summary.last_appointment_at'])
            ->withQueryString();

        return Inertia::render('Appointments/Clients', ['contacts' => $contacts, 'filters' => $request->only('search')]);
    }

    public function services(Request $request): Response
    {
        return Inertia::render('Appointments/Services', [
            'services' => AppointmentService::where('workspace_id', $this->workspaceId($request))
                ->with(['resources:id,uuid,name'])->withCount('appointments')->orderByDesc('active')->orderBy('name')->get(),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $this->serviceData($request);
        AppointmentService::create(array_merge($data, ['workspace_id' => $this->workspaceId($request), 'currency' => 'BRL']));

        return back()->with('success', 'Serviço criado.');
    }

    public function updateService(Request $request, AppointmentService $service): RedirectResponse
    {
        $this->authorise($request, $service);
        $service->update($this->serviceData($request));

        return back()->with('success', 'Serviço atualizado.');
    }

    public function toggleService(Request $request, AppointmentService $service): RedirectResponse
    {
        $this->authorise($request, $service);
        $service->update(['active' => ! $service->active]);

        return back()->with('success', $service->active ? 'Serviço ativado.' : 'Serviço arquivado.');
    }

    public function resources(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $settings = AppointmentSetting::forWorkspace($workspaceId);
        if (blank($settings->public_token)) {
            $settings->forceFill(['public_token' => Str::random(48)])->save();
        }
        $publicUrl = route('appointments.public.show', $settings->public_token);

        return Inertia::render('Appointments/Resources', [
            'resources' => AppointmentResource::where('workspace_id', $workspaceId)
                ->with(['services:id,uuid,name', 'availabilities'])->withCount('appointments')->orderByDesc('active')->orderBy('name')->get()
                ->map(function (AppointmentResource $resource) use ($settings): AppointmentResource {
                    $url = route('appointments.public.professional', [$settings->public_token, $resource->uuid]);
                    $resource->setAttribute('public_url', $url);
                    $resource->setAttribute('embed_html', $this->embedHtml($url, 'Agenda de '.$resource->name));

                    return $resource;
                }),
            'services' => AppointmentService::where('workspace_id', $workspaceId)->where('active', true)->orderBy('name')->get(['id', 'uuid', 'name']),
            'publicBooking' => [
                'url' => $publicUrl,
                'embed_html' => $this->embedHtml($publicUrl, 'Agenda online'),
            ],
        ]);
    }

    public function storeResource(Request $request): RedirectResponse
    {
        $data = $this->resourceData($request);
        $serviceIds = $this->serviceIds($request);
        $resource = AppointmentResource::create(array_merge($data, ['workspace_id' => $this->workspaceId($request), 'timezone' => 'America/Sao_Paulo']));
        $resource->services()->syncWithPivotValues($serviceIds, ['workspace_id' => $resource->workspace_id]);

        return back()->with('success', 'Profissional ou recurso criado.');
    }

    public function updateResource(Request $request, AppointmentResource $resource): RedirectResponse
    {
        $this->authorise($request, $resource);
        $resource->update($this->resourceData($request));
        $resource->services()->syncWithPivotValues($this->serviceIds($request), ['workspace_id' => $resource->workspace_id]);

        return back()->with('success', 'Profissional ou recurso atualizado.');
    }

    public function toggleResource(Request $request, AppointmentResource $resource): RedirectResponse
    {
        $this->authorise($request, $resource);
        $resource->update(['active' => ! $resource->active]);

        return back()->with('success', $resource->active ? 'Profissional ativado.' : 'Profissional arquivado.');
    }

    public function toggleResourceBooking(Request $request, AppointmentResource $resource): RedirectResponse
    {
        $this->authorise($request, $resource);
        abort_unless($resource->active, 422, 'Reative o cadastro antes de abrir a agenda.');
        $resource->update(['booking_enabled' => ! $resource->booking_enabled]);

        return back()->with('success', $resource->booking_enabled ? 'Agenda aberta para novos agendamentos.' : 'Agenda fechada manualmente.');
    }

    public function availability(Request $request): Response
    {
        return Inertia::render('Appointments/Availability', [
            'resources' => AppointmentResource::where('workspace_id', $this->workspaceId($request))->where('active', true)->with('availabilities')->orderBy('name')->get(),
            'selectedResourceUuid' => $request->query('profissional'),
        ]);
    }

    public function updateAvailability(Request $request, AppointmentResource $resource): RedirectResponse
    {
        $this->authorise($request, $resource);
        $data = $request->validate([
            'windows' => ['array', 'max:21'],
            'windows.*.weekday' => ['required', 'integer', 'between:0,6'],
            'windows.*.starts_at' => ['required', 'date_format:H:i'],
            'windows.*.ends_at' => ['required', 'date_format:H:i'],
        ]);
        $windows = collect($data['windows'] ?? [])->sortBy(fn ($window) => $window['weekday'].'-'.$window['starts_at'])->values();
        foreach ($windows->groupBy('weekday') as $dayWindows) {
            $previousEnd = null;
            foreach ($dayWindows as $window) {
                abort_unless($window['ends_at'] > $window['starts_at'], 422, 'O horário final deve ser posterior ao horário inicial.');
                abort_if($previousEnd && $window['starts_at'] < $previousEnd, 422, 'Existem horários sobrepostos no mesmo dia.');
                $previousEnd = $window['ends_at'];
            }
        }
        DB::transaction(function () use ($resource, $windows): void {
            $resource->availabilities()->delete();
            foreach ($windows as $window) {
                AppointmentAvailability::create(array_merge($window, ['workspace_id' => $resource->workspace_id, 'resource_id' => $resource->id]));
            }
        });

        return back()->with('success', 'Disponibilidade atualizada.');
    }

    public function slots(Request $request, AppointmentAvailabilityService $availability): JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $data = $request->validate(['resource_uuid' => ['required', 'uuid'], 'service_uuid' => ['required', 'uuid'], 'date' => ['required', 'date_format:Y-m-d']]);
        $resource = AppointmentResource::where('workspace_id', $workspaceId)->where('active', true)->where('uuid', $data['resource_uuid'])->firstOrFail();
        $service = AppointmentService::where('workspace_id', $workspaceId)->where('active', true)->where('uuid', $data['service_uuid'])->firstOrFail();
        abort_if($resource->services()->exists() && ! $resource->services()->whereKey($service->id)->exists(), 422, 'Este profissional não realiza o serviço selecionado.');

        return response()->json(['slots' => $availability->slots($resource, $service, Carbon::parse($data['date'], $resource->timezone))]);
    }

    public function channels(Request $request, ChannelAccountAccess $access): Response
    {
        $workspaceId = $this->workspaceId($request);
        $allowedIds = $access->allowedAccountIds($request->user(), $workspaceId);

        return Inertia::render('Appointments/Channels', [
            'settings' => AppointmentSetting::forWorkspace($workspaceId),
            'channels' => ChannelAccount::where('workspace_id', $workspaceId)->whereIn('id', $allowedIds)->where('status', 'active')->orderBy('display_name')->get(['id', 'channel', 'provider', 'display_name', 'status']),
        ]);
    }

    public function updateChannels(Request $request, ChannelAccountAccess $access): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $data = $request->validate([
            'channel_account_id' => ['nullable', 'integer'],
            'timezone' => ['required', 'timezone'],
            'booking_horizon_days' => ['required', 'integer', 'between:1,365'],
            'slot_interval_minutes' => ['required', Rule::in([5, 10, 15, 20, 30, 45, 60])],
            'send_confirmation' => ['boolean'],
            'send_reminders' => ['boolean'],
            'send_status_updates' => ['boolean'],
            'confirmation_message' => ['nullable', 'string', 'max:2000'],
            'reminder_message' => ['nullable', 'string', 'max:2000'],
            'cancellation_message' => ['nullable', 'string', 'max:2000'],
            'reschedule_message' => ['nullable', 'string', 'max:2000'],
            'google_calendar_enabled' => ['prohibited'],
            'google_calendar_invite_customer' => ['prohibited'],
        ]);
        if (! empty($data['channel_account_id'])) {
            $account = ChannelAccount::where('workspace_id', $workspaceId)->findOrFail($data['channel_account_id']);
            abort_unless($access->allows($request->user(), $account), 403);
        }
        AppointmentSetting::forWorkspace($workspaceId)->update($data);

        return back()->with('success', 'Canais e regras de envio atualizados.');
    }

    private function serviceData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'between:5,1440'],
            'buffer_minutes' => ['nullable', 'integer', 'between:0,240'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }

    private function resourceData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'position' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
    }

    /** @return list<int> */
    private function serviceIds(Request $request): array
    {
        $uuids = $request->validate(['service_uuids' => ['array'], 'service_uuids.*' => ['uuid']])['service_uuids'] ?? [];

        return AppointmentService::where('workspace_id', $this->workspaceId($request))->whereIn('uuid', $uuids)->pluck('id')->all();
    }

    private function authorise(Request $request, object $model): void
    {
        abort_unless((int) $model->workspace_id === $this->workspaceId($request), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function embedHtml(string $url, string $title): string
    {
        $src = e($url.'?embed=1');
        $safeTitle = e($title);

        return '<iframe src="'.$src.'" title="'.$safeTitle.'" style="width:100%;min-height:760px;border:0;border-radius:16px" loading="lazy"></iframe>';
    }
}
