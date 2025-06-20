// Service Worker Cleanup - Remove corrupted cache
self.addEventListener('install', function(event) {
    console.log('SW: Installing and cleaning up...');
    // Skip waiting to activate immediately
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('SW: Activating and clearing cache...');
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    console.log('SW: Deleting cache:', cacheName);
                    return caches.delete(cacheName);
                })
            );
        }).then(function() {
            console.log('SW: Cache cleared, unregistering...');
            return self.registration.unregister();
        })
    );
});

// Don't cache anything during this cleanup
self.addEventListener('fetch', function(event) {
    // Just pass through to network
    event.respondWith(fetch(event.request));
}); 