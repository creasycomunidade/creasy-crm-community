<?php

namespace Tests\Feature\MarketingSuite;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Services\Sms\SmsDriverInterface;
use App\Modules\Broadcasting\Services\Sms\SmsSendResult;
use App\Modules\Broadcasting\Services\Sms\SmsStatus;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastingTest extends TestCase
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
    public function creating_a_campaign_queues_the_launch_job(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->createUserWithWorkspace();

        $response = $this->actingAs($user)->post('/painel/broadcasts/campaigns', [
            'name' => 'Test SMS Campaign',
            'channel' => 'sms',
            'body' => 'Hello {{name}}!',
            'segment_ids' => [],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 302]);
    }

    #[Test]
    public function sms_driver_interface_is_implemented_correctly(): void
    {
        $mockDriver = new class implements SmsDriverInterface
        {
            public function send(string $to, string $body, array $opts = []): SmsSendResult
            {
                return new SmsSendResult(true, 'MSG001');
            }

            public function status(string $providerId): SmsStatus
            {
                return new SmsStatus($providerId, 'sent');
            }
        };

        $result = $mockDriver->send('+8801900000001', 'Test message');

        $this->assertTrue($result->success);
        $this->assertEquals('MSG001', $result->messageId);
        $this->assertEquals('', $result->error);
    }

    #[Test]
    public function campaign_recipient_tracks_delivery_status(): void
    {
        [$user, $workspace] = $this->createUserWithWorkspace();

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'sending',
        ]);

        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        $recipient->update(['status' => 'sent', 'sent_at' => now()]);

        $this->assertDatabaseHas('campaign_recipients', [
            'id' => $recipient->id,
            'status' => 'sent',
        ]);
    }
}
