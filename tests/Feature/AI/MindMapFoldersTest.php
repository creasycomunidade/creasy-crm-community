<?php

namespace Tests\Feature\AI;

use App\Models\WorkspaceFolder;
use App\Modules\AI\Models\AiMindMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MindMapFoldersTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_can_be_created_in_folder_opened_from_files_and_unlinked_without_deletion(): void
    {
        $context = $this->createWorkspaceContext();
        $folder = WorkspaceFolder::create(['workspace_id' => $context['workspace']->id, 'name' => 'Cliente A']);
        $this->actingAs($context['user'])->post(route('client.ai.mind-maps.store'), [
            'title' => 'Dados do cliente', 'template' => 'customer', 'folder_uuid' => $folder->uuid,
        ])->assertSessionHasNoErrors();
        $map = AiMindMap::firstOrFail();
        $this->assertSame($folder->id, $map->workspace_folder_id);
        $this->get(route('client.workspace-files.index', ['folder' => $folder->uuid]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('initialFolder', $folder->uuid)
                ->where('mindMaps.0.uuid', $map->uuid)->where('mindMaps.0.folder_uuid', $folder->uuid)
                ->missing('mindMaps.0.nodes')->missing('mindMaps.0.settings'));
        $this->get(route('client.ai.mind-maps.show', ['mindMap' => $map, 'from_folder' => $folder->uuid]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('returnFolder.uuid', $folder->uuid));
        $this->getJson(route('client.ai.mind-maps.show', $map))->assertOk()
            ->assertJsonPath('mindMap.uuid', $map->uuid)->assertJsonStructure(['mindMap' => ['nodes', 'edges']]);
        $this->get(route('client.ai.mind-maps.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('maps.data.0.uuid', $map->uuid));
        $this->delete(route('client.workspace-folders.destroy', $folder))->assertSessionHas('error');
        $this->assertNotSoftDeleted($folder);
        $this->patch(route('client.ai.mind-maps.folder', $map), ['folder_uuid' => null])->assertSessionHasNoErrors();
        $this->assertNull($map->fresh()->workspace_folder_id);
        $this->assertDatabaseHas('ai_mind_maps', ['id' => $map->id, 'title' => 'Dados do cliente']);
        $this->delete(route('client.workspace-folders.destroy', $folder))->assertSessionHas('success');
        $this->assertSoftDeleted($folder);
    }

    public function test_existing_map_can_move_between_folders_without_copying_its_content(): void
    {
        $context = $this->createWorkspaceContext();
        $a = WorkspaceFolder::create(['workspace_id' => $context['workspace']->id, 'name' => 'A']);
        $b = WorkspaceFolder::create(['workspace_id' => $context['workspace']->id, 'name' => 'B']);
        $map = AiMindMap::create(['workspace_id' => $context['workspace']->id, 'title' => 'Original', 'nodes' => [],
            'settings' => ['share_token' => 'preserve-me'], 'workspace_folder_id' => $a->id]);
        $this->actingAs($context['user'])->patch(route('client.ai.mind-maps.folder', $map), ['folder_uuid' => $b->uuid])->assertSessionHasNoErrors();
        $this->assertSame($b->id, $map->fresh()->workspace_folder_id);
        $this->assertSame('preserve-me', $map->fresh()->settings['share_token']);
        $this->assertDatabaseCount('ai_mind_maps', 1);
        $this->get(route('client.ai.mind-maps.show', ['mindMap' => $map, 'from_folder' => $a->uuid]))
            ->assertInertia(fn (Assert $page) => $page->where('returnFolder', null));
    }

    public function test_foreign_and_deleted_folders_and_maps_cannot_be_linked_or_listed(): void
    {
        $first = $this->createWorkspaceContext();
        $other = $this->createWorkspaceContext();
        $folder = WorkspaceFolder::create(['workspace_id' => $other['workspace']->id, 'name' => 'Privado']);
        $map = AiMindMap::create(['workspace_id' => $first['workspace']->id, 'title' => 'Meu mapa', 'nodes' => []]);
        $foreign = AiMindMap::create(['workspace_id' => $other['workspace']->id, 'title' => 'Privado', 'nodes' => []]);
        $this->actingAs($first['user'])->patch(route('client.ai.mind-maps.folder', $map), ['folder_uuid' => $folder->uuid])->assertSessionHasErrors('folder_uuid');
        $this->getJson(route('client.ai.mind-maps.show', $foreign))->assertForbidden();
        $this->patch(route('client.ai.mind-maps.folder', $foreign), ['folder_uuid' => null])->assertForbidden();
        $this->post(route('client.ai.mind-maps.store'), ['title' => 'Inválido', 'folder_uuid' => $folder->uuid])->assertSessionHasErrors('folder_uuid');
        $this->get(route('client.workspace-files.index'))->assertInertia(fn (Assert $page) => $page->has('mindMaps', 1)->where('mindMaps.0.uuid', $map->uuid));
        $deleted = WorkspaceFolder::create(['workspace_id' => $first['workspace']->id, 'name' => 'Apagada']);
        $deleted->delete();
        $this->patch(route('client.ai.mind-maps.folder', $map), ['folder_uuid' => $deleted->uuid])->assertSessionHasErrors('folder_uuid');
        $this->patch(route('client.ai.mind-maps.folder', $map), [])->assertSessionHasErrors('folder_uuid');
        $this->assertNull($map->fresh()->workspace_folder_id);
    }
}
