<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Require user to have an admin role (super_admin or support).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! $request->user()->isAdmin()) {
            abort(403, 'Access denied. Admin role required.');
        }

        return $next($request);
    }
}
