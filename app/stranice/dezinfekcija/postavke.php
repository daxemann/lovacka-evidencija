<?php
/** Dezinfekcija – stanice (po sekciji), razlozi dolaska, pristup za inspekciju. */
trazi(P_DEZ_POSTAVKE);
dez_osiguraj_stanice();
$stanice = array_merge(dez_stanice(), dez_stanice(true, 'M'));
$ids = array_map(fn($s) => (int) $s['Id'], $stanice);

/** "45.81234" ili "45,81234" → float; "45.8, 17.4" u jednom polju → [lat, lon]. */
$koord = function (string $lat, string $lon): array {
    if (preg_match('/^\s*(-?\d{1,3}(?:[.,]\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:[.,]\d+)?)\s*$/', $lat, $m) && trim($lon) === '') {
        [$lat, $lon] = [$m[1], $m[2]];
    }
    $a = u_broj($lat);
    $b = u_broj($lon);
    if ($a === null || $b === null || abs($a) > 90 || abs($b) > 180) {
        return [null, null];
    }
    return [round($a, 7), round($b, 7)];
};

// stari zajednički potpis (1.7.0) – sada je po sekciji
if (postavka('Dez.Potpis') || postavka('Dez.OdgovornaOsoba')) {
    obrisi_sliku(postavka('Dez.Potpis'));
    spremi_postavku('Dez.Potpis', null);
    spremi_postavku('Dez.OdgovornaOsoba', null);
}
/** Sekcija iz obrasca – samo sekcije stanica u opsegu korisnika. */
$sekcijeOdg = [];
foreach ($stanice as $s0) {
    if ($s0['Vrsta'] === 'F') {
        $sekcijeOdg[dez_kljuc_sekcije($s0['SekcijaId'] !== null ? (int) $s0['SekcijaId'] : null)] = [$s0['SekcijaId'] !== null ? (int) $s0['SekcijaId'] : null, $s0['SekcijaNaziv'] ?? 'Bez sekcije'];
    }
}
$sekcijaIzPosta = function () use ($sekcijeOdg): ?int {
    $kl = (string) ($_POST['sekcija'] ?? '');
    if (!isset($sekcijeOdg[$kl])) {
        zabranjeno();
    }
    return $sekcijeOdg[$kl][0];
};
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    switch ($radnja) {
        case 'stanice':
            foreach ((array) ($_POST['st'] ?? []) as $id => $s) {
                if (!in_array((int) $id, $ids, true)) {
                    continue;
                }
                $jeMob = vrijednost('SELECT Vrsta FROM DezStanice WHERE Id=?', [(int) $id]) === 'M';
                [$lat, $lon] = $jeMob ? [null, null] : $koord((string) ($s['Lat'] ?? ''), (string) ($s['Lon'] ?? ''));
                if (trim((string) ($s['Lat'] ?? '')) !== '' && $lat === null) {
                    poruka('Koordinate nisu ispravne (npr. 45.81234 i 17.45678) – ' . e((string) ($s['Naziv'] ?? '')), 'warning');
                    continue;
                }
                azuriraj('DezStanice', (int) $id, [
                    'Naziv' => mb_substr(trim((string) ($s['Naziv'] ?? '')), 0, 80) ?: 'Dezinfekcijska stanica',
                    'Lat' => $lat, 'Lon' => $lon,
                    'Radijus' => min(1000, max(20, (int) ($s['Radijus'] ?? 100))),
                    'Sredstvo' => mb_substr(trim((string) ($s['Sredstvo'] ?? '')), 0, 120) ?: null,
                    'Aktivna' => isset($s['Aktivna']) ? 1 : 0,
                ]);
            }
            dnevnik('Dezinfekcija – stanice spremljene');
            poruka('Stanice su spremljene.');
            break;
        case 'novi-qr':
            $id = (int) ($_POST['id'] ?? 0);
            if (in_array($id, $ids, true)) {
                azuriraj('DezStanice', $id, ['Token' => dez_novi_token()]);
                dnevnik('Dezinfekcija – nova QR oznaka stanice', 'DezStanica', $id);
                poruka('Nova QR oznaka je napravljena. <b>Stara više ne radi</b> – isprintajte i zamijenite oznaku na stanici.', 'warning');
            }
            break;
        case 'razlozi':
            foreach ((array) ($_POST['r'] ?? []) as $id => $r) {
                $n = mb_substr(trim((string) ($r['Naziv'] ?? '')), 0, 60);
                if ($n !== '') {
                    azuriraj('DezRazlozi', (int) $id, ['Naziv' => $n, 'Redoslijed' => (int) ($r['Redoslijed'] ?? 0), 'Aktivan' => isset($r['Aktivan']) ? 1 : 0, 'Slobodno' => isset($r['Slobodno']) ? 1 : 0]);
                }
            }
            if (ul_str('novi') !== '') {
                umetni('DezRazlozi', ['Naziv' => mb_substr(ul_str('novi'), 0, 60), 'Aktivan' => 1, 'Slobodno' => 0,
                    'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0)+1 FROM DezRazlozi WHERE Redoslijed<9')]);
            }
            dnevnik('Dezinfekcija – razlozi dolaska');
            poruka('Razlozi su spremljeni.');
            break;
        case 'inspekcija':
            $loz = trim((string) ($_POST['lozinka'] ?? ''));
            if ($loz !== '' && (mb_strlen($loz) < 6 || mb_strlen($loz) > 8 || !preg_match('/^[\p{L}\d]+$/u', $loz))) {
                poruka('Lozinka mora imati 6–8 znakova (slova i brojke, bez razmaka).', 'warning');
                break;
            }
            if ($loz !== '' && $loz !== dez_insp_lozinka()) {
                spremi_postavku('Dez.InspekcijaLozinka', sifriraj($loz));
                dnevnik('Dezinfekcija – promijenjena lozinka za inspekciju');
            } elseif ($loz === '' && postavka('Dez.InspekcijaLozinka')) {
                spremi_postavku('Dez.InspekcijaLozinka', null);
                dnevnik('Dezinfekcija – pristup za inspekciju isključen');
            }
            spremi_postavku('Dez.Loviste', mb_substr(ul_str('loviste'), 0, 120) ?: null);
            poruka('Spremljeno.');
            break;
        case 'odgovorna':
            $sid = $sekcijaIzPosta();
            $kl = dez_kljuc_sekcije($sid);
            $stara = (string) dez_odgovorna($sid);
            $nova = mb_substr(ul_str('odgovorna'), 0, 120);
            spremi_postavku('Dez.Odgovorna.' . $kl, $nova ?: null);
            if ($stara !== $nova && postavka('Dez.Potpis.' . $kl)) {
                // potpis pripada osobi – kod promjene osobe se briše
                obrisi_sliku(postavka('Dez.Potpis.' . $kl));
                spremi_postavku('Dez.Potpis.' . $kl, null);
                poruka('Odgovorna osoba je promijenjena – dosadašnji potpis je obrisan. Nova osoba neka se potpiše.', 'warning');
            }
            if ($stara !== $nova) {
                dnevnik('Dezinfekcija – odgovorna osoba', null, null, naziv_sekcije($sid) . ': ' . ($stara ?: '—') . ' → ' . ($nova ?: '—'));
            }
            poruka('Spremljeno.');
            break;
        case 'potpis':
            $sid = $sekcijaIzPosta();
            $kl = dez_kljuc_sekcije($sid);
            if (!dez_odgovorna($sid)) {
                poruka('Najprije upišite i spremite odgovornu osobu.', 'warning');
                break;
            }
            try {
                $png = (string) ($_POST['potpis_crtez'] ?? '');
                if (str_starts_with($png, 'data:image/png;base64,')) {
                    $tmp = tempnam(sys_get_temp_dir(), 'pot');
                    file_put_contents($tmp, (string) base64_decode(substr($png, 22), true));
                    $ime = spremi_sliku(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'potpis.png'], 'dezpotpis-', 900, true);
                    @unlink($tmp);
                } else {
                    $ime = spremi_sliku($_FILES['potpis_slika'] ?? [], 'dezpotpis-', 900, true);
                }
                obrisi_sliku(postavka('Dez.Potpis.' . $kl));
                spremi_postavku('Dez.Potpis.' . $kl, $ime);
                dnevnik('Dezinfekcija – spremljen potpis odgovorne osobe', null, null, naziv_sekcije($sid) . ': ' . dez_odgovorna($sid));
                poruka('Potpis je spremljen – od sada je na PDF-u evidencije ove sekcije.');
            } catch (Throwable $e) {
                poruka('Potpis nije spremljen: ' . e($e->getMessage()), 'danger');
            }
            break;
        case 'potpis-obrisi':
            $sid = $sekcijaIzPosta();
            obrisi_sliku(postavka('Dez.Potpis.' . dez_kljuc_sekcije($sid)));
            spremi_postavku('Dez.Potpis.' . dez_kljuc_sekcije($sid), null);
            dnevnik('Dezinfekcija – obrisan potpis odgovorne osobe', null, null, naziv_sekcije($sid));
            poruka('Potpis je obrisan.');
            break;
        case 'insp-novi-qr':
            dez_insp_token(true);
            dnevnik('Dezinfekcija – nova QR oznaka za inspekciju');
            poruka('Nova QR oznaka za inspekciju je napravljena. Stara više ne radi.', 'warning');
            break;
    }
    preusmjeri('dezinfekcija/postavke');
}
$razlozi = dez_razlozi(false);
$inspLoz = dez_insp_lozinka();
$pristupi = redovi("SELECT * FROM Dnevnik WHERE Radnja LIKE 'Inspekcija%' ORDER BY Id DESC LIMIT 8");
ob_start(); ?>
<h1 class="h3 mb-1">Dezinfekcija – stanice i QR</h1>
<p class="text-muted">Svaka sekcija ima svoju stanicu. Koordinate upišite ručno (npr. iz Google Maps: dugi dodir na kartu → kopirajte brojeve) ili stanite na stanicu i dodirnite „📍 Ovdje sam“.</p>
<form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="stanice">
<div class="row g-3">
<?php foreach ($stanice as $s): $i = (int) $s['Id']; ?>
    <div class="col-lg-6"><div class="card h-100 <?= $s['Aktivna'] ? '' : 'opacity-75' ?>">
        <div class="card-header d-flex align-items-center gap-2"><b><?= $s['Vrsta'] === 'M' ? 'Mobilna · ' : '' ?><?= e($s['SekcijaNaziv'] ?? 'Bez sekcije') ?></b>
            <?php if ($s['Vrsta'] === 'M'): ?><span class="badge bg-info text-dark">položaj pri aktivaciji</span>
            <?php else: ?><?= $s['Lat'] === null ? '<span class="badge bg-warning text-dark">nema koordinata</span>' : '<span class="badge bg-success">koordinate ✓</span>' ?><?php endif; ?>
            <a class="ms-auto btn btn-sm btn-outline-secondary" href="<?= e(url('dezinfekcija/qr', ['id' => $i])) ?>" target="_blank">QR za ispis</a></div>
        <div class="card-body"><div class="row g-2">
            <div class="col-12"><label class="form-label small">Naziv</label><input name="st[<?= $i ?>][Naziv]" class="form-control form-control-sm" maxlength="80" value="<?= e($s['Naziv']) ?>"></div>
