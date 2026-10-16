<?php

namespace Tests\Feature\CRM;

use App\Modules\Leads\Models\TaskBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskBoardGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_can_create_independent_task_groups(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user'])->get(route('client.crm.tasks.index'))->assertOk();
        $default = TaskBoard::where('workspace_id', $context['workspace']->id)->where('is_default', true)->firstOrFail();

        $this->actingAs($context['user'])->postJson(route('client.crm.tasks.boards.store'), [
            'name' => 'Marketing',
        ])->assertCreated();
        $group = TaskBoard::where('workspace_id', $context['workspace']->id)->where('name', 'Marketing')->firstOrFail();
        $column = $group->columns()->firstOrFail();
        $this->assertCount(4, $group->columns);

        $this->actingAs($context['user'])->postJson(route('client.crm.tasks.store'), [
            'board_uuid' => $group->uuid,
            'column_id' => $column->id,
            'title' => 'Campanha do grupo Marketing',
        ])->assertCreated();

        $this->assertDatabaseHas('task_cards', ['board_id' => $group->id, 'title' => 'Campanha do grupo Marketing']);
        $this->assertDatabaseMissing('task_cards', ['board_id' => $default->id, 'title' => 'Campanha do grupo Marketing']);
        $this->actingAs($context['user'])->get(route('client.crm.tasks.index', ['board' => $group->uuid]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CRM/Tasks/Index')
                ->where('board.uuid', $group->uuid)
                ->where('board.columns.0.tasks.0.title', 'Campanha do grupo Marketing')
                ->has('boards', 2));
    }

    public function test_task_group_from_another_workspace_is_not_accessible(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $foreign = TaskBoard::create(['workspace_id' => $second['workspace']->id, 'name' => 'Privado', 'is_default' => false]);

        $this->actingAs($first['user'])
            ->get(route('client.crm.tasks.index', ['board' => $foreign->uuid]))
            ->assertNotFound();
    }
}
