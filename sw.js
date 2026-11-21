/* No Days Off Club - service worker
   Guarda só arquivos visuais (CSS, fontes, logo) e a tela "sem conexão".
   Páginas com dados de saúde NUNCA são guardadas no aparelho. */
const VERSION = 'ndo-v10';
const SCOPE = self.registration.scope;
const PRECACHE = [
    'offline.html',
    'assets/css/app.css',
    'assets/fonts/archivo-black.woff',
    'assets/fonts/dm-sans.woff',
    'assets/img/logo-horizontal.svg',
    'assets/img/logo-nd.svg'
].map(function (p) { return new URL(p, SCOPE).href; });

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(VERSION).then(function (cache) {
            return Promise.all(PRECACHE.map(function (u) { return cache.add(u).catch(function () {}); }));
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') { return; }
    var url = new URL(req.url);
    if (url.origin !== location.origin) { return; }

    // Arquivos visuais: usa o guardado e atualiza em segundo plano
    if (url.href.indexOf(SCOPE + 'assets/') === 0) {
        event.respondWith(
            caches.open(VERSION).then(function (cache) {
                return cache.match(req, { ignoreSearch: true }).then(function (hit) {
                    var fresh = fetch(req).then(function (res) {
                        if (res && res.ok) { cache.put(req, res.clone()); }
                        return res;
                    }).catch(function () { return hit; });
                    return hit || fresh;
                });
            })
        );
        return;
    }

    // Páginas: sempre da rede; sem internet, mostra a tela "sem conexão"
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(new URL('offline.html', SCOPE).href);
            })
        );
    }
});