<?php if ($s['Vrsta'] !== 'M'): ?>
            <div class="col-6"><label class="form-label small">Geogr. širina (lat)</label><input name="st[<?= $i ?>][Lat]" id="lat<?= $i ?>" class="form-control form-control-sm" inputmode="decimal" placeholder="45.81234" value="<?= $s['Lat'] !== null ? e(rtrim(rtrim(sprintf('%.7F', $s['Lat']), '0'), '.')) : '' ?>"></div>
            <div class="col-6"><label class="form-label small">Geogr. dužina (lon)</label><input name="st[<?= $i ?>][Lon]" id="lon<?= $i ?>" class="form-control form-control-sm" inputmode="decimal" placeholder="17.45678" value="<?= $s['Lon'] !== null ? e(rtrim(rtrim(sprintf('%.7F', $s['Lon']), '0'), '.')) : '' ?>"></div>
            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary" data-ovdje="<?= $i ?>">📍 Ovdje sam – uzmi lokaciju</button>
                <?php if ($s['Lat'] !== null): ?><a class="btn btn-sm btn-link" target="_blank" rel="noopener" href="<?= e(karta_tocke_url((float) $s['Lat'], (float) $s['Lon'])) ?>">Prikaži na karti</a><?php endif; ?>
                <span class="small text-muted" id="info<?= $i ?>"></span></div>
