<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For client app routes: set current_client_id on the request from the authenticated user.
 * Controllers should use this to scope queries (e.g. only show data for the user's client).
 */
class EnsureClientScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->client_id) {
            $request->attributes->set('current_client_id', $user->client_id);
        }

        return $next($request);
    }
}
