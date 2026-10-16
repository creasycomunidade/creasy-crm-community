<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        // The admin and client sign-in pages are unified on this single route.
        // An admin who is already authenticated (on the separate `admin` guard)
        // and lands here goes straight to the admin panel rather than seeing the
        // form. Already-authenticated clients are handled by the `guest`
        // middleware on this route.
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        // Determine which social providers are configured
        $socialProviders = array_filter(
            ['google', 'github', 'microsoft'],
            fn ($p) => ! empty(config("services.{$p}.client_id"))
        );

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'socialProviders' => array_values($socialProviders),
            // In demo mode, surface the seeded demo credentials so the login
            // screen can offer single-click sign-in cards. Null otherwise. The
            // unified store() below routes the admin pair to the admin panel and
            // the client pair to the client dashboard automatically.
            'demo' => config('app.demo_mode')
                ? [
                    [
                        'role' => 'admin',
                        'email' => config('app.demo_admin_email'),
                        'password' => config('app.demo_admin_password'),
                    ],
                    [
                        'role' => 'client',
                        'email' => config('app.demo_email'),
                        'password' => config('app.demo_password'),
                    ],
                ]
                : null,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // This page serves both audiences. Try the admin guard first: if the
        // credentials match an admin account, sign them in there and send them
        // to the admin panel. A non-admin email simply falls through to the
        // client (web) guard below. When the same email exists in both tables,
        // admin wins.
        if ($this->attemptAdmin($request)) {
            $request->session()->regenerate();

            return $this->redirectAdminAfterLogin($request);
        }

        // Client (web) guard. LoginRequest handles validation, rate limiting
        // (keyed on email|ip, so a failed admin attempt above is still counted),
        // and the web-guard attempt.
        $request->authenticate();

        $user = Auth::user();

        // If 2FA is enabled, log out and redirect to the 2FA challenge
        if ($user && $user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa_user_id', $user->getAuthIdentifier());
            Auth::logout();

            return redirect()->route('auth.two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('client.dashboard', absolute: false), 303);
    }

    /**
     * Attempt to sign the credentials in against the admin guard.
     *
     * Returns false (so the caller can fall through to the client guard) when
     * the email/password don't match an admin. If they match but the admin
     * account is inactive we throw — we must not silently fall through and try
     * the same credentials as a client.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function attemptAdmin(LoginRequest $request): bool
    {
        if (! Auth::guard('admin')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            return false;
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->isActive()) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages([
                'email' => __('Your account is inactive.'),
            ]);
        }

        return true;
    }

    /**
     * Send a freshly-signed-in admin to the admin panel.
     *
     * We deliberately do NOT use redirect()->intended() blindly: `url.intended`
     * is shared across guards, so it can hold a client (web-guard) URL — e.g.
     * the admin clicked an /app/* link while logged out and was bounced here.
     * Replaying that would land the admin on a route they can't access on the
     * admin guard, bouncing them back to /login (an apparent "login fails"
     * loop). So we only honour an intended URL that points back into /admin.
     */
    protected function redirectAdminAfterLogin(Request $request): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');
        $fallback = route('admin.dashboard', absolute: false);

        $target = $intended && Str::startsWith((string) parse_url($intended, PHP_URL_PATH), '/admin')
            ? $intended
            : $fallback;

        return redirect()->to($target, 303);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
