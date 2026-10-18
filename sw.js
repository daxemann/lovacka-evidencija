/* Lovačka evidencija – service worker.
 * "Dodaj na početni zaslon" + rad bez signala:
 *  - stranice dezinfekcijskih stanica, stranica "Skeniraj" i "Moja iskaznica" spremaju se na mobitel,
 *  - bez interneta se aplikacija otvara na stranici "Skeniraj" (skener QR, upisi na čekanju, spremljene stanice),
 *  - upisi spremljeni bez interneta šalju se i kad je aplikacija zatvorena (Background Sync – Android/Chrome;
 *    iPhone to ne podržava – tamo se šalju kad se aplikacija otvori).
 * Ostale stranice se uvijek učitavaju svježe s poslužitelja. */
var SPREMNIK = 'ev-dez-2';
var BAZA = 'ev-dez', TABLICA = 'kv';

self.addEventListener('install', function (e) {
    self.skipWaiting();
    e.waitUntil(caches.open(SPREMNIK).then(function (c) {
        return fetch(skeniraj(), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { if (r.ok && !r.redirected) return c.put(skeniraj(), r); }).catch(function () {});
    }));
});
self.addEventListener('activate', function (e) {
    e.waitUntil(caches.keys().then(function (k) {
        return Promise.all(k.filter(function (n) { return n !== SPREMNIK; }).map(function (n) { return caches.delete(n); }));
    }).then(function () { return self.clients.claim(); }));
});
function skeniraj() { return new URL('index.php?p=skeniraj', self.registration.scope).href; }
function jeStanica(u) { return u.searchParams.get('p') === 'dez' && !!u.searchParams.get('s'); }
function jeSkeniraj(u) { return u.searchParams.get('p') === 'skeniraj'; }
function iskaznica() { return new URL('index.php?p=moja-iskaznica', self.registration.scope).href; }
function jeIskaznica(u) { return u.searchParams.get('p') === 'moja-iskaznica'; }
function bezInterneta() {
    return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        + '<div style="font-family:system-ui;padding:2rem;text-align:center"><h2>Nema interneta</h2><p>Provjerite vezu i pokušajte ponovno.</p>'
        + '<p style="color:#666;font-size:.9rem">Upis dezinfekcije bez signala radi ako ste aplikaciju na ovom mobitelu već jednom otvorili s internetom.</p>'
        + '<button onclick="location.reload()" style="padding:.6rem 1.2rem">Pokušaj ponovno</button></div>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
/** Bez interneta: stranica "Skeniraj" iz memorije (ako postoji), inače kratka poruka. */
function izvanmrezno() { return caches.match(skeniraj()).then(function (m) { return m || bezInterneta(); }); }
function sRokom(p, ms) { return new Promise(function (ok, ne) { var t = setTimeout(function () { ne(new Error('rok')); }, ms); p.then(function (r) { clearTimeout(t); ok(r); }, function (x) { clearTimeout(t); ne(x); }); }); }
function spremi(kljuc, odg) { if (odg && odg.ok && !odg.redirected && odg.type === 'basic') { var k = odg.clone(); caches.open(SPREMNIK).then(function (c) { c.put(kljuc, k); }); } }

// ---------- mala baza (IndexedDB) – red upisa za slanje u pozadini ----------
function baza() {
    return new Promise(function (ok, ne) {
        var z = indexedDB.open(BAZA, 1);
        z.onupgradeneeded = function () { z.result.createObjectStore(TABLICA); };
        z.onsuccess = function () { ok(z.result); };
        z.onerror = function () { ne(z.error); };
    });
}
function bazaGet(k) { return baza().then(function (db) { return new Promise(function (ok, ne) { var r = db.transaction(TABLICA).objectStore(TABLICA).get(k); r.onsuccess = function () { ok(r.result); }; r.onerror = function () { ne(r.error); }; }); }); }
function bazaSet(k, v) { return baza().then(function (db) { return new Promise(function (ok, ne) { var t = db.transaction(TABLICA, 'readwrite'); t.objectStore(TABLICA).put(v, k); t.oncomplete = function () { ok(); }; t.onerror = function () { ne(t.error); }; }); }); }

/** Slanje reda iz pozadine. Poslužitelj prepoznaje već poslane upise (isti id), pa dvostruko slanje ne stvara dvostruke upise. */
function posaljiRed() {
    return bazaGet('red').then(function (s) {
        if (!s || !s.api || !s.red || !s.red.length) return;
        return fetch(s.api, { credentials: 'same-origin', cache: 'no-store' }).then(function (o) {
            if (o.status === 401) return null; // nije prijavljen – pošalje se nakon prijave
            if (!o.ok) throw new Error('http ' + o.status);
            return o.json();
        }).then(function (j) {
            if (!j) return;
            var moji = s.red.filter(function (x) { return !x.u || x.u === j.uid; });
            if (!moji.length) return;
            var pomak = typeof j.sada === 'number' ? Math.round(j.sada - Date.now() / 1000) : null;
            return fetch(s.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': j.csrf },
                body: JSON.stringify({ stavke: moji.map(function (x) { return { id: x.id, s: x.s, t: x.t, u: x.u, po: x.po != null ? x.po : pomak, polja: x.polja }; }) }) })
                .then(function (o) { if (!o.ok) throw new Error('http ' + o.status); return o.json(); })
                .then(function (r) {
                    return bazaGet('red').then(function (s2) {
                        s2 = s2 || s;
                        var gotovo = {}, ishodi = s2.ishodi || {};
                        (r.rezultati || []).forEach(function (x) { if (x.ok || x.trajno) { gotovo[x.id] = 1; ishodi[x.id] = { ok: !!x.ok, poruka: x.poruka || null }; } });
                        s2.red = (s2.red || []).filter(function (x) { return !gotovo[x.id]; });
                        s2.ishodi = ishodi;
                        return bazaSet('red', s2).then(function () {
                            return self.clients.matchAll({ includeUncontrolled: true }).then(function (cl) { cl.forEach(function (c) { c.postMessage({ tip: 'dez-ishodi', ishodi: ishodi }); }); });
                        });
                    });
                });
        });
    });
}
self.addEventListener('sync', function (e) {
    if (e.tag === 'dez-red') e.waitUntil(posaljiRed());
});

