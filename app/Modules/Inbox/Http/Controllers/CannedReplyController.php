<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\CannedReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CannedReplyController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $replies = CannedReply::where('workspace_id', $this->workspaceId($request))
            ->orderBy('shortcut')
            ->get();

        return Inertia::render('Inbox/CannedReplies/Index', ['cannedReplies' => $replies]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'shortcut' => ['required', 'string', 'max:64',
                Rule::unique('inbox_canned_replies')->where('workspace_id', $wid)],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        CannedReply::create(array_merge($validated, ['workspace_id' => $wid]));

        return back()->with('success', 'Resposta rápida criada.');
    }

    public function update(Request $request, CannedReply $cannedReply): RedirectResponse
    {
        $this->authorise($request, $cannedReply);
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'shortcut' => ['required', 'string', 'max:64',
                Rule::unique('inbox_canned_replies')->where('workspace_id', $wid)->ignore($cannedReply->id)],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $cannedReply->update($validated);

        return back()->with('success', 'Resposta rápida atualizada.');
    }

    public function destroy(Request $request, CannedReply $cannedReply): RedirectResponse
    {
        $this->authorise($request, $cannedReply);
        $cannedReply->delete();

        return back()->with('success', 'Resposta rápida excluída.');
    }

    /** Lightweight JSON endpoint used by the slash-command picker in Show.jsx */
    public function list(Request $request): JsonResponse
    {
        $replies = CannedReply::where('workspace_id', $this->workspaceId($request))
            ->orderBy('shortcut')
            ->get(['id', 'shortcut', 'body']);

        return response()->json($replies);
    }

    private function authorise(Request $request, CannedReply $cannedReply): void
    {
        abort_unless((int) $cannedReply->workspace_id === $this->workspaceId($request), 403);
    }
}
