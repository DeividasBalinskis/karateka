// Programėlė telefone: atsidaro be naršyklės juostos, be ryšio rodo „Nėra interneto ryšio“.
// Puslapiai (su narių duomenimis) niekada nesaugomi - tik dizainas (assets, img, šriftai) ir offline.html.
const CACHE = 'karateka-v1';
const OFFLINE = 'offline.html';

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(c => c.addAll([OFFLINE, 'icon-512.png'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())
  );
});

function isStatic(url) {
  return url.origin === self.location.origin && /\/(assets|img)\//.test(url.pathname)
    || url.origin === 'https://fonts.googleapis.com' || url.origin === 'https://fonts.gstatic.com';
}

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // Puslapiai - visada iš serverio; nepavykus - offline.html
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }
  // Dizainas: iš karto iš atminties, fone atnaujinama (failų adresuose yra ?v=..., tad nauja versija - naujas adresas)
  if (isStatic(url)) {
    event.respondWith(caches.open(CACHE).then(async cache => {
      const hit = await cache.match(req);
      const net = fetch(req).then(res => {
        if (res.ok) {
          cache.put(req, res.clone());
          // Ta pati byla su senu ?v= - nebereikalinga
          cache.keys().then(keys => keys.forEach(k => {
            const u = new URL(k.url);
            if (u.pathname === url.pathname && u.search !== url.search) cache.delete(k);
          }));
        }
        return res;
      }).catch(() => hit);
      return hit || net;
    }));
  }
});
