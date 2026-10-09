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
            spremi_postavku('Dez.OdgovornaOsoba', mb_substr(ul_str('odgovorna'), 0, 120) ?: null);
            poruka('Spremljeno.');
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
    <input name="loviste" class="form-control mb-2" maxlength="120" value="<?= e(postavka('Dez.Loviste', '')) ?>" placeholder="npr. Zajedničko lovište br. X/Y „Naziv“">
    <label class="form-label">Odgovorna osoba</label>
    <input name="odgovorna" class="form-control mb-3" maxlength="120" value="<?= e(postavka('Dez.OdgovornaOsoba', '')) ?>" placeholder="ime i prezime">
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary">Spremi</button>
        <?php if ($inspLoz): ?><a class="btn btn-outline-secondary" target="_blank" href="<?= e(url('dezinfekcija/qr', ['inspekcija' => 1])) ?>">QR za inspekciju (ispis)</a><?php endif; ?>
        <button class="btn btn-link text-danger" form="inspnoviqr" data-potvrda="Napraviti novu QR oznaku za inspekciju? Stara više neće raditi.">Nova QR oznaka</button>
    </div>
</form>
<form method="post" action="<?= e(url('dezinfekcija/postavke')) ?>" id="inspnoviqr"><?= csrf() ?><input type="hidden" name="radnja" value="insp-novi-qr"></form>
<?php if ($pristupi): ?>
    <h3 class="h6 mt-3">Zadnji pristupi inspekcije</h3>
    <ul class="small list-unstyled text-muted"><?php foreach ($pristupi as $p): ?><li><?= e(datum_vrijeme($p['Vrijeme'])) ?> – <?= e($p['Radnja']) ?><?= $p['Detalji'] ? ' · ' . e($p['Detalji']) : '' ?></li><?php endforeach; ?></ul>
<?php endif; ?>
</div>
</div>
<script>
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
