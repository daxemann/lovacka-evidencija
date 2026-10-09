<?php
/**
 * Upis na dezinfekcijskoj stanici – otvara se skeniranjem QR oznake (index.php?p=dez&s=<oznaka stanice>).
 * Član mora biti prijavljen (prijava se pamti 30 dana). Vrijeme je vrijeme poslužitelja;
 * bez signala se upis sprema na mobitelu (vrijeme mobitela) i šalje čim ima interneta (assets/dez.js, api/dez-sync).
 * Stalna stanica (F) ima fiksne koordinate, mobilna (M) koordinate trenutne aktivacije.
 */
$k = korisnik();
$st = dez_stanica_po_tokenu((string) ($_GET['s'] ?? $_POST['s'] ?? ''));
if (!$st || !$st['Aktivna']) {
    stranica('Dezinfekcija', '<h1 class="h5">Dezinfekcijska stanica</h1><div class="alert alert-warning mb-0">Ova QR oznaka nije (više) važeća. '
        . 'Javite se lovočuvaru ili administratoru.</div>', 'prazno');
}
$mob = $st['Vrsta'] === 'M';
$stP = dez_stanica_s_polozajem($st);
$clan = $k['ClanId'] ? clan($k['ClanId']) : null;
$ja = $clan ? puno_ime($clan) : $k['Naziv'];
$vozila = $clan ? vozila_clana((int) $clan['Id']) : [];
$razlozi = dez_razlozi();

// obrazac bez JavaScripta (rezerva) – inače šalje dez.js preko api/dez-sync
if (je_post()) {
    $r = dez_spremi_upis($st, $k, $_POST);
    if (!$r['ok']) {
        poruka(e($r['poruka']), 'danger');
        preusmjeri('dez', ['s' => $st['Token']]);
    }
    preusmjeri('dez/potvrda', ['g' => $r['grupa']]);
}

