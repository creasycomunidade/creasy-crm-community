<?php

namespace Tests\Feature\AI;

use App\Models\Workspace;
use App\Modules\AI\Models\AiMindMap;
use App\Modules\AI\Models\AiMemoryNode;
use App\Modules\AI\Services\Llm\LlmResponse;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\AI\Services\SecondBrainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MindMapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_workspace_can_create_edit_and_export_a_mind_map(): void
    {
        $context = $this->createWorkspaceContext();
        $session = ['current_workspace_id' => $context['workspace']->id];

        $this->actingAs($context['user'])->withSession($session)
            ->post(route('client.ai.mind-maps.store'), [
                'title' => 'Planejamento comercial', 'template' => 'project',
            ])->assertRedirect();

        $map = AiMindMap::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertCount(6, $map->nodes);
        $root = $map->nodes[0];
        $root['data']['notes'] = 'Meta anual';
        $longLabel = str_repeat("Mensagem completa com acentuação.\n", 500)."Fim da mensagem.";
        $root['data']['label'] = $longLabel;
        unset($root['type']);
        $nodes = $map->nodes;
        $nodes[0] = $root;

        $this->actingAs($context['user'])->withSession($session)
            ->patchJson(route('client.ai.mind-maps.update', $map), [
                'title' => 'Planejamento 2027', 'description' => 'Mapa estratégico',
                'theme' => 'aurora', 'nodes' => $nodes, 'edges' => $map->edges,
                'settings' => ['layout' => 'mindmap'],
            ])->assertOk()->assertJsonStructure(['saved_at']);

        $saved = AiMindMap::find($map->id);
        $this->assertSame('Meta anual', $saved->nodes[0]['data']['notes']);
        $this->assertSame($longLabel, $saved->nodes[0]['data']['label']);
        foreach ($saved->nodes as $node) {
            $this->assertSame('mindNode', $node['type']);
        }
        $this->actingAs($context['user'])->withSession($session)
            ->get(route('client.ai.mind-maps.show', $map))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('AI/MindMaps/Editor')
                ->where('mindMap.nodes.0.type', 'mindNode')
                ->where('mindMap.nodes.0.data.label', $longLabel));
        $this->actingAs($context['user'])->withSession($session)
            ->get(route('client.ai.mind-maps.export', $map))
            ->assertOk()->assertHeader('content-type', 'application/json');
    }

    #[Test]
    public function legacy_maps_restore_their_renderer_on_every_reload_without_rewriting_content(): void
    {
        $context = $this->createWorkspaceContext();
        $label = str_repeat("Texto longo do mapa antigo.\n", 100)."Fim.";
        $map = AiMindMap::create([
            'workspace_id' => $context['workspace']->id,
            'title' => 'Mapa antigo',
            'theme' => 'aurora',
            'nodes' => [
                ['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => $label, 'level' => 0]],
                ['id' => 'child', 'type' => 'default', 'position' => ['x' => 320, 'y' => 0], 'data' => ['label' => 'Filho', 'color' => '#7c3aed']],
            ],
            'edges' => [['id' => 'edge', 'source' => 'root', 'target' => 'child', 'sourceHandle' => 'source-right', 'targetHandle' => 'target-left']],
        ]);
        for ($reload = 0; $reload < 2; $reload++) {
            $this->actingAs($context['user'])
                ->withSession(['current_workspace_id' => $context['workspace']->id])
                ->get(route('client.ai.mind-maps.show', $map))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->component('AI/MindMaps/Editor')
                    ->where('mindMap.nodes.0.type', 'mindNode')
                    ->where('mindMap.nodes.1.type', 'mindNode')
                    ->where('mindMap.nodes.0.data.label', $label)
                    ->where('mindMap.edges.0.sourceHandle', 'source-right')
                    ->where('mindMap.edges.0.targetHandle', 'target-left'));
        }
        $this->assertSame($map->nodes, $map->fresh()->nodes);
    }

    #[Test]
    public function maps_are_strictly_isolated_by_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        $foreign = Workspace::factory()->create();
        $map = AiMindMap::create([
            'workspace_id' => $foreign->id, 'title' => 'Mapa privado', 'theme' => 'aurora',
            'nodes' => [['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Segredo']]],
            'edges' => [],
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->get(route('client.ai.mind-maps.show', $map))
            ->assertForbidden();
    }

    #[Test]
    public function a_mind_map_becomes_searchable_knowledge_in_the_second_brain(): void
    {
        $workspace = Workspace::factory()->create();
        AiMindMap::create([
            'workspace_id' => $workspace->id, 'title' => 'Expansão comercial',
            'description' => 'Estratégia para novas cidades', 'theme' => 'aurora',
            'nodes' => [
                ['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Expansão comercial', 'notes' => 'Priorizar Blumenau']],
                ['id' => 'child', 'position' => ['x' => 200, 'y' => 0], 'data' => ['label' => 'Novas cidades']],
            ],
            'edges' => [['id' => 'edge', 'source' => 'root', 'target' => 'child']],
        ]);

        $counts = app(SecondBrainService::class)->syncWorkspace($workspace->id);

        $this->assertSame(1, $counts['mind_maps']);
        $this->assertDatabaseHas('ai_memory_nodes', [
            'workspace_id' => $workspace->id, 'source_type' => 'mind_map',
            'title' => 'Mapa mental: Expansão comercial',
        ]);
        $this->assertStringContainsString('Priorizar Blumenau', AiMemoryNode::where('workspace_id', $workspace->id)->where('source_type', 'mind_map')->firstOrFail()->content);
    }

    #[Test]
    public function ai_can_generate_an_isolated_horizontal_mind_map(): void
    {
        $context = $this->createWorkspaceContext();
        $map = AiMindMap::create([
            'workspace_id' => $context['workspace']->id, 'created_by' => $context['user']->id,
            'title' => 'Rascunho', 'theme' => 'aurora',
            'nodes' => [['id' => 'old-root', 'type' => 'mindNode', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Rascunho', 'level' => 0]]],
            'edges' => [],
        ]);
        $gateway = Mockery::mock(LlmGateway::class);
        $gateway->shouldReceive('chat')->once()->withArgs(fn (int $workspaceId) => $workspaceId === $context['workspace']->id)
            ->andReturn(new LlmResponse(json_encode([
                'title' => 'Plano comercial',
                'nodes' => [
                    ['id' => 'root', 'parent_id' => null, 'label' => 'Plano comercial', 'notes' => 'Objetivo', 'icon' => 'target', 'color' => '#7c3aed'],
                    ['id' => 'vendas', 'parent_id' => 'root', 'label' => 'Vendas', 'notes' => 'Metas', 'icon' => 'chart', 'color' => '#2563eb'],
                    ['id' => 'prospeccao', 'parent_id' => 'vendas', 'label' => 'Prospecção', 'notes' => '', 'icon' => 'person', 'color' => '#059669'],
                ],
            ], JSON_UNESCAPED_UNICODE), 10, 20, 'test-model', 5));
        $this->app->instance(LlmGateway::class, $gateway);

        $response = $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->postJson(route('client.ai.mind-maps.ai', $map), [
                'prompt' => 'Crie um plano comercial', 'mode' => 'create',
                'nodes' => $map->nodes, 'edges' => [],
            ]);

        $response->assertOk()->assertJsonPath('title', 'Plano comercial')->assertJsonCount(3, 'nodes')->assertJsonCount(2, 'edges');
        $nodes = collect($response->json('nodes'))->keyBy('id');
        $this->assertSame(0, $nodes['root']['position']['x']);
        $this->assertGreaterThan($nodes['root']['position']['x'], $nodes['vendas']['position']['x']);
        $this->assertGreaterThan($nodes['vendas']['position']['x'], $nodes['prospeccao']['position']['x']);
        $this->assertSame('source-right', $response->json('edges.0.sourceHandle'));
        $this->assertSame('target-left', $response->json('edges.0.targetHandle'));
    }

    #[Test]
    public function clients_can_import_json_markdown_and_csv_as_new_maps(): void
    {
        $context = $this->createWorkspaceContext();
        $session = ['current_workspace_id' => $context['workspace']->id];
        $json = json_encode([
            'format' => 'fazos-mind-map-v2', 'title' => 'Mapa JSON', 'theme' => 'oceano',
            'nodes' => [
                ['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Raiz', 'level' => 0, 'color' => '#2563eb']],
                ['id' => 'child', 'position' => ['x' => 320, 'y' => 0], 'data' => ['label' => 'Filho', 'level' => 1, 'color' => '#059669']],
            ],
            'edges' => [['source' => 'root', 'target' => 'child', 'data' => ['kind' => 'branch']]],
        ], JSON_UNESCAPED_UNICODE);

        foreach ([
            UploadedFile::fake()->createWithContent('mapa.json', $json),
            UploadedFile::fake()->createWithContent('plano.md', "# Plano Markdown\n\n- Estratégia\n  - Vendas\n  - Marketing"),
            UploadedFile::fake()->createWithContent('processo.csv', "id;título;nível;notas;link;icone;cor\n1;Processo;0;Visão geral;;target;#7c3aed\n2;Etapa;1;Detalhes;https://example.com;check;#059669"),
        ] as $file) {
            $this->actingAs($context['user'])->withSession($session)
                ->post(route('client.ai.mind-maps.import'), ['file' => $file])
                ->assertRedirect();
        }

        $this->assertSame(3, AiMindMap::where('workspace_id', $context['workspace']->id)->count());
        $csvMap = AiMindMap::where('title', 'processo')->firstOrFail();
        $this->assertSame('Detalhes', $csvMap->nodes[1]['data']['notes']);
        $this->assertSame('https://example.com', $csvMap->nodes[1]['data']['link']);
    }

    #[Test]
    public function an_authenticated_user_can_copy_a_shared_map_to_another_workspace_and_owner_can_revoke_it(): void
    {
        $owner = $this->createWorkspaceContext();
        $recipient = $this->createWorkspaceContext();
        $map = AiMindMap::create([
            'workspace_id' => $owner['workspace']->id, 'created_by' => $owner['user']->id,
            'title' => 'Estratégia compartilhada', 'theme' => 'aurora',
            'nodes' => [['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Estratégia', 'level' => 0]]],
            'edges' => [], 'settings' => ['layout' => 'mindmap'],
        ]);

        $share = $this->actingAs($owner['user'])->withSession(['current_workspace_id' => $owner['workspace']->id])
            ->postJson(route('client.ai.mind-maps.share', $map), ['expires_in' => 30])
            ->assertOk()->assertJsonStructure(['url', 'expires_at']);
        $token = data_get($map->fresh()->settings, 'share_token');
        $this->assertSame(64, strlen($token));

        $this->app['auth']->guard()->logout();
        $this->get(route('client.ai.mind-maps.shared', $token))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->patchJson(route('client.ai.mind-maps.update', $map), ['title' => 'Forbidden'])->assertUnauthorized();
        $this->postJson(route('client.ai.mind-maps.shared.copy', $token))->assertUnauthorized();
        $this->actingAs($recipient['user'])->withSession(['current_workspace_id' => $recipient['workspace']->id])
            ->get(route('client.ai.mind-maps.shared', $token))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('AI/MindMaps/Shared')
                ->where('sharedMap.title', 'Estratégia compartilhada')
                ->where('sharedMap.same_workspace', false));
        $this->actingAs($recipient['user'])->withSession(['current_workspace_id' => $recipient['workspace']->id])
            ->post(route('client.ai.mind-maps.shared.copy', $token))->assertRedirect();
        $this->assertDatabaseHas('ai_mind_maps', [
            'workspace_id' => $recipient['workspace']->id,
            'title' => 'Cópia de Estratégia compartilhada',
        ]);

        $this->actingAs($owner['user'])->withSession(['current_workspace_id' => $owner['workspace']->id])
            ->deleteJson(route('client.ai.mind-maps.share.revoke', $map))->assertOk();
        $this->actingAs($recipient['user'])->withSession(['current_workspace_id' => $recipient['workspace']->id])
            ->get(route('client.ai.mind-maps.shared', $token))->assertNotFound();
    }
    #[Test]
    public function public_links_expire_and_duplicates_do_not_inherit_access(): void
    {
        $owner = $this->createWorkspaceContext();
        $map = AiMindMap::create(['workspace_id' => $owner['workspace']->id, 'created_by' => $owner['user']->id,
            'title' => 'Privado', 'theme' => 'aurora', 'nodes' => [['id' => 'root', 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => 'Visível', 'notes' => 'Nota interna', 'memory_id' => 999, 'icon' => 'rocket', 'image_url' => '/storage/topic.png', 'image_alt' => 'Lançamento']]],
            'edges' => [['id' => 'edge', 'source' => 'root', 'target' => 'child', 'sourceHandle' => 'source-right', 'targetHandle' => 'target-left', 'data' => ['kind' => 'relationship', 'private_reference' => 'internal']]], 'settings' => ['share_token' => str_repeat('a', 64), 'share_expires_at' => now()->addDay()->toIso8601String()]]);
        $this->get(route('client.ai.mind-maps.shared', str_repeat('a', 64)))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sharedMap.nodes.0.data.label', 'Visível')->where('sharedMap.nodes.0.data.icon', 'rocket')->where('sharedMap.nodes.0.data.image_url', '/storage/topic.png')->where('sharedMap.nodes.0.data.image_alt', 'Lançamento')->missing('sharedMap.nodes.0.data.notes')->missing('sharedMap.nodes.0.data.memory_id')->missing('sharedMap.workspace_id')->where('sharedMap.edges.0.data.kind', 'relationship')->where('sharedMap.edges.0.sourceHandle', 'source-right')->missing('sharedMap.edges.0.data.private_reference'));
        $this->actingAs($owner['user'])->post(route('client.ai.mind-maps.duplicate', $map))->assertRedirect();
        $copy = AiMindMap::where('id', '!=', $map->id)->firstOrFail();
        $this->assertNull(data_get($copy->settings, 'share_token'));
        $this->app['auth']->guard()->logout();
        $this->travel(2)->days();
        $this->get(route('client.ai.mind-maps.shared', str_repeat('a', 64)))->assertNotFound();
        $this->get(route('client.ai.mind-maps.shared', 'invalid'))->assertNotFound();
    }

}
