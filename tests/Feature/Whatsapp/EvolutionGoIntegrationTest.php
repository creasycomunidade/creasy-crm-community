<?php

namespace Tests\Feature\Whatsapp;

use App\Events\MessageReceived;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Services\ConnectionTester;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\EvolutionGoClient;
use App\Modules\Whatsapp\Services\WhatsappDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvolutionGoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_URL = 'https://1.1.1.1';

    private function configureEvolutionGo(): void
    {
        IntegrationConfig::create([
            'provider' => 'evolution_go',
            'label' => 'Evolution Go',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => [
                'base_url' => self::BASE_URL,
                'global_api_key' => 'global-test-key',
            ],
        ]);
    }

    public function test_opening_inbox_does_not_import_available_groups(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:groups-instance',
            'display_name' => 'WhatsApp Comercial',
            'credentials' => ['instance_token' => 'groups-key', 'instance_id' => 'groups-instance'],
            'meta_json' => ['advanced_settings' => ['ignore_groups' => false]],
            'status' => 'active',
        ]);

        Http::fake([
            self::BASE_URL.'/group/list' => Http::response([
                'data' => [
                    ['JID' => '120363000000000123@g.us', 'Name' => 'Equipe Comercial'],
                    ['JID' => '120363000000000456@g.us', 'Name' => 'Suporte'],
                ],
                'message' => 'success',
            ]),
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inbox/Index')
                ->missing('whatsappGroups')
                ->has('conversations.data', 0));

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        Http::assertNothingSent();
    }

    public function test_connection_test_explains_when_free_license_is_not_activated(): void
    {
        $this->configureEvolutionGo();
        Http::fake([
            self::BASE_URL.'/license/status' => Http::response([
                'instance_id' => 'test-instance',
                'status' => 'inactive',
            ]),
        ]);

        $result = app(ConnectionTester::class)->test(
            IntegrationConfig::where('provider', 'evolution_go')->firstOrFail()
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('licença comunitária gratuita', $result['message']);
        $this->assertSame(self::BASE_URL.'/manager/login', $result['action_url']);
    }

    public function test_workspace_admin_can_create_an_isolated_qr_instance(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();

        Http::fake([
            self::BASE_URL.'/instance/create' => Http::response([
                'message' => 'success',
                'data' => ['id' => 'instance-uuid-1', 'name' => 'workspace-instance'],
            ]),
            self::BASE_URL.'/instance/connect' => Http::response([
                'message' => 'success',
                'data' => ['webhookUrl' => 'https://respondesk.test/webhook'],
            ]),
        ]);

        $this->actingAs($context['user'])
            ->post(route('client.whatsapp.evolution-go.store'), [
                'name' => 'WhatsApp Comercial',
                'risk_acknowledged' => true,
            ])
            ->assertSessionHas('success');

        $account = ChannelAccount::where('provider', 'evolution_go')->firstOrFail();
        $this->assertSame($context['workspace']->id, $account->workspace_id);
        $this->assertSame('WhatsApp Comercial', $account->display_name);
        $this->assertSame('instance-uuid-1', $account->credentials['instance_id']);
        $this->assertNotEmpty($account->webhook_token_hash);

        $rawCredentials = (string) DB::table('channel_accounts')
            ->where('id', $account->id)
            ->value('credentials');
        $this->assertStringNotContainsString(
            (string) $account->credentials['instance_token'],
            $rawCredentials
        );

        Http::assertSent(function ($request) use ($account) {
            if ($request->url() !== self::BASE_URL.'/instance/connect') {
                return false;
            }

            return $request->hasHeader('apikey', $account->credentials['instance_token'])
                && str_contains((string) $request['webhookUrl'], '/webhooks/evolution-go/')
                && in_array('CONNECTION', $request['subscribe'], true);
        });
    }

    public function test_qr_endpoint_normalises_top_level_bare_base64_from_gateway(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-qr',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'qr-key', 'instance_id' => 'instance-qr'],
            'status' => 'inactive',
        ]);
        $png = base64_encode("\x89PNG\r\n\x1a\nmock-image");

        Http::fake([
            self::BASE_URL.'/instance/qr' => Http::response([
                'message' => 'success',
                'qrcode' => $png,
                'code' => '2@raw-whatsapp-qr-content',
            ]),
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('client.whatsapp.evolution-go.qr', $account))
            ->assertOk()
            ->assertJson([
                'qrcode' => 'data:image/png;base64,'.$png,
                'code' => '2@raw-whatsapp-qr-content',
            ]);
    }

    public function test_qr_endpoint_accepts_nested_data_uri_shape(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-qr-nested',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'qr-nested-key', 'instance_id' => 'instance-qr-nested'],
            'status' => 'inactive',
        ]);
        $image = 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nmock-image");

        Http::fake([
            self::BASE_URL.'/instance/qr' => Http::response([
                'message' => 'success',
                'data' => ['code' => $image],
            ]),
        ]);

        $this->actingAs($context['user'])
            ->getJson(route('client.whatsapp.evolution-go.qr', $account))
            ->assertOk()
            ->assertJson(['qrcode' => $image, 'code' => null]);
    }

    public function test_reconnect_replaces_stale_gateway_instance_before_starting_new_qr_flow(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-stale',
            'webhook_token_hash' => hash('sha256', 'old-webhook-token'),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'stale-key', 'instance_id' => 'instance-stale'],
            'meta_json' => ['connection_status' => 'close'],
            'status' => 'inactive',
        ]);

        Http::fake([
            self::BASE_URL.'/instance/delete/instance-stale' => Http::response(['message' => 'success']),
            self::BASE_URL.'/instance/create' => Http::response([
                'message' => 'success',
                'data' => ['id' => 'instance-fresh'],
            ]),
            self::BASE_URL.'/instance/connect' => Http::response(['message' => 'success']),
        ]);

        $this->actingAs($context['user'])
            ->post(route('client.whatsapp.evolution-go.reconnect', $account))
            ->assertSessionHas('success');

        $requests = Http::recorded();
        $this->assertCount(3, $requests);
        $this->assertSame('DELETE', $requests[0][0]->method());
        $this->assertSame(self::BASE_URL.'/instance/delete/instance-stale', $requests[0][0]->url());
        $this->assertSame('POST', $requests[1][0]->method());
        $this->assertSame(self::BASE_URL.'/instance/create', $requests[1][0]->url());
        $connectRequest = $requests[count($requests) - 1][0];
        $this->assertSame('POST', $connectRequest->method());
        $this->assertSame(self::BASE_URL.'/instance/connect', $connectRequest->url());
        $this->assertStringContainsString('/webhooks/evolution-go/', (string) $connectRequest['webhookUrl']);

        $account->refresh();
        $this->assertSame('inactive', $account->status);
        $this->assertSame('connecting', $account->meta_json['connection_status']);
        $this->assertNull($account->meta_json['last_error']);
        $this->assertNotSame(hash('sha256', 'old-webhook-token'), $account->webhook_token_hash);
        $this->assertSame('instance-fresh', $account->credentials['instance_id']);
        $this->assertNotSame('stale-key', $account->credentials['instance_token']);
    }

    public function test_reconnect_returns_json_and_rotates_stale_instance_credentials(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:missing-instance',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'missing-key', 'instance_id' => 'missing-instance'],
            'status' => 'error',
        ]);

        Http::fake([
            self::BASE_URL.'/instance/delete/missing-instance' => Http::response(['error' => 'record not found'], 404),
            self::BASE_URL.'/instance/create' => Http::response(['data' => ['id' => 'replacement-instance']]),
            self::BASE_URL.'/instance/connect' => Http::response(['message' => 'success']),
        ]);

        $this->actingAs($context['user'])
            ->postJson(route('client.whatsapp.evolution-go.reconnect', $account))
            ->assertOk()
            ->assertJson(['success' => true]);

        $account->refresh();
        $this->assertSame('replacement-instance', $account->credentials['instance_id']);
        $this->assertNotSame('missing-key', $account->credentials['instance_token']);
        $this->assertSame('inactive', $account->status);
    }

    public function test_timeout_is_returned_as_disconnected_error_and_marks_account(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:timeout-instance',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'timeout-key', 'instance_id' => 'timeout-instance'],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+5511988887777',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => '5511988887777',
            'status' => 'open',
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->actingAs($context['user'])
            ->postJson(route('client.inbox.reply', $conversation), [
                'body' => 'Mensagem de teste',
                'type' => 'text',
            ])
            ->assertOk()
            ->assertJson(['error_code' => 'whatsapp_disconnected'])
            ->assertJsonPath('message.status', 'failed');

        $account->refresh();
        $this->assertSame('error', $account->status);
        $this->assertSame('disconnected', $account->meta_json['connection_status']);
    }

    public function test_current_evolution_go_webhook_creates_and_deduplicates_inbound_message(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('a', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-1',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'instance-key', 'instance_id' => 'instance-1'],
            'meta_json' => ['instance_name' => 'respondesk-w1-test'],
            'status' => 'active',
        ]);

        $payload = [
            'event' => 'Message',
            'instanceName' => 'respondesk-w1-test',
            'instanceToken' => 'must-never-be-persisted',
            'data' => [
                'Info' => [
                    'ID' => '3EB0EVOLUTION1',
                    'Chat' => '5511999999999@s.whatsapp.net',
                    'IsFromMe' => false,
                    'PushName' => 'João Silva',
                    'Timestamp' => '2026-07-27T12:00:00-03:00',
                ],
                'Message' => ['conversation' => 'Olá pelo WhatsApp'],
            ],
        ];

        $url = route('webhooks.evolution-go.receive', ['token' => $token]);
        $this->postJson($url, $payload)->assertOk()->assertJson(['received' => true]);
        $this->postJson($url, $payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseCount('messages', 1);
        $message = Message::firstOrFail();
        $this->assertSame('Olá pelo WhatsApp', $message->body);
        $this->assertSame('evogo:'.$account->id.':3EB0EVOLUTION1', $message->provider_message_id);
        $this->assertSame('in', $message->direction);

        $contact = Contact::firstOrFail();
        $this->assertSame('+5511999999999', $contact->phone_e164);
        $this->assertSame('João', $contact->first_name);
        $this->assertSame('Silva', $contact->last_name);
        $this->assertTrue($contact->opt_in_whatsapp);

        $conversation = Conversation::firstOrFail();
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('5511999999999@s.whatsapp.net', $conversation->external_thread_id);
        $this->assertArrayNotHasKey('instanceToken', $message->payload['evolution_go']);
        Event::assertDispatchedOnce(MessageReceived::class);
    }

    public function test_outbound_webhook_echo_does_not_replace_the_lead_name_with_the_connected_account_name(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('b', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-name',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'instance-key', 'instance_id' => 'instance-name'],
            'meta_json' => ['instance_name' => 'respondesk-name'],
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+5547999999999',
            'first_name' => 'Aurora',
            'last_name' => 'Tech Solutions',
            'source' => 'manual',
            'custom_fields' => [
                'evolution_go_jids' => ['5547999999999@s.whatsapp.net'],
            ],
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-name',
            'data' => [
                'Info' => [
                    'ID' => 'OUTBOUND-NAME-1',
                    'Chat' => '5547999999999@s.whatsapp.net',
                    'IsFromMe' => true,
                    'PushName' => 'Rick Tech Solutions',
                    'Timestamp' => '2026-07-29T12:00:00-03:00',
                ],
                'Message' => ['conversation' => 'Mensagem enviada'],
            ],
        ])->assertOk();

        $contact->refresh();
        $this->assertSame('Aurora', $contact->first_name);
        $this->assertSame('Tech Solutions', $contact->last_name);
        $this->assertDatabaseHas('messages', [
            'direction' => 'out',
            'provider_message_id' => 'evogo:'.$account->id.':OUTBOUND-NAME-1',
        ]);
    }

    public function test_inbound_profile_name_does_not_replace_an_existing_lead_name(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('e', 64);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-curated-name',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'instance-key', 'instance_id' => 'instance-curated-name'],
            'meta_json' => ['instance_name' => 'respondesk-curated-name'],
            'status' => 'active',
        ]);
        $contact = Contact::create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+5547888888888',
            'first_name' => 'Cliente',
            'last_name' => 'Personalizado',
            'source' => 'manual',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-curated-name',
            'data' => [
                'Info' => [
                    'ID' => 'INBOUND-NAME-1',
                    'Chat' => '5547888888888@s.whatsapp.net',
                    'IsFromMe' => false,
                    'PushName' => 'Nome do WhatsApp',
                    'Timestamp' => '2026-07-29T12:00:00-03:00',
                ],
                'Message' => ['conversation' => 'Olá'],
            ],
        ])->assertOk();

        $contact->refresh();
        $this->assertSame('Cliente', $contact->first_name);
        $this->assertSame('Personalizado', $contact->last_name);
    }

    public function test_webhook_normalises_device_jid_and_driver_replies_to_the_exact_recipient(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $token = str_repeat('c', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-device',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'device-key', 'instance_id' => 'instance-device'],
            'meta_json' => ['instance_name' => 'respondesk-device'],
            'status' => 'active',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-device',
            'data' => [
                'Info' => [
                    'ID' => '3EB0DEVICE1',
                    'Chat' => '+554733828981:12@s.whatsapp.net',
                    'IsFromMe' => false,
                ],
                'Message' => ['conversation' => 'Mensagem recebida'],
            ],
        ])->assertOk();

        $conversation = Conversation::firstOrFail();
        $this->assertSame('554733828981@s.whatsapp.net', $conversation->external_thread_id);
        $this->assertSame('+554733828981', $conversation->contact->phone_e164);

        $outbound = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Resposta',
            'status' => 'queued',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);
        Http::fake([
            self::BASE_URL.'/send/text' => Http::response([
                'message' => 'success',
                'data' => ['Info' => ['ID' => '3EB0DEVICE2']],
            ]),
        ]);

        app(WhatsappDriver::class)->send($outbound);

        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/send/text'
            && $request['number'] === '554733828981@s.whatsapp.net'
            && $request['formatJid'] === false);
    }

    public function test_webhook_unifies_lid_and_phone_number_messages_in_one_conversation(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('d', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-lid',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'lid-key', 'instance_id' => 'instance-lid'],
            'meta_json' => ['instance_name' => 'respondesk-lid'],
            'status' => 'active',
        ]);
        $url = route('webhooks.evolution-go.receive', ['token' => $token]);

        $this->postJson($url, [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-lid',
            'data' => [
                'Info' => [
                    'ID' => 'LID-MESSAGE-1',
                    'Chat' => '176543210987654@lid',
                    'Sender' => '176543210987654@lid',
                    'SenderAlt' => '5511998877665@s.whatsapp.net',
                    'IsFromMe' => false,
                ],
                'Message' => ['conversation' => 'Primeira mensagem'],
            ],
        ])->assertOk();

        $this->postJson($url, [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-lid',
            'data' => [
                'Info' => [
                    'ID' => 'LID-MESSAGE-2',
                    'Chat' => '5511998877665@s.whatsapp.net',
                    'Sender' => '5511998877665@s.whatsapp.net',
                    'SenderAlt' => '176543210987654@lid',
                    'IsFromMe' => false,
                ],
                'Message' => ['conversation' => 'Segunda mensagem'],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $contact = Contact::firstOrFail();
        $this->assertSame('+5511998877665', $contact->phone_e164);
        $this->assertEqualsCanonicalizing([
            '176543210987654@lid',
            '5511998877665@s.whatsapp.net',
        ], $contact->custom_fields['evolution_go_jids']);
        $this->assertSame('5511998877665@s.whatsapp.net', Conversation::firstOrFail()->external_thread_id);
    }

    public function test_webhook_reuses_a_lid_only_contact_without_faking_a_phone_number(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('e', 64);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-lid-only',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'lid-only-key', 'instance_id' => 'instance-lid-only'],
            'meta_json' => ['instance_name' => 'respondesk-lid-only'],
            'status' => 'active',
        ]);
        $url = route('webhooks.evolution-go.receive', ['token' => $token]);

        foreach (['LID-ONLY-1', 'LID-ONLY-2'] as $id) {
            $this->postJson($url, [
                'event' => 'MESSAGE',
                'instanceName' => 'respondesk-lid-only',
                'data' => [
                    'Info' => [
                        'ID' => $id,
                        'Chat' => '176500000000001@lid',
                        'Sender' => '176500000000001@lid',
                        'IsFromMe' => false,
                    ],
                    'Message' => ['conversation' => 'Olá'],
                ],
            ])->assertOk();
        }

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->assertNull(Contact::firstOrFail()->phone_e164);
    }

    public function test_webhook_persists_image_and_audio_base64_for_browser_playback(): void
    {
        Storage::fake('public');
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('f', 64);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-media',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'media-key', 'instance_id' => 'instance-media'],
            'meta_json' => ['instance_name' => 'respondesk-media'],
            'status' => 'active',
        ]);
        $url = route('webhooks.evolution-go.receive', ['token' => $token]);
        $binary = 'binary-media-content';

        foreach ([
            ['id' => 'MEDIA-IMAGE-1', 'key' => 'imageMessage', 'mime' => 'image/jpeg', 'type' => 'image'],
            ['id' => 'MEDIA-AUDIO-1', 'key' => 'audioMessage', 'mime' => 'audio/ogg; codecs=opus', 'type' => 'audio'],
        ] as $media) {
            $this->postJson($url, [
                'event' => 'MESSAGE',
                'instanceName' => 'respondesk-media',
                'data' => [
                    'Info' => [
                        'ID' => $media['id'],
                        'Chat' => '5511987654321@s.whatsapp.net',
                        'IsFromMe' => false,
                    ],
                    'Message' => [
                        $media['key'] => ['mimetype' => $media['mime']],
                        'base64' => base64_encode($binary),
                        'mimetype' => $media['mime'],
                    ],
                ],
            ])->assertOk();

            $message = Message::where('type', $media['type'])->firstOrFail();
            $this->assertStringStartsWith('/storage/message-media/evolution-go/', $message->payload['preview_url']);
            $this->assertArrayNotHasKey('base64', $message->payload['evolution_go']['Message']);
            $storedPath = str_replace('/storage/', '', $message->payload['preview_url']);
            Storage::disk('public')->assertExists($storedPath);
            $this->assertSame($binary, Storage::disk('public')->get($storedPath));
        }
    }

    public function test_ignored_group_message_never_becomes_a_private_conversation_or_auto_reply(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('c', 64);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-ignore-groups',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'group-key'],
            'meta_json' => [
                'instance_name' => 'respondesk-ignore-groups',
                'advanced_settings' => ['ignore_groups' => true],
            ],
            'status' => 'active',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-ignore-groups',
            'data' => [
                'Info' => [
                    'ID' => 'GROUP-MESSAGE-1',
                    'Chat' => '120363000000000000@g.us',
                    'Sender' => '5511987654321@s.whatsapp.net',
                    'IsFromMe' => false,
                ],
                'Message' => ['conversation' => 'Mensagem recebida no grupo'],
            ],
        ])->assertOk()->assertJsonPath('message_id', null);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        Event::assertNotDispatched(MessageReceived::class);
    }

    public function test_group_message_is_ignored_even_when_legacy_setting_is_disabled(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $token = str_repeat('f', 64);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-groups-enabled',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp Comercial',
            'credentials' => ['instance_token' => 'group-key'],
            'meta_json' => [
                'instance_name' => 'respondesk-groups-enabled',
                'advanced_settings' => ['ignore_groups' => false],
            ],
            'status' => 'active',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-groups-enabled',
            'data' => [
                'GroupName' => 'Equipe Comercial',
                'Info' => [
                    'ID' => 'GROUP-MESSAGE-ENABLED-1',
                    'Chat' => '120363000000000001@g.us',
                    'Sender' => '5511987654321@s.whatsapp.net',
                    'PushName' => 'Luciana',
                    'IsFromMe' => false,
                ],
                'Message' => ['conversation' => 'Bom dia, equipe!'],
            ],
        ])->assertOk()->assertJsonPath('message_id', null);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
        Event::assertNotDispatched(MessageReceived::class);
    }

    public function test_revoked_message_is_rendered_as_an_event_instead_of_unsupported(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('d', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-protocol',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'protocol-key'],
            'meta_json' => ['instance_name' => 'respondesk-protocol'],
            'status' => 'active',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-protocol',
            'data' => [
                'Info' => ['ID' => 'PROTOCOL-1', 'Chat' => '5511987654321@s.whatsapp.net', 'IsFromMe' => false],
                'Message' => ['protocolMessage' => [
                    'key' => ['ID' => 'DELETED-IMAGE-1'],
                    'type' => 0,
                ]],
            ],
        ])->assertOk();

        $message = Message::where('provider_message_id', 'evogo:'.$account->id.':PROTOCOL-1')->firstOrFail();
        $this->assertSame('event', $message->type);
        $this->assertSame('Mensagem apagada', $message->body);
        $this->assertSame('message_revoked', $message->payload['event']['type']);
    }

    public function test_webhook_normalises_emoji_reactions_and_links_the_original_message(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('a', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-reaction',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'reaction-key'],
            'meta_json' => ['instance_name' => 'respondesk-reaction'],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id, 'phone_e164' => '+5511987654321']);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => '5511987654321@s.whatsapp.net',
            'status' => 'open',
        ]);
        $original = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'image',
            'body' => 'Foto do produto',
            'payload' => ['preview_url' => '/storage/message-media/original.jpg'],
            'status' => 'delivered',
            'provider_message_id' => 'evogo:'.$account->id.':ORIGINAL-1',
            'sent_at' => now(),
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-reaction',
            'data' => [
                'Info' => ['ID' => 'REACTION-1', 'Chat' => '5511987654321@s.whatsapp.net', 'IsFromMe' => false],
                'Message' => ['reactionMessage' => [
                    'key' => ['ID' => 'ORIGINAL-1', 'remoteJID' => '5511987654321@s.whatsapp.net'],
                    'text' => '👍',
                ]],
            ],
        ])->assertOk();

        $reaction = Message::where('provider_message_id', 'evogo:'.$account->id.':REACTION-1')->firstOrFail();
        $this->assertSame('reaction', $reaction->type);
        $this->assertSame('👍', $reaction->payload['reaction']['emoji']);
        $this->assertSame($original->id, $reaction->payload['quoted']['message_id']);
        $this->assertSame('image', $reaction->payload['quoted']['type']);
    }

    public function test_webhook_preserves_the_message_or_image_being_replied_to(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $token = str_repeat('b', 64);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-quote',
            'webhook_token_hash' => hash('sha256', $token),
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'quote-key'],
            'meta_json' => ['instance_name' => 'respondesk-quote'],
            'status' => 'active',
        ]);

        $this->postJson(route('webhooks.evolution-go.receive', ['token' => $token]), [
            'event' => 'MESSAGE',
            'instanceName' => 'respondesk-quote',
            'data' => [
                'Info' => ['ID' => 'QUOTE-REPLY-1', 'Chat' => '5511987654321@s.whatsapp.net', 'IsFromMe' => false],
                'Message' => ['extendedTextMessage' => [
                    'text' => 'É esta imagem',
                    'contextInfo' => [
                        'stanzaID' => 'IMAGE-NOT-CACHED',
                        'quotedMessage' => ['imageMessage' => [
                            'caption' => 'Foto de referência',
                            'JPEGThumbnail' => base64_encode('thumbnail'),
                        ]],
                    ],
                ]],
            ],
        ])->assertOk();

        $reply = Message::where('body', 'É esta imagem')->firstOrFail();
        $this->assertSame('text', $reply->type);
        $this->assertSame('image', $reply->payload['quoted']['type']);
        $this->assertSame('Foto de referência', $reply->payload['quoted']['body']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $reply->payload['quoted']['thumbnail']);
    }

    public function test_client_can_recover_historical_encrypted_media(): void
    {
        $this->configureEvolutionGo();
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:historical-media',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'historical-key', 'instance_id' => 'historical-media'],
            'status' => 'active',
        ]);
        Http::fake([
            self::BASE_URL.'/message/downloadmedia' => Http::response([
                'message' => 'success',
                'data' => ['base64' => 'data:audio/ogg;base64,'.base64_encode('old-audio')],
            ]),
        ]);

        $download = EvolutionGoClient::forAccount($account)
            ->downloadMedia(['audioMessage' => ['directPath' => '/encrypted']]);

        $this->assertSame('old-audio', $download['bytes']);
        $this->assertSame('audio/ogg', $download['mime_type']);
        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/message/downloadmedia'
            && $request->hasHeader('apikey', 'historical-key')
            && $request['message']['audioMessage']['directPath'] === '/encrypted');
    }

    public function test_inbox_media_proxy_recovers_an_evolution_sticker_when_preview_is_stale(): void
    {
        Storage::fake('public');
        $this->configureEvolutionGo();
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:stale-sticker',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'sticker-key'],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => '5511987654321@s.whatsapp.net',
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'sticker',
            'body' => 'Figurinha',
            'payload' => [
                'preview_url' => 'https://temporary.invalid/sticker.webp',
                'evolution_go' => ['Message' => ['stickerMessage' => [
                    'directPath' => '/encrypted-sticker',
                    'mimetype' => 'image/webp',
                ]]],
            ],
            'status' => 'delivered',
            'sent_at' => now(),
        ]);
        Http::fake([
            self::BASE_URL.'/message/downloadmedia' => Http::response([
                'message' => 'success',
                'data' => ['base64' => 'data:image/webp;base64,'.base64_encode('sticker-bytes')],
            ]),
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.message-media', ['conversation' => $conversation, 'message' => $message]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $message->refresh();
        $this->assertSame('image/webp', $message->payload['mime_type']);
        $this->assertStringContainsString("message-media/{$message->id}.webp", $message->payload['preview_url']);
        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/message/downloadmedia');
    }

    public function test_repair_command_merges_existing_lid_and_phone_boxes(): void
    {
        $context = $this->createWorkspaceContext();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:repair',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'repair-key', 'instance_id' => 'repair'],
            'status' => 'active',
        ]);
        $lidContact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+176500000009999',
        ]);
        $phoneContact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+5511977776666',
        ]);
        $lidConversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $lidContact->id,
            'external_thread_id' => '176500000009999@lid',
            'status' => 'open',
        ]);
        $phoneConversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $phoneContact->id,
            'external_thread_id' => '5511977776666@s.whatsapp.net',
            'status' => 'open',
        ]);
        Message::create([
            'conversation_id' => $lidConversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Mensagem no LID',
            'payload' => ['evolution_go' => ['Info' => [
                'Chat' => '176500000009999@lid',
                'SenderAlt' => '5511977776666@s.whatsapp.net',
            ]]],
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now()->subMinute(),
        ]);
        Message::create([
            'conversation_id' => $phoneConversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Mensagem no número',
            'payload' => ['evolution_go' => ['Info' => [
                'Chat' => '5511977776666@s.whatsapp.net',
            ]]],
            'status' => 'delivered',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('inbox:repair-evolution-conversations', ['--apply' => true]));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $conversation = Conversation::firstOrFail();
        $this->assertSame($phoneContact->id, $conversation->contact_id);
        $this->assertSame('5511977776666@s.whatsapp.net', $conversation->external_thread_id);
    }

    public function test_workspace_admin_can_choose_webhook_transport_and_advanced_options(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-settings',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'settings-key', 'instance_id' => 'instance-settings'],
            'status' => 'active',
        ]);
        Http::fake([
            self::BASE_URL.'/instance/connect' => Http::response(['message' => 'success']),
            self::BASE_URL.'/instance/instance-settings/advanced-settings' => Http::response(['message' => 'success']),
        ]);

        $this->actingAs($context['user'])
            ->put(route('client.whatsapp.evolution-go.settings', $account), [
                'events' => ['MESSAGE', 'CONNECTION', 'CALL'],
                'rabbitmq' => 'disabled',
                'websocket' => 'enabled',
                'nats' => 'disabled',
                'always_online' => true,
                'reject_call' => true,
                'reject_call_message' => 'Não recebemos chamadas.',
                'read_messages' => true,
                'ignore_groups' => true,
                'ignore_status' => true,
            ])
            ->assertSessionHas('success');

        $account->refresh();
        $this->assertSame(['MESSAGE', 'CONNECTION', 'CALL'], $account->meta_json['connection_options']['subscribe']);
        $this->assertTrue($account->meta_json['advanced_settings']['always_online']);

        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/instance/connect'
            && $request['subscribe'] === ['MESSAGE', 'CONNECTION', 'CALL']
            && $request['rabbitmqEnable'] === 'disabled'
            && $request['websocketEnable'] === 'enabled');
        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/instance/instance-settings/advanced-settings'
            && $request['alwaysOnline'] === true
            && $request['rejectCall'] === true
            && $request['readMessages'] === true
            && $request['ignoreGroups'] === true
            && $request['ignoreStatus'] === true);
    }

    public function test_webhook_rejects_unknown_secret_token(): void
    {
        $this->postJson('/webhooks/evolution-go/'.str_repeat('b', 64), [
            'event' => 'Message',
            'data' => [],
        ])->assertNotFound();
    }

    public function test_whatsapp_driver_sends_text_with_instance_token_and_current_response_shape(): void
    {
        $context = $this->createWorkspaceContext();
        $this->configureEvolutionGo();

        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'external_account_id' => 'server:instance-2',
            'display_name' => 'WhatsApp QR',
            'credentials' => ['instance_token' => 'isolated-instance-key', 'instance_id' => 'instance-2'],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'phone_e164' => '+5511988887777',
        ]);
        $conversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => '5511988887777',
            'status' => 'open',
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Mensagem de teste',
            'status' => 'queued',
            'sent_by' => 'human',
            'sent_at' => now(),
        ]);

        Http::fake([
            self::BASE_URL.'/send/text' => Http::response([
                'message' => 'success',
                'data' => ['Info' => ['ID' => '3EB0SENT1']],
            ]),
        ]);

        $providerId = app(WhatsappDriver::class)->send($message);

        $this->assertSame('evogo:'.$account->id.':3EB0SENT1', $providerId);
        Http::assertSent(fn ($request) => $request->url() === self::BASE_URL.'/send/text'
            && $request->hasHeader('apikey', 'isolated-instance-key')
            && $request['number'] === '5511988887777'
            && $request['formatJid'] === true
            && $request['text'] === 'Mensagem de teste');
    }
}
