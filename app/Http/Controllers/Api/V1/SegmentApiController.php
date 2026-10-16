<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ContactResource;
use App\Http\Resources\Api\V1\SegmentResource;
use App\Modules\Shared\Models\Segment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SegmentApiController extends WorkspaceScopedController
{
    /**
     * GET /api/v1/segments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $segments = Segment::where('workspace_id', $this->workspaceId($request))
            ->latest('id')
            ->paginate(25);

        return SegmentResource::collection($segments);
    }

    /**
     * POST /api/v1/segments
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'in:static,dynamic'],
            'rules' => ['nullable', 'array'],
        ]);

        $segment = Segment::create([
            'workspace_id' => $this->workspaceId($request),
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'static',
            'rules_json' => $validated['rules'] ?? null,
            'contact_count' => 0,
        ]);

        return (new SegmentResource($segment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/segments/{id}/contacts
     */
    public function contacts(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $segment = Segment::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $segment) {
            return response()->json(['error' => 'Segment not found.'], 404);
        }

        $contacts = $segment->contacts()->with('tags')->latest('contacts.id')->paginate(25);

        return ContactResource::collection($contacts);
    }
}
