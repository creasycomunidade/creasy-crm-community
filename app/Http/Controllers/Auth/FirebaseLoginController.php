<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FirebaseLoginController extends Controller
{
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        if (SystemSetting::get('firebase_enabled', 'false') !== 'true') {
            return response()->json(['message' => 'Firebase login is not enabled.'], 403);
        }

        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $projectId = SystemSetting::get('firebase_project_id', '');
        if (! $projectId) {
            return response()->json(['message' => 'Firebase project is not configured.'], 500);
        }

        $tokenInfo = $this->verifyIdToken($request->id_token, $projectId);
        if (! $tokenInfo) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        $email   = $tokenInfo['email'] ?? null;
        $uid     = $tokenInfo['sub']   ?? null;
        $name    = $tokenInfo['name']  ?? $email;
        $avatar  = $tokenInfo['picture'] ?? null;

        if (! $email || ! $uid) {
            return response()->json(['message' => 'Could not retrieve email from token.'], 422);
        }

        $existing = SocialAccount::where('provider', 'firebase')
            ->where('provider_id', $uid)
            ->with('user')
            ->first();

        if ($existing) {
            Auth::login($existing->user, true);
            return response()->json(['redirect' => route('client.dashboard')]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! config('auth.allow_registration', true)) {
                return response()->json(['message' => 'No account found. Please register first.'], 403);
            }

            $user = DB::transaction(function () use ($name, $email) {
                $client = Client::create([
                    'name'              => $name,
                    'email'             => $email,
                    'status'            => Client::STATUS_ACTIVE,
                    'base_currency'     => 'BRL',
                    'currency_symbol'   => 'R$',
                    'currency_position' => 'before',
                ]);

                return User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'password'          => bcrypt(Str::random(32)),
                    'role'              => User::ROLE_CLIENT,
                    'status'            => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                    'client_id'         => $client->id,
                    'client_role'       => User::CLIENT_ROLE_ADMINISTRATOR,
                ]);
            });
        }

        $user->socialAccounts()->create([
            'provider'    => 'firebase',
            'provider_id' => $uid,
            'email'       => $email,
            'avatar_url'  => $avatar,
        ]);

        Auth::login($user, true);

        return response()->json(['redirect' => route('client.dashboard')]);
    }

    private function verifyIdToken(string $idToken, string $projectId): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            // Verify audience matches the Firebase project
            $aud = $data['aud'] ?? '';
            $iss = $data['iss'] ?? '';

            $validAudience = $aud === $projectId || Str::contains($aud, $projectId);
            $validIssuer   = in_array($iss, [
                "https://securetoken.google.com/{$projectId}",
                'https://accounts.google.com',
            ]);

            if (! $validAudience && ! $validIssuer) {
                return null;
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }
}
