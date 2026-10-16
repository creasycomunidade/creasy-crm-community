import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function readInertiaProps() {
    try {
        const el = document.getElementById('app');
        if (el?.dataset?.page) {
            const page = JSON.parse(el.dataset.page);
            window.__INERTIA_PAGE_PROPS__ = page.props ?? {};
        }
    } catch {
        // The page may not be hydrated yet; the environment fallback is used.
    }
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function initEcho() {
    if (window.Echo) return; // already initialised

    readInertiaProps();

    // Pusher config is injected by the server via Inertia shared props
    const pusherConfig = window.__INERTIA_PAGE_PROPS__?.pusher
        ?? window.__pusherConfig__
        ?? {};

    const key     = pusherConfig.key     || import.meta.env.VITE_PUSHER_APP_KEY     || '';
    const cluster = pusherConfig.cluster || import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
    const enabled = pusherConfig.enabled !== undefined ? pusherConfig.enabled : !!key;

    if (!key || !enabled) {
        // eslint-disable-next-line no-console
        console.warn('[echo] Pusher disabled or key missing — real-time disabled.', { enabled, hasKey: !!key });
        return;
    }

    const csrf = getCsrfToken();
    if (!csrf) {
        // eslint-disable-next-line no-console
        console.warn('[echo] CSRF meta tag missing — broadcasting/auth will likely fail.');
    }

    window.Echo = new Echo({
        broadcaster:       'pusher',
        key,
        cluster,
        forceTLS:          true,
        disableStats:      true,
        enabledTransports: ['ws', 'wss'],
        // Explicitly use absolute auth endpoint + send CSRF + cookies so the
        // session is always present on POST /broadcasting/auth.
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        },
        // Pusher uses an XHR for the auth call; ensure cookies are sent.
        authTransport: 'ajax',
    });

    // Log auth failures so we can see which channel + what status.
    window.Echo.connector.pusher.connection.bind('error', (err) => {
        // eslint-disable-next-line no-console
        console.warn('[echo] pusher connection error', err);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEcho, { once: true });
} else {
    initEcho();
}
