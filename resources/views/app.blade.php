<!DOCTYPE html>
@php
    $locale = request()->attributes->get('active_locale', app()->getLocale());
    $htmlDir = request()->attributes->get('is_rtl', false) ? 'rtl' : 'ltr';
@endphp
@php
    // Guarded: the database may be unavailable during first-run install.
    try {
        $serverTheme = request()->is('install', 'install/*') ? null : auth()->user()?->theme;
    } catch (\Throwable) {
        $serverTheme = null;
    }
@endphp
<html lang="{{ $locale === 'pt' ? 'pt-BR' : str_replace('_', '-', $locale) }}" dir="{{ $htmlDir }}">
    <head>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function() {
                var server = @json($serverTheme);
                var stored = localStorage.getItem('theme');
                var pref = (stored === 'light' || stored === 'dark') ? stored : ((server === 'light' || server === 'dark') ? server : 'light');
                document.documentElement.classList.toggle('dark', pref === 'dark');
            })();
        </script>
        <meta charset="utf-8">
        <meta name="author" content="{{ config('app.developer') }}">
        <meta name="generator" content="{{ config('app.developer') }} — CREASY CRM">
        <link rel="author" href="/humans.txt">
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => config('app.name', 'CREASY CRM'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'creator' => ['@type' => 'Organization', 'name' => config('app.developer')]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="csp-nonce" content="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        <meta name="vapid-public-key" content="{{ config('webpush.vapid_public_key') }}">

        <title inertia>{{ config('app.name', 'CREASY CRM') }}</title>
        @php
            try {
                $faviconPath = request()->is('install', 'install/*') ? null : \App\Models\SystemSetting::get('app_favicon_path');
                // Honor the disk the favicon was actually saved to (may be a cloud
                // provider). Hardcoding 'public' produced a wrong/404 URL whenever
                // the active storage disk was not the local public one.
                $faviconDisk = request()->is('install', 'install/*') ? 'public' : \App\Models\SystemSetting::get('app_favicon_disk', 'public');
                if ($faviconPath) {
                    app(\App\Services\StorageManager::class)->ensureDiskReady($faviconDisk);
                    $faviconUrl = \Illuminate\Support\Facades\Storage::disk($faviconDisk)->url($faviconPath);
                } else {
                    $faviconUrl = null;
                }
            } catch (\Throwable) {
                $faviconUrl = null;
            }
        @endphp
        @if($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}">
            <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        @else
            {{-- CREASY Community default mark. Custom uploads retain precedence. --}}
            <link rel="icon" type="image/svg+xml" href="/creasy-community-icon.svg">
            <link rel="apple-touch-icon" href="/creasy-community-icon.svg">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&display=swap" rel="stylesheet" />
        {{-- Anek Bangla for Bengali script. The Bengali glyph files are lazy-loaded
             by unicode-range, so they're only fetched when bn text actually renders
             (i.e. html[lang="bn"]); keeping the link unconditional means a client-side
             locale switch picks it up without a full page reload. --}}
        <link href="https://fonts.bunny.net/css?family=anek-bangla:400,500,600,700&display=swap" rel="stylesheet" />

        @if(!request()->is('install', 'install/*') && config('services.onesignal.app_id'))
        <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            window.OneSignalDeferred = window.OneSignalDeferred || [];

            OneSignalDeferred.push(async function (OneSignal) {
                try {
                    await OneSignal.init({
                        appId: "{{ config('services.onesignal.app_id') }}",
                        notifyButton: { enable: false },
                        allowLocalhostAsSecureOrigin: {{ app()->environment('local') ? 'true' : 'false' }},
                    });
                } catch (e) {
                    console.warn('[onesignal] init failed — push notifications disabled:', e?.message ?? e);
                    return;
                }

                // If permission is granted but the subscription has no token, the local
                // OneSignal state is stale (leftover from a previous broken registration).
                // Opt-out and back in to force a fresh SW subscription.
                if (Notification.permission === 'granted') {
                    try {
                        var sub = OneSignal.User?.PushSubscription;
                        if (sub && !sub.token && sub.optedIn) {
                            await sub.optOut();
                            await sub.optIn();
                        }
                    } catch (_) {}
                }

                // Suppress push notification when the user is actively viewing the inbox
                // (Echo already shows the message in real-time there).
                // On every other page the notification is shown as normal.
                try {
                    OneSignal.Notifications.addEventListener('foregroundWillDisplay', function(event) {
                        var p = window.location.pathname;
                        if (p.includes('/inbox')) {
                            event.preventDefault(); // user can see the message live — no popup needed
                        }
                        // else: let OneSignal display the notification
                    });
                } catch(_) {}

                @auth
                // Only login once we have a real push subscription (non-empty token).
                // Calling login() with an empty token causes a 400 from OneSignal.
                var _osUserId = "{{ auth()->id() }}";

                async function osLogin() {
                    try {
                        var sub = OneSignal.User?.PushSubscription;
                        var token = sub?.token;
                        var subId  = sub?.id;
                        // A "local-" prefixed ID means the subscription hasn't been
                        // confirmed by OneSignal's server yet; calling login() in that
                        // state returns 400 "No aliases found".
                        if (!token || (subId && String(subId).startsWith('local-'))) return;
                        await OneSignal.login(_osUserId);
                    } catch (e) {
                        console.warn('[onesignal] login failed:', e?.message ?? e);
                    }
                }
                window.osLogin = osLogin;

                // If permission is already granted, wait for the subscription token
                // to be populated before attempting login.
                if (Notification.permission === 'granted') {
                    var token = OneSignal.User?.PushSubscription?.token;
                    if (token) {
                        osLogin();
                    } else {
                        // Token arrives asynchronously — wait for the subscription change event
                        try {
                            OneSignal.User.PushSubscription.addEventListener('change', function handler(e) {
                                var cur = e.current;
                                if (cur?.token && !(cur?.id && String(cur.id).startsWith('local-'))) {
                                    OneSignal.User.PushSubscription.removeEventListener('change', handler);
                                    osLogin();
                                }
                            });
                        } catch (_) {}
                    }
                }

                // Login when the user grants permission later (e.g. after our prompt).
                try {
                    OneSignal.Notifications.addEventListener('permissionChange', function (granted) {
                        if (granted) {
                            // Give the SW subscription a moment to generate a token
                            setTimeout(osLogin, 1000);
                        }
                    });
                } catch (_) {}
                @endauth
            });

            // Suppress any unhandled SDK rejections so they don't pollute the console.
            window.addEventListener('unhandledrejection', function (ev) {
                var stack = String(ev.reason?.stack ?? ev.reason ?? '');
                if (stack.includes('OneSignal') || stack.includes('onesignal')) ev.preventDefault();
            });
        </script>
        @endif

        <!-- Facebook JS SDK — loaded eagerly when Meta App is configured -->
        <div id="fb-root"></div>
        @php
            // Guarded: integration_configs may be unreadable during first-run install.
            try {
                $metaAppId = request()->is('install', 'install/*') ? null : \App\Modules\Integrations\Services\CredentialResolver::system()->meta()?->appId();
            } catch (\Throwable) {
                $metaAppId = null;
            }
        @endphp
        @if($metaAppId)
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            window.fbAsyncInit = function() {
                FB.init({
                    appId: '{{ e($metaAppId) }}',
                    autoLogAppEvents: true,
                    xfbml: false,
                    version: 'v20.0',
                });
                window.__fbSdkReady = true;
            };
        </script>
        <script async defer crossorigin="anonymous"
            src="https://connect.facebook.net/en_US/sdk.js"></script>
        @endif

        <!-- Scripts -->
        @routes(nonce: \Illuminate\Support\Facades\Vite::cspNonce())
        @viteReactRefresh
        @vite(['resources/js/app.jsx', 'resources/js/Pages/' . (isset($page['component']) ? $page['component'] : 'Dashboard') . '.jsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
