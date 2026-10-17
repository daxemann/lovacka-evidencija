/* Lovačka evidencija – grupni lov: karta sudionika uživo, slanje vlastitog položaja, SOS. */
(function () {
    'use strict';
    var cfgEl = document.getElementById('gl-cfg');
    if (!cfgEl || !window.L) return;
    var cfg = JSON.parse(cfgEl.textContent);
    var st = cfg.stanje;
    var traka = document.getElementById('gl-traka');
    var sosTraka = document.getElementById('gl-sos-traka');
    var popis = document.getElementById('gl-sudionici');
    var brojevi = document.getElementById('gl-brojevi');
    var sosBtn = document.getElementById('gl-sos');
    var markeri = {}, krugovi = {};
    var fix = null, watchId = null, zadnjeSlanje = 0, wake = null, prvi = true, sosNaCekanju = false, sosNaoruzan = 0;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function poruka(t, greska) {
        var o = document.getElementById('obavijest');
        if (!o) return;
        o.innerHTML = '<div class="flex-grow-1' + (greska ? ' text-danger fw-semibold' : '') + '">' + esc(t) + '</div>';
        o.style.display = 'flex';
        clearTimeout(poruka.t); poruka.t = setTimeout(function () { o.style.display = 'none'; }, greska ? 7000 : 3500);
    }
    function inicijali(ime) { return ime.split(/\s+/).filter(Boolean).map(function (x) { return x[0]; }).join('').slice(0, 2).toUpperCase(); }
    var OPIS = { uzivo: 'uživo', pozadina: 'zaslon ugašen', signal: 'nema signala', 'bez-gps': 'bez GPS-a', dolazi: 'dolazi – još nije na karti', otisao: 'otišao', ne: 'ne dolazi', ceka: 'bez odgovora' };

    // ---------- karta (isti slojevi kao karta lovišta) ----------
    var karta = L.map('gl-karta', { zoomControl: true, tap: true });
    var satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 18, attribution: 'Snimke &copy; Esri, Maxar, Earthstar Geographics' });
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
    var topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenTopoMap (CC-BY-SA), OpenStreetMap' });
    satelit.addTo(karta);
    L.control.layers({ 'Satelit': satelit, 'Karta': osm, 'Topografska': topo }, null, { position: 'topright' }).addTo(karta);
    L.control.scale({ imperial: false }).addTo(karta);
    var granice = L.featureGroup().addTo(karta);
    (cfg.granice || []).forEach(function (g) {
        var stil = { color: '#ffd400', weight: 3, opacity: 0.95, fillColor: '#ffd400', fillOpacity: 0.04, interactive: false };
        (g.tip === 'poligon' ? L.polygon(g.koord, stil) : L.polyline(g.koord, stil)).addTo(granice);
    });
    var sloj = L.featureGroup().addTo(karta);
    if (cfg.centar) karta.setView([cfg.centar[0], cfg.centar[1]], cfg.centar[2]);
    else if (granice.getLayers().length) karta.fitBounds(granice.getBounds(), { padding: [10, 10] });
    else karta.setView([45.1, 16.4], 7);

    function klasa(s) { return s.sos ? 'sos' : s.st; }
    function ikona(s) {
        return L.divIcon({ className: 'revir-pin-omot', iconSize: [34, 34], iconAnchor: [17, 17],
            html: '<div class="gl-pin ' + klasa(s) + (s.ja ? ' ja' : '') + '">' + esc(inicijali(s.ime)) + '</div><div class="revir-pin-ime">' + esc(s.ja ? 'ja' : s.ime.split(' ')[0]) + '</div>' });
    }
    function opis(s) {
        var t = '<b>' + esc(s.ime) + '</b>' + (s.sekcija ? ' <span class="text-muted">· ' + esc(s.sekcija) + '</span>' : '') + '<br>' + esc(OPIS[s.st] || s.st);
        if (s.vrijeme && s.st !== 'uzivo') t += ' · zadnji položaj ' + esc(s.vrijeme) + (s.prije ? ' (prije ' + s.prije + ' min)' : '');
        if (s.toc) t += '<br><span class="text-muted">točnost ±' + s.toc + ' m</span>';
        if (s.sos) t += '<br><b class="text-danger">🆘 SOS od ' + esc(s.sos.od) + '</b>' + (s.sos.preuzeo ? ' – preuzeo ' + esc(s.sos.preuzeo) : '');
        if (s.tel) t += '<br><a href="tel:' + esc(s.tel) + '">📞 ' + esc(s.tel) + '</a>';
        if (s.lat !== null) t += '<br><a href="https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lon + '" target="_blank" rel="noopener">🧭 Navigacija</a>';
        return t;
    }

    function crtaj() {
        var vidljivi = {};
        st.sudionici.forEach(function (s) {
            var lat = s.sos && s.sos.lat !== null ? s.sos.lat : s.lat, lon = s.sos && s.sos.lon !== null ? s.sos.lon : s.lon;
            if (lat === null || lat === undefined) return;
            vidljivi[s.id] = true;
            if (!markeri[s.id]) {
                markeri[s.id] = L.marker([lat, lon], { icon: ikona(s), zIndexOffset: s.sos ? 1000 : (s.ja ? 500 : 0) }).addTo(sloj).bindPopup('');
            } else {
                markeri[s.id].setLatLng([lat, lon]).setIcon(ikona(s)).setZIndexOffset(s.sos ? 1000 : (s.ja ? 500 : 0));
            }
            markeri[s.id].setPopupContent(opis(s));
            if (s.toc && s.toc > 30 && s.st === 'uzivo') {
                if (!krugovi[s.id]) krugovi[s.id] = L.circle([lat, lon], { radius: s.toc, color: '#2e7d32', weight: 1, fillOpacity: 0.08, interactive: false }).addTo(sloj);
                else krugovi[s.id].setLatLng([lat, lon]).setRadius(s.toc);
            } else if (krugovi[s.id]) { sloj.removeLayer(krugovi[s.id]); delete krugovi[s.id]; }
        });
        Object.keys(markeri).forEach(function (id) {
            if (!vidljivi[id]) { sloj.removeLayer(markeri[id]); delete markeri[id]; if (krugovi[id]) { sloj.removeLayer(krugovi[id]); delete krugovi[id]; } }
        });
        if (prvi && sloj.getLayers().length) {
            prvi = false;
            if (cfg.fokus && markeri[cfg.fokus]) { karta.setView(markeri[cfg.fokus].getLatLng(), 16); markeri[cfg.fokus].openPopup(); }
            else if (sloj.getLayers().length) karta.fitBounds(sloj.getBounds(), { padding: [40, 40], maxZoom: 16 });
        }
    }

    function redPopisa(s) {
        var desno = s.sos ? '<span class="badge bg-danger">🆘 ' + esc(s.sos.od) + '</span>' : '<span class="small text-muted">' + esc(OPIS[s.st] || s.st) + (s.vrijeme && s.st !== 'uzivo' ? ' · ' + esc(s.vrijeme) : '') + '</span>';
        var gumbi = '';
        if (s.sos && !s.sos.preuzeo && !s.ja) gumbi += '<button type="button" class="btn btn-sm btn-danger mt-1" data-preuzmi="' + s.id + '">🚑 Idem pomoći</button> ';
        if (s.tel) gumbi += '<a class="btn btn-sm btn-outline-secondary mt-1" href="tel:' + esc(s.tel) + '">📞</a>';
        return '<div class="list-group-item px-1 gl-red ' + klasa(s) + '" data-k="' + s.id + '"><div class="d-flex align-items-center gap-2">'
            + '<i class="gl-tocka ' + klasa(s) + '"></i><span class="flex-grow-1">' + esc(s.ime) + (s.ja ? ' <b>(ja)</b>' : '') + '</span>' + desno + '</div>'
            + (s.sos && s.sos.preuzeo ? '<div class="small text-success">Preuzeo: ' + esc(s.sos.preuzeo) + '</div>' : '') + (gumbi ? '<div>' + gumbi + '</div>' : '') + '</div>';
    }
    var REDOSLIJED = { sos: 0, signal: 1, pozadina: 2, 'bez-gps': 3, uzivo: 4, dolazi: 5, ceka: 6, otisao: 7, ne: 8 };
    function prikazi() {
        var b = st.broj;
        brojevi.innerHTML = '<span class="badge bg-success">✔ dolazi ' + b.dolazi + '</span>' + (st.org ? '<span class="badge bg-secondary">✖ ne dolazi ' + b.ne + '</span><span class="badge bg-light text-dark border">? bez odgovora ' + b.ceka + '</span>' : '')
            + (b.otisao ? '<span class="badge bg-light text-dark border">otišli ' + b.otisao + '</span>' : '') + '<span class="text-muted ms-auto">osvježeno ' + esc(st.vrijeme.slice(0, 5)) + '</span>';
        var l = st.sudionici.slice().sort(function (a, c) { return (REDOSLIJED[klasa(a)] - REDOSLIJED[klasa(c)]) || a.ime.localeCompare(c.ime, 'hr'); });
        popis.innerHTML = l.map(redPopisa).join('') || '<div class="text-muted p-2">Još nitko nije na karti.</div>';
        // SOS – crvena traka za organizatora
        var sos = st.sudionici.filter(function (s) { return s.sos; });
        sos = sos.filter(function (s) { return !s.ja; });
        if (sos.length) {
            sosTraka.hidden = false;
            sosTraka.innerHTML = sos.map(function (s) { return '🆘 <b>' + esc(s.ime) + '</b> od ' + esc(s.sos.od) + (s.sos.preuzeo ? ' – preuzeo ' + esc(s.sos.preuzeo) : ' – <b>nitko nije preuzeo!</b>') + ' <a href="#" data-k="' + s.id + '">pokaži</a>' + (s.tel ? ' · <a href="tel:' + esc(s.tel) + '">📞 ' + esc(s.tel) + '</a>' : '') + (!s.sos.preuzeo ? ' <button type="button" class="btn btn-sm btn-light ms-1" data-preuzmi="' + s.id + '">🚑 Idem pomoći</button>' : ''); }).join('<br>');
        } else sosTraka.hidden = true;
        // vlastito stanje (sudionik)
        if (st.ja && cfg.saljem) {
            var t = '';
            if (st.ja.sos) t = '<div class="alert alert-danger py-2 mb-0">🆘 <b>SOS je poslan</b> organizatoru' + (st.ja.preuzeo ? ' – <b>' + esc(st.ja.preuzeo) + ' je primio, pomoć stiže.</b>' : ' – čeka se potvrda.')
                + ' Ostanite na mjestu, karta neka bude otvorena. <a href="tel:112" class="btn btn-sm btn-light ms-1">📞 112</a>'
                + (st.orgTel ? ' <a href="tel:' + esc(st.orgTel) + '" class="btn btn-sm btn-light">📞 Organizator</a>' : '')
                + ' <button type="button" class="btn btn-sm btn-outline-danger ms-1" id="gl-sos-otkazi">Sve u redu – otkaži SOS</button></div>';
            else if (sosNaCekanju) t = '<div class="alert alert-danger py-2 mb-0">🆘 SOS čeka signal – šalje se sam čim se veza vrati. <a href="tel:112" class="btn btn-sm btn-light ms-1">📞 112</a></div>';
            else if (!fix) t = '<div class="alert alert-warning py-2 mb-0">📍 Čekam GPS… Dopustite lokaciju za ovu stranicu.</div>';
            traka.innerHTML = t; traka.hidden = !t;
            if (sosBtn) sosBtn.hidden = !!st.ja.sos;
        }
        crtaj();
    }

    // ---------- komunikacija ----------
    function posalji(radnja, polja) {
        var fd = new FormData();
        fd.append('radnja', radnja); fd.append('_csrf', cfg.csrf);
        Object.keys(polja || {}).forEach(function (k) { if (polja[k] !== null && polja[k] !== undefined) fd.append(k, polja[k]); });
        return fetch(cfg.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': cfg.csrf } }).then(function (r) { return r.json(); }).then(obradi);
    }
    function osvjezi() { return fetch(cfg.api, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(obradi).catch(function () { }); }
    function obradi(j) {
        if (j.greska && !j.stanje) { poruka(j.greska, true); return j; }
        if (j.greska) poruka(j.greska, true);
        if (j.stanje) {
            st = j.stanje;
            if (!st.aktivan) { zaustavi(); location.reload(); return j; }
            prikazi();
        }
        return j;
    }
    function polozaj() { return fix ? { lat: fix.lat, lon: fix.lon, toc: Math.round(fix.toc) } : {}; }
    function javiSe() {
        zadnjeSlanje = Date.now();
        return posalji('polozaj', polozaj()).catch(function () { });
    }

    // ---------- praćenje vlastitog položaja ----------
    function zakljucajZaslon() {
        if (!('wakeLock' in navigator) || wake) return;
        navigator.wakeLock.request('screen').then(function (w) { wake = w; w.addEventListener('release', function () { wake = null; }); }).catch(function () { });
    }
    function pokreni() {
        if (!cfg.saljem || !navigator.geolocation) return;
        watchId = navigator.geolocation.watchPosition(function (p) {
            var novi = { lat: p.coords.latitude, lon: p.coords.longitude, toc: p.coords.accuracy || 0 };
            var prviFix = !fix, pomak = fix ? karta.distance([fix.lat, fix.lon], [novi.lat, novi.lon]) : 1e9;
            fix = novi;
            // prvi GPS odmah, zatim kad se pomakne > 25 m (inače redovito svakih N sekundi)
            if (prviFix || (pomak > 25 && Date.now() - zadnjeSlanje > 5000)) { javiSe(); prikazi(); }
        }, function (g) {
            if (g.code === 1) { traka.hidden = false; traka.innerHTML = '<div class="alert alert-danger py-2 mb-0">📍 Lokacija je blokirana – dopustite je u pregledniku (ikona lokota u adresnoj traci), inače vas organizator ne vidi.</div>'; }
        }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 30000 });
        zakljucajZaslon();
    }
    function zaustavi() {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        clearInterval(petlja);
        if (wake) { wake.release().catch(function () { }); wake = null; }
    }
    var petlja = setInterval(function () {
        if (document.hidden) return;
        if (sosNaCekanju) { posaljiSos(); return; }
        if (cfg.saljem && watchId !== null && Date.now() - zadnjeSlanje >= cfg.interval * 1000 - 500) javiSe();
        else if (!cfg.saljem || watchId === null) osvjezi();
        else if (Date.now() - zadnjeSlanje > 9000) osvjezi();
    }, 10000);
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            // zaslon ugašen / druga aplikacija – organizator vidi „zaslon ugašen“, a ne gubitak signala
            if (cfg.saljem && navigator.sendBeacon) { var fd = new FormData(); fd.append('radnja', 'skriveno'); fd.append('_csrf', cfg.csrf); navigator.sendBeacon(cfg.api, fd); }
        } else {
            zakljucajZaslon();
            cfg.saljem && watchId !== null ? javiSe() : osvjezi();
        }
    });

    // ---------- SOS ----------
    function posaljiSos() {
        var p = polozaj();
        return posalji('sos', p).then(function (j) {
            sosNaCekanju = false;
            if (j.ok) { poruka('🆘 SOS je poslan organizatoru.'); if (navigator.vibrate) navigator.vibrate([200, 100, 200]); }
            prikazi();
        }).catch(function () { sosNaCekanju = true; prikazi(); });
    }
    if (sosBtn) {
        sosBtn.addEventListener('click', function () {
            // dva dodira u 4 s – da ne ode slučajno iz džepa
            if (Date.now() - sosNaoruzan > 4000) {
                sosNaoruzan = Date.now();
                sosBtn.classList.add('naoruzan'); sosBtn.textContent = 'Dodirnite još jednom za SOS';
                setTimeout(function () { if (Date.now() - sosNaoruzan >= 4000) { sosBtn.classList.remove('naoruzan'); sosBtn.textContent = '🆘 SOS'; } }, 4100);
                return;
            }
            sosNaoruzan = 0; sosBtn.classList.remove('naoruzan'); sosBtn.textContent = '🆘 SOS';
            sosNaCekanju = true; prikazi(); posaljiSos();
        });
    }
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (t.id === 'gl-sos-otkazi') { if (confirm('Sve je u redu? SOS se otkazuje i organizator dobiva obavijest.')) posalji('sos-otkazi'); return; }
        var p = t.closest && t.closest('[data-preuzmi]');
        if (p) { posalji('sos-preuzmi', { k: p.getAttribute('data-preuzmi') }).then(function (j) { if (j.ok) poruka('Preuzeto – lovac i ostali dobili su obavijest da idete pomoći.'); }); return; }
        var r = t.closest && t.closest('[data-k]');
        if (r && !t.closest('a[href^="tel:"]') && !t.closest('button')) {
            e.preventDefault();
            var m = markeri[r.getAttribute('data-k')];
            if (m) { karta.setView(m.getLatLng(), Math.max(karta.getZoom(), 16)); m.openPopup(); document.getElementById('gl-karta').scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            else poruka('Za ovog lovca nema položaja na karti.');
        }
    });
    var odlazim = document.getElementById('gl-odlazim');
    if (odlazim) odlazim.addEventListener('click', function () {
        if (!confirm('Odlazite iz lova? Položaj se više neće slati.')) return;
        zaustavi();
        posalji('odlazim').then(function () { location.reload(); });
    });
    document.getElementById('gl-lociraj').addEventListener('click', function () {
        if (fix) { karta.setView([fix.lat, fix.lon], Math.max(karta.getZoom(), 16)); return; }
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(function (p) { karta.setView([p.coords.latitude, p.coords.longitude], 16); }, function () { poruka('Položaj nije dostupan.', true); }, { enableHighAccuracy: true, timeout: 15000 });
    });

    prikazi();
    pokreni();
    if (cfg.saljem) javiSe(); else osvjezi();
    if (cfg.sos && sosBtn) { sosNaoruzan = Date.now(); sosBtn.classList.add('naoruzan'); sosBtn.textContent = 'Dodirnite još jednom za SOS'; sosBtn.scrollIntoView({ block: 'center' }); }
})();
