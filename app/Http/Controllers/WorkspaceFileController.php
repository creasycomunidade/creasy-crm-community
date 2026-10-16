<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceFileController extends Controller
{
    private const EDITABLE = ['md', 'markdown', 'txt', 'html', 'htm', 'css', 'js', 'json', 'csv', 'xml', 'yaml', 'yml'];

    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        app(\App\Modules\AI\Services\NativeWorkspaceResources::class)->ensureDocuments($workspaceId, $request->user()->id);
        $files = WorkspaceFile::query()
            ->where('workspace_id', $workspaceId)
            ->latest('updated_at')
            ->get()
            ->map(fn (WorkspaceFile $file) => $this->payload($file));

        $folders = WorkspaceFolder::query()
            ->where('workspace_id', $workspaceId)
            ->orderBy('name')
            ->get()
            ->map(fn (WorkspaceFolder $folder) => [
                'uuid' => $folder->uuid,
                'parent_uuid' => $folder->parent_id
                    ? WorkspaceFolder::withTrashed()->whereKey($folder->parent_id)->value('uuid')
                    : null,
                'name' => $folder->name,
                'updated_at' => $folder->updated_at?->toIso8601String(),
            ]);

        $plan = null;
        $canUseMindMaps = ! $plan || $plan->hasModule('mind_maps');
        $mindMaps = $canUseMindMaps ? \App\Modules\AI\Models\AiMindMap::where('workspace_id', $workspaceId)
            ->with('folder:id,uuid,name')->orderBy('title')
            ->get(['id', 'uuid', 'title', 'workspace_folder_id', 'updated_at'])
            ->map(fn ($map) => ['uuid' => $map->uuid, 'name' => $map->title, 'kind' => 'mind_map',
                'folder_uuid' => $map->folder?->uuid, 'folder_name' => $map->folder?->name,
                'updated_at' => $map->updated_at?->toIso8601String(),
                'url' => route('client.ai.mind-maps.show', ['mindMap' => $map, 'from_folder' => $map->folder?->uuid]),
            ]) : [];
        $initialFolder = $folders->firstWhere('uuid', $request->query('folder'))['uuid'] ?? null;
        return Inertia::render('client/WorkspaceFiles/Index', ['files' => $files, 'folders' => $folders,
            'mindMaps' => $mindMaps, 'canUseMindMaps' => $canUseMindMaps, 'initialFolder' => $initialFolder]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'folder_uuid' => [
                'nullable', 'uuid',
                Rule::exists('workspace_folders', 'uuid')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->whereNull('deleted_at')),
            ],
        ]);
        $upload = $request->file('file');
        $extension = strtolower($upload->getClientOriginalExtension());
        $editable = in_array($extension, self::EDITABLE, true);
        $content = null;

        if ($editable) {
            $content = file_get_contents($upload->getRealPath());
            abort_if($content === false || strlen($content) > 2 * 1024 * 1024, 422, 'Arquivos editáveis podem ter no máximo 2 MB.');
        }

        $folderId = ! empty($validated['folder_uuid'])
            ? WorkspaceFolder::where('workspace_id', $workspaceId)->where('uuid', $validated['folder_uuid'])->value('id')
            : null;
        $storedPath = null;
        if (! $editable) {
            $storedPath = $upload->storeAs(
                "workspace-files/{$workspaceId}",
                Str::uuid().'.'.($extension ?: 'bin'),
                'local'
            );
        }

        WorkspaceFile::create([
            'workspace_id' => $workspaceId,
            'created_by' => $request->user()->id,
            'folder_id' => $folderId,
            'name' => Str::limit($upload->getClientOriginalName(), 255, ''),
            'extension' => $extension ?: null,
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'storage_path' => $storedPath,
            'content' => $content,
            'source' => 'user',
        ]);

        return back()->with('success', 'Arquivo adicionado ao espaço de trabalho.');
    }

    public function createText(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:2097152'],
            'folder_uuid' => [
                'nullable', 'uuid',
                Rule::exists('workspace_folders', 'uuid')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->whereNull('deleted_at')),
            ],
        ]);
        $name = trim($validated['name']);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $name .= '.md';
            $extension = 'md';
        }
        abort_unless(in_array($extension, self::EDITABLE, true), 422, 'O novo arquivo deve usar um formato de texto editável.');

        $folderId = ! empty($validated['folder_uuid'])
            ? WorkspaceFolder::where('workspace_id', $workspaceId)->where('uuid', $validated['folder_uuid'])->value('id')
            : null;
        $content = (string) ($validated['content'] ?? '');

        WorkspaceFile::create([
            'workspace_id' => $workspaceId,
            'created_by' => $request->user()->id,
            'folder_id' => $folderId,
            'name' => $name,
            'extension' => $extension,
            'mime_type' => $extension === 'html' || $extension === 'htm' ? 'text/html' : 'text/plain',
            'size' => strlen($content),
            'content' => $content,
            'source' => 'user',
        ]);

        return back()->with('success', 'Novo arquivo criado.');
    }

    public function update(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $this->authorise($request, $file);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'content' => [Rule::requiredIf($file->isEditable()), 'nullable', 'string', 'max:2097152'],
            'folder_uuid' => [
                'nullable', 'uuid',
                Rule::exists('workspace_folders', 'uuid')->where(fn ($query) => $query->where('workspace_id', $this->workspaceId($request))->whereNull('deleted_at')),
            ],
        ]);

        $folderId = ! empty($validated['folder_uuid'])
            ? WorkspaceFolder::where('workspace_id', $this->workspaceId($request))->where('uuid', $validated['folder_uuid'])->value('id')
            : null;
        $newExtension = strtolower((string) pathinfo($validated['name'], PATHINFO_EXTENSION));
        if ($file->isEditable()) {
            abort_unless(in_array($newExtension, self::EDITABLE, true), 422, 'Arquivos de texto devem manter uma extensão editável.');
        }

        $file->update([
            'name' => $validated['name'],
            'folder_id' => $folderId,
            'extension' => $file->isEditable() ? $newExtension : $file->extension,
            'mime_type' => $file->isEditable()
                ? (in_array($newExtension, ['html', 'htm'], true) ? 'text/html' : 'text/plain')
                : $file->mime_type,
            'content' => $file->isEditable() ? ($validated['content'] ?? '') : $file->content,
            'size' => $file->isEditable() ? strlen($validated['content'] ?? '') : $file->size,
        ]);

        return back()->with('success', 'Arquivo atualizado.');
    }

    public function preview(Request $request, WorkspaceFile $file): mixed
    {
        $this->authorise($request, $file);
        $headers = [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $file->name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; script-src 'none'; object-src 'self'; sandbox",
        ];

        if ($file->isEditable()) {
            return response($file->content ?? '', 200, $headers);
        }

        abort_unless($file->storage_path && Storage::disk('local')->exists($file->storage_path), 404);
        return response()->file(Storage::disk('local')->path($file->storage_path), $headers);
    }

    public function download(Request $request, WorkspaceFile $file): StreamedResponse
    {
        $this->authorise($request, $file);
        if ($file->isEditable()) {
            return response()->streamDownload(fn () => print($file->content ?? ''), $file->name, [
                'Content-Type' => $file->mime_type ?: 'text/plain; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort_unless($file->storage_path && Storage::disk('local')->exists($file->storage_path), 404);
        return Storage::disk('local')->download($file->storage_path, $file->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $this->authorise($request, $file);
        if ($file->storage_path) {
            Storage::disk('local')->delete($file->storage_path);
        }
        $file->delete();
        return back()->with('success', 'Arquivo removido.');
    }

    private function payload(WorkspaceFile $file): array
    {
        return [
            'uuid' => $file->uuid,
            'name' => $file->name,
            'extension' => $file->extension,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'source' => $file->source,
            'folder_uuid' => $file->folder_id
                ? WorkspaceFolder::withTrashed()->whereKey($file->folder_id)->value('uuid')
                : null,
            'editable' => $file->isEditable(),
            'content' => $file->isEditable() ? $file->content : null,
            'updated_at' => $file->updated_at?->toIso8601String(),
            'preview_url' => route('client.workspace-files.preview', $file),
            'download_url' => route('client.workspace-files.download', $file),
        ];
    }

    private function authorise(Request $request, WorkspaceFile $file): void
    {
        abort_unless((int) $file->workspace_id === $this->workspaceId($request), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
