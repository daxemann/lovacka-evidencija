/* Lovačka evidencija – vjetar: prognoza (Open-Meteo, besplatno, bez ključa), kompas, idealni smjerovi naprava.
 * Prognozu dohvaća preglednik izravno (ne poslužitelj) i čuva je 15 min na uređaju. */
window.EvVjetar = (function () {
    'use strict';
    var SMJ = ['S', 'SI', 'I', 'JI', 'J', 'JZ', 'Z', 'SZ'];
    var IZ = { S: 'sa sjevera', SI: 'sa sjeveroistoka', I: 's istoka', JI: 's jugoistoka', J: 's juga', JZ: 's jugozapada', Z: 's zapada', SZ: 'sa sjeverozapada' };
    var PREMA = { S: 'sjever', SI: 'sjeveroistok', I: 'istok', JI: 'jugoistok', J: 'jug', JZ: 'jugozapad', Z: 'zapad', SZ: 'sjeverozapad' };
    var TOLERANCIJA = 30; // ° oko označenog smjera (sektor 45° + malo zalihe)
    var TRAJANJE = 15 * 60 * 1000;

    function norm(d) { return ((d % 360) + 360) % 360; }
    function smjer(deg) { return SMJ[Math.round(norm(deg) / 45) % 8]; }
    function razlika(a, b) { var d = Math.abs(norm(a) - norm(b)); return d > 180 ? 360 - d : d; }
    /** true/false; null = naprava nema označene smjerove */
    function povoljan(lista, deg) {
        if (!lista || !lista.length || deg === null || deg === undefined) return null;
        return lista.some(function (s) { var i = SMJ.indexOf(s); return i >= 0 && razlika(deg, i * 45) <= TOLERANCIJA; });
    }
    function beaufort(kmh) {
        var g = [1, 6, 12, 20, 29, 39, 50, 62, 75, 89, 103, 118], i = 0;
        while (i < g.length && kmh >= g[i]) i++;
        return i;
    }
    function opisSnage(kmh) {
        if (kmh < 2) return 'tišina – miris se diže i kovitla';
        if (kmh < 6) return 'lahor';
        if (kmh < 12) return 'povjetarac';
        if (kmh < 20) return 'slab vjetar';
        if (kmh < 29) return 'umjeren vjetar';
        if (kmh < 39) return 'umjereno jak vjetar – divljač opreznija';
        return 'jak vjetar – divljač se zavlači u zaklon';
    }

    function dohvati(lat, lon) {
        var kljuc = 'ev-vrijeme2:' + (+lat).toFixed(2) + ',' + (+lon).toFixed(2), sad = Date.now();
        try {
            var c = JSON.parse(localStorage.getItem(kljuc) || 'null');
            if (c && sad - c.t < TRAJANJE) return Promise.resolve(c.d);
        } catch (x) { /* ništa */ }
        var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + (+lat).toFixed(3) + '&longitude=' + (+lon).toFixed(3) +
            '&current=temperature_2m,wind_speed_10m,wind_direction_10m,wind_gusts_10m,precipitation,cloud_cover' +
            '&hourly=wind_speed_10m,wind_direction_10m,wind_gusts_10m,temperature_2m,precipitation,precipitation_probability&past_hours=6&forecast_hours=48&wind_speed_unit=kmh&timezone=auto';
        return fetch(url).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(function (j) {
            var c = j.current || {}, h = j.hourly || {};
            var d = {
                smjer: c.wind_direction_10m, brzina: c.wind_speed_10m, udari: c.wind_gusts_10m, temp: c.temperature_2m,
                oborine: c.precipitation, naoblaka: c.cloud_cover, vrijeme: c.time,
                // svi sati (6 prošlih + 48 sljedećih) za ocjenu „Kad u lov?“; „sati“ = sljedećih 12 za prikaz vjetra
                svi: (h.time || []).map(function (t, i) {
                    return { t: t, sat: t.slice(11, 13), smjer: h.wind_direction_10m[i], brzina: h.wind_speed_10m[i], udari: (h.wind_gusts_10m || [])[i],
                        temp: (h.temperature_2m || [])[i], kisa: (h.precipitation || [])[i] || 0, vjer: (h.precipitation_probability || [])[i] || 0 };
                })
            };
            var od = (c.time || '').slice(0, 13);
            d.sati = d.svi.filter(function (x) { return x.t.slice(0, 13) >= od; }).slice(0, 12);
            try { localStorage.setItem(kljuc, JSON.stringify({ t: sad, d: d })); } catch (x) { /* ništa */ }
            return d;
        }).catch(function (e) {
            // bez interneta: zadnja spremljena prognoza (i starija), ako postoji
            try { var c = JSON.parse(localStorage.getItem(kljuc) || 'null'); if (c) { c.d.staro = true; return c.d; } } catch (x) { /* ništa */ }
            throw e;
        });
    }

    /** SVG kompas: strelica pokazuje kamo vjetar puše (od odakle puše). */
    function kompas(deg, kmh, velicina) {
        velicina = velicina || 130;
        var r = 50, crte = '', i;
        for (i = 0; i < 72; i++) {
            var a = i * 5 * Math.PI / 180, dug = i % 18 === 0 ? 7 : (i % 9 === 0 ? 5 : 2.5);
            crte += '<line x1="' + (60 + Math.sin(a) * r).toFixed(1) + '" y1="' + (60 - Math.cos(a) * r).toFixed(1) + '" x2="' + (60 + Math.sin(a) * (r - dug)).toFixed(1) +
                '" y2="' + (60 - Math.cos(a) * (r - dug)).toFixed(1) + '" class="vk-crta' + (i % 18 === 0 ? ' vk-glavna' : '') + '"/>';
        }
        var slova = [['S', 60, 34], ['I', 90, 64], ['J', 60, 94], ['Z', 30, 64]].map(function (s) {
            return '<text x="' + s[1] + '" y="' + s[2] + '" class="vk-slovo" text-anchor="middle">' + s[0] + '</text>';
        }).join('');
        var strelica = deg === null || deg === undefined ? '' :
            '<g transform="rotate(' + norm(deg + 180).toFixed(0) + ' 60 60)"><path d="M60 6 L67 20 L62 18 L62 30 L58 30 L58 18 L53 20 Z" class="vk-strelica"/>' +
            '<circle cx="60" cy="104" r="3" class="vk-rep"/></g>';
        return '<svg class="vjetar-kompas" viewBox="0 0 120 120" width="' + velicina + '" height="' + velicina + '" role="img" aria-label="Kompas vjetra">' +
            '<circle cx="60" cy="60" r="55" class="vk-krug"/>' + crte + slova + strelica +
            '<circle cx="60" cy="60" r="20" class="vk-sredina"/><text x="60" y="63" class="vk-broj" text-anchor="middle">' + (kmh === null || kmh === undefined ? '–' : Math.round(kmh)) + '</text>' +
            '<text x="60" y="73" class="vk-jed" text-anchor="middle">km/h</text></svg>';
    }

    /** Mala strelica za red sati (kamo puše). */
    function strelica(deg) {
        return '<span class="vjetar-strelica" style="transform:rotate(' + norm(deg + 180).toFixed(0) + 'deg)" title="iz ' + smjer(deg) + '">↑</span>';
    }

    /** Stabilnost smjera u sljedećih n sati (najveće odstupanje od sadašnjeg). */
    function stabilnost(d, n) {
        var max = 0;
        (d.sati || []).slice(0, n || 8).forEach(function (s) { max = Math.max(max, razlika(s.smjer, d.smjer)); });
        return max;
    }

    return { SMJ: SMJ, IZ: IZ, PREMA: PREMA, smjer: smjer, povoljan: povoljan, beaufort: beaufort, opisSnage: opisSnage, dohvati: dohvati, kompas: kompas, strelica: strelica, stabilnost: stabilnost, razlika: razlika };
})();
