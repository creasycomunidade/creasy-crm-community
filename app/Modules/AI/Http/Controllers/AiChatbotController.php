<?php

namespace App\Modules\AI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Models\AiKnowledgeBase;
use App\Modules\AI\Services\ChatbotRunner;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AiChatbotController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $chatbots = AiChatbot::where('workspace_id', $wid)->with('knowledgeBase')->latest()->get();
        $knowledgeBases = AiKnowledgeBase::where('workspace_id', $wid)->get(['id', 'name']);

        return Inertia::render('AI/Chatbots/Index', [
            'chatbots' => $chatbots,
            'knowledgeBases' => $knowledgeBases,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
        ]);

        AiChatbot::create(array_merge($validated, ['workspace_id' => $wid]));

        return back()->with('success', __('Chatbot created.'));
    }

    public function update(Request $request, AiChatbot $chatbot): RedirectResponse
    {
        $this->authorise($request, $chatbot);
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'ai_kb_id' => ['nullable', 'integer'],
            'system_prompt' => ['nullable', 'string', 'max:8192'],
            'tone' => ['nullable', 'string', 'max:64'],
            'max_context_chunks' => ['nullable', 'integer', 'min:1', 'max:20'],
            'fallback_reply' => ['nullable', 'string', 'max:512'],
            'channels' => ['nullable', 'array'],
            'enabled' => ['boolean'],
        ]);
        // Verify the knowledge base belongs to this workspace
        if (! empty($validated['ai_kb_id'])) {
            $kbExists = AiKnowledgeBase::where('workspace_id', $wid)
                ->where('id', $validated['ai_kb_id'])
                ->exists();
            abort_unless($kbExists, 422);
        }

        $chatbot->update($validated);

        return back()->with('success', __('Chatbot updated.'));
    }

    public function destroy(Request $request, AiChatbot $chatbot): RedirectResponse
    {
        $this->authorise($request, $chatbot);
        $chatbot->delete();

        return back()->with('success', __('Chatbot deleted.'));
    }

    public function playground(Request $request, AiChatbot $chatbot): JsonResponse
    {
        $this->authorise($request, $chatbot);
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array'],
        ]);

        $wid = $this->workspaceId($request);

        try {
            // Build a synthetic inbound Message model (unsaved) for ChatbotRunner
            $fakeMessage = new Message;
            $fakeMessage->body = $request->message;
            $fakeMessage->direction = 'in';
            $fakeMessage->channel = 'playground';

            // Attach a minimal conversation with workspace context
            $fakeConversation = new Conversation;
            $fakeConversation->workspace_id = $this->workspaceId($request);
            $fakeConversation->id = 0;
            $fakeMessage->setRelation('conversation', $fakeConversation);

            $reply = app(ChatbotRunner::class)->run($chatbot, $fakeMessage, throwOnFailure: true);

            return response()->json([
                'reply' => $reply ?? $chatbot->fallback_reply ?? __('No response was generated.'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI chatbot playground request failed.', [
                'workspace_id' => $wid,
                'chatbot_id' => $chatbot->id,
                'exception' => $e::class,
                'message' => preg_replace('/key=[^&\s]+/', 'key=[REDACTED]', $e->getMessage()),
            ]);

            return response()->json([
                'error' => __('The AI provider could not generate a response. Check the model, API key, or usage quota.'),
            ], 422);
        }
    }

    private function authorise(Request $request, AiChatbot $chatbot): void
    {
        abort_unless((int) $chatbot->workspace_id === $this->workspaceId($request), 403);
    }
}
