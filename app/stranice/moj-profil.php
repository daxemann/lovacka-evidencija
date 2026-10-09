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
                    <input type="file" name="foto" accept="image/*" class="d-none" onchange="this.form.submit()"></label></form>
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
<?php stranica('Moj profil', ob_get_clean());
