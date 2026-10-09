<?php
/** Naknadni upis (zaboravljen sken, bez signala…) – uvijek s razlogom, u knjizi označen kao „naknadno“. */
trazi(P_DEZ_UREDI);
dez_osiguraj_stanice();
$k = korisnik();
$stanice = array_values(array_filter(dez_stanice(), fn($s) => $s['Aktivna']));
$razlozi = dez_razlozi();
if (je_post()) {
    $st = null;
    foreach ($stanice as $s) {
        if ((int) $s['Id'] === (int) ($_POST['stanica'] ?? 0)) {
            $st = $s;
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
        $zasto === '' => 'Upišite zašto se upisuje naknadno.',
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
        'StanicaId' => (int) $st['Id'], 'SekcijaId' => $st['SekcijaId'], 'Vrijeme' => date('Y-m-d H:i:s', $t), 'Smjer' => $smjer,
        'ClanId' => $c ? (int) $c['Id'] : null, 'Ime' => $ime, 'Prezime' => $prez, 'Gost' => $c ? 0 : 1, 'Oznaka' => $oznaka, 'Razlog' => $razlog,
        'Vozilo' => isset($_POST['d_vozilo']) ? 1 : 0, 'Obuca' => isset($_POST['d_obuca']) ? 1 : 0, 'Oprema' => isset($_POST['d_oprema']) ? 1 : 0,
        'UpisaoKorisnikId' => $k['Id'], 'UpisaoIme' => $k['Naziv'], 'PozvaoIme' => $c ? null : $k['Naziv'],
        'Lokacija' => DEZ_LOK_NAKNADNO, 'Naknadno' => 1, 'NaknadnoRazlog' => $zasto, 'Grupa' => bin2hex(random_bytes(8)), 'Kreirano' => sada(),
    ]);
    dnevnik('Dezinfekcija – naknadni upis', 'DezUpis', $id, "$ime $prez, " . date('d.m.Y. H:i', $t) . ' ' . DEZ_SMJER[$smjer] . ': ' . $zasto);
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
            <select name="stanica" class="form-select" required><?php foreach ($stanice as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $o['stanica'] ?? '') ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label">Datum</label><input type="date" name="datum" class="form-control" required max="<?= danas() ?>" value="<?= e($o['datum'] ?? danas()) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label">Vrijeme</label><input type="time" name="vrijeme" class="form-control" required value="<?= e($o['vrijeme'] ?? '') ?>"></div>
        <div class="col-12"><div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="smjer" id="n-d" value="D"<?= chk(($o['smjer'] ?? 'D') === 'D') ?>><label class="btn btn-outline-success" for="n-d">Dolazak</label>
            <input type="radio" class="btn-check" name="smjer" id="n-o" value="O"<?= chk(($o['smjer'] ?? '') === 'O') ?>><label class="btn btn-outline-warning" for="n-o">Odlazak</label></div></div>
        <div class="col-md-6"><label class="form-label">Član</label>
            <select name="clan" id="n-clan" class="form-select"><option value="">— gost (upiši ispod) —</option>
                <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>" data-oznaka="<?= e($c['Oznaka']) ?>"<?= sel($c['Id'], $o['clan'] ?? '') ?>><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-3 gost-polje"><label class="form-label">Ime gosta</label><input name="ime" class="form-control" maxlength="60" value="<?= e($o['ime'] ?? '') ?>"></div>
        <div class="col-6 col-md-3 gost-polje"><label class="form-label">Prezime gosta</label><input name="prezime" class="form-control" maxlength="60" value="<?= e($o['prezime'] ?? '') ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">Reg. oznaka</label><input name="oznaka" id="n-oznaka" class="form-control text-uppercase" maxlength="20" value="<?= e($o['oznaka'] ?? '') ?>"></div>
        <div class="col-6 col-md-4"><label class="form-label">Razlog dolaska</label>
            <select name="razlog" class="form-select" required><?php foreach ($razlozi as $r): ?><option value="<?= (int) $r['Id'] ?>"<?= sel($r['Id'], $o['razlog'] ?? '') ?>><?= e($r['Naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Opis (za „Ostalo“)</label><input name="razlog_opis" class="form-control" maxlength="80" value="<?= e($o['razlog_opis'] ?? '') ?>"></div>
        <div class="col-12"><span class="form-label d-block">Dezinficirano</span>
            <?php foreach (['d_vozilo' => 'vozilo', 'd_obuca' => 'obuća', 'd_oprema' => 'oprema'] as $n => $t): ?>
                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="<?= $n ?>" id="<?= $n ?>" value="1"<?= chk(!$o || isset($o[$n])) ?>><label class="form-check-label" for="<?= $n ?>"><?= $t ?></label></div>
            <?php endforeach; ?></div>
        <div class="col-12"><label class="form-label">Zašto naknadno?</label><input name="naknadno_razlog" class="form-control" required maxlength="200" placeholder="npr. nije bilo signala, zaboravio skenirati" value="<?= e($o['naknadno_razlog'] ?? '') ?>"></div>
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
    c.addEventListener('change', function () { oz.value = ''; pr(); }); pr();
})();
</script>
<?php stranica('Naknadni upis', ob_get_clean());
