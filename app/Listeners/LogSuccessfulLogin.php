<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(
        private Request $request
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        $isAdmin = $event->guard === 'admin';

        AuditLog::create([
            'actor_admin_id' => $isAdmin ? $user->id : null,
            'user_id' => $isAdmin ? null : $user->id,
            'client_id' => $isAdmin ? null : ($user->client_id ?? null),
            'action' => 'auth.login',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => ['guard' => $event->guard],
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'url' => $this->request->fullUrl(),
        ]);
    }
}
