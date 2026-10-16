<?php

namespace Tests\Feature\Appointments;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentAvailability;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Appointments\Models\AppointmentSetting;
use App\Modules\Appointments\Services\AppointmentChannelNotifier;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agenda_submenus_are_available_to_the_workspace_user(): void
    {
        $context = $this->createWorkspaceContext();

        foreach ([
            'client.appointments.index',
            'client.appointments.calendar',
            'client.appointments.list',
            'client.appointments.clients',
            'client.appointments.services.index',
            'client.appointments.resources.index',
            'client.appointments.availability.index',
            'client.appointments.channels.index',
        ] as $routeName) {
            $this->actingAs($context['user'])->get(route($routeName))->assertOk();
        }
    }

    public function test_calendar_supports_month_week_and_day_views(): void
    {
        $context = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Consulta',
            'duration_minutes' => 30,
        ]);
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Profissional',
        ]);
        $contact = Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Cliente vinculado',
            'phone_e164' => '+5547999991234',
        ]);
        Appointment::create([
            'workspace_id' => $context['workspace']->id,
            'service_id' => $service->id,
            'resource_id' => $resource->id,
            'contact_id' => $contact->id,
            'customer_name' => 'Cliente vinculado',
            'starts_at' => '2026-08-28 13:00:00',
            'ends_at' => '2026-08-28 13:30:00',
        ]);

        foreach (['month', 'week', 'day'] as $view) {
            $this->actingAs($context['user'])
                ->get(route('client.appointments.calendar', ['view' => $view, 'date' => '2026-08-28']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Appointments/Calendar')
                    ->where('calendarView', $view)
                    ->where('calendarDate', '2026-08-28')
                    ->where('appointments.0.contact.uuid', $contact->uuid));
        }
    }

    public function test_appointment_contact_search_returns_workspace_contact_uuid(): void
    {
        $context = $this->createWorkspaceContext();
        $other = $this->createWorkspaceContext();
        $contact = Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Cliente Agenda',
            'phone_e164' => '+5547999991234',
        ]);
        Contact::create([
            'workspace_id' => $other['workspace']->id,
            'first_name' => 'Cliente Agenda Externo',
            'phone_e164' => '+5547999995678',
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('client.inbox.contacts.search', ['q' => 'Cliente Agenda']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.uuid', $contact->uuid);
    }

    public function test_same_resource_cannot_be_double_booked(): void
    {
        $context = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Consulta',
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
        ]);
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Dra. Maria',
            'timezone' => 'America/Sao_Paulo',
        ]);
        $start = now('America/Sao_Paulo')->addDay()->setTime(14, 0)->format('Y-m-d\TH:i');
        $payload = [
            'service_uuid' => $service->uuid,
            'resource_uuid' => $resource->uuid,
            'customer_name' => 'Primeiro cliente',
            'starts_at' => $start,
        ];

        $this->actingAs($context['user'])
            ->post(route('client.appointments.store'), $payload)
            ->assertRedirect();

        $this->actingAs($context['user'])
            ->postJson(route('client.appointments.store'), array_merge($payload, ['customer_name' => 'Segundo cliente']))
            ->assertUnprocessable();

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_resources_are_isolated_between_workspaces(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $first['workspace']->id,
            'name' => 'Corte',
            'duration_minutes' => 30,
        ]);
        $foreignResource = AppointmentResource::create([
            'workspace_id' => $second['workspace']->id,
            'name' => 'Recurso externo',
        ]);

        $this->actingAs($first['user'])->postJson(route('client.appointments.store'), [
            'service_uuid' => $service->uuid,
            'resource_uuid' => $foreignResource->uuid,
            'customer_name' => 'Cliente',
            'starts_at' => now()->addDay()->toIso8601String(),
        ])->assertNotFound();
    }

    public function test_availability_blocks_outside_hours_and_lists_free_slots(): void
    {
        $context = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Avaliação',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
        ]);
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Sala 1',
            'timezone' => 'America/Sao_Paulo',
        ]);
        $date = now('America/Sao_Paulo')->addDay()->startOfDay();
        AppointmentAvailability::create([
            'workspace_id' => $context['workspace']->id,
            'resource_id' => $resource->id,
            'weekday' => $date->dayOfWeek,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $this->actingAs($context['user'])->getJson(route('client.appointments.slots', [
            'resource_uuid' => $resource->uuid,
            'service_uuid' => $service->uuid,
            'date' => $date->format('Y-m-d'),
        ]))->assertOk()->assertJsonFragment(['09:00']);

        $this->actingAs($context['user'])->postJson(route('client.appointments.store'), [
            'service_uuid' => $service->uuid,
            'resource_uuid' => $resource->uuid,
            'customer_name' => 'Fora do horário',
            'starts_at' => $date->copy()->setTime(13, 0)->format('Y-m-d\TH:i'),
        ])->assertUnprocessable();
    }

    public function test_channel_settings_cannot_use_another_workspace_account(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $foreign = ChannelAccount::create([
            'workspace_id' => $second['workspace']->id,
            'channel' => 'messenger',
            'provider' => 'meta',
            'display_name' => 'Conta externa',
            'status' => 'active',
        ]);

        $this->actingAs($first['user'])->putJson(route('client.appointments.channels.update'), [
            'channel_account_id' => $foreign->id,
            'timezone' => 'America/Sao_Paulo',
            'booking_horizon_days' => 90,
            'slot_interval_minutes' => 30,
            'send_confirmation' => true,
            'send_reminders' => true,
            'send_status_updates' => true,
        ])->assertNotFound();
    }

    public function test_appointment_notification_uses_the_selected_workspace_channel(): void
    {
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'messenger',
            'provider' => 'meta',
            'display_name' => 'Atendimento',
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Ana',
            'phone_e164' => '+5547999999999',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => 'psid-ana',
            'status' => 'open',
        ]);
        $service = AppointmentService::create(['workspace_id' => $context['workspace']->id, 'name' => 'Consulta', 'duration_minutes' => 30]);
        $resource = AppointmentResource::create(['workspace_id' => $context['workspace']->id, 'name' => 'Dra. Maria']);
        $appointment = Appointment::create([
            'workspace_id' => $context['workspace']->id,
            'service_id' => $service->id,
            'resource_id' => $resource->id,
            'contact_id' => $contact->id,
            'customer_name' => 'Ana',
            'customer_phone' => $contact->phone_e164,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
        ]);
        AppointmentSetting::forWorkspace($context['workspace']->id)->update(['channel_account_id' => $account->id, 'send_confirmation' => true]);

        $driver = Mockery::mock(ChannelDriverInterface::class);
        $driver->shouldReceive('send')->once()->andReturn('provider.confirmation');
        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('driver')->with('messenger')->once()->andReturn($driver);

        $this->assertTrue((new AppointmentChannelNotifier($manager))->send($appointment, 'confirmation'));
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'status' => 'sent',
            'sent_by' => 'automation',
        ]);
    }

    public function test_resources_page_exposes_general_and_individual_public_booking_links(): void
    {
        $context = $this->createWorkspaceContext();
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Dra. Maria',
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.appointments.resources.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Resources')
                ->where('resources.0.uuid', $resource->uuid)
                ->where('resources.0.booking_enabled', true)
                ->where('resources.0.public_url', fn ($url) => str_contains($url, $resource->uuid))
                ->where('resources.0.embed_html', fn ($html) => str_contains($html, '<iframe'))
                ->where('publicBooking.url', fn ($url) => str_contains($url, '/agendar/'))
                ->where('publicBooking.embed_html', fn ($html) => str_contains($html, '<iframe')));
    }

    public function test_professional_agenda_can_be_closed_without_archiving_the_resource(): void
    {
        $context = $this->createWorkspaceContext();
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Sala 1',
        ]);

        $this->actingAs($context['user'])
            ->patch(route('client.appointments.resources.booking.toggle', $resource))
            ->assertRedirect();

        $resource->refresh();
        $this->assertTrue($resource->active);
        $this->assertFalse($resource->booking_enabled);
    }

    public function test_guest_can_book_using_the_workspace_public_agenda(): void
    {
        $context = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Corte',
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
        ]);
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'João',
            'timezone' => 'America/Sao_Paulo',
        ]);
        $date = now('America/Sao_Paulo')->addDays(2)->startOfDay();
        AppointmentAvailability::create([
            'workspace_id' => $context['workspace']->id,
            'resource_id' => $resource->id,
            'weekday' => $date->dayOfWeek,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $settings = AppointmentSetting::forWorkspace($context['workspace']->id);

        $this->get(route('appointments.public.show', $settings->public_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/PublicBooking')
                ->where('workspace.name', $context['workspace']->name)
                ->where('resources.0.uuid', $resource->uuid));

        $this->getJson(route('appointments.public.slots', [
            'token' => $settings->public_token,
            'resource_uuid' => $resource->uuid,
            'service_uuid' => $service->uuid,
            'date' => $date->format('Y-m-d'),
        ]))->assertOk()->assertJsonFragment(['09:00']);

        $this->postJson(route('appointments.public.store', $settings->public_token), [
            'resource_uuid' => $resource->uuid,
            'service_uuid' => $service->uuid,
            'customer_name' => 'Cliente público',
            'customer_phone' => '(47) 99999-1234',
            'date' => $date->format('Y-m-d'),
            'time' => '09:00',
        ])->assertCreated()->assertJsonPath('message', 'Agendamento realizado com sucesso.');

        $this->assertDatabaseHas('appointments', [
            'workspace_id' => $context['workspace']->id,
            'resource_id' => $resource->id,
            'service_id' => $service->id,
            'source' => 'public_booking',
            'customer_phone' => '+5547999991234',
        ]);
    }

    public function test_public_booking_token_cannot_access_another_workspace_resource(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $service = AppointmentService::create([
            'workspace_id' => $first['workspace']->id,
            'name' => 'Consulta',
            'duration_minutes' => 30,
        ]);
        $foreignResource = AppointmentResource::create([
            'workspace_id' => $second['workspace']->id,
            'name' => 'Profissional externo',
        ]);
        $settings = AppointmentSetting::forWorkspace($first['workspace']->id);

        $this->getJson(route('appointments.public.slots', [
            'token' => $settings->public_token,
            'resource_uuid' => $foreignResource->uuid,
            'service_uuid' => $service->uuid,
            'date' => now()->addDay()->format('Y-m-d'),
        ]))->assertNotFound();
    }
}
