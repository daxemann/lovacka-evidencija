/* Lovačka evidencija – stranica "Skeniraj" (radi i bez interneta).
 * Skener QR oznake unutar aplikacije: na iPhoneu kamera iz aplikacije (a ne Safari) → upis ostaje u aplikaciji
 * i koristi stranice spremljene u aplikaciji. Prepoznaje se samo oznaka dezinfekcijske stanice (p=dez&s=…),
 * i ako je ispisana s drugom adresom programa (npr. stalna adresa za QR) – otvara se stanica na ovoj adresi. */
(function () {
    'use strict';
    var C = window.SKENER || {};
    var $ = function (id) { return document.getElementById(id); };
    var video = $('sk-video'), kamera = $('sk-kamera'), poruka = $('sk-poruka'), start = $('sk-start');
    var tok = null, platno = document.createElement('canvas'), ctx = platno.getContext('2d', { willReadFrequently: true });
    var detektor = null, radi = false;

    // stranica stanice koja nije spremljena (service worker je otvorio ovu stranicu umjesto nje)
    var q = new URLSearchParams(location.search);
    if (q.get('p') === 'dez' && q.get('s')) $('sk-nema-stanice').hidden = false;

    function mreza() {
        var b = $('sk-mreza'), on = navigator.onLine !== false;
        b.className = 'badge rounded-pill ' + (on ? 'text-bg-success' : 'text-bg-warning');
        b.textContent = on ? '🟢 Internet' : '📴 Bez signala';
        $('sk-aplikacija').classList.toggle('disabled', !on);
    }
    window.addEventListener('online', function () { mreza(); stanice(); });
    window.addEventListener('offline', mreza);
    mreza();

    function token(tekst) {
        try {
            var u = new URL(String(tekst).trim(), location.href);
            if (u.searchParams.get('p') === 'dez' && /^[a-z0-9]{6,40}$/.test(u.searchParams.get('s') || '')) return u.searchParams.get('s');
        } catch (x) { /* nije adresa */ }
        return null;
    }
    function adresaStanice(s) { return (C.stanica || '').replace('__S__', encodeURIComponent(s)); }

    function stanice() {
        var p = (window.DEZ_RED && DEZ_RED.stanice()) || [], el = $('sk-stanice');
        if (!p.length) return;
        var ul = document.createElement('div'); ul.className = 'list-group';
        p.forEach(function (s) {
            var a = document.createElement('a');
            a.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
            a.href = new URL(s.url, location.href).href;
            a.innerHTML = '<span class="flex-grow-1"></span><span class="badge text-bg-light border sk-stanje"></span>';
            a.firstChild.textContent = (s.mob ? '🚙 ' : '📍 ') + s.naziv + (s.sekcija ? ' · ' + s.sekcija : '');
            ul.appendChild(a);
            if (window.caches) caches.match(a.href).then(function (m) {
                var b = a.querySelector('.sk-stanje');
                b.textContent = m ? 'spremljeno' : 'nije spremljeno';
                b.className = 'badge sk-stanje ' + (m ? 'text-bg-success' : 'text-bg-light border');
            });
        });
        el.innerHTML = ''; el.appendChild(ul);
        var n = document.createElement('div'); n.className = 'form-text';
        n.textContent = 'Bez signala možete otvoriti stanice označene „spremljeno“. Mobilna stanica mora biti aktivirana – provjerava se pri slanju.';
        el.appendChild(n);
    }
    stanice();
    if (window.DEZ_RED) DEZ_RED.provjeri().then(function (j) { if (j) stanice(); });

    var posalji = $('sk-posalji');
    if (posalji) posalji.addEventListener('click', function () { posalji.disabled = true; DEZ_RED.sinkroniziraj().then(function () { posalji.disabled = false; }); });

    // ---------- skener ----------
    function stani() {
        radi = false;
        if (tok) { tok.getTracks().forEach(function (t) { t.stop(); }); tok = null; }
        kamera.hidden = true; start.hidden = false;
    }
    function nadjeno(tekst) {
        var s = token(tekst);
        if (!s) { poruka.textContent = 'Ovo nije QR oznaka dezinfekcijske stanice.'; poruka.className = 'small text-center text-danger mb-3'; return false; }
        stani();
        poruka.textContent = 'Otvaram stanicu…'; poruka.className = 'small text-center text-success mb-3';
        if (navigator.vibrate) navigator.vibrate(60);
        location.href = adresaStanice(s);
        return true;
    }
    function okvir() {
        if (!radi) return;
        if (video.readyState >= 2 && video.videoWidth) {
            if (detektor) {
                detektor.detect(video).then(function (k) {
                    if (k && k.length && nadjeno(k[0].rawValue)) return;
                    setTimeout(okvir, 150);
                }).catch(function () { detektor = null; setTimeout(okvir, 150); });
                return;
            }
            var m = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
            platno.width = Math.round(video.videoWidth * m); platno.height = Math.round(video.videoHeight * m);
            ctx.drawImage(video, 0, 0, platno.width, platno.height);
            var slika = ctx.getImageData(0, 0, platno.width, platno.height);
            var k = window.jsQR && jsQR(slika.data, slika.width, slika.height, { inversionAttempts: 'dontInvert' });
            if (k && k.data && nadjeno(k.data)) return;
        }
        setTimeout(okvir, 150);
    }
    start.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            poruka.textContent = 'Ovaj preglednik ne dopušta kameru. Skenirajte QR kamerom mobitela.'; poruka.className = 'small text-center text-danger mb-3'; return;
        }
        poruka.textContent = 'Dopustite kameru ako mobitel pita…'; poruka.className = 'small text-center text-muted mb-3';
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (t) {
            tok = t; video.srcObject = t; kamera.hidden = false; start.hidden = true;
            video.setAttribute('playsinline', ''); video.muted = true;
            return video.play();
        }).then(function () {
            poruka.textContent = 'Usmjerite kameru na QR oznaku na stanici.';
            if ('BarcodeDetector' in window) { try { detektor = new BarcodeDetector({ formats: ['qr_code'] }); } catch (x) { detektor = null; } }
            radi = true; okvir();
        }).catch(function (e) {
            stani();
            poruka.className = 'small text-center text-danger mb-3';
            poruka.textContent = e && e.name === 'NotAllowedError'
                ? 'Kamera nije dopuštena. Dopustite kameru u postavkama mobitela za ovu aplikaciju / preglednik.'
                : 'Kamera se ne može pokrenuti. Skenirajte QR kamerom mobitela.';
        });
    });
    $('sk-stop').addEventListener('click', stani);
    document.addEventListener('visibilitychange', function () { if (document.hidden) stani(); });
})();
