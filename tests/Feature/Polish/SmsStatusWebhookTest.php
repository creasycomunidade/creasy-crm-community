<?php

namespace Tests\Feature\Polish;

use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsStatusWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(string $msgId = 'MSG123'): CampaignRecipient
    {
        ['workspace' => $workspace, 'client' => $client] = $this->createWorkspaceContext();

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'name' => 'Test SMS',
        ]);

        return CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'provider_message_id' => $msgId,
            'status' => 'sent',
        ]);
    }

    public function test_twilio_delivery_updates_recipient_status(): void
    {
        $recipient = $this->makeRecipient('SM_TW1');

        $this->postJson(route('webhooks.sms.status', 'twilio'), [
            'MessageSid' => 'SM_TW1',
            'MessageStatus' => 'delivered',
        ])->assertOk();

        $this->assertDatabaseHas('campaign_recipients', [
            'provider_message_id' => 'SM_TW1',
            'status' => 'delivered',
        ]);
    }

    public function test_smsbd_delivery_updates_recipient_status(): void
    {
        $recipient = $this->makeRecipient('SMSBD_001');

        $this->postJson(route('webhooks.sms.status', 'smsbd'), [
            'Message_ID' => 'SMSBD_001',
            'Delivery_Status' => 'DELIVRD',
        ])->assertOk();

        $this->assertDatabaseHas('campaign_recipients', [
            'provider_message_id' => 'SMSBD_001',
            'status' => 'delivered',
        ]);
    }

    public function test_reve_delivery_updates_recipient_status(): void
    {
        $recipient = $this->makeRecipient('REVE_002');

        $this->postJson(route('webhooks.sms.status', 'reve'), [
            'message_id' => 'REVE_002',
            'status' => 'delivered',
        ])->assertOk();

        $this->assertDatabaseHas('campaign_recipients', [
            'provider_message_id' => 'REVE_002',
            'status' => 'delivered',
        ]);
    }

    public function test_smsbd_failed_status_maps_correctly(): void
    {
        $recipient = $this->makeRecipient('SMSBD_FAIL');

        $this->postJson(route('webhooks.sms.status', 'smsbd'), [
            'Message_ID' => 'SMSBD_FAIL',
            'Delivery_Status' => 'UNDELIV',
        ])->assertOk();

        $this->assertDatabaseHas('campaign_recipients', [
            'provider_message_id' => 'SMSBD_FAIL',
            'status' => 'failed',
        ]);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/webhooks/sms/unknownprovider')->assertNotFound();
    }
}
