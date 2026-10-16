<?php

namespace Tests\Feature\Analytics;

use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_sums_to_total_recipient_count(): void
    {
        $ctx = $this->createWorkspaceContext();
        $wsId = $ctx['workspace']->id;

        $campaign = Campaign::create([
            'workspace_id' => $wsId,
            'name' => 'Funnel Test',
            'channel' => 'sms',
            'audience_type' => 'segment',
            'status' => 'completed',
        ]);

        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 1, 'status' => 'queued']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 2, 'status' => 'sent']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 3, 'status' => 'delivered']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 4, 'status' => 'read']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 5, 'status' => 'failed', 'failed_reason' => 'invalid_number']);

        /** @var AnalyticsService $svc */
        $svc = app(AnalyticsService::class);
        $funnel = $svc->campaignFunnel($campaign->id);

        $total = array_sum(array_column($funnel, 'value'));
        $this->assertEquals(5, $total);

        $names = array_column($funnel, 'name');
        $this->assertContains('Failed', $names);
    }

    public function test_failed_reason_donut_groups_correctly(): void
    {
        $ctx = $this->createWorkspaceContext();
        $wsId = $ctx['workspace']->id;

        $campaign = Campaign::create([
            'workspace_id' => $wsId,
            'name' => 'Reason Test',
            'channel' => 'sms',
            'audience_type' => 'segment',
            'status' => 'completed',
        ]);

        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 1, 'status' => 'failed', 'failed_reason' => 'timeout']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 2, 'status' => 'failed', 'failed_reason' => 'timeout']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 3, 'status' => 'failed', 'failed_reason' => 'invalid_number']);

        /** @var AnalyticsService $svc */
        $svc = app(AnalyticsService::class);
        $reasons = $svc->campaignFailedReasons($campaign->id);

        $this->assertCount(2, $reasons);
        $timeoutEntry = collect($reasons)->firstWhere('name', 'timeout');
        $this->assertNotNull($timeoutEntry);
        $this->assertEquals(2, $timeoutEntry['value']);
    }
}
