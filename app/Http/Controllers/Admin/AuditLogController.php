<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {

        $query = AuditLog::with(['user:id,name,email', 'actorAdmin:id,name,email']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('actor_admin_id')) {
            $query->where('actor_admin_id', $request->actor_admin_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->orderByDesc('created_at')->paginate(30)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
            'actor_admin' => $log->actorAdmin ? ['id' => $log->actorAdmin->id, 'name' => $log->actorAdmin->name, 'email' => $log->actorAdmin->email] : null,
            'action' => $log->action,
            'meta' => $log->meta,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip' => $log->ip,
            'url' => $log->url,
            'created_at' => $log->created_at->toIso8601String(),
        ]);

        return Inertia::render('Admin/AuditLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['user_id', 'actor_admin_id', 'action']),
        ]);
    }
}
