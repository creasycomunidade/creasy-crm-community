const activeNotifications = new Set();

export function showSiteNotification(title, options = {}) {
    if (typeof window === 'undefined' || typeof window.Notification === 'undefined') return null;
    if (window.Notification.permission !== 'granted') return null;

    try {
        const notification = new window.Notification(title, {
            icon: '/creasy-community-icon.svg',
            badge: '/creasy-community-icon.svg',
            renotify: false,
            ...options,
        });

        activeNotifications.add(notification);
        notification.addEventListener('close', () => activeNotifications.delete(notification), { once: true });
        return notification;
    } catch {
        return null;
    }
}

export async function clearSiteNotifications() {
    activeNotifications.forEach((notification) => notification.close());
    activeNotifications.clear();

    if (!('serviceWorker' in navigator)) return;

    try {
        const registrations = await navigator.serviceWorker.getRegistrations();
        const notifications = await Promise.all(
            registrations.map((registration) => registration.getNotifications().catch(() => [])),
        );
        notifications.flat().forEach((notification) => notification.close());
    } catch {
        // Some browsers do not expose notifications created by another worker.
    }
}
