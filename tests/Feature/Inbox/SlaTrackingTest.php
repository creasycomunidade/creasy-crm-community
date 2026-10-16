<?php

namespace Tests\Feature\Inbox;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaTrackingTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    private Conversation $conversation;

    private ChannelAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();

        $workspace = $this->ctx['workspace'];
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $this->account = ChannelAccount::create([
            'workspace_id' => $workspace->id, 'channel' => 'sms', 'provider' => 'twilio',
            'display_name' => 'SMS', 'status' => 'active',
        ]);
        $this->conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_inbound_at' => now()->subMinutes(5),
            'last_message_at' => now()->subMinutes(5),
        ]);
    }

    public function test_first_response_at_is_set_on_first_reply(): void
    {
        $user = $this->ctx['user'];

        $this->assertNull($this->conversation->first_response_at);

        $response = $this->actingAs($user)->post(route('client.inbox.reply', $this->conversation->uuid), [
            'body' => 'Hello there!',
            'type' => 'text',
        ]);

        $response->assertRedirect();
        $this->conversation->refresh();
        $this->assertNotNull($this->conversation->first_response_at);
    }

    public function test_first_response_at_is_not_overwritten_on_subsequent_replies(): void
    {
        $user = $this->ctx['user'];

        // Manually set first_response_at
        $firstResponse = now()->subMinutes(3);
        $this->conversation->update(['first_response_at' => $firstResponse]);

        // Reply again
        $this->actingAs($user)->post(route('client.inbox.reply', $this->conversation->uuid), [
            'body' => 'Follow-up reply',
            'type' => 'text',
        ]);

        $this->conversation->refresh();
        // Should remain the same
        $this->assertEquals(
            $firstResponse->toDateTimeString(),
            $this->conversation->first_response_at->toDateTimeString()
        );
    }

    public function test_resolved_at_is_set_when_status_changed_to_resolved(): void
    {
        $user = $this->ctx['user'];

        $this->assertNull($this->conversation->resolved_at);

        $this->actingAs($user)->post(route('client.inbox.status', $this->conversation->uuid), [
            'status' => 'resolved',
        ]);

        $this->conversation->refresh();
        $this->assertNotNull($this->conversation->resolved_at);
    }

    public function test_resolved_at_is_not_overwritten_if_already_set(): void
    {
        $user = $this->ctx['user'];

        $firstResolvedAt = now()->subHour();
        $this->conversation->update(['resolved_at' => $firstResolvedAt, 'status' => 'resolved']);

        // Change to open then resolve again
        $this->conversation->update(['status' => 'open']);
        $this->actingAs($user)->post(route('client.inbox.status', $this->conversation->uuid), [
            'status' => 'resolved',
        ]);

        $this->conversation->refresh();
        $this->assertEquals(
            $firstResolvedAt->toDateTimeString(),
            $this->conversation->resolved_at->toDateTimeString()
        );
    }
}
