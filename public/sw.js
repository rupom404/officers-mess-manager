self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// Keep a fetch handler so Chromium can recognize the service worker as part
// of an installable app, while still sending every request to the live server.
// No caching is performed, so authenticated and financial data stay fresh.
self.addEventListener('fetch', function (event) {
    event.respondWith(fetch(event.request));
});
