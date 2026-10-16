/**
 * push.js — registers the service worker and subscribes the user to Web Push.
 * Call subscribeToPush() from the notification settings page after user opts in.
 */

// Read the VAPID public key at runtime from the meta tag the server renders
// (config('webpush.vapid_public_key')). This keeps it in sync with the server's
// signing key and avoids a build-time VITE_ var that must be rebuilt on key
// changes. Falls back to the build-time env for backward compatibility.
function getVapidPublicKey() {
    const fromMeta = document.querySelector('meta[name="vapid-public-key"]')?.content?.trim();
    return fromMeta || (import.meta.env.VITE_VAPID_PUBLIC_KEY ?? '');
}

// Byte-compare the applicationServerKey on an existing subscription (an
// ArrayBuffer) against the key we intend to use (a Uint8Array). A missing
// existing key counts as a mismatch so we resubscribe.
function keysMatch(existing, desired) {
    if (! existing) return false;
    const a = new Uint8Array(existing);
    if (a.length !== desired.length) return false;
    for (let i = 0; i < a.length; i++) {
        if (a[i] !== desired[i]) return false;
    }
    return true;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export async function subscribeToPush() {
    if (! ('serviceWorker' in navigator) || ! ('PushManager' in window)) {
        console.warn('Web Push not supported');
        return null;
    }

    const vapidPublicKey = getVapidPublicKey();
    if (! vapidPublicKey) {
        console.warn('Web Push not configured: missing VAPID public key');
        return null;
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        return null;
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);

    // The Push API allows only one subscription per service worker and refuses
    // to change its key in place. If a stale subscription exists with a
    // different key (e.g. from a previous/rotated VAPID key), drop it first so
    // we can resubscribe with the current one.
    let subscription = await registration.pushManager.getSubscription();
    if (subscription && ! keysMatch(subscription.options?.applicationServerKey, applicationServerKey)) {
        await subscription.unsubscribe();
        subscription = null;
    }

    if (! subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey,
        });
    }

    const sub = subscription.toJSON();

    await fetch(route('client.push.subscribe'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            endpoint:  sub.endpoint,
            p256dh:    sub.keys?.p256dh,
            auth:      sub.keys?.auth,
            ua:        navigator.userAgent,
        }),
    });

    return subscription;
}

export async function unsubscribeFromPush() {
    if (! ('serviceWorker' in navigator)) return;

    const registration = await navigator.serviceWorker.getRegistration('/sw.js');
    if (! registration) return;

    const subscription = await registration.pushManager.getSubscription();
    if (! subscription) return;

    await fetch(route('client.push.unsubscribe'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ endpoint: subscription.endpoint }),
    });

    await subscription.unsubscribe();
}
