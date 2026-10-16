<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\InternalNote;
use App\Models\User;
use App\Modules\Shared\Models\Conversation;
use App\Notifications\MentionedInNoteNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class InternalNoteController extends Controller
{
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $notes = $conversation->internalNotes()->with('user:id,name')->latest()->get();

        return response()->json($notes);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        // Parse @mentions: match @word patterns, resolve to user ids
        preg_match_all('/@(\w+)/', $validated['body'], $matches);
        $mentionedUsernames = $matches[1] ?? [];
        $mentionedUsers = collect();

        if (! empty($mentionedUsernames)) {
            $mentionedUsers = User::forWorkspace($conversation->workspace_id)
                ->whereIn('name', $mentionedUsernames)
                ->get();
        }

        $note = InternalNote::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'mentioned_user_ids' => $mentionedUsers->pluck('id')->all(),
        ]);

        $note->load('user:id,name');

        // Send MentionedInNoteNotification to mentioned users (excluding author)
        if ($mentionedUsers->isNotEmpty()) {
            $recipients = $mentionedUsers->filter(fn ($u) => $u->id !== $request->user()->id);
            if ($recipients->isNotEmpty()) {
                Notification::send(
                    $recipients,
                    new MentionedInNoteNotification($request->user(), $conversation, $validated['body']),
                );
            }
        }

        return response()->json($note, 201);
    }

    private function authorise(Request $request, Conversation $conversation): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $conversation->workspace_id === (int) $workspaceId, 403);
    }
}
