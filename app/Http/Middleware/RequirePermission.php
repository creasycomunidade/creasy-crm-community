<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Require at least one of the given permission keys.
     *
     * @param  string  ...$permissions  Permission keys (e.g. view_admins, manage_admin_roles)
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = $request->user('admin');

        if (! $admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('admin.login');
        }

        if (! $admin->isActive()) {
            auth('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login')->with('error', __('Account is inactive.'));
        }

        if ($admin->hasAnyPermission($permissions)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
        }

        return redirect()->route('admin.dashboard')->with('error', __('Access denied.'));
    }
}
