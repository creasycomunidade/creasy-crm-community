<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $logs = AuditLog::where('user_id', $user->id)
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }
}
