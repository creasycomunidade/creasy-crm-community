<?php

namespace App\Modules\Leads\Services;

use App\Modules\Ads\Models\AdConnection;
use App\Modules\Ads\Models\AdConversionEvent;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesFunnelService
{
    /**
     * Build one workspace-scoped view of CRM stages, acquisition sources,
     * campaigns, channels and integration health.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $workspaceId, int $days = 30, ?string $pipelineUuid = null): array
    {
        $days = in_array($days, [7, 30, 90, 365], true) ? $days : 30;
        $start = now()->subDays($days - 1)->startOfDay();

        $pipelines = Pipeline::query()
            ->where('workspace_id', $workspaceId)
            ->with(['stages' => fn ($query) => $query->orderBy('position')->orderBy('id')])
            ->withCount('deals')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $selected = $pipelines->firstWhere('uuid', $pipelineUuid)
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->first();

        $dealQuery = Deal::query()
            ->where('crm_deals.workspace_id', $workspaceId)
            ->where('crm_deals.created_at', '>=', $start);

        if ($selected) {
            $dealQuery->where('crm_deals.pipeline_id', $selected->id);
        }

        $totalDeals = (clone $dealQuery)->count();
        $totalValue = (int) (clone $dealQuery)->sum('value_cents');
        $wonQuery = (clone $dealQuery)
            ->join('crm_pipeline_stages', 'crm_pipeline_stages.id', '=', 'crm_deals.stage_id')
            ->where('crm_pipeline_stages.conversion_event', 'Purchase');
        $wonDeals = (clone $wonQuery)->count('crm_deals.id');
        $wonValue = (int) (clone $wonQuery)->sum('crm_deals.value_cents');

        $stageRows = collect();
        if ($selected) {
            $stageRows = DB::table('crm_pipeline_stages as stages')
                ->leftJoin('crm_deals as deals', function ($join) use ($start): void {
                    $join->on('deals.stage_id', '=', 'stages.id')
                        ->where('deals.created_at', '>=', $start);
                })
                ->where('stages.workspace_id', $workspaceId)
                ->where('stages.pipeline_id', $selected->id)
                ->groupBy(
                    'stages.id',
                    'stages.uuid',
                    'stages.name',
                    'stages.color',
                    'stages.position',
                    'stages.conversion_event',
                )
                ->orderBy('stages.position')
                ->orderBy('stages.id')
                ->get([
                    'stages.id',
                    'stages.uuid',
                    'stages.name',
                    'stages.color',
                    'stages.position',
                    'stages.conversion_event',
                    DB::raw('COUNT(deals.id) as deal_count'),
                    DB::raw('COALESCE(SUM(deals.value_cents), 0) as value_cents'),
                ]);
        }

        $sources = $this->sourcePerformance($dealQuery);
        $channels = $this->channelPerformance($workspaceId, $start, $selected?->id);

        return [
            'period' => [
                'days' => $days,
                'start' => $start->toDateString(),
                'end' => now()->toDateString(),
            ],
            'pipelines' => $pipelines->map(fn (Pipeline $pipeline) => [
                'uuid' => $pipeline->uuid,
                'name' => $pipeline->name,
                'color' => $pipeline->color,
                'deals_count' => $pipeline->deals_count,
            ])->values(),
            'selectedPipeline' => $selected ? [
                'uuid' => $selected->uuid,
                'name' => $selected->name,
                'color' => $selected->color,
            ] : null,
            'summary' => [
                'new_deals' => $totalDeals,
                'pipeline_value_cents' => $totalValue,
                'won_deals' => $wonDeals,
                'won_value_cents' => $wonValue,
                'conversion_rate' => $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0,
                'average_ticket_cents' => $wonDeals > 0 ? (int) round($wonValue / $wonDeals) : 0,
            ],
            'stages' => $stageRows->map(fn ($row) => [
                'uuid' => $row->uuid,
                'name' => $row->name,
                'color' => $row->color,
                'conversion_event' => $row->conversion_event,
                'deal_count' => (int) $row->deal_count,
                'value_cents' => (int) $row->value_cents,
                'share' => $totalDeals > 0 ? round(((int) $row->deal_count / $totalDeals) * 100, 1) : 0,
            ])->values(),
            'sources' => $sources,
            'channels' => $channels,
            'campaigns' => collect(),
            'integrations' => collect(),
            'conversionDispatch' => collect(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sourcePerformance(Builder $dealQuery): Collection
    {
        return (clone $dealQuery)
            ->leftJoin('contacts', 'contacts.id', '=', 'crm_deals.contact_id')
            ->leftJoin('crm_pipeline_stages', 'crm_pipeline_stages.id', '=', 'crm_deals.stage_id')
            ->selectRaw("
                COALESCE(NULLIF(contacts.source, ''), 'Não informado') as source,
                COUNT(DISTINCT crm_deals.id) as leads,
                COUNT(DISTINCT CASE WHEN crm_pipeline_stages.conversion_event = 'Purchase' THEN crm_deals.id END) as won,
                COALESCE(SUM(CASE WHEN crm_pipeline_stages.conversion_event = 'Purchase' THEN crm_deals.value_cents ELSE 0 END), 0) as revenue_cents
            ")
            // Agrupar pela coluna de origem mantém o mesmo resultado do
            // COALESCE exibido e funciona com ONLY_FULL_GROUP_BY no MySQL da
            // produção, que rejeita a expressão derivada em algumas versões.
            ->groupBy('contacts.source')
            ->orderByDesc('leads')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'source' => $this->sourceLabel((string) $row->source),
                'leads' => (int) $row->leads,
                'won' => (int) $row->won,
                'revenue_cents' => (int) $row->revenue_cents,
                'conversion_rate' => (int) $row->leads > 0
                    ? round(((int) $row->won / (int) $row->leads) * 100, 1)
                    : 0,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function channelPerformance(int $workspaceId, Carbon $start, ?int $pipelineId): Collection
    {
        return DB::table('crm_deals as deals')
            ->join('conversations', 'conversations.contact_id', '=', 'deals.contact_id')
            ->leftJoin('channel_accounts', 'channel_accounts.id', '=', 'conversations.channel_account_id')
            ->leftJoin('crm_pipeline_stages as stages', 'stages.id', '=', 'deals.stage_id')
            ->where('deals.workspace_id', $workspaceId)
            ->where('conversations.workspace_id', $workspaceId)
            ->where('deals.created_at', '>=', $start)
            ->when($pipelineId, fn ($query) => $query->where('deals.pipeline_id', $pipelineId))
            ->selectRaw("
                COALESCE(channel_accounts.channel, 'outro') as channel,
                COUNT(DISTINCT deals.id) as leads,
                COUNT(DISTINCT CASE WHEN stages.conversion_event = 'Purchase' THEN deals.id END) as won
            ")
            ->groupBy('channel_accounts.channel')
            ->orderByDesc('leads')
            ->get()
            ->map(fn ($row) => [
                'channel' => $this->channelLabel((string) $row->channel),
                'key' => (string) $row->channel,
                'leads' => (int) $row->leads,
                'won' => (int) $row->won,
                'conversion_rate' => (int) $row->leads > 0
                    ? round(((int) $row->won / (int) $row->leads) * 100, 1)
                    : 0,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function campaignPerformance(int $workspaceId, Carbon $start, ?int $pipelineId): Collection
    {
        $campaigns = Campaign::query()
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', $start)
            ->latest()
            ->limit(12)
            ->get(['id', 'uuid', 'name', 'channel', 'status', 'totals_json', 'created_at']);

        if ($campaigns->isEmpty()) {
            return collect();
        }

        // Last-touch attribution inside the selected campaign window. A deal
        // reached by several campaigns is credited only to the most recent one,
        // preventing revenue from being counted more than once.
        $dealTouches = DB::table('campaign_recipients as recipients')
            ->join('crm_deals as deals', 'deals.contact_id', '=', 'recipients.contact_id')
            ->leftJoin('crm_pipeline_stages as stages', 'stages.id', '=', 'deals.stage_id')
            ->whereIn('recipients.campaign_id', $campaigns->pluck('id'))
            ->where('deals.workspace_id', $workspaceId)
            ->whereColumn('recipients.created_at', '<=', 'deals.updated_at')
            ->when($pipelineId, fn ($query) => $query->where('deals.pipeline_id', $pipelineId))
            ->orderBy('deals.id')
            ->orderByDesc('recipients.created_at')
            ->get([
                'recipients.campaign_id',
                'deals.id as deal_id',
                'deals.value_cents',
                'stages.conversion_event',
            ]);

        $conversionRows = $dealTouches
            ->unique('deal_id')
            ->groupBy('campaign_id')
            ->map(fn (Collection $rows) => (object) [
                'leads' => $rows->count(),
                'won' => $rows->where('conversion_event', 'Purchase')->count(),
                'revenue_cents' => $rows->where('conversion_event', 'Purchase')->sum('value_cents'),
            ]);

        return $campaigns->map(function (Campaign $campaign) use ($conversionRows): array {
            $totals = $campaign->totals_json ?? [];
            $row = $conversionRows->get($campaign->id);
            $sent = (int) ($totals['sent'] ?? 0);
            $read = (int) ($totals['read'] ?? 0);
            $clicked = (int) ($totals['clicked'] ?? 0);

            return [
                'uuid' => $campaign->uuid,
                'name' => $campaign->name,
                'channel' => $this->channelLabel($campaign->channel),
                'status' => $campaign->status,
                'sent' => $sent,
                'read' => $read,
                'clicked' => $clicked,
                'read_rate' => $sent > 0 ? round(($read / $sent) * 100, 1) : 0,
                'leads' => (int) ($row->leads ?? 0),
                'won' => (int) ($row->won ?? 0),
                'revenue_cents' => (int) ($row->revenue_cents ?? 0),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationHealth(int $workspaceId): array
    {
        $workspaceConnections = WorkspaceIntegration::query()
            ->where('workspace_id', $workspaceId)
            ->get(['provider', 'name', 'status', 'last_error']);
        $adConnections = AdConnection::query()
            ->where('workspace_id', $workspaceId)
            ->get(['provider', 'name', 'status', 'last_error']);
        $channels = ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->get(['channel', 'provider', 'display_name', 'status']);

        $items = collect()
            ->concat($workspaceConnections->map(fn ($item) => [
                'type' => 'integration',
                'provider' => $item->provider,
                'name' => $item->name,
                'status' => $item->status,
                'error' => $item->last_error,
            ]))
            ->concat($adConnections->map(fn ($item) => [
                'type' => 'ads',
                'provider' => $item->provider,
                'name' => $item->name,
                'status' => $item->status,
                'error' => $item->last_error,
            ]))
            ->concat($channels->map(fn ($item) => [
                'type' => 'channel',
                'provider' => $item->channel,
                'name' => $item->display_name ?: $this->channelLabel($item->channel),
                'status' => $item->status,
                'error' => null,
            ]))
            ->values();

        $connectedStatuses = ['connected', 'active'];

        return [
            'connected' => $items->whereIn('status', $connectedStatuses)->count(),
            'attention' => $items->reject(fn ($item) => in_array($item['status'], $connectedStatuses, true))->count(),
            'items' => $items,
        ];
    }

    private function channelLabel(string $channel): string
    {
        return match (strtolower($channel)) {
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'messenger' => 'Messenger',
            'telegram' => 'Telegram',
            'email' => 'E-mail',
            'sms' => 'SMS',
            'webchat' => 'Chat do site',
            default => ucfirst($channel ?: 'Outro'),
        };
    }

    private function sourceLabel(string $source): string
    {
        return match (strtolower($source)) {
            'instagram_comment' => 'Comentário no Instagram',
            'facebook_comment' => 'Comentário no Facebook',
            'google_places' => 'Google Places',
            'woocommerce' => 'WooCommerce',
            'shopify' => 'Shopify',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'messenger' => 'Messenger',
            'telegram' => 'Telegram',
            'google' => 'Google',
            'facebook', 'meta' => 'Meta',
            default => $source,
        };
    }
}
