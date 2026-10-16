<?php

namespace Tests\Feature\Campaign;

use App\Models\User;
use App\Models\Workspace;
use App\Modules\Broadcasting\Jobs\DispatchCampaignChunkJob;
use App\Modules\Broadcasting\Jobs\FinalizeCampaignJob;
use App\Modules\Broadcasting\Jobs\LaunchCampaignJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdempotentLaunchTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        return [$user, $workspace];
    }

    #[Test]
    public function relaunching_a_paused_campaign_does_not_duplicate_recipients(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        // 5 contacts in the workspace, all opted-in by factory default.
        Contact::factory()->count(5)->create([
            'workspace_id' => $workspace->id,
            'opt_in_sms' => true,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'queued',
            'audience_type' => 'contact_list',
            'payload_json' => ['body' => 'Hi'],
        ]);

        // First launch — materialises 5 recipients.
        (new LaunchCampaignJob($campaign->id))->handle();
        $this->assertSame(5, CampaignRecipient::where('campaign_id', $campaign->id)->count());

        // User pauses, then re-launches.
        $campaign->update(['status' => 'queued']);

        (new LaunchCampaignJob($campaign->id))->handle();

        // Still exactly 5 — no duplicates because of the unique index + insertOrIgnore.
        $this->assertSame(5, CampaignRecipient::where('campaign_id', $campaign->id)->count());
    }

    #[Test]
    public function launch_filters_out_contacts_that_did_not_opt_in_for_the_channel(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        // 3 SMS-opted-in, 2 opted out for SMS but in for WhatsApp.
        Contact::factory()->count(3)->create([
            'workspace_id' => $workspace->id,
            'opt_in_sms' => true,
        ]);
        Contact::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'opt_in_sms' => false,
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'queued',
            'audience_type' => 'contact_list',
            'payload_json' => ['body' => 'Hi'],
        ]);

        (new LaunchCampaignJob($campaign->id))->handle();

        $this->assertSame(3, CampaignRecipient::where('campaign_id', $campaign->id)->count());
    }

    #[Test]
    public function social_campaign_only_targets_contacts_with_a_thread_on_the_selected_account(): void
    {
        Queue::fake();

        [, $workspace] = $this->ctx();
        $selected = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'instagram',
            'provider' => 'meta',
            'display_name' => 'Perfil principal',
            'status' => 'active',
        ]);
        $other = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'instagram',
            'provider' => 'meta',
            'display_name' => 'Outro perfil',
            'status' => 'active',
        ]);

        $eligible = Contact::factory()->create(['workspace_id' => $workspace->id]);
        $wrongAccount = Contact::factory()->create(['workspace_id' => $workspace->id]);
        Contact::factory()->create(['workspace_id' => $workspace->id]);

        Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $selected->id,
            'contact_id' => $eligible->id,
            'external_thread_id' => 'ig-user-1',
        ]);
        Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $other->id,
            'contact_id' => $wrongAccount->id,
            'external_thread_id' => 'ig-user-2',
        ]);

        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'instagram',
            'channel_account_id' => $selected->id,
            'status' => 'queued',
            'audience_type' => 'contact_list',
            'payload_json' => ['body' => 'Olá {{contact.first_name}}'],
        ]);

        (new LaunchCampaignJob($campaign->id))->handle();

        $this->assertSame([$eligible->id], CampaignRecipient::where('campaign_id', $campaign->id)
            ->pluck('contact_id')->all());
    }

    #[Test]
    public function launch_with_empty_audience_marks_campaign_failed(): void
    {
        Queue::fake();

        [$user, $workspace] = $this->ctx();

        // Workspace has no contacts at all.
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'sms',
            'status' => 'queued',
            'audience_type' => 'contact_list',
            'payload_json' => ['body' => 'Hi'],
        ]);

        (new LaunchCampaignJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
        Queue::assertNotPushed(DispatchCampaignChunkJob::class);
        Queue::assertNotPushed(FinalizeCampaignJob::class);
    }
}
