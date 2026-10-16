importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');

// When the user clicks a push notification: focus an already-open tab for the
// target URL instead of always opening a new window.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = event.notification.data?.url
        || event.notification.data?.launchURL
        || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Find a tab that is already showing the target URL
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Otherwise, find any open tab for this origin and navigate it
            for (var j = 0; j < clientList.length; j++) {
                var c = clientList[j];
                if ('navigate' in c && 'focus' in c) {
                    return c.navigate(url).then(function (nc) { return nc ? nc.focus() : null; });
                }
            }
            // Fallback: open a new window
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
}, true); // capture=true so our handler runs before OneSignal's default
