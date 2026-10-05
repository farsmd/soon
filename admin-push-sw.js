/* Service Worker — پوش نوتیفیکیشن پنل مدیریت (۹٫۲۵) */
self.addEventListener('push', function(event) {
    var data = { title: 'لاینرلایت', body: 'اعلان تازه', url: 'admin.php?page=notifications' };
    try {
        if (event.data) { data = Object.assign(data, event.data.json()); }
    } catch (e) {}
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: 'assets/icon-192.png',
            badge: 'assets/icon-192.png',
            dir: 'rtl',
            lang: 'fa',
            data: { url: data.url }
        })
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || 'admin.php?page=notifications';
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(function(list) {
            for (var i = 0; i < list.length; i++) {
                if (list[i].url.indexOf('admin.php') !== -1) {
                    list[i].navigate(url);
                    return list[i].focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
