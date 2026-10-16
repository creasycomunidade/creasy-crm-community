<?php

namespace Tests\Feature\Inbox;

use App\Modules\AI\Services\AudioMessageTranscriber;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AudioMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendant_can_request_audio_transcription(): void
    {
        $context = $this->createWorkspaceContext();
        [$conversation, $message] = $this->audioConversation($context['workspace']->id, 'meta');
        $transcriber = Mockery::mock(AudioMessageTranscriber::class);
        $transcriber->shouldReceive('transcribe')->once()->with(Mockery::on(
            fn (Message $candidate) => $candidate->is($message)
        ))->andReturn('Texto transcrito.');
        $this->app->instance(AudioMessageTranscriber::class, $transcriber);

        $this->actingAs($context['user'])
            ->postJson(route('client.inbox.message-transcribe', [
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertOk()
            ->assertJson([
                'message_id' => $message->id,
                'transcription' => 'Texto transcrito.',
                'status' => 'completed',
            ]);
    }

    public function test_user_cannot_transcribe_audio_from_another_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        [$conversation, $message] = $this->audioConversation($second['workspace']->id, 'meta');

        $this->actingAs($first['user'])
            ->postJson(route('client.inbox.message-transcribe', [
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertForbidden();
    }

    public function test_zernio_audio_is_cached_privately_and_supports_byte_ranges(): void
    {
        Storage::fake('local');
        $context = $this->createWorkspaceContext();
        [$conversation, $message] = $this->audioConversation($context['workspace']->id, 'zernio', [
            'attachments' => [[
                'type' => 'audio',
                'url' => 'https://1.1.1.1/audio.ogg',
                'contentType' => 'audio/ogg',
            ]],
            'zernio' => [
                'id' => 'message-1',
                'conversationId' => 'conversation-1',
                'accountId' => 'account-1',
            ],
        ]);
        Http::fake([
            'https://1.1.1.1/audio.ogg' => Http::response('0123456789', 200, ['Content-Type' => 'audio/ogg; codecs=opus']),
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.message-media', [
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/ogg');

        $message->refresh();
        $this->assertSame('message-media/'.$message->id.'.ogg', $message->payload['private_media_path']);
        $this->assertArrayNotHasKey('preview_url', $message->payload);
        Storage::disk('local')->assertExists($message->payload['private_media_path']);

        $this->actingAs($context['user'])
            ->withHeader('Range', 'bytes=2-5')
            ->get(route('client.inbox.message-media', [
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Length', '4');
    }

    public function test_expired_zernio_attachment_is_refreshed_before_private_cache(): void
    {
        Storage::fake('local');
        $context = $this->createWorkspaceContext();
        IntegrationConfig::create([
            'provider' => 'zernio',
            'label' => 'Canais unificados',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['api_key' => 'central-test-key'],
        ]);
        [$conversation, $message] = $this->audioConversation($context['workspace']->id, 'zernio', [
            'attachments' => [[
                'type' => 'audio',
                'url' => 'https://1.1.1.1/expired.ogg',
                'refreshUrl' => '/refresh/audio',
                'contentType' => 'audio/ogg',
            ]],
            'zernio' => [
                'id' => 'message-1',
                'conversationId' => 'conversation-1',
                'accountId' => 'account-1',
            ],
        ]);
        Http::fake([
            'https://zernio.com/api/v1/inbox/conversations/conversation-1/messages/message-1/attachments/0*' => Http::response([
                'url' => 'https://1.1.1.1/fresh.ogg',
                'refreshed' => true,
            ]),
            'https://1.1.1.1/fresh.ogg' => Http::response('fresh-audio', 200, ['Content-Type' => 'application/octet-stream']),
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.message-media', [
                'conversation' => $conversation,
                'message' => $message,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/ogg');

        Storage::disk('local')->assertExists('message-media/'.$message->id.'.ogg');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/attachments/0')
            && $request['accountId'] === 'account-1'
            && $request['format'] === 'json');
    }

    /** @return array{0: Conversation, 1: Message} */
    private function audioConversation(int $workspaceId, string $provider, array $payload = []): array
    {
        $account = ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'whatsapp',
            'provider' => $provider,
            'external_account_id' => 'audio-'.$provider,
            'display_name' => 'WhatsApp',
            'credentials' => [],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $workspaceId]);
        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => 'conversation-1',
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'audio',
            'body' => 'Áudio',
            'payload' => $payload,
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        return [$conversation, $message];
    }
}
