<?php

namespace Tests\Feature\Realtime;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxPollingFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_new_workspace_messages_and_refreshes_the_list(): void
    {
        $context = $this->createWorkspaceContext();
        $workspace = $context['workspace'];
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'telegram',
            'display_name' => 'Telegram',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Rick',
            'last_name' => null,
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => now(),
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'telegram',
            'type' => 'text',
            'body' => 'Nova mensagem',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->actingAs($context['user'])->getJson(route(
            'client.inbox.workspace-updates',
            ['after_id' => $message->id - 1],
        ))
            ->assertOk()
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('conversations.0.unread_count', 1);

        $response = $this->actingAs($context['user'])->getJson(route(
            'client.inbox.updates',
            ['conversation' => $conversation->uuid, 'after_id' => $message->id - 1],
        ));

        $response->assertOk()
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.channel', 'telegram')
            ->assertJsonPath('messages.0.contact_name', 'Rick')
            ->assertJsonPath('conversations.0.id', $conversation->id)
            ->assertJsonPath('conversations.0.unread_count', 0)
            ->assertJsonPath('latest_id', $message->id);
    }

    public function test_it_does_not_expose_messages_from_another_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $contact = Contact::factory()->create(['workspace_id' => $first['workspace']->id]);
        $conversation = Conversation::create([
            'workspace_id' => $first['workspace']->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $this->actingAs($second['user'])
            ->getJson(route('client.inbox.updates', $conversation->uuid))
            ->assertForbidden();
    }

    public function test_global_notification_cursor_bootstraps_without_replaying_history(): void
    {
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'instagram',
            'display_name' => 'Instagram',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'instagram',
            'type' => 'text',
            'body' => 'Mensagem antiga',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('client.inbox.notification-updates'))
            ->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertJsonPath('latest_id', $message->id);
    }

    public function test_global_notification_cursor_returns_only_new_inbound_messages(): void
    {
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'display_name' => 'WhatsApp',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Maria',
            'last_name' => null,
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
        $cursor = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Enviada',
            'status' => 'sent',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);
        $incoming = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Olá',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('client.inbox.notification-updates', ['after_id' => $cursor->id]))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $incoming->id)
            ->assertJsonPath('messages.0.channel', 'whatsapp')
            ->assertJsonPath('messages.0.contact_name', 'Maria')
            ->assertJsonPath('latest_id', $incoming->id);
    }
}
