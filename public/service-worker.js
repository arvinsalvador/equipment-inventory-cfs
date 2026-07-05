const CACHE_NAME = 'ai-equipment-pwa-v6';
const SHELL_ASSETS = [
    '/manifest.webmanifest',
    '/pwa.css',
    '/pwa.js',
    '/icons/pwa-icon.svg',
    '/icons/pwa-maskable.svg',
];

const NEVER_INTERCEPT_PATHS = [
    '/admin',
    '/filament',
    '/livewire',
    '/login',
    '/logout',
    '/browser-push',
];

const STATIC_EXTENSIONS = [
    '.css',
    '.js',
    '.svg',
    '.png',
    '.jpg',
    '.jpeg',
    '.webp',
    '.ico',
    '.woff',
    '.woff2',
];

const isBlockedPath = (pathname) => NEVER_INTERCEPT_PATHS
    .some((path) => pathname === path || pathname.startsWith(`${path}/`));

const isShellAsset = (pathname) => SHELL_ASSETS.includes(pathname) || pathname.startsWith('/icons/');

const isStaticAsset = (pathname) => STATIC_EXTENSIONS.some((extension) => pathname.endsWith(extension));

const isHtmlNavigationRequest = (request) => request.mode === 'navigate'
    || (request.headers.get('accept') || '').includes('text/html');

const networkFirst = (request) => fetch(request, {
    cache: 'no-store',
    credentials: 'include',
});

const cacheFirst = (request) => caches.match(request).then((cached) => cached || fetch(request).then((response) => {
    if (! response.ok) {
        return response;
    }

    const copy = response.clone();
    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));

    return response;
}));

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin || request.method !== 'GET') {
        return;
    }

    if (isHtmlNavigationRequest(request)) {
        event.respondWith(networkFirst(request));

        return;
    }

    if (isBlockedPath(url.pathname)) {
        event.respondWith(networkFirst(request));

        return;
    }

    if (isShellAsset(url.pathname) || isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
    }
});
