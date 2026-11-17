// sw.js — Service Worker POSSIBILITY TECH PWA
const CACHE_NAME = 'possibilitytech-v1';
const STATIC_ASSETS = [
    '/gestion-boutique/',
    '/gestion-boutique/login.php',
    '/gestion-boutique/assets/images/logo_icon.png',
    '/gestion-boutique/assets/images/logo.png',
    '/gestion-boutique/offline.html'
];

// Install
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
});

// Activate
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => clients.claim())
    );
});

// Fetch — Network first, cache fallback
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    if (!event.request.url.includes('/gestion-boutique/')) return;

    event.respondWith(
        fetch(event.request)
            .then(response => {
                if (response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(() => {
                return caches.match(event.request)
                    .then(cached => cached || caches.match('/gestion-boutique/offline.html'));
            })
    );
});
