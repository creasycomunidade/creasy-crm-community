<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Broadcasting\Models\SmsProviderConfig;
use App\Modules\Shared\Models\Contact;
use App\Support\ApiAbilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessageSendApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/messages/send', [])->assertStatus(401);
    }

    public function test_wrong_scope_returns_403(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/messages/send', ['contact_id' => 1, 'channel' => 'whatsapp'])
            ->assertStatus(403);
    }

    public function test_missing_contact_returns_422_via_validation(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;

        // Missing required fields
        $this->withToken($token)
            ->postJson('/api/v1/messages/send', [])
            ->assertStatus(422);
    }

    public function test_contact_not_found_returns_404(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::MESSAGES_WRITE])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/messages/send', [
                'contact_id' => 9999,
                'channel' => 'whatsapp',
                'body' => 'Hello',
            ])
            ->assertStatus(404);
    }

    public function test_no_active_channel_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::MESSAGES_WRITE])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id, 'phone_e164' => '+8801700000001']);

        // No channel account configured — should return 422
        $this->withToken($token)
            ->postJson('/api/v1/messages/send', [
                'contact_id' => $contact->id,
                'channel' => 'whatsapp',
                'body' => 'Hello',
            ])
            ->assertStatus(422);
    }

    public function test_sms_send_happy_path(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', ['*'])->plainTextToken;
        $contact = Contact::factory()->create(['workspace_id' => $workspace->id, 'phone_e164' => '+8801700000002']);

        // Fake SMS driver HTTP calls
        Http::fake(['*' => Http::response(['msgid' => 'test-msg-id', 'status' => 'sent'], 200)]);

        // Provision a fake SMS config
        SmsProviderConfig::create([
            'workspace_id' => $workspace->id,
            'provider' => 'twilio',
            'credentials' => ['account_sid' => 'ACtest', 'auth_token' => 'token', 'from_number' => '+1234567890'],
            'default' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/messages/send', [
                'contact_id' => $contact->id,
                'channel' => 'sms',
                'body' => 'Test SMS',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['provider_message_id', 'status']);
    }
}
