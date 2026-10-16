<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Diagnostic middleware for /broadcasting/auth.
 *
 * Logs the request shape so we can tell apart:
 *   • user not authenticated (no session / cookie)  → returns 403
 *   • channel callback returned false                → returns 403
 *   • CSRF token mismatch                            → returns 419
 *
 * This is read-only; it always passes the request through.
 */
class BroadcastingAuthDebug
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only act on the broadcasting auth endpoint.
        if (! $request->is('broadcasting/auth')) {
            return $next($request);
        }

        $user = $request->user();

        Log::info('broadcast.auth.request', [
            'has_session_cookie' => (bool) $request->cookies->get(config('session.cookie')),
            'has_csrf_header' => (bool) $request->header('X-CSRF-TOKEN'),
            'authenticated' => (bool) $user,
            'user_id' => $user?->id,
            'channel_name' => $request->input('channel_name'),
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
        ]);

        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            Log::warning('broadcast.auth.response', [
                'status' => $response->getStatusCode(),
                'channel_name' => $request->input('channel_name'),
                'authenticated' => (bool) $user,
                'user_id' => $user?->id,
                'body' => substr((string) $response->getContent(), 0, 500),
            ]);
        }

        return $response;
    }
}
