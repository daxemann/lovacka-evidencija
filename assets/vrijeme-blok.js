/* Lovačka evidencija – blok „🌤️ Danas u lovištu – vrijeme“: svjetlo dana, Mjesec, vrijeme i „Kad u lov?“.
 * Sunce i Mjesec računa uređaj (bez interneta); temperatura, kiša i vjetar: Open-Meteo (EvVjetar.dohvati). */
(function () {
    'use strict';
    var blok = document.querySelector('[data-vrijeme-blok]');
    if (!blok || !window.EvAstro || !window.EvVjetar) return;
    var A = window.EvAstro, VJ = window.EvVjetar;
    var lat = +blok.getAttribute('data-lat'), lon = +blok.getAttribute('data-lon');
    var tijelo = blok.querySelector('[data-vr-tijelo]'), vrijemeEl = blok.querySelector('[data-vr-vrijeme]');
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function kljuc(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) + 'T' + ('0' + d.getHours()).slice(-2); }
    function sat(d, h) { var x = new Date(d); x.setHours(x.getHours() + h); return x; }

    /** Ocjena razdoblja (jutro / večer) iz satne prognoze. */
    function ocijeni(od, do_, svi, mj, jutro) {
        var k1 = kljuc(od), k2 = kljuc(do_), u = svi.filter(function (x) { return x.t.slice(0, 13) >= k1 && x.t.slice(0, 13) <= k2; });
        if (!u.length) return null;
        var vj = u.reduce(function (a, x) { return a + x.brzina; }, 0) / u.length;
        var udari = Math.max.apply(null, u.map(function (x) { return x.udari || 0; }));
        var vjer = Math.max.apply(null, u.map(function (x) { return x.vjer; }));
        var kisa = u.reduce(function (a, x) { return a + x.kisa; }, 0);
        var prije = svi.filter(function (x) { var t = x.t.slice(0, 13); return t < k1 && t >= kljuc(sat(od, -8)); }).reduce(function (a, x) { return a + x.kisa; }, 0);
        if (vj >= 29 || udari >= 60) return { o: 0, t: 'jak vjetar – divljač u zaklonu' };
        if (kisa >= 1 && vjer >= 60) return { o: 0, t: 'kiša' };
        if (vj >= 20) return { o: 1, t: 'jači vjetar – divljač opreznija' };
        if (vjer >= 40) return { o: 1, t: 'moguća kiša (' + Math.round(vjer) + ' %)' };
        if (vj < 2) return { o: 1, t: 'tišina – miris se kovitla' };
        if (jutro && mj.osv >= 0.85) return { o: 1, t: 'pun mjesec – divljač se hranila noću' };
        if (prije >= 0.5) return { o: 2, t: 'vedri se nakon kiše – divljač izlazi' };
        if (!jutro && mj.osv <= 0.15) return { o: 2, t: 'mirno, tamna noć' };
        return { o: 2, t: 'mirno' + (vj >= 12 ? ', stalan vjetar' : '') + ', bez kiše' };
    }
    var OZN = [['❌', 'Loše', 'text-danger'], ['🤏', 'Slabije', 'text-warning'], ['✅', 'Dobro', 'text-success']];

    function crtaj(d) {
        var sad = new Date(), danas = new Date(sad.getFullYear(), sad.getMonth(), sad.getDate()), sutra = new Date(danas.getTime() + 864e5);
        var s = A.sunce(danas, lat, lon), s2 = A.sunce(sutra, lat, lon), mj = A.mjesec(sad);
        var dan = s.izlazak && s.zalazak ? Math.round((s.zalazak - s.izlazak) / 6e4) : 0;
        var h = '<div class="vrijeme-svjetlo">' + [['Zora', s.zora], ['Izlazak', s.izlazak, 1], ['Zalazak', s.zalazak, 1], ['Sumrak', s.sumrak]].map(function (x) {
            return '<div><span>' + x[0] + '</span><b' + (x[2] ? ' class="vr-istaknuto"' : '') + '>' + A.hhmm(x[1]) + '</b></div>';
        }).join('') + '<div><span>Dan</span><b>' + Math.floor(dan / 60) + ':' + ('0' + (dan % 60)).slice(-2) + ' h</b></div></div>';
        h += '<div class="vrijeme-red mt-2"><span class="vr-mjesec" aria-hidden="true">' + mj.ikona + '</span><div><b>' + esc(mj.naziv) + '</b>' +
            '<div class="small text-muted">' + Math.round(mj.osv * 100) + ' % osvijetljeno · ' + (mj.osv >= 0.85 ? 'Svijetla noć – divljač se hrani noću, ranije izlazi u sumrak.' : mj.osv <= 0.15 ? 'Tamna noć – divljač kasnije odlazi s hranilišta.' : 'Srednje svijetla noć.') + '</div></div>';
        if (d) {
            var sljedeci = (d.sati || []).slice(0, 6), vjer = Math.max.apply(null, sljedeci.map(function (x) { return x.vjer; }).concat([0]));
            h += '<div class="ms-auto text-end"><div class="fs-4 lh-1">' + (d.temp !== null && d.temp !== undefined ? Math.round(d.temp) + ' °C' : '') + '</div>' +
                '<div class="small text-muted text-nowrap">☔ ' + Math.round(vjer) + ' % (6 h)</div></div>';
        }
        h += '</div>';
        if (d && d.svi) {
            var raz = [
                ['Danas', 'jutro', s.zora, sat(s.izlazak, 2), true, s.izlazak], ['Danas', 'večer', sat(s.zalazak, -2), s.sumrak, false, s.zalazak],
                ['Sutra', 'jutro', s2.zora, sat(s2.izlazak, 2), true, s2.izlazak], ['Sutra', 'večer', sat(s2.zalazak, -2), s2.sumrak, false, s2.zalazak]
            ].map(function (r) { return { dan: r[0], doba: r[1], od: r[2], do_: r[3], proslo: r[3] < sad, oc: r[2] && r[3] ? ocijeni(r[2], r[3], d.svi, mj, r[4]) : null }; });
            var naj = raz.filter(function (r) { return !r.proslo && r.oc && r.oc.o === 2; })[0] || raz.filter(function (r) { return !r.proslo && r.oc && r.oc.o === 1; })[0];
            h += '<div class="vr-kad mt-3"><div class="small fw-semibold mb-1">🎯 Kad u lov?</div>';
            if (naj) h += '<div class="vr-najbolje mb-2"><b>Najbolje: ' + naj.dan.toLowerCase() + ' ' + (naj.doba === 'jutro' ? 'ujutro' : 'navečer') + '</b> · ' + esc(naj.oc.t) +
                ' · na čeki ' + A.hhmm(naj.od) + '–' + A.hhmm(naj.do_) + '</div>';
            h += '<table class="table table-sm mb-0 small"><tbody>' + raz.map(function (r) {
                var o = r.oc ? OZN[r.oc.o] : null;
                return '<tr class="' + (r.proslo ? 'text-muted vr-proslo' : '') + '"><td class="text-nowrap">' + r.dan + ' ' + r.doba + '<div class="text-muted">' + A.hhmm(r.od) + '–' + A.hhmm(r.do_) + '</div></td>' +
                    '<td>' + (r.proslo ? 'prošlo' : o ? o[0] + ' <b class="' + o[2] + '">' + o[1] + '</b> – ' + esc(r.oc.t) : '–') + '</td></tr>';
            }).join('') + '</tbody></table><div class="small text-muted mt-1">Prema prognozi vjetra, kiše i Mjesecu – pomoć, ne pravilo.</div></div>';
        } else {
            h += '<div class="small text-muted mt-2">Prognoza (temperatura, kiša, „Kad u lov?“) nije dostupna bez interneta – Sunce i Mjesec rade i bez njega.</div>';
        }
        tijelo.innerHTML = h;
        vrijemeEl.textContent = d ? (d.staro ? 'bez interneta – zadnja prognoza' : 'Open-Meteo · ' + (d.vrijeme ? d.vrijeme.slice(11, 16) : '')) : '';
    }
    function ucitaj() { crtaj(null); VJ.dohvati(lat, lon).then(crtaj).catch(function () { /* ostaje Sunce i Mjesec */ }); }
    ucitaj();
    setInterval(function () { if (!document.hidden) ucitaj(); }, 15 * 60 * 1000);
})();
