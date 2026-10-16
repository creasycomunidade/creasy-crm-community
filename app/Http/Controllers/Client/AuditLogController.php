<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user->client_id || ! $user->isClientAdministrator()) {
            abort(403, __('Only client administrators can view the audit log.'));
        }

        $query = AuditLog::where('client_id', $user->client_id)
            ->with(['user:id,name,email']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
                'action' => $log->action,
                'meta' => $log->meta,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'ip' => $log->ip,
                'url' => $log->url,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('client/AuditLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['user_id', 'action']),
        ]);
    }
}
