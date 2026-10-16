<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SmtpConfiguration;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
            'agree_terms' => ['accepted'],
            'timezone'    => ['nullable', 'string', 'max:64'],
        ], [
            'agree_terms.accepted' => 'Você precisa aceitar os Termos e Condições para criar uma conta.',
        ]);

        // Use the browser-detected timezone; fall back to São Paulo.
        $timezone = $this->resolveTimezone($request->input('timezone'));

        $user = DB::transaction(function () use ($request, $timezone) {
            $client = Client::create([
                'name' => $request->name,
                'email' => $request->email,
                'status' => Client::STATUS_ACTIVE,
                'base_currency' => 'BRL',
                'currency_symbol' => 'R$',
                'currency_position' => 'before',
            ]);

            return User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_CLIENT,
                'status' => User::STATUS_ACTIVE,
                'client_id' => $client->id,
                'client_role' => User::CLIENT_ROLE_ADMINISTRATOR,
                'timezone' => $timezone,
                // No active SMTP config = app cannot send verification mail,
                // so auto-verify the account instead of trapping the user.
                'email_verified_at' => SmtpConfiguration::isConfigured() ? null : now(),
            ]);
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect(route('client.dashboard', absolute: false));
    }

    private function resolveTimezone(?string $tz): string
    {
        $default = 'America/Sao_Paulo';
        if (! $tz) {
            return $default;
        }
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Exception) {
            return $default;
        }
    }
}
