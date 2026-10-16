<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\CampaignRecipientResource;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Modules\Broadcasting\Jobs\LaunchCampaignJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Models\UsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CampaignApiController extends WorkspaceScopedController
{
    /**
     * GET /api/v1/campaigns
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Campaign::where('workspace_id', $this->workspaceId($request))->latest('id');

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return CampaignResource::collection($query->paginate(25));
    }

    /**
     * POST /api/v1/campaigns – create a draft
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', 'in:whatsapp,instagram,messenger,telegram,sms,email'],
            'channel_account_id' => [
                Rule::requiredIf(fn () => in_array((string) $request->input('channel'), ['instagram', 'messenger', 'telegram'], true)),
                'nullable', 'integer',
                Rule::exists('channel_accounts', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $this->workspaceId($request))
                    ->where('channel', (string) $request->input('channel'))
                    ->where('status', 'active')),
            ],
            'audience_type' => ['nullable', 'string', 'in:segment,contact_list,tag,csv'],
            'audience_ref' => ['nullable', 'string'],
            'template_ref' => ['nullable', 'array'],
            'payload_json' => ['nullable', 'array'],
            'schedule_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        // Default audience_type when omitted to keep DB enum happy.
        $validated['audience_type'] = $validated['audience_type'] ?? 'segment';

        $campaign = Campaign::create(array_merge($validated, [
            'workspace_id' => $this->workspaceId($request),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]));

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/campaigns/{id} – with fresh stats
     */
    public function show(Request $request, int $id): CampaignResource|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        // Recompute stats fresh from recipients (as specified in plan section D)
        $campaign->updateTotals();
        $campaign->refresh();

        return new CampaignResource($campaign);
    }

    /**
     * POST /api/v1/campaigns/{id}/launch
     */
    public function launch(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'paused'])) {
            return response()->json(['error' => 'Campaign cannot be launched from status: '.$campaign->status.'.'], 422);
        }

        $patch = ['status' => 'queued'];
        if ($request->has('schedule_at')) {
            $value = $request->input('schedule_at');
            $patch['schedule_at'] = filled($value) ? $value : null;
        }

        $campaign->update($patch);
        $campaign->refresh();

        if (! $campaign->schedule_at || $campaign->schedule_at->isPast()) {
            LaunchCampaignJob::dispatch($campaign->id)->onQueue('broadcast');
        }

        UsageMeter::track($campaign->workspace_id, 'campaigns');

        return response()->json([
            'ok' => true,
            'status' => 'queued',
            'schedule_at' => optional($campaign->schedule_at)->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/campaigns/{id}/pause
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if ($campaign->status !== 'sending') {
            return response()->json(['error' => 'Only campaigns with status "sending" can be paused.'], 422);
        }

        $campaign->update(['status' => 'paused']);

        return response()->json(['ok' => true, 'status' => 'paused']);
    }

    /**
     * GET /api/v1/campaigns/{id}/recipients
     */
    public function recipients(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->latest('id')
            ->paginate(50);

        return CampaignRecipientResource::collection($recipients);
    }

    /**
     * PATCH /api/v1/campaigns/{id} – update a draft or paused campaign.
     */
    public function update(Request $request, int $id): CampaignResource|JsonResponse
    {
        $campaign = Campaign::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $campaign) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        if (! in_array($campaign->status, ['draft', 'paused'], true)) {
            return response()->json(['error' => 'Only draft or paused campaigns can be edited.'], 422);
        }

        $effectiveChannel = (string) $request->input('channel', $campaign->channel);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'channel' => ['sometimes', 'required', 'string', 'in:whatsapp,instagram,messenger,telegram,sms,email'],
            'channel_account_id' => [
                Rule::requiredIf(fn () => in_array($effectiveChannel, ['instagram', 'messenger', 'telegram'], true)
                    && ! $campaign->channel_account_id),
                'nullable', 'integer',
                Rule::exists('channel_accounts', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $this->workspaceId($request))
                    ->where('channel', $effectiveChannel)
                    ->where('status', 'active')),
            ],
            'audience_type' => ['sometimes', 'required', 'string', 'in:segment,contact_list,tag,csv'],
            'audience_ref' => ['nullable', 'string'],
            'template_ref' => ['nullable', 'array'],
            'payload_json' => ['nullable', 'array'],
            'schedule_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $campaign->update($validated);

        return new CampaignResource($campaign->refresh());
    }
}
