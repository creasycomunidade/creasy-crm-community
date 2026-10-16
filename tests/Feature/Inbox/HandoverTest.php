<?php

namespace Tests\Feature\Inbox;

use App\Events\MessageReceived;
use App\Listeners\AutoReplyListener;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Notifications\ConversationHandoverNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    private Conversation $conversation;

    private ChannelAccount $account;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->createWorkspaceContext();

        $workspace = $this->ctx['workspace'];
        $this->contact = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $this->account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'meta',
            'display_name' => 'WA',
            'status' => 'active',
            'meta_json' => ['ai_chatbot_id' => null],
        ]);
        $this->conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $this->account->id,
            'contact_id' => $this->contact->id,
            'status' => 'open',
            'assigned_to' => 'bot',
        ]);
    }

    public function test_talk_to_human_phrase_triggers_handover(): void
    {
        Notification::fake();

        $workspace = $this->ctx['workspace'];

        // Create a chatbot linked to the channel account
        $chatbot = AiChatbot::create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Bot',
            'enabled' => true,
        ]);

        $this->account->update(['meta_json' => ['ai_chatbot_id' => $chatbot->id]]);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'I want to talk to human please',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);
        $message->setRelation('conversation', $this->conversation->load('channelAccount'));

        $event = new MessageReceived($message);
        $listener = app(AutoReplyListener::class);
        $listener->handle($event);

        $this->conversation->refresh();
        $this->assertEquals('human', $this->conversation->assigned_to);
        $this->assertNotNull($this->conversation->handover_at);

        Notification::assertSentTo(
            $this->ctx['user'],
            ConversationHandoverNotification::class
        );
    }

    public function test_bot_early_returns_if_already_handed_over(): void
    {
        Notification::fake();

        $workspace = $this->ctx['workspace'];
        $chatbot = AiChatbot::create([
            'workspace_id' => $workspace->id,
            'name' => 'Test Bot 2',
            'enabled' => true,
        ]);
        $this->account->update(['meta_json' => ['ai_chatbot_id' => $chatbot->id]]);
        // Conversation is already handed over
        $this->conversation->update(['assigned_to' => 'human']);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Some regular message',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);
        $message->setRelation('conversation', $this->conversation->load('channelAccount'));

        // Initial message count
        $countBefore = Message::where('conversation_id', $this->conversation->id)->where('direction', 'out')->count();

        $event = new MessageReceived($message);
        $listener = app(AutoReplyListener::class);
        $listener->handle($event);

        // No new outbound messages created (bot was skipped)
        $countAfter = Message::where('conversation_id', $this->conversation->id)->where('direction', 'out')->count();
        $this->assertEquals($countBefore, $countAfter);

        // No new notifications sent (no new handover)
        Notification::assertNothingSent();
    }

    public function test_handover_route_switches_to_human(): void
    {
        $user = $this->ctx['user'];

        $response = $this->actingAs($user)->postJson(route('client.inbox.handover', $this->conversation->uuid), [
            'mode' => 'human',
        ]);

        $response->assertOk()->assertJson(['assigned_to' => 'human']);
        $this->conversation->refresh();
        $this->assertEquals('human', $this->conversation->assigned_to);
    }

    public function test_handover_route_switches_back_to_bot(): void
    {
        $user = $this->ctx['user'];
        $this->conversation->update(['assigned_to' => 'human', 'handover_at' => now()]);

        $response = $this->actingAs($user)->postJson(route('client.inbox.handover', $this->conversation->uuid), [
            'mode' => 'bot',
        ]);

        $response->assertOk()->assertJson(['assigned_to' => 'bot']);
        $this->conversation->refresh();
        $this->assertEquals('bot', $this->conversation->assigned_to);
    }
}
