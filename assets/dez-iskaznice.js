/* Lovačka evidencija – mobilna stanica: upis lovaca skeniranjem članskih iskaznica (QR).
 * Samo ovlašteni (najviše 5 po stanici). Kamera ostaje otvorena; smjer (dolazak/odlazak) bira se jednom gore.
 * Vozilo: jedno → samo, više → odabir, nijedno → bez vozila. Uz svaki upis šalje se položaj OVOG mobitela (provjera kruga stanice).
 * Bez signala: ime i vozila prepoznaju se iz popisa članova sekcije (učitan s internetom), upis čeka na mobitelu i šalje se sam. */
(function () {
    'use strict';
    var C = JSON.parse(document.getElementById('di-cfg').textContent);
    var $ = function (id) { return document.getElementById(id); };
    var KLJUC = 'ev-dez-isk-' + C.a;
    var upisi = C.upisi || [], imenik = {};
    (C.imenik || []).forEach(function (c) { if (c.t) imenik[c.t] = c; });

    function citajRed() { try { return JSON.parse(localStorage.getItem(KLJUC) || '[]') || []; } catch (x) { return []; } }
    function spremiRed(r) { try { if (r.length) localStorage.setItem(KLJUC, JSON.stringify(r)); else localStorage.removeItem(KLJUC); } catch (x) { } }
    var red = citajRed();

    function token(tekst) {
        var t = String(tekst || '').trim().toUpperCase();
        if (t.indexOf('LEI1:') === 0) t = t.slice(5);
        return /^[0-9A-F]{24}$/.test(t) ? t : null;
    }
    function smjer() { return $('di-o').checked ? 'O' : 'D'; }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function slika(f, ime) {
        var ini = String(ime || '?').split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase();
        if (f) return '<img src="' + esc(f) + '" class="gp-foto" alt="" loading="lazy" data-ini="' + esc(ini) + '" onerror="this.outerHTML=\'<span class=&quot;gp-foto gp-ini&quot;>\'+this.dataset.ini+\'</span>\'">';
        return '<span class="gp-foto gp-ini">' + esc(ini) + '</span>';
    }
    function sat(ms) { var t = new Date(ms); return ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2); }

    // ---------- GPS ovog mobitela ----------
    var gps = null;
    if (navigator.geolocation && window.isSecureContext) {
        navigator.geolocation.watchPosition(function (p) {
            gps = { lat: p.coords.latitude, lon: p.coords.longitude, acc: Math.round(p.coords.accuracy), t: Date.now() };
            $('di-gps').textContent = '📍 lokacija ±' + gps.acc + ' m';
        }, function (e) {
            $('di-gps').className = 'small text-center text-danger mb-2';
            $('di-gps').textContent = e.code === 1 ? '📍 Lokacija nije dopuštena – upis bez lokacije bit će označen.' : '📍 Lokacija nije dostupna.';
        }, { enableHighAccuracy: true, maximumAge: 15000 });
    } else {
        $('di-gps').textContent = '📍 Lokacija radi samo preko https:// adrese.';
    }

    // ---------- prikaz ----------
    function prikazi() {
        var h = '';
        red.slice().reverse().forEach(function (x) {
            var c = imenik[token(x.q)];
            h += '<div class="list-group-item d-flex align-items-center gap-2 gp-ceka">' + slika(c && c.f, c ? c.ime : '?')
                + '<div class="flex-grow-1 lh-sm"><div class="fw-semibold">' + esc(c ? c.ime : 'Iskaznica – provjera kad bude signala') + '</div>'
                + '<div class="text-muted">' + (x.smjer === 'O' ? '⬆ odlazak' : '⬇ dolazak') + (x.oz ? ' · 🚗 ' + esc(x.oz) : '') + '</div></div>'
                + '<span class="badge text-bg-warning" title="Čeka signal">⏳ ' + sat(x.v) + '</span></div>';
        });
        upisi.forEach(function (u) {
            h += '<div class="list-group-item d-flex align-items-center gap-2">'
                + '<span class="badge ' + (u.s === 'O' ? 'bg-secondary' : 'bg-success') + '">' + (u.s === 'O' ? '⬆' : '⬇') + '</span>'
                + '<div class="flex-grow-1 lh-sm"><div class="fw-semibold">' + esc(u.ime) + '</div>'
                + '<div class="text-muted">' + esc([u.oz ? '🚗 ' + u.oz : 'bez vozila', u.tko ? 'skenirao ' + u.tko : '', u.izv ? 'offline' : ''].filter(Boolean).join(' · ')) + '</div></div>'
                + '<span class="text-muted">' + esc(u.vr) + '</span></div>';
        });
        $('di-popis').innerHTML = h || '<div class="list-group-item text-muted text-center py-4">Još nitko nije upisan skeniranjem.</div>';
        var d = upisi.filter(function (u) { return u.s === 'D'; }).length;
        $('di-broj').textContent = upisi.length + red.length;
        $('di-broj-detalj').textContent = '(⬇ ' + d + ' · ⬆ ' + (upisi.length - d) + ')' + (red.length ? ' · ' + red.length + ' čeka signal' : '');
        var r = $('di-red');
        r.hidden = !red.length;
        if (red.length) r.textContent = '⏳ Skenirano, čeka slanje: ' + red.length + ' – šalje se kad bude signala. Ne brišite podatke preglednika.';
    }
    var greske = [];
    function prikaziGreske() {
        $('di-greske').innerHTML = greske.slice(-5).reverse().map(function (g) { return '<div class="alert alert-danger py-1 px-2 mb-1">✖ ' + esc(g) + '</div>'; }).join('');
    }
    function zadnji(vrsta, ime, opis, f) {
        var el = $('di-zadnji');
        el.hidden = false;
        el.className = 'gp-zadnji mb-2 gp-' + vrsta;
        el.innerHTML = (vrsta === 'greska' ? '<span class="gp-foto gp-ini">✖</span>' : slika(f, ime))
            + '<div class="lh-sm"><div class="fw-semibold">' + esc(ime) + '</div><div>' + esc(opis) + '</div></div>';
    }
    var zvuk = null;
    function pisak(ok) {
        try {
            zvuk = zvuk || new (window.AudioContext || window.webkitAudioContext)();
            var o = zvuk.createOscillator(), g = zvuk.createGain();
            o.frequency.value = ok ? 1320 : 330; o.type = 'sine'; g.gain.value = 0.15;
            o.connect(g); g.connect(zvuk.destination); o.start(); o.stop(zvuk.currentTime + (ok ? 0.12 : 0.35));
        } catch (x) { }
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 80, 200]);
    }

    // ---------- slanje ----------
    var saljem = false;
    function mreza() {
        var b = $('di-mreza'), on = navigator.onLine !== false;
        b.className = 'badge rounded-pill ' + (on ? 'text-bg-success' : 'text-bg-warning');
        b.textContent = on ? '🟢 Internet' : '📴 Bez signala';
    }
    function odgovor(o) { if (!o.ok) throw new Error(o.status === 400 || o.status === 401 ? 'sesija' : 'http'); return o.json(); }
    function zatvoreno(j) {
        if (j && j.zatvoreno) {
            stani(); $('di-start').disabled = true;
            $('di-poruka').className = 'small text-center text-danger mb-2';
            $('di-poruka').textContent = j.greska || 'Stanica je zatvorena.';
            return true;
        }
        return false;
    }
    function sinkroniziraj() {
        if (saljem || !red.length || navigator.onLine === false) return Promise.resolve();
        saljem = true;
        return fetch(C.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': C.csrf },
            body: JSON.stringify({ stavke: red.slice(0, 300) }) })
            .then(odgovor)
            .then(function (j) {
                if (zatvoreno(j)) return j;
                var gotovo = {};
                (j.rezultati || []).forEach(function (r) {
                    if (r.ok || r.trajno) gotovo[r.k] = 1;
                    if (!r.ok && r.trajno) { greske.push(r.greska || 'Upis nije uspio.'); pisak(false); }
                });
                red = citajRed().filter(function (x) { return !gotovo[x.k]; });
                spremiRed(red);
                if (j.upisi) upisi = j.upisi;
                prikaziGreske(); prikazi();
                return j;
            })
            .catch(function (e) {
                if (e && e.message === 'sesija') {
                    $('di-poruka').className = 'small text-center text-danger mb-2';
                    $('di-poruka').textContent = 'Prijava je istekla – osvježite stranicu (skenirano ostaje spremljeno na mobitelu).';
                }
            })
            .then(function (j) { saljem = false; return j || null; });
    }
    window.addEventListener('online', function () { mreza(); sinkroniziraj(); });
    window.addEventListener('offline', mreza);
    setInterval(sinkroniziraj, 10000);
    setInterval(function () { // upisi i s drugih mobitela (do 5 skenera istodobno)
        if (navigator.onLine === false || red.length || document.hidden) return;
        fetch(C.api, { credentials: 'same-origin', cache: 'no-store' }).then(odgovor).then(function (j) { if (!zatvoreno(j) && j.upisi) { upisi = j.upisi; prikazi(); } }).catch(function () { });
    }, 30000);

    // ---------- upis ----------
    var cekaVozilo = false;
    function upisi_(t, c, vozilo, oz) {
        var st = { k: Date.now().toString(36) + Math.random().toString(36).slice(2, 7), q: 'LEI1:' + t, smjer: smjer(), vozilo: vozilo || '', oz: oz || '',
            v: Date.now(), o: navigator.onLine === false ? 1 : 0 };
        if (gps && Date.now() - gps.t < 60000) { st.lat = gps.lat; st.lon = gps.lon; st.acc = gps.acc; }
        red.push(st); spremiRed(red);
        var opis = '✔ ' + (st.smjer === 'O' ? 'odlazak' : 'dolazak') + (oz ? ' · 🚗 ' + oz : (vozilo === 'bez' ? ' · bez vozila' : ''));
        if (c) zadnji('ok', c.ime, opis, c.f);
        else zadnji('ok', 'Iskaznica skenirana', navigator.onLine === false ? 'član druge sekcije – provjera kad bude signala (zadano vozilo)' : 'provjeravam…');
        pisak(true); prikazi();
        sinkroniziraj().then(function (j) {
            var r = j && (j.rezultati || []).filter(function (x) { return x.k === st.k; })[0];
            if (!r) return;
            if (r.ok) zadnji(r.vec ? 'vec' : 'ok', r.ime, r.vec ? 'već upisan (isti smjer, zadnje 2 min)' : opis.replace(/ · 🚗.*$/, '') + (r.oznaka ? ' · 🚗 ' + r.oznaka : ' · bez vozila'), r.f);
            else zadnji('greska', r.ime || 'Upis nije uspio', r.greska || '');
        });
    }
    function biraj(t, c) {
        cekaVozilo = true;
        var box = $('di-vozilo'), g = $('di-vozilo-gumbi');
        $('di-vozilo-naslov').textContent = c.ime + ' – kojim vozilom?';
        g.innerHTML = c.v.map(function (v) { return '<button type="button" class="btn btn-primary btn-lg" data-v="v' + v.id + '" data-o="' + esc(v.o) + '">🚗 ' + esc(v.o) + '</button>'; }).join('')
            + '<button type="button" class="btn btn-outline-secondary btn-lg" data-v="bez">bez vozila</button>';
        box.hidden = false;
        zadnji('vec', c.ime, 'odaberite vozilo', c.f);
        pisak(true);
        g.onclick = function (e) {
            var b = e.target.closest('[data-v]'); if (!b) return;
            box.hidden = true; cekaVozilo = false;
            upisi_(t, c, b.dataset.v, b.dataset.o || '');
        };
    }
    function nadjeno(tekst) {
        if (cekaVozilo) return;
        var t = token(tekst);
        if (!t) { zadnji('greska', 'Nije članska iskaznica', 'Ovaj QR kod nije iskaznica udruge.'); pisak(false); return; }
        var c = imenik[t];
        if (!c) { upisi_(t, null, '', ''); return; }           // druga sekcija – poslužitelj uzima zadano vozilo
        if (c.v.length > 1) { biraj(t, c); return; }
        upisi_(t, c, c.v.length ? 'v' + c.v[0].id : 'bez', c.v.length ? c.v[0].o : '');
    }

    // ---------- kamera ----------
    var video = $('di-video'), kamera = $('di-kamera'), start = $('di-start'), poruka = $('di-poruka');
    var tok = null, platno = document.createElement('canvas'), ctx = platno.getContext('2d', { willReadFrequently: true });
    var detektor = null, radi = false, zadnjiTekst = '', zadnjeVrijeme = 0;
    function stani() {
        radi = false;
        if (tok) { tok.getTracks().forEach(function (t) { t.stop(); }); tok = null; }
        kamera.hidden = true; start.hidden = false;
    }
    function procitano(tekst) {
        var sad = Date.now();
        if (tekst === zadnjiTekst && sad - zadnjeVrijeme < 4000) return;
        zadnjiTekst = tekst; zadnjeVrijeme = sad;
        nadjeno(tekst);
    }
    function okvir() {
        if (!radi) return;
        if (video.readyState >= 2 && video.videoWidth && !cekaVozilo) {
            if (detektor) {
                detektor.detect(video).then(function (k) { if (k && k.length) procitano(k[0].rawValue); setTimeout(okvir, 120); })
                    .catch(function () { detektor = null; setTimeout(okvir, 120); });
                return;
            }
            var m = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
            platno.width = Math.round(video.videoWidth * m); platno.height = Math.round(video.videoHeight * m);
            ctx.drawImage(video, 0, 0, platno.width, platno.height);
            var s = ctx.getImageData(0, 0, platno.width, platno.height);
            var k = window.jsQR && jsQR(s.data, s.width, s.height, { inversionAttempts: 'dontInvert' });
            if (k && k.data) procitano(k.data);
        }
        setTimeout(okvir, 120);
    }
    $('di-potvrda').addEventListener('change', function () { start.disabled = !this.checked; });
    start.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            poruka.className = 'small text-center text-danger mb-2'; poruka.textContent = 'Ovaj preglednik ne dopušta kameru.'; return;
        }
        try { zvuk = zvuk || new (window.AudioContext || window.webkitAudioContext)(); zvuk.resume(); } catch (x) { }
        poruka.className = 'small text-center text-muted mb-2'; poruka.textContent = 'Dopustite kameru ako mobitel pita…';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (t) {
            tok = t; video.srcObject = t; kamera.hidden = false; start.hidden = true; video.setAttribute('playsinline', ''); video.muted = true;
            return video.play();
        }).then(function () {
            poruka.textContent = 'Usmjerite kameru na QR iskaznice – sljedeću nakon zvučnog signala. Smjer mijenjate gore.';
            if ('BarcodeDetector' in window) { try { detektor = new BarcodeDetector({ formats: ['qr_code'] }); } catch (x) { detektor = null; } }
            radi = true; okvir();
        }).catch(function (e) {
            stani();
            poruka.className = 'small text-center text-danger mb-2';
            poruka.textContent = e && e.name === 'NotAllowedError' ? 'Kamera nije dopuštena – dopustite je u postavkama mobitela.' : 'Kamera se ne može pokrenuti.';
        });
    });
    $('di-stop').addEventListener('click', stani);
    document.addEventListener('visibilitychange', function () { if (document.hidden) stani(); });
    document.querySelectorAll('input[name=di-smjer]').forEach(function (r) {
        r.addEventListener('change', function () {
            $('di-d').nextElementSibling.className = 'btn btn-lg ' + (smjer() === 'D' ? 'btn-success' : 'btn-outline-success');
            $('di-o').nextElementSibling.className = 'btn btn-lg ' + (smjer() === 'O' ? 'btn-secondary' : 'btn-outline-secondary');
        });
    });
    mreza(); prikazi(); sinkroniziraj();
})();
