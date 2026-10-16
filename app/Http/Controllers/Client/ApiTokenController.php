<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\ApiAbilities;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()->tokens()->latest()->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'abilities' => $t->abilities,
            'last_used_at' => $t->last_used_at?->toIso8601String(),
            'expires_at' => $t->expires_at?->toIso8601String(),
            'created_at' => $t->created_at->toIso8601String(),
        ]);

        return Inertia::render('client/Api/Tokens', [
            'tokens' => $tokens,
        ]);
    }

    /** Create a token using the already-authenticated client web session. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'in:*,'.implode(',', ApiAbilities::all())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $abilities = $validated['abilities'] ?? ['*'];
        $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        return response()->json([
            'id' => $token->accessToken->id,
            'name' => $validated['name'],
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toIso8601String(),
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    /** Revoke only tokens that belong to the authenticated client user. */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        return $deleted
            ? response()->json(['ok' => true])
            : response()->json(['message' => 'Token não encontrado.'], 404);
    }
}
