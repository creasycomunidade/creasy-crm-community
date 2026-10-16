<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('client.dashboard', absolute: false));
        }

        if (! $request->user()->sendEmailVerificationNow()) {
            return back()->withErrors([
                'email' => 'Não foi possível enviar o e-mail de confirmação. Tente novamente em alguns minutos.',
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
