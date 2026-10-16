<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When demo mode is enabled (config app.demo_mode), block every write operation
 * (POST, PUT, PATCH, DELETE) on the route groups this middleware guards — the
 * client area, the admin panel, the client module routes, and the public
 * /api/v1 REST API. A short allowlist keeps authentication actions working so a
 * visitor can still log in and out and an admin can leave an impersonation.
 *
 * Reads (GET/HEAD/OPTIONS) always pass; PII in those responses is masked
 * separately by App\Support\Concerns\MasksDemoData / App\Support\Demo.
 *
 * Inbound webhooks (routes/webhooks.php) are intentionally NOT guarded so a
 * demo deployment with a live channel still receives messages.
 */
class EnsureNotDemoMode
{
    /**
     * Route names that must keep working even in demo mode.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'logout',
        'admin.logout',
        // Impersonation start/stop only switches the session — it writes no
        // business data — so a demo visitor can "Log in as client" to explore the
        // client panel (where every write is still blocked) and return.
        'admin.clients.impersonate',
        'admin.impersonation.stop',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.demo_mode', false)) {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $name = $request->route()?->getName() ?? '';

        if (in_array($name, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // Admin login submit (POST admin/login) is registered without a route name.
        if ($request->path() === 'admin/login') {
            return $next($request);
        }

        $message = __('Demo mode: changes are disabled.');

        // The SPA (Inertia) and REST API clients get a 403 carrying a stable
        // `code` the front-end can detect — Inertia surfaces it through its
        // cancelable `invalid` event so app.jsx can show a toast instead of the
        // default error modal. Classic browser form posts fall back to a
        // redirect-back with a flash message.
        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json([
                'message' => $message,
                'code' => 'demo_mode',
            ], 403);
        }

        return redirect()->back()->with('error', $message);
    }
}
