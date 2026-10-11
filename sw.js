/*
 * StudyVibe service worker.
 *
 * What it does: keeps the static files (styles, scripts, images, fonts) so pages open fast on a weak connection, shows a friendly
 * offline page when the network is gone while a page is being opened, and receives web push notifications (new lesson, result,
 * reminder...) even when the app is closed.
 *
 * What it never does: touch /api/, any POST, any download or export, or any page that depends on who is signed in. Pages
 * always come from the network first, so a student never sees somebody else's cached page or an old exam.
 * The exam room keeps its own queue of unsent answers (see live-session.php) and replays it when the connection returns.
 */
const VERSION = 'sv-v2';
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

/* ───────────── push notifications ───────────── */
self.addEventListener('push', (event) => {
  let d = {};
  try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'StudyVibe', body: event.data ? event.data.text() : '' }; }
  const title = d.title || 'StudyVibe';
  const options = {
    body: d.body || '',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/badge-96.png',
    tag: d.tag || undefined,                // same tag = the newer notification replaces the older one
    renotify: !!d.tag,
    data: { url: d.url || '/' },
    lang: 'fr',
  };
  event.waitUntil((async () => {
    await self.registration.showNotification(title, options);
    // the number on the app icon, where the system allows it
    try {
      const list = await self.registration.getNotifications();
      if (self.navigator && self.navigator.setAppBadge) await self.navigator.setAppBadge(list.length);
    } catch (e) {}
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
  event.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) {
      if (new URL(w.url).origin === self.location.origin && 'focus' in w) {
        await w.focus();
        if ('navigate' in w) { try { await w.navigate(target); } catch (e) {} }
        return;
      }
    }
    await self.clients.openWindow(target);
    try { if (self.navigator && self.navigator.clearAppBadge) await self.navigator.clearAppBadge(); } catch (e) {}
  })());
});

// The browser rotated the subscription: tell the server about the new one (the user is still signed in on this origin).
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    try {
      const key = await (await fetch('/api/push.php?action=state', { credentials: 'same-origin' })).json();
      if (!key.publicKey) return;
      const sub = await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key.publicKey });
      await fetch('/api/push.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ action: 'subscribe' }, sub.toJSON())) });
    } catch (e) {}
  })());
});
