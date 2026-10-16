<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\AI\Models\AiRun;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Models\AutomationRunLog;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Social\Models\SocialPost;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    // ──────────────────────────────────────────────────────────────────────────────
    // Messages
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Daily message counts grouped by channel over the given range.
     * Returns: [['date' => 'YYYY-MM-DD', 'whatsapp' => n, 'sms' => n, 'email' => n, ...], ...]
     */
    public function messageVolumeByChannel(int $wsId, Carbon $from, Carbon $to): array
    {
        $rows = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->whereBetween('messages.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(messages.created_at) as date, messages.channel, COUNT(*) as total')
            ->groupBy('date', 'messages.channel')
            ->orderBy('date')
            ->get();

        // Pivot: date => [channel => count]
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date][$row->channel] = (int) $row->total;
        }

        $channels = $rows->pluck('channel')->unique()->values()->all();

        return $this->fillDateSeries($from, $to, $byDate, $channels);
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Campaigns
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Campaign delivery funnel: status bucket counts.
     * Returns: [['name' => 'Queued', 'value' => n], ...]
     */
    public function campaignFunnel(int $campaignId): array
    {
        $counts = CampaignRecipient::where('campaign_id', $campaignId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $steps = ['queued', 'sent', 'delivered', 'read', 'failed'];

        return array_values(array_filter(
            array_map(fn ($step) => [
                'name' => ucfirst($step),
                'value' => (int) ($counts[$step] ?? 0),
            ], $steps),
            fn ($item) => $item['value'] > 0,
        ));
    }

    /**
     * Hourly cumulative delivery curve for a campaign.
     * Returns: [['hour' => 'YYYY-MM-DD HH:00', 'sent' => n, 'delivered' => n, 'read' => n], ...]
     */
    public function campaignDeliveryOverTime(int $campaignId): array
    {
        $sent = CampaignRecipient::where('campaign_id', $campaignId)
            ->whereNotNull('sent_at')
            ->selectRaw($this->hourExpression('sent_at').' as hour, COUNT(*) as total')
            ->groupBy('hour')->orderBy('hour')
            ->pluck('total', 'hour')->toArray();

        $delivered = CampaignRecipient::where('campaign_id', $campaignId)
            ->whereNotNull('delivered_at')
            ->selectRaw($this->hourExpression('delivered_at').' as hour, COUNT(*) as total')
            ->groupBy('hour')->orderBy('hour')
            ->pluck('total', 'hour')->toArray();

        $read = CampaignRecipient::where('campaign_id', $campaignId)
            ->whereNotNull('read_at')
            ->selectRaw($this->hourExpression('read_at').' as hour, COUNT(*) as total')
            ->groupBy('hour')->orderBy('hour')
            ->pluck('total', 'hour')->toArray();

        $allHours = array_unique(array_merge(
            array_keys($sent),
            array_keys($delivered),
            array_keys($read),
        ));
        sort($allHours);

        return array_map(fn ($h) => [
            'hour' => $h,
            'sent' => (int) ($sent[$h] ?? 0),
            'delivered' => (int) ($delivered[$h] ?? 0),
            'read' => (int) ($read[$h] ?? 0),
        ], $allHours);
    }

    /**
     * Average lag (in seconds) between status transitions for a campaign.
     * Returns: ['sent_to_delivered' => n, 'delivered_to_read' => n, 'sent_to_read' => n].
     */
    public function campaignDeliveryLag(int $campaignId): array
    {
        $row = CampaignRecipient::where('campaign_id', $campaignId)
            ->selectRaw('AVG('.$this->secondsBetweenExpression('sent_at', 'delivered_at').') as sent_to_delivered, '
                .'AVG('.$this->secondsBetweenExpression('delivered_at', 'read_at').') as delivered_to_read, '
                .'AVG('.$this->secondsBetweenExpression('sent_at', 'read_at').') as sent_to_read')
            ->first();

        return [
            'sent_to_delivered' => (int) round((float) ($row->sent_to_delivered ?? 0)),
            'delivered_to_read' => (int) round((float) ($row->delivered_to_read ?? 0)),
            'sent_to_read' => (int) round((float) ($row->sent_to_read ?? 0)),
        ];
    }

    /**
     * Failed-reason breakdown for a campaign.
     * Returns: [['name' => reason, 'value' => n], ...]
     */
    public function campaignFailedReasons(int $campaignId): array
    {
        return CampaignRecipient::where('campaign_id', $campaignId)
            ->where('status', 'failed')
            ->selectRaw("COALESCE(failed_reason, 'unknown') as name, COUNT(*) as value")
            ->groupBy('name')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'value' => (int) $r->value])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // AI usage
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Daily AI token usage + cost for a workspace.
     * Returns: [['date' => 'YYYY-MM-DD', 'prompt' => n, 'completion' => n, 'cost_cents' => n], ...]
     */
    public function aiUsageByDay(int $wsId, Carbon $from, Carbon $to): array
    {
        $rows = AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(ai_runs.created_at) as date,
                         SUM(prompt_tokens) as prompt,
                         SUM(completion_tokens) as completion,
                         SUM(cost_cents) as cost_cents')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $byDate = [];
        foreach ($rows as $date => $row) {
            $byDate[$date] = [
                'prompt' => (int) $row->prompt,
                'completion' => (int) $row->completion,
                'cost_cents' => (int) $row->cost_cents,
            ];
        }

        return $this->fillDateSeries($from, $to, $byDate, ['prompt', 'completion', 'cost_cents']);
    }

    /**
     * Token and cost totals grouped by model.
     * Returns: [['model' => '...', 'tokens' => n, 'cost_cents' => n], ...]
     */
    public function aiUsageByModel(int $wsId, Carbon $from, Carbon $to): array
    {
        return AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('ai_runs.model,
                         SUM(prompt_tokens + completion_tokens) as tokens,
                         SUM(cost_cents) as cost_cents')
            ->groupBy('ai_runs.model')
            ->orderByDesc('tokens')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->model ?? 'unknown',
                'value' => (int) $r->tokens,
                'cost_cents' => (int) $r->cost_cents,
            ])
            ->toArray();
    }

    /**
     * AI run KPI aggregates for a workspace in a date range.
     */
    public function aiKpis(int $wsId, Carbon $from, Carbon $to): array
    {
        $row = AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('COUNT(*) as total_runs,
                         SUM(prompt_tokens + completion_tokens) as total_tokens,
                         SUM(cost_cents) as total_cost_cents,
                         AVG(latency_ms) as avg_latency_ms,
                         SUM(CASE WHEN ai_runs.status = \'error\' THEN 1 ELSE 0 END) as failed_runs')
            ->first();

        $errorRate = $row->total_runs > 0
            ? round(($row->failed_runs / $row->total_runs) * 100, 1)
            : 0;

        return [
            'total_runs' => (int) ($row->total_runs ?? 0),
            'total_tokens' => (int) ($row->total_tokens ?? 0),
            'total_cost_cents' => (int) ($row->total_cost_cents ?? 0),
            'avg_latency_ms' => round((float) ($row->avg_latency_ms ?? 0)),
            'error_rate' => $errorRate,
        ];
    }

    /**
     * Top chatbots by AI runs in the range.
     * Returns: [['chatbot_id' => n, 'name' => '...', 'runs' => n, 'tokens' => n, 'avg_latency_ms' => n], ...]
     */
    public function topChatbots(int $wsId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('ai_runs.chatbot_id,
                         ai_chatbots.name,
                         COUNT(*) as runs,
                         SUM(prompt_tokens + completion_tokens) as tokens,
                         AVG(latency_ms) as avg_latency_ms')
            ->groupBy('ai_runs.chatbot_id', 'ai_chatbots.name')
            ->orderByDesc('runs')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'chatbot_id' => $r->chatbot_id,
                'name' => $r->name,
                'runs' => (int) $r->runs,
                'tokens' => (int) $r->tokens,
                'avg_latency_ms' => (int) round($r->avg_latency_ms),
            ])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Inbox / Conversations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Daily opened vs resolved conversation counts.
     * Returns: [['date' => 'YYYY-MM-DD', 'opened' => n, 'resolved' => n], ...]
     */
    public function conversationsResolvedOverTime(int $wsId, Carbon $from, Carbon $to): array
    {
        $opened = Conversation::where('workspace_id', $wsId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')
            ->pluck('total', 'date')->toArray();

        $resolved = Conversation::where('workspace_id', $wsId)
            ->where('status', 'resolved')
            ->whereBetween('updated_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')
            ->pluck('total', 'date')->toArray();

        $byDate = [];
        foreach ($opened as $date => $count) {
            $byDate[$date]['opened'] = (int) $count;
        }
        foreach ($resolved as $date => $count) {
            $byDate[$date]['resolved'] = (int) $count;
        }

        return $this->fillDateSeries($from, $to, $byDate, ['opened', 'resolved']);
    }

    /**
     * Channel mix for conversations (donut data).
     * Returns: [['name' => 'whatsapp', 'value' => n], ...]
     */
    public function conversationChannelMix(int $wsId, Carbon $from, Carbon $to): array
    {
        return Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->whereBetween('messages.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('messages.channel as name, COUNT(DISTINCT conversations.id) as value')
            ->groupBy('messages.channel')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'value' => (int) $r->value])
            ->toArray();
    }

    /**
     * Agent leaderboard by resolved conversations and first-response time.
     * Returns: [['user_id' => n, 'name' => '...', 'handled' => n, 'avg_first_response_min' => n], ...]
     */
    public function agentLeaderboard(int $wsId, Carbon $from, Carbon $to): array
    {
        $convRows = Conversation::where('workspace_id', $wsId)
            ->whereNotNull('assigned_user_id')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('assigned_user_id, COUNT(*) as handled')
            ->groupBy('assigned_user_id')
            ->get()
            ->keyBy('assigned_user_id');

        if ($convRows->isEmpty()) {
            return [];
        }

        $userIds = $convRows->keys()->toArray();

        // First-response time: earliest outbound message after first inbound, per conversation
        $responseSeconds = $this->secondsBetweenExpression('first_in.created_at', 'first_out.sent_at');
        $responseMinutes = DB::getDriverName() === 'sqlite' ? "CAST(($responseSeconds) / 60 AS INTEGER)" : "TRUNCATE(($responseSeconds) / 60, 0)";
        if (DB::getDriverName() === 'pgsql') $responseMinutes = "TRUNC(($responseSeconds) / 60)";
        $firstResponse = DB::select("
            SELECT
                c.assigned_user_id,
                AVG({$responseMinutes}) AS avg_first_response_min
            FROM conversations c
            JOIN (
                SELECT conversation_id, MIN(created_at) as created_at
                FROM messages WHERE direction = 'in'
                GROUP BY conversation_id
            ) first_in ON first_in.conversation_id = c.id
            JOIN (
                SELECT conversation_id, MIN(sent_at) as sent_at
                FROM messages WHERE direction = 'out'
                GROUP BY conversation_id
            ) first_out ON first_out.conversation_id = c.id
            WHERE c.workspace_id = ?
              AND c.assigned_user_id IN (".implode(',', array_fill(0, count($userIds), '?')).')
              AND c.created_at BETWEEN ? AND ?
            GROUP BY c.assigned_user_id
        ', array_merge([$wsId], $userIds, [$from->startOfDay(), $to->endOfDay()]));

        $responseMap = [];
        foreach ($firstResponse as $row) {
            $responseMap[$row->assigned_user_id] = round((float) ($row->avg_first_response_min ?? 0));
        }

        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return $convRows->map(fn ($row) => [
            'user_id' => $row->assigned_user_id,
            'name' => $users[$row->assigned_user_id]?->name ?? 'Unknown',
            'handled' => (int) $row->handled,
            'avg_first_response_min' => $responseMap[$row->assigned_user_id] ?? null,
        ])->sortByDesc('handled')->values()->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Automations
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Daily automation runs grouped by status (stacked bar data).
     * Returns: [['date' => 'YYYY-MM-DD', 'running' => n, 'completed' => n, 'failed' => n], ...]
     */
    public function automationRunsByStatus(int $wsId, Carbon $from, Carbon $to): array
    {
        $rows = AutomationRun::query()
            ->join('automations', 'automations.id', '=', 'automation_runs.automation_id')
            ->where('automations.workspace_id', $wsId)
            ->whereBetween('automation_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(automation_runs.created_at) as date, automation_runs.status, COUNT(*) as total')
            ->groupBy('date', 'automation_runs.status')
            ->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row->date][$row->status] = (int) $row->total;
        }

        $statuses = $rows->pluck('status')->unique()->values()->all();

        return $this->fillDateSeries($from, $to, $byDate, $statuses);
    }

    /**
     * Per-automation run summary for a workspace.
     * Returns: [['automation_id' => n, 'name' => '...', 'runs' => n, 'completed' => n, 'failed' => n], ...]
     */
    public function automationRunsPerAutomation(int $wsId, Carbon $from, Carbon $to): array
    {
        return AutomationRun::query()
            ->join('automations', 'automations.id', '=', 'automation_runs.automation_id')
            ->where('automations.workspace_id', $wsId)
            ->whereBetween('automation_runs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('automation_runs.automation_id,
                         automations.name,
                         COUNT(*) as runs,
                         SUM(CASE WHEN automation_runs.status = \'completed\' THEN 1 ELSE 0 END) as completed,
                         SUM(CASE WHEN automation_runs.status = \'failed\' THEN 1 ELSE 0 END) as failed')
            ->groupBy('automation_runs.automation_id', 'automations.name')
            ->orderByDesc('runs')
            ->get()
            ->map(fn ($r) => [
                'automation_id' => $r->automation_id,
                'name' => $r->name,
                'runs' => (int) $r->runs,
                'completed' => (int) $r->completed,
                'failed' => (int) $r->failed,
            ])
            ->toArray();
    }

    /**
     * Top automation errors from run logs.
     * Returns: [['message' => '...', 'count' => n], ...]
     */
    public function automationTopErrors(int $wsId, Carbon $from, Carbon $to, int $limit = 10): array
    {
        return AutomationRunLog::query()
            ->join('automation_runs', 'automation_runs.id', '=', 'automation_run_logs.run_id')
            ->join('automations', 'automations.id', '=', 'automation_runs.automation_id')
            ->where('automations.workspace_id', $wsId)
            ->where('automation_run_logs.result', 'error')
            ->whereBetween('automation_run_logs.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('automation_run_logs.message, COUNT(*) as count')
            ->groupBy('automation_run_logs.message')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['message' => $r->message ?? '(no message)', 'count' => (int) $r->count])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Social
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Published post count grouped by social network (bar data).
     * Returns: [['name' => 'facebook', 'value' => n], ...]
     */
    public function socialPostsByNetwork(int $wsId, Carbon $from, Carbon $to): array
    {
        $posts = SocialPost::where('workspace_id', $wsId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->whereNotNull('target_accounts')
            ->get(['target_accounts', 'status']);

        $counts = [];
        foreach ($posts as $post) {
            foreach ((array) $post->target_accounts as $accountId) {
                $counts[$accountId] = ($counts[$accountId] ?? 0) + 1;
            }
        }

        return array_map(fn ($net, $count) => ['name' => $net, 'value' => $count], array_keys($counts), $counts);
    }

    /**
     * Social post status breakdown (scheduled/published/failed) for donut.
     */
    public function socialPostsByStatus(int $wsId, Carbon $from, Carbon $to): array
    {
        return SocialPost::where('workspace_id', $wsId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('status as name, COUNT(*) as value')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'value' => (int) $r->value])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Admin
    // ──────────────────────────────────────────────────────────────────────────────


    /**
     * New client signups per week (last N weeks).
     * Returns: [['week' => 'YYYY-WW', 'clients' => n], ...]
     */
    public function newClientsPerWeek(int $weeks = 12): array
    {
        $rows = Client::selectRaw($this->weekExpression('created_at').' as week, COUNT(*) as clients')
            ->where('created_at', '>=', now()->subWeeks($weeks))
            ->groupBy('week')
            ->orderBy('week')
            ->pluck('clients', 'week')
            ->toArray();

        $result = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $week = now()->subWeeks($i)->format('Y-W');
            $result[] = ['week' => $week, 'clients' => (int) ($rows[$week] ?? 0)];
        }

        return $result;
    }


    /**
     * Top workspaces by AI cost.
     * Returns: [['workspace_id' => n, 'name' => '...', 'total_cost_cents' => n], ...]
     */
    public function topWorkspacesByAiCost(int $limit = 10): array
    {
        return AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->join('workspaces', 'workspaces.id', '=', 'ai_chatbots.workspace_id')
            ->selectRaw('ai_chatbots.workspace_id, workspaces.name, SUM(cost_cents) as total_cost_cents')
            ->groupBy('ai_chatbots.workspace_id', 'workspaces.name')
            ->orderByDesc('total_cost_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'workspace_id' => $r->workspace_id,
                'name' => $r->name,
                'total_cost_cents' => (int) $r->total_cost_cents,
            ])
            ->toArray();
    }



    /**
     * New client signups per day over a range.
     * Returns: [['date' => 'YYYY-MM-DD', 'clients' => n], ...]
     */
    public function newClientsByDay(Carbon $from, Carbon $to): array
    {
        $rows = Client::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->date]['clients'] = (int) $r->total;
        }

        return $this->fillDateSeries($from, $to, $byDate, ['clients']);
    }

    /**
     * Platform-wide daily message counts grouped by channel.
     * Returns: [['date' => 'YYYY-MM-DD', 'whatsapp' => n, ...], ...]
     */
    public function platformMessageVolumeByChannel(Carbon $from, Carbon $to): array
    {
        $rows = Message::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, channel, COUNT(*) as total')
            ->groupBy('date', 'channel')->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->date][$r->channel] = (int) $r->total;
        }

        $channels = $rows->pluck('channel')->unique()->values()->all();

        return $this->fillDateSeries($from, $to, $byDate, $channels);
    }

    /**
     * Platform-wide channel mix (donut data) over a range.
     * Returns: [['name' => 'whatsapp', 'value' => n], ...]
     */
    public function platformChannelMix(Carbon $from, Carbon $to): array
    {
        return Message::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('channel as name, COUNT(*) as value')
            ->groupBy('channel')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['name' => $r->name ?? 'unknown', 'value' => (int) $r->value])
            ->toArray();
    }

    /**
     * Most recent client signups.
     * Returns: [['id', 'name', 'email', 'status', 'users_count', 'created_at'], ...]
     */
    public function recentClients(int $limit = 6): array
    {
        return Client::withCount('users')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'status' => $c->status,
                'users_count' => (int) $c->users_count,
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Most recent payment transactions.
     * Returns: [['id', 'amount_cents', 'currency', 'status', 'gateway', 'user', 'created_at'], ...]
     */

    // ──────────────────────────────────────────────────────────────────────────────
    // Client dashboard extras
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * New contacts per day for a workspace.
     * Returns: [['date' => 'YYYY-MM-DD', 'contacts' => n], ...]
     */
    public function contactsGrowthByDay(int $wsId, Carbon $from, Carbon $to): array
    {
        $rows = Contact::where('workspace_id', $wsId)
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')
            ->get();

        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->date]['contacts'] = (int) $r->total;
        }

        return $this->fillDateSeries($from, $to, $byDate, ['contacts']);
    }

    /**
     * Most recently active conversations in a workspace.
     * Returns: [['id', 'uuid', 'contact', 'status', 'unread', 'last_message_at'], ...]
     */
    public function recentConversations(int $wsId, int $limit = 6): array
    {
        return Conversation::where('workspace_id', $wsId)
            ->with('contact:id,first_name,last_name,phone_e164,email')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get()
            ->map(function ($c) {
                $contact = $c->contact;
                $name = $contact
                    ? (trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''))
                        ?: ($contact->phone_e164 ?? $contact->email ?? 'Unknown'))
                    : 'Unknown';

                return [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'contact' => $name,
                    'status' => $c->status,
                    'unread' => (int) $c->unread_count,
                    'last_message_at' => $c->last_message_at?->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Most recent campaigns in a workspace.
     * Returns: [['id', 'name', 'channel', 'status', 'recipients', 'created_at'], ...]
     */
    public function recentCampaigns(int $wsId, int $limit = 6): array
    {
        return Campaign::where('workspace_id', $wsId)
            ->withCount('recipients')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'channel' => $c->channel,
                'status' => $c->status,
                'recipients' => (int) $c->recipients_count,
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    /**
     * Median and P95 first-response times per day (in seconds).
     * Returns: [['date' => 'YYYY-MM-DD', 'median_seconds' => n, 'p95_seconds' => n], ...]
     */
    public function firstResponseTimes(int $wsId, Carbon $from, Carbon $to): array
    {
        return $this->slaMetrics($wsId, $from, $to, 'first_response_at', 'created_at');
    }

    /**
     * Median and P95 resolution times per day (in seconds).
     * Returns: [['date' => 'YYYY-MM-DD', 'median_seconds' => n, 'p95_seconds' => n], ...]
     */
    public function resolutionTimes(int $wsId, Carbon $from, Carbon $to): array
    {
        return $this->slaMetrics($wsId, $from, $to, 'resolved_at', 'created_at');
    }

    private function slaMetrics(int $wsId, Carbon $from, Carbon $to, string $endField, string $startField): array
    {
        $rows = Conversation::where('workspace_id', $wsId)
            ->whereNotNull($endField)
            ->whereBetween($endField, [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE('.$endField.') as date, '.$this->secondsBetweenExpression($startField, $endField).' as seconds')
            ->get();

        // Group by date, then compute median + p95
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r->date][] = (int) $r->seconds;
        }

        $result = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $values = $byDate[$dateStr] ?? [];
            sort($values);
            $count = count($values);

            $result[] = [
                'date' => $dateStr,
                'median_seconds' => $count ? $values[(int) ($count / 2)] : null,
                'p95_seconds' => $count ? $values[(int) (ceil($count * 0.95) - 1)] : null,
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * Fill a date series with zeros for missing dates.
     * $byDate: ['YYYY-MM-DD' => ['key' => value, ...]]
     * $keys: the value keys to include
     */
    private function fillDateSeries(Carbon $from, Carbon $to, array $byDate, array $keys): array
    {
        $result = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $item = ['date' => $dateStr];
            foreach ($keys as $key) {
                $item[$key] = (int) ($byDate[$dateStr][$key] ?? 0);
            }
            $result[] = $item;
        }

        return $result;
    }

    private function hourExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD HH24:00')",
            'sqlite' => "strftime('%Y-%m-%d %H:00', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
        };
    }

    private function weekExpression(string $column): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY') || '-' || lpad(((extract(doy from {$column})::int - 1 + 7 - extract(isodow from {$column})::int) / 7)::text, 2, '0')",
            'sqlite' => "strftime('%Y-%W', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%u')",
        };
    }

    private function secondsBetweenExpression(string $start, string $end): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "TRUNC(EXTRACT(EPOCH FROM ({$end} - {$start})))",
            'sqlite' => "CAST((julianday({$end}) - julianday({$start})) * 86400 AS INTEGER)",
            default => "TIMESTAMPDIFF(SECOND, {$start}, {$end})",
        };
    }
}