self.addEventListener('message', function (e) {
    var d = e.data || {};
    if (d.tip === 'dez-red') {
        // kopija reda sa stranice (localStorage nije dostupan service workeru)
        e.waitUntil(bazaGet('red').then(function (s) {
            s = s || {};
            var ishodi = s.ishodi || {}, u = {};
            (d.red || []).forEach(function (x) { u[x.id] = 1; });
            Object.keys(ishodi).forEach(function (id) { if (!u[id] && (d.potvrdeno || []).indexOf(id) !== -1) delete ishodi[id]; });
            return bazaSet('red', { api: d.api, red: (d.red || []).filter(function (x) { return !ishodi[x.id]; }), ishodi: ishodi });
        }).then(function () {
            if (d.red && d.red.length && self.registration.sync) return self.registration.sync.register('dez-red').catch(function () {});
        }).catch(function () {}));
        return;
    }
    if (d.tip === 'dez-ishodi?') {
        // stranica pita koje je upise poslala pozadina dok je bila zatvorena
        var port = e.ports && e.ports[0];
        e.waitUntil(bazaGet('red').then(function (s) { if (port) port.postMessage({ ishodi: (s && s.ishodi) || {} }); }).catch(function () { if (port) port.postMessage({ ishodi: {} }); }));
        return;
    }
    if (d.tip === 'dez-brisi') {
        // promjena korisnika / odjava: spremljene stranice stanica sadrže ime i vozila prethodnog korisnika
        e.waitUntil(caches.open(SPREMNIK).then(function (c) {
            return c.keys().then(function (k) {
                return Promise.all(k.filter(function (r) { var u = new URL(r.url); return jeStanica(u) || jeIskaznica(u); }).map(function (r) { return c.delete(r); }));
            });
        }));
        return;
    }
    if (d.tip !== 'dez-predmem' || !Array.isArray(d.urls)) return;
    e.waitUntil(caches.open(SPREMNIK).then(function (c) {
        return Promise.all(d.urls.slice(0, 80).map(function (u) {
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
            // stanica: s interneta (najviše 8 s, slab signal u lovištu), inače spremljena kopija; nije spremljena → "Skeniraj" s porukom
            e.respondWith(sRokom(fetch(req), 8000).then(function (r) { spremi(url.href, r); return r; })
                .catch(function () { return caches.match(url.href).then(function (m) { return m || izvanmrezno(); }); }));
            return;
        }
        if (jeIskaznica(url)) {
            // članska iskaznica: s interneta, bez signala spremljena kopija (lovnik je skenira i u lovištu)
            e.respondWith(sRokom(fetch(req), 8000).then(function (r) { spremi(iskaznica(), r); return r; })
                .catch(function () { return caches.match(iskaznica()).then(function (m) { return m || izvanmrezno(); }); }));
            return;
        }
        if (jeSkeniraj(url)) {
            e.respondWith(sRokom(fetch(req), 8000).then(function (r) { spremi(skeniraj(), r); return r; }).catch(izvanmrezno));
            return;
        }
        // ostale stranice: s interneta; bez interneta → "Skeniraj"
        e.respondWith(fetch(req).catch(izvanmrezno));
        return;
    }
    if (url.pathname.indexOf('/assets/') !== -1 || url.searchParams.get('p') === 'manifest' || url.searchParams.get('p') === 'ikona' || url.searchParams.get('p') === 'logo') {
        e.respondWith(fetch(req).then(function (r) { spremi(req.url, r); return r; })
            .catch(function () { return caches.match(req.url).then(function (m) { return m || Response.error(); }); }));
    }
});

// ---------- Push obavijesti (stižu i kad je aplikacija zatvorena) ----------
self.addEventListener('push', function (e) {
    var d = {};
    try { d = e.data ? e.data.json() : {}; } catch (x) { d = { b: e.data ? e.data.text() : '' }; }
    var naslov = d.t || 'Evidencija';
    var opcije = {
        body: d.b || '', icon: d.i || undefined, badge: d.i || undefined, tag: d.g || undefined, renotify: !!d.g,
        data: { url: d.u || self.registration.scope, gumbi: d.a || [] }, lang: 'hr'
    };
    if (d.r) opcije.requireInteraction = true; // SOS, poziv na grupni lov – ostaje dok se ne dodirne
    if (d.v) opcije.vibrate = d.v;
    // gumbi u obavijesti (Android/Chrome; iPhone ih ne prikazuje – tamo dodir otvara stranicu)
    if (d.a && d.a.length) opcije.actions = d.a.map(function (g) { return { action: g.a, title: g.t }; });
    e.waitUntil(self.registration.showNotification(naslov, opcije).then(function () {
        // otvorene stranice odmah osvježe brojače (zvono, poruke)
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cl) { cl.forEach(function (c) { c.postMessage({ tip: 'push', g: d.g }); }); });
    }));
});
self.addEventListener('notificationclick', function (e) {
    e.notification.close();
    var podaci = e.notification.data || {};
    var cilj = podaci.url || self.registration.scope;
    var gumb = e.action ? (podaci.gumbi || []).filter(function (g) { return g.a === e.action; })[0] : null;
    if (gumb && gumb.otvori) { e.waitUntil(otvori(gumb.otvori)); return; } // npr. „Dolazim“ – stranica sama sprema odgovor i pokreće praćenje
    if (gumb && gumb.url) {
        // odgovor izravno iz obavijesti (npr. „Ne dolazim“) – osobni jednokratni ključ u adresi, bez otvaranja aplikacije
        var odgovor = fetch(gumb.url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); })
            .then(function (j) { return self.registration.showNotification(j.ok ? (j.naslov || 'Spremljeno') : 'Nije spremljeno', { body: j.ok ? (j.tekst || '') : (j.greska || 'Otvorite aplikaciju i pokušajte ponovno.'), tag: podaci.gumbi ? 'gl-potvrda' : undefined, lang: 'hr' }); })
            .catch(function () { return self.registration.showNotification('Nema veze', { body: 'Odgovor nije poslan – otvorite aplikaciju kad budete imali signal.', lang: 'hr' }); });
        e.waitUntil(odgovor);
        return;
    }
    e.waitUntil(otvori(cilj));
});
function otvori(cilj) {
    return (self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cl) {
        for (var i = 0; i < cl.length; i++) {
            var c = cl[i];
            if (c.url.indexOf(self.registration.scope) === 0 && 'focus' in c) {
                return c.focus().then(function (f) { return (f || c).navigate ? (f || c).navigate(cilj) : null; }).catch(function () { return self.clients.openWindow(cilj); });
            }
        }
        return self.clients.openWindow(cilj);
    }));
}
self.addEventListener('pushsubscriptionchange', function (e) {
    // preglednik je obnovio pretplatu – stranica je pri sljedećem otvaranju ponovno prijavi (sinkronizacija u push.js)
    e.waitUntil(Promise.resolve());
});
