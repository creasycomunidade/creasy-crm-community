<?php

namespace Tests\Feature\Inbox;

use App\Modules\Inbox\Jobs\DispatchScheduledMessagesJob;
use App\Modules\Inbox\Jobs\SendScheduledMessageJob;
use App\Modules\Inbox\Models\ScheduledMessage;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ScheduledMessageTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(int $workspaceId): Conversation
    {
        $account = ChannelAccount::create([
            'workspace_id' => $workspaceId,
            'channel' => 'messenger',
            'provider' => 'meta',
            'status' => 'active',
            'display_name' => 'Atendimento',
        ]);
        $contact = Contact::create([
            'workspace_id' => $workspaceId,
            'first_name' => 'Maria',
            'phone_e164' => '+5547999999999',
        ]);

        return Conversation::create([
            'workspace_id' => $workspaceId,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    public function test_user_can_create_a_recurring_scheduled_message(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);

        $response = $this->actingAs($user)->post(route('client.inbox.scheduled-messages.store'), [
            'conversation_uuid' => $conversation->uuid,
            'body' => 'Lembrete recorrente',
            'send_at' => now()->addHour()->format('Y-m-d\\TH:i'),
            'recurrence_unit' => 'day',
            'recurrence_interval' => 2,
            'max_occurrences' => 5,
            'ends_at' => '',
        ]);

        $response->assertRedirect(route('client.inbox.scheduled-messages.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scheduled_messages', [
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'recurrence_unit' => 'day',
            'recurrence_interval' => 2,
            'max_occurrences' => 5,
            'status' => 'scheduled',
        ]);
    }

    public function test_popup_can_create_schedule_without_leaving_the_conversation(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);

        $this->actingAs($user)->postJson(route('client.inbox.scheduled-messages.store'), [
            'conversation_uuid' => $conversation->uuid,
            'body' => 'Mensagem criada no modal',
            'send_at' => now()->addHour()->format('Y-m-d\\TH:i'),
            'recurrence_unit' => 'once',
            'recurrence_interval' => 1,
            'max_occurrences' => '',
            'ends_at' => '',
        ])->assertCreated()
            ->assertJsonPath('message', 'Mensagem agendada.')
            ->assertJsonPath('schedule.status', 'scheduled');
    }

    public function test_user_can_edit_a_scheduled_message_from_the_list_or_conversation(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);
        $schedule = ScheduledMessage::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'body' => 'Mensagem original',
            'next_send_at' => now()->addHour(),
            'timezone' => 'America/Sao_Paulo',
            'recurrence_unit' => 'once',
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)->patchJson(route('client.inbox.scheduled-messages.update', $schedule), [
            'body' => 'Mensagem alterada',
            'send_at' => now()->addHours(2)->format('Y-m-d\\TH:i'),
            'recurrence_unit' => 'day',
            'recurrence_interval' => 3,
            'max_occurrences' => 8,
            'ends_at' => '',
            'remove_image' => false,
        ])->assertOk()->assertJsonPath('message', 'Mensagem agendada atualizada.');

        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $schedule->id,
            'conversation_id' => $conversation->id,
            'body' => 'Mensagem alterada',
            'recurrence_unit' => 'day',
            'recurrence_interval' => 3,
            'max_occurrences' => 8,
        ]);
    }

    public function test_scheduled_message_cannot_be_edited_by_another_workspace_or_after_completion(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createWorkspaceContext();
        ['user' => $foreignUser] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);
        $schedule = ScheduledMessage::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'created_by' => $owner->id,
            'body' => 'Protegida',
            'next_send_at' => now()->addHour(),
            'timezone' => 'America/Sao_Paulo',
            'recurrence_unit' => 'once',
            'status' => 'scheduled',
        ]);
        $payload = [
            'body' => 'Tentativa',
            'send_at' => now()->addHours(2)->format('Y-m-d\\TH:i'),
            'recurrence_unit' => 'once',
            'recurrence_interval' => 1,
            'max_occurrences' => '',
            'ends_at' => '',
        ];

        $this->actingAs($foreignUser)->patchJson(route('client.inbox.scheduled-messages.update', $schedule), $payload)->assertForbidden();
        $schedule->update(['status' => 'completed']);
        $this->actingAs($owner)->patchJson(route('client.inbox.scheduled-messages.update', $schedule), $payload)->assertUnprocessable();
    }

    public function test_user_can_permanently_delete_a_scheduled_message_and_its_media(): void
    {
        Storage::fake('public');
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);
        $path = "message-media/{$workspace->id}/scheduled/teste.jpg";
        Storage::disk('public')->put($path, 'image');
        $schedule = ScheduledMessage::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'body' => 'Excluir de verdade',
            'media_path' => $path,
            'media_url' => '/storage/'.$path,
            'next_send_at' => now()->addHour(),
            'timezone' => 'UTC',
            'recurrence_unit' => 'once',
            'status' => 'cancelled',
        ]);

        $this->actingAs($user)
            ->delete(route('client.inbox.scheduled-messages.destroy', $schedule))
            ->assertRedirect()
            ->assertSessionHas('success', 'Mensagem agendada excluída.');

        $this->assertDatabaseMissing('scheduled_messages', ['id' => $schedule->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_dispatcher_only_queues_due_messages(): void
    {
        Queue::fake();
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);

        $due = ScheduledMessage::create([
            'workspace_id' => $workspace->id, 'conversation_id' => $conversation->id,
            'created_by' => $user->id, 'body' => 'Agora', 'next_send_at' => now()->subMinute(),
            'timezone' => 'UTC', 'recurrence_unit' => 'once', 'status' => 'scheduled',
        ]);
        ScheduledMessage::create([
            'workspace_id' => $workspace->id, 'conversation_id' => $conversation->id,
            'created_by' => $user->id, 'body' => 'Depois', 'next_send_at' => now()->addHour(),
            'timezone' => 'UTC', 'recurrence_unit' => 'once', 'status' => 'scheduled',
        ]);

        (new DispatchScheduledMessagesJob)->handle();

        Queue::assertPushed(SendScheduledMessageJob::class, 1);
        Queue::assertPushed(SendScheduledMessageJob::class, fn ($job) => $job->scheduledMessageId === $due->id);
    }

    public function test_dedicated_cron_command_sends_due_messages_without_the_general_queue(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);
        $schedule = ScheduledMessage::create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'body' => 'Disparo direto do cron',
            'next_send_at' => now()->subMinute(),
            'timezone' => 'UTC',
            'recurrence_unit' => 'once',
            'status' => 'scheduled',
        ]);
        $driver = Mockery::mock(ChannelDriverInterface::class);
        $driver->shouldReceive('send')->once()->andReturn('mid.cron');
        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('driver')->with('messenger')->once()->andReturn($driver);
        $this->app->instance(ChannelManager::class, $manager);

        $this->artisan('messages:dispatch-scheduled')->assertSuccessful();

        $this->assertSame('completed', $schedule->fresh()->status);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Disparo direto do cron',
            'status' => 'sent',
        ]);
    }

    public function test_loop_sends_and_moves_to_the_next_occurrence(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);
        $schedule = ScheduledMessage::create([
            'workspace_id' => $workspace->id, 'conversation_id' => $conversation->id,
            'created_by' => $user->id, 'body' => 'Mensagem em loop', 'next_send_at' => now()->subMinutes(12),
            'timezone' => 'UTC', 'recurrence_unit' => 'minute', 'recurrence_interval' => 5,
            'max_occurrences' => 2, 'status' => 'scheduled',
        ]);

        $driver = Mockery::mock(ChannelDriverInterface::class);
        $driver->shouldReceive('send')->once()->andReturn('mid.scheduled');
        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('driver')->with('messenger')->once()->andReturn($driver);
        $this->app->instance(ChannelManager::class, $manager);

        app()->call([new SendScheduledMessageJob($schedule->id), 'handle']);

        $schedule->refresh();
        $this->assertSame('scheduled', $schedule->status);
        $this->assertSame(1, $schedule->occurrences_sent);
        $this->assertTrue($schedule->next_send_at->isFuture());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Mensagem em loop',
            'status' => 'sent',
            'sent_by' => 'automation',
        ]);
    }

    public function test_scheduled_image_is_stored_and_sent_as_an_image(): void
    {
        Storage::fake('public');
        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $conversation = $this->conversation($workspace->id);

        $this->actingAs($user)->withHeader('Accept', 'application/json')->post(
            route('client.inbox.scheduled-messages.store'),
            [
                'conversation_uuid' => $conversation->uuid,
                'body' => 'Confira esta imagem',
                'image' => UploadedFile::fake()->image('promocao.jpg', 640, 480),
                'send_at' => now()->addHour()->format('Y-m-d\\TH:i'),
                'recurrence_unit' => 'once',
                'recurrence_interval' => 1,
                'max_occurrences' => '',
                'ends_at' => '',
            ],
        )->assertCreated();

        $schedule = ScheduledMessage::latest('id')->firstOrFail();
        $this->assertNotNull($schedule->media_path);
        $this->assertSame('promocao.jpg', $schedule->media_name);
        Storage::disk('public')->assertExists($schedule->media_path);

        $schedule->update(['next_send_at' => now()->subMinute()]);
        $driver = Mockery::mock(ChannelDriverInterface::class);
        $driver->shouldReceive('send')->once()->with(Mockery::on(
            fn (Message $message) => $message->type === 'image'
                && $message->payload['preview_url'] === $schedule->media_url
                && $message->payload['caption'] !== null
        ))->andReturn('mid.image');
        $manager = Mockery::mock(ChannelManager::class);
        $manager->shouldReceive('driver')->with('messenger')->once()->andReturn($driver);
        $this->app->instance(ChannelManager::class, $manager);

        app()->call([new SendScheduledMessageJob($schedule->id), 'handle']);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'image',
            'body' => 'Confira esta imagem',
            'status' => 'sent',
        ]);
    }
}
