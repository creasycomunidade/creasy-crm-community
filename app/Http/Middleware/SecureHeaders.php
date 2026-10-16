<?php

namespace App\Http\Middleware;

use App\Modules\Integrations\Services\CredentialResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        if ($request->is('agendar/*') || $request->is('widgets/chat/*')) {
            $response->headers->remove('X-Frame-Options');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Prevent the browser from caching authenticated pages. Without this, the
        // back button after logout restores a cached/bfcache copy of the dashboard,
        // making it look as if the session is still active. `no-store` forces a
        // server round-trip on back/forward, which redirects to login once logged out.
        if ($request->is('install', 'install/*') || auth()->guard('web')->check() || auth()->guard('admin')->check()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        $csp = $this->buildCsp($nonce);
        if ($csp !== null) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    private function buildCsp(string $nonce): ?string
    {
        $unsafeEval = config('app.env') !== 'production' ? " 'unsafe-eval'" : '';
        $scriptSrc = "'self' 'nonce-{$nonce}'".$unsafeEval.$this->viteDevSources().$this->thirdPartyScriptSources();
        $styleSrc = "'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com".$this->viteDevSources().$this->thirdPartyStyleSources();
        $fontSrc = "'self' data: https://fonts.bunny.net https://fonts.gstatic.com https://fonts.googleapis.com";

        $frameSrc = "'self'".$this->metaFrameSources();

        $directives = array_filter([
            "default-src 'self'",
            'script-src '.$scriptSrc,
            'script-src-elem '.$scriptSrc,
            'style-src '.$styleSrc,
            'style-src-elem '.$styleSrc,
            "img-src 'self' data: https: blob:",
            'font-src '.$fontSrc,
            "connect-src 'self' ".$this->connectSources(),
            'frame-src '.$frameSrc,
            (request()->is('agendar/*') || request()->is('widgets/chat/*')) ? 'frame-ancestors *' : "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "form-action 'self'",
        ]);

        if (app()->environment('production') && request()->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /** Allow Vite dev server (different port) in development so CSP does not block scripts. */
    private function viteDevSources(): string
    {
        if (config('app.env') === 'production') {
            return '';
        }

        // Vite default port 5173. Do not use http://[::1]:5173 — invalid in script-src for Chromium and triggers console noise.
        return ' http://localhost:5173 http://127.0.0.1:5173';
    }

    /** OneSignal (when configured), Meta JS SDK, and Cloudflare Web Analytics / beacon scripts. */
    private function thirdPartyScriptSources(): string
    {
        $extra = ' https://static.cloudflareinsights.com';
        if (filled(config('services.onesignal.app_id'))) {
            // SDK loads from cdn; runtime sync/scripts also come from api.* (see OneSignal v16 CSP docs).
            $extra .= ' https://cdn.onesignal.com https://*.onesignal.com';
        }
        if ($this->metaSdkEnabled()) {
            $extra .= ' https://connect.facebook.net';
        }

        return $extra;
    }

    /** OneSignal injects styles from the apex host (e.g. OneSignalSDK.page.styles.css). */
    private function thirdPartyStyleSources(): string
    {
        if (! filled(config('services.onesignal.app_id'))) {
            return '';
        }

        return ' https://onesignal.com https://*.onesignal.com';
    }

    private function connectSources(): string
    {
        $sources = [];
        if (config('app.env') !== 'production') {
            $sources[] = 'ws:';
            $sources[] = 'wss:';
        }
        $url = config('app.url');
        if ($url) {
            $sources[] = parse_url($url, PHP_URL_HOST) ?: $url;
        }
        if (filled(config('services.onesignal.app_id'))) {
            $sources[] = 'https://onesignal.com';
            $sources[] = 'https://*.onesignal.com';
        }
        if ($this->metaSdkEnabled()) {
            $sources[] = 'https://graph.facebook.com';
            $sources[] = 'https://www.facebook.com';
            $sources[] = 'https://web.facebook.com';
        }

        return implode(' ', array_unique($sources));
    }

    /** Allow Meta Login / Embedded Signup dialogs in iframes when the Meta App is configured. */
    private function metaFrameSources(): string
    {
        if (! $this->metaSdkEnabled()) {
            return '';
        }

        return ' https://www.facebook.com https://web.facebook.com https://connect.facebook.net';
    }

    private function metaSdkEnabled(): bool
    {
        if (request()->is('install', 'install/*')) {
            return false;
        }
        try {
            return filled(CredentialResolver::system()->meta()?->appId());
        } catch (\Throwable) {
            return false;
        }
    }
}
