// Mijn kookboek — minimal PWA service worker.
// Goal: enable Add to Home Screen + offline shell on iOS Safari & Android Chrome.
const CACHE_VERSION = 'kookboek-v2';
const APP_SHELL = [
    '/icon.svg',
    '/icon-maskable.svg',
    '/icon-192.png',
    '/icon-512.png',
    '/icon-512-maskable.png',
    '/apple-touch-icon.png',
    '/favicon.svg',
    '/favicon.ico',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(APP_SHELL)).catch(() => null)
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// Strategy:
// - Same-origin GET navigations / static assets: network-first, fall back to cache.
// - Cross-origin or non-GET: pass through to the network.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Don't cache Inertia/JSON responses or auth-sensitive endpoints.
    if (url.pathname.startsWith('/cook/') || url.pathname.startsWith('/grocery/')) return;
    if (request.headers.get('X-Inertia')) return;

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response && response.status === 200 && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy)).catch(() => null);
                }
                return response;
            })
            .catch(() => caches.match(request).then((cached) => cached || caches.match('/')))
    );
});
