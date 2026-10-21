/* Lovačka evidencija – popis sudionika skupnog lova: skeniranje članskih iskaznica (QR).
 * Kamera ostaje otvorena, iskaznice se skeniraju jedna za drugom. Bez signala: ime se prepoznaje iz popisa članova
 * sekcija lova (učitan s internetom), upis čeka na mobitelu (localStorage) i šalje se sam kad bude signala. */
(function () {
    'use strict';
    var C = JSON.parse(document.getElementById('gp-cfg').textContent);
    var $ = function (id) { return document.getElementById(id); };
    var KLJUC = 'ev-gl-popis-' + C.lov;
    var popis = C.popis || [], imenik = {}, poId = {};
    (C.imenik || []).forEach(function (c) { if (c.t) imenik[c.t] = c; poId[c.id] = c; });

    // ---------- red za slanje (preživi zatvaranje stranice) ----------
    function citajRed() { try { return JSON.parse(localStorage.getItem(KLJUC) || '[]') || []; } catch (x) { return []; } }
    function spremiRed(r) { try { if (r.length) localStorage.setItem(KLJUC, JSON.stringify(r)); else localStorage.removeItem(KLJUC); } catch (x) { } }
    var red = citajRed();

    function token(tekst) {
        var t = String(tekst || '').trim().toUpperCase();
        if (t.indexOf('LEI1:') === 0) t = t.slice(5);
        return /^[0-9A-F]{24}$/.test(t) ? t : null;
    }
    function naPopisu(clanId) { return popis.some(function (p) { return p.clan === clanId; }); }
    function uRedu(t) { return red.some(function (x) { return token(x.q) === t; }); }

    // ---------- prikaz ----------
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function slika(f, ime) {
        var ini = String(ime || '?').split(/\s+/).map(function (w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase();
        // bez signala se fotografija ne učita – tada inicijali
        if (f) return '<img src="' + esc(f) + '" class="gp-foto" alt="" loading="lazy" data-ini="' + esc(ini) + '" onerror="this.outerHTML=\'<span class=&quot;gp-foto gp-ini&quot;>\'+this.dataset.ini+\'</span>\'">';
        return '<span class="gp-foto gp-ini">' + esc(ini) + '</span>';
    }
    function prikaziPopis() {
        var el = $('gp-popis'), h = '';
        var cekaju = red.map(function (x) {
            var c = imenik[token(x.q)];
            return { ime: c ? c.ime : (x.q.indexOf('LEG1:') === 0 ? 'Pozivnica gosta' : 'Iskaznica') + ' – provjera kad bude signala', sek: c ? c.sek : '', f: c ? c.f : null, ceka: true, v: x.v };
        });
        var svi = popis.slice().reverse();
        cekaju.slice().reverse().forEach(function (c) {
            var t = new Date(c.v);
            h += '<div class="list-group-item d-flex align-items-center gap-2 gp-ceka">' + slika(c.f, c.ime)
                + '<div class="flex-grow-1 lh-sm"><div class="fw-semibold">' + esc(c.ime) + '</div><div class="text-muted">' + esc(c.sek) + '</div></div>'
                + '<span class="badge text-bg-warning" title="Čeka signal">⏳ ' + ('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2) + '</span></div>';
        });
        svi.forEach(function (p) {
            h += '<div class="list-group-item d-flex align-items-center gap-2">' + slika(p.f, p.ime)
                + '<div class="flex-grow-1 lh-sm"><div class="fw-semibold">' + esc(p.ime) + (p.gost ? ' <span class="badge text-bg-info">gost</span>' : '') + '</div>'
                + '<div class="text-muted">' + esc([p.sek, p.gost && p.pozvao ? 'pozvao ' + p.pozvao : '', p.gost && p.isk ? 'LI ' + p.isk : '', p.gost && p.ol ? 'OL ' + p.ol : '', p.nacin === 'rucno' ? '✍ ručno' : (p.nacin === 'voditelj' ? '🎯 voditelj lova' : ''), p.gost ? (p.pot ? 'potpisan' : 'bez potpisa') : ''].filter(Boolean).join(' · ')) + '</div></div>'
                + (!p.pot ? '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 me-1" data-potpis="' + p.id + '" title="Potpis">✍</button>' : '')
                + '<span class="text-muted">' + esc(p.vrijeme) + '</span>'
                + '<button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" data-ukloni="' + p.id + '" title="Ukloni s popisa" aria-label="Ukloni ' + esc(p.ime) + '">✕</button></div>';
        });
        el.innerHTML = h || '<div class="list-group-item text-muted text-center py-4">Još nitko nije na popisu.</div>';
        var gosti = popis.filter(function (p) { return p.gost; }).length;
        $('gp-broj').textContent = popis.length + cekaju.length;
        $('gp-broj-detalj').textContent = (gosti ? '(gostiju ' + gosti + ')' : '') + (cekaju.length ? ' · ' + cekaju.length + ' čeka signal' : '');
        var r = $('gp-red');
        r.hidden = !red.length;
        if (red.length) r.textContent = '⏳ Skenirano, čeka slanje: ' + red.length + ' – šalje se samo kad bude signala. Ne brišite podatke preglednika.';
        // ručni upis: samo članovi koji još nisu na popisu
        var dl = $('gp-imenik'), o = '';
        (C.imenik || []).forEach(function (c) { if (!naPopisu(c.id)) o += '<option value="' + esc(c.ime + (c.sek ? ' · ' + c.sek : '')) + '">'; });
        dl.innerHTML = o;
    }

    var greske = [];
    function prikaziGreske() {
        $('gp-greske').innerHTML = greske.slice(-5).reverse().map(function (g) {
            return '<div class="alert alert-danger py-1 px-2 mb-1">✖ ' + esc(g) + '</div>';
        }).join('');
    }

    function zadnji(vrsta, ime, opis, f) {
        var el = $('gp-zadnji');
        el.hidden = false;
        el.className = 'gp-zadnji mb-2 gp-' + vrsta;
        el.innerHTML = (vrsta === 'greska' ? '<span class="gp-foto gp-ini">✖</span>' : slika(f, ime))
            + '<div class="lh-sm"><div class="fw-semibold">' + esc(ime) + '</div><div>' + esc(opis) + '</div></div>';
    }

    // ---------- zvuk i vibracija ----------
    var zvuk = null;
    function pisak(ok) {
        try {
            zvuk = zvuk || new (window.AudioContext || window.webkitAudioContext)();
            var o = zvuk.createOscillator(), g = zvuk.createGain();
            o.frequency.value = ok ? 1320 : 330; o.type = 'sine';
            g.gain.value = 0.15; o.connect(g); g.connect(zvuk.destination);
            o.start(); o.stop(zvuk.currentTime + (ok ? 0.12 : 0.35));
        } catch (x) { }
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 80, 200]);
    }

    // ---------- slanje ----------
    var saljem = false;
    function mreza() {
        var b = $('gp-mreza'), on = navigator.onLine !== false;
        b.className = 'badge rounded-pill ' + (on ? 'text-bg-success' : 'text-bg-warning');
        b.textContent = on ? '🟢 Internet' : '📴 Bez signala';
    }
    function odgovor(o) {
        if (!o.ok) throw new Error(o.status === 400 || o.status === 401 ? 'sesija' : 'http');
        return o.json();
    }
    function sinkroniziraj() {
        if (saljem || !red.length || navigator.onLine === false) return Promise.resolve();
        saljem = true;
        var saljemo = red.slice(0, 300);
        return fetch(C.api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': C.csrf },
            body: JSON.stringify({ stavke: saljemo }) })
            .then(odgovor)
            .then(function (j) {
                var gotovo = {};
                (j.rezultati || []).forEach(function (r) {
                    if (r.ok || r.trajno) gotovo[r.k] = 1;
                    if (!r.ok && r.trajno) { greske.push(r.greska || 'Iskaznica nije upisana.'); pisak(false); }
                });
                red = citajRed().filter(function (x) { return !gotovo[x.k]; });
                spremiRed(red);
                if (j.popis) popis = j.popis;
                prikaziGreske(); prikaziPopis();
                return j;
            })
            .catch(function (e) {
                if (e && e.message === 'sesija') {
                    $('gp-poruka').className = 'small text-center text-danger mb-3';
                    $('gp-poruka').textContent = 'Prijava je istekla – osvježite stranicu (skenirano ostaje spremljeno na mobitelu).';
                }
            })
            .then(function (j) { saljem = false; return j || null; });
    }
    window.addEventListener('online', function () { mreza(); sinkroniziraj(); });
    window.addEventListener('offline', mreza);
    setInterval(sinkroniziraj, 10000);
    setInterval(function () {
        // popis i s drugih mobitela (više lovnika skenira istodobno)
        if (navigator.onLine === false || red.length || document.hidden) return;
        fetch(C.api, { credentials: 'same-origin', cache: 'no-store' }).then(odgovor).then(function (j) { if (j.ok && j.popis) { popis = j.popis; prikaziPopis(); } }).catch(function () { });
    }, 30000);

    function nadjeno(tekst) {
        if (String(tekst || '').trim().toUpperCase().indexOf('LEG1:') === 0) { // pozivnica lovnog gosta – provjerava poslužitelj
            var g = String(tekst).trim().toUpperCase();
            if (red.some(function (x) { return x.q === g; })) return;
            var sg = { k: Date.now().toString(36) + Math.random().toString(36).slice(2, 7), q: g, v: Date.now() };
            red.push(sg); spremiRed(red);
            zadnji('ok', 'Pozivnica gosta', navigator.onLine === false ? 'provjera kad bude signala' : 'provjeravam…'); pisak(true); prikaziPopis();
            sinkroniziraj().then(function (j) {
                var r = j && (j.rezultati || []).filter(function (x) { return x.k === sg.k; })[0];
                if (!r) return;
                if (r.ok) { zadnji(r.vec ? 'vec' : 'ok', r.ime, (r.vec ? 'već je na popisu · ' : '✔ upisan · ') + r.sek); if (!r.pot) otvoriPotpis(r.pid, r.ime, { isk: r.isk, ol: r.ol }); }
                else zadnji('greska', 'Pozivnica ne vrijedi', r.greska || '');
            });
            return;
        }
        var t = token(tekst);
        if (!t) { zadnji('greska', 'Nije članska iskaznica', 'Ovaj QR kod nije iskaznica udruge.'); pisak(false); return; }
        var c = imenik[t];
        if (c && naPopisu(c.id) || uRedu(t)) { zadnji('vec', c ? c.ime : 'Iskaznica', 'već je na popisu', c && c.f); pisak(true); return; }
        var stavka = { k: Date.now().toString(36) + Math.random().toString(36).slice(2, 7), q: 'LEI1:' + t, v: Date.now() };
        red.push(stavka);
        spremiRed(red);
        if (c) zadnji('ok', c.ime, '✔ upisan' + (c.sek ? ' · ' + c.sek : ''), c.f);
        else zadnji('ok', 'Iskaznica skenirana', navigator.onLine === false ? 'član druge sekcije – ime stiže kad bude signala' : 'provjeravam…');
        pisak(true);
        prikaziPopis();
        sinkroniziraj().then(function (j) {
            // iskaznica koju mobitel nije poznavao (druga sekcija) – ime i provjera s poslužitelja
            var r = j && (j.rezultati || []).filter(function (x) { return x.k === stavka.k; })[0];
            if (r && r.ok && r.pot === false && r.pid) otvoriPotpis(r.pid, r.ime, r.gost ? { isk: r.isk, ol: r.ol } : null);
            if (c || !r) return;
            if (r.ok) zadnji(r.vec ? 'vec' : 'ok', r.ime, r.vec ? 'već je na popisu' : '✔ upisan' + (r.sek ? ' · ' + r.sek : ''), r.f);
            else zadnji('greska', r.ime || 'Iskaznica ne vrijedi', r.greska || '');
        });
    }

    // ---------- kamera (kontinuirano skeniranje) ----------
    var video = $('gp-video'), kamera = $('gp-kamera'), start = $('gp-start'), poruka = $('gp-poruka');
    var tok = null, platno = document.createElement('canvas'), ctx = platno.getContext('2d', { willReadFrequently: true });
    var detektor = null, radi = false, zadnjiTekst = '', zadnjeVrijeme = 0;
    function stani() {
        radi = false;
        if (tok) { tok.getTracks().forEach(function (t) { t.stop(); }); tok = null; }
        kamera.hidden = true; start.hidden = false;
    }
    function procitano(tekst) {
        var sad = Date.now();
        if (tekst === zadnjiTekst && sad - zadnjeVrijeme < 4000) return; // ista iskaznica još ispred kamere
        zadnjiTekst = tekst; zadnjeVrijeme = sad;
        nadjeno(tekst);
    }
    function okvir() {
        if (!radi) return;
        if (video.readyState >= 2 && video.videoWidth) {
            if (detektor) {
                detektor.detect(video).then(function (k) {
                    if (k && k.length) procitano(k[0].rawValue);
                    setTimeout(okvir, 120);
                }).catch(function () { detektor = null; setTimeout(okvir, 120); });
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
    start.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            poruka.className = 'small text-center text-danger mb-3';
            poruka.textContent = 'Ovaj preglednik ne dopušta kameru. Upišite sudionike ručno.';
            return;
        }
        try { zvuk = zvuk || new (window.AudioContext || window.webkitAudioContext)(); zvuk.resume(); } catch (x) { } // iPhone: zvuk tek nakon dodira
        poruka.className = 'small text-center text-muted mb-3';
        poruka.textContent = 'Dopustite kameru ako mobitel pita…';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (t) {
            tok = t; video.srcObject = t; kamera.hidden = false; start.hidden = true;
            video.setAttribute('playsinline', ''); video.muted = true;
            return video.play();
        }).then(function () {
            poruka.textContent = 'Usmjerite kameru na QR kod iskaznice – sljedeću odmah nakon zvučnog signala.';
            if ('BarcodeDetector' in window) { try { detektor = new BarcodeDetector({ formats: ['qr_code'] }); } catch (x) { detektor = null; } }
            radi = true; okvir();
        }).catch(function (e) {
            stani();
            poruka.className = 'small text-center text-danger mb-3';
            poruka.textContent = e && e.name === 'NotAllowedError'
                ? 'Kamera nije dopuštena. Dopustite kameru u postavkama mobitela za ovu aplikaciju / preglednik.'
                : 'Kamera se ne može pokrenuti.';
        });
    });
    $('gp-stop').addEventListener('click', stani);
    document.addEventListener('visibilitychange', function () { if (document.hidden) stani(); });

    // ---------- ručni upis i uklanjanje (treba signal) ----------
    function posalji(polja) {
        var fd = new FormData();
        Object.keys(polja).forEach(function (k) { fd.append(k, polja[k]); });
        return fetch(C.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': C.csrf } }).then(odgovor).then(function (j) {
            if (j.popis) popis = j.popis;
            if (!j.ok && j.greska) alert(j.greska);
            prikaziPopis();
            return j;
        }).catch(function () { alert('Nema veze s poslužiteljem – pokušajte kad bude signala.'); return { ok: false }; });
    }
    $('gp-clan-dodaj').addEventListener('click', function () {
        var v = $('gp-clan').value.trim(), c = null;
        (C.imenik || []).forEach(function (x) { if (!c && (x.ime + (x.sek ? ' · ' + x.sek : '') === v || x.ime === v)) c = x; });
        if (!c) { alert('Odaberite člana s popisa (počnite upisivati prezime).'); return; }
        posalji({ radnja: 'rucno', clan: c.id }).then(function (j) { if (j.ok) $('gp-clan').value = ''; });
    });
    // potpis prstom u polju (gost pri ručnom upisu, član bez spremljenog potpisa nakon skeniranja)
    function potpisPolje(c) {
        var x = c.getContext('2d'), crta = false, o = { ima: false };
        x.lineWidth = 3; x.lineCap = 'round'; x.lineJoin = 'round'; x.strokeStyle = '#0b2a6b';
        function T(e) { var r = c.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [(t.clientX - r.left) * c.width / r.width, (t.clientY - r.top) * c.height / r.height]; }
        function P(e) { e.preventDefault(); crta = true; var p = T(e); x.beginPath(); x.moveTo(p[0], p[1]); }
        function M(e) { if (!crta) return; e.preventDefault(); var p = T(e); x.lineTo(p[0], p[1]); x.stroke(); o.ima = true; }
        c.addEventListener('mousedown', P); c.addEventListener('mousemove', M); window.addEventListener('mouseup', function () { crta = false; });
        c.addEventListener('touchstart', P, { passive: false }); c.addEventListener('touchmove', M, { passive: false }); c.addEventListener('touchend', function () { crta = false; });
        o.ocisti = function () { x.clearRect(0, 0, c.width, c.height); o.ima = false; };
        o.png = function () { return c.toDataURL('image/png'); };
        return o;
    }
    var gp = potpisPolje($('gp-gost-potpis')), gc = { toDataURL: gp.png };
    function gOcisti() { gp.ocisti(); }
    $('gp-gost-ocisti').addEventListener('click', gOcisti);

    // ---------- potpis člana bez spremljenog potpisa ----------
    var cp = potpisPolje($('gp-potpis-platno')), potpisPid = null, potpisRed = [];
    var potpisGost = null;
    function otvoriPotpis(pid, ime, gost) {
        if (potpisPid === pid) return;
        if (potpisPid !== null) { if (potpisRed.indexOf(pid) < 0) potpisRed.push(pid); return; }
        potpisPid = pid; cp.ocisti();
        if (!gost) { var pp = popis.filter(function (x) { return x.id === pid; })[0]; if (pp && pp.gost) gost = { isk: pp.isk, ol: pp.ol }; }
        potpisGost = gost || null;
        $('gp-potpis-gost').hidden = !gost; $('gp-potpis-spremi-red').hidden = !!gost;
        if (gost) { $('gp-potpis-isk').value = gost.isk || ''; $('gp-potpis-ol').value = gost.ol || ''; }
        $('gp-potpis-naslov').textContent = '✍ ' + ime + (gost ? ' (gost) – ' + (gost.isk && gost.ol ? 'potpis' : 'dopunite podatke i potpis') : ' – potpis (nema spremljen potpis)');
        $('gp-potpis').hidden = false;
    }
    function zatvoriPotpis() {
        $('gp-potpis').hidden = true; potpisPid = null;
        var sl = potpisRed.shift();
        if (sl != null) { var p = popis.filter(function (x) { return x.id === sl; })[0]; if (p && !p.pot) otvoriPotpis(p.id, p.ime); }
    }
    $('gp-potpis-ocisti').addEventListener('click', cp.ocisti);
    $('gp-potpis-kasnije').addEventListener('click', zatvoriPotpis);
    $('gp-potpis-spremi-btn').addEventListener('click', function () {
        if (!cp.ima) { alert('Najprije se potpišite u polju.'); return; }
        var pid = potpisPid, polja = { radnja: 'potpis', pid: pid, potpis: cp.png(), spremi: $('gp-potpis-spremi').checked ? 1 : '' };
        if (potpisGost) {
            polja.iskaznica = $('gp-potpis-isk').value.trim(); polja.oruzni = $('gp-potpis-ol').value.trim();
            if (!polja.iskaznica || !polja.oruzni) { alert('Upišite broj lovačke iskaznice i oružni list gosta.'); return; }
        }
        posalji(polja).then(function (j) { if (j.ok) zatvoriPotpis(); });
    });
    $('gp-gost-dodaj').addEventListener('click', function () {
        var v = $('gp-gost').value.trim(), isk = $('gp-gost-isk').value.trim(), ol = $('gp-gost-ol').value.trim();
        if (v.length < 3) { alert('Upišite ime i prezime gosta.'); return; }
        if (!isk || !ol) { alert('Upišite broj lovačke iskaznice i oružni list gosta.'); return; }
        if (!gp.ima && !confirm('Gost se nije potpisao. Dodati ga bez potpisa?')) return;
        posalji({ radnja: 'rucno', gost: v, gost_iskaznica: isk, gost_oruzni: ol, gost_potpis: gp.ima ? gp.png() : '' }).then(function (j) {
            if (j.ok) { $('gp-gost').value = ''; $('gp-gost-isk').value = ''; $('gp-gost-ol').value = ''; gOcisti(); }
        });
    });
    $('gp-popis').addEventListener('click', function (e) {
        var bp = e.target.closest('[data-potpis]');
        if (bp) { var pp = popis.filter(function (x) { return String(x.id) === bp.dataset.potpis; })[0]; if (pp) { potpisPid = null; otvoriPotpis(pp.id, pp.ime); } return; }
        var b = e.target.closest('[data-ukloni]');
        if (!b) return;
        var p = popis.filter(function (x) { return String(x.id) === b.dataset.ukloni; })[0];
        if (p && confirm('Ukloniti ' + p.ime + ' s popisa?')) posalji({ radnja: 'ukloni', pid: p.id });
    });

    mreza();
    prikaziPopis();
    sinkroniziraj();
})();
