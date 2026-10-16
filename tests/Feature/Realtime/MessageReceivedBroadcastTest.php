<?php

namespace Tests\Feature\Realtime;

use App\Events\MessageReceived;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageReceivedBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_received_broadcasts_on_correct_channels(): void
    {
        Event::fake([MessageReceived::class]);

        $ctx = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $ctx['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Canal seguro',
            'status' => 'active',
            'meta_json' => [],
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $ctx['workspace']->id]);
        $conv = Conversation::create([
            'workspace_id' => $ctx['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'body' => 'Hello',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        MessageReceived::dispatch($message);

        Event::assertDispatched(MessageReceived::class, function ($event) use ($message) {
            return $event->message->id === $message->id;
        });
    }

    public function test_message_received_broadcast_with_contains_expected_fields(): void
    {
        $ctx = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $ctx['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Canal seguro',
            'status' => 'active',
            'meta_json' => [],
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $ctx['workspace']->id]);
        $conv = Conversation::create([
            'workspace_id' => $ctx['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'body' => 'Hello broadcast',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Explicitly load the conversation relationship so broadcastOn() can access workspace_id
        $message->setRelation('conversation', $conv);

        $event = new MessageReceived($message);
        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('conversation_id', $payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertEquals('Hello broadcast', $payload['body']);

        $channels = $event->broadcastOn();
        $channelNames = array_map(fn ($c) => $c->name, $channels);
        $this->assertContains("private-conversation.{$conv->id}", $channelNames);
        $this->assertContains("private-channel-account.{$account->id}", $channelNames);
        $this->assertNotContains("private-workspace.{$ctx['workspace']->id}", $channelNames);
    }
}
