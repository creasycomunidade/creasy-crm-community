<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(private Google2FA $google2fa) {}

    /**
     * Show 2FA setup page (enables and generates QR).
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        $qrCode = null;
        $secretKey = null;
        $recoveryCodes = [];

        if (! $user->hasTwoFactorEnabled()) {
            $secretKey = $this->google2fa->generateSecretKey();
            $qrCode = $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secretKey
            );
            $request->session()->put('2fa_secret', $secretKey);
        } else {
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
        }

        return Inertia::render('Profile/TwoFactor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'qrCode' => $qrCode,
            'secretKey' => $secretKey,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Confirm and enable 2FA using user-provided OTP.
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $user = $request->user();
        $secret = $request->session()->get('2fa_secret');

        if (! $secret || ! $this->google2fa->verifyKey($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget('2fa_secret');

        return redirect()->route('client.profile.2fa')->with('success', 'Two-factor authentication enabled.');
    }

    /**
     * Disable 2FA for the user.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('client.profile.2fa')->with('success', 'Two-factor authentication disabled.');
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->update([
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
        ]);

        return redirect()->route('client.profile.2fa')->with('success', 'Recovery codes regenerated.');
    }

    /**
     * Verify 2FA challenge after login (session-based, before full auth).
     */
    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Verify OTP or recovery code to complete login.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('2fa_user_id');
        $user = \App\Models\User::findOrFail($userId);
        $code = $request->input('code');

        $valid = false;

        if (strlen($code) === 6 && ctype_digit($code)) {
            $valid = $this->google2fa->verifyKey($user->two_factor_secret, $code);
        } else {
            // Recovery code
            $codes = $user->two_factor_recovery_codes ?? [];
            $index = array_search($code, $codes, true);
            if ($index !== false) {
                $valid = true;
                unset($codes[$index]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);
            }
        }

        if (! $valid) {
            return back()->withErrors(['code' => 'Invalid code.']);
        }

        auth()->login($user);
        $request->session()->forget('2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('client.dashboard'));
    }

    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)))->all();
    }
}
