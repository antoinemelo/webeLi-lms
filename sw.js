const CACHE = 'lms-shell-v128';
const SHELL = [
  'assets/vendor/bootstrap/bootstrap.min.css',
  'assets/vendor/bootstrap/bootstrap.bundle.min.js',
  'assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
  'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  'assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff',
  'assets/vendor/pdfjs/pdf.min.mjs',
  'assets/vendor/pdfjs/pdf.worker.min.mjs',
  'assets/app.css',
  'assets/app.js',
  'assets/messaging.js',
  'assets/messaging.css',
  'assets/content-blocks.js',
  'assets/announcement-messages.js',
  'assets/student-admin.js',
  'assets/icon.svg',
  'assets/icon-192.png',
  'assets/icon-512.png',
  'assets/icon-maskable-512.png',
  'assets/apple-touch-icon.png',
  'assets/field-notes.svg',
  'manifest.webmanifest',
];

self.addEventListener('install', (event) => event.waitUntil(
  caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()),
));

self.addEventListener('activate', (event) => event.waitUntil(
  caches.keys()
    .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
    .then(() => self.clients.claim()),
));

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || event.request.mode === 'navigate') return;
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin || !SHELL.some((path) => url.pathname.endsWith(path))) return;
  event.respondWith(caches.match(event.request).then((hit) => hit || fetch(event.request).then((response) => {
    const copy = response.clone();
    caches.open(CACHE).then((cache) => cache.put(event.request, copy));
    return response;
  })));
});

// Keep only the installation's subscription identifier, never conversation content.
function notificationOwner(value) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('liike-notifications', 1);
    request.onupgradeneeded = () => request.result.createObjectStore('settings');
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction('settings', value === undefined ? 'readonly' : 'readwrite');
      const store = tx.objectStore('settings');
      const operation = value === undefined ? store.get(self.registration.scope) : store.put(value, self.registration.scope);
      tx.oncomplete = () => { resolve(operation.result); db.close(); };
      tx.onerror = () => { reject(tx.error); db.close(); };
    };
  });
}
self.addEventListener('message', (event) => {
  if (event.data?.type === 'notification-owner') event.waitUntil(notificationOwner(event.data.subscription || null));
});
self.addEventListener('push', (event) => event.waitUntil((async () => {
  const payload = event.data?.json();
  if (!payload || !payload.subscription || payload.subscription !== await notificationOwner()) return;
  const target = new URL(payload.url || '?view=discussions', self.registration.scope);
  if (target.origin !== self.location.origin || target.pathname !== new URL(self.registration.scope).pathname) return;
  const count = Math.max(0, Number(payload.count) || 0);
  if (self.navigator.setAppBadge) await self.navigator.setAppBadge(count).catch(() => {});
  await self.registration.showNotification(payload.title || 'liike', {
    body: payload.body || 'liike', icon: 'assets/icon-192.png', badge: 'assets/icon-192.png',
    tag: target.search, data: { url: target.href, subscription: payload.subscription },
  });
})()));
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil((async () => {
    if (event.notification.data?.subscription !== await notificationOwner()) return;
    const target = new URL(event.notification.data.url, self.registration.scope);
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of clients) {
      if (client.url.startsWith(self.registration.scope)) { await client.navigate(target.href); await client.focus(); return; }
    }
    await self.clients.openWindow(target.href);
  })());
});
