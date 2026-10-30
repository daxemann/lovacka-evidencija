/* Lovačka evidencija – Sunce i Mjesec, računa se na uređaju (radi i bez interneta).
 * Formule prema SunCalc (c) Vladimir Agafonkin, BSD-2-Clause, https://github.com/mourner/suncalc */
window.EvAstro = (function () {
    'use strict';
    var PI = Math.PI, sin = Math.sin, cos = Math.cos, tan = Math.tan, asin = Math.asin, atan = Math.atan2, acos = Math.acos, rad = PI / 180;
    var dayMs = 864e5, J1970 = 2440588, J2000 = 2451545, e = rad * 23.4397, J0 = 0.0009;

    function toJulian(d) { return d.valueOf() / dayMs - 0.5 + J1970; }
    function fromJulian(j) { return new Date((j + 0.5 - J1970) * dayMs); }
    function toDays(d) { return toJulian(d) - J2000; }
    function rightAscension(l, b) { return atan(sin(l) * cos(e) - tan(b) * sin(e), cos(l)); }
    function declination(l, b) { return asin(sin(b) * cos(e) + cos(b) * sin(e) * sin(l)); }
    function solarMeanAnomaly(d) { return rad * (357.5291 + 0.98560028 * d); }
    function eclipticLongitude(M) {
        var C = rad * (1.9148 * sin(M) + 0.02 * sin(2 * M) + 0.0003 * sin(3 * M));
        return M + C + rad * 102.9372 + PI;
    }
    function sunCoords(d) { var L = eclipticLongitude(solarMeanAnomaly(d)); return { dec: declination(L, 0), ra: rightAscension(L, 0) }; }

    /** Zora (građanska), izlazak, zalazak, sumrak (građanski) za dan datuma – Date ili null (polarni dan/noć). */
    function sunce(datum, lat, lon) {
        var lw = rad * -lon, phi = rad * lat, d = toDays(new Date(datum.getFullYear(), datum.getMonth(), datum.getDate(), 12));
        var n = Math.round(d - J0 - lw / (2 * PI)), ds = J0 + lw / (2 * PI) + n;
        var M = solarMeanAnomaly(ds), L = eclipticLongitude(M), dec = declination(L, 0);
        var Jnoon = J2000 + ds + 0.0053 * sin(M) - 0.0069 * sin(2 * L);
        function par(h) {
            var x = (sin(h * rad) - sin(phi) * sin(dec)) / (cos(phi) * cos(dec));
            if (x < -1 || x > 1) return [null, null];
            var w = acos(x), a = J0 + (w + lw) / (2 * PI) + n;
            var Jset = J2000 + a + 0.0053 * sin(M) - 0.0069 * sin(2 * L);
            return [fromJulian(Jnoon - (Jset - Jnoon)), fromJulian(Jset)];
        }
        var s = par(-0.833), g = par(-6);
        return { zora: g[0], izlazak: s[0], podne: fromJulian(Jnoon), zalazak: s[1], sumrak: g[1] };
    }

    /** Mjesec: osvijetljenost 0–1, faza 0–1 (0 mlađak, 0.5 uštap), naziv, ikona. */
    function mjesec(datum) {
        var d = toDays(datum), s = sunCoords(d);
        var L = rad * (218.316 + 13.176396 * d), M = rad * (134.963 + 13.064993 * d), F = rad * (93.272 + 13.229350 * d);
        var l = L + rad * 6.289 * sin(M), b = rad * 5.128 * sin(F), dist = 385001 - 20905 * cos(M);
        var m = { ra: rightAscension(l, b), dec: declination(l, b) }, sdist = 149598000;
        var fi = acos(sin(s.dec) * sin(m.dec) + cos(s.dec) * cos(m.dec) * cos(s.ra - m.ra));
        var inc = atan(sdist * sin(fi), dist - sdist * cos(fi));
        var kut = atan(cos(s.dec) * sin(s.ra - m.ra), sin(s.dec) * cos(m.dec) - cos(s.dec) * sin(m.dec) * cos(s.ra - m.ra));
        var faza = 0.5 + 0.5 * inc * (kut < 0 ? -1 : 1) / PI, osv = (1 + cos(inc)) / 2;
        var t = 0.035, naziv, ikona;
        if (faza < t || faza > 1 - t) { naziv = 'Mlađak'; ikona = '🌑'; }
        else if (faza < 0.25 - t) { naziv = 'Rastući srp'; ikona = '🌒'; }
        else if (faza < 0.25 + t) { naziv = 'Prva četvrt'; ikona = '🌓'; }
        else if (faza < 0.5 - t) { naziv = 'Rastući mjesec'; ikona = '🌔'; }
        else if (faza < 0.5 + t) { naziv = 'Uštap (pun mjesec)'; ikona = '🌕'; }
        else if (faza < 0.75 - t) { naziv = 'Opadajući mjesec'; ikona = '🌖'; }
        else if (faza < 0.75 + t) { naziv = 'Zadnja četvrt'; ikona = '🌗'; }
        else { naziv = 'Opadajući srp'; ikona = '🌘'; }
        return { osv: osv, faza: faza, naziv: naziv, ikona: ikona };
    }

    function hhmm(d) { return d ? ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) : '–'; }
    return { sunce: sunce, mjesec: mjesec, hhmm: hhmm };
})();
