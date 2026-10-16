<?php

namespace Tests\Feature\Campaign;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Jobs\LaunchCampaignJob;
use App\Modules\Broadcasting\Jobs\LaunchScheduledCampaignsJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->update(['workspace_id' => $workspace->id]);
        $user->refresh();

        return [$user, $workspace];
    }

    #[Test]
    public function launching_a_draft_with_a_future_schedule_does_not_dispatch_immediately(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'draft',
            'schedule_at' => now()->addHours(3),
        ]);

        $this->actingAs($user)
            ->post(route('client.campaigns.launch', $campaign->uuid))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('queued', $campaign->status);
        $this->assertNotNull($campaign->schedule_at);
        $this->assertTrue($campaign->schedule_at->isFuture());

        Queue::assertNotPushed(LaunchCampaignJob::class);
    }

    #[Test]
    public function launching_without_explicit_schedule_at_preserves_the_existing_schedule(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        $future = now()->addHours(2);
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'draft',
            'schedule_at' => $future,
        ]);

        // POST without schedule_at — must keep the existing schedule.
        $this->actingAs($user)
            ->post(route('client.campaigns.launch', $campaign->uuid))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertNotNull($campaign->schedule_at);
        $this->assertEqualsWithDelta(
            $future->getTimestamp(),
            $campaign->schedule_at->getTimestamp(),
            1,
        );
        Queue::assertNotPushed(LaunchCampaignJob::class);
    }

    #[Test]
    public function the_scheduler_picks_up_due_campaigns_and_dispatches_the_launch_job(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        $due = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'queued',
            'schedule_at' => now()->subMinute(),
        ]);

        $notDue = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'queued',
            'schedule_at' => now()->addHour(),
        ]);

        (new LaunchScheduledCampaignsJob)->handle();

        Queue::assertPushed(LaunchCampaignJob::class, 1);
        Queue::assertPushed(
            LaunchCampaignJob::class,
            fn (LaunchCampaignJob $job) => $job->campaignId === $due->id,
        );
        Queue::assertNotPushed(
            LaunchCampaignJob::class,
            fn (LaunchCampaignJob $job) => $job->campaignId === $notDue->id,
        );
    }

    #[Test]
    public function launching_with_an_empty_schedule_at_string_dispatches_immediately(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'draft',
            'schedule_at' => now()->addHour(),
        ]);

        // Empty string explicitly clears the schedule and sends now.
        $this->actingAs($user)
            ->post(route('client.campaigns.launch', $campaign->uuid), ['schedule_at' => ''])
            ->assertRedirect();

        $campaign->refresh();
        $this->assertNull($campaign->schedule_at);
        Queue::assertPushed(LaunchCampaignJob::class, 1);
    }

    #[Test]
    public function storing_a_campaign_persists_iso_schedule_as_utc_and_saves_timezone(): void
    {
        [$user, $workspace] = $this->ctx();

        // Frontend converts the user's "15:30 in Asia/Dhaka" wall-clock to UTC
        // before posting (Dhaka = UTC+6, so it becomes 09:30Z).
        $iso = '2030-01-15T09:30:00.000Z';

        $this->actingAs($user)
            ->post(route('client.campaigns.store'), [
                'name' => 'TZ Check',
                'channel' => 'sms',
                'audience_type' => 'contact_list',
                'audience_ref' => null,
                'template_ref' => null,
                'payload_json' => ['body' => 'hello'],
                'schedule_at' => $iso,
                'timezone' => 'Asia/Dhaka',
            ])
            ->assertRedirect();

        $campaign = Campaign::where('workspace_id', $workspace->id)->latest('id')->first();
        $this->assertNotNull($campaign, 'campaign was not created');

        // Stored as UTC: 2030-01-15 09:30:00 UTC, which is 15:30 in Asia/Dhaka.
        $this->assertSame('2030-01-15 09:30:00', $campaign->schedule_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2030-01-15 15:30:00',
            $campaign->schedule_at->copy()->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'),
        );
        $this->assertSame('Asia/Dhaka', $campaign->timezone);
    }

    #[Test]
    public function whatsapp_campaign_requires_and_persists_the_selected_workspace_account(): void
    {
        [$user, $workspace] = $this->ctx();
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Caixa Comercial',
            'credentials' => ['instance_token' => 'selected-token'],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('client.campaigns.store'), [
                'name' => 'Campanha pela caixa comercial',
                'channel' => 'whatsapp',
                'channel_account_id' => $account->id,
                'audience_type' => 'contact_list',
                'payload_json' => ['body' => 'Olá {{contact.first_name}}'],
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $campaign = Campaign::where('workspace_id', $workspace->id)->latest('id')->firstOrFail();
        $this->assertSame($account->id, $campaign->channel_account_id);
        $this->assertNull($campaign->whatsapp_phone_number_id);
    }

    #[Test]
    public function whatsapp_campaign_rejects_an_account_from_another_workspace(): void
    {
        [$user, $workspace] = $this->ctx();
        [, $otherWorkspace] = $this->ctx();
        $foreignAccount = ChannelAccount::create([
            'workspace_id' => $otherWorkspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Caixa de outra empresa',
            'credentials' => ['instance_token' => 'foreign-token'],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->from(route('client.campaigns.create'))
            ->post(route('client.campaigns.store'), [
                'name' => 'Campanha inválida',
                'channel' => 'whatsapp',
                'channel_account_id' => $foreignAccount->id,
                'audience_type' => 'contact_list',
                'payload_json' => ['body' => 'Olá'],
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertSessionHasErrors('channel_account_id');

        $this->assertDatabaseMissing('campaigns', [
            'workspace_id' => $workspace->id,
            'name' => 'Campanha inválida',
        ]);
    }

    #[Test]
    public function updating_a_draft_overwrites_schedule_in_utc_and_keeps_timezone(): void
    {
        [$user, $workspace] = $this->ctx();

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'audience_type' => 'contact_list',
            'status' => 'draft',
            'schedule_at' => now()->addDay(),
            'timezone' => 'UTC',
        ]);

        $iso = '2030-06-01T18:00:00.000Z';

        $response = $this->actingAs($user)
            ->patch(route('client.campaigns.update', $campaign->uuid), [
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'audience_type' => $campaign->audience_type,
                'audience_ref' => $campaign->audience_ref,
                'template_ref' => $campaign->template_ref,
                'payload_json' => $campaign->payload_json,
                'schedule_at' => $iso,
                'timezone' => 'Asia/Kolkata',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $campaign->refresh();
        $this->assertSame('2030-06-01 18:00:00', $campaign->schedule_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Kolkata', $campaign->timezone);
    }
}
