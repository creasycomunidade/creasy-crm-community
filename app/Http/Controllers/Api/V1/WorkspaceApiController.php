<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()->accessibleWorkspaces()->map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'currency_code' => $w->currency_code,
            'created_at' => $w->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $workspaces]);
    }
}
