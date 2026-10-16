<?php

namespace Tests\Feature\Analytics;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiRun;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $svc;

    private int $wsId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AnalyticsService::class);

        $ctx = $this->createWorkspaceContext();
        $this->wsId = $ctx['workspace']->id;
    }

    // ─── messageVolumeByChannel ────────────────────────────────────────────────

    public function test_message_volume_returns_date_series(): void
    {
        // Create a conversation + 3 messages for this workspace
        $conv = Conversation::create([
            'workspace_id' => $this->wsId,
            'channel_account_id' => 1,
            'contact_id' => 1,
            'status' => 'open',
        ]);
        Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'hi',
            'status' => 'sent',
        ]);

        $from = Carbon::now()->subDays(1);
        $to = Carbon::now();

        $result = $this->svc->messageVolumeByChannel($this->wsId, $from, $to);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        // Each item should have a 'date' key
        $this->assertArrayHasKey('date', $result[0]);
    }

    // ─── campaignFunnel ───────────────────────────────────────────────────────

    public function test_campaign_funnel_returns_status_buckets(): void
    {
        $campaign = Campaign::create([
            'workspace_id' => $this->wsId,
            'name' => 'Test Campaign',
            'channel' => 'whatsapp',
            'audience_type' => 'segment',
            'status' => 'completed',
        ]);

        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 1, 'status' => 'sent']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 2, 'status' => 'delivered']);
        CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => 3, 'status' => 'delivered']);

        $funnel = $this->svc->campaignFunnel($campaign->id);

        $this->assertIsArray($funnel);
        $names = array_column($funnel, 'name');
        $this->assertContains('Sent', $names);
        $this->assertContains('Delivered', $names);

        // Sum of values must equal total recipients created
        $this->assertEquals(3, array_sum(array_column($funnel, 'value')));
    }

    // ─── aiUsageByDay ─────────────────────────────────────────────────────────

    public function test_ai_usage_by_day_returns_date_series(): void
    {
        $chatbot = AiChatbot::create([
            'workspace_id' => $this->wsId,
            'name' => 'Bot',
            'system_prompt' => 'You are a bot.',
            'enabled' => true,
        ]);

        AiRun::create([
            'chatbot_id' => $chatbot->id,
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'cost_cents' => 3,
            'latency_ms' => 400,
            'model' => 'gpt-4o',
            'status' => 'ok',
        ]);

        $from = Carbon::now()->subDays(1);
        $to = Carbon::now();

        $result = $this->svc->aiUsageByDay($this->wsId, $from, $to);

        $this->assertIsArray($result);
        $totals = array_sum(array_column($result, 'prompt'));
        $this->assertEquals(100, $totals);
    }

    // ─── conversationsResolvedOverTime ────────────────────────────────────────

    public function test_conversations_resolved_over_time(): void
    {
        Conversation::create([
            'workspace_id' => $this->wsId,
            'channel_account_id' => 1,
            'contact_id' => 1,
            'status' => 'open',
        ]);

        $from = Carbon::now()->subDays(1);
        $to = Carbon::now();

        $result = $this->svc->conversationsResolvedOverTime($this->wsId, $from, $to);

        $this->assertIsArray($result);
        $openedTotal = array_sum(array_column($result, 'opened'));
        $this->assertGreaterThanOrEqual(1, $openedTotal);
    }
}
