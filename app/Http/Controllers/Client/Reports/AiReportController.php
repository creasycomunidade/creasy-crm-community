<?php

namespace App\Http\Controllers\Client\Reports;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiReportController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $wsId = $request->user()->workspace_id;
        abort_if(! $wsId, 403);

        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return Inertia::render('client/Reports/Ai/Index', [
            'kpis' => $analytics->aiKpis($wsId, $from, $to),
            'tokensByDay' => $analytics->aiUsageByDay($wsId, $from, $to),
            'tokensByModel' => $analytics->aiUsageByModel($wsId, $from, $to),
            'topChatbots' => $analytics->topChatbots($wsId, $from, $to),
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
