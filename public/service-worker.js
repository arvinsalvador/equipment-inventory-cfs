const CACHE_NAME = 'ai-equipment-pwa-v7';
const SHELL_ASSETS = [
    '/manifest.webmanifest',
    '/pwa.css',
    '/pwa.js',
    '/browser-push.js',
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
    '/push-subscriptions',
];

const DEFAULT_NOTIFICATION_URL = '/admin/mobile-technician-dashboard';

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

self.addEventListener('push', (event) => {
    let payload = {};

    if (event.data) {
        try {
            payload = event.data.json();
        } catch (error) {
            payload = { body: event.data.text() };
        }
    }

    const title = payload.title || 'SEIMS Notification';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/pwa-icon.svg',
        badge: payload.badge || '/icons/pwa-maskable.svg',
        tag: payload.tag || 'seims-browser-push',
        data: {
            url: payload.data?.url || payload.url || DEFAULT_NOTIFICATION_URL,
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = new URL(event.notification.data?.url || DEFAULT_NOTIFICATION_URL, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            const matchingClient = clientList.find((client) => client.url.startsWith(self.location.origin));

            if (matchingClient) {
                if ('navigate' in matchingClient) {
                    matchingClient.navigate(targetUrl);
                }

                return matchingClient.focus();
            }

            return clients.openWindow(targetUrl);
        }),
    );
});
