<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Shared\Models\Contact;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/automations')->assertStatus(401);
    }

    public function test_wrong_scope_returns_403(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/automations')->assertStatus(403);
    }

    public function test_list_automations_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Welcome Flow',
            'status' => 'active',
            'trigger_type' => 'contact_created',
            'trigger_config' => [],
            'nodes' => [],
            'edges' => [],
        ]);

        $res = $this->withToken($token)
            ->getJson('/api/v1/automations')
            ->assertOk();

        $this->assertCount(1, $res->json('data'));
    }

    public function test_trigger_automation_creates_run_and_dispatches_job(): void
    {
        Queue::fake();

        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::AUTOMATIONS_WRITE])->plainTextToken;

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Flow',
            'status' => 'active',
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'nodes' => [],
            'edges' => [],
        ]);

        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $this->withToken($token)
            ->postJson("/api/v1/automations/{$automation->id}/trigger", [
                'contact_id' => $contact->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('automation_id', $automation->id)
            ->assertJsonPath('contact_id', $contact->id);

        $this->assertDatabaseHas('automation_runs', [
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ExecuteAutomationRunJob::class);
    }

    public function test_trigger_automation_invalid_contact_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $automation = Automation::create([
            'workspace_id' => $workspace->id,
            'name' => 'Flow',
            'status' => 'active',
            'trigger_type' => 'manual',
            'trigger_config' => [],
            'nodes' => [],
            'edges' => [],
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/automations/{$automation->id}/trigger", ['contact_id' => 9999])
            ->assertStatus(404);
    }
}
