/* Lovačka evidencija – mali pomoćnici u pregledniku (bez okvira). */
window.kopirajTekst = function (t) {
    if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(t).catch(function () {}); }
    var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch (x) { /* ništa */ }
    ta.remove(); return Promise.resolve();
};
/* Viber ne prima tekst kroz poveznicu – tekst se kopira, Viber otvara razgovor, korisnik samo zalijepi. */
window.viberPoruka = function (broj) {
    var t = document.getElementById('obavijest');
    if (t) { t.innerHTML = '<div class="flex-grow-1"><b>Tekst je kopiran</b><div class="small text-muted">U Viberu dugo pritisnite polje za poruku → Zalijepi.</div></div>'; t.style.display = 'flex'; setTimeout(function () { t.style.display = 'none'; }, 6000); }
    setTimeout(function () { location.href = 'viber://chat?number=%2B' + broj; }, 400);
};
(function () {
    'use strict';
    // potvrda prije opasnih radnji: <button data-potvrda="Sigurno?">
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-potvrda]');
        if (b && !window.confirm(b.getAttribute('data-potvrda'))) { e.preventDefault(); e.stopPropagation(); }
    }, true);
    // automatsko slanje obrasca kad se promijeni izbor: <select data-auto>
    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-auto]')) { e.target.form.submit(); }
    });
    // "označi sve": <input type=checkbox data-sve="ime-polja">
    document.addEventListener('change', function (e) {
        var s = e.target.getAttribute && e.target.getAttribute('data-sve');
        if (!s) return;
        document.querySelectorAll('input[type=checkbox][name="' + s + '"]').forEach(function (c) {
            if (!c.disabled && c.closest('tr,label,div') && c.offsetParent !== null) c.checked = e.target.checked;
        });
        document.dispatchEvent(new Event('ev-oznaceno'));
    });
    // kopiranje u međuspremnik: <button data-kopiraj="#id">
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-kopiraj]');
        if (!b) return;
        var el = document.querySelector(b.getAttribute('data-kopiraj'));
        var t = el ? (el.value !== undefined ? el.value : el.textContent) : '';
        var gotovo = function () { var s = b.textContent; b.textContent = 'Kopirano ✓'; setTimeout(function () { b.textContent = s; }, 1500); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(gotovo); }
        else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); gotovo(); }
    });
    // obavijesti o novim porukama (svakih 20 s)
    if (window.EV && EV.api) {
        var zadnji = EV.n;
        var prikazi = function (o) {
            var t = document.getElementById('obavijest');
            if (!t) return;
            t.innerHTML = '';
            var a = document.createElement('a'); a.href = o.link || EV.poruke; a.className = 'text-decoration-none text-body flex-grow-1';
            var b = document.createElement('b'); b.textContent = o.naslov; a.appendChild(b);
            var d = document.createElement('div'); d.className = 'small text-muted'; d.textContent = o.tekst; a.appendChild(d);
            var x = document.createElement('button'); x.type = 'button'; x.className = 'btn-close ms-2'; x.onclick = function () { t.style.display = 'none'; };
            t.appendChild(a); t.appendChild(x); t.style.display = 'flex';
            setTimeout(function () { t.style.display = 'none'; }, 12000);
        };
        var provjeri = function () {
            if (document.hidden) return;
            fetch(EV.api, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
                if (!j) return;
                document.querySelectorAll('a.nav-link[href*="p=poruke"]').forEach(function (l) {
                    var bd = l.querySelector('.badge');
                    if (j.n > 0) { if (!bd) { bd = document.createElement('span'); bd.className = 'badge rounded-pill bg-warning text-dark'; l.appendChild(bd); } bd.textContent = j.n; }
                    else if (bd) bd.remove();
                });
                if (j.n > zadnji && j.zadnja) prikazi(j.zadnja);
                zadnji = j.n;
                document.dispatchEvent(new CustomEvent('ev-poruke', { detail: j }));
            }).catch(function () {});
        };
        setInterval(provjeri, 20000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) provjeri(); });
    }

    // dodir na vlastitu poruku prikazuje gumb za brisanje (mobitel nema "hover")
    document.addEventListener('click', function (e) {
        if (e.target.closest('.chat-brisi')) return;
        var r = e.target.closest('.chat-red.moja');
        document.querySelectorAll('.chat-red.aktivna').forEach(function (x) { if (x !== r) x.classList.remove('aktivna'); });
        if (r) r.classList.toggle('aktivna');
    });
    // ---------- "Dodaj na početni zaslon" (PWA) ----------
    var ls = { get: function (k) { try { return localStorage.getItem(k); } catch (x) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (x) { /* privatni način */ } } };
    if ('serviceWorker' in navigator && window.EV && EV.sw && window.isSecureContext) { navigator.serviceWorker.register(EV.sw).catch(function () {}); }
    var ua = navigator.userAgent || '';
    var instalirano = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var android = /android/i.test(ua);
    var uAplikaciji = /FBAN|FBAV|Instagram|Viber|WhatsApp|Line\/|; wv\)|GSA\//i.test(ua);
    var mobitel = ios || android;
    var odgodeno = null;
    var traka = document.getElementById('instal-traka');
    var upute = document.getElementById('instal-upute');
    var prikaziGumbe = function () {
        if (instalirano) return;
        document.querySelectorAll('.instal-gumb').forEach(function (g) { g.hidden = false; });
        if (traka && mobitel && ls.get('ev-instal-skriveno') !== '1') traka.hidden = false;
    };
    var pokaziUpute = function (s) {
        if (!upute) return;
        upute.querySelectorAll('[data-sustav]').forEach(function (d) { d.hidden = d.getAttribute('data-sustav') !== s; });
        upute.hidden = false;
    };
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); odgodeno = e; prikaziGumbe(); });
    window.addEventListener('appinstalled', function () { instalirano = true; if (traka) traka.hidden = true; document.querySelectorAll('.instal-gumb').forEach(function (g) { g.hidden = true; }); });
    if (!instalirano && (ios || uAplikaciji || android)) prikaziGumbe();
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-instaliraj]')) {
            if (odgodeno) { odgodeno.prompt(); odgodeno.userChoice.then(function (r) { if (r.outcome === 'accepted' && traka) traka.hidden = true; odgodeno = null; }); }
            else pokaziUpute(uAplikaciji ? 'app' : (ios ? 'ios' : 'android'));
        }
        if (e.target.closest('[data-instal-zatvori]')) { traka.hidden = true; ls.set('ev-instal-skriveno', '1'); }
        if (e.target.closest('[data-upute-zatvori]') || e.target === upute) upute.hidden = true;
    });
})();
