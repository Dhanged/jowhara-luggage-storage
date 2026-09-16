const CACHE_NAME = 'jls-v3';
const STATIC_ASSETS = [
  '/luggage_storage/assets/app.css',
  '/luggage_storage/offline.html',
  '/luggage_storage/manifest.json',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.protocol === 'chrome-extension:') return;

  if (/\.(css|js|woff2?|ttf|png|jpg|jpeg|gif|svg|ico)(\?.*)?$/.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then(cached => cached || fetch(request).then(resp => {
        if (resp.ok) { const clone = resp.clone(); caches.open(CACHE_NAME).then(c => c.put(request, clone)); }
        return resp;
      }))
    );
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/luggage_storage/offline.html')));
    return;
  }

  event.respondWith(fetch(request).catch(() => caches.match(request)));
});
