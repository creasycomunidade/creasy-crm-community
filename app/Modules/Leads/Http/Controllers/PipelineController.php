<?php

namespace App\Modules\Leads\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\User;
use App\Modules\Leads\Models\Deal;
use App\Modules\Leads\Models\Pipeline;
use App\Modules\Leads\Models\PipelineStage;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    private const DEFAULT_STAGES = [
        ['name' => 'Novo lead', 'color' => '#8b5cf6'],
        ['name' => 'Em contato', 'color' => '#3b82f6'],
        ['name' => 'Proposta enviada', 'color' => '#f59e0b'],
        ['name' => 'Negociação', 'color' => '#ec4899'],
        ['name' => 'Ganho', 'color' => '#22c55e', 'conversion_event' => 'Purchase'],
        ['name' => 'Perdido', 'color' => '#ef4444'],
    ];

    public function index(Request $request): Response
    {
        $workspaceId = $this->workspaceId($request);
        $this->ensureDefaultPipeline($workspaceId);

        $pipelines = Pipeline::query()
            ->where('workspace_id', $workspaceId)
            ->withCount('deals')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $selected = $pipelines->firstWhere('uuid', (string) $request->query('pipeline'))
            ?? $pipelines->firstWhere('is_default', true)
            ?? $pipelines->first();

        $selected?->load([
            'stages' => fn ($query) => $query
                ->with(['deals.contact', 'deals.assignedUser:id,name'])
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        $contacts = Contact::query()
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'uuid', 'first_name', 'last_name', 'phone_e164', 'email', 'avatar', 'source']);

        $team = User::forWorkspace($workspaceId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $prefillContact = null;
        $prefillAlreadyAdded = false;
        if ($request->filled('contact')) {
            $prefillContact = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->where(function ($query) use ($request): void {
                    $value = (string) $request->query('contact');
                    $query->where('uuid', $value);
                    if (ctype_digit($value)) {
                        $query->orWhere('id', (int) $value);
                    }
                })
                ->first();

            $prefillAlreadyAdded = $prefillContact && $selected
                ? Deal::where('pipeline_id', $selected->id)
                    ->where('contact_id', $prefillContact->id)
                    ->exists()
                : false;
        }

        return Inertia::render('CRM/Pipelines/Index', [
            'pipelines' => $pipelines,
            'pipeline' => $selected,
            'contacts' => $contacts,
            'teamMembers' => $team,
            'currency' => Currency::defaultCode() ?: 'BRL',
            'prefillContactId' => $prefillContact?->id,
            'prefillAlreadyAdded' => $prefillAlreadyAdded,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->workspaceId($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $pipeline = DB::transaction(function () use ($workspaceId, $data): Pipeline {
            $pipeline = Pipeline::create([
                'workspace_id' => $workspaceId,
                'name' => $data['name'],
                'color' => $data['color'] ?? '#7c3aed',
                'position' => (int) Pipeline::where('workspace_id', $workspaceId)->max('position') + 1,
            ]);

            foreach (self::DEFAULT_STAGES as $position => $stage) {
                $pipeline->stages()->create($stage + [
                    'workspace_id' => $workspaceId,
                    'position' => $position,
                ]);
            }

            return $pipeline;
        });

        return redirect()->route('client.crm.pipelines.index', ['pipeline' => $pipeline->uuid])
            ->with('success', 'Pipeline criado.');
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorise($request, $pipeline);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $pipeline->update($data);

        return response()->json(['pipeline' => $pipeline->fresh()->loadCount('deals')]);
    }

    public function destroy(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $this->authorise($request, $pipeline);
        $remaining = Pipeline::where('workspace_id', $pipeline->workspace_id)->where('id', '!=', $pipeline->id)->count();
        if ($remaining === 0) {
            return back()->withErrors(['pipeline' => 'É necessário manter pelo menos um pipeline.']);
        }

        DB::transaction(function () use ($pipeline): void {
            Deal::where('pipeline_id', $pipeline->id)->delete();
            PipelineStage::where('pipeline_id', $pipeline->id)->delete();
            $pipeline->delete();
        });

        return redirect()->route('client.crm.pipelines.index')->with('success', 'Pipeline removido.');
    }

    public function storeStage(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorise($request, $pipeline);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'conversion_event' => ['nullable', 'in:Lead,QualifiedLead,Purchase'],
        ]);

        $stage = $pipeline->stages()->create($data + [
            'workspace_id' => $pipeline->workspace_id,
            'position' => (int) $pipeline->stages()->max('position') + 1,
        ]);

        return response()->json(['stage' => $stage], 201);
    }

    public function updateStage(Request $request, PipelineStage $stage): JsonResponse
    {
        $this->authorise($request, $stage);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'conversion_event' => ['nullable', 'in:Lead,QualifiedLead,Purchase'],
        ]);
        $stage->update($data);

        return response()->json(['stage' => $stage->fresh()]);
    }

    public function destroyStage(Request $request, PipelineStage $stage): JsonResponse
    {
        $this->authorise($request, $stage);
        $replacement = PipelineStage::query()
            ->where('pipeline_id', $stage->pipeline_id)
            ->where('id', '!=', $stage->id)
            ->orderBy('position')
            ->first();
        abort_unless($replacement, 422, 'É necessário manter pelo menos uma coluna.');

        DB::transaction(function () use ($stage, $replacement): void {
            Deal::where('stage_id', $stage->id)->update(['stage_id' => $replacement->id]);
            $stage->delete();
        });

        return response()->json(['moved_to_stage_uuid' => $replacement->uuid]);
    }

    public function storeDeal(Request $request, Pipeline $pipeline): JsonResponse
    {
        $this->authorise($request, $pipeline);
        $data = $request->validate([
            'stage_id' => ['required', 'integer'],
            'contact_id' => [
                'required',
                'integer',
                Rule::unique('crm_deals', 'contact_id')->where('workspace_id', $pipeline->workspace_id),
            ],
            'title' => ['nullable', 'string', 'max:180'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'assigned_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'contact_id.unique' => 'Este contato já está em um Kanban. Use a Caixa de entrada para movê-lo.',
        ]);

        $stage = PipelineStage::where('pipeline_id', $pipeline->id)->findOrFail($data['stage_id']);
        $contact = Contact::where('workspace_id', $pipeline->workspace_id)->findOrFail($data['contact_id']);
        $this->validateAssignee($pipeline->workspace_id, $data['assigned_user_id'] ?? null);

        $deal = Deal::create([
            'workspace_id' => $pipeline->workspace_id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'title' => trim((string) ($data['title'] ?? '')) ?: ($contact->full_name ?: $contact->phone_e164 ?: $contact->email ?: 'Novo lead'),
            'value_cents' => (int) round(((float) ($data['value'] ?? 0)) * 100),
            'currency' => Currency::defaultCode() ?: 'BRL',
            'notes' => $data['notes'] ?? null,
            'position' => (int) Deal::where('stage_id', $stage->id)->max('position') + 1,
        ]);

        return response()->json(['deal' => $deal->load(['contact', 'assignedUser:id,name'])], 201);
    }

    public function updateDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->authorise($request, $deal);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'assigned_user_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->validateAssignee($deal->workspace_id, $data['assigned_user_id'] ?? null);
        $deal->update([
            'title' => $data['title'],
            'value_cents' => (int) round(((float) ($data['value'] ?? 0)) * 100),
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['deal' => $deal->fresh()->load(['contact', 'assignedUser:id,name'])]);
    }

    public function moveDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->authorise($request, $deal);
        $data = $request->validate(['stage_id' => ['required', 'integer']]);
        $stage = PipelineStage::where('pipeline_id', $deal->pipeline_id)->findOrFail($data['stage_id']);
        $deal->update([
            'stage_id' => $stage->id,
            'position' => (int) Deal::where('stage_id', $stage->id)->where('id', '!=', $deal->id)->max('position') + 1,
        ]);

        return response()->json(['deal' => $deal->fresh()]);
    }

    public function destroyDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->authorise($request, $deal);
        $deal->delete();

        return response()->json([], 204);
    }

    private function ensureDefaultPipeline(int $workspaceId): void
    {
        if (Pipeline::where('workspace_id', $workspaceId)->exists()) {
            return;
        }

        DB::transaction(function () use ($workspaceId): void {
            $pipeline = Pipeline::firstOrCreate(
                ['workspace_id' => $workspaceId, 'is_default' => true],
                ['name' => 'Pipeline de vendas', 'color' => '#7c3aed', 'position' => 0],
            );

            if ($pipeline->stages()->exists()) {
                return;
            }
            foreach (self::DEFAULT_STAGES as $position => $stage) {
                $pipeline->stages()->create($stage + [
                    'workspace_id' => $workspaceId,
                    'position' => $position,
                ]);
            }
        });
    }

    private function workspaceId(Request $request): int
    {
        return (int) ($request->user()->current_workspace_id ?? $request->user()->workspace_id);
    }

    private function authorise(Request $request, object $model): void
    {
        abort_unless((int) $model->workspace_id === $this->workspaceId($request), 403);
    }

    private function validateAssignee(int $workspaceId, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }
        abort_unless(User::forWorkspace($workspaceId)->whereKey($userId)->exists(), 422, 'Responsável inválido.');
    }
}
