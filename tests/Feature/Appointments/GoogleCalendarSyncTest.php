<?php
namespace Tests\Feature\Appointments;
use App\Modules\Appointments\Jobs\SyncAppointmentGoogleCalendarJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;
    public function test_legacy_jobs_do_not_contact_google(): void
    {
        Http::fake();
        (new SyncAppointmentGoogleCalendarJob(1))->handle();
        (new SyncAppointmentGoogleCalendarJob(1, 'delete'))->handle();
        Http::assertNothingSent();
    }
    public function test_google_connection_is_unavailable(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get('/painel/agendamentos/google-agenda/conectar')->assertNotFound();
        $this->get(route('client.appointments.channels.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Appointments/Channels')->missing('googleCalendar'));
        $this->putJson(route('client.appointments.channels.update'), [
            'timezone' => 'America/Sao_Paulo', 'booking_horizon_days' => 90,
            'slot_interval_minutes' => 30, 'google_calendar_enabled' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('google_calendar_enabled');
    }
}
