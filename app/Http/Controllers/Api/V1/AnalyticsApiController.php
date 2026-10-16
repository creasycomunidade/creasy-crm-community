<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Broadcasting\Models\Campaign;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsApiController extends WorkspaceScopedController
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /**
     * GET /api/v1/analytics/messages
     * Message volume by channel for the workspace, date-range filterable.
     */
    public function messages(Request $request): JsonResponse
    {
        [$wsId, $from, $to] = $this->rangeParams($request);

        return response()->json([
            'data' => $this->analytics->messageVolumeByChannel($wsId, $from, $to),
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /**
     * GET /api/v1/analytics/ai-usage
     */
    public function aiUsage(Request $request): JsonResponse
    {
        [$wsId, $from, $to] = $this->rangeParams($request);

        return response()->json([
            'data' => [
                'kpis' => $this->analytics->aiKpis($wsId, $from, $to),
                'tokens_by_day' => $this->analytics->aiUsageByDay($wsId, $from, $to),
                'tokens_by_model' => $this->analytics->aiUsageByModel($wsId, $from, $to),
            ],
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    /**
     * GET /api/v1/analytics/campaign/{campaign}/funnel
     */
    public function campaignFunnel(Request $request, int $campaignId): JsonResponse
    {
        $wsId = $this->workspaceId($request);
        $campaign = Campaign::findOrFail($campaignId);
        abort_if($campaign->workspace_id !== $wsId, 403, 'Campaign not found in this workspace.');

        return response()->json([
            'data' => [
                'funnel' => $this->analytics->campaignFunnel($campaign->id),
                'delivery_over_time' => $this->analytics->campaignDeliveryOverTime($campaign->id),
                'failed_reasons' => $this->analytics->campaignFailedReasons($campaign->id),
            ],
        ]);
    }

    /**
     * GET /api/v1/analytics/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        [$wsId, $from, $to] = $this->rangeParams($request);

        return response()->json([
            'data' => [
                'over_time' => $this->analytics->conversationsResolvedOverTime($wsId, $from, $to),
                'channel_mix' => $this->analytics->conversationChannelMix($wsId, $from, $to),
            ],
            'meta' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────────

    private function rangeParams(Request $request): array
    {
        $wsId = $this->workspaceId($request);
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();
        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$wsId, $from, $to];
    }
}
