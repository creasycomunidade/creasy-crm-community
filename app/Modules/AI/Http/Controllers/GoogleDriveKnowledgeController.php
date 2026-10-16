<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Jobs\IndexDocumentJob;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Integrations\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleDriveKnowledgeController extends Controller
{
    private const OAUTH_SESSION_KEY = 'google_drive_oauth';

    public function connect(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $returnKb = $request->string('kb')->toString();
        if ($returnKb !== '') {
            $kb = AiKnowledgeBase::where('uuid', $returnKb)->where('workspace_id', $workspaceId)->firstOrFail();
            $returnKb = $kb->uuid;
        }

        if (! $drive->isConfigured()) {
            return back()->with('error', 'O aplicativo Google ainda não foi configurado pelo administrador do sistema.');
        }

        $state = Str::random(64);
        $request->session()->put(self::OAUTH_SESSION_KEY, [
            'state' => $state,
            'workspace_id' => $workspaceId,
            'user_id' => (int) $request->user()->id,
            'return_kb' => $returnKb ?: null,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away($drive->authorizationUrl(
            $state,
            route('client.ai.google-drive.callback'),
        ));
    }

    public function callback(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $oauth = $request->session()->pull(self::OAUTH_SESSION_KEY);
        $returnTo = $oauth['return_to'] ?? null;
        $returnRoute = $this->returnRoute($oauth['return_kb'] ?? null, $returnTo);

        if ($request->filled('error')) {
            return redirect($returnRoute)->with('error', 'A conexão com o Google Drive foi cancelada ou recusada.');
        }
        if (! is_array($oauth)
            || ! hash_equals((string) ($oauth['state'] ?? ''), $request->string('state')->toString())
            || (int) ($oauth['workspace_id'] ?? 0) !== $this->workspaceId($request)
            || (int) ($oauth['user_id'] ?? 0) !== (int) $request->user()->id
            || (int) ($oauth['created_at'] ?? 0) < now()->subMinutes(15)->timestamp) {
            return redirect($returnRoute)->with('error', 'A autorização do Google expirou ou não pôde ser validada. Tente conectar novamente.');
        }

        try {
            $tokens = $drive->exchangeAuthorizationCode(
                $request->string('code')->toString(),
                route('client.ai.google-drive.callback'),
            );
            $profile = $drive->userInfo($tokens['access_token']);
            $email = (string) ($profile['email'] ?? 'Conta Google');

            $connection = WorkspaceIntegration::firstOrNew([
                'workspace_id' => $this->workspaceId($request),
                'provider' => 'google_drive',
            ]);
            $oldCredentials = $connection->exists ? ($connection->credentials ?? []) : [];
            if (($tokens['refresh_token'] ?? '') === '' && ! empty($oldCredentials['refresh_token'])) {
                $tokens['refresh_token'] = $oldCredentials['refresh_token'];
            }
            if (($tokens['refresh_token'] ?? '') === '') {
                throw new \RuntimeException('O Google não forneceu um token de atualização. Remova o acesso anterior na sua Conta Google e conecte novamente.');
            }

            $connection->fill([
                'name' => 'Google Drive · '.$email,
                'credentials' => $tokens,
                'settings' => [
                    'google_account_id' => $profile['id'] ?? null,
                    'email' => $email,
                    'picture' => $profile['picture'] ?? null,
                    'scope' => $tokens['scope'] ?? null,
                ],
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_error' => null,
            ])->save();

            Log::info('Google Drive connected to workspace', [
                'workspace_id' => $connection->workspace_id,
                'connection_id' => $connection->id,
                'user_id' => $request->user()->id,
            ]);

            $message = $returnTo === 'appointments'
                ? 'Google Agenda conectado. Ative a sincronização e salve as configurações.'
                : 'Google Drive conectado. Agora selecione os arquivos que deseja manter sincronizados.';

            return redirect($returnRoute)->with('success', $message);
        } catch (\Throwable $e) {
            report($e);

            return redirect($returnRoute)->with('error', $e->getMessage());
        }
    }

    public function files(Request $request, GoogleDriveService $drive): JsonResponse
    {
        $connection = $this->connection($request);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'page_token' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            return response()->json($drive->listFiles(
                $connection,
                $validated['q'] ?? null,
                $validated['page_token'] ?? null,
            ));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function import(Request $request, AiKnowledgeBase $kb, GoogleDriveService $drive): RedirectResponse
    {
        $this->authoriseKb($request, $kb);
        $connection = $this->connection($request);
        $validated = $request->validate([
            'file_ids' => ['required', 'array', 'min:1', 'max:50'],
            'file_ids.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);

        $imported = 0;
        foreach ($validated['file_ids'] as $fileId) {
            try {
                $metadata = $drive->metadata($connection, $fileId);
                $document = AiKbDocument::updateOrCreate([
                    'kb_id' => $kb->id,
                    'workspace_integration_id' => $connection->id,
                    'external_provider' => 'google_drive',
                    'source_ref' => $fileId,
                ], [
                    // Keep the original enum-compatible type. external_provider is
                    // the authoritative source discriminator for Drive documents.
                    'source_type' => 'file',
                    'title' => (string) ($metadata['name'] ?? 'Arquivo do Google Drive'),
                    'source_metadata' => $metadata,
                    'source_version' => $drive->version($metadata),
                    'sync_enabled' => true,
                    'status' => 'pending',
                    'sync_error' => null,
                ]);
                IndexDocumentJob::dispatch($document->id)->onQueue('ai');
                $imported++;
            } catch (\Throwable $e) {
                Log::warning('Google Drive knowledge import failed', [
                    'workspace_id' => $kb->workspace_id,
                    'kb_id' => $kb->id,
                    'file_id' => $fileId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($imported === 0) {
            return back()->with('error', 'Nenhum arquivo pôde ser importado. Confirme as permissões e os formatos selecionados.');
        }

        return back()->with('success', "{$imported} arquivo(s) do Google Drive enviado(s) para indexação e sincronização automática.");
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $connection = $this->connection($request);

        DB::transaction(function () use ($connection): void {
            AiKbDocument::where('workspace_integration_id', $connection->id)
                ->each(function (AiKbDocument $document): void {
                    $document->chunks()->delete();
                    $document->delete();
                });
            $connection->delete();
        });

        Log::info('Google Drive disconnected from workspace and indexed copies removed', [
            'workspace_id' => $connection->workspace_id,
            'connection_id' => $connection->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Google Drive desconectado e suas cópias indexadas foram removidas deste espaço.');
    }

    private function connection(Request $request): WorkspaceIntegration
    {
        return WorkspaceIntegration::where('workspace_id', $this->workspaceId($request))
            ->where('provider', 'google_drive')
            ->whereIn('status', ['connected', 'error'])
            ->firstOrFail();
    }

    private function authoriseKb(Request $request, AiKnowledgeBase $kb): void
    {
        abort_unless((int) $kb->workspace_id === $this->workspaceId($request), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function returnRoute(?string $kbUuid, ?string $returnTo = null): string
    {
        if ($returnTo === 'appointments') {
            return route('client.appointments.channels.index');
        }

        return $kbUuid
            ? route('client.ai.knowledge-bases.show', $kbUuid)
            : route('client.integrations.index');
    }
}
