<?php

namespace App\Modules\Leads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Services\SalesFunnelService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SalesFunnelController extends Controller
{
    public function __invoke(Request $request, SalesFunnelService $service): Response
    {
        $workspaceId = (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);

        if (! $this->tablesReady()) {
            return $this->setupRequiredResponse();
        }

        if (! Pipeline::where('workspace_id', $workspaceId)->exists()) {
            return Inertia::render('CRM/Funnel/Index', [
                'funnel' => null,
                'currency' => Currency::defaultCode() ?: 'BRL',
                'needsPipeline' => true,
            ]);
        }

        $days = (int) $request->integer('days', 30);
        $pipeline = $request->filled('pipeline') ? (string) $request->query('pipeline') : null;

        try {
            return Inertia::render('CRM/Funnel/Index', [
                'funnel' => $service->dashboard($workspaceId, $days, $pipeline),
                'currency' => Currency::defaultCode() ?: 'BRL',
                'needsPipeline' => false,
                'setupRequired' => false,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return $this->setupRequiredResponse();
        }
    }

    private function tablesReady(): bool
    {
        foreach ([
            'crm_pipelines',
            'crm_pipeline_stages',
            'crm_deals',
            'workspace_integration_connections',
            'ad_connections',
            'ad_conversion_events',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function setupRequiredResponse(): Response
    {
        return Inertia::render('CRM/Funnel/Index', [
            'funnel' => null,
            'currency' => 'BRL',
            'needsPipeline' => false,
            'setupRequired' => true,
        ]);
    }
}