// prijedlog smjera: ako je zadnji upis (24 h) dolazak → sada odlazak
$zadnji = $clan ? dez_zadnji_upis_clana((int) $clan['Id'], (int) $st['Id']) : null;
$predSmjer = $zadnji && $zadnji['Smjer'] === 'D' ? 'O' : 'D';
$predRazlog = null;
$izRazloga = $zadnji && $zadnji['Smjer'] === 'D' ? (string) $zadnji['Razlog'] : (string) ($stP['AktivacijaRazlog'] ?? '');
foreach ($razlozi as $r) {
    if ($izRazloga !== '' && ($izRazloga === $r['Naziv'] || str_starts_with($izRazloga, $r['Naziv'] . ':'))) {
        $predRazlog = (int) $r['Id'];
    }
}
$clanovi = redovi('SELECT Id, Ime, Prezime FROM Clanovi WHERE Status=0 AND Id<>? ORDER BY Prezime, Ime', [$clan['Id'] ?? 0]);
$imaKoord = $stP && $stP['Lat'] !== null && $stP['Lon'] !== null;
// upis za drugog lovca (lovočuvar i ovlašteni): zadano vozilo i zadnji smjer svakog člana na ovoj stanici
$zaDruge = ima(P_DEZ_ZA_DRUGE);
$drugi = [];
if ($zaDruge) {
    $smjerovi = [];
    foreach (redovi('SELECT ClanId, Smjer FROM DezUpisi WHERE StanicaId=? AND Ponisteno=0 AND ClanId IS NOT NULL AND Vrijeme>=? ORDER BY Vrijeme, Id',
        [$st['Id'], date('Y-m-d H:i:s', time() - 86400)]) as $r) {
        $smjerovi[(int) $r['ClanId']] = $r['Smjer'];
    }
    foreach (redovi('SELECT c.Id, c.Ime, c.Prezime, (SELECT Oznaka FROM ClanVozila v WHERE v.ClanId=c.Id ORDER BY Zadano DESC, Id LIMIT 1) AS Oznaka
        FROM Clanovi c WHERE c.Status=0 AND c.Id<>? ORDER BY c.Prezime, c.Ime', [$clan['Id'] ?? 0]) as $r) {
        $drugi[] = $r + ['Smjer' => $smjerovi[(int) $r['Id']] ?? null];
    }
}
$js = [
    'token' => $st['Token'], 'lat' => $imaKoord ? (float) $stP['Lat'] : null, 'lon' => $imaKoord ? (float) $stP['Lon'] : null,
    'r' => max(10, (int) $st['Radijus']), 'mob' => $mob, 'aktivna' => (bool) $stP, 'naziv' => $st['Naziv'],
    'api' => url('api/dez-sync'), 'potvrda' => url('dez/potvrda'), 'ja' => $ja, 'clanId' => $clan['Id'] ?? null,
    'spremljeno' => date('d.m.Y. H:i'),
];
ob_start(); ?>
<div class="dez-glava mb-3">
    <div class="small text-muted text-uppercase"><?= $mob ? 'Mobilna dezinfekcijska stanica' : 'Dezinfekcijska stanica' ?></div>
    <h1 class="h5 mb-0"><?= e($mob && $stP ? $stP['AktivacijaNaziv'] : $st['Naziv']) ?></h1>
    <div class="small text-muted"><?= $mob && $stP ? e($st['Naziv']) . ' · do ' . e(date('H:i', strtotime($stP['AktivnaDo']))) : ($st['SekcijaNaziv'] ? 'Sekcija ' . e($st['SekcijaNaziv']) : '') ?></div>
</div>
<div id="dez-offline-traka" class="alert alert-info small py-2" hidden>📴 Nema interneta – upis se sprema na mobitelu i šalje se automatski čim bude signala.</div>
<div id="dez-red-traka" class="alert alert-light border small py-2" hidden></div>
<?php if ($mob && !$stP): ?>
    <div class="alert alert-warning" id="dez-neaktivna"><b>Mobilna stanica trenutno nije aktivna.</b><br>Voditelj lova / lovočuvar je aktivira na mjestu dezinfekcije.
        Kad je aktivirana, osvježite stranicu.</div>
<?php endif; ?>
<div id="dez-greska" class="alert alert-danger" hidden></div>
<div id="gps" class="dez-gps dez-gps-ceka mb-3" role="status">
    <div class="fw-semibold" id="gps-naslov">📍 Tražim vašu lokaciju…</div>
    <div class="small" id="gps-tekst">Dopustite pristup lokaciji kad preglednik pita.</div>
</div>
<form method="post" action="<?= e(url('dez', ['s' => $st['Token']])) ?>" id="dez-obrazac" autocomplete="off"><?= csrf() ?>
    <input type="hidden" name="s" value="<?= e($st['Token']) ?>">
    <input type="hidden" name="lat" id="f-lat"><input type="hidden" name="lon" id="f-lon"><input type="hidden" name="acc" id="f-acc">
    <?php if ($zaDruge): ?>
    <div class="mb-3 dez-za-druge"><label class="form-label small mb-1" for="za-clana">Upisujem</label>
        <select name="za_clana" id="za-clana" class="form-select">
            <option value="">sebe – <?= e($ja) ?></option>
            <?php foreach ($drugi as $d): ?><option value="<?= (int) $d['Id'] ?>" data-oznaka="<?= e($d['Oznaka']) ?>" data-smjer="<?= e($d['Smjer']) ?>"><?= e(prezime_ime($d)) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text" id="za-druge-info" hidden>Lovac je ovdje s vama na stanici. Upis vrijedi s vašom lokacijom i trenutnim vremenom, u napomeni piše da ste ga upisali vi.</div>
    </div>
    <?php else: ?>
    <div class="mb-1 small text-muted"><?= e($ja) ?></div>
    <?php endif; ?>
    <div class="dez-smjer mb-3">
        <input type="radio" class="btn-check" name="smjer" id="sm-d" value="D"<?= chk($predSmjer === 'D') ?>>
        <label class="btn btn-outline-success" for="sm-d">⬇ DOLAZAK</label>
        <input type="radio" class="btn-check" name="smjer" id="sm-o" value="O"<?= chk($predSmjer === 'O') ?>>
        <label class="btn btn-outline-warning" for="sm-o">⬆ ODLAZAK</label>
    </div>
    <?php if ($zadnji && $zadnji['Smjer'] === 'D'): ?>
        <div class="small text-muted mb-2" id="zadnji-dolazak">Dolazak upisan u <?= e(date('H:i', strtotime($zadnji['Vrijeme']))) ?> (<?= e(datum($zadnji['Vrijeme'])) ?>).</div>
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
            <div class="form-check"><input class="form-check-input" type="radio" name="vozilo" id="vz<?= (int) $vz['Id'] ?>" value="v<?= (int) $vz['Id'] ?>" data-oznaka="<?= e($vz['Oznaka']) ?>"<?= chk($i === 0) ?>>
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
<div id="dez-spremljeno" class="text-center" hidden>
    <div class="dez-kvacica dez-offline">✓</div>
    <h2 class="h4 mt-2 mb-0" id="sp-naslov">Spremljeno na mobitelu</h2>
    <div class="display-6 fw-bold my-1" id="sp-vrijeme"></div>
    <div class="text-muted" id="sp-opis"></div>
    <ul class="list-group mt-3 text-start" id="sp-imena"></ul>
    <div class="alert alert-info small mt-3 text-start mb-0">📴 Nema interneta. Upis je spremljen na ovom mobitelu s ovim vremenom i <b>poslat će se automatski</b>
        čim bude signala (otvorite aplikaciju kad ste opet u dometu). U knjizi će biti označen „offline“.</div>
    <button type="button" class="btn btn-outline-secondary w-100 mt-3" onclick="location.reload()">Novi upis</button>
</div>
<template id="tpl-suputnik"><div class="input-group mb-2"><select name="suputnik[]" class="form-select"><option value="">— lovac —</option>
    <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
    </select><button type="button" class="btn btn-outline-danger" data-ukloni>✕</button></div></template>
<template id="tpl-gost"><div class="border rounded p-2 mb-2 bg-light"><div class="d-flex justify-content-between small fw-semibold mb-1">Gost<button type="button" class="btn-close" data-ukloni aria-label="Ukloni"></button></div>
    <div class="row g-1"><div class="col-6"><input name="gost_ime[]" class="form-control form-control-sm" placeholder="Ime" maxlength="60"></div>
    <div class="col-6"><input name="gost_prezime[]" class="form-control form-control-sm" placeholder="Prezime" maxlength="60"></div>
    <div class="col-12"><input name="gost_oznaka[]" class="form-control form-control-sm text-uppercase" placeholder="Reg. oznaka (prazno = moje vozilo)" maxlength="20"></div></div></div></template>
<div class="text-center mt-3"><a class="small" href="<?= e(url()) ?>">Početna</a></div>
<script>window.DEZ = <?= json_encode($js, JSON_UNESCAPED_UNICODE) ?>; window.DEZ_MAX = [<?= DEZ_MAX_SUPUTNIKA ?>, <?= DEZ_MAX_GOSTIJU ?>];</script>
<script src="<?= e(asset('assets/dez.js')) ?>" data-sw="<?= e(bazni_put() . 'sw.js') ?>"></script>
<?php stranica('Dezinfekcija', ob_get_clean(), 'prazno');
