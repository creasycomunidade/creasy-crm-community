<?php

namespace Tests\Feature\Ads;

use App\Modules\Ads\Jobs\ProcessMetaLeadEventsJob;
use App\Modules\Ads\Models\TrackingSite;
use App\Modules\Ads\Models\AdConnection;
use App\Modules\Ads\Models\AttributionTouch;
use App\Modules\Ads\Jobs\DispatchConversionEventJob;
use App\Modules\Ads\Services\ConversionRecorder;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_rejects_unknown_origins(): void
    {
        $context = $this->createWorkspaceContext();
        $site = TrackingSite::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Site principal',
            'allowed_domains' => ['empresa.test'],
            'enabled' => true,
        ]);
        $payload = [
            'visitor_id' => (string) Str::uuid(),
            'event' => 'page_view',
            'landing_url' => 'https://empresa.test/produto?utm_source=google',
            'source' => 'google',
            'gclid' => 'click-123',
        ];

        $this->withHeader('Origin', 'https://invasor.test')
            ->postJson(route('ads.track', $site->public_key), $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('attribution_touches', 0);
    }

    public function test_tracker_accepts_an_allowed_domain(): void
    {
        $context = $this->createWorkspaceContext();
        $site = TrackingSite::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Site principal',
            'allowed_domains' => ['empresa.test'],
            'enabled' => true,
        ]);
        $payload = [
            'visitor_id' => (string) Str::uuid(),
            'event' => 'page_view',
            'landing_url' => 'https://empresa.test/produto?utm_source=google',
            'source' => 'google',
            'gclid' => 'click-123',
        ];

        $this->withHeader('Origin', 'https://empresa.test')
            ->postJson(route('ads.track', $site->public_key), $payload)
            ->assertAccepted();
        $this->assertDatabaseHas('attribution_touches', [
            'workspace_id' => $context['workspace']->id,
            'source' => 'google',
            'gclid' => 'click-123',
        ]);
    }

    public function test_instagram_comment_becomes_one_contact_without_messaging_opt_in(): void
    {
        $context = $this->createWorkspaceContext();
        ChannelAccount::create([
            'workspace_id' => $context['workspace']->id,
            'channel' => 'instagram',
            'provider' => 'meta',
            'display_name' => 'Instagram da empresa',
            'status' => 'active',
            'meta_json' => ['instagram_account_id' => 'ig-account-1'],
        ]);
        $payload = ['entry' => [[
            'id' => 'ig-account-1',
            'changes' => [[
                'field' => 'comments',
                'value' => [
                    'item' => 'comment',
                    'comment_id' => 'comment-1',
                    'post_id' => 'post-1',
                    'message' => 'Quero saber o preço',
                    'from' => ['id' => 'person-1', 'username' => 'maria'],
                ],
            ]],
        ]]];

        (new ProcessMetaLeadEventsJob($payload, 'instagram'))->handle();
        (new ProcessMetaLeadEventsJob($payload, 'instagram'))->handle();

        $contact = Contact::where('workspace_id', $context['workspace']->id)->sole();
        $this->assertSame('instagram_comment', $contact->source);
        $this->assertSame('person-1', $contact->custom_fields['instagram_commenter_id']);
        $this->assertFalse($contact->opt_in_whatsapp);
        $this->assertFalse($contact->opt_in_email);
    }

    public function test_qualified_kanban_stage_queues_a_deduplicated_offline_conversion(): void
    {
        Queue::fake();
        $context = $this->createWorkspaceContext();
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'email' => 'cliente@example.com',
        ]);
        $pipeline = Pipeline::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Vendas',
            'color' => '#7c3aed',
        ]);
        $stage = PipelineStage::create([
            'workspace_id' => $context['workspace']->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualificado',
            'color' => '#22c55e',
            'conversion_event' => 'QualifiedLead',
        ]);
        $deal = Deal::create([
            'workspace_id' => $context['workspace']->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'title' => 'Oportunidade',
            'value_cents' => 150000,
            'currency' => 'BRL',
        ]);
        AdConnection::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'meta',
            'name' => 'Meta',
            'account_id' => '123',
            'dataset_id' => '456',
            'credentials' => ['access_token' => 'secret'],
            'status' => 'connected',
        ]);
        AttributionTouch::create([
            'workspace_id' => $context['workspace']->id,
            'contact_id' => $contact->id,
            'visitor_id' => (string) Str::uuid(),
            'event' => 'form_submit',
            'fbclid' => 'fb-click',
            'occurred_at' => now(),
        ]);

        app(ConversionRecorder::class)->recordDealStage($deal);
        app(ConversionRecorder::class)->recordDealStage($deal);

        $this->assertDatabaseCount('ad_conversion_events', 1);
        $this->assertDatabaseHas('ad_conversion_events', [
            'contact_id' => $contact->id,
            'event_name' => 'QualifiedLead',
            'value' => '1500.00',
            'fbclid' => 'fb-click',
        ]);
        Queue::assertPushed(DispatchConversionEventJob::class, 1);
    }
}
