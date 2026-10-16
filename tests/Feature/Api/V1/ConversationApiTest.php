<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/conversations')->assertStatus(401);
    }

    public function test_wrong_scope_returns_403(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/conversations')->assertStatus(403);
    }

    public function test_list_conversations_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'cloud_api',
            'display_name' => 'Test WA',
            'status' => 'active',
        ]);

        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channelAccount->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $res = $this->withToken($token)
            ->getJson('/api/v1/conversations')
            ->assertOk();

        $this->assertNotEmpty($res->json('data'));
    }

    public function test_conversation_messages_returns_thread(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'cloud_api',
            'display_name' => 'Test WA',
            'status' => 'active',
        ]);

        $contact = Contact::factory()->create(['workspace_id' => $workspace->id]);

        $conv = Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $channelAccount->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Hello!',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/conversations/{$conv->id}/messages")
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.body', 'Hello!');
    }

    public function test_conversation_from_other_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        ['workspace' => $otherWs] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $otherWs->id,
            'channel' => 'whatsapp',
            'provider' => 'cloud_api',
            'display_name' => 'Test WA',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $otherWs->id]);
        $conv = Conversation::create([
            'workspace_id' => $otherWs->id,
            'channel_account_id' => $channelAccount->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/conversations/{$conv->id}/messages")
            ->assertStatus(404);
    }
}
