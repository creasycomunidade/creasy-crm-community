<?php

namespace Tests\Feature\Analytics;

use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_export_returns_csv_with_headers(): void
    {
        $ctx = $this->createWorkspaceContext();
        $user = $ctx['user'];
        $workspace = $ctx['workspace'];

        Contact::factory()->count(3)->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->get(route('reports.exports.contacts'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_contacts_export_is_workspace_scoped(): void
    {
        // Create two separate workspaces
        $ctx1 = $this->createWorkspaceContext();
        $ctx2 = $this->createWorkspaceContext();

        Contact::factory()->count(2)->create(['workspace_id' => $ctx1['workspace']->id]);
        Contact::factory()->count(5)->create(['workspace_id' => $ctx2['workspace']->id]);

        // User from workspace 1 should only get their own contacts
        $response = $this->actingAs($ctx1['user'])
            ->get(route('reports.exports.contacts'));

        $response->assertOk();
        $body = $response->streamedContent();
        $lines = array_filter(explode("\n", $body));
        // 1 header + 2 data rows = 3 lines
        $this->assertCount(3, $lines);
    }

    public function test_campaign_recipients_export_scoped_to_campaign_workspace(): void
    {
        $ctx1 = $this->createWorkspaceContext();
        $ctx2 = $this->createWorkspaceContext();

        $campaign = Campaign::create([
            'workspace_id' => $ctx1['workspace']->id,
            'name' => 'My Campaign',
            'channel' => 'sms',
            'audience_type' => 'segment',
            'status' => 'completed',
        ]);

        // User from workspace 2 cannot export workspace 1's campaign
        $this->actingAs($ctx2['user'])
            ->get(route('reports.exports.campaign-recipients', $campaign))
            ->assertForbidden();
    }
}
