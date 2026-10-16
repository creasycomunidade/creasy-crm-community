<?php

namespace App\Modules\Inbox\Http\Controllers;

use App\Events\ConversationAssigned;
use App\Events\MessageSent;
use App\Events\TypingChanged;
use App\Http\Controllers\Controller;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Models\Currency;
use App\Models\User;
use App\Modules\AI\Services\AudioMessageTranscriber;
use App\Modules\Inbox\Models\InboxLabel;
use App\Modules\Inbox\Models\ScheduledMessage;
use App\Modules\Inbox\Services\ChannelAccountAccess;
use App\Modules\Integrations\Services\ZernioClient;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Leads\Services\TaskBoardService;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\ContactTag;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\ChannelManager;
use App\Modules\Shared\Services\OutboundMessageSignature;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use App\Modules\Whatsapp\Services\EvolutionGoConnectionException;
use App\Modules\Whatsapp\Services\EvolutionGoClient;
use App\Notifications\ConversationHandoverNotification;
use App\Services\StorageManager;
use App\Support\Demo;
use App\Support\PublicHttpUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxController extends Controller
{
    public function __construct(
        private ChannelManager $channelManager,
        private StorageManager $storageManager,
        private OutboundMessageSignature $messageSignature,
        private TaskBoardService $taskBoards,
        private ChannelAccountAccess $channelAccess,
    ) {}

    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $userId = $request->user()->id;
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), (int) $workspaceId);
        $conversations = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->whereDoesntHave('contact', fn ($contactQuery) => $contactQuery
                ->where('custom_fields->is_whatsapp_group', true))
            ->with(['contact.tags', 'contact.segments', 'channelAccount', 'lastMessage', 'labels'])
            ->when($request->folder === 'mine', fn ($q) => $q->where('assigned_user_id', $userId))
            ->when($request->folder === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($request->channel, fn ($q) => $q->whereHas('channelAccount', fn ($q) => $q->where('channel', $request->channel)))
            ->when($request->account_id, fn ($q) => $q->where('channel_account_id', $request->account_id))
            ->when(! in_array($request->folder, ['resolved', 'snoozed'], true), fn ($q) => $q->where('status', 'open'))
            ->when($request->folder === 'resolved', fn ($q) => $q->where('status', 'resolved'))
            ->when($request->folder === 'snoozed', fn ($q) => $q->where('status', 'snoozed'))
            ->when($request->label, fn ($q) => $q->whereHas('labels', fn ($q) => $q->where('inbox_labels.id', $request->label)))
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $labels = InboxLabel::where('workspace_id', $workspaceId)->orderBy('name')->get(['id', 'name', 'color']);
        $channelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->whereIn('id', $allowedAccountIds)
            ->where('status', 'active')
            ->orderBy('channel')
            ->orderBy('display_name')
            ->get(['id', 'channel', 'provider', 'display_name', 'phone_number_id']);
        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'filters' => $request->only('folder', 'channel', 'label', 'account_id'),
            'labels' => $labels,
            'channelAccounts' => $channelAccounts,
            'latestMessageId' => (int) Message::query()
                ->whereHas('conversation', fn ($query) => $query
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('channel_account_id', $allowedAccountIds))
                ->max('id'),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response|RedirectResponse
    {
        $this->authorise($request, $conversation);

        $conversation->load(['contact.tags', 'contact.segments', 'channelAccount', 'labels']);
        if ((bool) data_get($conversation->contact?->custom_fields, 'is_whatsapp_group', false)) {
            return redirect()->route('client.inbox.index');
        }
        // Provider timestamps can have a different timezone or only second-level
        // precision. The local message id is the authoritative arrival sequence,
        // and prevents a newly shared product from being inserted beside an older
        // outbound message after an Inertia reload.
        $messages = $conversation->messages()->with('conversation')->orderBy('id')->get();

        // Mark as read
        $conversation->update(['unread_count' => 0]);

        // Align UI with WhatsApp session rules (inbound-only window; see Conversation::isWhatsappWindowOpen)
        $conversation->setAttribute(
            'is_whatsapp_window_open',
            $conversation->channelAccount?->channel !== 'whatsapp'
                || $conversation->channelAccount?->provider === 'evolution_go'
                || $conversation->isWhatsappWindowOpen(),
        );

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $copilotAgent = null;
        $copilotHistory = collect();
        $userId = $request->user()->id;
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), (int) $workspaceId);
        $allLabels = InboxLabel::where('workspace_id', $workspaceId)->orderBy('name')->get(['id', 'name', 'color']);

        // Team members for agent assignment
        $teamMembers = User::forWorkspace($workspaceId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $member) => $conversation->channelAccount
                && $this->channelAccess->allows($member, $conversation->channelAccount))
            ->values();

        // WhatsApp approved templates for the template picker (used when 24h session is closed)
        $whatsappTemplates = $conversation->channelAccount?->channel === 'whatsapp'
            && ! in_array($conversation->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)
            ? WhatsappTemplate::where('workspace_id', $workspaceId)
                ->where('status', 'APPROVED')
                ->orderBy('name')
                ->get(['id', 'name', 'language', 'components'])
            : collect();

        // Pass conversation list so the left panel stays populated on the show page
        $filters = $request->only('folder', 'channel', 'label', 'account_id');
        $conversations = Conversation::where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->whereDoesntHave('contact', fn ($contactQuery) => $contactQuery
                ->where('custom_fields->is_whatsapp_group', true))
            ->with(['contact.tags', 'contact.segments', 'channelAccount', 'lastMessage', 'labels'])
            ->when(($filters['folder'] ?? null) === 'mine', fn ($q) => $q->where('assigned_user_id', $userId))
            ->when(($filters['folder'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_user_id'))
            ->when($filters['channel'] ?? null, fn ($q, $ch) => $q->whereHas('channelAccount', fn ($q) => $q->where('channel', $ch)))
            ->when($filters['account_id'] ?? null, fn ($q, $aid) => $q->where('channel_account_id', $aid))
            ->when(! in_array($filters['folder'] ?? null, ['resolved', 'snoozed'], true), fn ($q) => $q->where('status', 'open'))
            ->when(($filters['folder'] ?? null) === 'resolved', fn ($q) => $q->where('status', 'resolved'))
            ->when(($filters['folder'] ?? null) === 'snoozed', fn ($q) => $q->where('status', 'snoozed'))
            ->when($filters['label'] ?? null, fn ($q, $lid) => $q->whereHas('labels', fn ($q) => $q->where('inbox_labels.id', $lid)))
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $channelAccounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->whereIn('id', $allowedAccountIds)
            ->where('status', 'active')
            ->orderBy('channel')
            ->orderBy('display_name')
            ->get(['id', 'channel', 'provider', 'display_name', 'phone_number_id']);
        // Whether to show the Orders tab (Ecommerce module). Queried directly to
        // avoid a cross-module model import; table may not exist if module removed.
        $hasEcommerceStore = false && Schema::hasTable('ecommerce_stores')
            && DB::table('ecommerce_stores')
                ->where('workspace_id', $workspaceId)
                ->where('status', 'connected')
                ->exists();

        $kanbanPipelines = Schema::hasTable('crm_pipelines')
            ? Pipeline::query()
                ->where('workspace_id', $workspaceId)
                ->with(['stages:id,uuid,pipeline_id,name,color,position'])
                ->orderBy('position')
                ->orderBy('id')
                ->get(['id', 'uuid', 'name', 'color', 'position'])
            : collect();

        $kanbanAssignment = Schema::hasTable('crm_deals')
            ? Deal::query()
                ->where('workspace_id', $workspaceId)
                ->where('contact_id', $conversation->contact_id)
                ->with(['pipeline:id,uuid,name,color', 'stage:id,uuid,name,color'])
                ->orderByDesc('updated_at')
                ->first()
            : null;

        $taskBoards = Schema::hasTable('task_boards')
            ? collect([$this->taskBoards->defaultFor((int) $workspaceId)])
                ->each->load('columns:id,uuid,board_id,name,color,position')
            : collect();

        $contactPhone = trim((string) $conversation->contact?->phone_e164);
        $appointmentPhoneCandidates = array_values(array_unique(array_filter([
            $contactPhone,
            ltrim($contactPhone, '+'),
        ])));
        $contactAppointments = $conversation->contact_id || $appointmentPhoneCandidates !== []
            ? Appointment::query()
                ->where('workspace_id', $workspaceId)
                ->where(function ($query) use ($conversation, $appointmentPhoneCandidates) {
                    if ($conversation->contact_id) {
                        $query->where('contact_id', $conversation->contact_id);
                    }
                    if ($appointmentPhoneCandidates !== []) {
                        $conversation->contact_id
                            ? $query->orWhereIn('customer_phone', $appointmentPhoneCandidates)
                            : $query->whereIn('customer_phone', $appointmentPhoneCandidates);
                    }
                })
                ->with(['service:id,uuid,name,duration_minutes,color', 'resource:id,uuid,name'])
                ->orderByDesc('starts_at')
                ->get()
            : collect();

        return Inertia::render('Inbox/Show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'allLabels' => $allLabels,
            'conversations' => $conversations,
            'filters' => $filters,
            'teamMembers' => $teamMembers,
            'whatsappTemplates' => $whatsappTemplates,
            'channelAccounts' => $channelAccounts,
            'latestMessageId' => (int) Message::query()
                ->whereHas('conversation', fn ($query) => $query
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('channel_account_id', $allowedAccountIds))
                ->max('id'),
            'hasEcommerceStore' => $hasEcommerceStore,
            'kanbanPipelines' => $kanbanPipelines,
            'kanbanAssignment' => $kanbanAssignment,
            'taskBoards' => $taskBoards,
            'fazos' => ['connected' => false, 'links' => []],
            'availableContactTags' => ContactTag::where('workspace_id', $workspaceId)
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'availableStaticSegments' => Segment::where('workspace_id', $workspaceId)
                ->where('type', 'static')
                ->orderBy('name')
                ->get(['id', 'name']),
            'appointmentServices' => AppointmentService::where('workspace_id', $workspaceId)
                ->where('active', true)->orderBy('name')
                ->get(['uuid', 'name', 'duration_minutes']),
            'appointmentResources' => AppointmentResource::where('workspace_id', $workspaceId)
                ->where('active', true)->where('booking_enabled', true)->orderBy('name')
                ->get(['uuid', 'name', 'position']),
            'contactAppointments' => $contactAppointments,
            'scheduledMessages' => ScheduledMessage::query()
                ->where('workspace_id', $workspaceId)
                ->where('conversation_id', $conversation->id)
                ->latest('id')
                ->get(),
            'aiCopilotAgents' => $copilotAgent ? collect([$copilotAgent]) : collect(),
            'copilotHistory' => $copilotHistory,
        ]);
    }

    /**
     * Create or move the conversation contact's Kanban card without leaving the inbox.
     */
    public function updateKanban(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $data = $request->validate([
            'pipeline_uuid' => ['required', 'uuid'],
            'stage_uuid' => ['required', 'uuid'],
        ]);

        $pipeline = Pipeline::query()
            ->where('workspace_id', $workspaceId)
            ->where('uuid', $data['pipeline_uuid'])
            ->firstOrFail();
        $stage = PipelineStage::query()
            ->where('workspace_id', $workspaceId)
            ->where('pipeline_id', $pipeline->id)
            ->where('uuid', $data['stage_uuid'])
            ->firstOrFail();

        $deal = DB::transaction(function () use ($conversation, $pipeline, $stage, $workspaceId): Deal {
            $deal = Deal::query()
                ->where('workspace_id', $workspaceId)
                ->where('contact_id', $conversation->contact_id)
                ->lockForUpdate()
                ->orderByDesc('updated_at')
                ->first();

            if ($deal) {
                $deal->update([
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'position' => (int) Deal::where('stage_id', $stage->id)->max('position') + 1,
                ]);
            } else {
                $contact = $conversation->contact;
                $deal = Deal::create([
                    'workspace_id' => $workspaceId,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'contact_id' => $conversation->contact_id,
                    'title' => $contact->full_name ?: $contact->phone_e164 ?: $contact->email ?: 'Novo lead',
                    'value_cents' => 0,
                    'currency' => Currency::defaultCode() ?: 'BRL',
                    'position' => (int) Deal::where('stage_id', $stage->id)->max('position') + 1,
                ]);
            }

            return $deal;
        });

        $deal->load(['pipeline:id,uuid,name,color', 'stage:id,uuid,name,color']);


        return response()->json([
            'message' => 'Contato atualizado no Kanban.',
            'assignment' => $deal,
        ]);
    }

    public function updateContactPermissions(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $data = $request->validate([
            'opt_in_whatsapp' => ['sometimes', 'boolean'],
            'opt_in_sms' => ['sometimes', 'boolean'],
            'opt_in_email' => ['sometimes', 'boolean'],
        ]);

        abort_if($data === [], 422, 'Informe ao menos uma permissão para atualizar.');

        $conversation->contact()->where('workspace_id', $conversation->workspace_id)->update($data);
        $contact = $conversation->contact()->firstOrFail();
        Log::info('inbox.contact_permissions.updated', [
            'workspace_id' => $conversation->workspace_id,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
            'fields' => array_keys($data),
        ]);

        return response()->json([
            'message' => 'Permissões do contato atualizadas.',
            'permissions' => [
                'opt_in_whatsapp' => (bool) $contact->opt_in_whatsapp,
                'opt_in_sms' => (bool) $contact->opt_in_sms,
                'opt_in_email' => (bool) $contact->opt_in_email,
            ],
        ]);
    }

    /**
     * Assign the contact tags used by campaigns and its manually managed segments.
     * Dynamic segment memberships are calculated by their rules and are preserved.
     */
    public function updateContactSegmentation(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        $data = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('contact_tags', 'id')->where('workspace_id', $workspaceId),
            ],
            'segment_ids' => ['present', 'array'],
            'segment_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('segments', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)->where('type', 'static')
                ),
            ],
        ]);

        $contact = $conversation->contact()->where('workspace_id', $workspaceId)->firstOrFail();
        $oldStaticIds = $contact->segments()->where('type', 'static')->pluck('segments.id')->map(fn ($id) => (int) $id)->all();
        $dynamicIds = $contact->segments()->where('type', 'dynamic')->pluck('segments.id')->map(fn ($id) => (int) $id)->all();
        $staticIds = array_values(array_unique(array_map('intval', $data['segment_ids'])));
        $tagIds = array_values(array_unique(array_map('intval', $data['tag_ids'])));

        DB::transaction(function () use ($contact, $tagIds, $staticIds, $dynamicIds, $oldStaticIds): void {
            $contact->tags()->sync($tagIds);
            $contact->segments()->sync(array_values(array_unique(array_merge($dynamicIds, $staticIds))));

            Segment::whereIn('id', array_values(array_unique(array_merge($oldStaticIds, $staticIds))))
                ->each(fn (Segment $segment) => $segment->update([
                    'contact_count' => $segment->contacts()->count(),
                ]));
        });

        Log::info('inbox.contact_segmentation.updated', [
            'workspace_id' => $workspaceId,
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
            'tag_count' => count($tagIds),
            'static_segment_count' => count($staticIds),
        ]);

        return response()->json([
            'message' => 'Segmentos e etiquetas do contato atualizados.',
            'tag_ids' => $tagIds,
            'segment_ids' => $staticIds,
        ]);
    }

    /** Create a campaign/contact tag without leaving the conversation. */
    public function storeContactTag(Request $request): JsonResponse
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('contact_tags', 'name')->where('workspace_id', $workspaceId),
            ],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag = ContactTag::create([
            'workspace_id' => $workspaceId,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? '#7c3aed',
        ]);

        Log::info('inbox.contact_tag.created', [
            'workspace_id' => $workspaceId,
            'tag_id' => $tag->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Etiqueta criada.',
            'tag' => $tag->only(['id', 'name', 'color']),
        ], 201);
    }

    /**
     * Lightweight realtime fallback for shared hosting.
     *
     * Pusher/Reverb remains the fastest path when configured, but this endpoint
     * keeps the inbox live even when the host cannot run a websocket server and
     * no external broadcaster credentials are available.
     */
    public function updates(Request $request, ?Conversation $conversation = null): JsonResponse
    {
        if ($conversation) {
            $this->authorise($request, $conversation);
        }

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'folder' => ['nullable', 'in:mine,unassigned,resolved,snoozed'],
            'channel' => ['nullable', 'in:whatsapp,instagram,messenger,telegram,sms,email,webchat'],
            'account_id' => ['nullable', 'integer', 'min:1'],
            'label' => ['nullable', 'integer', 'min:1'],
        ]);

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $userId = $request->user()->id;
        $afterId = (int) ($validated['after_id'] ?? 0);
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), (int) $workspaceId);

        $messages = Message::query()
            ->where('id', '>', $afterId)
            ->whereHas('conversation', fn ($query) => $query
                ->where('workspace_id', $workspaceId)
                ->whereIn('channel_account_id', $allowedAccountIds)
                ->whereDoesntHave('contact', fn ($contactQuery) => $contactQuery
                    ->where('custom_fields->is_whatsapp_group', true)))
            ->with(['conversation.contact', 'conversation.channelAccount'])
            ->orderBy('id')
            ->limit(100)
            ->get();

        // Messages received while this thread is visibly open are already read.
        if ($conversation && $messages->contains(fn (Message $message) => (
            (int) $message->conversation_id === (int) $conversation->id
            && $message->direction === 'in'
        ))) {
            $conversation->update(['unread_count' => 0]);
        }

        $folder = $validated['folder'] ?? null;
        $channel = $validated['channel'] ?? null;
        $accountId = $validated['account_id'] ?? null;
        $labelId = $validated['label'] ?? null;
        $conversations = Conversation::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('channel_account_id', $allowedAccountIds)
            ->whereDoesntHave('contact', fn ($contactQuery) => $contactQuery
                ->where('custom_fields->is_whatsapp_group', true))
            ->with(['contact', 'channelAccount', 'lastMessage', 'labels'])
            ->when($folder === 'mine', fn ($query) => $query->where('assigned_user_id', $userId))
            ->when($folder === 'unassigned', fn ($query) => $query->whereNull('assigned_user_id'))
            ->when($channel, fn ($query, $value) => $query->whereHas(
                'channelAccount',
                fn ($accountQuery) => $accountQuery->where('channel', $value)
            ))
            ->when($accountId, fn ($query, $value) => $query->where('channel_account_id', $value))
            ->when(! in_array($folder, ['resolved', 'snoozed'], true), fn ($query) => $query->where('status', 'open'))
            ->when($folder === 'resolved', fn ($query) => $query->where('status', 'resolved'))
            ->when($folder === 'snoozed', fn ($query) => $query->where('status', 'snoozed'))
            ->when($labelId, fn ($query, $value) => $query->whereHas(
                'labels',
                fn ($labelQuery) => $labelQuery->where('inbox_labels.id', $value)
            ))
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get();

        return response()->json([
            'messages' => $messages->map(function (Message $message): array {
                $contact = $message->conversation?->contact;

                return array_merge($message->toArray(), [
                    'conversation_uuid' => $message->conversation?->uuid,
                    'channel_account_id' => $message->conversation?->channel_account_id,
                    'contact_name' => $contact?->full_name ?: $contact?->phone_e164,
                ]);
            })->values(),
            'conversations' => $conversations,
            'latest_id' => max(
                $afterId,
                (int) ($messages->max('id') ?? 0)
            ),
        ]);
    }

    /**
     * Global new-message cursor used outside the inbox.
     *
     * The first request intentionally returns only the current cursor, preventing
     * a newly opened browser tab from alerting for historical messages.
     */
    public function notificationUpdates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), (int) $workspaceId);
        $baseQuery = Message::query()
            ->where('direction', 'in')
            ->whereHas('conversation', fn ($query) => $query
                ->where('workspace_id', $workspaceId)
                ->whereIn('channel_account_id', $allowedAccountIds));

        $latestId = (int) ((clone $baseQuery)->max('id') ?? 0);

        if (! array_key_exists('after_id', $validated)) {
            return response()->json([
                'messages' => [],
                'latest_id' => $latestId,
            ]);
        }

        $afterId = (int) $validated['after_id'];
        $messages = $baseQuery
            ->where('id', '>', $afterId)
            ->with(['conversation.contact', 'conversation.channelAccount'])
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'messages' => $messages->map(function (Message $message): array {
                $conversation = $message->conversation;
                $contact = $conversation?->contact;

                return [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'conversation_uuid' => $conversation?->uuid,
                    'channel_account_id' => $conversation?->channel_account_id,
                    'direction' => $message->direction,
                    'channel' => $message->channel ?: $conversation?->channelAccount?->channel,
                    'body' => $message->body,
                    'contact_name' => $contact?->full_name ?: $contact?->phone_e164,
                    'created_at' => $message->created_at?->toIso8601String(),
                ];
            })->values(),
            'latest_id' => max($afterId, $latestId, (int) ($messages->max('id') ?? 0)),
        ]);
    }

    public function reply(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4096'],
            'type' => ['nullable', 'in:text,template,image,document,video,audio'],
            'payload' => ['nullable', 'array'],
            // Allow-list of messaging media types (no HTML/SVG/executables).
            'attachment' => [
                'nullable', 'file', 'max:20480',
                'mimes:jpg,jpeg,png,webp,mp4,3gp,mov,mp3,aac,m4a,amr,ogg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
            ],
        ]);

        $msgType = $validated['type'] ?? 'text';
        $msgPayload = $validated['payload'] ?? null;

        // Handle direct file attachment (image / document sent from compose bar)
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';

            // Derive type from MIME if not explicitly set
            if ($msgType === 'text') {
                $msgType = str_starts_with($mimeType, 'image/') ? 'image'
                    : (str_starts_with($mimeType, 'video/') ? 'video' : 'document');
            }

            $storedPath = $this->storageManager->prefixedPath('message-media/'.$file->hashName());
            $this->storageManager->disk()->putFileAs(dirname($storedPath), $file, basename($storedPath));
            $previewUrl = $this->storageManager->disk()->url($storedPath);

            $msgPayload = array_merge($msgPayload ?? [], [
                'preview_url' => $previewUrl,
                'caption' => $validated['body'] ?? null,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
            ]);

            // WhatsApp requires its own uploaded media ID. Telegram consumes
            // the public preview URL directly through the Bot API.
            if ($conversation->channelAccount?->channel === 'whatsapp'
                && ! in_array($conversation->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)) {
                $client = CloudApiClient::forWorkspace($conversation->workspace_id);
                if (! $client) {
                    return response()->json(['error' => 'Nenhuma conta ativa do WhatsApp foi encontrada.'], 422);
                }
                $msgPayload['media_id'] = $client->uploadMedia($file->getRealPath(), $mimeType);
            }

            // For image/document the 'body' shown in the chat is the caption or filename
            $validated['body'] = $validated['body'] ?? $file->getClientOriginalName();
        }

        // Require body for plain text messages
        if ($msgType === 'text' && empty($validated['body'])) {
            return back()->withErrors(['body' => 'Digite uma mensagem antes de enviar.']);
        }

        // Enforce 24h window for WhatsApp — templates bypass the window restriction
        if ($conversation->channelAccount?->channel === 'whatsapp'
            && ! in_array($conversation->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)
            && ! $conversation->isWhatsappWindowOpen()
            && $msgType !== 'template') {
            return back()->with('error', __('WhatsApp 24-hour session is closed. Use an approved template to re-engage this contact.'));
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

        // Send via the channel driver
        $sendError = null;
        $sendErrorCode = null;
        try {
            $driver = $this->channelManager->driver($channel);
            $messageId = $driver->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            $sendErrorCode = $e instanceof EvolutionGoConnectionException
                ? 'whatsapp_disconnected'
                : null;
            Log::error('Inbox reply send failed', [
                'conversation_id' => $conversation->id,
                'channel' => $channel,
                'error' => $sendError,
            ]);
            $message->update(['status' => 'failed', 'error_json' => ['message' => $sendError]]);
        }

        $conversation->update(['last_message_at' => now()]);

        // SLA: set first_response_at on first outbound after inbound
        if ($conversation->last_inbound_at && ! $conversation->first_response_at) {
            $conversation->update(['first_response_at' => now()]);
        }

        // Re-load the relation so the broadcast event can resolve workspace_id
        $message->load('conversation');

        MessageSent::dispatch($message);

        if ($request->wantsJson()) {
            // Always return 200 so the UI can display the queued/failed bubble
            // immediately; the message status conveys delivery state.
            return response()->json([
                'message' => $message,
                'error' => $sendError,
                'error_code' => $sendErrorCode,
            ]);
        }

        if ($sendError) {
            return back()->with('error', __('Message saved but failed to send: :error', ['error' => $sendError]));
        }

        return back()->with('success', __('Message sent.'));
    }

    /**
     * Share a connected-store product into the conversation as a rich image card
     * (product photo + caption) — WhatsApp sends one captioned image, Messenger /
     * Instagram send the photo as an attachment followed by the caption. Products
     * without a photo fall back to a plain text card. The product is looked up via
     * the query builder rather than the Ecommerce model so the Inbox stays
     * decoupled from that module (mirrors the hasEcommerceStore probe in show()).
     */
    public function shareProduct(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $validated = $request->validate(['product_id' => ['required', 'integer']]);
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        // Join the store for its currency (external_meta) and domain (URL building),
        // still without importing the Ecommerce model so the Inbox stays decoupled.
        $product = Schema::hasTable('ecommerce_products')
            ? DB::table('ecommerce_products as p')
                ->leftJoin('ecommerce_stores as s', 's.id', '=', 'p.store_id')
                ->where('p.workspace_id', $workspaceId)
                ->where('p.id', $validated['product_id'])
                ->select('p.*', 's.external_meta as store_meta', 's.domain as store_domain')
                ->first()
            : null;

        abort_unless($product, 404, __('Product not found.'));

        $channel = $conversation->channelAccount?->channel ?? 'whatsapp';

        // Free-form messages need an open 24h session on WhatsApp.
        if ($channel === 'whatsapp'
            && ! in_array($conversation->channelAccount?->provider, ['evolution_go', 'waha', 'evolution_api'], true)
            && ! $conversation->isWhatsappWindowOpen()) {
            return response()->json([
                'error' => __('WhatsApp 24-hour session is closed. Use an approved template to re-engage this contact.'),
            ], 422);
        }

        $storeMeta = json_decode($product->store_meta ?? '', true) ?: [];
        $currency = (string) ($storeMeta['currency'] ?? Currency::defaultCode() ?? 'BRL');
        $url = $this->productShareUrl($product);

        // WhatsApp renders bold (*…*); other channels show it literally, so only bold there.
        $caption = $this->formatProductMessage($product, currency: $currency, url: $url, bold: $channel === 'whatsapp');
        $image = $product->image_url ?: null;

        // Send the product photo as a real image on every channel (drivers handle the
        // per-channel rendering); fall back to text only when there is no photo.
        $useImage = (bool) $image;
        $productPayload = $useImage ? [
            'link' => $image,
            'preview_url' => $image,
            'caption' => $caption,
            'content_kind' => 'product_share',
            'product_id' => (int) $product->id,
        ] : [
            'content_kind' => 'product_share',
            'product_id' => (int) $product->id,
        ];
        $signed = $this->messageSignature->apply(
            $request->user(),
            $channel,
            $caption,
            $productPayload,
            $useImage ? 'image' : 'text',
        );

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'channel' => $channel,
            'type' => $useImage ? 'image' : 'text',
            'body' => $signed['body'],
            'payload' => $signed['payload'],
            'status' => 'queued',
            'sent_by' => 'human',
            'user_id' => $request->user()->id,
            'sent_at' => now(),
        ]);

        $sendError = null;
        try {
            $messageId = $this->channelManager->driver($channel)->send($message);
            $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error('Inbox shareProduct send failed', [
                'conversation_id' => $conversation->id,
                'channel' => $channel,
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

        return response()->json(['message' => $message, 'error' => $sendError]);
    }

    /**
     * Format a product row into a message caption. The photo is sent as an image,
     * so the caption never includes the raw image URL.
     */
    private function formatProductMessage(object $product, string $currency = '', ?string $url = null, bool $bold = false): string
    {
        $name = trim((string) $product->name);
        $lines = [$bold ? '🛍️ *'.$name.'*' : '🛍️ '.$name];

        if ($product->price !== null && $product->price !== '') {
            $isBrazilianPortuguese = str_starts_with(app()->getLocale(), 'pt');
            $price = number_format(
                (float) $product->price,
                2,
                $isBrazilianPortuguese ? ',' : '.',
                $isBrazilianPortuguese ? '.' : ',',
            );
            $lines[] = __('Price').': '.$this->currencyPrefix($currency).$price;
        }
        if (! empty($product->sku)) {
            $lines[] = __('Product code').': '.$product->sku;
        }
        if (! empty($url)) {
            $lines[] = $url;
        }

        return implode("\n", $lines);
    }

    /**
     * Render a currency as a symbol when known (e.g. "USD" → "$"), otherwise the
     * ISO code with a trailing space, or "" when no currency is set.
     */
    private function currencyPrefix(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            return '';
        }

        $symbols = [
            'USD' => 'US$ ', 'EUR' => '€ ', 'GBP' => '£ ', 'JPY' => '¥ ', 'INR' => '₹ ',
            'AUD' => 'A$ ', 'CAD' => 'C$ ', 'NZD' => 'NZ$ ', 'BRL' => 'R$ ',
        ];

        return $symbols[$currency] ?? $currency.' ';
    }

    /**
     * Best-effort public storefront URL for a shared product, derived from the raw
     * platform payload. Shopify uses its published URL (or domain + handle); Woo
     * uses the product permalink. Returns null when none can be built.
     */
    private function productShareUrl(object $product): ?string
    {
        $raw = json_decode($product->raw ?? '', true);
        if (! is_array($raw)) {
            return null;
        }
        $domain = $product->store_domain ?? null;

        return match ($product->platform) {
            'shopify' => $raw['online_store_url']
                ?? (! empty($raw['handle']) && $domain ? "https://{$domain}/products/{$raw['handle']}" : null),
            'woocommerce' => ! empty($raw['permalink']) && filter_var($raw['permalink'], FILTER_VALIDATE_URL)
                ? $raw['permalink']
                : null,
            default => null,
        };
    }

    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['user_id' => ['nullable', 'integer']]);

        $assignedTo = null;
        if ($request->user_id) {
            $assignedTo = User::forWorkspace($conversation->workspace_id)
                ->find($request->user_id);
            abort_unless($assignedTo && $conversation->channelAccount
                && $this->channelAccess->allows($assignedTo, $conversation->channelAccount), 422);
        }

        $conversation->update(['assigned_user_id' => $request->user_id]);
        ConversationAssigned::dispatch($conversation, $assignedTo);

        return back()->with('success', __('Conversation assigned.'));
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['is_typing' => ['required', 'boolean']]);

        broadcast(new TypingChanged($conversation, $request->user(), (bool) $request->is_typing))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function updateStatus(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorise($request, $conversation);
        $request->validate(['status' => ['required', 'in:open,pending,resolved,snoozed']]);

        $updates = ['status' => $request->status];
        if ($request->status === 'resolved' && ! $conversation->resolved_at) {
            $updates['resolved_at'] = now();
        }
        $conversation->update($updates);

        return back()->with('success', 'Estado atualizado.');
    }

    public function handover(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);
        $mode = $request->input('mode', 'human'); // 'human' or 'bot'

        $updates = ['assigned_to' => $mode];
        if ($mode === 'human' && ! $conversation->handover_at) {
            $updates['handover_at'] = now();
        }
        $conversation->update($updates);

        if ($mode === 'human') {
            $members = User::forWorkspace($conversation->workspace_id)->get()
                ->filter(fn (User $member) => $conversation->channelAccount
                    && $this->channelAccess->allows($member, $conversation->channelAccount));
            foreach ($members as $member) {
                $member->notify(new ConversationHandoverNotification($conversation, 'manual'));
            }
        }

        return response()->json(['ok' => true, 'assigned_to' => $mode]);
    }

    /**
     * Proxy / lazy-download inbound WhatsApp media.
     * Checks payload.preview_url first, then downloads from WhatsApp Graph API,
     * caches to local storage, updates the message, and redirects.
     */
    public function serveMedia(Request $request, Conversation $conversation, Message $message): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorise($request, $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);

        $payload = $message->payload ?? [];
        $isEvolutionGo = $conversation->channelAccount?->provider === 'evolution_go';

        $privateMediaPath = (string) ($payload['private_media_path'] ?? '');
        if (str_starts_with($privateMediaPath, 'message-media/')
            && Storage::disk('local')->exists($privateMediaPath)) {
            return $this->storedMediaResponse(
                $request,
                Storage::disk('local'),
                $privateMediaPath,
                (string) ($payload['mime_type'] ?? '')
            );
        }

        if (in_array($conversation->channelAccount?->provider, ['waha', 'evolution_api'], true)
            && is_array($payload['gateway_media'] ?? null)) {
            try {
                $download = \App\Modules\Whatsapp\Services\WhatsappGatewayClient::forAccount($conversation->channelAccount)
                    ->downloadMedia($payload['gateway_media']);
                $mime = $download['mime_type'];
                $path = 'message-media/gateway/'.$message->id.'.'.$this->mediaExtension($mime);
                $disk = Storage::disk('local');
                if (! $disk->put($path, $download['bytes'])) {
                    throw new \RuntimeException('Não foi possível armazenar a mídia.');
                }
                $message->update(['payload' => array_merge($payload, ['private_media_path' => $path, 'mime_type' => $mime])]);
                return $this->storedMediaResponse($request, $disk, $path, $mime);
            } catch (\Throwable $e) {
                Log::warning('Gateway media download failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
                abort(502, 'Não foi possível obter a mídia neste momento. Tente novamente.');
            }
        }

        // Already cached or hosted by the configured storage provider.
        if (! empty($payload['preview_url'])) {
            $disk = $this->storageManager->disk();
            $previewUrl = (string) $payload['preview_url'];
            $parsedPath = parse_url($previewUrl, PHP_URL_PATH);
            if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
                $storagePath = ltrim(substr($parsedPath, strlen('/storage/')), '/');
                if ($disk->exists($storagePath)) {
                    return $this->storedMediaResponse(
                        $request,
                        $disk,
                        $storagePath,
                        (string) ($payload['mime_type'] ?? '')
                    );
                }
            } elseif (str_starts_with($previewUrl, 'http://') || str_starts_with($previewUrl, 'https://')) {
                // Evolution's media URL can be temporary or point at a local
                // storage object whose first browser request was cached as a
                // failure. Proxy it through this authenticated endpoint and,
                // when needed, recover it again from the encrypted payload.
                if (! $isEvolutionGo) {
                    return redirect($previewUrl);
                }
            }

            // File missing — clear stale preview_url and fall through to re-download
            $payload = array_merge($payload, ['preview_url' => null]);
            $message->update(['payload' => $payload]);
        }

        if ($isEvolutionGo) {
            $rawMessage = data_get($payload, 'evolution_go.Message')
                ?? data_get($payload, 'evolution_go.message');
            if (! is_array($rawMessage)) {
                abort(404, 'Nenhuma mídia disponível.');
            }

            try {
                $download = EvolutionGoClient::forAccount($conversation->channelAccount)
                    ->downloadMedia($rawMessage);
                $mimeType = $download['mime_type'];
                $ext = $this->mediaExtension($mimeType);
                $filename = $this->storageManager->prefixedPath("message-media/{$message->id}.{$ext}");
                $disk = $this->storageManager->disk();
                $disk->put($filename, $download['bytes'], ['visibility' => 'public']);
                $previewUrl = $disk->url($filename);

                $message->update(['payload' => array_merge($payload, [
                    'preview_url' => $previewUrl,
                    'mime_type' => $mimeType,
                ])]);

                return $this->storedMediaResponse($request, $disk, $filename, $mimeType);
            } catch (\Throwable $e) {
                Log::warning('Evolution Go historical media download failed', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
                abort(502, 'Não foi possível obter a mídia: '.$e->getMessage());
            }
        }

        if ($conversation->channelAccount?->provider === 'zernio') {
            try {
                return $this->serveZernioAttachment($request, $conversation, $message, $payload);
            } catch (\Throwable $e) {
                Log::warning('Zernio attachment download failed', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
                abort(502, 'Não foi possível obter o áudio neste momento. Tente novamente.');
            }
        }

        // Resolve media ID from raw WhatsApp webhook payload
        $type = $message->type ?? 'image';
        $mediaId = $payload[$type]['id'] ?? $payload['media_id'] ?? null;

        if (! $mediaId) {
            abort(404, 'Nenhuma mídia disponível.');
        }

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $client = CloudApiClient::forWorkspace($workspaceId);

        if (! $client) {
            abort(503, 'Conta do WhatsApp não configurada.');
        }

        try {
            ['url' => $downloadUrl, 'mime_type' => $mimeType] = $client->getMediaUrl($mediaId);
            $bytes = $client->downloadMedia($downloadUrl);
            $ext = explode('/', $mimeType)[1] ?? 'bin';
            $ext = str_replace(['jpeg'], ['jpg'], $ext);
            $filename = "message-media/{$message->id}.{$ext}";

            $filename = $this->storageManager->prefixedPath($filename);
            $this->storageManager->disk()->put($filename, $bytes);
            $previewUrl = $this->storageManager->disk()->url($filename);

            // Cache for next request
            $message->update(['payload' => array_merge($payload, ['preview_url' => $previewUrl, 'mime_type' => $mimeType])]);

            if ($this->storageManager->disk()->exists($filename)) {
                return $this->storedMediaResponse(
                    $request,
                    $this->storageManager->disk(),
                    $filename,
                    $mimeType
                );
            }

            return redirect($previewUrl);
        } catch (\Throwable $e) {
            abort(502, 'Não foi possível obter a mídia: '.$e->getMessage());
        }
    }

    public function transcribeAudio(
        Request $request,
        Conversation $conversation,
        Message $message,
        AudioMessageTranscriber $transcriber,
    ): JsonResponse {
        $this->authorise($request, $conversation);
        abort_unless((int) $message->conversation_id === (int) $conversation->id, 404);

        if ($message->type !== 'audio') {
            return response()->json(['message' => 'Esta mensagem não contém um áudio.'], 422);
        }

        $transcription = $transcriber->transcribe($message);
        if ($transcription === null || trim($transcription) === '') {
            return response()->json([
                'message' => 'Não foi possível transcrever este áudio. Verifique o arquivo e a configuração do provedor de IA e tente novamente.',
            ], 422);
        }

        return response()->json([
            'message_id' => $message->id,
            'transcription' => $transcription,
            'status' => 'completed',
        ]);
    }

    /**
     * Resolve an expiring provider attachment and cache it on Laravel's private
     * local disk. The browser can only read it through the authorised media route.
     *
     * @param  array<string, mixed>  $payload
     */
    private function serveZernioAttachment(
        Request $request,
        Conversation $conversation,
        Message $message,
        array $payload,
    ): \Symfony\Component\HttpFoundation\Response {
        $attachment = (array) data_get($payload, 'attachments.0', []);
        abort_if($attachment === [], 404, 'Nenhum anexo de áudio foi encontrado.');

        $messageData = (array) ($payload['zernio'] ?? []);
        $url = (string) ($attachment['url'] ?? '');
        $refreshUrl = (string) ($attachment['refreshUrl'] ?? '');
        $conversationId = (string) ($messageData['conversationId'] ?? $conversation->external_thread_id ?? '');
        $messageId = (string) ($messageData['id'] ?? $messageData['_id'] ?? $messageData['messageId'] ?? $messageData['platformMessageId'] ?? '');
        $accountId = (string) ($messageData['accountId'] ?? $conversation->channelAccount?->external_account_id ?? '');

        if ($refreshUrl !== '' || $url === '') {
            abort_if($conversationId === '' || $messageId === '' || $accountId === '', 404, 'Não foi possível identificar o anexo de áudio.');
            $resolved = app(ZernioClient::class)->attachmentUrl($conversationId, $messageId, 0, $accountId);
            $url = (string) data_get($resolved, 'url', data_get($resolved, 'data.url', ''));
        }
        abort_if($url === '', 404, 'O serviço não devolveu o endereço do áudio.');

        $response = Http::withOptions(PublicHttpUrl::requestOptions($url))
            ->timeout(90)
            ->connectTimeout(8)
            ->retry(1, 250, throw: false)
            ->get($url);
        if (! $response->successful()
            && $refreshUrl === ''
            && $conversationId !== ''
            && $messageId !== ''
            && $accountId !== '') {
            $resolved = app(ZernioClient::class)->attachmentUrl($conversationId, $messageId, 0, $accountId);
            $url = (string) data_get($resolved, 'url', data_get($resolved, 'data.url', ''));
            abort_if($url === '', 404, 'O serviço não devolveu o endereço atualizado do áudio.');
            $response = Http::withOptions(PublicHttpUrl::requestOptions($url))
                ->timeout(90)
                ->connectTimeout(8)
                ->retry(1, 250, throw: false)
                ->get($url);
        }
        abort_unless($response->successful(), 502, 'O serviço não conseguiu baixar o áudio.');

        $bytes = $response->body();
        abort_if($bytes === '' || strlen($bytes) > 30 * 1024 * 1024, 422, 'O arquivo de áudio está vazio ou excede 30 MB.');

        $responseMime = strtolower(trim(explode(';', (string) $response->header('Content-Type'), 2)[0]));
        $declaredMime = strtolower(trim(explode(';', (string) (
            $attachment['mimeType'] ?? $attachment['contentType'] ?? 'audio/ogg'
        ), 2)[0]));
        $mimeType = in_array($responseMime, ['', 'application/octet-stream', 'binary/octet-stream'], true)
            ? $declaredMime
            : $responseMime;
        $extension = $this->mediaExtension($mimeType);
        $path = "message-media/{$message->id}.{$extension}";
        $disk = Storage::disk('local');
        $disk->put($path, $bytes);

        $message->update(['payload' => array_merge($payload, [
            'private_media_path' => $path,
            'mime_type' => $mimeType,
        ])]);

        return $this->storedMediaResponse($request, $disk, $path, $mimeType);
    }

    private function mediaExtension(string $mimeType): string
    {
        $mimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/aac' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    /**
     * Stream cached media with byte-range support. Audio and video elements use
     * Range requests to discover duration and seek; a plain streamed response
     * leaves some browsers stuck at 0:00 / 0:00.
     */
    private function storedMediaResponse(Request $request, mixed $disk, string $path, string $fallbackMime = ''): StreamedResponse
    {
        $size = (int) $disk->size($path);
        abort_if($size <= 0, 404, 'Arquivo de mídia vazio ou indisponível.');

        $mimeType = trim($fallbackMime);
        if ($mimeType === '') {
            try {
                $mimeType = (string) $disk->mimeType($path);
            } catch (\Throwable) {
                $mimeType = 'application/octet-stream';
            }
        }

        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $request->header('Range');

        if (is_string($range) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches)) {
            if ($matches[1] === '' && $matches[2] !== '') {
                $suffixLength = min($size, max(1, (int) $matches[2]));
                $start = $size - $suffixLength;
            } else {
                $start = (int) ($matches[1] !== '' ? $matches[1] : 0);
                $end = $matches[2] !== '' ? min((int) $matches[2], $size - 1) : $size - 1;
            }

            if ($start < 0 || $start >= $size || $end < $start) {
                abort(416, 'Intervalo de mídia inválido.', ['Content-Range' => 'bytes */'.$size]);
            }
            $status = 206;
        }

        $length = $end - $start + 1;
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($path)).'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($disk, $path, $start, $length): void {
            $stream = $disk->readStream($path);
            abort_unless(is_resource($stream), 404, 'Não foi possível abrir a mídia.');

            $toSkip = $start;
            $metadata = stream_get_meta_data($stream);
            if ($toSkip > 0 && ($metadata['seekable'] ?? false)) {
                fseek($stream, $toSkip);
                $toSkip = 0;
            }
            while ($toSkip > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(8192, $toSkip));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $toSkip -= strlen($chunk);
            }

            $remaining = $length;
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(8192, $remaining));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
            }
            fclose($stream);
        }, $status, $headers);
    }

    /** Upload media for the active channel and return its sendable reference. */
    public function uploadMedia(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorise($request, $conversation);

        $request->validate(['file' => ['required', 'file', 'max:16384']]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        try {
            $path = $this->storageManager->prefixedPath('template-media/'.$file->hashName());
            $this->storageManager->disk()->putFileAs(dirname($path), $file, basename($path));
            $previewUrl = $this->storageManager->disk()->url($path);

            if ($conversation->channelAccount?->channel === 'telegram'
                || $conversation->channelAccount?->provider === 'evolution_go') {
                return response()->json([
                    'media_id' => $previewUrl,
                    'mime_type' => $mimeType,
                    'preview_url' => $previewUrl,
                ]);
            }

            $client = CloudApiClient::forWorkspace($workspaceId);
            if (! $client) {
                return response()->json(['error' => 'Nenhuma conta ativa do WhatsApp foi encontrada.'], 422);
            }
            $mediaId = $client->uploadMedia($file->getRealPath(), $mimeType);

            return response()->json(['media_id' => $mediaId, 'mime_type' => $mimeType, 'preview_url' => $previewUrl]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /** Return approved WhatsApp templates for the workspace (JSON) */
    public function templates(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $templates = WhatsappTemplate::where('workspace_id', $workspaceId)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get(['id', 'name', 'language', 'category', 'components']);

        return response()->json($templates);
    }

    /** Search contacts for the new-conversation modal (JSON) */
    public function contactSearch(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $q = $request->input('q', '');

        $contacts = Contact::where('workspace_id', $workspaceId)
            ->with('tags')
            ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('phone_e164', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->latest()
            ->limit(30)
            ->get(['id', 'uuid', 'first_name', 'last_name', 'phone_e164', 'email', 'country', 'avatar']);

        return response()->json($contacts->map(fn ($c) => array_merge($c->toArray(), [
            'avatar_url' => Demo::active() ? null : $c->avatar_url,
        ])));
    }

    /** Return active channel accounts for the workspace (JSON) */
    public function channelAccounts(Request $request): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $allowedAccountIds = $this->channelAccess->allowedAccountIds($request->user(), (int) $workspaceId);

        $accounts = ChannelAccount::where('workspace_id', $workspaceId)
            ->whereIn('id', $allowedAccountIds)
            ->where('status', 'active')
            ->get(['id', 'channel', 'provider', 'display_name', 'phone_number_id']);

        return response()->json($accounts);
    }

    /** Find or create a conversation, then redirect to it */
    public function startConversation(Request $request): RedirectResponse|JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'channel_account_id' => ['required', 'integer'],
            'body' => ['nullable', 'string', 'max:4096'],
        ]);

        $contact = Contact::where('workspace_id', $workspaceId)->findOrFail($validated['contact_id']);
        $channelAccount = ChannelAccount::where('workspace_id', $workspaceId)->findOrFail($validated['channel_account_id']);
        abort_unless($this->channelAccess->allows($request->user(), $channelAccount), 403);

        // Reuse the most recent open conversation for this contact + channel, or create a new one
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $channelAccount->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'contact_id' => $contact->id,
                'channel_account_id' => $channelAccount->id,
                'status' => 'open',
                'assigned_to' => 'human',
                'assigned_user_id' => $request->user()->id,
                'last_message_at' => now(),
            ]);
        }

        // Send the opening message if provided
        if (! empty($validated['body'])) {
            $signed = $this->messageSignature->apply(
                $request->user(),
                $channelAccount->channel,
                $validated['body'],
                null,
                'text',
            );
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'out',
                'channel' => $channelAccount->channel,
                'type' => 'text',
                'body' => $signed['body'],
                'payload' => $signed['payload'],
                'status' => 'queued',
                'sent_by' => 'human',
                'user_id' => $request->user()->id,
                'sent_at' => now(),
            ]);

            try {
                $driver = $this->channelManager->driver($channelAccount->channel);
                $messageId = $driver->send($message);
                $message->update(['status' => 'sent', 'provider_message_id' => $messageId]);
            } catch (\Throwable $e) {
                Log::error('startConversation send failed', [
                    'conversation_id' => $conversation->id,
                    'channel' => $channelAccount->channel,
                    'error' => $e->getMessage(),
                ]);
                $message->update(['status' => 'failed', 'error_json' => ['message' => $e->getMessage()]]);
            }

            $conversation->update(['last_message_at' => now()]);
            $message->load('conversation');
            MessageSent::dispatch($message);
        }

        return redirect()->route('client.inbox.show', $conversation);
    }

    private function authorise(Request $request, Conversation $conversation): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $conversation->workspace_id === (int) $workspaceId, 403);
        $conversation->loadMissing('channelAccount');
        abort_unless($conversation->channelAccount && $this->channelAccess->allows($request->user(), $conversation->channelAccount), 403);
    }

}
