<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Services\AgentActionExecutor;
use App\Modules\AI\Services\AgentRunner;
use App\Modules\AI\Services\AgentRoutineService;
use App\Modules\AI\Services\AgentWorkspaceActionService;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;
use ReflectionMethod;

class AgentChannelRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_account_is_not_confused_with_recipient_contact(): void
    {
        $context = $this->createWorkspaceContext();
        $workspace = $context['workspace'];
        $agent = AiAgent::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'slug' => 'oracle',
            'name' => 'Maestro',
            'layer' => 'business',
            'description' => 'Coordena o espaço.',
            'persona' => 'Maestro.',
            'system_prompt' => 'Ajude a equipe.',
            'enabled' => true,
        ]);
        $auroraAccount = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Aurora Tech',
            'status' => 'active',
            'meta_json' => ['operations' => ['agent_access_mode' => 'all']],
        ]);
        ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Paolo',
            'status' => 'active',
            'meta_json' => ['operations' => ['agent_access_mode' => 'all']],
        ]);
        $rick = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Rick',
            'last_name' => 'Gabriel',
            'phone_e164' => '+5547988222565',
            'opt_in_whatsapp' => true,
        ]);
        $wrongAuroraContact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Aurora Tech',
            'last_name' => 'Solutions',
            'phone_e164' => '+554733328981',
            'opt_in_whatsapp' => true,
        ]);
        Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $auroraAccount->id,
            'contact_id' => $wrongAuroraContact->id,
            'status' => 'open',
        ]);

        $manager = new ChannelManager;
        $manager->register('whatsapp', FakeAgentWhatsAppDriver::class);
        $executor = new AgentActionExecutor(
            $manager,
            app(AgentRoutineService::class),
            app(AgentWorkspaceActionService::class),
        );
        $reply = "Vou enviar agora.\n```respondesk-actions\n".json_encode([
            'actions' => [[
                'type' => 'send_message',
                'source_channel_account_id' => $auroraAccount->id,
                'recipient_contact_uuid' => $rick->uuid,
                'body' => 'Estas são as suas tarefas de hoje.',
            ]],
        ], JSON_UNESCAPED_UNICODE)."\n```";

        $result = $executor->process(
            $agent,
            $context['user'],
            'Use a conta Aurora Tech e mande para +5547988222565',
            $reply,
        );

        $this->assertDatabaseCount('messages', 0);
        $this->assertStringContainsString('Confirme os dados antes de executar', $result['reply']);
        $this->assertStringContainsString('+5547988222565', $result['reply']);
        $result = $executor->process(
            $agent,
            $context['user'],
            'sim',
            'Confirmado.',
        );

        $message = Message::query()->latest('id')->firstOrFail();
        $conversation = $message->conversation;
        $this->assertSame($auroraAccount->id, $conversation->channel_account_id);
        $this->assertSame($rick->id, $conversation->contact_id);
        $this->assertNotSame($wrongAuroraContact->id, $conversation->contact_id);
        $this->assertSame('sent', $message->status);
        $this->assertStringContainsString('pela conta Aurora Tech para Rick Gabriel', $result['reply']);
    }

    public function test_self_directed_send_uses_only_profile_phone_and_requires_confirmation(): void
    {
        $context = $this->createWorkspaceContext([], ['name' => 'Paolo', 'phone_e164' => '+5547999999999']);
        $agent = AiAgent::create([
            'workspace_id' => $context['workspace']->id,
            'slug' => 'oracle', 'name' => 'Maestro', 'layer' => 'business',
            'description' => 'Coordena.', 'persona' => 'Maestro.', 'system_prompt' => 'Ajude.', 'enabled' => true,
        ]);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp', 'provider' => 'evolution_go',
            'display_name' => 'Aurora Tech', 'status' => 'active',
        ]);
        $wrong = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Paolo', 'phone_e164' => '+5547111111111', 'opt_in_whatsapp' => true,
        ]);
        $wrongConversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id, 'contact_id' => $wrong->id, 'status' => 'open',
        ]);
        $manager = new ChannelManager;
        $manager->register('whatsapp', FakeAgentWhatsAppDriver::class);
        $executor = new AgentActionExecutor($manager, app(AgentRoutineService::class), app(AgentWorkspaceActionService::class));
        $reply = "Vou preparar.\n```respondesk-actions\n".json_encode(['actions' => [[
            'type' => 'send_message',
            'conversation_uuid' => $wrongConversation->uuid,
            'recipient_self' => true,
            'body' => 'Seu relatório diário.',
        ]] ], JSON_UNESCAPED_UNICODE)."\n```";

        $prepared = $executor->process($agent, $context['user'], 'Me envie o relatório no meu WhatsApp.', $reply);
        $this->assertDatabaseCount('messages', 0);
        $this->assertStringContainsString('+5547999999999', $prepared['reply']);
        $this->assertStringNotContainsString('+5547111111111', $prepared['reply']);

        $executor->process($agent, $context['user'], 'sim', 'Confirmado.');
        $message = Message::query()->latest('id')->firstOrFail();
        $this->assertSame('+5547999999999', $message->conversation->contact->phone_e164);
        $this->assertNotSame($wrong->id, $message->conversation->contact_id);
    }

    public function test_agent_context_uses_saved_channel_business_hours_as_source_of_truth(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = AiAgent::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $context['workspace']->id,
            'slug' => 'oracle',
            'name' => 'Maestro',
            'layer' => 'business',
            'description' => 'Coordena o espaço.',
            'persona' => 'Maestro.',
            'system_prompt' => 'Ajude a equipe.',
            'enabled' => true,
        ]);
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Aurora Tech',
            'status' => 'active',
            'meta_json' => ['operations' => [
                'timezone' => 'America/Sao_Paulo',
                'business_hours' => [
                    'monday' => ['enabled' => true, 'start' => '09:15', 'end' => '17:45'],
                    'sunday' => ['enabled' => false, 'start' => '09:00', 'end' => '18:00'],
                ],
                'agent_access_mode' => 'all',
            ]],
        ]);

        $method = new ReflectionMethod(AgentRunner::class, 'communicationContext');
        $contextText = $method->invoke(
            app(AgentRunner::class),
            $agent,
            $context['user'],
            'Qual é o horário de funcionamento da Aurora Tech?',
        );

        $this->assertStringContainsString('CONTA REMETENTE: Aurora Tech', $contextText);
        $this->assertStringContainsString('monday 09:15–17:45', $contextText);
        $this->assertStringContainsString('sunday fechado', $contextText);
        $this->assertStringContainsString('Nunca invente horários', $contextText);
    }

    public function test_current_brazilian_mobile_number_finds_legacy_whatsapp_contact(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = AiAgent::create([
            'workspace_id' => $context['workspace']->id,
            'slug' => 'oracle',
            'name' => 'Maestro',
            'layer' => 'business',
            'description' => 'Coordena o espaço.',
            'persona' => 'Maestro.',
            'system_prompt' => 'Ajude a equipe.',
            'enabled' => true,
        ]);
        $account = ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Paolo',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Rick',
            'phone_e164' => '+554788222565',
            'opt_in_whatsapp' => true,
        ]);
        Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $contextMethod = new ReflectionMethod(AgentRunner::class, 'communicationContext');
        $contextText = $contextMethod->invoke(
            app(AgentRunner::class),
            $agent,
            $context['user'],
            'Envie o relatório por WhatsApp para 5547988222565.',
        );
        $this->assertStringContainsString("recipient_contact_uuid: {$contact->uuid}", $contextText);

        $executor = app(AgentActionExecutor::class);
        $candidatesMethod = new ReflectionMethod(AgentActionExecutor::class, 'phoneCandidates');
        $this->assertContains('+554788222565', $candidatesMethod->invoke($executor, '5547988222565'));
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'phone_e164' => '+554788222565']);

        $resolveMethod = new ReflectionMethod(AgentActionExecutor::class, 'resolveActionConversation');
        $conversation = $resolveMethod->invoke($executor, $agent, [
            'source_channel_account_id' => $account->id,
            'recipient_phone' => '5547988222565',
        ]);
        $this->assertSame($contact->id, $conversation->contact_id);
    }
}

class FakeAgentWhatsAppDriver implements ChannelDriverInterface
{
    public function send(Message $message): string
    {
        return 'fake-provider-'.$message->id;
    }

    public function receiveWebhook(Request $request): array
    {
        return [];
    }

    public function verifyCreds(): bool
    {
        return true;
    }
}
