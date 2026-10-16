<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'github', 'microsoft'];

    /**
     * Redirect to the provider's OAuth page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback.
     */
    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Social login failed. Please try again.']);
        }

        $email = $socialUser->getEmail();
        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => 'No email address returned by provider.']);
        }

        $existing = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->with('user')
            ->first();

        if ($existing) {
            $existing->update([
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);
            Auth::login($existing->user, true);

            return redirect()->intended(route('client.dashboard'));
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! config('auth.allow_registration', true)) {
                return redirect()->route('login')->withErrors(['email' => 'No account found. Please register first.']);
            }

            $user = DB::transaction(function () use ($socialUser, $email) {
                $name = $socialUser->getName() ?? $email;
                $client = Client::create([
                    'name' => $name,
                    'email' => $email,
                    'status' => Client::STATUS_ACTIVE,
                    'base_currency' => 'BRL',
                    'currency_symbol' => 'R$',
                    'currency_position' => 'before',
                ]);

                return User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                    'role' => User::ROLE_CLIENT,
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                    'client_id' => $client->id,
                    'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
                ]);
            });
        }

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'email' => $email,
            'avatar_url' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
        ]);

        Auth::login($user, true);

        return redirect()->intended(route('client.dashboard'));
    }
}
