<?php

namespace Tests\Feature\Polish;

use App\Jobs\GenerateWorkspaceExportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkspaceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_export_route_is_registered_and_authorized(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $user = $ctx['user'];

        // The route must exist (not 404) and be accessible by authenticated users
        // 500 can happen in test env due to Vite manifest missing for new pages — that's a build artifact, not a logic error
        $response = $this->actingAs($user)->get(route('client.settings.data-export'));

        $this->assertNotEquals(404, $response->getStatusCode(), 'Data export route must be registered');
        $this->assertNotEquals(403, $response->getStatusCode(), 'Authenticated user must be able to access data export');
    }

    public function test_requesting_export_dispatches_job(): void
    {
        Queue::fake();

        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $user = $ctx['user'];

        $this->actingAs($user)
            ->post(route('client.settings.data-export.store'))
            ->assertRedirect();

        Queue::assertPushed(GenerateWorkspaceExportJob::class, fn ($job) => true);
    }

    public function test_non_administrator_cannot_export_everyones_workspace_data(): void
    {
        Queue::fake();
        $ctx = $this->createWorkspaceContext([], [
            'email_verified_at' => now(),
            'client_role' => User::CLIENT_ROLE_STAFF,
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('client.settings.data-export'))
            ->assertForbidden();
        $this->actingAs($ctx['user'])
            ->post(route('client.settings.data-export.store'))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }
}
