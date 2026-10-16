<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigestMail;
use App\Models\ClientSetting;
use App\Models\Workspace;
use App\Modules\AI\Models\AiRun;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyDigestCommand extends Command
{
    protected $signature = 'reports:weekly-digest';

    protected $description = 'Send weekly performance digest emails to workspace owners';

    public function handle(): int
    {
        $from = Carbon::now()->subWeek()->startOfDay();
        $to = Carbon::now()->startOfDay();
        $period = $from->format('M j').'–'.$to->subDay()->format('M j, Y');

        Workspace::with('owner')->chunk(50, function ($workspaces) use ($from, $to, $period) {
            foreach ($workspaces as $workspace) {
                $owner = $workspace->owner;
                if (! $owner || ! $owner->client_id) {
                    continue;
                }

                // Respect opt-out toggle (default: enabled)
                $enabled = ClientSetting::get($owner->client_id, 'weekly_digest_enabled', '1');
                if ($enabled === '0' || $enabled === 0 || $enabled === false) {
                    continue;
                }

                $stats = $this->buildStats($workspace->id, $from, $to);

                Mail::to($owner->email)->queue(
                    new WeeklyDigestMail(
                        workspace: $workspace,
                        stats: $stats,
                        period: $period,
                        dashboardUrl: route('client.dashboard'),
                        settingsUrl: route('client.settings.index'),
                    )
                );

                $this->line("  Queued digest for workspace #{$workspace->id}: {$workspace->name}");
            }
        });

        $this->info('Weekly digest emails queued.');

        return self::SUCCESS;
    }

    private function buildStats(int $wsId, Carbon $from, Carbon $to): array
    {
        // Messages sent
        $messagesSent = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->where('messages.direction', 'out')
            ->whereBetween('messages.created_at', [$from, $to])
            ->count();

        // Delivery rate from campaigns in the period
        $totalRecipients = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_recipients.campaign_id')
            ->where('campaigns.workspace_id', $wsId)
            ->whereBetween('campaign_recipients.created_at', [$from, $to])
            ->count();

        $delivered = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_recipients.campaign_id')
            ->where('campaigns.workspace_id', $wsId)
            ->whereIn('campaign_recipients.status', ['delivered', 'read'])
            ->whereBetween('campaign_recipients.created_at', [$from, $to])
            ->count();

        $deliveryRate = $totalRecipients > 0 ? round(($delivered / $totalRecipients) * 100, 1) : 0;

        // Conversations
        $convsOpened = Conversation::where('workspace_id', $wsId)->whereBetween('created_at', [$from, $to])->count();
        $convsResolved = Conversation::where('workspace_id', $wsId)->where('status', 'resolved')
            ->whereBetween('updated_at', [$from, $to])->count();

        // AI usage
        $aiRow = AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from, $to])
            ->selectRaw('COUNT(*) as runs, SUM(prompt_tokens + completion_tokens) as tokens, SUM(cost_cents) as cost_cents')
            ->first();

        // Top campaign
        $topCampaign = Campaign::where('workspace_id', $wsId)
            ->whereBetween('created_at', [$from, $to])
            ->withCount(['recipients as delivered_count' => fn ($q) => $q->whereIn('status', ['delivered', 'read'])])
            ->withCount('recipients as total_count')
            ->orderByDesc('delivered_count')
            ->first();

        $topCampaignData = null;
        if ($topCampaign) {
            $topCampaignData = [
                'name' => $topCampaign->name,
                'delivered_pct' => $topCampaign->total_count > 0
                    ? round(($topCampaign->delivered_count / $topCampaign->total_count) * 100, 1)
                    : 0,
            ];
        }

        // Top chatbot
        $topChatbotRow = AiRun::query()
            ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
            ->where('ai_chatbots.workspace_id', $wsId)
            ->whereBetween('ai_runs.created_at', [$from, $to])
            ->selectRaw('ai_chatbots.name, COUNT(*) as runs')
            ->groupBy('ai_chatbots.name')
            ->orderByDesc('runs')
            ->first();

        return [
            'messages_sent' => $messagesSent,
            'delivery_rate' => $deliveryRate,
            'conversations_opened' => $convsOpened,
            'conversations_resolved' => $convsResolved,
            'ai_runs' => (int) ($aiRow->runs ?? 0),
            'ai_tokens' => (int) ($aiRow->tokens ?? 0),
            'ai_cost_usd' => round(($aiRow->cost_cents ?? 0) / 100, 2),
            'top_campaign' => $topCampaignData,
            'top_chatbot' => $topChatbotRow ? ['name' => $topChatbotRow->name, 'runs' => $topChatbotRow->runs] : null,
        ];
    }
}
