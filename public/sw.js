// Service Worker for Push Notifications
self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }

    const data = event.data ? event.data.json() : {};
    
    const title = data.title || 'PCCS Notification';
    const options = {
        body: data.body || 'You have a new notification',
        icon: data.icon || '/favicon.ico',
        badge: data.badge || '/favicon.ico',
        tag: data.tag || 'default-tag',
        requireInteraction: data.requireInteraction || false,
        data: data.data || {},
        actions: data.actions || []
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.action === 'unlock') {
        // Open the scanner locks page
        event.waitUntil(
            clients.openWindow(event.notification.data.url || '/manage/scanner-locks')
        );
    } else {
        // Default action: open the URL from notification data or scanner locks page
        const url = event.notification.data.url || '/manage/scanner-locks';
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
                // Check if there's already a window open with this URL
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // If no window is open, open a new one
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
        );
    }
});

self.addEventListener('pushsubscriptionchange', function(event) {
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then(function(subscription) {
                // Send the new subscription to the server
                return fetch('/push-subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(subscription)
                });
            })
    );
});
