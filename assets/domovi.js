/* Lovački domovi: karta svih domova (pregled) i odabir položaja doma (uređivanje). */
(function () {
    'use strict';
    var el = document.getElementById('dom-cfg');
    if (!el || !window.L) return;
    var cfg = JSON.parse(el.textContent);
    var mapa = document.getElementById(cfg.karta || 'dom-karta');
    if (!mapa) return;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    var karta = L.map(mapa, { zoomControl: true, tap: true });
    var satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 18, attribution: 'Snimke &copy; Esri, Maxar, Earthstar Geographics' });
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
    var topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenTopoMap (CC-BY-SA), OpenStreetMap' });
    osm.addTo(karta);
    L.control.layers({ 'Karta': osm, 'Satelit': satelit, 'Topografska': topo }, null, { position: 'topright' }).addTo(karta);
    L.control.scale({ imperial: false }).addTo(karta);

    var granice = L.featureGroup().addTo(karta);
    (cfg.granice || []).forEach(function (g) {
        var stil = { color: '#c79a00', weight: 2, opacity: 0.9, fillColor: '#ffd400', fillOpacity: 0.03, interactive: false };
        (g.tip === 'poligon' ? L.polygon(g.koord, stil) : L.polyline(g.koord, stil)).addTo(granice);
    });
    function pocetniPrikaz(sloj) {
        if (sloj && sloj.getLayers().length) karta.fitBounds(sloj.getBounds(), { padding: [30, 30], maxZoom: 15 });
        else if (cfg.centar) karta.setView([cfg.centar[0], cfg.centar[1]], cfg.centar[2]);
        else if (granice.getLayers().length) karta.fitBounds(granice.getBounds(), { padding: [10, 10] });
        else karta.setView([45.1, 16.4], 7);
    }
    function ikona(tekst) {
        return L.divIcon({ className: 'revir-pin-omot', iconSize: [34, 34], iconAnchor: [17, 17],
            html: '<div class="dom-pin">🏡</div>' + (tekst ? '<div class="revir-pin-ime">' + esc(tekst) + '</div>' : '') });
    }

    // ---------- pregled: svi domovi ----------
    if (cfg.domovi) {
        var sloj = L.featureGroup().addTo(karta);
        cfg.domovi.forEach(function (d) {
            if (d.lat === null) return;
            var t = '<b>' + esc(d.naziv) + '</b><br><span class="text-muted">' + esc(d.opseg) + '</span>'
                + (d.nav ? '<br><a href="' + esc(d.nav) + '" target="_blank" rel="noopener">🧭 Navigacija</a>' : '')
                + '<br><a href="' + esc(d.url) + '">📅 Kalendar i rezervacija</a>';
            L.marker([d.lat, d.lon], { icon: ikona(d.naziv) }).bindPopup(t).addTo(sloj);
        });
        pocetniPrikaz(sloj);
        return;
    }

    // ---------- uređivanje: odabir položaja ----------
    var lat = document.getElementById(cfg.lat), lon = document.getElementById(cfg.lon);
    var pin = null;
    function postavi(ll, pomakni) {
        lat.value = ll.lat.toFixed(6); lon.value = ll.lng.toFixed(6);
        if (!pin) {
            pin = L.marker(ll, { icon: ikona(''), draggable: true }).addTo(karta);
            pin.on('dragend', function () { postavi(pin.getLatLng(), false); });
        } else pin.setLatLng(ll);
        if (pomakni) karta.setView(ll, Math.max(karta.getZoom() || 0, 15));
    }
    function izPolja() {
        var a = parseFloat(String(lat.value).replace(',', '.')), b = parseFloat(String(lon.value).replace(',', '.'));
        if (isFinite(a) && isFinite(b) && Math.abs(a) <= 90 && Math.abs(b) <= 180) { postavi(L.latLng(a, b), true); return true; }
        return false;
    }
    if (!izPolja()) pocetniPrikaz(null);
    karta.on('click', function (e) { postavi(e.latlng, false); });
    lat.addEventListener('change', izPolja); lon.addEventListener('change', izPolja);
    var gumb = document.getElementById('dom-ovdje');
    if (gumb) gumb.addEventListener('click', function () {
        if (!navigator.geolocation) { alert('Mobitel / preglednik ne daje položaj.'); return; }
        gumb.disabled = true;
        navigator.geolocation.getCurrentPosition(function (p) {
            gumb.disabled = false; postavi(L.latLng(p.coords.latitude, p.coords.longitude), true);
        }, function () { gumb.disabled = false; alert('Položaj nije dostupan – dopustite lokaciju ili dodirnite kartu.'); }, { enableHighAccuracy: true, timeout: 15000 });
    });
    var brisi = document.getElementById('dom-bez');
    if (brisi) brisi.addEventListener('click', function () {
        lat.value = ''; lon.value = '';
        if (pin) { karta.removeLayer(pin); pin = null; }
    });
})();
