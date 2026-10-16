<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\InboxLabel;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $labels = InboxLabel::where('workspace_id', $this->workspaceId($request))
            ->orderBy('name')
            ->get();

        return Inertia::render('Inbox/Labels/Index', ['labels' => $labels]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64',
                Rule::unique('inbox_labels')->where('workspace_id', $wid)],
            'color' => ['required', 'string', 'max:16'],
        ]);

        InboxLabel::create(array_merge($validated, ['workspace_id' => $wid]));

        return back()->with('success', 'Etiqueta criada.');
    }

    public function update(Request $request, InboxLabel $label): RedirectResponse
    {
        $this->authorise($request, $label);
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64',
                Rule::unique('inbox_labels')->where('workspace_id', $wid)->ignore($label->id)],
            'color' => ['required', 'string', 'max:16'],
        ]);

        $label->update($validated);

        return back()->with('success', 'Etiqueta atualizada.');
    }

    public function destroy(Request $request, InboxLabel $label): RedirectResponse
    {
        $this->authorise($request, $label);
        $label->delete();

        return back()->with('success', 'Etiqueta excluída.');
    }

    public function attach(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authoriseConversation($request, $conversation);
        $validated = $request->validate(['label_id' => ['required', 'integer']]);

        $label = InboxLabel::where('id', $validated['label_id'])
            ->where('workspace_id', $conversation->workspace_id)
            ->firstOrFail();

        $conversation->labels()->syncWithoutDetaching([$label->id]);

        return response()->json(['ok' => true, 'label' => $label]);
    }

    public function detach(Request $request, Conversation $conversation, InboxLabel $label): JsonResponse
    {
        $this->authoriseConversation($request, $conversation);
        abort_unless((int) $label->workspace_id === (int) $conversation->workspace_id, 403);

        $conversation->labels()->detach($label->id);

        return response()->json(['ok' => true]);
    }

    private function authorise(Request $request, InboxLabel $label): void
    {
        abort_unless((int) $label->workspace_id === $this->workspaceId($request), 403);
    }

    private function authoriseConversation(Request $request, Conversation $conversation): void
    {
        abort_unless((int) $conversation->workspace_id === $this->workspaceId($request), 403);
    }
}
