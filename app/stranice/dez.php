<?php
/**
 * Upis na dezinfekcijskoj stanici – otvara se skeniranjem QR oznake (index.php?p=dez&s=<oznaka stanice>).
 * Član mora biti prijavljen (prijava se pamti 30 dana). Vrijeme je uvijek vrijeme poslužitelja.
 */
$k = korisnik();
$st = dez_stanica_po_tokenu((string) ($_GET['s'] ?? $_POST['s'] ?? ''));
if (!$st || !$st['Aktivna']) {
    stranica('Dezinfekcija', '<h1 class="h5">Dezinfekcijska stanica</h1><div class="alert alert-warning mb-0">Ova QR oznaka nije (više) važeća. '
        . 'Javite se lovočuvaru ili administratoru.</div>', 'prazno');
}
$clan = $k['ClanId'] ? clan($k['ClanId']) : null;
$ja = $clan ? puno_ime($clan) : $k['Naziv'];
$vozila = $clan ? vozila_clana((int) $clan['Id']) : [];
$razlozi = dez_razlozi();

if (je_post()) {
    $nazad = fn(string $p, string $v = 'danger') => [poruka($p, $v), preusmjeri('dez', ['s' => $st['Token']])];
    $smjer = (string) ($_POST['smjer'] ?? '');
    if (!isset(DEZ_SMJER[$smjer])) {
        $nazad('Odaberite DOLAZAK ili ODLAZAK.');
    }
    if (empty($_POST['potvrda'])) {
        $nazad('Potvrdite da je dezinfekcija provedena.');
    }
    $raz = red('SELECT * FROM DezRazlozi WHERE Id=? AND Aktivan=1', [(int) ($_POST['razlog'] ?? 0)]);
    if (!$raz) {
        $nazad('Odaberite razlog.');
    }
    $razlog = $raz['Naziv'];
    if ($raz['Slobodno'] && ul_str('razlog_opis') !== '') {
        $razlog .= ': ' . mb_substr(ul_str('razlog_opis'), 0, 80);
    }
    // vozilo
    $v = (string) ($_POST['vozilo'] ?? '');
    $saVozilom = true;
    $oznaka = null;
    if ($v === 'bez') {
        $saVozilom = false;
    } elseif (str_starts_with($v, 'v') && $clan) {
        $oznaka = (string) vrijednost('SELECT Oznaka FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) substr($v, 1), $clan['Id']]) ?: null;
    } else {
        $oznaka = normaliziraj_oznaku(ul_str('oznaka')) ?: null;
        if ($oznaka !== null && $clan && !empty($_POST['spremi_vozilo'])) {
            dodaj_vozilo((int) $clan['Id'], $oznaka);
        }
    }
    if ($saVozilom && $oznaka === null) {
        $nazad('Upišite registarsku oznaku vozila (ili odaberite „bez vozila“).');
    }
    // položaj
    $f = fn($n) => is_numeric($_POST[$n] ?? null) ? (float) $_POST[$n] : null;
    $lat = $f('lat');
    $lon = $f('lon');
    $toc = $f('acc');
    if ($lat !== null && (abs($lat) > 90 || abs((float) $lon) > 180)) {
        $lat = $lon = null;
    }
    [$lok, $udalj] = dez_procijeni_lokaciju($st, $lat, $lon, $toc);
    if ($lok === null) {
        dnevnik('Dezinfekcija – odbijen upis (predaleko)', 'DezStanica', (int) $st['Id'], $ja . ': ' . round((float) $udalj) . ' m, ±' . round((float) $toc) . ' m');
        $nazad('Predaleko ste od dezinfekcijske stanice (' . number_format((float) $udalj / 1000, 1, ',', '.') . ' km). Upis je moguć samo na samoj stanici.');
    }
    // dvostruki dodir: isti smjer u zadnje 2 minute
    if ($clan) {
        $isti = red('SELECT Grupa FROM DezUpisi WHERE ClanId=? AND StanicaId=? AND Smjer=? AND Ponisteno=0 AND Vrijeme>=? ORDER BY Id DESC LIMIT 1',
            [$clan['Id'], $st['Id'], $smjer, date('Y-m-d H:i:s', time() - 120)]);
        if ($isti) {
            preusmjeri('dez/potvrda', ['g' => $isti['Grupa']]);
        }
    }
    // suputnici (članovi) i gosti
    $suputnici = [];
    foreach (array_slice(array_unique(array_map('intval', (array) ($_POST['suputnik'] ?? []))), 0, DEZ_MAX_SUPUTNIKA) as $sid) {
        if ($sid && $sid !== ($clan['Id'] ?? 0) && ($c = red('SELECT * FROM Clanovi WHERE Id=? AND Status=0', [$sid]))) {
            $suputnici[] = $c;
        }
    }
    $gosti = [];
    $gi = (array) ($_POST['gost_ime'] ?? []);
    $gp = (array) ($_POST['gost_prezime'] ?? []);
    $go = (array) ($_POST['gost_oznaka'] ?? []);
    foreach ($gi as $i => $ime) {
        $ime = mb_substr(trim((string) $ime), 0, 60);
        $prez = mb_substr(trim((string) ($gp[$i] ?? '')), 0, 60);
        if ($ime === '' && $prez === '') {
            continue;
        }
        if ($ime === '' || $prez === '') {
            $nazad('Za gosta upišite ime i prezime.');
        }
        $gosti[] = [$ime, $prez, normaliziraj_oznaku((string) ($go[$i] ?? '')) ?: null];
        if (count($gosti) >= DEZ_MAX_GOSTIJU) {
            break;
        }
    }
    $grupa = bin2hex(random_bytes(8));
    $vrijeme = sada();
    $zajedno = [
        'StanicaId' => (int) $st['Id'], 'SekcijaId' => $st['SekcijaId'], 'Vrijeme' => $vrijeme, 'Smjer' => $smjer, 'Razlog' => $razlog,
        'Obuca' => 1, 'Oprema' => 1, 'UpisaoKorisnikId' => $k['Id'], 'UpisaoIme' => $ja, 'Lat' => $lat, 'Lon' => $lon, 'Tocnost' => $toc,
        'Udaljenost' => $udalj, 'Lokacija' => $lok, 'Naknadno' => 0, 'Grupa' => $grupa, 'Kreirano' => $vrijeme,
    ];
    transakcija(function () use ($zajedno, $clan, $k, $ja, $oznaka, $saVozilom, $suputnici, $gosti) {
        umetni('DezUpisi', $zajedno + ['ClanId' => $clan['Id'] ?? null, 'Ime' => $clan['Ime'] ?? $k['Naziv'], 'Prezime' => $clan['Prezime'] ?? '',
            'Gost' => 0, 'Oznaka' => $oznaka, 'Vozilo' => $saVozilom ? 1 : 0, 'PozvaoIme' => null]);
        foreach ($suputnici as $c) {
            umetni('DezUpisi', $zajedno + ['ClanId' => (int) $c['Id'], 'Ime' => $c['Ime'], 'Prezime' => $c['Prezime'], 'Gost' => 0,
                'Oznaka' => $oznaka, 'Vozilo' => $saVozilom ? 1 : 0, 'PozvaoIme' => $ja]);
        }
        foreach ($gosti as [$ime, $prez, $oz]) {
            umetni('DezUpisi', $zajedno + ['ClanId' => null, 'Ime' => $ime, 'Prezime' => $prez, 'Gost' => 1,
                'Oznaka' => $oz ?? $oznaka, 'Vozilo' => ($oz !== null || $saVozilom) ? 1 : 0, 'PozvaoIme' => $ja]);
        }
    });
    dnevnik('Dezinfekcija – ' . mb_strtolower(DEZ_SMJER[$smjer]), 'DezStanica', (int) $st['Id'], $ja . ' + ' . count($suputnici) . ' suputnika, ' . count($gosti) . ' gostiju · ' . DEZ_LOKACIJA[$lok]);
    preusmjeri('dez/potvrda', ['g' => $grupa]);
}

// prijedlog smjera: ako je zadnji upis (24 h) dolazak → sada odlazak
$zadnji = $clan ? dez_zadnji_upis_clana((int) $clan['Id'], (int) $st['Id']) : null;
$predSmjer = $zadnji && $zadnji['Smjer'] === 'D' ? 'O' : 'D';
$predRazlog = null;
if ($zadnji && $zadnji['Smjer'] === 'D') {
    foreach ($razlozi as $r) {
        if ($zadnji['Razlog'] === $r['Naziv'] || str_starts_with((string) $zadnji['Razlog'], $r['Naziv'] . ':')) {
            $predRazlog = (int) $r['Id'];
        }
    }
}
$clanovi = redovi('SELECT Id, Ime, Prezime FROM Clanovi WHERE Status=0 AND Id<>? ORDER BY Prezime, Ime', [$clan['Id'] ?? 0]);
$imaKoord = $st['Lat'] !== null && $st['Lon'] !== null;
ob_start(); ?>
<div class="dez-glava mb-3">
    <div class="small text-muted text-uppercase">Dezinfekcijska stanica</div>
    <h1 class="h5 mb-0"><?= e($st['Naziv']) ?></h1>
    <?php if ($st['SekcijaNaziv']): ?><div class="small text-muted">Sekcija <?= e($st['SekcijaNaziv']) ?></div><?php endif; ?>
</div>
<div id="gps" class="dez-gps dez-gps-ceka mb-3" role="status">
    <div class="fw-semibold" id="gps-naslov">📍 Tražim vašu lokaciju…</div>
    <div class="small" id="gps-tekst">Dopustite pristup lokaciji kad preglednik pita.</div>
</div>
<form method="post" action="<?= e(url('dez', ['s' => $st['Token']])) ?>" id="dez-obrazac" autocomplete="off"><?= csrf() ?>
    <input type="hidden" name="s" value="<?= e($st['Token']) ?>">
    <input type="hidden" name="lat" id="f-lat"><input type="hidden" name="lon" id="f-lon"><input type="hidden" name="acc" id="f-acc">
    <div class="mb-1 small text-muted"><?= e($ja) ?></div>
    <div class="dez-smjer mb-3">
        <input type="radio" class="btn-check" name="smjer" id="sm-d" value="D"<?= chk($predSmjer === 'D') ?>>
        <label class="btn btn-outline-success" for="sm-d">⬇ DOLAZAK</label>
        <input type="radio" class="btn-check" name="smjer" id="sm-o" value="O"<?= chk($predSmjer === 'O') ?>>
        <label class="btn btn-outline-warning" for="sm-o">⬆ ODLAZAK</label>
    </div>
    <?php if ($zadnji && $zadnji['Smjer'] === 'D'): ?>
        <div class="small text-muted mb-2">Dolazak upisan u <?= e(date('H:i', strtotime($zadnji['Vrijeme']))) ?> (<?= e(datum($zadnji['Vrijeme'])) ?>).</div>
    <?php endif; ?>
    <div class="mb-3"><label class="form-label">Razlog</label>
        <select name="razlog" id="razlog" class="form-select form-select-lg" required>
            <option value="">— odaberite —</option>
            <?php foreach ($razlozi as $r): ?><option value="<?= (int) $r['Id'] ?>" data-slobodno="<?= (int) $r['Slobodno'] ?>"<?= sel($r['Id'], $predRazlog) ?>><?= e($r['Naziv']) ?></option><?php endforeach; ?>
        </select>
        <input name="razlog_opis" id="razlog-opis" class="form-control mt-2" maxlength="80" placeholder="kratki opis" hidden>
    </div>
    <div class="mb-3"><label class="form-label">Vozilo (reg. oznaka)</label>
        <?php foreach ($vozila as $i => $vz): ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="vozilo" id="vz<?= (int) $vz['Id'] ?>" value="v<?= (int) $vz['Id'] ?>"<?= chk($i === 0) ?>>
                <label class="form-check-label fw-semibold" for="vz<?= (int) $vz['Id'] ?>"><?= e($vz['Oznaka']) ?></label></div>
        <?php endforeach; ?>
        <div class="form-check"><input class="form-check-input" type="radio" name="vozilo" id="vz-drugo" value="drugo"<?= chk(!$vozila) ?>>
            <label class="form-check-label" for="vz-drugo"><?= $vozila ? 'Drugo vozilo' : 'Upišite oznaku' ?></label></div>
        <div id="drugo-polje" class="ms-4 mt-1"<?= $vozila ? ' hidden' : '' ?>>
            <input name="oznaka" class="form-control text-uppercase" maxlength="20" placeholder="npr. VT 123-AB">
            <?php if ($clan): ?><div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="spremi_vozilo" id="spremi-vz" value="1"<?= chk(!$vozila) ?>>
                <label class="form-check-label" for="spremi-vz">spremi u moj profil (sljedeći put je već upisano)</label></div><?php endif; ?>
        </div>
        <div class="form-check"><input class="form-check-input" type="radio" name="vozilo" id="vz-bez" value="bez">
            <label class="form-check-label" for="vz-bez">Bez vozila / dolazim s drugim lovcem</label></div>
    </div>
    <div class="mb-3">
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dodaj-suputnika">+ Lovac iz udruge (do <?= DEZ_MAX_SUPUTNIKA ?>)</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dodaj-gosta">+ Gost</button>
        </div>
        <div id="suputnici" class="mt-2"></div>
        <div id="gosti" class="mt-2"></div>
        <div class="form-text">Suputnici i gosti dobivaju isti upis (vrijeme, smjer, razlog, vaše vozilo ako ne upišete drugo).</div>
    </div>
    <div class="form-check dez-potvrda mb-3">
        <input class="form-check-input" type="checkbox" name="potvrda" id="potvrda" value="1" required>
        <label class="form-check-label" for="potvrda">Potvrđujem dezinfekciju <b>vozila, obuće i opreme</b><?= $st['Sredstvo'] ? ' (' . e($st['Sredstvo']) . ')' : '' ?>.</label>
    </div>
    <button class="btn btn-primary btn-lg w-100" id="posalji" disabled>POTVRDI</button>
    <div class="small text-muted text-center mt-2" id="posalji-napomena">Čekam lokaciju…</div>
</form>
<template id="tpl-suputnik"><div class="input-group mb-2"><select name="suputnik[]" class="form-select"><option value="">— lovac —</option>
    <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
    </select><button type="button" class="btn btn-outline-danger" data-ukloni>✕</button></div></template>
<template id="tpl-gost"><div class="border rounded p-2 mb-2 bg-light"><div class="d-flex justify-content-between small fw-semibold mb-1">Gost<button type="button" class="btn-close" data-ukloni aria-label="Ukloni"></button></div>
    <div class="row g-1"><div class="col-6"><input name="gost_ime[]" class="form-control form-control-sm" placeholder="Ime" maxlength="60"></div>
    <div class="col-6"><input name="gost_prezime[]" class="form-control form-control-sm" placeholder="Prezime" maxlength="60"></div>
    <div class="col-12"><input name="gost_oznaka[]" class="form-control form-control-sm text-uppercase" placeholder="Reg. oznaka (prazno = moje vozilo)" maxlength="20"></div></div></div></template>
<div class="text-center mt-3"><a class="small" href="<?= e(url()) ?>">Početna</a></div>
<script>
(function () {
    var ST = <?= json_encode(['lat' => $imaKoord ? (float) $st['Lat'] : null, 'lon' => $imaKoord ? (float) $st['Lon'] : null, 'r' => max(10, (int) $st['Radijus'])]) ?>;
    var gps = document.getElementById('gps'), nas = document.getElementById('gps-naslov'), txt = document.getElementById('gps-tekst');
    var btn = document.getElementById('posalji'), nap = document.getElementById('posalji-napomena');
    var najbolja = null, gotovo = false;
    function stanje(klasa, n, t) { gps.className = 'dez-gps ' + klasa + ' mb-3'; nas.textContent = n; txt.textContent = t; }
    function omoguci(t) { btn.disabled = false; nap.textContent = t || ''; }
    function udalj(a, b, c, d) { var R = 6371000, r = Math.PI / 180, x = (c - a) * r, y = (d - b) * r;
        var h = Math.sin(x / 2) * Math.sin(x / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(y / 2) * Math.sin(y / 2); return 2 * R * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h)); }
    function m(x) { return x >= 1000 ? (x / 1000).toFixed(1).replace('.', ',') + ' km' : Math.round(x) + ' m'; }
    function polozaj(p) {
        var c = p.coords;
        if (najbolja && c.accuracy > najbolja.accuracy && Date.now() - najbolja.t < 15000) return;
        najbolja = { latitude: c.latitude, longitude: c.longitude, accuracy: c.accuracy, t: Date.now() };
        document.getElementById('f-lat').value = c.latitude; document.getElementById('f-lon').value = c.longitude; document.getElementById('f-acc').value = Math.round(c.accuracy);
        if (ST.lat === null) { stanje('dez-gps-ok', '📍 Lokacija pronađena', 'Stanica još nema upisane koordinate – upis se bilježi bez provjere.'); omoguci(); return; }
        var d = udalj(ST.lat, ST.lon, c.latitude, c.longitude), a = Math.round(c.accuracy);
        if (d <= ST.r) { stanje('dez-gps-ok', '🟢 Na stanici ste', m(d) + ' od stanice · točnost ±' + a + ' m'); omoguci(); }
        else if (d - a <= ST.r) { stanje('dez-gps-pola', '🟠 Lokacija nepouzdana', m(d) + ' od stanice, točnost ±' + a + ' m – upis je moguć, bit će označen. Na otvorenom je točnije.'); omoguci(); }
        else { stanje('dez-gps-ne', '🔴 Predaleko od stanice', m(d) + ' od stanice (±' + a + ' m). Upis je moguć samo na dezinfekcijskoj stanici.'); btn.disabled = true; nap.textContent = 'Priđite stanici – lokacija se osvježava sama.'; }
    }
    function greska(e) {
        if (e.code === 1) { gotovo = true; stanje('dez-gps-ne', '🔴 Lokacija nije dopuštena', 'Dopustite lokaciju za ovu stranicu (ikona lokota / ⓘ pored adrese → Lokacija → Dopusti) i osvježite stranicu.');
            btn.disabled = true; nap.textContent = 'Bez lokacije upis nije moguć.'; return; }
        if (!najbolja) { stanje('dez-gps-pola', '🟠 Lokacija još nije pronađena', 'Uključite lokaciju (GPS) na mobitelu. Tražim dalje…'); }
    }
    if (!window.isSecureContext || !navigator.geolocation) {
        stanje('dez-gps-ne', '🔴 Lokacija nije dostupna', 'Stranica mora biti otvorena preko https:// adrese (QR oznaka), a preglednik mora podržavati lokaciju.');
    } else {
        navigator.geolocation.watchPosition(polozaj, greska, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
        setTimeout(function () { if (!najbolja && !gotovo) { stanje('dez-gps-pola', '🟠 Lokacija nije pronađena', 'Upis je moguć, ali bit će označen „bez lokacije“.'); omoguci('Upis bez lokacije – bit će označen.'); } }, 30000);
    }
    // razlog "Ostalo" → opis
    var raz = document.getElementById('razlog'), opis = document.getElementById('razlog-opis');
    function razlogOpis() { var o = raz.options[raz.selectedIndex]; opis.hidden = !(o && o.getAttribute('data-slobodno') === '1'); }
    raz.addEventListener('change', razlogOpis); razlogOpis();
    // vozilo "drugo"
    document.querySelectorAll('input[name=vozilo]').forEach(function (r) { r.addEventListener('change', function () { document.getElementById('drugo-polje').hidden = document.getElementById('vz-drugo').checked === false; }); });
    // suputnici i gosti
    function dodaj(tpl, gdje, max) { var g = document.getElementById(gdje); if (g.children.length >= max) return;
        g.appendChild(document.getElementById(tpl).content.firstElementChild.cloneNode(true)); var p = g.lastElementChild.querySelector('select,input'); if (p) p.focus(); }
    document.getElementById('dodaj-suputnika').onclick = function () { dodaj('tpl-suputnik', 'suputnici', <?= DEZ_MAX_SUPUTNIKA ?>); };
    document.getElementById('dodaj-gosta').onclick = function () { dodaj('tpl-gost', 'gosti', <?= DEZ_MAX_GOSTIJU ?>); };
    document.addEventListener('click', function (e) { var u = e.target.closest('[data-ukloni]'); if (u) u.parentElement.closest('.input-group, .border').remove(); });
    document.getElementById('dez-obrazac').addEventListener('submit', function () { btn.disabled = true; btn.textContent = 'Spremam…'; });
})();
</script>
<?php stranica('Dezinfekcija', ob_get_clean(), 'prazno');
