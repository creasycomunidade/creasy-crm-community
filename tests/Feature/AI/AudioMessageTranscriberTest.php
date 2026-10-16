<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Services\AudioMessageTranscriber;
use App\Modules\AI\Services\ConversationMessageContext;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AudioMessageTranscriberTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_is_transcribed_saved_and_supplied_as_ai_context(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('message-media/customer.ogg', 'voice-bytes');
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'audio-account',
            'display_name' => 'WhatsApp',
            'credentials' => ['instance_token' => 'token'],
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
            'channel' => 'whatsapp',
            'type' => 'audio',
            'body' => 'Áudio',
            'payload' => ['preview_url' => '/storage/message-media/customer.ogg', 'mime_type' => 'audio/ogg'],
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
        $gateway = Mockery::mock(LlmGateway::class);
        $gateway->shouldReceive('transcribe')
            ->once()
            ->with($context['workspace']->id, 'voice-bytes', 'audio/ogg', ['language' => 'pt'], $conversation->id)
            ->andReturn('Quero saber o valor do conserto.');
        $this->app->instance(LlmGateway::class, $gateway);

        $text = app(ConversationMessageContext::class)->text($message);

        $this->assertSame('[Áudio transcrito] Quero saber o valor do conserto.', $text);
        $this->assertSame('Quero saber o valor do conserto.', $message->fresh()->payload['transcription']);
        $this->assertSame('completed', $message->fresh()->payload['transcription_status']);
    }

    public function test_audio_can_be_transcribed_from_private_cached_media(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('message-media/voice.ogg', 'private-voice-bytes');
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'zernio',
            'external_account_id' => 'account-private-audio',
            'display_name' => 'WhatsApp',
            'credentials' => [],
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
            'channel' => 'whatsapp',
            'type' => 'audio',
            'body' => 'Áudio',
            'payload' => [
                'private_media_path' => 'message-media/voice.ogg',
                'mime_type' => 'audio/ogg',
            ],
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
        $gateway = Mockery::mock(LlmGateway::class);
        $gateway->shouldReceive('transcribe')
            ->once()
            ->with($context['workspace']->id, 'private-voice-bytes', 'audio/ogg', ['language' => 'pt'], $conversation->id)
            ->andReturn('Transcrição privada.');
        $this->app->instance(LlmGateway::class, $gateway);

        $this->assertSame('Transcrição privada.', app(AudioMessageTranscriber::class)->transcribe($message));
        $this->assertSame('completed', $message->fresh()->payload['transcription_status']);
    }
}
