/* Lovačka evidencija – „📥 Karta bez signala“: preuzimanje satelitskih pločica lovišta u memoriju mobitela (Cache API).
 * Service worker (sw.js) ih zatim služi bez interneta. GPS položaj radi i bez signala (satelitski prijemnik mobitela). */
(function () {
    'use strict';
    var K = window.EvKarta;
    if (!K || !window.caches || !window.L) return;
    var SPREMNIK = 'ev-karta-1', MAX = 2500, INFO = 'ev-karta-info';
    var karta = K.karta;
    var gumb = document.getElementById('revir-offline'), panel = document.getElementById('revir-offline-panel'), traka = document.getElementById('revir-bez-signala');

    function url(z, x, y) { return 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/' + z + '/' + y + '/' + x; }
    function tile(lat, lon, z) {
        var n = Math.pow(2, z), r = lat * Math.PI / 180;
        return { x: Math.floor((lon + 180) / 360 * n), y: Math.floor((1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * n) };
    }
    /** Područje: granice lovišta, inače naprave, inače ono što je sada na zaslonu – s 10 % ruba. */
    function podrucje() {
        var b = null;
        if (K.granice.getLayers().length) b = K.granice.getBounds();
        else if (K.sloj.getLayers().length) b = K.sloj.getBounds();
        if (!b || !b.isValid()) b = karta.getBounds();
        return b.pad(0.1);
    }
    function popis(b, doZooma) {
        var out = [];
        for (var z = 11; z <= doZooma; z++) {
            var a = tile(b.getNorth(), b.getWest(), z), c = tile(b.getSouth(), b.getEast(), z);
            for (var x = a.x; x <= c.x; x++) for (var y = a.y; y <= c.y; y++) out.push(url(z, x, y));
        }
        return out;
    }
    function info() { try { return JSON.parse(localStorage.getItem(INFO) || 'null'); } catch (x) { return null; } }
    function mb(b) { return (b / 1048576).toFixed(b > 10485760 ? 0 : 1).replace('.', ',') + ' MB'; }
    function datum(t) { var d = new Date(t); return d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear() + '.'; }

    function prikazi() {
        var i = info(), b = podrucje(), h = '<button type="button" class="btn-close float-end" data-ko-zatvori aria-label="Zatvori"></button>' +
            '<div class="h6 mb-1">📥 Karta bez signala</div>' +
            '<p class="small text-muted mb-2">Satelitska karta lovišta sprema se na ovaj mobitel. U lovištu bez signala karta, lovne naprave (zadnje stanje) i vaš GPS položaj ◎ rade i dalje.</p>';
        if (i) h += '<div class="alert alert-success py-2 small mb-2">✓ Spremljeno ' + datum(i.t) + ' · ' + i.n + ' pločica · ' + mb(i.b) + ' (do razine ' + i.z + ')</div>';
        h += '<label class="form-label small mb-0">Detalji</label><select class="form-select form-select-sm mb-2" data-ko-zoom>' +
            [[16, 'srednje'], [17, 'detaljno (preporučeno)'], [18, 'najdetaljnije – puno MB']].map(function (o) {
                var n = popis(b, o[0]).length;
                return '<option value="' + o[0] + '"' + (o[0] === 17 ? ' selected' : '') + (n > MAX ? ' disabled' : '') + '>' + o[1] + ' – ' + n + ' pločica, ~' + mb(n * 22000) + (n > MAX ? ' (preveliko područje)' : '') + '</option>';
            }).join('') + '</select>' +
            '<div class="d-flex gap-2"><button type="button" class="btn btn-success btn-sm flex-grow-1" data-ko-preuzmi>📥 ' + (i ? 'Preuzmi ponovno' : 'Preuzmi kartu') + '</button>' +
            (i ? '<button type="button" class="btn btn-outline-danger btn-sm" data-ko-brisi>🗑 Obriši</button>' : '') + '</div>' +
            '<div class="progress mt-2" style="height:.6rem" hidden data-ko-tijek><div class="progress-bar bg-success" style="width:0%"></div></div><div class="small text-muted mt-1" data-ko-status></div>' +
            '<div class="small text-muted mt-2">Preuzmite preko Wi-Fi-ja. Područje: ' + (K.granice.getLayers().length ? 'granice lovišta' : K.sloj.getLayers().length ? 'sve lovne naprave' : 'trenutni prikaz karte') + '.</div>';
        panel.innerHTML = h;
        panel.hidden = false;
    }
    function preuzmi(doZooma) {
        var lista = popis(podrucje(), doZooma);
        if (lista.length > MAX) return;
        var tijek = panel.querySelector('[data-ko-tijek]'), bar = tijek.firstChild, st = panel.querySelector('[data-ko-status]');
        panel.querySelectorAll('button').forEach(function (b) { if (!b.hasAttribute('data-ko-zatvori')) b.disabled = true; });
        tijek.hidden = false;
        var gotovo = 0, greske = 0, bajtova = 0, i = 0;
        return caches.open(SPREMNIK).then(function (c) {
            function radnik() {
                if (i >= lista.length) return Promise.resolve();
                var u = lista[i++];
                return fetch(u, { mode: 'cors' }).then(function (r) {
                    if (!r.ok) throw new Error(r.status);
                    var len = +(r.headers.get('content-length') || 0);
                    return r.clone().blob().then(function (bl) { bajtova += bl.size || len; return c.put(u, r); });
                }).catch(function () { greske++; }).then(function () {
                    gotovo++;
                    bar.style.width = Math.round(gotovo / lista.length * 100) + '%';
                    st.textContent = gotovo + ' / ' + lista.length + ' · ' + mb(bajtova);
                    return radnik();
                });
            }
            return Promise.all([radnik(), radnik(), radnik(), radnik()]);
        }).then(function () {
            try { localStorage.setItem(INFO, JSON.stringify({ t: Date.now(), n: gotovo - greske, b: bajtova, z: doZooma })); } catch (x) { /* ništa */ }
            prikazi();
            var s2 = panel.querySelector('[data-ko-status]');
            if (s2) s2.textContent = greske ? '⚠ ' + greske + ' pločica nije preuzeto (slab signal?) – pokušajte ponovno.' : '✓ Gotovo – karta radi i bez signala.';
            if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(function () {});
        });
    }
    function brisi() {
        return caches.delete(SPREMNIK).then(function () { try { localStorage.removeItem(INFO); } catch (x) { /* ništa */ } prikazi(); });
    }

    gumb.addEventListener('click', function () { if (panel.hidden) prikazi(); else panel.hidden = true; });
    panel.addEventListener('click', function (e) {
        if (e.target.closest('[data-ko-zatvori]')) { panel.hidden = true; return; }
        if (e.target.closest('[data-ko-preuzmi]')) { preuzmi(+panel.querySelector('[data-ko-zoom]').value); return; }
        if (e.target.closest('[data-ko-brisi]') && window.confirm('Obrisati spremljenu kartu s ovog mobitela?')) brisi();
    });
    if (info()) gumb.classList.add('spremljeno');

    // traka „bez signala“
    function veza() { if (traka) traka.hidden = navigator.onLine; }
    window.addEventListener('online', veza); window.addEventListener('offline', veza); veza();
})();
