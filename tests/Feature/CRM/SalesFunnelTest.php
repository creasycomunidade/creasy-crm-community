<?php

namespace Tests\Feature\CRM;

use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesFunnelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_unifies_pipeline_source_campaign_and_revenue(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])
            ->get(route('client.crm.pipelines.index'))
            ->assertOk();

        $pipeline = Pipeline::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $wonStage = $pipeline->stages()->where('conversion_event', 'Purchase')->firstOrFail();
        $contact = Contact::factory()->create([
            'workspace_id' => $context['workspace']->id,
            'source' => 'instagram_comment',
        ]);
        $deal = Deal::create([
            'workspace_id' => $context['workspace']->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $wonStage->id,
            'contact_id' => $contact->id,
            'title' => 'Venda atribuída',
            'value_cents' => 125000,
            'currency' => 'BRL',
        ]);
        $campaign = Campaign::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Lançamento',
            'channel' => 'whatsapp',
            'audience_type' => 'segment',
            'status' => 'completed',
            'totals_json' => ['total' => 1, 'sent' => 1, 'read' => 1, 'clicked' => 1],
            'created_by' => $context['user']->id,
        ]);
        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'read',
            'sent_at' => now(),
            'read_at' => now(),
        ]);
        $deal->touch();

        $this->actingAs($context['user'])
            ->get(route('client.crm.funnel.index', ['days' => 30]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Funnel/Index')
                ->where('needsPipeline', false)
                ->where('funnel.summary.new_deals', 1)
                ->where('funnel.summary.won_deals', 1)
                ->where('funnel.summary.won_value_cents', 125000)
                ->where('funnel.summary.conversion_rate', 100)
                ->where('funnel.sources.0.source', 'Comentário no Instagram')
                ->where('funnel.campaigns.0.name', 'Lançamento')
                ->where('funnel.campaigns.0.won', 1)
                ->where('funnel.campaigns.0.revenue_cents', 125000));
    }

    public function test_funil_never_exposes_another_workspace_data(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $this->actingAs($first['user'])->get(route('client.crm.pipelines.index'))->assertOk();

        $pipeline = Pipeline::where('workspace_id', $first['workspace']->id)->firstOrFail();
        $stage = $pipeline->stages()->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $first['workspace']->id]);
        Deal::create([
            'workspace_id' => $first['workspace']->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'title' => 'Dado isolado',
        ]);

        $this->actingAs($second['user'])
            ->get(route('client.crm.funnel.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('needsPipeline', true)
                ->where('funnel', null));
    }

    public function test_creating_a_deal_directly_in_a_conversion_stage_records_the_event(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.pipelines.index'))->assertOk();
        $pipeline = Pipeline::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $stage = PipelineStage::where('pipeline_id', $pipeline->id)
            ->where('conversion_event', 'Purchase')
            ->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);

        $this->actingAs($context['user'])
            ->postJson(route('client.crm.pipelines.deals.store', $pipeline), [
                'stage_id' => $stage->id,
                'contact_id' => $contact->id,
                'title' => 'Venda direta',
                'value' => 500,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('crm_deals', [
            'workspace_id' => $context['workspace']->id,
            'contact_id' => $contact->id,
            'value_cents' => 50000,
        ]);
    }
}
