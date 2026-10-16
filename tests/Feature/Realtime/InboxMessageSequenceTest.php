<?php

namespace Tests\Feature\Realtime;

use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InboxMessageSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_uses_arrival_sequence_when_provider_timestamps_conflict(): void
    {
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'telegram',
            'display_name' => 'Telegram',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $first = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'telegram',
            'type' => 'text',
            'body' => 'Recebida antes',
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now()->addHour(),
        ]);
        $product = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'telegram',
            'type' => 'image',
            'body' => 'Produto enviado depois',
            'payload' => ['content_kind' => 'product_share', 'product_id' => 10],
            'status' => 'sent',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inbox/Show')
                ->where('messages.0.id', $first->id)
                ->where('messages.1.id', $product->id)
                ->where('messages.1.payload.content_kind', 'product_share'));
    }
}
