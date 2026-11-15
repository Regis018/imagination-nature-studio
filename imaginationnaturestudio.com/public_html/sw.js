/* Imagination Nature Studio - Web Push Service Worker */

self.addEventListener('push', function (event) {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {
            title: 'New Booking · Imagination Nature Studio',
            body: 'You have a new booking.'
        };
    }

    const title = data.title || 'New Booking · Imagination Nature Studio';
    const options = {
        body: data.body || 'You have a new booking.',
        icon: data.icon || '/assets/images/favicon.png',
        badge: data.badge || '/assets/images/favicon.png',
        tag: data.tag || 'ims-new-booking',
        renotify: true,
        requireInteraction: false,
        data: {
            url: data.url || '/admin/bookings.php'
        }
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const targetUrl =
        (event.notification.data && event.notification.data.url)
            ? event.notification.data.url
            : '/admin/bookings.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (clientList) {
                for (const client of clientList) {
                    if ('focus' in client) {
                        client.navigate(targetUrl);
                        return client.focus();
                    }
                }

                if (clients.openWindow) {
                    return clients.openWindow(targetUrl);
                }
            })
    );
});
