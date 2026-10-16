<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns or propagates a unique request ID via the X-Request-Id header.
 * The ID is added to log context so all log lines for a request share the same ID.
 */
class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        // Share with log context
        Log::withContext(['request_id' => $requestId]);

        // Add to request so controllers can access it
        $request->headers->set('X-Request-Id', $requestId);

        // Share user/workspace context once the user is resolved
        $user = $request->user();
        if ($user) {
            Log::withContext([
                'user_id' => $user->id,
                'workspace_id' => $user->current_workspace_id ?? $user->workspace_id,
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
