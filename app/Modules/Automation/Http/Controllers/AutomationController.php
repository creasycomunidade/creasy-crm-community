<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Models\AiChatbot;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;
use App\Modules\Automation\Services\WorkflowGenerator;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Ecommerce\Models\EcommerceStore;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Appointments\Models\AppointmentResource;
use App\Modules\Appointments\Models\AppointmentService;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    public function index(Request $request): Response
    {
        $wid = $this->workspaceId($request);
        $automations = Automation::where('workspace_id', $wid)
            ->withCount('runs')
            ->latest()->get();

        return Inertia::render('Automation/Index', ['automations' => $automations, 'social_triggers' => \App\Modules\Automation\Services\SocialAutomationTriggers::catalog()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate(['name' => ['required', 'string', 'max:128']]);

        $auto = Automation::create(array_merge($validated, [
            'workspace_id' => $wid,
            'status' => 'draft',
            'nodes' => [['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'Trigger']]],
            'edges' => [],
        ]));

        return redirect()->route('client.automations.edit', $auto->uuid)->with('success', 'Automação criada.');
    }

    public function edit(Request $request, Automation $automation): Response
    {
        $this->authorise($request, $automation);
        $wid = (int) $automation->workspace_id;

        return Inertia::render('Automation/Builder', [
            'automation' => $automation,
            'resources' => $this->builderResources($wid, $automation->id),
        ]);
    }

    /**
     * Reference data the builder needs to populate node config dropdowns
     * (templates, campaigns, chatbots, sub-flows, agents, stores) plus a map of
     * which optional integrations are connected.
     */
    private function builderResources(int $workspaceId, int $currentAutomationId): array
    {
        return [
            // All templates (approved first) so the builder can list existing ones and
            // surface their body variables; non-approved are shown but flagged in the UI.
            'social_drafts' => collect(),
            'node_catalog' => \App\Modules\Automation\Services\ExtendedAutomationNodes::catalog(),
            'segments' => \App\Modules\Shared\Models\Segment::where('workspace_id',$workspaceId)->where('type','static')->get(['id','name']),
            'task_columns' => \App\Modules\Leads\Models\TaskColumn::where('workspace_id',$workspaceId)->get(['id','name']),
            'pipeline_stages' => \App\Modules\Leads\Models\PipelineStage::where('workspace_id',$workspaceId)->get(['id','name']),
            'social_accounts' => collect(),
            'templates' => WhatsappTemplate::where('workspace_id', $workspaceId)
                ->orderByRaw("CASE WHEN status = 'APPROVED' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['name', 'language', 'status', 'components'])
                ->map(fn ($t) => [
                    'name' => $t->name,
                    'language' => $t->language,
                    'status' => $t->status,
                    'components' => $t->components,
                ])
                ->values(),
            'social_triggers' => \App\Modules\Automation\Services\SocialAutomationTriggers::catalog(),
            'trigger_accounts' => \App\Modules\Automation\Services\SocialAutomationTriggers::accounts($workspaceId),
            'channel_accounts' => \App\Modules\Shared\Models\ChannelAccount::where('workspace_id', $workspaceId)
                ->whereIn('channel', ['whatsapp', 'messenger', 'instagram', 'telegram'])
                ->orderBy('display_name')->get(['id', 'channel', 'provider', 'display_name', 'status'])->map(fn($a)=>array_merge($a->toArray(),['message_types'=>\App\Modules\Automation\Services\AutomationChannelCapabilities::types($a)]))->values(),
            'campaigns' => collect(),
            'chatbots' => AiChatbot::where('workspace_id', $workspaceId)
                ->where('enabled', true)->orderBy('name')->get(['id', 'name'])->values(),
            'subflows' => Automation::where('workspace_id', $workspaceId)
                ->where('id', '!=', $currentAutomationId)
                ->orderBy('name')->get(['uuid', 'name', 'status'])->values(),
            'agents' => User::forWorkspace($workspaceId)
                ->orderBy('name')->get(['id', 'name'])->values(),
            'stores' => collect(),
            'appointment_services' => AppointmentService::where('workspace_id', $workspaceId)
                ->where('active', true)->orderBy('name')
                ->get(['id', 'uuid', 'name', 'duration_minutes'])->values(),
            'appointment_resources' => AppointmentResource::where('workspace_id', $workspaceId)
                ->where('active', true)->orderBy('name')
                ->get(['id', 'uuid', 'name', 'timezone'])->values(),
            'integrations' => [
                'google' => (bool) optional(IntegrationConfig::forProvider('google_workspace'))->enabled,
            ],
        ];
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorise($request, $automation);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'status' => ['sometimes', 'in:active,paused,draft'],
            'trigger_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['contact.created', 'message.received', 'webhook', 'webhook.received'])],
            'trigger_config' => ['nullable', 'array'],
            'trigger_config.account_key' => ['nullable', 'string', \Illuminate\Validation\Rule::in(array_column(\App\Modules\Automation\Services\SocialAutomationTriggers::accounts((int) $automation->workspace_id), 'key'))],
            'trigger_config.post_id' => ['nullable', 'string', 'max:255'],
            'trigger_config.keywords' => ['sometimes', 'array', 'max:50'],
            'trigger_config.keywords.*' => ['string', 'max:255'],
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
        ]);
        if (($validated['status'] ?? $automation->status) === 'active') {
            foreach ($validated['nodes'] ?? $automation->nodes ?? [] as $node) {
                $type=$node['data']['nodeType'] ?? $node['type'] ?? '';
                if (in_array($type, ['send_sms', 'add_to_campaign', 'woocommerce_product', 'shopify_product', 'save_memory', 'lookup_lead', 'social_draft', 'publish_social_post', 'ads_summary', 'lookup_order', 'lookup_service_order', 'social_message', 'reply_comment', 'private_reply', 'reply_review'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['nodes' => 'Este nó depende de um módulo não incluído na comunidade: '.$type]);
                }
                if (isset(\App\Modules\Automation\Services\ExtendedAutomationNodes::catalog()[$type])) {
                    try { \App\Modules\Automation\Services\ExtendedAutomationNodes::validate($type,$node['data'] ?? []); }
                    catch (\Illuminate\Validation\ValidationException $e) { throw \Illuminate\Validation\ValidationException::withMessages(['nodes'=>'Confira os campos do nó '.\App\Modules\Automation\Services\ExtendedAutomationNodes::catalog()[$type]['label'].': '.implode(' ',\Illuminate\Support\Arr::flatten($e->errors()))]); }
                }
            }
        }
        $automation->update($validated);

        return back()->with('success', 'Automação salva.');
    }

    public function export(Request $request, Automation $automation)
    {
        $this->authorise($request, $automation);
        $document = app(\App\Modules\Automation\Services\AutomationTransfer::class)->export($automation);
        return response()->streamDownload(fn () => print(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'automacao-'.Str::slug($automation->name).'.json', ['Content-Type'=>'application/json']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file'=>['required','file','max:2048']]);
        try { $document = json_decode(file_get_contents($request->file('file')->getRealPath()), true, 64, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'Selecione um arquivo JSON válido exportado pelo Respondesk.']); }
        if (!is_array($document)) throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'Formato de automação inválido.']);
        $data = app(\App\Modules\Automation\Services\AutomationTransfer::class)->parse($document);
        $automation = Automation::create($data + ['workspace_id'=>$this->workspaceId($request)]);
        return redirect()->route('client.automations.edit',$automation->uuid)->with('success','Automação importada como rascunho. Revise as contas, recursos e credenciais antes de ativar.');
    }

    public function destroy(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorise($request, $automation);
        $automation->delete();

        return redirect()->route('client.automations.index')->with('success', 'Automação excluída.');
    }

    public function runs(Request $request, Automation $automation): Response
    {
        $this->authorise($request, $automation);
        $runs = AutomationRun::where('automation_id', $automation->id)
            ->with('logs')
            ->latest()->paginate(50);

        return Inertia::render('Automation/Runs', ['automation' => $automation, 'runs' => $runs]);
    }

    public function generateToken(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $automation->update(['trigger_token' => Str::random(48)]);

        return response()->json(['trigger_token' => $automation->trigger_token]);
    }

    /**
     * Dry-run the automation and return a step-by-step trace. Tests the live builder
     * state (posted nodes/edges) when supplied, otherwise the saved version. The engine
     * simulates every action — nothing is actually sent and no data is written.
     */
    public function test(Request $request, Automation $automation): JsonResponse
    {
        $this->authorise($request, $automation);
        $validated = $request->validate([
            'nodes' => ['nullable', 'array'],
            'edges' => ['nullable', 'array'],
            'trigger_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['contact.created', 'message.received', 'webhook', 'webhook.received'])],
            'trigger_config' => ['nullable', 'array'],
            'sample_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $nodes = $validated['nodes'] ?? $automation->nodes ?? [];
        $edges = $validated['edges'] ?? $automation->edges ?? [];
        if (array_key_exists('trigger_type', $validated)) {
            $automation->trigger_type = $validated['trigger_type']; // in-memory only, never persisted
        }

        $context = [];
        if (! empty($validated['sample_message'])) {
            $context['message_body'] = $validated['sample_message'];
        }

        return response()->json(app(AutomationEngine::class)->testRun($automation, $nodes, $edges, $context));
    }

    /**
     * Generate a full automation graph from a natural-language prompt via the workspace LLM.
     * With persist=true a new automation is created and an edit URL returned; otherwise the
     * normalised graph is returned for the builder to drop onto the canvas for review.
     */
    public function generate(Request $request): JsonResponse
    {
        $wid = $this->workspaceId($request);
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'persist' => ['nullable', 'boolean'],
        ]);

        try {
            $graph = app(WorkflowGenerator::class)->generate($wid, $validated['prompt']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if ($request->boolean('persist')) {
            $auto = Automation::create([
                'workspace_id' => $wid,
                'name' => $graph['name'],
                'status' => 'draft',
                'trigger_type' => $graph['trigger_type'],
                'trigger_config' => $graph['trigger_config'],
                'nodes' => $graph['nodes'],
                'edges' => $graph['edges'],
            ]);

            return response()->json(['ok' => true, 'redirect' => route('client.automations.edit', $auto->uuid)]);
        }

        return response()->json(['ok' => true, 'graph' => $graph]);
    }

    private function authorise(Request $request, Automation $automation): void
    {
        abort_unless((int) $automation->workspace_id === $this->workspaceId($request), 403);
    }
}
