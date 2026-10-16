<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationAssigned;
use App\Events\MessageSent;
use App\Events\TypingChanged;
use App\Models\InternalNote;
use App\Models\User;
use App\Modules\Inbox\Models\InboxLabel;
use App\Modules\Inbox\Services\ChannelAccountAccess;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Shared\Services\OutboundMessageSignature;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Services\StorageManager;
use App\Support\Demo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileConversationController extends WorkspaceScopedController
{
    public function __construct(
        private ChannelManager $channelManager,
        private StorageManager $storageManager,
        private OutboundMessageSignature $messageSignature,
        private ChannelAccountAccess $channelAccess,
    ) {}

    /**
     * GET /api/v1/mobile/conversations
     * List conversations with full filter support for the agent inbox.
     */
    public function index(Request $request): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $userId = $request->user()->id;
        $folder = $request->input('folder', 'all');
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), $wsId);

        $conversations = Conversation::where('workspace_id', $wsId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->with(['contact', 'channelAccount', 'lastMessage', 'labels', 'assignedUser'])
            ->when($folder === 'mine', fn ($q) => $q->where('assigned_user_id', $userId))
            ->when($folder === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when(! in_array($folder, ['resolved', 'snoozed'], true), fn ($q) => $q->where('status', 'open'))
            ->when($folder === 'resolved', fn ($q) => $q->where('status', 'resolved'))
            ->when($folder === 'snoozed', fn ($q) => $q->where('status', 'snoozed'))
            ->when($request->channel, fn ($q) => $q->whereHas('channelAccount', fn ($q) => $q->where('channel', $request->channel)))
            ->when($request->account_id, fn ($q) => $q->where('channel_account_id', $request->account_id))
            ->when($request->label_id, fn ($q) => $q->whereHas('labels', fn ($q) => $q->where('inbox_labels.id', $request->label_id)))
            ->when($request->search, function ($q) use ($request) {
                $term = '%'.$request->search.'%';
                $q->whereHas('contact', function ($c) use ($term) {
                    $c->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone_e164', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json([
            'data' => $conversations->map(fn ($c) => $this->formatConversation($c)),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/conversations/{uuid}
     * Full conversation detail including messages and metadata.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid, ['contact', 'channelAccount', 'labels', 'assignedUser']);

        $messages = $conversation->messages()
            ->with('conversation')
            ->orderBy('sent_at')
            ->paginate(50);

        $conversation->update(['unread_count' => 0]);

        $conversation->setAttribute(
            'is_whatsapp_window_open',
            $conversation->channelAccount?->channel !== 'whatsapp' || $conversation->isWhatsappWindowOpen(),
        );

        return response()->json([
            'conversation' => $this->formatConversation($conversation, detail: true),
            'messages' => $messages->map(fn ($m) => $this->formatMessage($m)),
            'messages_meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/mobile/conversations/{uuid}/messages
     * Paginated message history (for loading older messages).
     */
    public function messages(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $messages = $conversation->messages()
            ->orderBy('sent_at')
            ->paginate(50);

        return response()->json([
            'data' => $messages->map(fn ($m) => $this->formatMessage($m)),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/mobile/conversations/{uuid}/reply
     * Send a message (text, template, media).
     */
    public function reply(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid, ['channelAccount']);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4096'],
            'type' => ['nullable', 'in:text,template,image,document,video,audio'],
            'payload' => ['nullable', 'array'],
            'attachment' => [
                'nullable', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,webp,mp4,3gp,mov,mp3,aac,m4a,amr,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
            ],
        ]);

        $msgType = $validated['type'] ?? 'text';
        $msgPayload = $validated['payload'] ?? null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';

            if ($msgType === 'text') {
                $msgType = str_starts_with($mimeType, 'image/') ? 'image'
                    : (str_starts_with($mimeType, 'video/') ? 'video' : 'document');
            }

            $channel = $conversation->channelAccount?->channel ?? 'whatsapp';
            if ($channel === 'whatsapp') {
                $client = CloudApiClient::forWorkspace($conversation->workspace_id);
                if (! $client) {
                    return response()->json(['error' => 'No active WhatsApp account.'], 422);
                }
                $mediaId = $client->uploadMedia($file->getRealPath(), $mimeType);
                $storedPath = $this->storageManager->prefixedPath('message-media/'.$file->hashName());
                $this->storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
                $previewUrl = $this->storageManager->disk()->url($storedPath);

                $msgPayload = array_merge($msgPayload ?? [], [
                    'media_id' => $mediaId,
                    'preview_url' => $previewUrl,
                    'caption' => $validated['body'] ?? null,
                    'filename' => $file->getClientOriginalName(),
                ]);
            } else {
                $storedPath = $this->storageManager->prefixedPath('message-media/'.$file->hashName());
                $this->storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
                $previewUrl = $this->storageManager->disk()->url($storedPath);
                $msgPayload = array_merge($msgPayload ?? [], [
                    'preview_url' => $previewUrl,
                    'caption' => $validated['body'] ?? null,
                    'filename' => $file->getClientOriginalName(),
                ]);
            }

            $validated['body'] = $validated['body'] ?? $file->getClientOriginalName();
        }

        if ($msgType === 'text' && empty($validated['body'])) {
            return response()->json(['error' => 'Message body is required.'], 422);
        }

        if ($conversation->channelAccount?->channel === 'whatsapp'
            && ! $conversation->isWhatsappWindowOpen()
            && $msgType !== 'template') {
            return response()->json([
                'error' => 'WhatsApp 24-hour session is closed. Use an approved template.',
                'window_closed' => true,
            ], 422);
        }

        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';
        $signed = $this->messageSignature->apply(
            $request->user(),
            $channel,
            $validated['body'] ?? null,
            $msgPayload,
            $msgType,
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => $msgType,
            'body' => $signed['body'],
            'payload' => $signed['payload'],
            'status' => 'queued',
            'sent_by' => 'human',
            'user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        $sendError = null;
        try {
            $driver = $this->channelManager->driver($channel);
            $messageId = $driver->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error('Mobile reply send failed', [
                'conversation_id' => $conversation->id,
                'error' => $sendError,
            ]);
            $message->update(['status' => 'failed', 'error_json' => ['message' => $sendError]]);
        }

        $conversation->update(['last_message_at' => now()]);
        if ($conversation->last_inbound_at && ! $conversation->first_response_at) {
            $conversation->update(['first_response_at' => now()]);
        }

        $message->load('conversation');
        MessageSent::dispatch($message);

        return response()->json([
            'message' => $this->formatMessage($message),
            'error' => $sendError,
        ]);
    }

    /**
     * PATCH /api/v1/mobile/conversations/{uuid}/assign
     */
    public function assign(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid, ['channelAccount']);

        $request->validate(['user_id' => ['nullable', 'integer']]);

        $assignedTo = null;
        if ($request->user_id) {
            $assignedTo = User::forWorkspace($conversation->workspace_id)->find($request->user_id);
            abort_unless($assignedTo && $conversation->channelAccount
                && $this->channelAccess->allows($assignedTo, $conversation->channelAccount), 422, 'User not found or not authorised for this channel.');
        }

        $conversation->update(['assigned_user_id' => $request->user_id]);
        ConversationAssigned::dispatch($conversation, $assignedTo);

        return response()->json(['ok' => true, 'assigned_user_id' => $request->user_id]);
    }

    /**
     * PATCH /api/v1/mobile/conversations/{uuid}/status
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $request->validate(['status' => ['required', 'in:open,pending,resolved,snoozed']]);

        $updates = ['status' => $request->status];
        if ($request->status === 'resolved' && ! $conversation->resolved_at) {
            $updates['resolved_at'] = now();
        }
        $conversation->update($updates);

        return response()->json(['ok' => true, 'status' => $request->status]);
    }

    /**
     * POST /api/v1/mobile/conversations/{uuid}/typing
     */
    public function typing(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $request->validate(['is_typing' => ['required', 'boolean']]);
        broadcast(new TypingChanged($conversation, $request->user(), (bool) $request->is_typing))->toOthers();

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/mobile/conversations/{uuid}/handover
     */
    public function handover(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $mode = $request->input('mode', 'human');
        $updates = ['assigned_to' => $mode];
        if ($mode === 'human' && ! $conversation->handover_at) {
            $updates['handover_at'] = now();
        }
        $conversation->update($updates);

        return response()->json(['ok' => true, 'assigned_to' => $mode]);
    }

    /**
     * GET /api/v1/mobile/conversations/{uuid}/notes
     */
    public function notes(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $notes = $conversation->internalNotes()
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $notes->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'user' => $n->user ? ['id' => $n->user->id, 'name' => $n->user->name, 'avatar' => $n->user->avatar] : null,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /api/v1/mobile/conversations/{uuid}/notes
     */
    public function storeNote(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $validated = $request->validate(['body' => ['required', 'string', 'max:4096']]);

        $note = $conversation->internalNotes()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);
        $note->load('user:id,name,avatar');

        return response()->json([
            'id' => $note->id,
            'body' => $note->body,
            'user' => $note->user ? ['id' => $note->user->id, 'name' => $note->user->name] : null,
            'created_at' => $note->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * POST /api/v1/mobile/conversations/{uuid}/labels
     */
    public function attachLabel(Request $request, string $uuid): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $request->validate(['label_id' => ['required', 'integer']]);
        $label = InboxLabel::where('workspace_id', $conversation->workspace_id)
            ->findOrFail($request->label_id);

        $conversation->labels()->syncWithoutDetaching([$label->id]);

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/v1/mobile/conversations/{uuid}/labels/{labelId}
     */
    public function detachLabel(Request $request, string $uuid, int $labelId): JsonResponse
    {
        $conversation = $this->authorisedConversation($request, $uuid);

        $conversation->labels()->detach($labelId);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/v1/mobile/conversations/start
     * Start a new conversation.
     */
    public function start(Request $request): JsonResponse
    {
        $wsId = $this->workspaceId($request);

        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'channel_account_id' => ['required', 'integer'],
        ]);

        $channelAccount = ChannelAccount::where('workspace_id', $wsId)
            ->where('status', 'active')
            ->findOrFail($validated['channel_account_id']);
        abort_unless($this->channelAccess->allows($request->user(), $channelAccount), 403);

        $conversation = Conversation::firstOrCreate(
            [
                'workspace_id' => $wsId,
                'contact_id' => $validated['contact_id'],
                'channel_account_id' => $channelAccount->id,
            ],
            ['status' => 'open']
        );

        $conversation->load(['contact', 'channelAccount', 'labels']);

        return response()->json([
            'conversation' => $this->formatConversation($conversation),
        ], 201);
    }

    // ─── Private formatters ───────────────────────────────────────────────────

    private function authorisedConversation(Request $request, string $uuid, array $with = []): Conversation
    {
        $workspaceId = $this->workspaceId($request);
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), $workspaceId);

        return Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->where('uuid', $uuid)
            ->with($with)
            ->firstOrFail();
    }

    private function formatConversation(Conversation $c, bool $detail = false): array
    {
        $data = [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'status' => $c->status,
            'channel' => $c->channelAccount?->channel,
            'channel_account_id' => $c->channel_account_id,
            'unread_count' => (int) $c->unread_count,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'assigned_user_id' => $c->assigned_user_id,
            'contact' => $c->contact ? [
                'id' => $c->contact->id,
                'name' => Demo::name($c->contact->full_name),
                'phone' => Demo::phone($c->contact->phone_e164),
                'email' => Demo::email($c->contact->email),
                'avatar' => Demo::active() ? null : $c->contact->avatar_url,
            ] : null,
            'channel_account' => $c->channelAccount ? [
                'id' => $c->channelAccount->id,
                'channel' => $c->channelAccount->channel,
                'display_name' => $c->channelAccount->display_name,
            ] : null,
            'labels' => $c->labels?->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
            ])->values(),
            'last_message' => $c->lastMessage ? $this->formatMessage($c->lastMessage) : null,
        ];

        if ($detail) {
            $data['is_whatsapp_window_open'] = $c->getAttribute('is_whatsapp_window_open') ?? true;
            $data['assigned_user'] = $c->assignedUser ? [
                'id' => $c->assignedUser->id,
                'name' => $c->assignedUser->name,
                'avatar' => $c->assignedUser->avatar ?? null,
            ] : null;
            $data['assigned_to'] = $c->assigned_to;
            $data['handover_at'] = $c->handover_at?->toIso8601String();
            $data['resolved_at'] = $c->resolved_at?->toIso8601String();
            $data['created_at'] = $c->created_at->toIso8601String();
        }

        return $data;
    }

    private function formatMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'direction' => $m->direction,
            'channel' => $m->channel,
            'type' => $m->type,
            'body' => Demo::text($m->body),
            'payload' => $m->payload,
            'status' => $m->status,
            'sent_by' => $m->sent_by,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}
