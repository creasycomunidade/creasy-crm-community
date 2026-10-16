<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Leads\Jobs\ScrapeLeadsJob;
use App\Modules\Leads\Models\Lead;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithWorkspace(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->update(['workspace_id' => $workspace->id]);
        $user->refresh();

        return [$user, $workspace];
    }

    #[Test]
    public function scrape_request_dispatches_job(): void
    {
        Queue::fake();

        [$user] = $this->createUserWithWorkspace();
        IntegrationConfig::create([
            'provider' => 'google_places',
            'label' => 'Google Places',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['api_key' => 'test-key'],
        ]);

        $response = $this->actingAs($user)->post('/painel/leads/scrape', [
            'source' => 'google_places',
            'keyword' => 'restaurants',
            'location' => 'Dhaka',
        ]);

        $response->assertStatus(302);
        Queue::assertPushed(ScrapeLeadsJob::class);
    }

    #[Test]
    public function push_to_contacts_creates_contact_and_marks_lead(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $phone = '+8801700000001';
        $lead = Lead::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Restaurant',
            'phone' => $phone,
            'pushed_to_contacts' => false,
        ]);

        $response = $this->actingAs($user)->post('/painel/leads/push-to-contacts', [
            'ids' => [$lead->id],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'pushed_to_contacts' => true]);
    }

    #[Test]
    public function selected_leads_can_be_edited_and_added_to_the_same_segment(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        $segment = Segment::create([
            'workspace_id' => $workspace->id,
            'name' => 'Prospectos de Itajaí',
            'type' => 'static',
        ]);
        $first = Lead::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Nome antigo A', 'phone' => '+5547999990001', 'pushed_to_contacts' => false]);
        $second = Lead::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Nome antigo B', 'phone' => '+5547999990002', 'pushed_to_contacts' => false]);

        $response = $this->actingAs($user)->post('/painel/leads/push-to-contacts', [
            'segment_id' => $segment->id,
            'leads' => [
                ['id' => $first->id, 'name' => 'Empresa Alfa', 'phone' => '+5547999990001', 'email' => 'alfa@example.com', 'category' => 'Ótica'],
                ['id' => $second->id, 'name' => 'Empresa Beta', 'phone' => '+5547999990002', 'email' => 'beta@example.com', 'category' => 'Ótica'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leads', ['id' => $first->id, 'name' => 'Empresa Alfa', 'category' => 'Ótica', 'pushed_to_contacts' => true]);
        $this->assertDatabaseHas('leads', ['id' => $second->id, 'name' => 'Empresa Beta', 'category' => 'Ótica', 'pushed_to_contacts' => true]);
        $contacts = Contact::where('workspace_id', $workspace->id)->whereIn('lead_id', [$first->id, $second->id])->get();
        $this->assertCount(2, $contacts);
        $this->assertSame(2, $segment->contacts()->whereIn('contacts.id', $contacts->pluck('id'))->count());
        $this->assertSame(2, $segment->fresh()->contact_count);
    }

    #[Test]
    public function lead_import_cannot_use_another_workspace_segment(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();
        [, $otherWorkspace] = $this->createUserWithWorkspace();
        $lead = Lead::factory()->create(['workspace_id' => $workspace->id, 'phone' => '+5547999990003', 'pushed_to_contacts' => false]);
        $foreignSegment = Segment::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Privado', 'type' => 'static']);

        $this->actingAs($user)->post('/painel/leads/push-to-contacts', [
            'segment_id' => $foreignSegment->id,
            'leads' => [['id' => $lead->id, 'name' => 'Lead', 'phone' => '+5547999990003', 'email' => '']],
        ])->assertSessionHasErrors('segment_id');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'pushed_to_contacts' => false]);
    }

    #[Test]
    public function cannot_delete_other_workspace_lead(): void
    {
        [$userA, $workspaceA] = $this->createUserWithWorkspace();
        [$userB, $workspaceB] = $this->createUserWithWorkspace();

        $lead = Lead::factory()->create(['workspace_id' => $workspaceB->id]);

        $response = $this->actingAs($userA)->delete("/painel/leads/{$lead->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('leads', ['id' => $lead->id]);
    }
}
