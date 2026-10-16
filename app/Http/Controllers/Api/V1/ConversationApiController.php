<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Modules\Inbox\Services\ChannelAccountAccess;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationApiController extends WorkspaceScopedController
{
    public function __construct(private ChannelAccountAccess $channelAccess) {}

    /**
     * GET /api/v1/conversations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $wsId = $this->workspaceId($request);
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), $wsId);
        $query = Conversation::with('channelAccount')
            ->where('workspace_id', $wsId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->latest('last_message_at');

        if ($request->filled('channel')) {
            $query->whereHas('channelAccount', fn ($q) => $q->where('channel', $request->channel));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_user_id', $request->assigned_to);
        }

        return ConversationResource::collection($query->cursorPaginate(25));
    }

    /**
     * GET /api/v1/conversations/{id}/messages
     */
    public function messages(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $workspaceId = $this->workspaceId($request);
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), $workspaceId);
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->find($id);

        if (! $conversation) {
            return response()->json(['error' => 'Conversation not found.'], 404);
        }

        $messages = $conversation->messages()
            ->orderBy('sent_at')
            ->paginate(50);

        return MessageResource::collection($messages);
    }
}
