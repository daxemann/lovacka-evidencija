/* Lovačka evidencija – potpis prstom / mišem u polju (canvas) → skriveno polje potpis_crtez (PNG). */
document.querySelectorAll('.potpis-obrazac').forEach(function (f) {
    var c = f.querySelector('canvas'), x = c.getContext('2d'), crta = false, ima = false;
    x.lineWidth = 3.2; x.lineCap = 'round'; x.lineJoin = 'round'; x.strokeStyle = '#0b2a6b';
    function tocka(e) { var r = c.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return [(t.clientX - r.left) * c.width / r.width, (t.clientY - r.top) * c.height / r.height]; }
    function pocni(e) { e.preventDefault(); crta = true; var p = tocka(e); x.beginPath(); x.moveTo(p[0], p[1]); }
    function crtaj(e) { if (!crta) return; e.preventDefault(); var p = tocka(e); x.lineTo(p[0], p[1]); x.stroke(); ima = true; }
    function kraj() { crta = false; }
    c.addEventListener('mousedown', pocni); c.addEventListener('mousemove', crtaj); window.addEventListener('mouseup', kraj);
    c.addEventListener('touchstart', pocni, { passive: false }); c.addEventListener('touchmove', crtaj, { passive: false }); c.addEventListener('touchend', kraj);
    f.querySelector('[data-ocisti]').onclick = function () { x.clearRect(0, 0, c.width, c.height); ima = false; };
    f.addEventListener('submit', function (e) {
        var fi = f.querySelector('input[type=file]');
        if (fi && fi.files.length) return;
        if (!ima) { e.preventDefault(); alert('Najprije se potpišite u polju.'); return; }
        f.querySelector('input[name=potpis_crtez]').value = c.toDataURL('image/png');
    });
});
