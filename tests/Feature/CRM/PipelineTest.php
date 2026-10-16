<?php

namespace Tests\Feature\CRM;

use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_creates_an_isolated_default_pipeline(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();

        $this->actingAs($first['user'])
            ->get(route('client.crm.pipelines.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Pipelines/Index')
                ->has('pipelines', 1)
                ->where('pipeline.name', 'Pipeline de vendas')
                ->has('pipeline.stages', 6));

        $this->assertDatabaseCount('crm_pipelines', 1);
        $this->assertDatabaseHas('crm_pipelines', ['workspace_id' => $first['workspace']->id]);
        $this->assertDatabaseMissing('crm_pipelines', ['workspace_id' => $second['workspace']->id]);
    }

    public function test_a_workspace_can_create_multiple_pipelines_and_custom_stages(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.pipelines.index'))->assertOk();

        $response = $this->actingAs($context['user'])->post(route('client.crm.pipelines.store'), [
            'name' => 'Pós-venda',
            'color' => '#4f46e5',
        ]);

        $pipeline = Pipeline::where('workspace_id', $context['workspace']->id)
            ->where('name', 'Pós-venda')
            ->firstOrFail();

        $response->assertRedirect(route('client.crm.pipelines.index', ['pipeline' => $pipeline->uuid]));
        $this->assertCount(6, $pipeline->stages);

        $this->actingAs($context['user'])
            ->postJson(route('client.crm.pipelines.stages.store', $pipeline), [
                'name' => 'Renovação',
                'color' => '#10b981',
            ])
            ->assertCreated()
            ->assertJsonPath('stage.name', 'Renovação');
    }

    public function test_deal_can_move_between_columns_but_not_across_workspaces(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $this->actingAs($first['user'])->get(route('client.crm.pipelines.index'))->assertOk();

        $pipeline = Pipeline::where('workspace_id', $first['workspace']->id)->firstOrFail();
        $source = $pipeline->stages()->firstOrFail();
        $target = $pipeline->stages()->where('id', '!=', $source->id)->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $first['workspace']->id]);

        $created = $this->actingAs($first['user'])
            ->postJson(route('client.crm.pipelines.deals.store', $pipeline), [
                'stage_id' => $source->id,
                'contact_id' => $contact->id,
                'title' => 'Contrato empresarial',
                'value' => '1299.90',
            ])
            ->assertCreated()
            ->assertJsonPath('deal.value_cents', 129990);

        $deal = Deal::findOrFail($created->json('deal.id'));
        $this->actingAs($first['user'])
            ->patchJson(route('client.crm.pipelines.deals.move', $deal), ['stage_id' => $target->id])
            ->assertOk()
            ->assertJsonPath('deal.stage_id', $target->id);

        $this->actingAs($second['user'])
            ->patchJson(route('client.crm.pipelines.deals.move', $deal), ['stage_id' => $target->id])
            ->assertForbidden();
    }

    public function test_deleting_a_column_moves_its_cards_and_keeps_the_pipeline_valid(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.pipelines.index'))->assertOk();
        $pipeline = Pipeline::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $stage = $pipeline->stages()->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        $deal = Deal::create([
            'workspace_id' => $context['workspace']->id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'title' => 'Lead de teste',
        ]);

        $response = $this->actingAs($context['user'])
            ->deleteJson(route('client.crm.pipelines.stages.destroy', $stage))
            ->assertOk();

        $this->assertDatabaseMissing('crm_pipeline_stages', ['id' => $stage->id]);
        $this->assertNotSame($stage->id, $deal->fresh()->stage_id);
        $this->assertSame(
            $response->json('moved_to_stage_uuid'),
            PipelineStage::findOrFail($deal->fresh()->stage_id)->uuid,
        );
    }

    public function test_inbox_contact_link_prefills_the_kanban_without_crossing_workspaces(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $contact = Contact::factory()->create(['workspace_id' => $first['workspace']->id]);

        $this->actingAs($first['user'])
            ->get(route('client.crm.pipelines.index', ['contact' => $contact->uuid]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('prefillContactId', $contact->id)
                ->where('prefillAlreadyAdded', false));

        $this->actingAs($second['user'])
            ->get(route('client.crm.pipelines.index', ['contact' => $contact->uuid]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('prefillContactId', null)
                ->where('prefillAlreadyAdded', false));
    }

    public function test_inbox_can_create_and_move_a_contact_between_pipeline_columns_without_redirecting(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.pipelines.index'))->assertOk();

        $pipeline = Pipeline::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $first = $pipeline->stages()->firstOrFail();
        $second = $pipeline->stages()->where('id', '!=', $first->id)->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);
        $account = \App\Modules\Shared\Models\ChannelAccount::create(['workspace_id' => $context['workspace']->id, 'channel' => 'whatsapp', 'provider' => 'evolution', 'display_name'=>'Canal de teste', 'credentials' => [], 'status' => 'active']);
        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'workspace_id' => $context['workspace']->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        $this->actingAs($context['user'])
            ->postJson(route('client.inbox.kanban.update', $conversation), [
                'pipeline_uuid' => $pipeline->uuid,
                'stage_uuid' => $first->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('assignment.pipeline.uuid', $pipeline->uuid)
            ->assertJsonPath('assignment.stage.uuid', $first->uuid);

        $deal = Deal::where('contact_id', $contact->id)->sole();

        $this->actingAs($context['user'])
            ->postJson(route('client.inbox.kanban.update', $conversation), [
                'pipeline_uuid' => $pipeline->uuid,
                'stage_uuid' => $second->uuid,
            ])
            ->assertOk()
            ->assertJsonPath('assignment.stage.uuid', $second->uuid);

        $this->assertDatabaseCount('crm_deals', 1);
        $this->assertSame($second->id, $deal->fresh()->stage_id);
    }

    public function test_a_contact_cannot_be_duplicated_in_another_pipeline(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.pipelines.index'))->assertOk();
        $firstPipeline = Pipeline::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $contact = Contact::factory()->create(['workspace_id' => $context['workspace']->id]);

        $this->actingAs($context['user'])
            ->postJson(route('client.crm.pipelines.deals.store', $firstPipeline), [
                'stage_id' => $firstPipeline->stages()->firstOrFail()->id,
                'contact_id' => $contact->id,
            ])
            ->assertCreated();

        $this->actingAs($context['user'])->post(route('client.crm.pipelines.store'), [
            'name' => 'Renovação',
            'color' => '#7c3aed',
        ])->assertRedirect();
        $secondPipeline = Pipeline::where('workspace_id', $context['workspace']->id)
            ->where('id', '!=', $firstPipeline->id)
            ->firstOrFail();

        $this->actingAs($context['user'])
            ->postJson(route('client.crm.pipelines.deals.store', $secondPipeline), [
                'stage_id' => $secondPipeline->stages()->firstOrFail()->id,
                'contact_id' => $contact->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_id');

        $this->assertSame(1, Deal::where('contact_id', $contact->id)->count());
    }
}
