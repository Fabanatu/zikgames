// ZikGames Service Worker
const APP_BASE      = '/ZikGames';
const CACHE_NAME    = 'zikgames-v4';
const STATIC_ASSETS = [
  `${APP_BASE}/`,
  `${APP_BASE}/index.php`,
  `${APP_BASE}/assets/css/style.css`,
  `${APP_BASE}/assets/images/hero_bg.png`,
];

// Install
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Activate
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  if (event.request.url.includes('/api/')) return; // Don't cache API calls

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).then(response => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => caches.match(event.request))
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(cached => {
      const networkFetch = fetch(event.request).then(response => {
        if (response.ok) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      }).catch(() => cached);
      return cached || networkFetch;
    })
  );
});

// Push Notification Handler
self.addEventListener('push', event => {
  let data = { title: 'ZikGames', body: 'You have a new notification!' };
  if (event.data) {
    try { data = event.data.json(); } catch(e) { data.body = event.data.text(); }
  }
  const options = {
    body:  data.body,
    icon:  `${APP_BASE}/assets/images/icon-192.png`,
    badge: `${APP_BASE}/assets/images/icon-72.png`,
    vibrate: [200, 100, 200],
    data:  { url: data.url || `${APP_BASE}/dashboard.php` },
    actions: [
      { action: 'play', title: '🎮 Play Now' },
      { action: 'dismiss', title: '✕ Dismiss' }
    ]
  };
  event.waitUntil(self.registration.showNotification(data.title, options));
});

// Notification Click Handler
self.addEventListener('notificationclick', event => {
  event.notification.close();
  if (event.action === 'dismiss') return;
  const url = event.notification.data?.url || `${APP_BASE}/dashboard.php`;
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(clientList => {
      for (const client of clientList) {
        if (client.url === url && 'focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});

