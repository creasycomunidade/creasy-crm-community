<?php

use App\Http\Controllers\Admin\Auth\AdminLoginController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Install\InstallController;
use App\Http\Middleware\BroadcastingAuthDebug;
use App\Http\Middleware\CheckApiAbility;
use App\Http\Middleware\EnforceLimit;
use App\Http\Middleware\EnsurePlanModuleEnabled;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureClientScope;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotDemoMode;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfAdminAuthenticated;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Web setup wizard (guest). Reachable on a fresh deploy until the app
            // is marked installed; EnsureInstalled redirects everything else here.
            Route::middleware(['web'])
                ->prefix('install')
                ->name('install.')
                ->group(function () {
                    Route::get('/', [InstallController::class, 'show'])->name('show');
                    Route::post('test-database', [InstallController::class, 'testDatabase'])->name('test-database');
                    Route::post('/', [InstallController::class, 'run'])->name('run');
                });

            // Webhook intake routes (no CSRF, no auth – signature-verified inside controllers)
            Route::middleware(['web'])
                ->group(base_path('routes/webhooks.php'));

            Route::middleware(['web', 'auth', 'role:client', 'client.scope', 'demo'])
                ->prefix('painel')
                ->name('client.')
                ->group(base_path('routes/client.php'));

            // Reports & CSV exports
            Route::middleware(['auth', 'role:client', 'client.scope'])
                ->group(base_path('routes/reports.php'));

            // Admin sign-in is unified onto the main /login page (the
            // AuthenticatedSessionController tries the admin guard first). We
            // keep the `admin.login` route name so the many existing redirects
            // to it (RequirePermission, install flow, admin logout, the
            // auth-exception handler) still resolve — it now just forwards to
            // /login. `redirect.if.admin` still bounces an already-signed-in
            // admin to the dashboard.
            Route::middleware(['web', 'redirect.if.admin'])
                ->get('admin/login', fn () => redirect()->route('login'))
                ->name('admin.login');

            // Admin logout (authenticated admin only)
            Route::post('admin/logout', [AdminLoginController::class, 'destroy'])
                ->middleware(['web', 'auth:admin'])
                ->name('admin.logout');

            // Impersonation stop: callable by impersonated user (web guard), no admin auth required
            Route::post('admin/impersonation/stop', [ImpersonationController::class, 'stop'])
                ->middleware(['web', 'auth'])
                ->name('admin.impersonation.stop');

            // Admin panel (authenticated admin only; RBAC applied per-route).
            Route::middleware(['web', 'auth:admin', 'demo'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\EnsureCommunityEdition::class);
        $middleware->web(append: [
            // Runs before the DB-querying middleware below so a fresh deploy is
            // redirected to /install without touching the (empty) database.
            EnsureInstalled::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            SecureHeaders::class,
            RequestIdMiddleware::class,
            \App\Http\Middleware\DeliverCommunityRegistration::class,
            // Diagnostics for Pusher's auth POST. Read-only; always passes.
            BroadcastingAuthDebug::class,
        ]);
        $middleware->alias([
            'demo' => EnsureNotDemoMode::class,
            'admin' => EnsureAdminRole::class,
            'admin.super' => EnsureSuperAdmin::class,
            'role' => EnsureUserRole::class,
            'permission' => RequirePermission::class,
            'redirect.if.admin' => RedirectIfAdminAuthenticated::class,
            'client.scope' => EnsureClientScope::class,
            'limit' => EnforceLimit::class,
            'module' => EnsurePlanModuleEnabled::class,
            'api.ability' => CheckApiAbility::class,
        ]);
        // Shared middleware stack for all client module routes (mirrors routes/client.php).
        $middleware->appendToGroup('client-app', [
            'auth',
            'verified',
            'role:client',
            EnsureClientScope::class,
            EnsureNotDemoMode::class,
        ]);
        // Trust forwarded headers only when explicit reverse-proxy addresses
        // are configured. A directly exposed single server should leave this empty.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
            'webhooks/paddle',
            'webhooks/whatsapp/*',
            'webhooks/meta/*',
            'webhooks/telegram/*',
            'webhooks/evolution-go/*',
            'webhooks/sms/*',
            'webhooks/automation/*',
            'webhooks/ecommerce/*',
            'webhooks/zernio',
            'rastreio/*',
            'agendar/*',
            'widgets/chat/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['api_key', 'webhook_token', 'token', 'header_value', 'install_token', 'db_password', 'admin_password', 'admin_password_confirmation']);
        $exceptions->reportable(function (Throwable $e) {
            Log::channel('errors')->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });

        // Optional Sentry integration — only active when SENTRY_LARAVEL_DSN is set
        if (config('sentry.dsn')) {
            $exceptions->reportable(function (Throwable $e) {
                if (function_exists('\Sentry\configureScope')) {
                    \Sentry\configureScope(function (Scope $scope) {
                        $user = auth()->user();
                        if ($user) {
                            $scope->setTag('workspace_id', (string) ($user->current_workspace_id ?? $user->workspace_id ?? 'unknown'));
                            $scope->setUser(['id' => $user->id, 'email' => $user->email]);
                        }
                    });
                }
                Integration::captureUnhandledException($e);
            });
        }

        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if (in_array('admin', $e->guards(), true)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Unauthenticated.'], 401)
                    : redirect()->route('admin.login');
            }

            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        });
    })->create();
