<?php

namespace App\Http\Middleware;

use App\Models\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Set application locale from: 1) user preference, 2) tenant default, 3) session/cookie (guests), 4) default locale from DB.
     * Validate against enabled locales only.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install', 'install/*')) {
            $locale = config('app.locale', 'pt');
            app()->setLocale($locale);
            $request->attributes->set('active_locale', $locale);
            $request->attributes->set('is_rtl', false);
            return $next($request);
        }

        try {
            $enabledCodes = Locale::enabled()->pluck('code')->all();
            $defaultCode = Locale::defaultCode();
        } catch (\Throwable $e) {
            $enabledCodes = ['pt'];
            $defaultCode = 'pt';
        }
        if (empty($enabledCodes)) {
            $enabledCodes = ['pt'];
        }
        if (! in_array($defaultCode, $enabledCodes, true)) {
            $defaultCode = $enabledCodes[0] ?? 'pt';
        }

        $locale = $defaultCode;

        // 1) Authenticated user preference (web guard = client User)
        $user = $request->user();
        if ($user && ! empty($user->locale) && in_array($user->locale, $enabledCodes, true)) {
            $locale = $user->locale;
        }

        // 2) Workspace default locale (when no user locale and user has workspace)
        if ($locale === $defaultCode && $user?->workspace_id) {
            $workspace = $user->workspace;
            if ($workspace && $workspace->default_locale && in_array($workspace->default_locale, $enabledCodes, true)) {
                $locale = $workspace->default_locale;
            }
        }

        // 3) Guest/session preference (and admin panel: admin has no locale on model, use session)
        if ($locale === $defaultCode && $request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if ($sessionLocale && in_array($sessionLocale, $enabledCodes, true)) {
                $locale = $sessionLocale;
            }
        }

        // 4) Cookie for guests (no session)
        if ($locale === $defaultCode && ! $request->hasSession() && $request->cookie('locale')) {
            $cookieLocale = $request->cookie('locale');
            if (in_array($cookieLocale, $enabledCodes, true)) {
                $locale = $cookieLocale;
            }
        }

        // Resolve the RTL flag defensively — the locales table may be
        // unavailable (e.g. before the database is configured during the
        // first-run install), and this must not crash the install wizard.
        try {
            $localeModel = Locale::where('code', $locale)->first();
            $isRtl = $localeModel && $localeModel->is_rtl;
        } catch (\Throwable $e) {
            $isRtl = false;
        }

        app()->setLocale($locale);
        $request->attributes->set('active_locale', $locale);
        $request->attributes->set('is_rtl', $isRtl);

        $response = $next($request);

        // Optional: set cookie for guests so next visit remembers
        if (! $request->user() && $request->hasSession()) {
            // Session is enough; cookie can be set when they switch language
        }

        return $response;
    }
}
