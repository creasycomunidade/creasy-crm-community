<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceFolderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'parent_uuid' => [
                'nullable', 'uuid',
                Rule::exists('workspace_folders', 'uuid')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->whereNull('deleted_at')),
            ],
        ]);

        WorkspaceFolder::create([
            'workspace_id' => $workspaceId,
            'parent_id' => ! empty($validated['parent_uuid'])
                ? WorkspaceFolder::where('workspace_id', $workspaceId)->where('uuid', $validated['parent_uuid'])->value('id')
                : null,
            'created_by' => $request->user()->id,
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'Nova pasta criada.');
    }

    public function update(Request $request, WorkspaceFolder $folder): RedirectResponse
    {
        $this->authorise($request, $folder);
        $workspaceId = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'parent_uuid' => [
                'nullable', 'uuid', 'not_in:'.$folder->uuid,
                Rule::exists('workspace_folders', 'uuid')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->whereNull('deleted_at')),
            ],
        ]);

        $parent = ! empty($validated['parent_uuid'])
            ? WorkspaceFolder::where('workspace_id', $workspaceId)->where('uuid', $validated['parent_uuid'])->first()
            : null;
        abort_if($parent && $this->isDescendant($parent, $folder), 422, 'Uma pasta não pode ser movida para dentro dela mesma.');

        $folder->update(['name' => trim($validated['name']), 'parent_id' => $parent?->id]);
        return back()->with('success', 'Pasta atualizada.');
    }

    public function destroy(Request $request, WorkspaceFolder $folder): RedirectResponse
    {
        $this->authorise($request, $folder);
        if ($folder->children()->exists() || $folder->files()->exists() || $folder->mindMaps()->exists()) {
            return back()->with('error', 'A pasta não pode ser apagada porque ainda contém arquivos, mapas mentais ou subpastas. Mova ou apague o conteúdo primeiro.');
        }
        $folder->delete();
        return back()->with('success', 'Pasta removida.');
    }

    private function isDescendant(WorkspaceFolder $candidate, WorkspaceFolder $folder): bool
    {
        $parentId = $candidate->parent_id;
        while ($parentId !== null) {
            if ((int) $parentId === (int) $folder->id) {
                return true;
            }
            $parentId = WorkspaceFolder::whereKey($parentId)->value('parent_id');
        }
        return false;
    }

    private function authorise(Request $request, WorkspaceFolder $folder): void
    {
        abort_unless((int) $folder->workspace_id === $this->workspaceId($request), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
