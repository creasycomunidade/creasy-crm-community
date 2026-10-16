<?php

namespace Tests\Feature;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileShare;
use App\Models\WorkspaceFolder;
use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceFilesAndEditingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_text_files_are_private_editable_and_isolated(): void
    {
        Storage::fake('local');
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->post(route('client.workspace-files.store'), [
                'file' => UploadedFile::fake()->createWithContent('manual.md', '# Versão 1'),
            ])
            ->assertRedirect();

        $file = WorkspaceFile::firstOrFail();
        $this->assertSame('# Versão 1', $file->content);
        $this->assertTrue($file->isEditable());

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->patch(route('client.workspace-files.update', $file), ['name' => 'roubado.md', 'content' => 'x'])
            ->assertForbidden();

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->patch(route('client.workspace-files.update', $file), ['name' => 'manual.md', 'content' => '# Versão 2'])
            ->assertRedirect();

        $this->assertSame('# Versão 2', $file->fresh()->content);
    }

    #[Test]
    public function workspace_files_support_nested_folders_preview_and_public_sharing(): void
    {
        Storage::fake('local');
        $context = $this->createWorkspaceContext();
        $session = ['current_workspace_id' => $context['workspace']->id];

        $this->actingAs($context['user'])->withSession($session)
            ->post(route('client.workspace-folders.store'), ['name' => 'Clientes'])
            ->assertRedirect();
        $root = WorkspaceFolder::firstOrFail();

        $this->actingAs($context['user'])->withSession($session)
            ->post(route('client.workspace-folders.store'), ['name' => 'Contratos', 'parent_uuid' => $root->uuid])
            ->assertRedirect();
        $child = WorkspaceFolder::where('name', 'Contratos')->firstOrFail();
        $this->assertSame($root->id, $child->parent_id);

        $this->actingAs($context['user'])->withSession($session)
            ->post(route('client.workspace-files.create-text'), [
                'name' => 'proposta.md',
                'content' => '# Proposta privada',
                'folder_uuid' => $child->uuid,
            ])->assertRedirect();
        $file = WorkspaceFile::firstOrFail();
        $this->assertSame($child->id, $file->folder_id);

        $this->actingAs($context['user'])->withSession($session)
            ->get(route('client.workspace-files.preview', $file))
            ->assertOk()
            ->assertSee('# Proposta privada');

        $this->actingAs($context['user'])->withSession($session)
            ->post(route('client.workspace-files.share', $file), ['lifetime' => '7_days'])
            ->assertSessionHas('shared_url');
        $share = WorkspaceFileShare::firstOrFail();

        $this->get(route('public.workspace-file-shares.show', $share->token))
            ->assertOk()
            ->assertSee('# Proposta privada');

        $this->actingAs($context['user'])->withSession($session)
            ->get(route('client.ai.agent-links.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('AI/AgentArtifacts/Index')
                ->has('artifacts', 1)
                ->where('artifacts.0.type', 'file')
                ->where('artifacts.0.title', 'proposta.md'));
    }

    #[Test]
    public function deleting_a_non_empty_folder_returns_a_handled_message_instead_of_an_error_page(): void
    {
        $context = $this->createWorkspaceContext();
        $session = ['current_workspace_id' => $context['workspace']->id];
        $folder = WorkspaceFolder::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'Com conteúdo',
        ]);
        WorkspaceFile::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'folder_id' => $folder->id,
            'name' => 'documento.md',
            'extension' => 'md',
            'mime_type' => 'text/markdown',
            'content' => '# Documento',
            'size' => 11,
            'source' => 'user',
        ]);

        $this->actingAs($context['user'])
            ->withSession($session)
            ->from(route('client.workspace-files.index'))
            ->delete(route('client.workspace-folders.destroy', $folder))
            ->assertRedirect(route('client.workspace-files.index'))
            ->assertSessionHas('error', 'A pasta não pode ser apagada porque ainda contém arquivos, mapas mentais ou subpastas. Mova ou apague o conteúdo primeiro.');

        $this->assertDatabaseHas('workspace_folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('workspace_files', ['folder_id' => $folder->id]);
    }

    #[Test]
    public function an_empty_workspace_folder_can_be_deleted(): void
    {
        $context = $this->createWorkspaceContext();
        $folder = WorkspaceFolder::create([
            'workspace_id' => $context['workspace']->id,
            'created_by' => $context['user']->id,
            'name' => 'Vazia',
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.workspace-folders.destroy', $folder))
            ->assertRedirect()
            ->assertSessionHas('success', 'Pasta removida.');

        $this->assertSoftDeleted('workspace_folders', ['id' => $folder->id]);
    }
    #[Test]
    public function folders_and_file_shares_cannot_cross_workspaces(): void
    {
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $folder = WorkspaceFolder::create([
            'workspace_id' => $first['workspace']->id,
            'created_by' => $first['user']->id,
            'name' => 'Privada',
        ]);
        $file = WorkspaceFile::create([
            'workspace_id' => $first['workspace']->id,
            'created_by' => $first['user']->id,
            'folder_id' => $folder->id,
            'name' => 'segredo.md',
            'extension' => 'md',
            'mime_type' => 'text/plain',
            'content' => 'segredo',
            'size' => 7,
            'source' => 'user',
        ]);

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->post(route('client.workspace-files.share', $file), ['lifetime' => 'permanent'])
            ->assertForbidden();

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->patch(route('client.workspace-folders.update', $folder), ['name' => 'Invadida'])
            ->assertForbidden();
    }

    #[Test]
    public function knowledge_document_edit_is_reindexed_and_cannot_cross_workspaces(): void
    {
        Queue::fake();
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $first['workspace']->id, 'name' => 'Privada']);
        $document = AiKbDocument::create(['kb_id' => $kb->id, 'source_type' => 'text', 'source_ref' => 'Antes', 'title' => 'Manual']);

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->patch(route('client.ai.documents.update', $document), ['title' => 'Ataque', 'source_ref' => 'Vazamento'])
            ->assertForbidden();

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->patch(route('client.ai.documents.update', $document), ['title' => 'Manual atualizado', 'source_ref' => 'Depois'])
            ->assertRedirect();

        $this->assertSame('Depois', $document->fresh()->source_ref);
        Queue::assertPushed(IndexDocumentJob::class, fn (IndexDocumentJob $job) => $job->documentId === $document->id);
    }

    #[Test]
    public function indexed_file_content_can_be_viewed_edited_and_restored_without_changing_its_source(): void
    {
        Queue::fake();
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $first['workspace']->id, 'name' => 'Manuais']);
        $document = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'file',
            'source_ref' => 'uploads/kb-docs/manual.pdf',
            'title' => 'Manual',
            'status' => 'indexed',
        ]);
        AiKbChunk::create(['kb_id' => $kb->id, 'document_id' => $document->id, 'ord' => 0, 'content' => 'Primeira parte conteúdo comum', 'tokens' => 4]);
        AiKbChunk::create(['kb_id' => $kb->id, 'document_id' => $document->id, 'ord' => 1, 'content' => 'conteúdo comum segunda parte', 'tokens' => 4]);

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->getJson(route('client.ai.documents.content', $document))
            ->assertForbidden();

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->getJson(route('client.ai.documents.content', $document))
            ->assertOk()
            ->assertJsonPath('content', 'Primeira parte conteúdo comum segunda parte')
            ->assertJsonPath('has_override', false);

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->patch(route('client.ai.documents.update', $document), [
                'title' => 'Manual revisado',
                'content' => 'Texto corrigido para a IA.',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame('uploads/kb-docs/manual.pdf', $document->source_ref);
        $this->assertSame('Texto corrigido para a IA.', $document->content_override);
        $this->assertNotNull($document->content_override_updated_at);

        $this->actingAs($first['user'])
            ->withSession(['current_workspace_id' => $first['workspace']->id])
            ->patch(route('client.ai.documents.update', $document), [
                'title' => 'Manual revisado',
                'restore_source' => true,
            ])
            ->assertRedirect();

        $this->assertNull($document->fresh()->content_override);
        Queue::assertPushed(IndexDocumentJob::class, 2);
    }

    #[Test]
    public function markdown_document_keeps_original_formatting_and_has_a_safe_preview(): void
    {
        Storage::fake('public');
        $context = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $context['workspace']->id, 'name' => 'Conteúdo']);
        $markdown = "# Campanha\n\n## WhatsApp\n\n- Primeiro item\n- Segundo item\n\n**Importante**";
        Storage::disk('public')->put('kb-docs/campanha.md', $markdown);
        $document = AiKbDocument::create([
            'kb_id' => $kb->id,
            'source_type' => 'file',
            'source_ref' => 'kb-docs/campanha.md',
            'title' => 'Campanha.md',
            'status' => 'indexed',
        ]);
        AiKbChunk::create([
            'kb_id' => $kb->id,
            'document_id' => $document->id,
            'ord' => 0,
            'content' => '# Campanha ## WhatsApp - Primeiro item',
            'tokens' => 8,
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->getJson(route('client.ai.documents.content', $document))
            ->assertOk()
            ->assertJsonPath('content', $markdown)
            ->assertJsonPath('format', 'markdown');

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->postJson(route('client.ai.markdown.preview'), [
                'content' => "# Título\n\n- Item\n\n<script>alert('x')</script>",
            ])
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, '<h1>Título</h1>')
                && str_contains($html, '<li>Item</li>')
                && ! str_contains($html, '<script>'));
    }

    #[Test]
    public function owner_can_rename_and_soft_delete_a_workspace_when_another_exists(): void
    {
        $context = $this->createWorkspaceContext();
        $other = \App\Models\Workspace::create([
            'name' => 'Outro', 'owner_id' => $context['user']->id, 'client_id' => $context['client']->id,
        ]);
        $other->members()->attach($context['user']->id, ['role' => 'owner']);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->patch(route('client.workspaces.update', $context['workspace']), ['name' => 'Nome novo'])
            ->assertRedirect();
        $this->assertSame('Nome novo', $context['workspace']->fresh()->name);

        $this->delete(route('client.workspaces.destroy', $context['workspace']))->assertRedirect(route('client.workspaces.index'));
        $this->assertSoftDeleted('workspaces', ['id' => $context['workspace']->id]);
        $this->assertSame($other->id, $context['user']->fresh()->workspace_id);
    }
}
