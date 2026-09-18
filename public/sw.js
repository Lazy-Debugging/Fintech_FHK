const CACHE_NAME = 'fhk-kiosk-v3';
const appUrl = (path) => new URL(path, self.registration.scope).href;
const KIOSK_HOME = appUrl('./kiosk?kiosk_id=FHK-JAKARTA-01');
const OFFLINE_PAGE = appUrl('./offline');
const MANIFEST = appUrl('./manifest.webmanifest');

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll([
      KIOSK_HOME,
      OFFLINE_PAGE,
      MANIFEST,
    ]))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Payment, API, and administrative data must always be live.
  if (event.request.destination === 'document' || url.pathname.includes('/api/') || url.pathname.includes('/admin') || url.pathname.includes('/callback')) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(OFFLINE_PAGE))
    );
    return;
  }

  if (url.origin !== self.location.origin) return;

  event.respondWith(
    caches.match(event.request).then((cachedResponse) => {
      const networkResponse = fetch(event.request).then((response) => {
        if (response.ok) {
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, response.clone()));
        }
        return response;
      });

      return cachedResponse || networkResponse.catch(() => Response.error());
    })
  );
});
