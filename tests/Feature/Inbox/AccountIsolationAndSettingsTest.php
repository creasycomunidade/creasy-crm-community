<?php

namespace Tests\Feature\Inbox;

use App\Events\MessageReceived;
use App\Listeners\AutoReplyListener;
use App\Models\User;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Inbox\Models\ScheduledMessage;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountIsolationAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_shows_only_the_appointments_linked_to_its_contact(): void
    {
        $context = $this->createWorkspaceContext();
        [, $conversation] = $this->accountWithConversation($context['workspace']->id, 'Agenda');
        [, $otherConversation] = $this->accountWithConversation($context['workspace']->id, 'Outro contato');
        $service = AppointmentService::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Consulta',
            'duration_minutes' => 30,
        ]);
        $resource = AppointmentResource::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Sala 1',
        ]);
        $visible = Appointment::create([
            'workspace_id' => $context['workspace']->id,
            'service_id' => $service->id,
            'resource_id' => $resource->id,
            'contact_id' => $conversation->contact_id,
            'customer_name' => 'Lead da conversa',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
        ]);
        Appointment::create([
            'workspace_id' => $context['workspace']->id,
            'service_id' => $service->id,
            'resource_id' => $resource->id,
            'contact_id' => $otherConversation->contact_id,
            'customer_name' => 'Outro lead',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addMinutes(30),
            'status' => 'scheduled',
        ]);
        $messageSchedule = ScheduledMessage::create([
            'workspace_id' => $context['workspace']->id,
            'conversation_id' => $conversation->id,
            'created_by' => $context['user']->id,
            'body' => 'Lembrete desta conversa',
            'timezone' => 'America/Sao_Paulo',
            'next_send_at' => now()->addHour(),
            'recurrence_unit' => 'once',
            'recurrence_interval' => 1,
            'status' => 'scheduled',
        ]);
        ScheduledMessage::create([
            'workspace_id' => $context['workspace']->id,
            'conversation_id' => $otherConversation->id,
            'created_by' => $context['user']->id,
            'body' => 'Mensagem de outra conversa',
            'timezone' => 'America/Sao_Paulo',
            'next_send_at' => now()->addHour(),
            'recurrence_unit' => 'once',
            'recurrence_interval' => 1,
            'status' => 'scheduled',
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contactAppointments', 1)
                ->where('contactAppointments.0.uuid', $visible->uuid)
                ->where('contactAppointments.0.service.name', 'Consulta')
                ->where('contactAppointments.0.resource.name', 'Sala 1')
                ->has('scheduledMessages', 1)
                ->where('scheduledMessages.0.uuid', $messageSchedule->uuid)
                ->where('scheduledMessages.0.body', 'Lembrete desta conversa'));
    }

    public function test_show_keeps_conversation_list_isolated_by_selected_channel_account(): void
    {
        $context = $this->createWorkspaceContext();
        [$first, $firstConversation] = $this->accountWithConversation($context['workspace']->id, 'Aurora Tech');
        [, $secondConversation] = $this->accountWithConversation($context['workspace']->id, 'Paolo');

        $this->actingAs($context['user'])
            ->get(route('client.inbox.show', [
                'conversation' => $firstConversation,
                'account_id' => $first->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inbox/Show')
                ->where('filters.account_id', (string) $first->id)
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $firstConversation->id)
                ->missing('conversations.data.1'));

        $this->assertNotSame($firstConversation->id, $secondConversation->id);
    }

    public function test_contact_permissions_can_be_edited_from_the_conversation(): void
    {
        $context = $this->createWorkspaceContext();
        [, $conversation] = $this->accountWithConversation($context['workspace']->id, 'Aurora Tech');

        $this->actingAs($context['user'])
            ->patchJson(route('client.inbox.contact-permissions.update', $conversation), [
                'opt_in_whatsapp' => true,
                'opt_in_sms' => true,
            ])
            ->assertOk()
            ->assertJsonPath('permissions.opt_in_whatsapp', true)
            ->assertJsonPath('permissions.opt_in_sms', true);

        $this->assertDatabaseHas('contacts', [
            'id' => $conversation->contact_id,
            'opt_in_whatsapp' => true,
            'opt_in_sms' => true,
        ]);
    }

    public function test_contact_segments_and_campaign_tags_can_be_edited_from_the_conversation(): void
    {
        $context = $this->createWorkspaceContext();
        [, $conversation] = $this->accountWithConversation($context['workspace']->id, 'Aurora Tech');
        $tag = ContactTag::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Cliente VIP',
            'color' => '#7c3aed',
        ]);
        $segment = Segment::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Clientes ativos',
            'type' => 'static',
        ]);
        $dynamic = Segment::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Regra automática',
            'type' => 'dynamic',
            'rules_json' => [],
        ]);
        $conversation->contact->segments()->attach($dynamic->id);

        $this->actingAs($context['user'])
            ->patchJson(route('client.inbox.contact-segmentation.update', $conversation), [
                'tag_ids' => [$tag->id],
                'segment_ids' => [$segment->id],
            ])
            ->assertOk()
            ->assertJsonPath('tag_ids.0', $tag->id)
            ->assertJsonPath('segment_ids.0', $segment->id);

        $contact = $conversation->contact->fresh();
        $this->assertTrue($contact->tags()->whereKey($tag->id)->exists());
        $this->assertTrue($contact->segments()->whereKey($segment->id)->exists());
        $this->assertTrue($contact->segments()->whereKey($dynamic->id)->exists(), 'Dynamic segment membership must be preserved.');
        $this->assertSame(1, $segment->fresh()->contact_count);
    }

    public function test_contact_segmentation_rejects_tags_and_segments_from_another_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        [, $conversation] = $this->accountWithConversation($context['workspace']->id, 'Aurora Tech');
        $otherContext = $this->createWorkspaceContext();
        $foreignTag = ContactTag::create([
            'workspace_id' => $otherContext['workspace']->id,
            'name' => 'Etiqueta externa',
            'color' => '#ef4444',
        ]);
        $foreignSegment = Segment::create([
            'workspace_id' => $otherContext['workspace']->id,
            'name' => 'Segmento externo',
            'type' => 'static',
        ]);

        $this->actingAs($context['user'])
            ->patchJson(route('client.inbox.contact-segmentation.update', $conversation), [
                'tag_ids' => [$foreignTag->id],
                'segment_ids' => [$foreignSegment->id],
            ])
            ->assertUnprocessable();

        $this->assertFalse($conversation->contact->tags()->exists());
        $this->assertFalse($conversation->contact->segments()->exists());
    }

    public function test_contact_tag_can_be_created_from_the_inbox(): void
    {
        $context = $this->createWorkspaceContext();

        $this->actingAs($context['user'])
            ->postJson(route('client.inbox.contact-tags.store'), ['name' => '  Retorno prioritário  '])
            ->assertCreated()
            ->assertJsonPath('tag.name', 'Retorno prioritário');

        $this->assertDatabaseHas('contact_tags', [
            'workspace_id' => $context['workspace']->id,
            'name' => 'Retorno prioritário',
        ]);
    }

    public function test_channel_operations_save_hours_human_access_and_outside_hours_message(): void
    {
        $context = $this->createWorkspaceContext();
        [$account] = $this->accountWithConversation($context['workspace']->id, 'Aurora Tech');

        $hours = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->mapWithKeys(fn (string $day, int $index) => [$day => [
                'enabled' => $index < 5,
                'start' => '08:00',
                'end' => '18:00',
            ]])->all();

        $this->actingAs($context['user'])
            ->patch(route('client.inbox.setup.operations.update', $account), [
                'timezone' => 'America/Sao_Paulo',
                'business_hours' => $hours,
                'human_access_mode' => 'selected',
                'allowed_user_ids' => [$context['user']->id],
                'outside_hours_enabled' => true,
                'outside_hours_message' => 'Estamos fechados agora.',
                'outside_hours_cooldown_hours' => 12,
            ])
            ->assertSessionHasNoErrors();

        $operations = $account->refresh()->meta_json['operations'];
        $this->assertSame('America/Sao_Paulo', $operations['timezone']);
        $this->assertSame('selected', $operations['human_access_mode']);
        $this->assertSame([$context['user']->id], $operations['allowed_user_ids']);
        $this->assertTrue($operations['outside_hours_enabled']);
        $this->assertSame('Estamos fechados agora.', $operations['outside_hours_message']);
        $this->assertTrue($operations['business_hours']['monday']['enabled']);
    }

    public function test_outside_hours_reply_is_sent_without_a_chatbot_and_respects_cooldown(): void
    {
        $context = $this->createWorkspaceContext();
        [$account, $conversation] = $this->accountWithConversation($context['workspace']->id, 'Telegram Empresa');
        $account->update([
            'channel' => 'telegram',
            'meta_json' => ['operations' => [
                'timezone' => 'America/Sao_Paulo',
                'business_hours' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                    ->mapWithKeys(fn (string $day) => [$day => ['enabled' => false, 'start' => '08:00', 'end' => '18:00']])->all(),
                'outside_hours_enabled' => true,
                'outside_hours_message' => 'Olá! Retornaremos no próximo horário de atendimento.',
                'outside_hours_cooldown_hours' => 12,
            ]],
        ]);

        $manager = new ChannelManager;
        $manager->register('telegram', FakeOperationsTelegramDriver::class);
        $this->app->instance(ChannelManager::class, $manager);
        $listener = $this->app->make(AutoReplyListener::class);

        foreach (['Primeira mensagem', 'Segunda mensagem'] as $body) {
            $inbound = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'in',
                'channel' => 'telegram',
                'type' => 'text',
                'body' => $body,
                'payload' => [],
                'status' => 'received',
                'sent_at' => now(),
            ]);
            $listener->handle(new MessageReceived($inbound));
        }

        $replies = $conversation->messages()->where('direction', 'out')->get();
        $this->assertCount(1, $replies);
        $this->assertSame('Olá! Retornaremos no próximo horário de atendimento.', $replies->first()->body);
        $this->assertSame('outside_business_hours', $replies->first()->payload['system_auto_reply']);
        $this->assertSame('sent', $replies->first()->status);
    }

    public function test_selected_human_channel_access_hides_conversations_from_unlisted_staff(): void
    {
        $context = $this->createWorkspaceContext();
        [$account] = $this->accountWithConversation($context['workspace']->id, 'Privado');
        $staff = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $account->update(['meta_json' => ['operations' => [
            'human_access_mode' => 'selected',
            'allowed_user_ids' => [$context['user']->id],
        ]]]);

        $this->actingAs($staff)
            ->get(route('client.inbox.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 0));
    }

    public function test_channel_access_is_manual_by_default_and_filters_polling_notifications_and_account_lists(): void
    {
        $context = $this->createWorkspaceContext();
        [$allowedAccount, $allowedConversation] = $this->accountWithConversation($context['workspace']->id, 'Comercial');
        [$privateAccount, $privateConversation] = $this->accountWithConversation($context['workspace']->id, 'Financeiro');
        $staff = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'client_id' => $context['client']->id,
            'client_role' => User::CLIENT_ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
            'workspace_id' => $context['workspace']->id,
            'email_verified_at' => now(),
        ]);
        $allowedAccount->update(['meta_json' => ['operations' => [
            'human_access_mode' => 'selected',
            'allowed_user_ids' => [$staff->id],
        ]]]);

        $allowedMessage = Message::create([
            'conversation_id' => $allowedConversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Mensagem comercial',
            'status' => 'received',
            'sent_at' => now(),
        ]);
        Message::create([
            'conversation_id' => $privateConversation->id,
            'direction' => 'in',
            'channel' => 'whatsapp',
            'type' => 'text',
            'body' => 'Mensagem financeira sigilosa',
            'status' => 'received',
            'sent_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('client.inbox.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $allowedConversation->id));

        $this->actingAs($staff)
            ->getJson(route('client.inbox.notification-updates', ['after_id' => 0]))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $allowedMessage->id);

        $this->actingAs($staff)
            ->getJson(route('client.inbox.channel-accounts'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $allowedAccount->id);

        $this->actingAs($staff)
            ->get(route('client.inbox.show', $privateConversation))
            ->assertForbidden();

        // Client administrators always retain visibility over every channel.
        $this->actingAs($context['user'])
            ->get(route('client.inbox.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 2));

        $this->assertSame([], $privateAccount->meta_json['operations']['allowed_user_ids'] ?? []);
    }

    public function test_whatsapp_groups_are_hidden_and_only_direct_conversations_are_returned(): void
    {
        $context = $this->createWorkspaceContext();
        [$account, $regularConversation] = $this->accountWithConversation($context['workspace']->id, 'Atendimento');
        $groupContact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Equipe Comercial',
            'last_name' => null,
            'custom_fields' => [
                'is_whatsapp_group' => true,
                'whatsapp_group_jid' => '120363000000000123@g.us',
                'whatsapp_group_name' => 'Equipe Comercial',
            ],
        ]);
        $groupConversation = Conversation::create([
            'workspace_id' => $context['workspace']->id,
            'channel_account_id' => $account->id,
            'contact_id' => $groupContact->id,
            'external_thread_id' => '120363000000000123@g.us',
            'status' => 'open',
            'last_message_at' => now(),
            'unread_count' => 3,
        ]);

        $this->actingAs($context['user'])
            ->get(route('client.inbox.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inbox/Index')
                ->missing('whatsappGroups')
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $regularConversation->id));

        $this->actingAs($context['user'])
            ->get(route('client.contacts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.id', $regularConversation->contact_id));

        $this->actingAs($context['user'])
            ->get(route('client.inbox.show', $groupConversation))
            ->assertRedirect(route('client.inbox.index'));

        $this->actingAs($context['user'])
            ->get(route('client.inbox.index', ['groups' => 1, 'channel' => 'whatsapp']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('filters.groups')
                ->has('conversations.data', 1)
                ->where('conversations.data.0.id', $regularConversation->id));

        $this->actingAs($context['user'])
            ->getJson(route('client.inbox.workspace-updates', [
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.id', $regularConversation->id);

        $this->assertNotSame($regularConversation->id, $groupConversation->id);
    }

    private function accountWithConversation(int $workspaceId, string $name): array
    {
        $account = ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => $name,
            'status' => 'active',
            'meta_json' => [],
        ]);
        $contact = Contact::factory()->create(['workspace_id' => $workspaceId]);
        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        return [$account, $conversation];
    }
}

class FakeOperationsTelegramDriver implements ChannelDriverInterface
{
    public function send(Message $message): string
    {
        return 'outside-hours-'.$message->id;
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
