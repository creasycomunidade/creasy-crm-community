<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a fresh deploy through the web setup wizard. Until the app is marked
 * installed (APP_INSTALLED=true), every request is redirected to /install —
 * except the installer routes themselves. Once installed, this is a no-op.
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.installed') || is_file(storage_path('installed.lock'))) {
            return $next($request);
        }

        // Let the installer (and its POST endpoints) through.
        if ($request->is('install', 'install/*')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
