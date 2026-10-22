<?php
/** Naknadni upis (zaboravljen sken, bez signala…) – uvijek s razlogom, u knjizi označen kao „naknadno“. */
trazi(P_DEZ_UREDI);
dez_osiguraj_stanice();
$k = korisnik();
$stanice = array_values(array_filter(dez_stanice(), fn($s) => $s['Aktivna']));
$razlozi = dez_razlozi();
$mobIds = array_map(fn($s) => (int) $s['Id'], dez_stanice(true, 'M'));
$akcije = $mobIds ? redovi('SELECT a.*, st.SekcijaId, st.Naziv AS Stanica FROM DezAktivacije a JOIN DezStanice st ON st.Id=a.StanicaId
    WHERE a.StanicaId IN (' . implode(',', $mobIds) . ') ORDER BY a.Od DESC LIMIT 15') : [];
// sken iskaznice → član (JSON za obrazac)
if (isset($_GET['isk'])) {
    $token = isk_token_iz_teksta((string) $_GET['isk']);
    $c = $token ? isk_clan($token) : null;
    if (!$c) {
        json(['ok' => false, 'greska' => $token ? 'Iskaznica ne vrijedi (zamijenjena novom ili član više ne postoji).' : 'Ovo nije članska iskaznica.']);
    }
    if ((int) $c['Status'] !== CLAN_AKTIVAN) {
        json(['ok' => false, 'greska' => prezime_ime($c) . ' nije aktivan član.']);
    }
    json(['ok' => true, 'id' => (int) $c['Id'], 'ime' => prezime_ime($c), 'vozila' => array_values(array_filter(array_map(fn($v) => (string) $v['Oznaka'], vozila_clana((int) $c['Id']))))]);
}
if (je_post()) {
    $st = null;
    $akc = null;
    $izbor = (string) ($_POST['stanica'] ?? '');
    foreach ($stanice as $s) {
        if ('s' . $s['Id'] === $izbor || (string) $s['Id'] === $izbor) {
            $st = $s;
        }
    }
    foreach ($akcije as $a) {
        if ('a' . $a['Id'] === $izbor) {
            $akc = $a;
            $st = ['Id' => $a['StanicaId'], 'SekcijaId' => $a['SekcijaId']];
        }
    }
    $d = u_datum(ul_str('datum'));
    $vr = preg_match('/^\d{1,2}:\d{2}$/', ul_str('vrijeme')) ? ul_str('vrijeme') : null;
    $smjer = (string) ($_POST['smjer'] ?? '');
    $raz = red('SELECT * FROM DezRazlozi WHERE Id=?', [(int) ($_POST['razlog'] ?? 0)]);
    $zasto = mb_substr(ul_str('naknadno_razlog'), 0, 200);
    $clanId = ul_int('clan');
    $c = $clanId ? red('SELECT * FROM Clanovi WHERE Id=?', [$clanId]) : null;
    $ime = $c ? $c['Ime'] : mb_substr(ul_str('ime'), 0, 60);
    $prez = $c ? $c['Prezime'] : mb_substr(ul_str('prezime'), 0, 60);
    $t = $d && $vr ? strtotime("$d $vr") : false;
    $greska = match (true) {
        !$st => 'Odaberite stanicu.',
        !$t => 'Upišite datum i vrijeme.',
        $t > time() + 60 => 'Vrijeme ne može biti u budućnosti.',
        !isset(DEZ_SMJER[$smjer]) => 'Odaberite dolazak ili odlazak.',
        !$raz => 'Odaberite razlog dolaska.',
        $ime === '' || $prez === '' => 'Odaberite člana ili upišite ime i prezime gosta.',
        default => null,
    };
    if ($greska) {
        poruka($greska, 'warning');
        $_SESSION['dez_obrazac'] = $_POST;
        preusmjeri('dezinfekcija/naknadno');
    }
    $razlog = $raz['Naziv'] . ($raz['Slobodno'] && ul_str('razlog_opis') !== '' ? ': ' . mb_substr(ul_str('razlog_opis'), 0, 80) : '');
    $oznaka = normaliziraj_oznaku(ul_str('oznaka')) ?: null;
    $id = umetni('DezUpisi', [
        'StanicaId' => (int) $st['Id'], 'SekcijaId' => $st['SekcijaId'], 'AktivacijaId' => $akc ? (int) $akc['Id'] : null, 'Vrijeme' => date('Y-m-d H:i:s', $t), 'Smjer' => $smjer,
        'ClanId' => $c ? (int) $c['Id'] : null, 'Ime' => $ime, 'Prezime' => $prez, 'Gost' => $c ? 0 : 1, 'Oznaka' => $oznaka, 'Razlog' => $razlog,
        'Vozilo' => isset($_POST['d_vozilo']) ? 1 : 0, 'Obuca' => isset($_POST['d_obuca']) ? 1 : 0, 'Oprema' => isset($_POST['d_oprema']) ? 1 : 0,
        'UpisaoKorisnikId' => $k['Id'], 'UpisaoIme' => $k['Naziv'], 'PozvaoIme' => $c ? null : $k['Naziv'],
        'Lokacija' => DEZ_LOK_NAKNADNO, 'Naknadno' => 1, 'NaknadnoRazlog' => $zasto !== '' ? $zasto : null, 'Grupa' => bin2hex(random_bytes(8)), 'Kreirano' => sada(),
    ]);
    dnevnik('Dezinfekcija – naknadni upis', 'DezUpis', $id, "$ime $prez, " . date('d.m.Y. H:i', $t) . ' ' . DEZ_SMJER[$smjer] . ($zasto !== '' ? ': ' . $zasto : ''));
    poruka(e("Naknadno upisano: $ime $prez, " . date('d.m.Y. H:i', $t) . '.'));
    preusmjeri(isset($_POST['jos']) ? 'dezinfekcija/naknadno' : 'dezinfekcija');
}
$o = $_SESSION['dez_obrazac'] ?? [];
unset($_SESSION['dez_obrazac']);
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime, (SELECT Oznaka FROM ClanVozila v WHERE v.ClanId=c.Id ORDER BY Zadano DESC, Id LIMIT 1) AS Oznaka
    FROM Clanovi c WHERE c.Status=0 ORDER BY c.Prezime, c.Ime');
ob_start(); ?>
<h1 class="h3 mb-1">Naknadni upis</h1>
<p class="text-muted">Za upise koji nisu napravljeni na stanici (zaboravljen sken, nema signala…). U knjizi i PDF-u piše „naknadno“, tko je upisao i zašto.</p>
<form method="post" action="<?= e(url('dezinfekcija/naknadno')) ?>" class="card card-body" style="max-width:720px"><?= csrf() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Stanica</label>
            <select name="stanica" class="form-select" required><optgroup label="Stalne stanice"><?php foreach ($stanice as $s): ?><option value="s<?= (int) $s['Id'] ?>"<?= sel('s' . $s['Id'], $o['stanica'] ?? '') ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?></optgroup>
                <?php if ($akcije): ?><optgroup label="Mobilne stanice (akcije)"><?php foreach ($akcije as $a): ?><option value="a<?= (int) $a['Id'] ?>"<?= sel('a' . $a['Id'], $o['stanica'] ?? '') ?>><?= e($a['Naziv'] . ' · ' . datum($a['Od'])) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label">Datum</label><input type="date" name="datum" id="n-datum" class="form-control" required max="<?= danas() ?>" value="<?= e($o['datum'] ?? danas()) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label">Vrijeme</label><input type="time" name="vrijeme" id="n-vrijeme" class="form-control" required value="<?= e($o['vrijeme'] ?? '') ?>"></div>
        <div class="col-12"><div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="smjer" id="n-d" value="D"<?= chk(($o['smjer'] ?? 'D') === 'D') ?>><label class="btn btn-outline-success" for="n-d">Dolazak</label>
            <input type="radio" class="btn-check" name="smjer" id="n-o" value="O"<?= chk(($o['smjer'] ?? '') === 'O') ?>><label class="btn btn-outline-warning" for="n-o">Odlazak</label></div></div>
        <div class="col-md-6"><label class="form-label">Član</label>
            <div class="input-group">
                <select name="clan" id="n-clan" class="form-select"><option value="">— gost (upiši ispod) —</option>
                    <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>" data-oznaka="<?= e($c['Oznaka']) ?>"<?= sel($c['Id'], $o['clan'] ?? '') ?>><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline-success" id="n-sken" title="Skeniraj člansku iskaznicu">📷 Iskaznica</button>
            </div>
            <div id="n-sken-poruka" class="small mt-1" hidden></div>
            <div id="n-kamera" class="skener-kamera mt-2" hidden>
                <video id="n-video" playsinline muted></video>
                <div class="skener-okvir-ciljnik"></div>
                <button type="button" class="btn btn-light btn-sm skener-zatvori" id="n-stop">✕</button>
            </div></div>
        <div class="col-6 col-md-3 gost-polje"><label class="form-label">Ime gosta</label><input name="ime" class="form-control" maxlength="60" value="<?= e($o['ime'] ?? '') ?>"></div>
        <div class="col-6 col-md-3 gost-polje"><label class="form-label">Prezime gosta</label><input name="prezime" class="form-control" maxlength="60" value="<?= e($o['prezime'] ?? '') ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">Reg. oznaka</label><input name="oznaka" id="n-oznaka" class="form-control text-uppercase" maxlength="20" value="<?= e($o['oznaka'] ?? '') ?>">
            <div id="n-vozila" class="d-flex flex-wrap gap-1 mt-1" hidden></div></div>
        <div class="col-6 col-md-4"><label class="form-label">Razlog dolaska</label>
            <select name="razlog" class="form-select" required><?php foreach ($razlozi as $r): ?><option value="<?= (int) $r['Id'] ?>"<?= sel($r['Id'], $o['razlog'] ?? '') ?>><?= e($r['Naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Opis (za „Ostalo“)</label><input name="razlog_opis" class="form-control" maxlength="80" value="<?= e($o['razlog_opis'] ?? '') ?>"></div>
        <div class="col-12"><span class="form-label d-block">Dezinficirano</span>
            <?php foreach (['d_vozilo' => 'vozilo', 'd_obuca' => 'obuća', 'd_oprema' => 'oprema'] as $n => $t): ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="<?= $n ?>" id="<?= $n ?>" value="1"<?= chk(!$o || isset($o[$n])) ?>><label class="form-check-label" for="<?= $n ?>"><?= $t ?></label></div>
            <?php endforeach; ?></div>
        <div class="col-12"><label class="form-label">Zašto naknadno? <span class="small text-muted">(nije obavezno)</span></label><input name="naknadno_razlog" class="form-control" maxlength="200" placeholder="npr. nije bilo signala, zaboravio skenirati" value="<?= e($o['naknadno_razlog'] ?? '') ?>"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary">Spremi</button>
        <button class="btn btn-outline-primary" name="jos" value="1">Spremi i upiši još jedan</button>
        <a class="btn btn-link" href="<?= e(url('dezinfekcija')) ?>">Odustani</a>
    </div>
</form>
<script>
(function () {
    var c = document.getElementById('n-clan'), oz = document.getElementById('n-oznaka');
    function pr() { var gost = !c.value; document.querySelectorAll('.gost-polje').forEach(function (d) { d.hidden = !gost; });
        var o = c.options[c.selectedIndex]; if (!gost && !oz.value && o.getAttribute('data-oznaka')) oz.value = o.getAttribute('data-oznaka'); }
    c.addEventListener('change', function () { oz.value = ''; var v = document.getElementById('n-vozila'); if (v) { v.hidden = true; v.innerHTML = ''; } pr(); }); pr();

    // sken članske iskaznice (QR) → odabir člana
    var btn = document.getElementById('n-sken'), kam = document.getElementById('n-kamera'), video = document.getElementById('n-video'),
        msg = document.getElementById('n-sken-poruka'), tok = null, radi = false, det = null,
        pl = document.createElement('canvas'), ctx = pl.getContext('2d', { willReadFrequently: true });
    var url = <?= json_encode(url('dezinfekcija/naknadno'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> + '&isk=';
    var voz = document.getElementById('n-vozila');
    function dvije(n) { return (n < 10 ? '0' : '') + n; }
    function vozila(lista) {
        voz.innerHTML = ''; voz.hidden = lista.length < 2;
        if (lista.length === 1) { oz.value = lista[0]; return; }
        if (lista.length > 1) {
            oz.value = lista[0];
            lista.forEach(function (o) {
                var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm ' + (o === oz.value ? 'btn-secondary' : 'btn-outline-secondary'); b.textContent = o;
                b.addEventListener('click', function () { oz.value = o; voz.querySelectorAll('button').forEach(function (x) { x.className = 'btn btn-sm btn-outline-secondary'; }); b.className = 'btn btn-sm btn-secondary'; });
                voz.appendChild(b);
            });
        }
    }
    function javi(t, kl) { msg.hidden = false; msg.className = 'small mt-1 ' + kl; msg.textContent = t; }
    function stani() { radi = false; if (tok) { tok.getTracks().forEach(function (x) { x.stop(); }); tok = null; } kam.hidden = true; }
    function nadjeno(tekst) {
        stani();
        javi('Provjeravam iskaznicu…', 'text-muted');
        fetch(url + encodeURIComponent(tekst), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) { javi('✖ ' + d.greska, 'text-danger'); return; }
                c.value = String(d.id); oz.value = ''; pr();
                if (c.value !== String(d.id)) { javi('✖ ' + d.ime + ' nije na popisu članova.', 'text-danger'); return; }
                // trenutak skeniranja = datum i vrijeme upisa
                var t = new Date();
                document.getElementById('n-datum').value = t.getFullYear() + '-' + dvije(t.getMonth() + 1) + '-' + dvije(t.getDate());
                document.getElementById('n-vrijeme').value = dvije(t.getHours()) + ':' + dvije(t.getMinutes());
                vozila(d.vozila || []);
                javi('✔ ' + d.ime + ((d.vozila || []).length > 1 ? ' – odaberite vozilo' : ''), 'text-success fw-semibold');
                try { navigator.vibrate && navigator.vibrate(80); } catch (x) { }
            })
            .catch(function () { javi('✖ Provjera nije uspjela – treba signal. Odaberite člana s popisa.', 'text-danger'); });
    }
    function okvir() {
        if (!radi) return;
        if (video.readyState >= 2 && video.videoWidth) {
            if (det) {
                det.detect(video).then(function (k) { if (k && k.length) nadjeno(k[0].rawValue); else setTimeout(okvir, 120); })
                    .catch(function () { det = null; setTimeout(okvir, 120); });
                return;
            }
            var m = Math.min(1, 720 / Math.max(video.videoWidth, video.videoHeight));
            pl.width = Math.round(video.videoWidth * m); pl.height = Math.round(video.videoHeight * m);
            ctx.drawImage(video, 0, 0, pl.width, pl.height);
            var s = ctx.getImageData(0, 0, pl.width, pl.height), k = window.jsQR && jsQR(s.data, s.width, s.height, { inversionAttempts: 'dontInvert' });
            if (k && k.data) { nadjeno(k.data); return; }
        }
        setTimeout(okvir, 120);
    }
    btn.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { javi('Ovaj preglednik ne dopušta kameru – odaberite člana s popisa.', 'text-danger'); return; }
        javi('Usmjerite kameru na QR kod iskaznice…', 'text-muted');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (t) {
            tok = t; video.srcObject = t; kam.hidden = false; return video.play();
        }).then(function () {
            if ('BarcodeDetector' in window) { try { det = new BarcodeDetector({ formats: ['qr_code'] }); } catch (x) { det = null; } }
            radi = true; okvir();
        }).catch(function (e) { stani(); javi(e && e.name === 'NotAllowedError' ? 'Kamera nije dopuštena.' : 'Kamera se ne može pokrenuti.', 'text-danger'); });
    });
    document.getElementById('n-stop').addEventListener('click', function () { stani(); msg.hidden = true; });
    document.addEventListener('visibilitychange', function () { if (document.hidden) stani(); });
})();
</script>
<script src="<?= e(asset('assets/jsqr.min.js')) ?>"></script>
<?php stranica('Naknadni upis', ob_get_clean());
