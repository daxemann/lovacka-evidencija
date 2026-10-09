/* Lovačka evidencija – knjiga dezinfekcije u pregledniku:
 * 1) upis na stanici (lokacija, obrazac, slanje),
 * 2) bez interneta: upis se sprema na mobitelu (vrijeme mobitela) i šalje automatski kad ima signala,
 * 3) stranice stanica se spremaju na mobitel (service worker) da rade i bez signala. */
(function () {
    'use strict';
    var skripta = document.currentScript;
    var API = (window.DEZ && DEZ.api) || (skripta && skripta.getAttribute('data-api'));
    var SW = skripta && skripta.getAttribute('data-sw');
    if (!API) return;
    var KL_RED = 'ev-dez-red', KL_PORUKE = 'ev-dez-poruke', KL_PREDMEM = 'ev-dez-predmem';
    var ls = {
        get: function (k, z) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : z; } catch (x) { return z; } },
        set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch (x) { return false; } }
    };
    function red() { return ls.get(KL_RED, []); }
    function hex16() { var a = new Uint8Array(8); (window.crypto || window.msCrypto).getRandomValues(a); return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
    function obavijest(html, vrsta) {
        var t = document.getElementById('dez-obavijest');
        if (!t) { t = document.createElement('div'); t.id = 'dez-obavijest'; t.className = 'dez-obavijest'; document.body.appendChild(t); }
        t.className = 'dez-obavijest ' + (vrsta || ''); t.innerHTML = html; t.hidden = false;
        clearTimeout(t._z); t._z = setTimeout(function () { t.hidden = true; }, 9000);
    }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ---------- slanje spremljenih upisa ----------
    var salje = false;
    function sinkroniziraj() {
        var r = red();
        if (salje || !r.length || navigator.onLine === false) return Promise.resolve();
        salje = true;
        return fetch(API, { credentials: 'same-origin', cache: 'no-store' }).then(function (o) {
            if (o.status === 401) throw { prijava: true };
            if (!o.ok) throw {};
            return o.json();
        }).then(function (j) {
            return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': j.csrf },
                body: JSON.stringify({ stavke: r.map(function (x) { return { id: x.id, s: x.s, t: x.t, polja: x.polja }; }) }) });
        }).then(function (o) { if (!o.ok) throw {}; return o.json(); }).then(function (j) {
            var gotovo = {}, poslano = 0, poruke = ls.get(KL_PORUKE, []);
            (j.rezultati || []).forEach(function (x) {
                if (x.ok) { gotovo[x.id] = 1; poslano++; }
                else if (x.trajno) {
                    gotovo[x.id] = 1;
                    var st = r.filter(function (y) { return y.id === x.id; })[0] || {};
                    poruke.push({ v: st.t, n: st.naziv, p: x.poruka });
                }
            });
            ls.set(KL_RED, red().filter(function (x) { return !gotovo[x.id]; }));
            ls.set(KL_PORUKE, poruke.slice(-10));
            if (poslano) obavijest('<b>✓ Poslano ' + poslano + (poslano === 1 ? ' upis' : ' upisa') + ' dezinfekcije</b><div class="small">spremljeno na mobitelu dok nije bilo signala</div>', 'ok');
            prikaziPoruke(); prikaziRed();
        }).catch(function (e) {
            if (e && e.prijava) obavijest('<b>Upisi dezinfekcije čekaju na mobitelu.</b><div class="small">Prijavite se u aplikaciju da se pošalju.</div>', 'upoz');
        }).then(function () { salje = false; });
    }
    function prikaziRed() {
        var b = document.getElementById('dez-red-traka'), n = red().length;
        if (b) { b.hidden = !n; b.textContent = '⏳ Na ovom mobitelu ' + (n === 1 ? 'čeka 1 upis' : 'čeka ' + n + ' upisa') + ' za slanje (šalje se automatski kad ima interneta).'; }
    }
    function prikaziPoruke() {
        var p = ls.get(KL_PORUKE, []);
        if (!p.length) return;
        var g = document.getElementById('dez-greska');
        var html = '<b>Neki spremljeni upisi nisu prihvaćeni:</b><ul class="mb-1">' + p.map(function (x) {
            return '<li>' + (x.v ? new Date(x.v * 1000).toLocaleString('hr-HR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) + ' · ' : '') + esc(x.n) + ': ' + esc(x.p) + '</li>';
        }).join('') + '</ul><div class="small">Javite se lovočuvaru za naknadni upis.</div>';
        if (g) { g.innerHTML = html; g.hidden = false; } else { obavijest(html, 'upoz'); }
        ls.set(KL_PORUKE, []);
    }
    window.addEventListener('online', function () { sinkroniziraj(); traka(); });
    window.addEventListener('offline', traka);
    setInterval(function () { if (red().length) sinkroniziraj(); }, 60000);

    // ---------- spremanje stranica stanica na mobitel ----------
    function predmemoriraj() {
        if (!('serviceWorker' in navigator) || !window.isSecureContext || navigator.onLine === false) return;
        var zadnje = ls.get(KL_PREDMEM, 0);
        if (Date.now() - zadnje < 6 * 3600 * 1000) return;
        fetch(API, { credentials: 'same-origin', cache: 'no-store' }).then(function (o) { return o.ok ? o.json() : null; }).then(function (j) {
            if (!j) return;
            var resursi = Array.prototype.map.call(document.querySelectorAll('link[rel=stylesheet][href], script[src]'), function (x) { return x.href || x.src; });
            navigator.serviceWorker.ready.then(function (reg) {
                if (reg.active) { reg.active.postMessage({ tip: 'dez-predmem', urls: j.stanice.concat(resursi) }); ls.set(KL_PREDMEM, Date.now()); }
            });
        }).catch(function () {});
    }
    if ('serviceWorker' in navigator && window.isSecureContext && SW) { navigator.serviceWorker.register(SW).catch(function () {}); }

    // ---------- stranica stanice ----------
    function traka() { var t = document.getElementById('dez-offline-traka'); if (t) t.hidden = navigator.onLine !== false; }
    if (window.DEZ && document.getElementById('dez-obrazac')) obrazac();

    function obrazac() {
        var ST = window.DEZ;
        var gps = document.getElementById('gps'), nas = document.getElementById('gps-naslov'), txt = document.getElementById('gps-tekst');
        var btn = document.getElementById('posalji'), nap = document.getElementById('posalji-napomena'), forma = document.getElementById('dez-obrazac');
        var greska = document.getElementById('dez-greska');
        var najbolja = null, gotovo = false, blokirano = false;
        traka(); prikaziRed(); prikaziPoruke();
        var neaktivna = ST.mob && !ST.aktivna;
        function stanje(klasa, n, t) { gps.className = 'dez-gps ' + klasa + ' mb-3'; nas.textContent = n; txt.textContent = t; }
        function omoguci(t) {
            if (neaktivna && navigator.onLine !== false) { btn.disabled = true; nap.textContent = 'Mobilna stanica nije aktivna.'; return; }
            btn.disabled = false; nap.textContent = t || '';
        }
        function udalj(a, b, c, d) { var R = 6371000, r = Math.PI / 180, x = (c - a) * r, y = (d - b) * r;
            var h = Math.sin(x / 2) * Math.sin(x / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(y / 2) * Math.sin(y / 2); return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)); }
        function m(x) { return x >= 1000 ? (x / 1000).toFixed(1).replace('.', ',') + ' km' : Math.round(x) + ' m'; }
        function polozaj(p) {
            var c = p.coords;
            if (najbolja && c.accuracy > najbolja.accuracy && Date.now() - najbolja.t < 15000) return;
            najbolja = { latitude: c.latitude, longitude: c.longitude, accuracy: c.accuracy, t: Date.now() };
            document.getElementById('f-lat').value = c.latitude; document.getElementById('f-lon').value = c.longitude; document.getElementById('f-acc').value = Math.round(c.accuracy);
            if (ST.lat === null) {
                stanje('dez-gps-ok', '📍 Lokacija pronađena', ST.mob ? 'Udaljenost od mobilne stanice provjerava se pri slanju.' : 'Stanica još nema upisane koordinate – upis se bilježi bez provjere.');
                omoguci(); return;
            }
            var d = udalj(ST.lat, ST.lon, c.latitude, c.longitude), a = Math.round(c.accuracy);
            if (d <= ST.r) { stanje('dez-gps-ok', '🟢 Na stanici ste', m(d) + ' od stanice · točnost ±' + a + ' m'); blokirano = false; omoguci(); }
            else if (d - a <= ST.r) { stanje('dez-gps-pola', '🟠 Lokacija nepouzdana', m(d) + ' od stanice, točnost ±' + a + ' m – upis je moguć, bit će označen. Na otvorenom je točnije.'); blokirano = false; omoguci(); }
            else { stanje('dez-gps-ne', '🔴 Predaleko od stanice', m(d) + ' od stanice (±' + a + ' m). Upis je moguć samo na dezinfekcijskoj stanici.'); blokirano = true; btn.disabled = true; nap.textContent = 'Priđite stanici – lokacija se osvježava sama.'; }
        }
        function greskaLok(e) {
            if (e.code === 1) { gotovo = true; stanje('dez-gps-ne', '🔴 Lokacija nije dopuštena', 'Dopustite lokaciju za ovu stranicu (ikona lokota / ⓘ pored adrese → Lokacija → Dopusti) i osvježite stranicu.');
                btn.disabled = true; nap.textContent = 'Bez lokacije upis nije moguć.'; return; }
            if (!najbolja) { stanje('dez-gps-pola', '🟠 Lokacija još nije pronađena', 'Uključite lokaciju (GPS) na mobitelu. Tražim dalje…'); }
        }
        if (!window.isSecureContext || !navigator.geolocation) {
            stanje('dez-gps-ne', '🔴 Lokacija nije dostupna', 'Stranica mora biti otvorena preko https:// adrese (QR oznaka), a preglednik mora podržavati lokaciju.');
        } else {
            navigator.geolocation.watchPosition(polozaj, greskaLok, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
            setTimeout(function () { if (!najbolja && !gotovo) { stanje('dez-gps-pola', '🟠 Lokacija nije pronađena', 'Upis je moguć, ali bit će označen „bez lokacije“.'); omoguci('Upis bez lokacije – bit će označen.'); } }, 30000);
        }
        if (neaktivna) { btn.disabled = true; }
        window.addEventListener('online', function () { if (neaktivna) location.reload(); });
        // prijedlog smjera iz upisa koji čekaju na mobitelu (stranica iz memorije ne zna za njih)
        var moji = red().filter(function (x) { return x.s === ST.token && Date.now() / 1000 - x.t < 86400; });
        if (moji.length) {
            var z = moji[moji.length - 1];
            document.getElementById(z.polja.smjer === 'D' ? 'sm-o' : 'sm-d').checked = true;
            if (z.polja.razlog) document.getElementById('razlog').value = z.polja.razlog;
        }
        // razlog "Ostalo" → opis
        var raz = document.getElementById('razlog'), opis = document.getElementById('razlog-opis');
        function razlogOpis() { var o = raz.options[raz.selectedIndex]; opis.hidden = !(o && o.getAttribute('data-slobodno') === '1'); }
        raz.addEventListener('change', razlogOpis); razlogOpis();
        document.querySelectorAll('input[name=vozilo]').forEach(function (r) { r.addEventListener('change', function () { document.getElementById('drugo-polje').hidden = !document.getElementById('vz-drugo').checked; }); });
        function dodaj(tpl, gdje, max) { var g = document.getElementById(gdje); if (g.children.length >= max) return;
            g.appendChild(document.getElementById(tpl).content.firstElementChild.cloneNode(true)); var p = g.lastElementChild.querySelector('select,input'); if (p) p.focus(); }
        document.getElementById('dodaj-suputnika').onclick = function () { dodaj('tpl-suputnik', 'suputnici', window.DEZ_MAX[0]); };
        document.getElementById('dodaj-gosta').onclick = function () { dodaj('tpl-gost', 'gosti', window.DEZ_MAX[1]); };
        document.addEventListener('click', function (e) { var u = e.target.closest('[data-ukloni]'); if (u) u.parentElement.closest('.input-group, .border').remove(); });

        function polja() {
            var o = {};
            new FormData(forma).forEach(function (v, k) {
                if (k === '_csrf' || k === 's') return;
                if (k.slice(-2) === '[]') { k = k.slice(0, -2); (o[k] = o[k] || []).push(v); } else o[k] = v;
            });
            return o;
        }
        function imena(p) {
            var n = [ST.ja + (p.vozilo === 'bez' ? '' : ' · ' + oznaka(p))];
            forma.querySelectorAll('select[name="suputnik[]"]').forEach(function (s) { if (s.value) n.push(s.options[s.selectedIndex].text); });
            (p.gost_ime || []).forEach(function (im, i) { if (im) n.push(im + ' ' + (p.gost_prezime[i] || '') + ' (gost)'); });
            return n;
        }
        function oznaka(p) { if (p.vozilo && p.vozilo.charAt(0) === 'v') { var r = forma.querySelector('input[name=vozilo]:checked'); return r ? r.getAttribute('data-oznaka') : ''; } return (p.oznaka || '').toUpperCase(); }
        function spremiLokalno(st) {
            st.t = Math.floor(Date.now() / 1000);
            st.naziv = ST.naziv; st.imena = imena(st.polja);
            var r = red(); r.push(st);
            if (!ls.set(KL_RED, r)) { prikaziGresku('Mobitel ne dopušta spremanje (privatni način preglednika?). Upis nije spremljen.'); return; }
            forma.hidden = true; gps.hidden = true;
            document.getElementById('sp-naslov').textContent = (st.polja.smjer === 'D' ? 'Dolazak' : 'Odlazak') + ' spremljen na mobitelu';
            document.getElementById('sp-vrijeme').textContent = new Date(st.t * 1000).toLocaleTimeString('hr-HR', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('sp-opis').textContent = new Date(st.t * 1000).toLocaleDateString('hr-HR') + ' · ' + ST.naziv;
            var ul = document.getElementById('sp-imena'); ul.innerHTML = '';
            st.imena.forEach(function (x) { var li = document.createElement('li'); li.className = 'list-group-item'; li.textContent = x; ul.appendChild(li); });
            document.getElementById('dez-spremljeno').hidden = false; prikaziRed(); window.scrollTo(0, 0);
        }
        function prikaziGresku(t) { greska.textContent = t; greska.hidden = false; btn.disabled = false; btn.textContent = 'POTVRDI'; window.scrollTo(0, 0); }
        function posaljiOnline(st, csrf, ponovno) {
            return fetch(API, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
                body: JSON.stringify({ stavke: [{ id: st.id, s: st.s, t: null, polja: st.polja }] }) }).then(function (o) {
                if (o.status === 400 && !ponovno) { // istekla stranica (npr. iz memorije) – novi sigurnosni ključ
                    return fetch(API, { credentials: 'same-origin', cache: 'no-store' }).then(function (g) { return g.json(); }).then(function (j) { return posaljiOnline(st, j.csrf, true); });
                }
                if (o.status === 401) { prikaziGresku('Prijava je istekla – prijavite se ponovno pa skenirajte QR oznaku.'); return null; }
                if (!o.ok) throw new Error('http ' + o.status);
                return o.json();
            });
        }
        forma.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!forma.reportValidity()) return;
            greska.hidden = true;
            btn.disabled = true; btn.textContent = 'Spremam…';
            var st = { id: hex16(), s: ST.token, polja: polja() };
            if (navigator.onLine === false) { spremiLokalno(st); return; }
            var csrf = (forma.querySelector('input[name=_csrf]') || {}).value || '';
            var gotov = false;
            var sat = setTimeout(function () { if (!gotov) { gotov = true; spremiLokalno(st); } }, 15000); // slab signal
            posaljiOnline(st, csrf, false).then(function (j) {
                if (gotov) return; gotov = true; clearTimeout(sat);
                if (!j) return;
                var r = (j.rezultati || [])[0] || {};
                if (r.ok) { location.href = r.url; } else { prikaziGresku(r.poruka || 'Upis nije spremljen.'); }
            }).catch(function () { if (!gotov) { gotov = true; clearTimeout(sat); spremiLokalno(st); } });
        });
    }

    sinkroniziraj();
    if (document.readyState === 'complete') predmemoriraj(); else window.addEventListener('load', predmemoriraj);
})();
