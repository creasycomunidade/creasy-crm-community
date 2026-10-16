<?php

namespace Tests\Feature\Whatsapp;

use App\Events\MessageReceived;
use App\Events\MessageStatusUpdated;
use App\Models\Workspace;
use App\Modules\Broadcasting\Jobs\SendCampaignMessageJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Services\CampaignPersonalizer;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\WhatsappGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WhatsappGatewayIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public static function providers(): array
    {
        return [['waha'], ['evolution_api']];
    }

    public function test_waha_operations_are_saved_exposed_and_enforced_in_inbox(): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account('waha', $context['workspace']->id);
        $staff = \App\Models\User::factory()->create([
            'role' => \App\Models\User::ROLE_CLIENT, 'client_role' => \App\Models\User::CLIENT_ROLE_STAFF,
            'client_id' => $context['client']->id, 'workspace_id' => $context['workspace']->id,
            'status' => \App\Models\User::STATUS_ACTIVE, 'email_verified_at' => now(),
        ]);
        $staff->workspaces()->syncWithoutDetaching([$context['workspace']->id]);
        $contact = Contact::factory()->create(['workspace_id' => $account->workspace_id, 'phone_e164' => '+5547999999999']);
        $conversation = Conversation::create(['workspace_id' => $account->workspace_id, 'contact_id' => $contact->id,
            'channel_account_id' => $account->id, 'status' => 'open']);
        $this->actingAs($staff)->get(route('client.inbox.show', $conversation))->assertForbidden();
        $operations = [
            'timezone' => 'America/Sao_Paulo', 'business_hours' => ['monday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00']],
            'human_access_mode' => 'selected', 'allowed_user_ids' => [$staff->id],
            'outside_hours_enabled' => true, 'outside_hours_message' => 'Retornaremos amanhã.', 'outside_hours_cooldown_hours' => 12,
        ];
        $this->actingAs($context['user'])->patch(route('client.inbox.setup.operations.update', $account), $operations)->assertSessionHasNoErrors();
        $this->assertSame($operations, $account->refresh()->meta_json['operations']);
        $this->get(route('client.inbox.setup'))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('gatewayAccounts.0.meta_json.operations.allowed_user_ids.0', $staff->id)
            ->where('gatewayAccounts.0.meta_json.operations.outside_hours_message', 'Retornaremos amanhã.')
            ->missing('gatewayAccounts.0.credentials'));
        $this->actingAs($staff)->get(route('client.inbox.show', $conversation))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->where('conversation.is_whatsapp_window_open', true));
        Http::fake(['*' => Http::response(['id' => 'human-reply'])]);
        $this->postJson(route('client.inbox.reply', $conversation), ['type' => 'text', 'body' => 'Olá, como posso ajudar?'])
            ->assertOk()->assertJsonPath('message.status', 'sent')->assertJsonPath('error', null);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sendText') && $request['session'] === 'selected');
        $this->actingAs($staff)->patch(route('client.inbox.setup.operations.update', $account), $operations)->assertForbidden();
    }

    public function test_waha_inbound_sends_outside_hours_reply_without_chatbot_once(): void
    {
        $account = $this->account('waha');
        $account->update(['meta_json' => ['operations' => [
            'timezone' => 'America/Sao_Paulo',
            'business_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                ->mapWithKeys(fn ($day) => [$day => ['enabled' => false, 'start' => '08:00', 'end' => '18:00']])->all(),
            'outside_hours_enabled' => true, 'outside_hours_message' => 'Estamos fechados.', 'outside_hours_cooldown_hours' => 12,
        ]]]);
        Event::fake([\App\Events\MessageSent::class]);
        Http::fake(['*' => Http::response(['id' => 'outside-hours'])]);
        foreach (['first', 'second'] as $id) {
            $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]), [
                'event' => 'message', 'session' => 'selected',
                'payload' => ['id' => $id, 'fromMe' => false, 'from' => '5547999999999@c.us', 'body' => 'Olá'],
            ])->assertOk();
        }
        $this->assertSame(2, Message::where('direction', 'in')->count());
        $this->assertSame(1, Message::where('direction', 'out')->where('status', 'sent')->count());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['text'] === 'Estamos fechados.' && $request['session'] === 'selected');
    }

    public function test_waha_lid_messages_are_visible_and_replyable_without_inventing_phone_numbers(): void
    {
        Event::fake([MessageReceived::class]);
        $account = $this->account('waha');
        Http::fake(['*/lids/*' => Http::response(['pn' => null]), '*/api/sendText' => Http::response(['id' => 'lid-reply'])]);
        $payload = ['event'=>'message', 'session'=>'selected', 'payload'=>['id'=>'lid-msg', 'from'=>'123456789012345@lid', 'fromMe'=>false, 'body'=>'Mensagem com identificador privado']];
        $url = route('webhooks.whatsapp-gateway.receive', ['token'=>str_repeat('a',64)]);
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $conversation = Conversation::firstOrFail();
        $this->assertNull($conversation->contact->phone_e164);
        $this->assertSame('123456789012345@lid', $conversation->external_thread_id);
        $this->assertDatabaseCount('messages',1);
        $message = Message::create(['conversation_id'=>$conversation->id,'channel'=>'whatsapp','direction'=>'out','type'=>'text','body'=>'Resposta','status'=>'queued']);
        app(\App\Modules\Whatsapp\Services\WhatsappDriver::class)->send($message);
        Http::assertSent(fn ($request) => str_ends_with($request->url(),'/api/sendText') && $request['chatId']==='123456789012345@lid');
        $this->assertSame('message',$account->refresh()->meta_json['last_webhook_event']);
    }

    public function test_gateway_webhook_accepts_authenticated_provider_without_browser_csrf(): void
    {
        $this->account('waha');
        $this->app['env'] = 'production'; // Laravel normally skips CSRF in tests.
        try {
            $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token'=>str_repeat('a',64)]), [
                'event'=>'diagnostic.ping','session'=>'selected','payload'=>[],
            ])->assertOk()->assertJsonPath('ok',true);
            $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token'=>str_repeat('b',64)]), [
                'event'=>'diagnostic.ping','session'=>'selected','payload'=>[],
            ])->assertNotFound();
        } finally { $this->app['env'] = 'testing'; }
    }

    public function test_noweb_reply_and_serialized_receipt_use_the_same_outbound_message(): void
    {
        Event::fake([\App\Events\MessageStatusUpdated::class]);
        $context = $this->createWorkspaceContext();
        $account = $this->account('waha', $context['workspace']->id);
        $contact = Contact::factory()->create(['workspace_id'=>$account->workspace_id,'phone_e164'=>'+5547999999999']);
        $conversation = Conversation::create(['workspace_id'=>$account->workspace_id,'channel_account_id'=>$account->id,'contact_id'=>$contact->id,'status'=>'open']);
        Http::fake(['*'=>Http::response(['key'=>['id'=>'ABC123','fromMe'=>true,'remoteJid'=>'5547999999999@s.whatsapp.net'],'message'=>['conversation'=>'Resposta']])]);
        $this->actingAs($context['user'])->postJson(route('client.inbox.reply',$conversation),['type'=>'text','body'=>'Resposta'])
            ->assertOk()->assertJsonPath('message.status','sent')->assertJsonPath('error',null);
        $message = $conversation->messages()->firstOrFail();
        $this->assertSame('waha:'.$account->id.':ABC123',$message->provider_message_id);
        $this->postJson(route('webhooks.whatsapp-gateway.receive',['token'=>str_repeat('a',64)]),[
            'event'=>'message.ack','session'=>'selected','payload'=>['id'=>'true_5547999999999@c.us_ABC123','ack'=>3],
        ])->assertOk();
        $this->assertSame('read',$message->refresh()->status);
        Http::assertSentCount(1);
    }

    public function test_waha_supports_browser_engine_ids_and_does_not_invent_missing_ids(): void
    {
        $this->assertSame('serialized', WhatsappGatewayClient::wahaMessageId(['id'=>['_serialized'=>'serialized']]));
        $this->assertSame('nested', WhatsappGatewayClient::wahaMessageId(['id'=>['id'=>'nested']]));
        $this->assertNull(WhatsappGatewayClient::wahaMessageId(['success'=>true]));
    }

    private function configure(string $provider): void
    {
        Http::preventStrayRequests();
        IntegrationConfig::create(['provider' => $provider, 'label' => $provider, 'mode' => 'live', 'enabled' => true,
            'credentials' => ['base_url' => 'https://1.1.1.1', 'api_key' => 'secret', 'edition' => 'plus']]);
    }

    public function test_phone_messages_and_media_are_imported_without_unread_or_api_duplicates(): void
    {
        Event::fake([MessageReceived::class, \App\Events\MessageSent::class]);
        $account = $this->account('waha');
        Http::fake(['*' => Http::response([], 404)]);
        $url = route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]);
        $payload = ['event' => 'message.any', 'session' => 'selected', 'payload' => [
            'id' => 'phone-1', 'fromMe' => true, 'from' => '5547888888888@c.us',
            'to' => '5547999999999@c.us', 'body' => 'Olá 👋', 'source' => 'app',
        ]];
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $message = Message::firstOrFail();
        $this->assertSame('out', $message->direction);
        $this->assertSame('Olá 👋', $message->body);
        $this->assertSame('5547999999999@c.us', $message->conversation->external_thread_id);
        $this->assertSame(0, (int) $message->conversation->unread_count);
        $this->assertNull($message->conversation->last_inbound_at);
        Event::assertNotDispatched(MessageReceived::class);
        Event::assertDispatchedTimes(\App\Events\MessageSent::class, 1);
        $payload['payload']['id'] = 'api-echo';
        $payload['payload']['source'] = 'api';
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('messages', 1);
        foreach (['image/jpeg' => 'image', 'audio/ogg' => 'audio', 'video/mp4' => 'video', 'application/pdf' => 'document'] as $mime => $type) {
            $payload['payload'] = ['id' => $type, 'fromMe' => false, 'from' => '5547999999999@c.us',
                'hasMedia' => true, 'media' => ['url' => 'http://localhost:3000/api/files/example', 'mimetype' => $mime]];
            $this->postJson($url, $payload)->assertOk();
            $received = Message::where('type', $type)->firstOrFail();
            $this->assertSame($type, $received->payload['media_id']);
            $this->assertSame($mime, $received->payload['mime_type']);
            $this->assertArrayNotHasKey('preview_url', $received->payload);
        }
    }

    public function test_gateway_media_is_authenticated_cached_and_range_readable(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $account = $this->account('waha', $context['workspace']->id);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII=');
        Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
        $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]), [
            'event' => 'message.any', 'session' => 'selected', 'payload' => [
                'id' => 'image-test', 'from' => '5547999999999@c.us', 'hasMedia' => true,
                'media' => ['url' => 'http://localhost:3000/api/files/image.png', 'mimetype' => 'image/png'],
            ],
        ])->assertOk();
        $message = Message::firstOrFail();
        $url = route('client.inbox.message-media', ['conversation' => $message->conversation, 'message' => $message->id]);
        $this->actingAs($context['user'])->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/api/files/image.png' && $request->hasHeader('X-Api-Key'));
        $this->get($url, ['Range' => 'bytes=0-7'])->assertStatus(206)->assertHeader('Content-Length', '8');
        Http::assertSentCount(1);
        $this->assertNotEmpty($message->refresh()->payload['private_media_path']);
    }

    public function test_gateway_normalizes_wrapped_evolution_media_and_reactions(): void
    {
        $parse = \App\Modules\Whatsapp\Services\GatewayMessageContent::class;
        [$type, $body, $payload] = $parse::parse(['key' => ['id' => 'img'], 'message' => [
            'ephemeralMessage' => ['message' => ['imageMessage' => ['caption' => 'Oi 😀', 'mimetype' => 'image/jpeg']]],
        ]], false);
        $this->assertSame('image', $type);
        $this->assertSame('Oi 😀', $body);
        $this->assertSame('img', $payload['gateway_media']['id']);
        [$type, $body, $payload] = $parse::parse(['reaction' => ['text' => '👍', 'messageId' => 'target']], true);
        $this->assertSame('reaction', $type);
        $this->assertSame('👍', $payload['reaction']['emoji']);
        [$type, $body, $payload] = $parse::parse(['reaction' => ['text' => '', 'messageId' => 'target']], true);
        $this->assertSame('removed', $payload['reaction']['action']);
    }

    public function test_gateway_media_rejects_non_file_paths_without_exposing_credentials(): void
    {
        $account = $this->account('waha');
        Http::fake();
        try {
            WhatsappGatewayClient::forAccount($account)->downloadMedia(['url' => 'https://other.example/api/sessions']);
            $this->fail('Non-file paths must be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_evolution_media_is_downloaded_by_message_id_and_decoded(): void
    {
        $account = $this->account('evolution_api');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII=');
        Http::fake(['*' => Http::response(['base64' => base64_encode($png)])]);
        $download = WhatsappGatewayClient::forAccount($account)->downloadMedia(['id' => 'media-id']);
        $this->assertSame($png, $download['bytes']);
        $this->assertSame('image/png', $download['mime_type']);
        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/chat/getBase64FromMediaMessage/selected'
            && $request['message']['key']['id'] === 'media-id' && $request->hasHeader('apikey', 'secret'));
    }

    public function test_noweb_phone_messages_without_to_use_remote_chat_and_preserve_inbound_state(): void
    {
        Event::fake([MessageReceived::class, \App\Events\MessageSent::class]);
        $account = $this->account('waha');
        Http::fake(['*' => Http::response([], 404)]);
        $url = route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]);
        $this->postJson($url, ['event' => 'message', 'session' => 'selected', 'payload' => [
            'id' => 'false_5547999999999@c.us_IN', 'fromMe' => false, 'from' => '5547999999999@c.us', 'body' => 'Oi',
        ]])->assertOk();
        $conversation = Conversation::firstOrFail();
        $inboundAt = $conversation->last_inbound_at->toISOString();
        $this->travel(2)->minutes();
        $payload = ['event' => 'message.any', 'session' => 'selected', 'payload' => [
            'id' => 'true_5547999999999@c.us_PHONE', 'fromMe' => true, 'from' => '5547999999999@c.us',
            'source' => 'app', 'body' => 'Resposta pelo celular 👍',
            '_data' => ['key' => ['remoteJid' => '5547999999999@s.whatsapp.net', 'fromMe' => true, 'id' => 'PHONE']],
        ]];
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $out = Message::where('direction', 'out')->firstOrFail();
        $this->assertSame($conversation->id, $out->conversation_id);
        $this->assertSame('Resposta pelo celular 👍', $out->body);
        $conversation->refresh();
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame($inboundAt, $conversation->last_inbound_at->toISOString());
        Event::assertDispatchedTimes(\App\Events\MessageSent::class, 1);
        Event::assertDispatchedTimes(MessageReceived::class, 1);
        $payload['payload']['id'] = 'true_5547999999999@c.us_PHOTO';
        $payload['payload']['body'] = '';
        $payload['payload']['hasMedia'] = true;
        $payload['payload']['media'] = ['mimetype' => 'image/jpeg', 'url' => null];
        unset($payload['payload']['_data']);
        $this->postJson($url, $payload)->assertOk();
        $photo = Message::where('type', 'image')->firstOrFail();
        $this->assertSame('out', $photo->direction);
        $this->assertSame('5547999999999@c.us', $photo->payload['gateway_media']['chat_id']);
    }

    public function test_waha_chat_id_handles_noweb_and_webjs_without_using_own_number(): void
    {
        $this->assertSame('5547999999999@c.us', WhatsappGatewayClient::wahaChatId([
            'fromMe' => true, 'from' => '5547888888888@c.us', 'to' => '5547999999999@c.us',
        ]));
        $this->assertSame('5547999999999@lid', WhatsappGatewayClient::wahaChatId([
            'fromMe' => true, 'from' => '5547999999999@lid',
        ]));
        $this->assertSame('12345678@g.us', WhatsappGatewayClient::wahaChatId([
            'fromMe' => true, '_data' => ['key' => ['remoteJid' => '12345678@g.us']], 'from' => '5547999999999@c.us',
        ]));
    }

    public function test_waha_sends_image_and_caption_together_without_text_only_error(): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account('waha', $context['workspace']->id);
        $contact = Contact::factory()->create(['workspace_id' => $account->workspace_id, 'phone_e164' => '+5547999999999']);
        $conversation = Conversation::create(['workspace_id' => $account->workspace_id, 'contact_id' => $contact->id,
            'channel_account_id' => $account->id, 'status' => 'open']);
        config(['filesystems.disks.public.url' => 'https://1.1.1.1/storage']);
        \Illuminate\Support\Facades\Storage::fake('public', ['url' => 'https://1.1.1.1/storage']);
        $image = \Illuminate\Http\UploadedFile::fake()->createWithContent('pedidos.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII='));
        Http::fake(['*' => Http::response(['key' => ['id' => 'IMAGE-SENT']])]);
        $this->actingAs($context['user'])->postJson(route('client.inbox.reply', $conversation), [
            'type' => 'image', 'body' => 'Confira estes pedidos 👋',
            'attachment' => $image,
        ])->assertOk()->assertJsonPath('message.status', 'sent')->assertJsonPath('error', null);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sendImage')
            && $request['chatId'] === '5547999999999@c.us' && $request['session'] === 'selected'
            && $request['file']['mimetype'] === 'image/png' && $request['file']['filename'] === 'pedidos.png'
            && str_contains($request['caption'], 'Confira estes pedidos 👋'));
        $this->assertSame('waha:'.$account->id.':IMAGE-SENT', Message::latest('id')->first()->provider_message_id);
    }

    public function test_waha_image_send_does_not_retry_an_ambiguous_gateway_response(): void
    {
        $account = $this->account('waha');
        Http::fake(['*' => Http::response([])]);
        try {
            WhatsappGatewayClient::forAccount($account)->sendImage('123456789012345@lid', 'https://1.1.1.1/photo.jpg', '', 'photo.jpg', 'image/jpeg');
            $this->fail('Missing provider ID must be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Verifique o envio', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chatId'] === '123456789012345@lid' && $request['caption'] === '');
    }

    public function test_audio_is_saved_privately_on_receipt_before_gateway_file_expires(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        Event::fake([MessageReceived::class]);
        $account = $this->account('waha');
        $bytes = 'OggS'.str_repeat('audio-test', 20);
        Http::fake(['*' => Http::response($bytes, 200)]);
        $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]), [
            'event' => 'message.any', 'session' => 'selected', 'payload' => [
                'id' => 'audio-persist', 'from' => '5547999999999@c.us', 'hasMedia' => true,
                'media' => ['url' => '/api/files/selected/audio.oga', 'mimetype' => 'audio/ogg'],
            ],
        ])->assertOk();
        $message = Message::where('type', 'audio')->firstOrFail();
        $path = $message->payload['private_media_path'];
        $this->assertSame($bytes, \Illuminate\Support\Facades\Storage::disk('local')->get($path));
        Http::fake(['*' => Http::response([], 404)]);
        app(\App\Modules\Whatsapp\Services\GatewayMediaStorage::class)->cache($message);
        Http::assertNothingSent();
    }

    public function test_expired_gateway_file_is_refetched_once_from_message_history(): void
    {
        $account = $this->account('waha');
        Http::fake([
            '*/api/files/expired.oga' => Http::response('', 404),
            '*/messages/*' => Http::response(['media' => ['url' => '/api/files/fresh.oga']]),
            '*/api/files/fresh.oga' => Http::response('OggS-audio'),
        ]);
        $result = WhatsappGatewayClient::forAccount($account)->downloadMedia([
            'url' => '/api/files/expired.oga', 'chat_id' => '5547999999999@c.us', 'id' => 'audio-id',
        ]);
        $this->assertSame('OggS-audio', $result['bytes']);
        Http::assertSentCount(3);
    }

    private function account(string $provider, ?int $workspaceId = null): ChannelAccount
    {
        $this->configure($provider);

        return ChannelAccount::create(['workspace_id' => $workspaceId ?? Workspace::factory()->create()->id,
            'channel' => 'whatsapp', 'provider' => $provider, 'display_name' => 'Comercial', 'status' => 'active',
            'webhook_token_hash' => hash('sha256', str_repeat('a', 64)),
            'credentials' => ['base_url' => 'https://1.1.1.1', 'api_key' => 'secret', 'instance' => 'selected']]);
    }

    #[DataProvider('providers')]
    public function test_workspace_admin_creates_session_and_encrypted_credentials(string $provider): void
    {
        $context = $this->createWorkspaceContext();
        $this->configure($provider);
        Http::fake(['*' => Http::response(['name' => 'created'])]);
        $this->actingAs($context['user'])->post(route('client.whatsapp.gateway.store'), ['provider' => $provider, 'name' => 'Comercial'])
            ->assertSessionHas('success');
        $account = ChannelAccount::where('provider', $provider)->firstOrFail();
        $this->assertSame($context['workspace']->id, $account->workspace_id);
        $this->assertSame('inactive', $account->status);
        $this->assertStringNotContainsString('secret', DB::table('channel_accounts')->where('id', $account->id)->value('credentials'));
        $this->assertArrayNotHasKey('credentials', $account->toArray());
        Http::assertSent(function ($request) use ($provider, $account) {
            $hook = $provider === 'waha' ? $request['config']['webhooks'][0]['url'] : $request['webhook']['url'];

            return $request->hasHeader($provider === 'waha' ? 'X-Api-Key' : 'apikey', 'secret')
                && $request[$provider === 'waha' ? 'name' : 'instanceName'] === $account->credentials['instance']
                && hash('sha256', basename($hook)) === $account->webhook_token_hash;
        });
    }

    #[DataProvider('providers')]
    public function test_campaign_uses_selected_gateway_and_respects_opt_out(string $provider): void
    {
        $account = $this->account($provider);
        $contact = Contact::factory()->create(['workspace_id' => $account->workspace_id, 'first_name' => 'Ana',
            'phone_e164' => '+5547999999999', 'opt_in_whatsapp' => true]);
        $campaign = Campaign::factory()->create(['workspace_id' => $account->workspace_id, 'channel' => 'whatsapp',
            'channel_account_id' => $account->id, 'status' => 'sending', 'payload_json' => ['body' => 'Olá {{contact.first_name}}!']]);
        $recipient = CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => 'queued']);
        Http::fake(['*' => Http::response($provider === 'waha' ? ['id' => 'msg-1'] : ['key' => ['id' => 'msg-1']])]);
        (new SendCampaignMessageJob($campaign->id, $contact->id))->handle(app(CampaignPersonalizer::class));
        $this->assertSame('sent', $recipient->refresh()->status);
        $this->assertSame($provider.':'.$account->id.':msg-1', $recipient->provider_message_id);
        Http::assertSent(fn ($request) => $request['text'] === 'Olá Ana!'
            && ($provider === 'waha'
                ? $request['session'] === 'selected' && $request['chatId'] === '5547999999999@c.us'
                : str_ends_with($request->url(), '/message/sendText/selected') && $request['number'] === '5547999999999'));
        $this->assertDatabaseHas('messages', ['provider_message_id' => $recipient->provider_message_id, 'body' => 'Olá Ana!']);
        (new SendCampaignMessageJob($campaign->id, $contact->id))->handle(app(CampaignPersonalizer::class));
        Http::assertSentCount(1);
        $contact->update(['opt_in_whatsapp' => false]);
        $recipient->update(['status' => 'queued']);
        (new SendCampaignMessageJob($campaign->id, $contact->id))->handle(app(CampaignPersonalizer::class));
        $this->assertSame('opted_out', $recipient->refresh()->failed_reason);
        Http::assertSentCount(1);
    }

    #[DataProvider('providers')]
    public function test_webhook_authentication_replays_and_marketing_consent(string $provider): void
    {
        Event::fake([MessageReceived::class]);
        $account = $this->account($provider);
        $url = route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]);
        $payload = $provider === 'waha'
            ? ['event' => 'message', 'session' => 'selected', 'payload' => ['id' => 'in-1', 'fromMe' => false, 'from' => '5547999999999@c.us', 'body' => 'Olá']]
            : ['event' => 'messages.upsert', 'instance' => 'selected', 'data' => ['key' => ['id' => 'in-1', 'fromMe' => false, 'remoteJid' => '5547999999999@s.whatsapp.net'], 'message' => ['conversation' => 'Olá']]];
        $this->postJson($url, array_merge($payload, [$provider === 'waha' ? 'session' : 'instance' => 'other']))->assertForbidden();
        $this->postJson(route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('b', 64)]), $payload)->assertNotFound();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(1, Conversation::firstOrFail()->unread_count);
        $this->assertFalse((bool) Contact::firstOrFail()->opt_in_whatsapp);
        Event::assertDispatchedTimes(MessageReceived::class, 1);
    }

    #[DataProvider('providers')]
    public function test_other_workspace_cannot_manage_connection(string $provider): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account($provider);
        foreach (['status', 'qr'] as $action) {
            $this->actingAs($context['user'])->getJson(route('client.whatsapp.gateway.'.$action, $account->id))->assertForbidden();
        }
        $this->actingAs($context['user'])->post(route('client.whatsapp.gateway.reconnect', $account->id))->assertForbidden();
        $this->actingAs($context['user'])->delete(route('client.whatsapp.gateway.destroy', $account->id))->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('providers')]
    public function test_gateway_does_not_accept_success_without_message_id(string $provider): void
    {
        $account = $this->account($provider);
        Http::fake(['*' => Http::response(['success' => true])]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não confirmou');
        WhatsappGatewayClient::send($account, '+5547999999999', 'Teste');
    }

    #[DataProvider('providers')]
    public function test_qr_and_status(string $provider): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account($provider, $context['workspace']->id);
        $png = base64_encode("\x89PNG\r\n\x1a\nexample");
        Http::fake(['*' => Http::sequence()
            ->push($provider === 'waha' ? ['data' => $png] : ['base64' => 'data:image/png;base64,'.$png])
            ->push($provider === 'waha' ? ['status' => 'WORKING'] : ['instance' => ['state' => 'open']])]);
        $this->actingAs($context['user'])->getJson(route('client.whatsapp.gateway.qr', $account->id))
            ->assertOk()->assertJsonPath('qr', 'data:image/png;base64,'.$png);
        $this->actingAs($context['user'])->getJson(route('client.whatsapp.gateway.status', $account->id))
            ->assertOk()->assertJsonPath('status', 'active');
    }

    #[DataProvider('providers')]
    public function test_delivery_receipts_update_campaign_without_regressing_read_status(string $provider): void
    {
        Event::fake([MessageStatusUpdated::class]);
        $account = $this->account($provider);
        $contact = Contact::factory()->create(['workspace_id' => $account->workspace_id]);
        $conversation = Conversation::create(['workspace_id' => $account->workspace_id, 'channel_account_id' => $account->id, 'contact_id' => $contact->id]);
        $fullId = $provider.':'.$account->id.':receipt-1';
        $message = Message::create(['conversation_id' => $conversation->id, 'direction' => 'out', 'channel' => 'whatsapp', 'type' => 'text',
            'body' => 'Teste', 'status' => 'sent', 'provider_message_id' => $fullId, 'sent_by' => 'broadcast']);
        $campaign = Campaign::factory()->create(['workspace_id' => $account->workspace_id, 'channel' => 'whatsapp', 'channel_account_id' => $account->id]);
        $recipient = CampaignRecipient::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id,
            'status' => 'sent', 'provider_message_id' => $fullId]);
        $url = route('webhooks.whatsapp-gateway.receive', ['token' => str_repeat('a', 64)]);
        foreach (['read', 'delivered'] as $status) {
            $payload = $provider === 'waha'
                ? ['event' => 'message.ack', 'session' => 'selected', 'payload' => ['id' => 'receipt-1', 'ack' => $status === 'read' ? 3 : 2]]
                : ['event' => 'messages.update', 'instance' => 'selected', 'data' => [['keyId' => 'receipt-1', 'status' => $status === 'read' ? 'READ' : 'DELIVERY_ACK']]];
            $this->postJson($url, $payload)->assertOk();
        }
        $this->assertSame('read', $message->refresh()->status);
        $this->assertSame('read', $recipient->refresh()->status);
        $this->assertNotNull($recipient->read_at);
        $this->assertNotNull($recipient->delivered_at);
        Event::assertDispatchedTimes(MessageStatusUpdated::class, 1);
    }

    public function test_current_waha_creates_isolated_sessions_even_with_legacy_core_setting(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $this->configure('waha');
        IntegrationConfig::where('provider', 'waha')->firstOrFail()->update([
            'credentials' => ['base_url' => 'https://1.1.1.1', 'api_key' => 'secret', 'edition' => 'core'],
        ]);
        Http::fake(['*' => Http::response(['name' => 'default'])]);
        $this->actingAs($first['user'])->post(route('client.whatsapp.gateway.store'), ['provider' => 'waha', 'name' => 'Primeira'])->assertSessionHas('success');
        $this->actingAs($second['user'])->post(route('client.whatsapp.gateway.store'), ['provider' => 'waha', 'name' => 'Segunda'])->assertSessionHas('success');
        $accounts = ChannelAccount::where('provider', 'waha')->get();
        $this->assertCount(2, $accounts);
        $this->assertNotSame($accounts[0]->credentials['instance'], $accounts[1]->credentials['instance']);
        foreach ($accounts as $account) {
            $this->assertStringStartsWith('creasy-crm-w'.$account->workspace_id.'-', $account->credentials['instance']);
        }
        Http::assertSentCount(2);
    }

    public function test_disconnected_or_disabled_gateway_never_sends(): void
    {
        $account = $this->account('waha');
        IntegrationConfig::where('provider', 'waha')->update(['enabled' => false]);
        try {
            WhatsappGatewayClient::send($account, '+5547999999999', 'Teste');
            $this->fail('A disabled gateway must not send.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('desativada', $e->getMessage());
        }
        Http::assertNothingSent();
    }
}
