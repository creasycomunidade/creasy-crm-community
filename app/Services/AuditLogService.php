<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): AuditLog {
        $request = $request ?? request();
        $user = $request->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'client_id' => $user?->client_id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);
    }

    /**
     * Log an action performed by an admin (platform admin user).
     * Uses actor_admin_id and optional meta (plan_id, billing_cycle, reason, etc.).
     */
    public function logAdmin(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $meta = null,
        ?AdminUser $admin = null,
        ?Request $request = null
    ): AuditLog {
        $request = $request ?? request();
        $admin = $admin ?? $request->user('admin');

        return AuditLog::create([
            'actor_admin_id' => $admin?->id,
            'action' => $action,
            'auditable_type' => $targetType,
            'auditable_id' => $targetId,
            'meta' => $meta,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);
    }
}
