<?php
namespace Tests\Feature\AI;

use App\Models\WorkspaceFile;
use App\Modules\AI\Models\AiSkill;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Services\AgentSkillContext;
use App\Modules\AI\Services\NativeWorkspaceResources;
use App\Modules\AI\Services\WorkspaceResourceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NativeWorkspaceResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_resources_are_isolated_and_customizations_survive_repeated_visits(): void
    {
        $this->withoutVite();
        $a=$this->createWorkspaceContext();$b=$this->createWorkspaceContext();
        $service=app(NativeWorkspaceResources::class);
        $service->ensureSkills($a['workspace']->id);$service->ensureDocuments($a['workspace']->id,$a['user']->id);
        $this->assertSame(193,AiSkill::where('workspace_id',$a['workspace']->id)->count());
        $this->assertSame(25,WorkspaceFile::where('workspace_id',$a['workspace']->id)->count());
        $this->assertSame(0,AiSkill::where('workspace_id',$b['workspace']->id)->count());
        $skill=AiSkill::where('workspace_id',$a['workspace']->id)->first();$skill->update(['instructions'=>'Nossa regra personalizada']);
        $file=WorkspaceFile::where('workspace_id',$a['workspace']->id)->first();$file->update(['content'=>'Nosso documento personalizado']);
        $service->ensureSkills($a['workspace']->id);$service->ensureDocuments($a['workspace']->id,$a['user']->id);
        $this->assertSame('Nossa regra personalizada',$skill->fresh()->instructions);
        $this->assertSame('Nosso documento personalizado',$file->fresh()->content);
        $this->assertSame(193,AiSkill::where('workspace_id',$a['workspace']->id)->count());
        $this->assertSame(25,WorkspaceFile::where('workspace_id',$a['workspace']->id)->count());
        $this->assertSame(0,AiSkill::where('auto_activate',true)->count());
        $this->actingAs($a['user'])->get(route('client.ai.library.index'))->assertOk()->assertInertia(fn(Assert $page)=>$page->component('AI/Library/Index')->missing('catalog')->has('missions')->has('projects'));
    }

    public function test_native_skill_is_used_by_agent_without_external_files(): void
    {
        $a=$this->createWorkspaceContext();app(NativeWorkspaceResources::class)->ensureSkills($a['workspace']->id);
        $skill=AiSkill::where('workspace_id',$a['workspace']->id)->where('slug','respondesk-cs-draft-response')->firstOrFail();
        $agent=AiAgent::create(['workspace_id'=>$a['workspace']->id,'slug'=>'review','name'=>'Atendimento','system_prompt'=>'Ajude','enabled'=>true,'layer'=>'business','persona'=>'Atendimento','soul'=>'Ajude','description'=>'Atendimento de teste']);
        $agent->assignedSkills()->attach($skill);
        $context=app(AgentSkillContext::class)->build($agent,'Prepare uma resposta');
        $this->assertStringContainsString('Redigir resposta ao cliente',$context);
        $this->assertStringNotContainsString('.claude',$context);
        $this->assertStringNotContainsString('SKILL.md',$context);
    }

    public function test_every_adapted_resource_is_native_and_has_portuguese_guidance(): void
    {
        $entries=collect(app(WorkspaceResourceCatalog::class)->entries());
        $skills=$entries->where('kind','skill');$this->assertCount(193,$skills);
        foreach($skills as $e){
            $this->assertSame(2,$e['version']);
            $this->assertStringContainsString('Como trabalhar no Respondesk',$e['content']);
            $this->assertDoesNotMatchRegularExpression('/\.claude|SKILL\.md|\/workspace|Claude Code/i',$e['content']);
            $this->assertNotSame($e['slug'],$e['name']);
        }
        foreach($entries->where('kind','template') as $e){
            $this->assertStringContainsString('Responsável',$e['content']);
            $this->assertStringContainsString('Plano de ação',$e['content']);
        }
    }
    public function test_existing_custom_skill_is_reused_and_not_replaced(): void
    {
        $a=$this->createWorkspaceContext();
        $skill=AiSkill::create(['workspace_id'=>$a['workspace']->id,'slug'=>'nexus-cs-draft-response','name'=>'Atendimento personalizado','instructions'=>'Nossa política de atendimento','enabled'=>false,'category'=>'custom','version'=>3]);
        app(NativeWorkspaceResources::class)->ensureSkills($a['workspace']->id);
        $this->assertSame('Nossa política de atendimento',$skill->fresh()->instructions);
        $this->assertFalse($skill->fresh()->enabled);
        $this->assertSame(193,AiSkill::where('workspace_id',$a['workspace']->id)->count());
        $this->assertDatabaseMissing('ai_skills',['workspace_id'=>$a['workspace']->id,'slug'=>'respondesk-cs-draft-response']);
    }

}
