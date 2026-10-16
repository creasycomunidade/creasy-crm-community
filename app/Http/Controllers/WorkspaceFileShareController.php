<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WorkspaceFileShareController extends Controller
{
    public function store(Request $request, WorkspaceFile $file): RedirectResponse
    {
        $this->authoriseFile($request, $file);
        $validated = $request->validate(['lifetime' => ['required', 'in:1_day,7_days,30_days,permanent']]);
        $expiresAt = match ($validated['lifetime']) {
            '1_day' => now()->addDay(),
            '7_days' => now()->addDays(7),
            '30_days' => now()->addDays(30),
            default => null,
        };

        $share = WorkspaceFileShare::create([
            'workspace_id' => $file->workspace_id,
            'workspace_file_id' => $file->id,
            'created_by' => $request->user()->id,
            'expires_at' => $expiresAt,
        ]);

        return back()
            ->with('success', 'Link compartilhado criado e adicionado em Links e páginas.')
            ->with('shared_url', route('public.workspace-file-shares.show', $share->token));
    }

    public function destroy(Request $request, WorkspaceFileShare $share): RedirectResponse
    {
        abort_unless((int) $share->workspace_id === $this->workspaceId($request), 403);
        $share->update(['revoked_at' => now()]);
        $share->delete();
        return back()->with('success', 'Link removido e acesso público revogado.');
    }

    public function show(string $token): mixed
    {
        $share = WorkspaceFileShare::where('token', $token)->with('file')->firstOrFail();
        abort_unless($share->isAvailable(), 404);
        $file = $share->file;

        WorkspaceFileShare::whereKey($share->id)->update([
            'views' => $share->views + 1,
            'last_viewed_at' => now(),
        ]);

        $inline = str_starts_with((string) $file->mime_type, 'image/')
            || str_starts_with((string) $file->mime_type, 'audio/')
            || str_starts_with((string) $file->mime_type, 'video/')
            || $file->mime_type === 'application/pdf'
            || $file->isEditable();
        $headers = [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"', '', $file->name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; script-src 'none'; object-src 'self'; frame-src 'none'; form-action 'none'; base-uri 'none'; sandbox",
        ];

        if ($file->isEditable()) {
            return response($file->content ?? '', 200, $headers);
        }

        abort_unless($file->storage_path && Storage::disk('local')->exists($file->storage_path), 404);
        return response()->file(Storage::disk('local')->path($file->storage_path), $headers);
    }

    private function authoriseFile(Request $request, WorkspaceFile $file): void
    {
        abort_unless((int) $file->workspace_id === $this->workspaceId($request), 403);
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }
}
