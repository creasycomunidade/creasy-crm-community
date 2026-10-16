<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify that the current Sanctum token has the required ability.
 * Used as: ->middleware('api.ability:contacts:read')
 */
class CheckApiAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Wildcard '*' tokens (e.g. tokens created without explicit abilities) pass everything
        if (in_array('*', (array) $token->abilities, true)) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                return response()->json([
                    'error' => 'Forbidden. This token does not have the required scope.',
                    'required_scope' => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
