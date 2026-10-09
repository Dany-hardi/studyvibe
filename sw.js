/*
 * StudyVibe service worker.
 *
 * What it does: keeps the static files (styles, scripts, images, fonts) so pages open fast on a weak connection, and shows
 * a friendly offline page when the network is gone while a page is being opened.
 *
 * What it never does: touch /api/, any POST, any download or export, or any page that depends on who is signed in. Pages
 * always come from the network first, so a student never sees somebody else's cached page or an old exam.
 * The exam room keeps its own queue of unsent answers (see live-session.php) and replays it when the connection returns.
 */
const VERSION = 'sv-v1';
const STATIC = 'sv-static-' + VERSION;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE_URL, '/assets/img/logo-mark.svg'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('sv-static-') && k !== STATIC).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

const isStaticAsset = (url) =>
  url.origin === self.location.origin && /^\/assets\/(css|js|img|anim|logos|vendor)\//.test(url.pathname) ||
  url.hostname === 'fonts.gstatic.com' || url.hostname === 'fonts.googleapis.com';

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (req.mode === 'navigate') {
    if (url.origin !== self.location.origin) return;
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  if (isStaticAsset(url)) {
    // stale-while-revalidate: answer from the cache at once, refresh it in the background
    event.respondWith(
      caches.open(STATIC).then((cache) =>
        cache.match(req).then((hit) => {
          const refresh = fetch(req).then((res) => {
            if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone());
            return res;
          }).catch(() => hit);
          return hit || refresh;
        })
      )
    );
  }
});
