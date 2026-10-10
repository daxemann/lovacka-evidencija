/* Lovačka evidencija – karta lovišta (Leaflet): lovne naprave, zauzimanje, uređivanje. */
(function () {
    'use strict';
    var cfgEl = document.getElementById('revir-cfg');
    if (!cfgEl || !window.L) return;
    var cfg = JSON.parse(cfgEl.textContent);
    var sekEl = document.getElementById('revir-sekcija');
    var panel = document.getElementById('revir-panel');
    var mojeEl = document.getElementById('revir-moje');
    var urediBtn = document.getElementById('revir-uredi');
    var urediTraka = document.getElementById('revir-uredi-traka');
    var bezEl = document.getElementById('revir-bez-polozaja');

    var st = { sek: cfg.zadana === null ? '' : String(cfg.zadana), naprave: [], uredi: false, odabrana: null, obrazac: false, postavi: null, prvi: true };
    var markeri = {};
    var privremeni = null;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function oznaka(n) { return (n.broj ? n.broj + ' · ' : '') + n.naziv; }
    function nadji(id) { for (var i = 0; i < st.naprave.length; i++) if (st.naprave[i].id === id) return st.naprave[i]; return null; }
    function nazivSekcije(id) { for (var i = 0; i < cfg.sekcije.length; i++) if (cfg.sekcije[i].id === id) return cfg.sekcije[i].naziv; return ''; }
    function obavijest(t, greska) {
        var o = document.getElementById('obavijest');
        if (!o) { window.alert(t); return; }
        o.innerHTML = '<div class="flex-grow-1' + (greska ? ' text-danger fw-semibold' : '') + '">' + esc(t) + '</div>';
        o.style.display = 'flex';
        clearTimeout(obavijest.t); obavijest.t = setTimeout(function () { o.style.display = 'none'; }, greska ? 7000 : 3500);
    }

    // ---------- Karta ----------
    var karta = L.map('revir-karta', { zoomControl: true, tap: true });
    var satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, maxNativeZoom: 18, attribution: 'Snimke &copy; Esri, Maxar, Earthstar Geographics'
    });
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
    // izdaleka manji pinovi bez imena – inače se preklapaju
    function velicina() { document.getElementById('revir-karta').classList.toggle('daleko', karta.getZoom() < 14); }
    karta.on('zoomend', velicina);

    if (cfg.centar) karta.setView([cfg.centar[0], cfg.centar[1]], cfg.centar[2]);
    else if (granice.getLayers().length) karta.fitBounds(granice.getBounds(), { padding: [10, 10] });
    else karta.setView([45.1, 16.4], 7);

    function ikona(n) {
        var z = n.zauzeto, kl = !z ? 'slobodno' : (z.moje ? 'moje' : 'zauzeto');
        var tekst = n.broj ? esc(n.broj) : '•';
        return L.divIcon({
            className: 'revir-pin-omot',
            html: '<div class="revir-pin ' + kl + (z && z.gost ? ' gost' : '') + (st.odabrana === n.id ? ' odabran' : '') + '">' + tekst + '</div>' +
                (st.uredi || z ? '<div class="revir-pin-ime">' + esc(z ? (z.gost ? 'gost' : z.ime.split(' ')[0]) : n.naziv) + '</div>' : ''),
            iconSize: [34, 34], iconAnchor: [17, 17]
        });
    }

    function crtaj() {
        var vidljivi = {};
        st.naprave.forEach(function (n) {
            if (n.lat === null) return;
            vidljivi[n.id] = true;
            var m = markeri[n.id];
            if (!m) {
                m = L.marker([n.lat, n.lon], { icon: ikona(n), draggable: st.uredi && n.mozeUrediti, autoPan: true });
                m.on('click', function () { otvori(n.id); });
                m.on('dragend', function () { pomaknut(n.id, m); });
                m.addTo(sloj);
                markeri[n.id] = m;
            } else {
                m.setLatLng([n.lat, n.lon]);
                m.setIcon(ikona(n));
                if (m.dragging) { if (st.uredi && n.mozeUrediti) m.dragging.enable(); else m.dragging.disable(); }
            }
        });
        Object.keys(markeri).forEach(function (id) { if (!vidljivi[id]) { sloj.removeLayer(markeri[id]); delete markeri[id]; } });
        crtajMoje();
        crtajBezPolozaja();
        if (st.prvi) {
            st.prvi = false;
            if (!cfg.centar && !granice.getLayers().length && sloj.getLayers().length) karta.fitBounds(sloj.getBounds(), { padding: [30, 30], maxZoom: 16 });
            if (cfg.odabir && nadji(+cfg.odabir)) otvori(+cfg.odabir, true);
        }
        if (st.odabrana && !st.obrazac) { if (nadji(st.odabrana)) prikaziPanel(nadji(st.odabrana)); else zatvori(); }
    }

    function crtajMoje() {
        var moje = st.naprave.filter(function (n) { return n.zauzeto && n.zauzeto.moje; });
        if (!moje.length) { mojeEl.hidden = true; return; }
        var ja = moje.filter(function (n) { return !n.zauzeto.gost; }), gosti = moje.filter(function (n) { return n.zauzeto.gost; });
        var t = ja.length ? 'Sjedite na <b>' + esc(oznaka(ja[0])) + '</b> od ' + esc(ja[0].zauzeto.od) : '';
        if (gosti.length) t += (t ? '<br>' : '') + 'Gost: ' + gosti.map(function (n) { return '<b>' + esc(oznaka(n)) + '</b>' + (n.zauzeto.gost !== 'gost' ? ' (' + esc(n.zauzeto.gost) + ')' : ''); }).join(', ');
        mojeEl.innerHTML = '<div class="flex-grow-1 small">' + t + '</div><button type="button" class="btn btn-danger btn-sm text-nowrap" data-odlazim>🏁 Odlazim</button>';
        mojeEl.hidden = false;
    }

    function crtajBezPolozaja() {
        if (!bezEl) return;
        var bez = st.naprave.filter(function (n) { return n.lat === null && n.mozeUrediti; });
        if (!st.uredi || !bez.length) { bezEl.hidden = true; return; }
        bezEl.innerHTML = '<div class="mb-1">Naprave bez položaja – dodirnite pa kartu:</div>' + bez.map(function (n) {
            return '<button type="button" class="btn btn-sm ' + (st.postavi === n.id ? 'btn-primary' : 'btn-outline-primary') + ' me-1 mb-1" data-postavi="' + n.id + '">' + esc(oznaka(n)) + '</button>';
        }).join('');
        bezEl.hidden = false;
    }

    // ---------- Podaci ----------
    function ucitaj() {
        return fetch(cfg.api + '&s=' + encodeURIComponent(st.sek || 'sve'), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.naprave) { st.naprave = j.naprave; crtaj(); } else if (j.greska) obavijest(j.greska, true); })
            .catch(function () { obavijest('Nema veze s poslužiteljem.', true); });
    }
    function posalji(podaci, datoteka) {
        var fd = podaci instanceof FormData ? podaci : new FormData();
        if (!(podaci instanceof FormData)) Object.keys(podaci).forEach(function (k) { if (podaci[k] !== null && podaci[k] !== undefined) fd.append(k, podaci[k]); });
        fd.append('_csrf', cfg.csrf);
        return fetch(cfg.api + '&s=' + encodeURIComponent(st.sek || 'sve'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': cfg.csrf } })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function (j) {
                if (j.naprave) { st.naprave = j.naprave; }
                if (j.greska) obavijest(j.greska, true);
                crtaj();
                return j;
            })
            .catch(function (e) { obavijest('Nije spremljeno – nema veze s poslužiteljem.', true); throw e; });
    }

    // ---------- Panel ----------
    function otvori(id, centriraj) {
        st.odabrana = id; st.obrazac = false;
        ukloniPrivremeni();
        var n = nadji(id);
        if (!n) return;
        if (centriraj && n.lat !== null) karta.setView([n.lat, n.lon], Math.max(karta.getZoom(), 16));
        crtaj();
        prikaziPanel(n);
    }
    function zatvori() {
        st.odabrana = null; st.obrazac = false; panel.hidden = true; panel.innerHTML = '';
        ukloniPrivremeni();
        crtaj();
    }
    function prikaziPanel(n) {
        var z = n.zauzeto, h = '';
        h += '<button type="button" class="btn-close float-end" data-zatvori aria-label="Zatvori"></button>';
        if (n.foto) h += '<a href="' + esc(n.foto) + '" target="_blank" rel="noopener"><img src="' + esc(n.foto) + '" class="revir-foto" alt=""></a>';
        h += '<div class="h5 mb-0">' + esc(oznaka(n)) + '</div>';
        h += '<div class="small text-muted mb-2">' + esc([n.vrsta, cfg.sekcije.length > 1 ? nazivSekcije(n.sekcija) : ''].filter(Boolean).join(' · ')) + '</div>';
        if (n.napomena) h += '<div class="small mb-2">' + esc(n.napomena) + '</div>';
        if (z) {
            h += '<div class="revir-status zauzeto mb-2">🔴 Zauzeto: <b>' + esc(z.gost ? 'gost' + (z.gost !== 'gost' ? ' ' + z.gost : '') : z.ime) + '</b>' +
                (z.gost ? ' <span class="small">(gost od ' + esc(z.ime) + ')</span>' : '') + ' <span class="small">od ' + esc(z.od) + '</span></div>';
            if (z.moje) h += '<button type="button" class="btn btn-success w-100 mb-2" data-oslobodi="' + z.id + '">🟢 Oslobodi' + (z.gost ? ' (gost otišao)' : ' – odlazim') + '</button>';
            else if (n.mozeObrisati) h += '<button type="button" class="btn btn-outline-danger btn-sm w-100 mb-2" data-obrisi="' + z.id + '">Obriši zauzeće</button>';
        } else {
            h += '<div class="revir-status slobodno mb-2">🟢 Slobodno</div>';
            if (n.mozeZauzeti) {
                h += '<button type="button" class="btn btn-danger btn-lg w-100 mb-2" data-zauzmi="' + n.id + '">Zauzmi – sjedim ovdje</button>';
                h += '<details class="mb-2"><summary class="small">Zauzmi za gosta…</summary><div class="input-group input-group-sm mt-2">' +
                    '<input type="text" class="form-control" maxlength="60" placeholder="ime gosta (neobavezno)" data-gost-ime>' +
                    '<button type="button" class="btn btn-outline-danger" data-zauzmi-gost="' + n.id + '">Zauzmi za gosta</button></div></details>';
            } else if (cfg.clan) h += '<div class="small text-muted mb-2">Druga sekcija – samo pregled.</div>';
        }
        var nav = n.lat !== null ? '<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=' + n.lat + ',' + n.lon + '">🧭 Navigacija</a>' : '';
        var ur = n.mozeUrediti && st.uredi ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-uredi-napravu="' + n.id + '">✏️ Uredi</button>' : '';
        if (nav || ur) h += '<div class="d-flex gap-2">' + nav + ur + '</div>';
        panel.innerHTML = h;
        panel.hidden = false;
    }

    function obrazac(n, lat, lon) {
        st.obrazac = true;
        var novi = !n;
        n = n || { id: 0, broj: '', naziv: '', vrsta: cfg.vrste[0], sekcija: st.sek !== '' ? +st.sek : (cfg.uredive[0] ? cfg.uredive[0].id : 0), napomena: '', foto: null, lat: lat, lon: lon };
        var sek = cfg.uredive.map(function (s) { return '<option value="' + s.id + '"' + (s.id === n.sekcija ? ' selected' : '') + '>' + esc(s.naziv) + '</option>'; }).join('');
        var vr = cfg.vrste.map(function (v) { return '<option' + (v === n.vrsta ? ' selected' : '') + '>' + esc(v) + '</option>'; }).join('');
        var h = '<button type="button" class="btn-close float-end" data-zatvori aria-label="Zatvori"></button>' +
            '<div class="h6">' + (novi ? 'Nova lovna naprava' : 'Uredi: ' + esc(oznaka(n))) + '</div>' +
            '<form data-obrazac class="row g-2">' +
            '<input type="hidden" name="id" value="' + n.id + '">' +
            (lat !== undefined && lat !== null ? '<input type="hidden" name="lat" value="' + lat + '"><input type="hidden" name="lon" value="' + lon + '">' : '') +
            '<div class="col-4"><label class="form-label small mb-0">Broj</label><input name="broj" class="form-control form-control-sm" maxlength="12" value="' + esc(n.broj) + '" inputmode="numeric"></div>' +
            '<div class="col-8"><label class="form-label small mb-0">Naziv *</label><input name="naziv" class="form-control form-control-sm" maxlength="80" required value="' + esc(n.naziv) + '"></div>' +
            '<div class="col-6"><label class="form-label small mb-0">Vrsta</label><select name="vrsta" class="form-select form-select-sm">' + vr + '</select></div>' +
            '<div class="col-6"><label class="form-label small mb-0">Sekcija</label><select name="sekcija" class="form-select form-select-sm">' + sek + '</select></div>' +
            '<div class="col-12"><label class="form-label small mb-0">Napomena</label><input name="napomena" class="form-control form-control-sm" maxlength="500" value="' + esc(n.napomena) + '"></div>' +
            '<div class="col-12"><label class="form-label small mb-0">Fotografija</label><input type="file" name="foto" accept="image/*" class="form-control form-control-sm">' +
            (n.foto ? '<label class="small mt-1"><input type="checkbox" name="obrisiFoto" value="1"> obriši postojeću</label>' : '') + '</div>' +
            '<div class="col-12 d-flex gap-2 mt-2"><button class="btn btn-primary btn-sm flex-grow-1">Spremi</button>' +
            (!novi ? '<button type="button" class="btn btn-outline-danger btn-sm" data-obrisi-napravu="' + n.id + '">Obriši</button>' : '') + '</div>' +
            (lat !== undefined && lat !== null ? '<div class="col-12 small text-muted">Položaj: ' + (+lat).toFixed(5) + ', ' + (+lon).toFixed(5) + ' – pin možete i povući.</div>' : '') +
            '</form>';
        panel.innerHTML = h;
        panel.hidden = false;
        var nz = panel.querySelector('[name=naziv]'); if (novi && nz) setTimeout(function () { nz.focus(); }, 50);
    }

    function ukloniPrivremeni() { if (privremeni) { karta.removeLayer(privremeni); privremeni = null; } }
    function nova(lat, lon) {
        st.odabrana = null;
        ukloniPrivremeni();
        privremeni = L.marker([lat, lon], { draggable: true, icon: L.divIcon({ className: 'revir-pin-omot', html: '<div class="revir-pin novi">+</div>', iconSize: [34, 34], iconAnchor: [17, 17] }) }).addTo(karta);
        privremeni.on('dragend', function () { var p = privremeni.getLatLng(); var f = panel.querySelector('[data-obrazac]'); if (f) { f.lat.value = p.lat.toFixed(7); f.lon.value = p.lng.toFixed(7); } });
        obrazac(null, lat.toFixed(7), lon.toFixed(7));
    }

    function pomaknut(id, m) {
        var n = nadji(id), p = m.getLatLng();
        if (!n) return;
        if (!window.confirm('Premjestiti „' + oznaka(n) + '“ na novi položaj?')) { m.setLatLng([n.lat, n.lon]); return; }
        posalji({ radnja: 'naprava-pomakni', id: id, lat: p.lat.toFixed(7), lon: p.lng.toFixed(7) }).then(function (j) { if (j.ok) obavijest('Položaj spremljen.'); });
    }

    // ---------- Lokacija ----------
    function najboljaLokacija(gotovo, info) {
        if (!navigator.geolocation || !window.isSecureContext) { obavijest('Lokacija radi samo preko https:// adrese.', true); return; }
        var najb = null, kraj = false;
        info && info('Tražim lokaciju…');
        function zavrsi() { if (kraj) return; kraj = true; navigator.geolocation.clearWatch(w); if (najb) gotovo(najb); else obavijest('Lokacija nije pronađena – uključite GPS.', true); }
        var w = navigator.geolocation.watchPosition(function (p) {
            if (!najb || p.coords.accuracy < najb.accuracy) { najb = p.coords; info && info('Točnost ±' + Math.round(najb.accuracy) + ' m…'); }
            if (najb.accuracy <= 12) zavrsi();
        }, function (er) { if (er.code === 1) { kraj = true; obavijest('Lokacija nije dopuštena u pregledniku.', true); } }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
        setTimeout(zavrsi, 15000);
    }
    var ja = null, jaKrug = null, jaWatch = null;
    document.getElementById('revir-lociraj').addEventListener('click', function () {
        var b = this;
        if (jaWatch !== null) { navigator.geolocation.clearWatch(jaWatch); jaWatch = null; if (ja) { karta.removeLayer(ja); karta.removeLayer(jaKrug); ja = null; } b.classList.remove('aktivan'); return; }
        if (!navigator.geolocation || !window.isSecureContext) { obavijest('Lokacija radi samo preko https:// adrese.', true); return; }
        b.classList.add('aktivan');
        var prvi = true;
        jaWatch = navigator.geolocation.watchPosition(function (p) {
            var ll = [p.coords.latitude, p.coords.longitude];
            if (!ja) { jaKrug = L.circle(ll, { radius: p.coords.accuracy, weight: 1, color: '#1a73e8', fillOpacity: 0.1, interactive: false }).addTo(karta); ja = L.circleMarker(ll, { radius: 7, color: '#fff', weight: 2, fillColor: '#1a73e8', fillOpacity: 1, interactive: false }).addTo(karta); }
            else { ja.setLatLng(ll); jaKrug.setLatLng(ll).setRadius(p.coords.accuracy); }
            if (prvi) { prvi = false; karta.setView(ll, Math.max(karta.getZoom(), 16)); }
        }, function () { obavijest('Lokacija nije dostupna.', true); b.classList.remove('aktivan'); jaWatch = null; }, { enableHighAccuracy: true, maximumAge: 5000 });
    });

    // ---------- Događaji ----------
    karta.on('click', function (e) {
        if (st.postavi) {
            var id = st.postavi; st.postavi = null;
            posalji({ radnja: 'naprava-pomakni', id: id, lat: e.latlng.lat.toFixed(7), lon: e.latlng.lng.toFixed(7) }).then(function (j) { if (j.ok) obavijest('Položaj spremljen.'); });
            return;
        }
        if (st.uredi && cfg.uredive.length) { nova(e.latlng.lat, e.latlng.lng); return; }
        if (!panel.hidden) zatvori();
    });
    if (sekEl) sekEl.addEventListener('change', function () { st.sek = sekEl.value === 'sve' ? '' : sekEl.value; zatvori(); ucitaj(); });
    if (urediBtn) urediBtn.addEventListener('click', function () {
        st.uredi = !st.uredi; st.postavi = null;
        urediBtn.classList.toggle('active', st.uredi); urediBtn.setAttribute('aria-pressed', st.uredi ? 'true' : 'false');
        urediTraka.hidden = !st.uredi;
        document.getElementById('revir-karta').classList.toggle('uredjivanje', st.uredi);
        zatvori();
    });
    var ovdje = document.getElementById('revir-ovdje');
    if (ovdje) ovdje.addEventListener('click', function () {
        var s = ovdje.textContent;
        ovdje.disabled = true;
        najboljaLokacija(function (c) { ovdje.disabled = false; ovdje.textContent = s; karta.setView([c.latitude, c.longitude], Math.max(karta.getZoom(), 17)); nova(c.latitude, c.longitude); },
            function (t) { ovdje.textContent = t; });
        setTimeout(function () { ovdje.disabled = false; ovdje.textContent = s; }, 16000);
    });

    document.addEventListener('click', function (e) {
        var t = e.target, el;
        if (t.closest('[data-zatvori]')) { zatvori(); return; }
        if ((el = t.closest('[data-zauzmi]'))) {
            el.disabled = true;
            posalji({ radnja: 'zauzmi', id: el.getAttribute('data-zauzmi') }).then(function (j) { if (j.ok) obavijest('Zauzeto – dobar lov! Ne zaboravite „Odlazim“.'); });
            return;
        }
        if ((el = t.closest('[data-zauzmi-gost]'))) {
            var ime = panel.querySelector('[data-gost-ime]');
            el.disabled = true;
            posalji({ radnja: 'zauzmi', id: el.getAttribute('data-zauzmi-gost'), gost: 1, gostIme: ime ? ime.value : '' }).then(function (j) { if (j.ok) obavijest('Zauzeto za gosta.'); });
            return;
        }
        if ((el = t.closest('[data-oslobodi]'))) {
            el.disabled = true;
            posalji({ radnja: 'oslobodi', zid: el.getAttribute('data-oslobodi') }).then(function (j) { if (j.ok) obavijest('Slobodno.'); });
            return;
        }
        if ((el = t.closest('[data-odlazim]'))) {
            if (!window.confirm('Osloboditi sve vaše zauzete naprave (i gostove)?')) return;
            el.disabled = true;
            posalji({ radnja: 'odlazim' }).then(function (j) { if (j.ok) obavijest('Sve oslobođeno. Lijep pozdrav!'); });
            return;
        }
        if ((el = t.closest('[data-obrisi]'))) {
            if (!window.confirm('Obrisati ovo zauzeće? Neće se upisati u lovački dnevnik.')) return;
            posalji({ radnja: 'obrisi', zid: el.getAttribute('data-obrisi') }).then(function (j) { if (j.ok) obavijest('Zauzeće obrisano.'); });
            return;
        }
        if ((el = t.closest('[data-uredi-napravu]'))) { var n = nadji(+el.getAttribute('data-uredi-napravu')); if (n) obrazac(n); return; }
        if ((el = t.closest('[data-obrisi-napravu]'))) {
            if (!window.confirm('Trajno obrisati ovu lovnu napravu? Lovački dnevnik ostaje.')) return;
            posalji({ radnja: 'naprava-obrisi', id: el.getAttribute('data-obrisi-napravu') }).then(function (j) { if (j.ok) { obavijest('Obrisano.'); zatvori(); } });
            return;
        }
        if ((el = t.closest('[data-postavi]'))) {
            var pid = +el.getAttribute('data-postavi');
            st.postavi = st.postavi === pid ? null : pid;
            crtajBezPolozaja();
            if (st.postavi) obavijest('Dodirnite kartu na mjestu naprave.');
        }
    });
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('[data-obrazac]');
        if (!f) return;
        e.preventDefault();
        var fd = new FormData(f);
        fd.append('radnja', 'naprava-spremi');
        var b = f.querySelector('button.btn-primary'); if (b) { b.disabled = true; b.textContent = 'Spremam…'; }
        var foto = f.foto && f.foto.files[0];
        (foto && window.RevirFoto ? RevirFoto.smanji(foto) : Promise.resolve(null)).then(function (mala) {
            if (mala) fd.set('foto', mala, mala.name || 'foto.jpg');
            return posalji(fd);
        }).then(function (j) {
            if (j.ok) { ukloniPrivremeni(); obavijest('Spremljeno.'); otvori(j.id); }
            else if (b) { b.disabled = false; b.textContent = 'Spremi'; }
        }).catch(function () { if (b) { b.disabled = false; b.textContent = 'Spremi'; } });
    });

    velicina();
    ucitaj();
    setInterval(function () { if (!document.hidden && !st.obrazac) ucitaj(); }, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden && !st.obrazac) ucitaj(); });
})();
