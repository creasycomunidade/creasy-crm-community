<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Models\AiAgentMemory;
use App\Modules\AI\Models\AiProviderConfig;
use App\Modules\AI\Services\AgentCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_creates_isolated_agents_with_safe_default_permissions(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($first['workspace']->id);

        $this->assertSame(36, AiAgent::where('workspace_id', $first['workspace']->id)->count());
        $this->assertSame(0, AiAgent::where('workspace_id', $second['workspace']->id)->count());
        $this->assertDatabaseHas('ai_agents', [
            'workspace_id' => $first['workspace']->id,
            'slug' => 'vault-security',
            'permission_mode' => 'read_only',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('ai_agents', [
            'workspace_id' => $first['workspace']->id,
            'slug' => 'nex-sales',
            'permission_mode' => 'approval_required',
        ]);
    }

    public function test_agent_memory_is_encrypted_at_rest(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $memory = AiAgentMemory::create([
            'workspace_id' => $context['workspace']->id,
            'agent_id' => $agent->id,
            'content' => 'Cliente estratégico: Empresa Exemplo',
            'importance' => 4,
        ]);

        $raw = DB::table('ai_agent_memories')->where('id', $memory->id)->value('content');
        $this->assertStringNotContainsString('Empresa Exemplo', $raw);
        $this->assertSame('Cliente estratégico: Empresa Exemplo', $memory->fresh()->content);
    }

    public function test_personal_assistant_memory_is_strictly_isolated_per_user(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $personal = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('slug', AiAgent::PERSONAL_ASSISTANT_SLUG)
            ->firstOrFail();
        $secondUser = User::factory()->create([
            'client_id' => $context['client']->id,
            'role' => User::ROLE_CLIENT,
            'client_role' => User::CLIENT_ROLE_STAFF,
        ]);
        $context['workspace']->members()->syncWithoutDetaching([$secondUser->id => ['role' => 'member']]);

        AiAgentMemory::create([
            'workspace_id' => $context['workspace']->id,
            'agent_id' => $personal->id,
            'user_id' => $context['user']->id,
            'scope' => AiAgentMemory::SCOPE_PRIVATE,
            'content' => 'Segredo pessoal do primeiro usuário',
        ]);
        AiAgentMemory::create([
            'workspace_id' => $context['workspace']->id,
            'agent_id' => $personal->id,
            'user_id' => $secondUser->id,
            'scope' => AiAgentMemory::SCOPE_PRIVATE,
            'content' => 'Segredo pessoal do segundo usuário',
        ]);
        AiAgentMemory::create([
            'workspace_id' => $context['workspace']->id,
            'agent_id' => $personal->id,
            'scope' => AiAgentMemory::SCOPE_WORKSPACE,
            'content' => 'Memória compartilhada legada',
        ]);

        $firstVisible = $personal->memoriesVisibleTo($context['user'])->get();
        $secondVisible = $personal->memoriesVisibleTo($secondUser)->get();

        $this->assertCount(1, $firstVisible);
        $this->assertSame('Segredo pessoal do primeiro usuário', $firstVisible->first()->content);
        $this->assertCount(1, $secondVisible);
        $this->assertSame('Segredo pessoal do segundo usuário', $secondVisible->first()->content);
    }

    public function test_regular_agents_never_load_another_users_private_memory(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('slug', 'atlas-project')->firstOrFail();
        $secondUser = User::factory()->create([
            'client_id' => $context['client']->id,
            'role' => User::ROLE_CLIENT,
            'client_role' => User::CLIENT_ROLE_STAFF,
        ]);

        foreach ([
            [$context['user']->id, AiAgentMemory::SCOPE_PRIVATE, 'Privada do primeiro'],
            [$secondUser->id, AiAgentMemory::SCOPE_PRIVATE, 'Privada do segundo'],
            [null, AiAgentMemory::SCOPE_WORKSPACE, 'Compartilhada da equipe'],
        ] as [$userId, $scope, $content]) {
            AiAgentMemory::create([
                'workspace_id' => $context['workspace']->id,
                'agent_id' => $agent->id,
                'user_id' => $userId,
                'scope' => $scope,
                'content' => $content,
            ]);
        }

        $visible = $agent->memoriesVisibleTo($context['user'])->get()->pluck('content')->all();

        $this->assertContains('Privada do primeiro', $visible);
        $this->assertContains('Compartilhada da equipe', $visible);
        $this->assertNotContains('Privada do segundo', $visible);
    }

    public function test_company_agents_store_shared_memory_while_personal_agent_uses_user_memory(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $companyAgent = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('slug', 'atlas-project')->firstOrFail();
        $personalAgent = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('slug', AiAgent::PERSONAL_ASSISTANT_SLUG)->firstOrFail();

        $this->assertSame(AiAgentMemory::SCOPE_WORKSPACE, $companyAgent->memoryScope());
        $this->assertNull($companyAgent->memoryOwnerId($context['user']));
        $this->assertSame(AiAgentMemory::SCOPE_PRIVATE, $personalAgent->memoryScope());
        $this->assertSame($context['user']->id, $personalAgent->memoryOwnerId($context['user']));
    }

    public function test_clearing_memories_deletes_only_the_current_users_private_memory(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('slug', AiAgent::PERSONAL_ASSISTANT_SLUG)->firstOrFail();
        $secondUser = User::factory()->create([
            'client_id' => $context['client']->id,
            'role' => User::ROLE_CLIENT,
            'client_role' => User::CLIENT_ROLE_STAFF,
        ]);

        foreach ([$context['user']->id, $secondUser->id] as $userId) {
            AiAgentMemory::create([
                'workspace_id' => $context['workspace']->id,
                'agent_id' => $agent->id,
                'user_id' => $userId,
                'scope' => AiAgentMemory::SCOPE_PRIVATE,
                'content' => 'Privada '.$userId,
            ]);
        }

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.ai.agents.memories.clear', $agent))
            ->assertRedirect();

        $this->assertDatabaseMissing('ai_agent_memories', ['agent_id' => $agent->id, 'user_id' => $context['user']->id]);
        $this->assertDatabaseHas('ai_agent_memories', ['agent_id' => $agent->id, 'user_id' => $secondUser->id]);
    }

    public function test_workspace_member_cannot_manage_agents(): void
    {
        $context = $this->createWorkspaceContext();
        app(AgentCatalog::class)->ensureForWorkspace($context['workspace']->id);
        $agent = AiAgent::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $member = User::factory()->create([
            'client_id' => $context['client']->id,
            'role' => User::ROLE_CLIENT,
            'client_role' => User::CLIENT_ROLE_STAFF,
        ]);
        $context['workspace']->members()->syncWithoutDetaching([$member->id => ['role' => 'member']]);

        $this->actingAs($member)
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->put(route('client.ai.agents.update', $agent), [
                'name' => 'Nome alterado',
                'enabled' => false,
                'soul' => '',
                'system_prompt' => '',
                'permission_mode' => 'read_only',
                'provider' => null,
                'model' => null,
            ])
            ->assertForbidden();
    }

    public function test_user_can_create_and_delete_only_a_custom_agent_in_the_current_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        AiProviderConfig::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'gemini',
            'credentials' => ['api_key' => 'test-key'],
            'default_model_chat' => 'gemini-3.5-flash-lite',
            'enabled' => true,
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.ai.agents.store'), [
                'name' => 'Especialista em Orçamentos',
                'description' => 'Prepara rascunhos de orçamentos para a equipe.',
                'soul' => '',
                'system_prompt' => '',
                'permission_mode' => 'approval_required',
                'provider' => 'gemini',
                'model' => 'gemini-3.5-flash-lite',
                'enabled' => true,
            ])->assertRedirect();

        $agent = AiAgent::where('workspace_id', $context['workspace']->id)
            ->where('name', 'Especialista em Orçamentos')->firstOrFail();
        $this->assertStringStartsWith('custom-', $agent->slug);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.ai.agents.destroy', $agent))
            ->assertRedirect();
        $this->assertDatabaseMissing('ai_agents', ['id' => $agent->id]);
    }
}
