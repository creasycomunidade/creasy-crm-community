<?php

namespace Tests\Feature;

use App\Events\MessageReceived;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebchatChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_creates_a_conversation_and_receives_a_message(): void
    {
        Event::fake([MessageReceived::class]);
        $context = $this->createWorkspaceContext();
        $account = $this->account($context['workspace']->id, ['site.exemplo.com']);
        $signature = hash_hmac('sha256', $account->id.'|site.exemplo.com', (string) config('app.key'));

        $this->withHeader('Referer', 'https://site.exemplo.com/pagina')
            ->get(route('public.webchat.script', ['key' => $account->external_account_id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $session = $this->postJson(route('public.webchat.session', [
            'key' => $account->external_account_id,
            'site' => 'site.exemplo.com',
            'signature' => $signature,
        ]), [
            'site' => 'site.exemplo.com',
            'signature' => $signature,
            'visitor_id' => 'visitante-teste-12345',
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
        ])->assertOk()->json('session');

        $this->postJson(route('public.webchat.send', [
            'key' => $account->external_account_id,
            'site' => 'site.exemplo.com',
            'signature' => $signature,
        ]), [
            'site' => 'site.exemplo.com',
            'signature' => $signature,
            'session' => $session,
            'body' => 'Preciso de ajuda',
        ])->assertCreated();

        $this->assertDatabaseHas('contacts', ['workspace_id' => $context['workspace']->id, 'email' => 'maria@example.com', 'source' => 'webchat']);
        $this->assertDatabaseHas('messages', ['channel' => 'webchat', 'direction' => 'in', 'body' => 'Preciso de ajuda']);
        Event::assertDispatched(MessageReceived::class);
    }

    public function test_widget_rejects_a_domain_not_in_the_allowlist(): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account($context['workspace']->id, ['permitido.com.br']);

        $this->withHeader('Referer', 'https://nao-permitido.com.br')
            ->get(route('public.webchat.script', ['key' => $account->external_account_id]))
            ->assertForbidden();
    }

    public function test_launcher_shadow_stays_inside_the_closed_widget_frame(): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account($context['workspace']->id, ['site.exemplo.com']);
        $signature = hash_hmac('sha256', $account->id.'|site.exemplo.com', (string) config('app.key'));

        $this->get(route('public.webchat.frame', [
            'key' => $account->external_account_id,
            'site' => 'site.exemplo.com',
            'signature' => $signature,
            'visitor' => 'visitante-teste-12345',
        ]))
            ->assertOk()
            ->assertSee('right:15px;bottom:15px', false)
            ->assertSee('box-shadow:0 6px 18px -6px rgba(24,24,27,.35)', false)
            ->assertDontSee('box-shadow:0 12px 30px rgba(39,39,42,.25)', false);
    }
    public function test_administrator_can_create_multiple_site_channels(): void
    {
        $context = $this->createWorkspaceContext();
        $payload = [
            'name' => 'Site institucional',
            'title' => 'Fale conosco',
            'welcome_message' => 'Olá! Como podemos ajudar?',
            'input_placeholder' => 'Digite sua mensagem...',
            'primary_color' => '#7c3aed',
            'position' => 'bottom_right',
            'allowed_domains' => ['empresa.com.br'],
            'require_name' => true,
            'require_email' => false,
            'enabled' => true,
            'chatbot_id' => null,
            'timezone' => 'America/Sao_Paulo',
            'business_hours' => [
                'monday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
                'tuesday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
                'wednesday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
                'thursday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
                'friday' => ['enabled' => true, 'start' => '08:00', 'end' => '18:00'],
                'saturday' => ['enabled' => false, 'start' => '08:00', 'end' => '12:00'],
                'sunday' => ['enabled' => false, 'start' => '08:00', 'end' => '12:00'],
            ],
            'outside_hours_enabled' => true,
            'outside_hours_message' => 'Responderemos no próximo horário útil.',
            'allowed_user_ids' => [],
        ];

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.inbox.webchat.store'), $payload)
            ->assertRedirect();

        $payload['name'] = 'Loja virtual';
        $payload['allowed_domains'] = ['loja.empresa.com.br'];
        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.inbox.webchat.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('channel_accounts', 2);
        $this->assertDatabaseHas('channel_accounts', ['workspace_id' => $context['workspace']->id, 'channel' => 'webchat', 'display_name' => 'Site institucional']);
        $this->assertDatabaseHas('channel_accounts', ['workspace_id' => $context['workspace']->id, 'channel' => 'webchat', 'display_name' => 'Loja virtual']);
    }
    public function test_automatic_reply_can_target_a_site_chat_channel(): void
    {
        $context = $this->createWorkspaceContext();
        $account = $this->account($context['workspace']->id, []);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.whatsapp.auto-replies.store'), [
                'channel_account_id' => $account->id,
                'trigger_type' => 'keyword',
                'match_mode' => 'contains',
                'keywords' => ['preço'],
                'response kiezen' => 'unused',
                'response_kind' => 'text',
                'payload_json' => ['text' => 'Vou ajudar com os valores.'],
                'enabled' => true,
                'priority' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('whatsapp_auto_replies', [
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'response_kind' => 'text',
        ]);
    }
    private function account(int $workspaceId, array $domains): ChannelAccount
    {
        return ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'webchat',
            'provider' => 'respondesk',
            'external_account_id' => 'widget_'.str()->random(24),
            'display_name' => 'Site principal',
            'status' => 'active',
            'meta_json' => [
                'webchat' => [
                    'title' => 'Atendimento',
                    'welcome_message' => 'Olá! Como podemos ajudar?',
                    'input_placeholder' => 'Digite sua mensagem...',
                    'primary_color' => '#7c3aed',
                    'position' => 'bottom_right',
                    'allowed_domains' => $domains,
                    'require_name' => true,
                    'require_email' => true,
                ],
                'operations' => ['allowed_user_ids' => []],
            ],
        ]);
    }
}