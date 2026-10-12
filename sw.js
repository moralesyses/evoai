/* Service worker: caches static assets for faster loads and offline shell support.
   API calls (POST api.php) are never cached. */
const CACHE = 'evoai-static-v1';
const STATIC = ['icon.svg', 'manifest.webmanifest', 'assets/css/app.css', 'assets/css/custom.css', 'assets/js/app.js', 'assets/js/icons.js', 'assets/js/crypto.js'];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).catch(() => {}));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || url.pathname.endsWith('api.php')) return;
  // Pages: network first, fall back to the cached copy when offline.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match('./')))
    );
    return;
  }
  // Static assets: cache first, refresh in the background.
  if (/\.(css|js|svg|png|jpg|jpeg|webp|woff2?)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const network = fetch(req)
          .then((res) => {
            if (res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
            return res;
          })
          .catch(() => cached);
        return cached || network;
      })
    );
  }
});
