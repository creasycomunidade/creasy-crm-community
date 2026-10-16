<?php

namespace Tests\Feature\AI;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFolder;
use App\Modules\AI\Models\AiAgent;
use App\Modules\AI\Services\AgentActionExecutor;
use App\Modules\AI\Services\AgentWorkspaceActionService;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Leads\Models\TaskBoard;
use App\Modules\Leads\Models\TaskCard;
use App\Modules\Leads\Models\TaskColumn;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AgentWorkspaceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_create_and_update_a_text_file_inside_its_workspace(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        $service = app(AgentWorkspaceActionService::class);

        $created = $service->execute($agent, $context['user'], 'Crie o arquivo proposta.md', [
            'type' => 'create_workspace_file',
            'name' => 'proposta.md',
            'content' => '# Proposta inicial',
        ]);

        $file = WorkspaceFile::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertTrue($created['ok']);
        $this->assertSame('agent', $file->source);
        $this->assertSame('# Proposta inicial', $file->content);

        $service->execute($agent, $context['user'], 'Atualize o arquivo proposta.md', [
            'type' => 'update_workspace_file',
            'file_uuid' => $file->uuid,
            'content' => '# Proposta aprovada',
        ]);

        $this->assertSame('# Proposta aprovada', $file->fresh()->content);
    }

    public function test_agent_can_complete_a_file_request_over_consecutive_messages(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);

        $result = app(AgentWorkspaceActionService::class)->execute(
            $agent,
            $context['user'],
            'Nome: Teste Arquivo. Conteúdo: Teste.',
            [
                'type' => 'create_workspace_file',
                'name' => 'Teste Arquivo',
                'content' => 'Teste',
            ],
            "Crie um arquivo na pasta Arquivos.\nNome: Teste Arquivo. Conteúdo: Teste.",
        );

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'name' => 'Teste Arquivo.md',
            'content' => 'Teste',
            'source' => 'agent',
        ]);
    }

    public function test_agent_can_create_a_file_inside_a_requested_workspace_folder(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        $folder = WorkspaceFolder::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'teste',
        ]);

        app(AgentWorkspaceActionService::class)->execute(
            $agent,
            $context['user'],
            'Crie apresentação.html na pasta teste',
            [
                'type' => 'create_workspace_file',
                'folder_uuid' => $folder->uuid,
                'name' => 'apresentação.html',
                'content' => '<h1>Apresentação</h1>',
            ],
        );

        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'folder_id' => $folder->id,
            'name' => 'apresentação.html',
            'source' => 'agent',
        ]);
    }

    public function test_agent_creates_nested_folders_and_saves_file_with_the_correct_extension(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);

        $result = app(AgentWorkspaceActionService::class)->execute(
            $agent,
            $context['user'],
            'Crie um arquivo JSON chamado clientes na pasta Arquivos/Importações/2026',
            [
                'type' => 'create_workspace_file',
                'folder_path' => 'Arquivos/Importações/2026',
                'name' => 'clientes',
                'content' => '{"clientes":[]}',
            ],
        );

        $parent = WorkspaceFolder::where('workspace_id', $context['workspace']->id)
            ->whereNull('parent_id')->where('name', 'Importações')->firstOrFail();
        $leaf = WorkspaceFolder::where('workspace_id', $context['workspace']->id)
            ->where('parent_id', $parent->id)->where('name', '2026')->firstOrFail();

        $this->assertSame('json', $result['extension']);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'folder_id' => $leaf->id,
            'name' => 'clientes.json',
            'extension' => 'json',
            'mime_type' => 'application/json',
        ]);
    }

    public function test_agent_can_create_a_folder_hierarchy_and_move_an_existing_file_into_it(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        $service = app(AgentWorkspaceActionService::class);
        $file = WorkspaceFile::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'pagina.html', 'extension' => 'html', 'mime_type' => 'text/html',
            'size' => 13, 'content' => '<h1>Site</h1>', 'source' => 'agent',
        ]);

        $folderResult = $service->execute($agent, $context['user'], 'Crie a pasta Projetos/Site', [
            'type' => 'create_workspace_folder',
            'path' => 'Projetos/Site',
        ]);
        $moveResult = $service->execute($agent, $context['user'], 'Mova pagina.html para Projetos/Site', [
            'type' => 'move_workspace_file',
            'file_uuid' => $file->uuid,
            'folder_path' => 'Projetos/Site',
        ]);

        $leaf = WorkspaceFolder::where('workspace_id', $context['workspace']->id)
            ->where('uuid', $folderResult['folder_uuid'])->firstOrFail();
        $this->assertTrue($moveResult['ok']);
        $this->assertSame($leaf->id, $file->fresh()->folder_id);
        $this->assertSame('pagina.html', $file->fresh()->name);
    }

    public function test_folder_paths_are_isolated_between_workspaces(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $firstAgent = $this->agent($first['workspace']->id);
        $secondAgent = $this->agent($second['workspace']->id);
        $service = app(AgentWorkspaceActionService::class);

        $service->execute($firstAgent, $first['user'], 'Crie a pasta Clientes/Ativos', [
            'type' => 'create_workspace_folder', 'path' => 'Clientes/Ativos',
        ]);
        $service->execute($secondAgent, $second['user'], 'Crie a pasta Clientes/Ativos', [
            'type' => 'create_workspace_folder', 'path' => 'Clientes/Ativos',
        ]);

        $this->assertSame(2, WorkspaceFolder::where('name', 'Clientes')->count());
        $this->assertSame(2, WorkspaceFolder::where('name', 'Ativos')->count());
        $this->assertSame(2, WorkspaceFolder::distinct()->count('workspace_id'));
    }
    public function test_agent_understands_common_typo_in_create_file_request(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);

        $result = app(AgentWorkspaceActionService::class)->execute(
            $agent,
            $context['user'],
            'Crei um arquivo em Arquivos para mim. Nome: Teste Arquivo. Conteúdo: Teste.',
            [
                'type' => 'create_workspace_file',
                'name' => 'Teste Arquivo',
                'content' => 'Teste',
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'name' => 'Teste Arquivo.md',
            'content' => 'Teste',
        ]);
    }

    public function test_explicit_html_request_corrects_wrong_extension_and_uses_workspace_root(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = $this->agent($context['workspace']->id);

        $result = app(AgentWorkspaceActionService::class)->execute(
            $agent,
            $context['user'],
            'Crie uma página HTML chamada painel',
            [
                'type' => 'create_workspace_file',
                'name' => 'painel.md',
                'content' => '<!doctype html><html><body><h1>Painel</h1></body></html>',
            ],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('text/html', $result['mime_type']);
        $this->assertNull($result['folder_uuid']);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'folder_id' => null,
            'name' => 'painel.html',
            'extension' => 'html',
            'mime_type' => 'text/html',
            'source' => 'agent',
        ]);
    }

    public function test_executor_recovers_generated_html_when_action_block_is_incomplete(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = $this->agent($context['workspace']->id);
        $folder = WorkspaceFolder::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'Testes',
        ]);
        $foreign = $this->createWorkspaceContext();
        WorkspaceFolder::create([
            'workspace_id' => $foreign['workspace']->id,
            'created_by' => $foreign['user']->id,
            'name' => 'Testes',
        ]);

        $reply = "Página pronta.\n```respondesk-html\n"
            ."<!doctype html><html><body><h1>Cursos</h1></body></html>\n"
            ."```respondesk-actions\n{\"actions\":[";

        $result = app(AgentActionExecutor::class)->process(
            $agent,
            $context['user'],
            'Crie landing-page-cursos.html na pasta Testes',
            $reply,
        );

        $this->assertCount(1, $result['results']);
        $this->assertStringNotContainsString('<!doctype html>', mb_strtolower($result['reply']));
        $this->assertStringContainsString('landing-page-cursos.html', $result['reply']);
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'folder_id' => $folder->id,
            'name' => 'landing-page-cursos.html',
            'extension' => 'html',
            'mime_type' => 'text/html',
            'source' => 'agent',
        ]);
        $this->assertDatabaseMissing('workspace_files', [
            'workspace_id' => $foreign['workspace']->id,
            'name' => 'landing-page-cursos.html',
        ]);
    }

    public function test_executor_saves_large_file_content_outside_the_action_json(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = $this->agent($context['workspace']->id);
        $reply = "Arquivo preparado.\n```respondesk-file:html\n"
            ."<!doctype html><html><body><h1>Portal</h1></body></html>\n```\n"
            ."```respondesk-actions\n"
            .'{"actions":[{"type":"create_workspace_file","name":"portal.html","folder_path":"Sites/Portal"}]}'
            ."\n```";

        $result = app(AgentActionExecutor::class)->process(
            $agent,
            $context['user'],
            'Crie portal.html na pasta Sites/Portal',
            $reply,
        );

        $this->assertCount(1, $result['results']);
        $this->assertStringNotContainsString('<!doctype html>', mb_strtolower($result['reply']));
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'name' => 'portal.html',
            'extension' => 'html',
            'content' => '<!doctype html><html><body><h1>Portal</h1></body></html>',
        ]);
    }

    public function test_executor_resumes_an_old_generated_file_when_the_new_action_is_truncated(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = $this->agent($context['workspace']->id);
        $previousReply = "```respondesk-html\n"
            ."<!doctype html><html><body><h1>Cursos</h1></body></html>\n```";
        $truncatedReply = "Executando agora.\n```respondesk-actions\n{\"actions\":[";

        $result = app(AgentActionExecutor::class)->process(
            $agent,
            $context['user'],
            'Agora você tem permissão, pode gerar o arquivo',
            $truncatedReply,
            'Agora você tem permissão, pode gerar o arquivo',
            $previousReply,
            "Crie landing-page-cursos.html na pasta Testes\nAgora você tem permissão, pode gerar o arquivo",
        );

        $this->assertCount(1, $result['results']);
        $this->assertStringContainsString('landing-page-cursos.html', $result['reply']);
        $folder = WorkspaceFolder::where('workspace_id', $context['workspace']->id)
            ->where('name', 'Testes')->firstOrFail();
        $this->assertDatabaseHas('workspace_files', [
            'workspace_id' => $context['workspace']->id,
            'folder_id' => $folder->id,
            'name' => 'landing-page-cursos.html',
            'mime_type' => 'text/html',
        ]);
    }
    public function test_agent_refuses_to_save_a_truncated_html_document(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = $this->agent($context['workspace']->id);

        try {
            app(AgentWorkspaceActionService::class)->execute(
                $agent,
                $context['user'],
                'Crie uma página HTML completa',
                [
                    'type' => 'create_workspace_file',
                    'name' => 'incompleta.html',
                    'content' => '<!doctype html><html><head><style>body { color: red; }',
                ],
            );
            $this->fail('HTML truncado não deveria ser salvo.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('incompleto', $exception->getMessage());
        }

        $this->assertDatabaseMissing('workspace_files', ['name' => 'incompleta.html']);
    }
    public function test_agent_cannot_update_a_file_from_another_workspace(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $this->actingAs($first['user']);
        $agent = $this->agent($first['workspace']->id);
        $foreign = WorkspaceFile::create([
            'workspace_id' => $second['workspace']->id,
            'created_by' => $second['user']->id,
            'name' => 'privado.md', 'extension' => 'md', 'mime_type' => 'text/plain',
            'size' => 7, 'content' => 'segredo', 'source' => 'user',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(AgentWorkspaceActionService::class)->execute($agent, $first['user'], 'Atualize o arquivo privado.md', [
            'type' => 'update_workspace_file', 'file_uuid' => $foreign->uuid, 'content' => 'vazou',
        ]);
    }

    public function test_deletion_requires_an_explicit_user_request(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        $file = WorkspaceFile::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'contrato.md', 'extension' => 'md', 'mime_type' => 'text/plain',
            'size' => 4, 'content' => 'texto', 'source' => 'user',
        ]);

        $this->expectException(HttpException::class);
        app(AgentWorkspaceActionService::class)->execute($agent, $context['user'], 'Veja o arquivo contrato.md', [
            'type' => 'delete_workspace_file', 'file_uuid' => $file->uuid,
        ]);
    }

    public function test_destructive_action_cannot_switch_to_a_different_target(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        WorkspaceFile::create([
            'workspace_id' => $context['workspace']->id, 'created_by' => $context['user']->id,
            'name' => 'apagar.md', 'extension' => 'md', 'mime_type' => 'text/plain', 'size' => 1, 'content' => 'a', 'source' => 'user',
        ]);
        $protected = WorkspaceFile::create([
            'workspace_id' => $context['workspace']->id, 'created_by' => $context['user']->id,
            'name' => 'manter.md', 'extension' => 'md', 'mime_type' => 'text/plain', 'size' => 1, 'content' => 'b', 'source' => 'user',
        ]);

        try {
            app(AgentWorkspaceActionService::class)->execute($agent, $context['user'], 'Exclua apagar.md', [
                'type' => 'delete_workspace_file', 'file_uuid' => $protected->uuid,
            ]);
            $this->fail('A troca de alvo destrutivo deveria ser bloqueada.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('workspace_files', ['uuid' => $protected->uuid, 'deleted_at' => null]);
    }

    public function test_agent_can_manage_tasks_contacts_and_sales_funnel_records(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);
        $service = app(AgentWorkspaceActionService::class);
        $board = TaskBoard::create(['workspace_id' => $context['workspace']->id, 'name' => 'Tarefas']);
        $todo = TaskColumn::create(['workspace_id' => $context['workspace']->id, 'board_id' => $board->id, 'name' => 'A fazer']);
        $done = TaskColumn::create(['workspace_id' => $context['workspace']->id, 'board_id' => $board->id, 'name' => 'Concluído']);

        $service->execute($agent, $context['user'], 'Crie a tarefa e depois atualize quando necessário', [
            'type' => 'create_task', 'board_uuid' => $board->uuid, 'column_uuid' => $todo->uuid,
            'title' => 'Enviar proposta', 'priority' => 'high', 'checklist' => ['Revisar valores', 'Enviar PDF'],
        ]);
        $task = TaskCard::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $service->execute($agent, $context['user'], 'Mova e atualize a tarefa para concluída', [
            'type' => 'update_task', 'task_uuid' => $task->uuid, 'column_uuid' => $done->uuid,
            'priority' => 'normal', 'checklist' => [['title' => 'Revisar valores', 'completed' => true]],
        ]);
        $this->assertSame($done->id, $task->fresh()->column_id);
        $this->assertTrue($task->fresh()->checklist->first()->completed);

        $service->execute($agent, $context['user'], 'Cadastre o contato Ana', [
            'type' => 'create_contact', 'first_name' => 'Ana', 'phone' => '+55 47 99999-0000',
        ]);
        $contact = Contact::where('workspace_id', $context['workspace']->id)->where('first_name', 'Ana')->firstOrFail();
        $pipeline = Pipeline::create(['workspace_id' => $context['workspace']->id, 'name' => 'Vendas']);
        $stage = PipelineStage::create(['workspace_id' => $context['workspace']->id, 'pipeline_id' => $pipeline->id, 'name' => 'Novo lead']);
        $service->execute($agent, $context['user'], 'Adicione Ana ao funil como negócio', [
            'type' => 'create_deal', 'pipeline_uuid' => $pipeline->uuid, 'stage_uuid' => $stage->uuid,
            'contact_uuid' => $contact->uuid, 'title' => 'Proposta Ana', 'value' => 1500.50,
        ]);
        $deal = Deal::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertSame(150050, $deal->value_cents);
    }

    public function test_agent_campaign_tools_only_create_a_draft(): void
    {
        $context = $this->createWorkspaceContext();
        $this->actingAs($context['user']);
        $agent = $this->agent($context['workspace']->id);

        app(AgentWorkspaceActionService::class)->execute($agent, $context['user'], 'Crie um rascunho de campanha de e-mail', [
            'type' => 'create_campaign_draft', 'name' => 'Boas-vindas', 'channel' => 'email',
            'audience_type' => 'segment', 'audience_ref' => 'clientes-novos',
        ]);

        $campaign = Campaign::where('workspace_id', $context['workspace']->id)->firstOrFail();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame('email', $campaign->channel);
    }

    private function agent(int $workspaceId): AiAgent
    {
        return AiAgent::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $workspaceId,
            'slug' => 'oracle', 'name' => 'Maestro', 'layer' => 'business',
            'description' => 'Coordena o espaço.', 'persona' => 'Maestro.',
            'system_prompt' => 'Ajude a equipe.', 'enabled' => true,
        ]);
    }
}
