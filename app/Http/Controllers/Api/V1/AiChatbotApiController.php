<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\AI\Models\AiChatbot;
use App\Modules\AI\Services\ChatbotRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChatbotApiController extends WorkspaceScopedController
{
    public function __construct(private readonly ChatbotRunner $runner) {}

    /**
     * GET /api/v1/ai/chatbots
     */
    public function index(Request $request): JsonResponse
    {
        $chatbots = AiChatbot::where('workspace_id', $this->workspaceId($request))
            ->latest('id')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'enabled' => $b->enabled,
                'kb_id' => $b->ai_kb_id,
                'channels' => $b->channels ?? [],
                'created_at' => $b->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $chatbots]);
    }

    /**
     * POST /api/v1/ai/chatbots/{id}/chat
     */
    public function chat(Request $request, int $id): JsonResponse
    {
        $chatbot = AiChatbot::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $chatbot) {
            return response()->json(['error' => 'Chatbot not found.'], 404);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'contact_id' => ['nullable', 'integer'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string'],
        ]);

        $result = $this->runner->runForApi(
            $chatbot,
            $validated['message'],
            $this->workspaceId($request),
            $validated['history'] ?? [],
        );

        return response()->json($result);
    }
}
