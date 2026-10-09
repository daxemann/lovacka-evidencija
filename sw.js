/* Lovačka evidencija – service worker.
 * "Dodaj na početni zaslon" + stranice dezinfekcijskih stanica rade i bez signala (spremljene na mobitelu).
 * Ostale stranice se uvijek učitavaju svježe s poslužitelja. */
var SPREMNIK = 'ev-dez-2';
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) {
    e.waitUntil(caches.keys().then(function (k) {
        return Promise.all(k.filter(function (n) { return n !== SPREMNIK; }).map(function (n) { return caches.delete(n); }));
    }).then(function () { return self.clients.claim(); }));
});
function jeStanica(u) { return u.searchParams.get('p') === 'dez' && !!u.searchParams.get('s'); }
function bezInterneta() {
    return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<div style="font-family:system-ui;padding:2rem;text-align:center"><h2>Nema interneta</h2><p>Provjerite vezu i pokušajte ponovno.</p>'
        + '<p style="color:#666;font-size:.9rem">Upis dezinfekcije bez signala radi ako ste stranicu stanice (QR oznaku) na ovom mobitelu već jednom otvorili s internetom.</p>'
        + '<button onclick="location.reload()" style="padding:.6rem 1.2rem">Pokušaj ponovno</button></div>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
function sRokom(p, ms) { return new Promise(function (ok, ne) { var t = setTimeout(function () { ne(new Error('rok')); }, ms); p.then(function (r) { clearTimeout(t); ok(r); }, function (x) { clearTimeout(t); ne(x); }); }); }
function spremi(kljuc, odg) { if (odg && odg.ok && !odg.redirected && odg.type === 'basic') { var k = odg.clone(); caches.open(SPREMNIK).then(function (c) { c.put(kljuc, k); }); } }

self.addEventListener('message', function (e) {
    var d = e.data || {};
    if (d.tip !== 'dez-predmem' || !Array.isArray(d.urls)) return;
    e.waitUntil(caches.open(SPREMNIK).then(function (c) {
        return Promise.all(d.urls.slice(0, 60).map(function (u) {
            var url = new URL(u, self.registration.scope);
            if (url.origin !== self.location.origin) return null;
            return fetch(url.href, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
                if (r.ok && !r.redirected) return c.put(url.href, r);
            }).catch(function () {});
        }));
    }));
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (req.mode === 'navigate') {
        if (jeStanica(url)) {
            // stanica: s interneta (najviše 8 s, slab signal u lovištu), inače spremljena kopija
            e.respondWith(sRokom(fetch(req), 8000).then(function (r) { spremi(url.href, r); return r; })
                .catch(function () { return caches.match(url.href).then(function (m) { return m || bezInterneta(); }); }));
            return;
        }
        e.respondWith(fetch(req).catch(bezInterneta));
        return;
    }
    if (url.pathname.indexOf('/assets/') !== -1) {
        e.respondWith(fetch(req).then(function (r) { spremi(req.url, r); return r; })
            .catch(function () { return caches.match(req.url).then(function (m) { return m || Response.error(); }); }));
    }
});
