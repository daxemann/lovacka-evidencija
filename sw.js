/* Lovačka evidencija – service worker (omogućuje "Dodaj na početni zaslon"; bez izvanmrežnog rada, podaci uvijek svježi). */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
    if (e.request.mode !== 'navigate') return;
    e.respondWith(fetch(e.request).catch(function () {
        return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            + '<div style="font-family:system-ui;padding:2rem;text-align:center"><h2>Nema interneta</h2><p>Provjerite vezu i pokušajte ponovno.</p>'
            + '<button onclick="location.reload()" style="padding:.6rem 1.2rem">Pokušaj ponovno</button></div>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
    }));
});
