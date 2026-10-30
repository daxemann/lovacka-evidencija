/* Lovačka evidencija – početna: blok „💨 Vjetar u lovištu“ (kompas, 12 sati, čeke za ovaj vjetar). */
(function () {
    'use strict';
    var blok = document.querySelector('[data-vjetar-blok]');
    if (!blok || !window.EvVjetar) return;
    var VJ = window.EvVjetar, pod = JSON.parse(blok.querySelector('[data-vj-podaci]').textContent);
    var tijelo = blok.querySelector('[data-vj-tijelo]'), vrijemeEl = blok.querySelector('[data-vj-vrijeme]');
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    function prikazi(d) {
        var sm = VJ.smjer(d.smjer), bf = VJ.beaufort(d.brzina), stab = VJ.stabilnost(d, 8);
        var dobre = pod.n.filter(function (n) { return VJ.povoljan(n.v, d.smjer); });
        var h = '<div class="vjetar-glavno">' + VJ.kompas(d.smjer, d.brzina, 128) + '<div class="flex-grow-1">' +
            '<div class="small text-muted">Vjetar puše</div><div class="h4 mb-1">' + esc(VJ.IZ[sm].charAt(0).toUpperCase() + VJ.IZ[sm].slice(1)) + ' <span class="text-muted fs-6">' + Math.round(d.smjer) + '°</span></div>' +
            '<div class="d-flex flex-wrap gap-1 mb-2"><span class="badge text-bg-secondary">' + bf + ' Bf</span><span class="badge text-bg-secondary">udari ' + Math.round(d.udari || 0) + ' km/h</span>' +
            (d.temp !== null && d.temp !== undefined ? '<span class="badge text-bg-secondary">' + Math.round(d.temp) + ' °C</span>' : '') + '</div>' +
            '<div class="small">Divljač očekujte <b>' + esc(VJ.IZ[sm]) + '</b> strane – čeki prilazite s vjetrom u lice, vaš miris odlazi prema <b>' + esc(VJ.PREMA[VJ.smjer(d.smjer + 180)]) + 'u</b>.</div>' +
            '<div class="small text-muted mt-1">' + esc(VJ.opisSnage(d.brzina)) + ' · ' + (stab <= 30 ? '✓ smjer stabilan sljedećih 8 sati' : '⚠ smjer se mijenja u sljedećih 8 sati') + '</div>' +
            '</div></div>';
        if (d.sati && d.sati.length) {
            h += '<div class="vjetar-sati mt-3">' + d.sati.map(function (s, i) {
                return '<div><span class="small text-muted">' + (i === 0 ? 'sada' : s.sat + 'h') + '</span>' + VJ.strelica(s.smjer) + '<span class="small">' + Math.round(s.brzina) + '</span></div>';
            }).join('') + '</div>';
        }
        if (pod.n.length) {
            h += '<div class="mt-3"><div class="small fw-semibold mb-1">Čeke za ovaj vjetar (' + dobre.length + ' od ' + pod.n.length + ')</div>' +
                (dobre.length ? '<div class="d-flex flex-wrap gap-1">' + dobre.map(function (n) {
                    return '<a class="btn btn-sm btn-outline-success" href="' + esc(pod.karta) + '&naprava=' + n.id + '">✓ ' + esc(n.n) + '</a>';
                }).join('') + '</div>' : '<div class="small text-muted">Trenutni vjetar ne odgovara nijednoj čeki s upisanim smjerovima.</div>') + '</div>';
        } else if (pod.u) {
            h += '<div class="small text-muted mt-3">Upišite idealne smjerove vjetra na napravama (Karta lovišta → ✏️ Uredi naprave) – ovdje će se pokazati koje čeke odgovaraju vjetru.</div>';
        }
        tijelo.innerHTML = h;
        vrijemeEl.textContent = (d.staro ? 'bez interneta – zadnja prognoza ' : 'Open-Meteo · ') + (d.vrijeme ? d.vrijeme.slice(11, 16) : '');
    }
    function ucitaj() {
        VJ.dohvati(pod.t[0], pod.t[1]).then(prikazi).catch(function () { tijelo.innerHTML = '<div class="small text-muted">Vjetar trenutno nije dostupan (nema interneta?).</div>'; });
    }
    ucitaj();
    setInterval(function () { if (!document.hidden) ucitaj(); }, 15 * 60 * 1000);
})();
