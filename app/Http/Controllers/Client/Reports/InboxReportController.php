<?php

namespace App\Http\Controllers\Client\Reports;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxReportController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $wsId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_if(! $wsId, 403);

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return Inertia::render('client/Reports/Inbox/Index', [
            'isDashboard' => $request->routeIs('client.dashboard'),
            'conversationsOverTime' => $analytics->conversationsResolvedOverTime($wsId, $from, $to),
            'channelMix' => $analytics->conversationChannelMix($wsId, $from, $to),
            'agentLeaderboard' => $analytics->agentLeaderboard($wsId, $from, $to),
            'firstResponseTimes' => $analytics->firstResponseTimes($wsId, $from, $to),
            'resolutionTimes' => $analytics->resolutionTimes($wsId, $from, $to),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
