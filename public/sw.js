self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// Intentionally do not intercept fetches or cache authenticated pages/data.
// Financial information should always be read from the live application.
