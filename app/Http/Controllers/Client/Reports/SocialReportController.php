<?php

namespace App\Http\Controllers\Client\Reports;

use App\Http\Controllers\Controller;
use App\Modules\Social\Models\SocialPost;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialReportController extends Controller
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

        $recentPosts = SocialPost::where('workspace_id', $wsId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'title', 'status', 'target_accounts', 'scheduled_at', 'published_at', 'post_url']);

        return Inertia::render('client/Reports/Social/Index', [
            'postsByNetwork' => $analytics->socialPostsByNetwork($wsId, $from, $to),
            'postsByStatus' => $analytics->socialPostsByStatus($wsId, $from, $to),
            'recentPosts' => $recentPosts,
            'dateRange' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }
}