<?php else: ?><div class="col-12 small text-muted">Položaj se zadaje pri aktivaciji (Dezinfekcija → Mobilna stanica). Preporučeni krug 150 m.</div><?php endif; ?>
            <div class="col-5"><label class="form-label small">Radijus (m)</label><input type="number" min="20" max="1000" step="10" name="st[<?= $i ?>][Radijus]" class="form-control form-control-sm" value="<?= (int) $s['Radijus'] ?>"></div>
            <div class="col-7"><label class="form-label small">Sredstvo za dezinfekciju</label><input name="st[<?= $i ?>][Sredstvo]" class="form-control form-control-sm" maxlength="120" placeholder="npr. Virkon S 1 %" value="<?= e($s['Sredstvo']) ?>"></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="st[<?= $i ?>][Aktivna]" id="akt<?= $i ?>" value="1"<?= chk($s['Aktivna']) ?>><label class="form-check-label small" for="akt<?= $i ?>">Aktivna (QR oznaka radi)</label></div></div>
        </div></div>
        <div class="card-footer small d-flex flex-wrap gap-2 align-items-center"><span class="text-muted text-truncate" style="max-width:70%"><?= e(dez_qr_url($s)) ?></span>
            <button class="btn btn-sm btn-link text-danger ms-auto p-0" form="noviqr<?= $i ?>" data-potvrda="Napraviti novu QR oznaku? Stara (isprintana) više neće raditi.">Nova QR oznaka</button></div>
    </div></div>
