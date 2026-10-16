<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleDriveKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_can_import_an_authorized_drive_file_without_exposing_tokens(): void
    {
        Queue::fake();
        $context = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $context['workspace']->id, 'name' => 'Documentos']);
        $connection = $this->driveConnection($context['workspace']->id);

        Http::fake([
            'www.googleapis.com/drive/v3/files/file-123*' => Http::response($this->metadata('file-123', 'Manual atualizado'), 200),
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->post(route('client.ai.knowledge-bases.google-drive.import', $kb), [
                'file_ids' => ['file-123'],
            ])
            ->assertRedirect();

        $document = AiKbDocument::where('kb_id', $kb->id)->firstOrFail();
        $this->assertSame('google_drive', $document->external_provider);
        $this->assertSame('file-123', $document->source_ref);
        $this->assertSame($connection->id, $document->workspace_integration_id);
        $this->assertTrue($document->sync_enabled);
        Queue::assertPushed(IndexDocumentJob::class, fn (IndexDocumentJob $job) => $job->documentId === $document->id);

        $rawCredentials = DB::table('workspace_integration_connections')->where('id', $connection->id)->value('credentials');
        $this->assertStringNotContainsString('workspace-access-secret', $rawCredentials);
    }

    #[Test]
    public function a_workspace_cannot_import_drive_files_into_another_workspaces_base(): void
    {
        Queue::fake();
        $first = $this->createWorkspaceContext();
        $second = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $first['workspace']->id, 'name' => 'Privada']);
        $this->driveConnection($second['workspace']->id);

        $this->actingAs($second['user'])
            ->withSession(['current_workspace_id' => $second['workspace']->id])
            ->post(route('client.ai.knowledge-bases.google-drive.import', $kb), [
                'file_ids' => ['file-123'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ai_kb_documents', 0);
    }

    #[Test]
    public function disconnecting_drive_removes_only_that_workspaces_indexed_copies(): void
    {
        $context = $this->createWorkspaceContext();
        $kb = AiKnowledgeBase::create(['workspace_id' => $context['workspace']->id, 'name' => 'Privada']);
        $connection = $this->driveConnection($context['workspace']->id);
        $document = AiKbDocument::create([
            'kb_id' => $kb->id,
            'workspace_integration_id' => $connection->id,
            'source_type' => 'file',
            'external_provider' => 'google_drive',
            'source_ref' => 'file-123',
            'title' => 'Documento',
            'sync_enabled' => true,
        ]);
        DB::table('ai_kb_chunks')->insert([
            'kb_id' => $kb->id,
            'document_id' => $document->id,
            'ord' => 0,
            'content' => 'Conteúdo privado',
            'tokens' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.ai.google-drive.disconnect'))
            ->assertRedirect();

        $this->assertDatabaseMissing('workspace_integration_connections', ['id' => $connection->id]);
        $this->assertDatabaseMissing('ai_kb_documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('ai_kb_chunks', ['document_id' => $document->id]);
    }

    #[Test]
    public function service_refreshes_expired_token_and_returns_supported_files(): void
    {
        $context = $this->createWorkspaceContext();
        $this->googleApplication();
        $connection = WorkspaceIntegration::create([
            'workspace_id' => $context['workspace']->id,
            'provider' => 'google_drive',
            'name' => 'Drive',
            'credentials' => [
                'access_token' => 'expired-token',
                'refresh_token' => 'refresh-secret',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ],
            'status' => 'connected',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600], 200),
            'www.googleapis.com/drive/v3/files*' => Http::response(['files' => [$this->metadata('file-1', 'Políticas')]], 200),
        ]);

        $result = app(GoogleDriveService::class)->listFiles($connection);

        $this->assertSame('file-1', $result['files'][0]['id']);
        $this->assertSame('fresh-token', $connection->fresh()->credentials['access_token']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/drive/v3/files')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    private function driveConnection(int $workspaceId): WorkspaceIntegration
    {
        return WorkspaceIntegration::create([
            'workspace_id' => $workspaceId,
            'provider' => 'google_drive',
            'name' => 'Google Drive · empresa@example.com',
            'credentials' => [
                'access_token' => 'workspace-access-secret',
                'refresh_token' => 'workspace-refresh-secret',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'settings' => ['email' => 'empresa@example.com'],
            'status' => 'connected',
        ]);
    }

    private function googleApplication(): IntegrationConfig
    {
        return IntegrationConfig::create([
            'provider' => 'google_workspace',
            'label' => 'Google Workspace',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => [
                'client_id' => 'google-client-id',
                'client_secret' => 'google-client-secret',
                'refresh_token' => 'global-refresh-token',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function metadata(string $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.document',
            'modifiedTime' => '2026-08-24T12:00:00.000Z',
            'headRevisionId' => 'revision-1',
            'webViewLink' => "https://docs.google.com/document/d/{$id}/edit",
            'trashed' => false,
        ];
    }
}
