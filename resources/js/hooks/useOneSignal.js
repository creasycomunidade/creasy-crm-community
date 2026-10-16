import { useEffect, useRef } from 'react';
import { showSiteNotification } from '@/Utils/browserNotifications';

/**
 * Requests push-notification permission via the OneSignal SDK that is already
 * loaded and initialised by app.blade.php. Does NOT load or init the SDK again.
 */
export function useOneSignal({ appId, enabled = true }) {
    const asked = useRef(false);

    useEffect(() => {
        if (!enabled || !appId || asked.current) return;
        asked.current = true;

        // Blade already initialised the SDK. We only need to prompt for
        // permission if the user hasn't been asked yet.
        const requestPermission = async () => {
            if (typeof Notification === 'undefined') return;
            if (Notification.permission !== 'default') return; // already granted or denied

            try {
                // OneSignal v16: use SDK helper so the prompt is attributed to the app.
                if (window.OneSignal?.Notifications?.requestPermission) {
                    const granted = await window.OneSignal.Notifications.requestPermission();
                    // Blade-level osLogin() handles the login on permissionChange,
                    // but call it explicitly here as a safety net.
                    if (granted && window.osLogin) window.osLogin();
                } else if (Array.isArray(window.OneSignalDeferred)) {
                    window.OneSignalDeferred.push(async (OneSignal) => {
                        const granted = await OneSignal.Notifications.requestPermission().catch(() => false);
                        if (granted && window.osLogin) window.osLogin();
                    });
                } else {
                    Notification.requestPermission().catch(() => {});
                }
            } catch {
                // Silently ignore if the SDK isn't ready yet
            }
        };

        // Delay slightly so the SDK has time to finish its own init queue
        const t = setTimeout(requestPermission, 1500);
        return () => clearTimeout(t);
    }, [appId, enabled]);
}

/**
 * Returns true when the user is currently on the inbox or dashboard page.
 */
export function isOnInboxOrDashboard() {
    try {
        return (
            route().current('client.dashboard') ||
            route().current('client.inbox.*')
        );
    } catch {
        const p = window.location.pathname;
        return p.includes('/inbox') || p.endsWith('/dashboard');
    }
}

/**
 * Shows an OS-level browser notification.
 * Works as long as the user has granted push permission (via OneSignal or natively).
 */
export function showBrowserNotification(title, body, url, icon = '/creasy-community-icon.svg') {
    const notification = showSiteNotification(title, { body, icon, tag: url || title });
    if (notification && url) {
        notification.onclick = () => {
            window.focus();
            window.location.href = url;
            notification.close();
        };
    }
}
