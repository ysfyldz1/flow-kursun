// Flow service worker: statik dosyaları önbellekler, bağlantı yokken çevrimdışı sayfası gösterir.
// Sayfalar ve veriler her zaman ağdan gelir (güncel ve yetkiye bağlı içerik önbelleğe alınmaz).
const VERSION = 'flow-v1';
const OFFLINE = '/offline';

self.addEventListener('install', event => {
    event.waitUntil(caches.open(VERSION).then(c => c.addAll([OFFLINE])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== location.origin) return;

    // Sayfa gezintisi: ağ, olmazsa çevrimdışı sayfası
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
        return;
    }

    // CSS, ikon ve görseller: önce önbellek, arkada güncelle
    if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(
            caches.open(VERSION).then(cache =>
                cache.match(req).then(hit => {
                    const fresh = fetch(req).then(res => {
                        if (res.ok) cache.put(req, res.clone());
                        return res;
                    }).catch(() => hit);
                    return hit || fresh;
                })
            )
        );
    }
});
