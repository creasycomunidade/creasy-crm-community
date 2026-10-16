<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\LandingPageController;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('marketing/Contact', [
            'canRegister' => Route::has('register'),
            'landing'     => LandingPageController::getPublicSettings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create([
            ...$validated,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', __('Your message has been received. We\'ll get back to you soon!'));
    }
}
