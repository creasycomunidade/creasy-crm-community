<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationApiController extends WorkspaceScopedController
{
    /**
     * GET /api/v1/automations
     */
    public function index(Request $request): JsonResponse
    {
        $automations = Automation::where('workspace_id', $this->workspaceId($request))
            ->latest('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'status' => $a->status,
                'trigger_type' => $a->trigger_type,
                'run_count' => $a->run_count,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $automations]);
    }

    /**
     * POST /api/v1/automations/{id}/trigger
     * Manually fire an automation for a specific contact.
     */
    public function trigger(Request $request, int $id): JsonResponse
    {
        $automation = Automation::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $automation) {
            return response()->json(['error' => 'Automation not found.'], 404);
        }

        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
        ]);

        $contact = Contact::where('workspace_id', $this->workspaceId($request))->find($validated['contact_id']);
        if (! $contact) {
            return response()->json(['error' => 'Contact not found.'], 404);
        }

        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'context' => [],
            'started_at' => now(),
        ]);

        ExecuteAutomationRunJob::dispatch($run->id)->onQueue('automations');

        return response()->json([
            'run_id' => $run->id,
            'automation_id' => $automation->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
        ], 201);
    }
}
