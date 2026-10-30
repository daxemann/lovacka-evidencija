/* Lovačka evidencija – uređivanje lovišta: granica i linije (Leaflet + Leaflet-Geoman free, MIT).
 * Točku po točku (Geoman), slobodno olovkom (vlastiti način: crta se izgladi pri puštanju), pomicanje / brisanje točaka,
 * zaključavanje karte, uvoz i izvoz KML-a, površina u ha i duljina linija. */
(function () {
    'use strict';
    var cfg = JSON.parse(document.getElementById('lov-cfg').textContent);
    var forma = document.getElementById('lov-obrazac'), info = document.getElementById('lov-info');
    var bojaEl = document.getElementById('lov-boja'), povEl = document.getElementById('lov-povrsina');
    var liPanel = document.getElementById('lov-linija-panel');
    var okvir = document.querySelector('.lov-karta-okvir'), oznakaZakljucano = document.querySelector('.lov-zakljucano-oznaka');
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ---------- karta ----------
    var karta = L.map('lov-karta', { zoomControl: true });
    var satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 18, attribution: '&copy; Esri, Maxar' }).addTo(karta);
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
    var topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenTopoMap, OpenStreetMap' });
    L.control.layers({ 'Satelit': satelit, 'Karta': osm, 'Topografska': topo }, null, { position: 'topright' }).addTo(karta);
    L.control.scale({ imperial: false }).addTo(karta);
    try { karta.pm.setLang('hr'); } catch (x) { /* nema hrvatskog – engleski */ }
    karta.pm.setGlobalOptions({ snappable: true, snapDistance: 15, allowSelfIntersection: false });

    // ostala lovišta i naprave – samo za orijentaciju
    var pozadina = L.featureGroup().addTo(karta);
    cfg.ostala.forEach(function (o) { o.granica.forEach(function (p) { L.polygon(p, { color: o.boja, weight: 2, opacity: 0.6, fillOpacity: 0.03, dashArray: '4 4', interactive: false, pmIgnore: true }).bindTooltip(esc(o.naziv)).addTo(pozadina); }); });
    cfg.naprave.forEach(function (n) { L.circleMarker([n[0], n[1]], { radius: 4, color: '#fff', weight: 1, fillColor: '#2e7d32', fillOpacity: 1, interactive: false, pmIgnore: true }).addTo(pozadina); });

    var granice = L.featureGroup().addTo(karta), linije = L.featureGroup().addTo(karta);
    function stilGranice() { var b = bojaEl.value; return { color: b, weight: 3, fillColor: b, fillOpacity: 0.08 }; }
    function stilLinije(p) { return { color: p.boja || '#ffffff', weight: 4, opacity: 0.95, dashArray: p.crtkano ? '8 6' : null }; }
    function dodajGranicu(koord) { var l = L.polygon(koord, stilGranice()).addTo(granice); veziGranicu(l); return l; }
    function dodajLiniju(koord, p) {
        p = p || { naziv: '', vrsta: 'Put', boja: '#ffffff', crtkano: 0 };
        var l = L.polyline(koord, stilLinije(p)).addTo(linije);
        l.ev = p;
        veziLiniju(l);
        return l;
    }
    function veziGranicu(l) { l.on('pm:edit pm:dragend', osvjezi); }
    function veziLiniju(l) {
        l.on('click', function (e) { if (nacin) return; L.DomEvent.stop(e); odaberi(l); });
        l.on('pm:edit pm:dragend', function () { osvjezi(); if (odabrana === l) prikaziLiniju(); });
    }
    cfg.granica.forEach(dodajGranicu);
    cfg.linije.forEach(function (li) { dodajLiniju(li.koord, { naziv: li.naziv, vrsta: li.vrsta, boja: li.boja, crtkano: li.crtkano }); });

    var sve = L.featureGroup([granice, linije]);
    if (granice.getLayers().length || linije.getLayers().length) karta.fitBounds(sve.getBounds(), { padding: [20, 20] });
    else if (pozadina.getLayers().length) karta.fitBounds(pozadina.getBounds(), { padding: [20, 20] });
    else if (cfg.centar) karta.setView([cfg.centar[0], cfg.centar[1]], cfg.centar[2]);
    else karta.setView([45.1, 16.4], 7);

    // ---------- mjere ----------
    function povrsina(koord) {
        var R = 6378137, a = 0, n = koord.length;
        for (var i = 0; i < n; i++) {
            var p1 = koord[i], p2 = koord[(i + 1) % n];
            a += (p2.lng - p1.lng) * Math.PI / 180 * (2 + Math.sin(p1.lat * Math.PI / 180) + Math.sin(p2.lat * Math.PI / 180));
        }
        return Math.abs(a * R * R / 2);
    }
    function prsten(l) { var k = l.getLatLngs(); while (Array.isArray(k[0]) && !(k[0] instanceof L.LatLng)) k = k[0]; return k; }
    function duljina(l) { var k = l.getLatLngs(), d = 0; for (var i = 1; i < k.length; i++) d += k[i - 1].distanceTo(k[i]); return d; }
    function m(d) { return d >= 1000 ? (d / 1000).toFixed(2).replace('.', ',') + ' km' : Math.round(d) + ' m'; }
    function osvjezi() {
        var ha = 0, km = 0;
        granice.eachLayer(function (l) { ha += povrsina(prsten(l)) / 10000; });
        linije.eachLayer(function (l) { km += duljina(l); });
        info.textContent = 'Granica: ' + granice.getLayers().length + (granice.getLayers().length ? ' (' + ha.toFixed(1).replace('.', ',') + ' ha)' : '') +
            ' · Linije: ' + linije.getLayers().length + (linije.getLayers().length ? ' (' + m(km) + ')' : '');
        povEl.placeholder = ha ? ha.toFixed(1).replace('.', ',') + ' (iz granice)' : 'iz granice';
    }

    // ---------- odabrana linija (svojstva) ----------
    var odabrana = null;
    function odaberi(l) {
        if (odabrana && odabrana !== l) odabrana.setStyle({ weight: 4 });
        odabrana = l; l.setStyle({ weight: 7 }); prikaziLiniju();
    }
    function prikaziLiniju() {
        if (!odabrana) { liPanel.hidden = true; return; }
        var p = odabrana.ev;
        liPanel.querySelector('[data-li=naziv]').value = p.naziv || '';
        liPanel.querySelector('[data-li=vrsta]').value = p.vrsta || 'Ostalo';
        liPanel.querySelector('[data-li=boja]').value = p.boja || '#ffffff';
        liPanel.querySelector('[data-li=crtkano]').checked = !!p.crtkano;
        liPanel.querySelector('[data-li=duljina]').textContent = m(duljina(odabrana));
        liPanel.hidden = false;
    }
    liPanel.addEventListener('input', function (e) {
        if (!odabrana) return;
        var k = e.target.getAttribute('data-li'), p = odabrana.ev;
        if (k === 'crtkano') p.crtkano = e.target.checked ? 1 : 0; else if (k) p[k] = e.target.value;
        odabrana.setStyle(stilLinije(p)); odabrana.setStyle({ weight: 7 });
    });
    karta.on('click', function () { if (!nacin && odabrana) { odabrana.setStyle({ weight: 4 }); odabrana = null; liPanel.hidden = true; } });
    bojaEl.addEventListener('input', function () { granice.setStyle(stilGranice()); });

    // ---------- zaključavanje karte ----------
    var rucnoZakljucano = false;
    function zakljucaj(da) {
        ['dragging', 'touchZoom', 'doubleClickZoom', 'scrollWheelZoom', 'boxZoom', 'keyboard'].forEach(function (h) { if (karta[h]) karta[h][da ? 'disable' : 'enable'](); });
        okvir.classList.toggle('zakljucano', da); oznakaZakljucano.hidden = !da;
        var g = document.querySelector('[data-alat=zakljucaj]');
        g.classList.toggle('active', da); g.textContent = da ? '🔓 Otključaj kartu' : '🔒 Zaključaj kartu';
    }

    // ---------- načini rada ----------
    var nacin = null;
    function sto() { return document.querySelector('[name=lov-sto]:checked').value; }
    function ugasi() {
        karta.pm.disableDraw(); if (karta.pm.globalEditModeEnabled()) karta.pm.disableGlobalEditMode();
        if (karta.pm.globalRemovalModeEnabled()) karta.pm.disableGlobalRemovalMode();
        olovkaUgasi();
        nacin = null;
        document.querySelectorAll('[data-alat]').forEach(function (b) { if (b.getAttribute('data-alat') !== 'zakljucaj') b.classList.remove('active'); });
    }
    function upali(n, gumb) {
        var bio = nacin; ugasi();
        if (bio === n) return; // drugi dodir = isključi
        nacin = n; if (gumb) gumb.classList.add('active');
        if (n === 'tocke') karta.pm.enableDraw(sto() === 'granica' ? 'Polygon' : 'Line', { pathOptions: sto() === 'granica' ? stilGranice() : stilLinije({}), finishOn: null });
        if (n === 'uredi') karta.pm.enableGlobalEditMode({ allowSelfIntersection: false });
        if (n === 'brisi') karta.pm.enableGlobalRemovalMode();
        if (n === 'olovka') olovkaUpali();
    }
    karta.on('pm:create', function (e) {
        var l = e.layer, k = l.getLatLngs();
        karta.removeLayer(l);
        if (e.shape === 'Polygon') dodajGranicu(k); else odaberi(dodajLiniju(k, { naziv: '', vrsta: 'Put', boja: '#ffffff', crtkano: 0 }));
        ugasi(); osvjezi();
    });
    karta.on('pm:remove', function (e) { if (e.layer === odabrana) { odabrana = null; liPanel.hidden = true; } granice.removeLayer(e.layer); linije.removeLayer(e.layer); osvjezi(); });

    // ---------- olovka (slobodno crtanje) ----------
    var trag = null, tocke = [], pokazivac = null;
    function olovkaUpali() { zakljucaj(true); okvir.classList.add('olovka'); }
    function olovkaUgasi() { okvir.classList.remove('olovka'); if (trag) { karta.removeLayer(trag); trag = null; } tocke = []; if (!rucnoZakljucano) zakljucaj(false); }
    var el = karta.getContainer();
    el.addEventListener('pointerdown', function (e) {
        if (nacin !== 'olovka' || e.target.closest('.leaflet-control')) return;
        e.preventDefault(); pokazivac = e.pointerId; el.setPointerCapture(e.pointerId);
        tocke = [karta.mouseEventToLatLng(e)];
        trag = L.polyline(tocke, { color: sto() === 'granica' ? bojaEl.value : '#ffffff', weight: 3, dashArray: '2 6', interactive: false, pmIgnore: true }).addTo(karta);
    });
    el.addEventListener('pointermove', function (e) {
        if (nacin !== 'olovka' || e.pointerId !== pokazivac || !trag) return;
        tocke.push(karta.mouseEventToLatLng(e)); trag.setLatLngs(tocke);
    });
    function olovkaKraj(e) {
        if (nacin !== 'olovka' || e.pointerId !== pokazivac || !trag) return;
        pokazivac = null; karta.removeLayer(trag); trag = null;
        // izgladi: točke u piksele, Douglas-Peucker (tolerancija 3 px), natrag u koordinate
        var px = tocke.map(function (t) { return karta.latLngToLayerPoint(t); });
        var jed = L.LineUtil.simplify(px, 3).map(function (p) { return karta.layerPointToLatLng(p); });
        tocke = [];
        if (sto() === 'granica') { if (jed.length >= 3) dodajGranicu(jed); } else if (jed.length >= 2) odaberi(dodajLiniju(jed));
        ugasi(); osvjezi();
    }
    el.addEventListener('pointerup', olovkaKraj); el.addEventListener('pointercancel', olovkaKraj);

    // ---------- KML ----------
    function kmlKoord(txt) {
        return txt.trim().split(/\s+/).map(function (t) { var d = t.split(','); return d.length >= 2 && !isNaN(+d[0]) && !isNaN(+d[1]) ? [+d[1], +d[0]] : null; }).filter(Boolean);
    }
    function uvoz(datoteka) {
        var r = new FileReader();
        r.onload = function () {
            var dok = new DOMParser().parseFromString(r.result, 'application/xml'), n = 0;
            Array.prototype.forEach.call(dok.getElementsByTagNameNS('*', 'Placemark'), function (pm) {
                var ime = (pm.getElementsByTagNameNS('*', 'name')[0] || {}).textContent || '';
                Array.prototype.forEach.call(pm.getElementsByTagNameNS('*', 'Polygon'), function (pg) {
                    var c = pg.getElementsByTagNameNS('*', 'outerBoundaryIs')[0];
                    c = c && c.getElementsByTagNameNS('*', 'coordinates')[0];
                    var k = c ? kmlKoord(c.textContent) : [];
                    if (k.length >= 3) { dodajGranicu(k); n++; }
                });
                Array.prototype.forEach.call(pm.getElementsByTagNameNS('*', 'LineString'), function (ls) {
                    var c = ls.getElementsByTagNameNS('*', 'coordinates')[0], k = c ? kmlKoord(c.textContent) : [];
                    if (k.length >= 2) { dodajLiniju(k, { naziv: ime.trim().slice(0, 60), vrsta: 'Ostalo', boja: '#ffffff', crtkano: 0 }); n++; }
                });
            });
            if (n) { karta.fitBounds(L.featureGroup([granice, linije]).getBounds(), { padding: [20, 20] }); osvjezi(); }
            window.alert(n ? 'Uvezeno oblika: ' + n + '. Ne zaboravite „Spremi lovište“.' : 'U datoteci nema granica ni linija (samo KML, ne KMZ).');
        };
        r.readAsText(datoteka);
    }
    function izvoz() {
        var naziv = forma.naziv.value || 'Lovište', x = '<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>' + esc(naziv) + '</name>';
        function k(niz) { return niz.map(function (t) { return t.lng.toFixed(7) + ',' + t.lat.toFixed(7) + ',0'; }).join(' '); }
        granice.eachLayer(function (l) { var p = prsten(l).slice(); p.push(p[0]); x += '<Placemark><name>' + esc(naziv) + '</name><Polygon><outerBoundaryIs><LinearRing><coordinates>' + k(p) + '</coordinates></LinearRing></outerBoundaryIs></Polygon></Placemark>'; });
        linije.eachLayer(function (l) { x += '<Placemark><name>' + esc(l.ev.naziv || l.ev.vrsta) + '</name><LineString><coordinates>' + k(l.getLatLngs()) + '</coordinates></LineString></Placemark>'; });
        x += '</Document></kml>';
        var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([x], { type: 'application/vnd.google-earth.kml+xml' }));
        a.download = naziv.replace(/[^\wčćšžđČĆŠŽĐ-]+/g, '-') + '.kml'; document.body.appendChild(a); a.click(); a.remove();
    }

    // ---------- gumbi ----------
    document.querySelector('.lov-alati').addEventListener('click', function (e) {
        var b = e.target.closest('button[data-alat]');
        if (!b) return;
        var a = b.getAttribute('data-alat');
        if (a === 'zakljucaj') { rucnoZakljucano = !rucnoZakljucano; zakljucaj(rucnoZakljucano); return; }
        if (a === 'izvoz') { izvoz(); return; }
        upali(a, b);
    });
    document.querySelectorAll('[name=lov-sto]').forEach(function (r) { r.addEventListener('change', function () { if (nacin === 'tocke' || nacin === 'olovka') { var n = nacin; ugasi(); upali(n, document.querySelector('[data-alat=' + n + ']')); } }); });
    document.querySelector('input[data-alat=kml]').addEventListener('change', function () { if (this.files[0]) uvoz(this.files[0]); this.value = ''; });

    // ---------- spremanje ----------
    forma.addEventListener('submit', function () {
        var g = [], li = [];
        granice.eachLayer(function (l) { g.push(prsten(l).map(function (t) { return [+t.lat.toFixed(7), +t.lng.toFixed(7)]; })); });
        linije.eachLayer(function (l) { li.push({ naziv: l.ev.naziv, vrsta: l.ev.vrsta, boja: l.ev.boja, crtkano: l.ev.crtkano, koord: l.getLatLngs().map(function (t) { return [+t.lat.toFixed(7), +t.lng.toFixed(7)]; }) }); });
        document.getElementById('lov-granica').value = JSON.stringify(g);
        document.getElementById('lov-linije').value = JSON.stringify(li);
    });
    osvjezi();
})();