<?php endforeach; ?>
</div>
<button class="btn btn-primary mt-3">Spremi stanice</button>
<span class="small text-muted ms-2">Radijus 100 m je dovoljan i za starije mobitele. Nova sekcija → stanica se pojavi ovdje automatski.</span>
</form>
<?php foreach ($stanice as $s): ?><form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" id="noviqr<?= (int) $s['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="novi-qr"><input type="hidden" name="id" value="<?= (int) $s['Id'] ?>"></form><?php endforeach; ?>

<div class="row g-4 mt-2">
<div class="col-lg-6">
<h2 class="h5">Razlozi dolaska</h2>
<form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="razlozi">
<table class="table table-sm align-middle">
    <thead><tr><th style="width:5rem">Red.</th><th>Naziv</th><th class="text-center small">Aktivan</th><th class="text-center small" title="Član upisuje kratki opis">S opisom</th></tr></thead>
    <tbody>
    <?php foreach ($razlozi as $r): $i = (int) $r['Id']; ?>
        <tr><td><input type="number" name="r[<?= $i ?>][Redoslijed]" class="form-control form-control-sm" value="<?= (int) $r['Redoslijed'] ?>"></td>
            <td><input name="r[<?= $i ?>][Naziv]" class="form-control form-control-sm" maxlength="60" value="<?= e($r['Naziv']) ?>"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="r[<?= $i ?>][Aktivan]" value="1"<?= chk($r['Aktivan']) ?>></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="r[<?= $i ?>][Slobodno]" value="1"<?= chk($r['Slobodno']) ?>></td></tr>
    <?php endforeach; ?>
    <tr><td></td><td colspan="3"><input name="novi" class="form-control form-control-sm" maxlength="60" placeholder="+ novi razlog"></td></tr>
    </tbody>
</table>
<button class="btn btn-outline-primary btn-sm">Spremi razloge</button>
</form>
</div>
<div class="col-lg-6">
<h2 class="h5">Pristup za inspekciju</h2>
<form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" class="card card-body" autocomplete="off"><?= csrf() ?><input type="hidden" name="radnja" value="inspekcija">
    <label class="form-label">Lozinka (6–8 znakova)</label>
    <input name="lozinka" class="form-control font-monospace" minlength="6" maxlength="8" autocomplete="off" value="<?= e($inspLoz ?? '') ?>" placeholder="npr. Fazan24">
    <div class="form-text mb-2"><?= $inspLoz ? 'Lovočuvar je daje inspektoru na licu mjesta. Promjena odmah vrijedi – stara lozinka više ne radi.' : '<b>Pristup je isključen</b> dok ne upišete lozinku.' ?></div>
    <label class="form-label">Lovište (za zaglavlje PDF-a)</label>
    <input name="loviste" class="form-control mb-3" maxlength="120" value="<?= e(postavka('Dez.Loviste', '')) ?>" placeholder="npr. Zajedničko lovište br. X/Y „Naziv“">
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary">Spremi</button>
        <?php if ($inspLoz): ?><a class="btn btn-outline-secondary" target="_blank" href="<?= e(url('dezinfekcija/qr', ['inspekcija' => 1])) ?>">QR za inspekciju (ispis)</a><?php endif; ?>
        <button class="btn btn-link text-danger" form="inspnoviqr" data-potvrda="Napraviti novu QR oznaku za inspekciju? Stara više neće raditi.">Nova QR oznaka</button>
    </div>
</form>
<form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" id="inspnoviqr"><?= csrf() ?><input type="hidden" name="radnja" value="insp-novi-qr"></form>
<h2 class="h5 mt-4">Odgovorna osoba i potpis (po sekciji)</h2>
<p class="small text-muted">Svaka sekcija ima svoju odgovornu osobu. Potpiše se jednom – potpis je zatim na svakom PDF-u evidencije te sekcije (i onom koji preuzme inspekcija),
    uz napomenu „elektronički generirano iz evidencije“. Skenirani potpis nije kvalificirani elektronički potpis. Bez potpisa PDF ima crtu za potpis rukom.</p>
<?php foreach ($sekcijeOdg as $kl => [$sid, $sNaziv]): $odg = dez_odgovorna($sid); $potpis = dez_potpis_datauri($sid); ?>
<div class="card card-body mb-3">
    <div class="fw-semibold mb-2">Sekcija <?= e($sNaziv) ?></div>
    <form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" class="d-flex gap-2 mb-2"><?= csrf() ?><input type="hidden" name="radnja" value="odgovorna"><input type="hidden" name="sekcija" value="<?= e($kl) ?>">
        <input name="odgovorna" class="form-control" maxlength="120" value="<?= e($odg) ?>" placeholder="odgovorna osoba – ime i prezime">
        <button class="btn btn-outline-primary">Spremi</button></form>
    <?php if ($odg): ?>
        <?php if ($potpis): ?>
            <div class="d-flex align-items-center gap-3 mb-2"><img src="<?= e($potpis) ?>" alt="potpis" class="dez-potpis-slika">
                <form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="potpis-obrisi"><input type="hidden" name="sekcija" value="<?= e($kl) ?>">
                    <button class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati potpis?">Obriši potpis</button></form></div>
        <?php endif; ?>
        <details<?= $potpis ? '' : ' open' ?>><summary class="small"><?= $potpis ? 'Novi potpis' : 'Potpis – ' . e($odg) ?></summary>
        <form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" enctype="multipart/form-data" class="potpis-obrazac mt-2"><?= csrf() ?>
            <input type="hidden" name="radnja" value="potpis"><input type="hidden" name="sekcija" value="<?= e($kl) ?>"><input type="hidden" name="potpis_crtez">
            <canvas class="dez-potpis-platno" width="600" height="200"></canvas>
            <div class="d-flex flex-wrap gap-2 mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-ocisti>Očisti</button>
                <button class="btn btn-sm btn-primary">Spremi potpis</button></div>
            <div class="small text-muted mt-2">ili fotografija potpisa (na bijelom papiru): <input type="file" name="potpis_slika" accept="image/*" class="form-control form-control-sm mt-1" onchange="if(this.files.length)this.form.submit()"></div>
        </form></details>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ($pristupi): ?>
    <h3 class="h6 mt-3">Zadnji pristupi inspekcije</h3>
    <ul class="small list-unstyled text-muted"><?php foreach ($pristupi as $p): ?><li><?= e(datum_vrijeme($p['Vrijeme'])) ?> – <?= e($p['Radnja']) ?><?= $p['Detalji'] ? ' · ' . e($p['Detalji']) : '' ?></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
</div>
<script>
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
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-ovdje]'); if (!b) return;
    var i = b.getAttribute('data-ovdje'), info = document.getElementById('info' + i);
    if (!navigator.geolocation || !window.isSecureContext) { info.textContent = 'Lokacija radi samo preko https:// adrese.'; return; }
    info.textContent = 'Tražim lokaciju… (pričekajte do 30 s za točniji položaj)';
    var najb = null, kraj = Date.now() + 30000;
    var w = navigator.geolocation.watchPosition(function (p) {
        if (!najb || p.coords.accuracy < najb.accuracy) {
            najb = p.coords;
            document.getElementById('lat' + i).value = najb.latitude.toFixed(6);
            document.getElementById('lon' + i).value = najb.longitude.toFixed(6);
            info.textContent = 'Točnost ±' + Math.round(najb.accuracy) + ' m' + (najb.accuracy > 25 ? ' – pričekajte još malo…' : ' ✓') + ' Ne zaboravite „Spremi stanice“.';
        }
        if (najb.accuracy <= 10 || Date.now() > kraj) navigator.geolocation.clearWatch(w);
    }, function (er) { info.textContent = er.code === 1 ? 'Lokacija nije dopuštena u pregledniku.' : 'Lokacija nije pronađena.'; }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 });
    setTimeout(function () { navigator.geolocation.clearWatch(w); }, 31000);
});
</script>
<?php stranica('Dezinfekcija – stanice', ob_get_clean());
