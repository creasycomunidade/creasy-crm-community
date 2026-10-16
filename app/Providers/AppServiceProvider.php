<?php

namespace App\Providers;

use App\Events\AutomationFailed;
use App\Events\AutomationWebhookReceived;
use App\Events\CampaignCompleted;
use App\Events\CommerceEventReceived;
use App\Events\ContactCreated;
use App\Events\ConversationAssigned;
use App\Events\MessageReceived;
use App\Listeners\AutomationTriggerListener;
use App\Listeners\AutoReplyListener;
use App\Listeners\DispatchOutboundWebhookListener;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\SendAutomationFailedNotification;
use App\Listeners\SendCampaignCompletedNotification;
use App\Listeners\SendConversationAssignedNotification;
use App\Listeners\SendNewMessageNotification;
use App\Listeners\SendWelcomeNotification;
use App\Models\Workspace;
use App\Models\SmtpConfiguration;
use App\Modules\Shared\Services\ChannelManager;
use App\Services\StorageManager;
use App\Services\Mail\MailService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // On a fresh deploy the database (and its sessions/cache/queue tables)
        // does not exist yet, which would otherwise break the installer's own
        // session + CSRF. Until the app is installed, fall back to filesystem
        // drivers so the setup wizard works against an empty database.
        if (! config('app.installed')) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'array',
                'queue.default' => 'sync',
            ]);
        }

        $this->app->singleton(StorageManager::class);
        $this->app->singleton(ChannelManager::class, fn () => new ChannelManager);
    }

    public function boot(): void
    {
        $this->configureHttpClientSsl();
        $this->forceHttpsForWebhookUrls();
        $this->configureSystemMailer();

        Gate::define('viewAdmin', fn ($user) => $user?->isAdmin());
        Gate::define('manageAdminSensitive', fn ($user) => $user?->isAdmin());

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Registered::class, SendWelcomeNotification::class);

        Event::listen(MessageReceived::class, [AutoReplyListener::class, 'handle']);
        Event::listen(ContactCreated::class, [DispatchOutboundWebhookListener::class, 'handleContactCreated']);
        Event::listen(MessageReceived::class, [DispatchOutboundWebhookListener::class, 'handleMessageReceived']);
        Event::listen(AutomationFailed::class, SendAutomationFailedNotification::class);
        Event::listen(ContactCreated::class, [AutomationTriggerListener::class, 'handleContactCreated']);
        Event::listen(MessageReceived::class, [AutomationTriggerListener::class, 'handleMessageReceived']);
        Event::listen(AutomationWebhookReceived::class, [AutomationTriggerListener::class, 'handleAutomationWebhookReceived']);

        // ── Outbound webhook event delivery ─────────────────────────────────

        // ── Notification bridging listeners ──────────────────────────────────
        Event::listen(MessageReceived::class, SendNewMessageNotification::class);
        Event::listen(ConversationAssigned::class, SendConversationAssignedNotification::class);

        // ── Subscription & billing notifications ────────────────────────────

        // ── Named rate limiters ─────────────────────────────────────────────
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            // Use the real client IP (respects X-Forwarded-For when trusted proxies are set).
            // Limit is intentionally high: a single Meta app services multiple workspaces and
            // all their traffic arrives from a small pool of Meta egress IPs.
            return Limit::perMinute(1000)->by($request->getClientIp());
        });


        // ── Scramble / OpenAPI ──────────────────────────────────────────────
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        // Only include /api/v1/* routes in the spec
        Scramble::routes(function (Route $route) {
            return str_starts_with($route->uri(), 'api/v1');
        });

        Vite::prefetch(concurrency: 3);
    }

    /**
     * Route every framework notification through the SMTP configured in the
     * admin panel. Without this, MailMessage notifications silently use the
     * default "log" transport and never reach the recipient.
     */
    private function configureSystemMailer(): void
    {
        try {
            if (! config('app.installed') || ! Schema::hasTable('smtp_configurations')) {
                return;
            }

            $smtp = SmtpConfiguration::getActive();
            if (! $smtp) {
                return;
            }

            app(MailService::class)->configureMailer($smtp);
            config(['mail.default' => 'dynamic_smtp']);
        } catch (\Throwable $error) {
            report($error);
        }
    }

    /**
     * Point Guzzle at a valid CA bundle when php.ini references a missing file
     * (Windows cURL error 77), or optionally disable verify for local dev only.
     */
    /** Meta webhook callbacks must use HTTPS in production. */
    private function forceHttpsForWebhookUrls(): void
    {
        $appUrl = config('app.url');
        if (
            app()->environment('production')
            && is_string($appUrl)
            && str_starts_with($appUrl, 'https://')
        ) {
            URL::forceScheme('https');
        }
    }

    private function configureHttpClientSsl(): void
    {
        $caPath = config('http.ca_path');

        if (is_string($caPath) && $caPath !== '' && is_file($caPath)) {
            Http::globalOptions(['verify' => $caPath]);

            return;
        }

        // Laragon / Windows PHP builds: cacert.pem next to php.exe (php.ini may still point elsewhere).
        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            $phpDirBundle = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'
                .DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'cacert.pem';
            if (is_file($phpDirBundle)) {
                Http::globalOptions(['verify' => $phpDirBundle]);

                return;
            }
        }

        if (config('http.verify_ssl') === false && app()->environment('local', 'testing')) {
            Http::globalOptions(['verify' => false]);
        }
    }
}
