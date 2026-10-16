<?php

namespace Tests\Feature\Campaign;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Whatsapp\Services\WhatsappDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsappWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->update(['workspace_id' => $workspace->id]);

        return [$user, $workspace];
    }

    private function makeRecipient(int $workspaceId, string $providerId): CampaignRecipient
    {
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspaceId,
            'channel' => 'whatsapp',
            'status' => 'sending',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $workspaceId]);

        return CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'sent',
            'provider_message_id' => $providerId,
            'sent_at' => now()->subMinutes(2),
        ]);
    }

    private function statusPayload(string $providerId, string $status, array $extra = []): array
    {
        return [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => array_merge([
                                'statuses' => [
                                    array_merge([
                                        'id' => $providerId,
                                        'status' => $status,
                                        'timestamp' => (string) now()->timestamp,
                                    ], $extra),
                                ],
                            ], []),
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function delivered_status_updates_campaign_recipient_with_timestamp(): void
    {
        [$user, $workspace] = $this->ctx();
        $providerId = 'wamid.deliver_123';
        $recipient = $this->makeRecipient($workspace->id, $providerId);

        $driver = app(WhatsappDriver::class);
        $driver->processWebhookPayload($this->statusPayload($providerId, 'delivered'));

        $recipient->refresh();
        $this->assertSame('delivered', $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
    }

    #[Test]
    public function read_status_updates_campaign_recipient_with_timestamp(): void
    {
        [$user, $workspace] = $this->ctx();
        $providerId = 'wamid.read_123';
        $recipient = $this->makeRecipient($workspace->id, $providerId);

        $driver = app(WhatsappDriver::class);
        $driver->processWebhookPayload($this->statusPayload($providerId, 'read'));

        $recipient->refresh();
        $this->assertSame('read', $recipient->status);
        $this->assertNotNull($recipient->read_at);
        // Read implies delivered too — backfilled
        $this->assertNotNull($recipient->delivered_at);
    }

    #[Test]
    public function failed_status_records_provider_error(): void
    {
        [$user, $workspace] = $this->ctx();
        $providerId = 'wamid.fail_123';
        $recipient = $this->makeRecipient($workspace->id, $providerId);

        $driver = app(WhatsappDriver::class);
        $driver->processWebhookPayload($this->statusPayload($providerId, 'failed', [
            'errors' => [['title' => 'Recipient blocked you']],
        ]));

        $recipient->refresh();
        $this->assertSame('failed', $recipient->status);
        $this->assertSame('Recipient blocked you', $recipient->failed_reason);
    }

    #[Test]
    public function status_is_never_downgraded(): void
    {
        [$user, $workspace] = $this->ctx();
        $providerId = 'wamid.downgrade_123';
        $recipient = $this->makeRecipient($workspace->id, $providerId);

        $driver = app(WhatsappDriver::class);

        // Apply read first, then delivered. The downgrade must be ignored.
        $driver->processWebhookPayload($this->statusPayload($providerId, 'read'));
        $driver->processWebhookPayload($this->statusPayload($providerId, 'delivered'));

        $recipient->refresh();
        $this->assertSame('read', $recipient->status);
    }
}
