/* Lovačka evidencija – fotografije lovnih naprava: GPS iz EXIF-a (JPEG) i smanjivanje prije slanja. */
(function () {
    'use strict';
    /** GPS položaj iz EXIF-a JPEG fotografije → Promise<{lat, lon} | null>. */
    function gps(file) {
        return new Promise(function (ok) {
            if (!file || !/jpe?g$/i.test(file.type || file.name)) { ok(null); return; }
            var r = new FileReader();
            r.onload = function () {
                try { ok(citaj(new DataView(r.result))); } catch (x) { ok(null); }
            };
            r.onerror = function () { ok(null); };
            r.readAsArrayBuffer(file.slice(0, 256 * 1024));
        });
    }
    function citaj(v) {
        if (v.getUint16(0) !== 0xFFD8) return null;
        var p = 2;
        while (p + 4 < v.byteLength) {
            var m = v.getUint16(p), d = v.getUint16(p + 2);
            if (m === 0xFFE1 && v.getUint32(p + 4) === 0x45786966) return tiff(v, p + 10); // "Exif"
            if ((m & 0xFF00) !== 0xFF00) return null;
            p += 2 + d;
        }
        return null;
    }
    function tiff(v, t) {
        var le = v.getUint16(t) === 0x4949;
        var u16 = function (o) { return v.getUint16(t + o, le); }, u32 = function (o) { return v.getUint32(t + o, le); };
        var ifd0 = u32(4), n = u16(ifd0), gpsOff = null;
        for (var i = 0; i < n; i++) { var e = ifd0 + 2 + i * 12; if (u16(e) === 0x8825) gpsOff = u32(e + 8); }
        if (gpsOff === null) return null;
        var g = {}, cnt = u16(gpsOff);
        for (var j = 0; j < cnt; j++) {
            var en = gpsOff + 2 + j * 12, tag = u16(en), off = u32(en + 8);
            if (tag === 1 || tag === 3) g[tag] = String.fromCharCode(v.getUint8(t + en + 8));
            if (tag === 2 || tag === 4) {
                var s = 0;
                for (var k = 0; k < 3; k++) { var den = u32(off + k * 8 + 4); s += (den ? u32(off + k * 8) / den : 0) / Math.pow(60, k); }
                g[tag] = s;
            }
        }
        if (g[2] === undefined || g[4] === undefined || (g[2] === 0 && g[4] === 0)) return null;
        return { lat: g[1] === 'S' ? -g[2] : g[2], lon: g[3] === 'W' ? -g[4] : g[4] };
    }
    /** Smanjuje fotografiju na najviše max px (JPEG) – manje podataka u lovištu i bez ograničenja veličine na poslužitelju. */
    function smanji(file, max) {
        max = max || 1600;
        return new Promise(function (ok) {
            if (!file || !/^image\//.test(file.type) || !window.HTMLCanvasElement) { ok(file); return; }
            var url = URL.createObjectURL(file), img = new Image();
            img.onload = function () {
                var s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
                if (s === 1 && file.size < 1500000) { URL.revokeObjectURL(url); ok(file); return; }
                var c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * s); c.height = Math.round(img.naturalHeight * s);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                URL.revokeObjectURL(url);
                c.toBlob(function (b) { ok(b ? new File([b], (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(url); ok(file); };
            img.src = url;
        });
    }
    window.RevirFoto = { gps: gps, smanji: smanji };
})();
