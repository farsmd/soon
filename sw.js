/* Service Worker — linerlight CMS (public site only)
 * Strategy:
 *  - Static assets (css/js/fonts/images, incl. versioned style.php): cache-first, network fallback
 *  - HTML navigations: network-first, offline fallback to /offline.html
 *  - NEVER intercepts: admin.php, api.php, setup.php, sitemap.php, non-GET, cross-origin
 * Bump CACHE_VERSION on each release that changes cached assets.
 */
const CACHE_VERSION = 'linerlight-v1';
const OFFLINE_URL = '/offline.html';
const NEVER_CACHE = /(^|\/)(admin|api|setup|sitemap)\.php/i;
const STATIC_RE = /\.(?:css|js|mjs|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|svg|ico)(\?|$)/i;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) =>
        Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
      )
      .then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  // style.php is versioned via ?v=... so cache-first is safe
  if (/\/style\.php(\?|$)/i.test(url.pathname)) return true;
  return STATIC_RE.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  let url;
  try { url = new URL(req.url); } catch (e) { return; }
  if (url.origin !== self.location.origin) return; // cross-origin: leave alone
  if (NEVER_CACHE.test(url.pathname)) return;      // admin/api/setup/sitemap: always network

  // 1) Static assets: cache-first
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(req, { ignoreSearch: false }).then((cached) => {
        if (cached) return cached;
        return fetch(req).then((res) => {
          // only cache successful, basic (same-origin) responses
          if (res && res.ok && res.type === 'basic') {
            const copy = res.clone();
            caches.open(CACHE_VERSION).then((cache) => cache.put(req, copy)).catch(() => {});
          }
          return res;
        });
      })
    );
    return;
  }

  // 2) Navigations / HTML pages: network-first, offline fallback
  //    (navigations are never written to cache — only the offline page is served)
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // 3) Everything else same-origin GET (e.g. JSON): pass through untouched
});
