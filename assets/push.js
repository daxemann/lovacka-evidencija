/* Lovačka evidencija – push obavijesti (Web Push): uključivanje na uređaju, sinkronizacija pretplate, traka s ponudom. */
(function () {
    'use strict';
    if (window.EVPush) return;
    var EV = window.EV || {};
    var api = EV.push;
    var ls = { get: function (k) { try { return localStorage.getItem(k); } catch (x) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (x) { } } };
    var ss = { get: function (k) { try { return sessionStorage.getItem(k); } catch (x) { return null; } }, set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (x) { } } };
    var ua = navigator.userAgent || '';
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var instalirano = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    var podrzano = !!(api && window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);

    function csrf() { var m = document.querySelector('meta[name=csrf]'); return m ? m.content : ''; }
    function b64(u) {
        var p = '='.repeat((4 - u.length % 4) % 4), s = atob((u + p).replace(/-/g, '+').replace(/_/g, '/')), a = new Uint8Array(s.length);
        for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i);
        return a;
    }
    function posalji(podaci) {
        var fd = new FormData();
        Object.keys(podaci).forEach(function (k) { fd.append(k, podaci[k]); });
        fd.append('_csrf', csrf());
        return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': csrf() } }).then(function (r) { return r.json(); });
    }
    function registracija() { return navigator.serviceWorker.register(EV.sw).then(function () { return navigator.serviceWorker.ready; }); }
    function prijaviNaPosluzitelj(sub) {
        var j = sub.toJSON();
        return posalji({ radnja: 'pretplati', endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth }).then(function (r) {
            if (r.ok) { ls.set('ev-push-uid', String(EV.uid)); ss.set('ev-push-sinkr', '1'); }
            return r;
        });
    }

    /** 'nepodrzano' | 'ios-instaliraj' | 'blokirano' | 'iskljuceno' | 'ukljuceno' */
    function stanje() {
        if (!podrzano) return Promise.resolve(ios && !instalirano ? 'ios-instaliraj' : 'nepodrzano');
        if (Notification.permission === 'denied') return Promise.resolve('blokirano');
        return registracija().then(function (reg) { return reg.pushManager.getSubscription(); })
            .then(function (s) { return s && Notification.permission === 'granted' ? 'ukljuceno' : 'iskljuceno'; })
            .catch(function () { return 'nepodrzano'; });
    }
    function ukljuci() {
        return Notification.requestPermission().then(function (d) {
            if (d !== 'granted') throw new Error(d === 'denied' ? 'blokirano' : 'odbijeno');
            return Promise.all([registracija(), fetch(api, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); })]);
        }).then(function (x) {
            var reg = x[0], k = x[1];
            if (!k.ok) throw new Error(k.greska || 'kljuc');
            return reg.pushManager.getSubscription().then(function (s) {
                // stara pretplata s drugim ključem (npr. nova baza) se zamjenjuje
                if (s && s.options && s.options.applicationServerKey) {
                    var a = new Uint8Array(s.options.applicationServerKey), b = b64(k.kljuc), isti = a.length === b.length;
                    for (var i = 0; isti && i < a.length; i++) if (a[i] !== b[i]) isti = false;
                    if (!isti) return s.unsubscribe().then(function () { return null; });
                }
                return s;
            }).then(function (s) { return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(k.kljuc) }); });
        }).then(prijaviNaPosluzitelj);
    }
    function iskljuci() {
        return registracija().then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (s) {
            if (!s) return;
            var e = s.endpoint;
            return s.unsubscribe().then(function () { return posalji({ radnja: 'odjavi', endpoint: e }); });
        });
    }
    /** Jednom po sesiji (i pri promjeni korisnika) javi poslužitelju pretplatu ovog uređaja. */
    function sinkroniziraj() {
        if (!podrzano || !EV.uid || Notification.permission !== 'granted') return;
        if (ss.get('ev-push-sinkr') === '1' && ls.get('ev-push-uid') === String(EV.uid)) return;
        registracija().then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (s) { if (s) return prijaviNaPosluzitelj(s); }).catch(function () { });
    }
    window.EVPush = { podrzano: podrzano, ios: ios, instalirano: instalirano, stanje: stanje, ukljuci: ukljuci, iskljuci: iskljuci, posalji: posalji };

    function poruka(t, greska) {
        var o = document.getElementById('obavijest');
        if (!o) { window.alert(t); return; }
        o.innerHTML = '<div class="flex-grow-1' + (greska ? ' text-danger fw-semibold' : '') + '"></div>';
        o.firstChild.textContent = t;
        o.style.display = 'flex';
        clearTimeout(poruka.t); poruka.t = setTimeout(function () { o.style.display = 'none'; }, greska ? 7000 : 3500);
    }
    function greskaTekst(e) {
        var m = e && e.message;
        return m === 'blokirano' ? 'Obavijesti su blokirane u pregledniku – dopustite ih u postavkama.' : m === 'odbijeno' ? 'Obavijesti nisu dopuštene.' : 'Uključivanje nije uspjelo' + (m ? ' (' + m + ')' : '') + '.';
    }

    function init() {
        sinkroniziraj();

        // ---------- traka s ponudom (jednom, može se zatvoriti) ----------
        var traka = document.getElementById('push-traka');
        if (traka && podrzano && Notification.permission === 'default' && ls.get('ev-push-traka') !== '0' && !document.getElementById('push-kartica')) traka.hidden = false;
        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-push-zatvori]')) { traka.hidden = true; ls.set('ev-push-traka', '0'); }
            if (e.target.closest('[data-push-ukljuci]')) {
                ukljuci().then(function (r) { if (traka) traka.hidden = true; poruka(r.ok ? 'Obavijesti uključene ✅' : (r.greska || 'Greška'), !r.ok); })
                    .catch(function (x) { poruka(greskaTekst(x), true); });
            }
        });

        // ---------- stranica „Push obavijesti“ ----------
        var k = document.getElementById('push-kartica');
        if (!k) return;
        var el = function (id) { return document.getElementById(id); };
        function osvjezi() {
            stanje().then(function (s) {
                var t = { nepodrzano: 'Ovaj preglednik ne podržava push obavijesti.', 'ios-instaliraj': 'Na iPhoneu najprije dodajte aplikaciju na početni zaslon.',
                    blokirano: '⛔ Obavijesti su blokirane u pregledniku.', iskljuceno: 'Isključeno – na ovaj uređaj ne stižu obavijesti.', ukljuceno: '✅ Uključeno – obavijesti stižu i kad je aplikacija zatvorena.' }[s];
                el('push-stanje').textContent = t;
                el('push-ukljuci').hidden = s !== 'iskljuceno';
                el('push-iskljuci').hidden = s !== 'ukljuceno';
                el('push-ios').hidden = s !== 'ios-instaliraj';
                el('push-blokirano').hidden = s !== 'blokirano';
                if (s === 'ukljuceno') {
                    el('push-proba').hidden = false;
                    registracija().then(function (r) { return r.pushManager.getSubscription(); }).then(function (sub) {
                        if (!sub) return;
                        document.querySelectorAll('[data-endpoint]').forEach(function (li) { if (li.getAttribute('data-endpoint') === sub.endpoint) li.querySelector('[data-ovaj]').classList.remove('d-none'); });
                    });
                }
            });
        }
        el('push-ukljuci').addEventListener('click', function () {
            var b = this; b.disabled = true;
            ukljuci().then(function (r) { if (r.ok) location.reload(); else { poruka(r.greska || 'Greška', true); b.disabled = false; } })
                .catch(function (x) { poruka(greskaTekst(x), true); b.disabled = false; osvjezi(); });
        });
        el('push-iskljuci').addEventListener('click', function () {
            this.disabled = true;
            iskljuci().then(function () { location.reload(); }).catch(function () { location.reload(); });
        });
        el('push-proba').addEventListener('click', function () {
            var b = this; b.disabled = true;
            posalji({ radnja: 'proba' }).then(function (r) {
                b.disabled = false;
                poruka(r.ok ? 'Poslano na ' + r.uspjelo + ' od ' + r.ukupno + ' uređaja – trebala bi stići za nekoliko sekundi.' : (r.greska || 'Nije poslano.'), !r.ok);
            }).catch(function () { b.disabled = false; poruka('Nema veze s poslužiteljem.', true); });
        });
        osvjezi();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
