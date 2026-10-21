<?php
/** Član: vlastiti profil – kontakt podaci i fotografija. */
$k = korisnik();
if (!$k['ClanId']) {
    preusmjeri();
}
$c = clan($k['ClanId']);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'foto') {
        try {
            $ime = spremi_sliku($_FILES['foto'] ?? [], 'clan-' . $c['Id'] . '-', 800);
            obrisi_sliku($c['FotoDatoteka']);
            azuriraj('Clanovi', (int) $c['Id'], ['FotoDatoteka' => $ime, 'Azurirano' => sada()]);
            dnevnik('Član promijenio fotografiju', 'Clan', (int) $c['Id'], puno_ime($c));
            poruka('Slika je spremljena.');
        } catch (Throwable $e) {
            poruka(e($e->getMessage()), 'danger');
        }
    } elseif ($radnja === 'dijeljenje') {
        spremi_vidljivost((int) $c['Id'], array_map(fn($f) => isset($_POST['v'][$f]), array_combine(array_keys(POLJA_VIDLJIVOSTI), array_keys(POLJA_VIDLJIVOSTI))));
        dnevnik('Član promijenio dijeljenje kontakata', 'Clan', (int) $c['Id'], puno_ime($c));
        poruka('Spremljeno – imenik prikazuje samo ono što ste označili.');
    } elseif ($radnja === 'vozilo-dodaj') {
        $oz = normaliziraj_oznaku(ul_str('oznaka'));
        if ($oz !== '') {
            dodaj_vozilo((int) $c['Id'], $oz, isset($_POST['zadano']));
            poruka('Vozilo je spremljeno.');
        }
    } elseif ($radnja === 'vozilo-obrisi') {
        q('DELETE FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) ($_POST['id'] ?? 0), $c['Id']]);
        if (!vrijednost('SELECT 1 FROM ClanVozila WHERE ClanId=? AND Zadano=1', [$c['Id']])) {
            q('UPDATE ClanVozila SET Zadano=1 WHERE Id=(SELECT MIN(Id) FROM ClanVozila WHERE ClanId=?)', [$c['Id']]);
        }
    } elseif ($radnja === 'vozilo-zadano') {
        if (vrijednost('SELECT 1 FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) ($_POST['id'] ?? 0), $c['Id']])) {
            q('UPDATE ClanVozila SET Zadano=(Id=?) WHERE ClanId=?', [(int) $_POST['id'], $c['Id']]);
        }
    } elseif (str_starts_with($radnja, 'pas-')) {
        psi_obradi((int) $c['Id'], $radnja, puno_ime($c));
        preusmjeri('moj-profil', ['psi' => 1]);
    } elseif ($radnja === 'potpis' && ($k['Prava'] || potpis_korisnika((int) $k['Id']))) {
        try {
            potpis_spremi((int) $k['Id'], (string) ($_POST['potpis_crtez'] ?? ''), $_FILES['potpis_slika'] ?? []);
            dnevnik('Spremljen osobni potpis', 'Korisnik', (int) $k['Id'], $k['Naziv']);
            poruka('Potpis je spremljen – od sada je automatski na PDF-ovima gdje ste potpisnik.');
        } catch (Throwable $e) {
            poruka('Potpis nije spremljen: ' . e($e->getMessage()), 'danger');
        }
        preusmjeri('moj-profil', ['potpis' => 1]);
    } elseif ($radnja === 'potpis-obrisi') {
        potpis_obrisi((int) $k['Id']);
        dnevnik('Obrisan osobni potpis', 'Korisnik', (int) $k['Id'], $k['Naziv']);
        poruka('Potpis je obrisan.');
        preusmjeri('moj-profil', ['potpis' => 1]);
    } elseif ($radnja === 'obrisi-foto') {
        obrisi_sliku($c['FotoDatoteka']);
        azuriraj('Clanovi', (int) $c['Id'], ['FotoDatoteka' => null, 'Azurirano' => sada()]);
    } else {
        $p = [];
        foreach (['Ulica', 'KucniBroj', 'PostanskiBroj', 'Mjesto', 'MobilniTelefon', 'FiksniTelefon', 'Email'] as $f) {
            $p[$f] = ul_null($f);
        }
        if ($p['Email'] !== null && !filter_var($p['Email'], FILTER_VALIDATE_EMAIL)) {
            poruka('E-mail adresa nije ispravna.', 'danger');
            preusmjeri('moj-profil');
        }
        $p['Azurirano'] = sada();
        azuriraj('Clanovi', (int) $c['Id'], $p);
        $promjene = array_filter(array_keys($p), fn($f) => $f !== 'Azurirano' && (string) $p[$f] !== (string) $c[$f]);
        dnevnik('Član ažurirao kontakt podatke', 'Clan', (int) $c['Id'], puno_ime($c) . ($promjene ? ': ' . implode(', ', $promjene) : ''));
        poruka('Promjene su spremljene.');
    }
    preusmjeri('moj-profil');
}
$fun = array_column(redovi('SELECT f.Naziv FROM ClanFunkcije cf JOIN Funkcije f ON f.Id=cf.FunkcijaId WHERE cf.ClanId=? AND cf.Do IS NULL ORDER BY f.Redoslijed', [$c['Id']]), 'Naziv');
$lg = pocetak_lovne_godine();
$bodovi = (float) vrijednost('SELECT COALESCE(SUM(CAST(Bodovi AS REAL)),0) FROM RadneAkcije WHERE ClanId=? AND Status=1 AND substr(Datum,1,10) BETWEEN ? AND ?', [$c['Id'], $lg->format('Y-m-d'), $lg->modify('+1 year -1 day')->format('Y-m-d')]);
$ceka = (int) vrijednost('SELECT COUNT(*) FROM RadneAkcije WHERE ClanId=? AND Status=0', [$c['Id']]);
$pv = fn($f) => e($c[$f] ?? '');
$vid = vidljivost_clana((int) $c['Id']);
$vozila = vozila_clana((int) $c['Id']);
ob_start(); ?>
<div class="d-flex align-items-center gap-3 mb-3">
    <div class="text-center">
        <?= avatar($c, true) ?>
        <div class="mt-1">
            <form method="post" action="<?= e(url('moj-profil')) ?>" enctype="multipart/form-data" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="foto">
                <label class="btn btn-sm btn-outline-primary mb-0"><?= $c['FotoDatoteka'] ? 'Promijeni sliku' : 'Dodaj sliku' ?>
                    <input type="file" name="foto" accept="image/*" data-smanji="1200" class="d-none" onchange="this.form.submit()"></label></form>
            <?php if ($c['FotoDatoteka']): ?>
                <form method="post" action="<?= e(url('moj-profil')) ?>" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-foto">
                    <button class="btn btn-sm btn-link text-danger" data-potvrda="Ukloniti sliku?">Ukloni</button></form>
            <?php endif; ?>
        </div>
    </div>
    <div>
        <h1 class="h4 mb-0"><?= e(puno_ime($c)) ?></h1>
        <div class="text-muted small"><?= e($c['SekcijaNaziv'] ?? 'Sekcija nije dodijeljena') ?><?php if ($fun): ?> · <?= e(implode(', ', $fun)) ?><?php endif; ?></div>
    </div>
</div>
<div class="row g-4">
    <div class="col-12 instal-gumb" hidden><div class="card border-success"><div class="card-body d-flex align-items-center gap-3">
        <div class="flex-grow-1"><b>📲 Aplikacija na mobitelu</b><div class="small text-muted">Dodajte ikonu na početni zaslon – otvara se bez preglednika, kao prava aplikacija.</div></div>
        <button type="button" class="btn btn-success" data-instaliraj>Dodaj na početni zaslon</button>
    </div></div></div>
    <div class="col-12"><div class="card"><div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div><div class="kartica-broj"><?= e(broj($bodovi, 2)) ?></div>
            <div class="text-muted small">bodova u tekućoj lovnoj godini · <?= $ceka ?> na čekanju</div></div>
        <a href="<?= e(url('moje-akcije')) ?>" class="btn btn-primary ms-auto">Moje radne akcije</a>
    </div><div class="card-footer bg-white"><?= predlozak('moja-clanarina', ['clanId' => (int) $c['Id']]) ?></div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Moji kontakt podaci</div><div class="card-body">
        <form method="post" action="<?= e(url('moj-profil')) ?>"><?= csrf() ?>
            <div class="row g-2">
                <div class="col-8"><label class="form-label small">Ulica</label><input name="Ulica" class="form-control" value="<?= $pv('Ulica') ?>"></div>
                <div class="col-4"><label class="form-label small">Kućni broj</label><input name="KucniBroj" class="form-control" value="<?= $pv('KucniBroj') ?>"></div>
                <div class="col-4"><label class="form-label small">Poštanski broj</label><input name="PostanskiBroj" class="form-control" inputmode="numeric" value="<?= $pv('PostanskiBroj') ?>"></div>
                <div class="col-8"><label class="form-label small">Mjesto</label><input name="Mjesto" class="form-control" value="<?= $pv('Mjesto') ?>"></div>
                <div class="col-6"><label class="form-label small">Mobitel</label><input name="MobilniTelefon" type="tel" class="form-control" value="<?= $pv('MobilniTelefon') ?>"></div>
                <div class="col-6"><label class="form-label small">Fiksni telefon</label><input name="FiksniTelefon" type="tel" class="form-control" value="<?= $pv('FiksniTelefon') ?>"></div>
                <div class="col-12"><label class="form-label small">E-mail</label><input name="Email" type="email" class="form-control" value="<?= $pv('Email') ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3 w-100">Spremi promjene</button>
        </form>
    </div></div></div>
    <div class="col-lg-6" id="dijeljenje"><div class="card"><div class="card-header">Što vide ostali članovi (imenik)</div><div class="card-body">
        <form method="post" action="<?= e(url('moj-profil')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="dijeljenje">
            <p class="small text-muted">Ime, nadimak, slika i sekcija su vidljivi svima. Ostalo odlučujete vi – neoznačeno piše „nije podijelio“.
                Poruka unutar aplikacije uvijek radi. Uprava udruge (tajnik, blagajnik, lovnik…) vidi vaše podatke zbog vođenja evidencije.</p>
            <?php foreach (POLJA_VIDLJIVOSTI as $f => $n): ?>
                <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="v[<?= $f ?>]" id="v<?= $f ?>" value="1"<?= chk($vid[$f]) ?>>
                    <label class="form-check-label" for="v<?= $f ?>"><?= e($n) ?></label></div>
            <?php endforeach; ?>
            <button class="btn btn-outline-primary btn-sm mt-2">Spremi</button>
            <a class="btn btn-link btn-sm mt-2" href="<?= e(url('imenik')) ?>">Otvori imenik</a>
        </form>
    </div></div></div>
    <div class="col-lg-6" id="vozila"><div class="card"><div class="card-header">Moja vozila (reg. oznake)</div><div class="card-body">
        <p class="small text-muted">Za knjigu dezinfekcije – pri upisu na stanici oznaka je već odabrana. Prvo vozilo je zadano.</p>
        <?php if ($vozila): ?><ul class="list-group mb-3">
            <?php foreach ($vozila as $vz): ?><li class="list-group-item d-flex align-items-center gap-2">
                <b class="me-auto"><?= e($vz['Oznaka']) ?></b>
                <?php if ($vz['Zadano']): ?><span class="badge bg-success">zadano</span><?php else: ?>
                    <form method="post" action="<?= e(url('moj-profil')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-zadano"><input type="hidden" name="id" value="<?= (int) $vz['Id'] ?>"><button class="btn btn-sm btn-link p-0">zadano</button></form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('moj-profil')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-obrisi"><input type="hidden" name="id" value="<?= (int) $vz['Id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" data-potvrda="Ukloniti vozilo?">✕</button></form>
            </li><?php endforeach; ?></ul><?php endif; ?>
        <?php if (count($vozila) < 5): ?>
        <form method="post" action="<?= e(url('moj-profil')) ?>" class="d-flex gap-2"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-dodaj">
            <input name="oznaka" class="form-control text-uppercase" maxlength="20" placeholder="npr. VT 123-AB" required>
            <button class="btn btn-outline-primary text-nowrap">Dodaj</button></form>
        <?php endif; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">Moji podaci (mijenja ih udruga)</div>
        <ul class="list-group list-group-flush small">
            <li class="list-group-item d-flex"><span class="text-muted">Datum rođenja</span><span class="ms-auto"><?= e(datum($c['DatumRodjenja'])) ?></span></li>
            <li class="list-group-item d-flex"><span class="text-muted">OIB</span><span class="ms-auto"><?= e($c['Oib']) ?></span></li>
            <li class="list-group-item d-flex"><span class="text-muted">Lovačka iskaznica</span><span class="ms-auto"><?= e($c['BrojIskaznice']) ?></span></li>
            <li class="list-group-item d-flex"><span class="text-muted">Iskaznica vrijedi do</span>
                <span class="ms-auto <?= $c['IskaznicaVrijediDo'] && substr($c['IskaznicaVrijediDo'], 0, 10) < danas() ? 'text-danger' : '' ?>"><?= e(datum($c['IskaznicaVrijediDo'])) ?></span></li>
            <li class="list-group-item d-flex"><span class="text-muted">Član udruge od</span><span class="ms-auto"><?= e(datum($c['ClanOd'])) ?></span></li>
            <li class="list-group-item d-flex"><span class="text-muted">Sekcija / lovna jedinica</span><span class="ms-auto"><?= e($c['SekcijaNaziv'] ?? '—') ?></span></li>
        </ul>
        <div class="card-footer small text-muted">Ako nešto nije točno, javite se tajniku ili administratoru.</div>
    </div></div>
</div>
<?php if ($oruzje = oruzni_listovi((int) $c['Id'])): ?>
<div class="card mt-3" style="max-width:640px"><div class="card-header">Moji oružni listovi <span class="small text-muted">(upisuje udruga)</span></div>
    <ul class="list-group list-group-flush small"><?php foreach ($oruzje as $o): ?><li class="list-group-item d-flex gap-2"><b><?= e($o['Broj']) ?></b><span class="text-muted"><?= e((string) $o['Oruzje']) ?></span></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (modul('dezinfekcija')) dez_prenesi_stare_potpise(); if ($k['Prava'] || potpis_korisnika((int) $k['Id'])): $mojPotpis = potpis_datauri((int) $k['Id']); ?>
<h2 class="h5 mt-4" id="potpis">✍ Moj potpis</h2>
<div class="card card-body" style="max-width:640px">
    <p class="small text-muted mb-2">Potpis se automatski stavlja na PDF-ove gdje ste potpisnik: popis sudionika skupnog lova (voditelj lova), knjiga dezinfekcije (odgovorna osoba sekcije / predsjednik).
        Samo vi ga možete spremiti ili obrisati. Skenirani potpis nije kvalificirani elektronički potpis – PDF nosi napomenu da je elektronički generiran iz evidencije.</p>
    <?php if ($mojPotpis): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2"><img src="<?= e($mojPotpis) ?>" alt="potpis" class="dez-potpis-slika">
            <span class="small text-muted">spremljen <?= e(datum_vrijeme((string) postavka('Potpis.Datum.K' . $k['Id']))) ?></span>
            <form method="post" action="<?= e(url('moj-profil')) ?>" class="ms-auto"><?= csrf() ?><input type="hidden" name="radnja" value="potpis-obrisi">
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati vaš potpis?">Obriši</button></form></div>
    <?php endif; ?>
    <details<?= $mojPotpis ? '' : ' open' ?>><summary class="small"><?= $mojPotpis ? 'Novi potpis' : 'Potpišite se prstom ili mišem' ?></summary>
    <form method="post" action="<?= e(url('moj-profil')) ?>" enctype="multipart/form-data" class="potpis-obrazac mt-2"><?= csrf() ?>
        <input type="hidden" name="radnja" value="potpis"><input type="hidden" name="potpis_crtez">
        <canvas class="dez-potpis-platno" width="600" height="200"></canvas>
        <div class="d-flex flex-wrap gap-2 mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-ocisti>Očisti</button>
            <button class="btn btn-sm btn-primary">Spremi potpis</button></div>
        <div class="small text-muted mt-2">ili fotografija potpisa (na bijelom papiru): <input type="file" name="potpis_slika" accept="image/*" class="form-control form-control-sm mt-1" onchange="if(this.files.length)this.form.submit()"></div>
    </form></details>
</div>
<script src="<?= e(asset('assets/potpis.js')) ?>"></script>
<?php if (!empty($_GET['potpis'])): ?><script>document.getElementById('potpis').scrollIntoView();</script><?php endif; ?>
<?php endif; ?>
<?php if (modul('psi')): ?>
<h2 class="h5 mt-4" id="psi">🐕 Moji lovački psi</h2>
<?= predlozak('clan-psi', ['id' => (int) $c['Id'], 'psiProfil' => true]) ?>
<?php endif; ?>
<?php if (!empty($_GET['psi'])): ?><script>document.getElementById('psi').scrollIntoView();</script><?php endif; ?>
<?php stranica('Moj profil', ob_get_clean());
