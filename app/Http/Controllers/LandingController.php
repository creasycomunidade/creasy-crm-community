<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\LandingPageController;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    private function landingDisabledRedirect(): ?RedirectResponse
    {
        if (SystemSetting::get('landing.page_enabled', '1') === '1' || ! Route::has('login')) {
            return null;
        }

        return redirect()->route('login');
    }




    public function faq(): Response|RedirectResponse
    {
        if ($redirect = $this->landingDisabledRedirect()) {
            return $redirect;
        }

        return Inertia::render('marketing/Faq', [
            'canRegister' => Route::has('register'),
            'landing'     => LandingPageController::getPublicSettings(),
        ]);
    }

    public function useCases(): Response|RedirectResponse
    {
        if ($redirect = $this->landingDisabledRedirect()) {
            return $redirect;
        }

        return Inertia::render('marketing/UseCases', [
            'canRegister' => Route::has('register'),
            'landing'     => LandingPageController::getPublicSettings(),
        ]);
    }

    public function about(): Response|RedirectResponse
    {
        if ($redirect = $this->landingDisabledRedirect()) {
            return $redirect;
        }

        return Inertia::render('marketing/About', [
            'canRegister' => Route::has('register'),
            'landing'     => LandingPageController::getPublicSettings(),
        ]);
    }

    public function integrations(): Response|RedirectResponse
    {
        if ($redirect = $this->landingDisabledRedirect()) {
            return $redirect;
        }

        return Inertia::render('marketing/Integrations', [
            'canRegister' => Route::has('register'),
            'landing'     => LandingPageController::getPublicSettings(),
        ]);
    }
}
