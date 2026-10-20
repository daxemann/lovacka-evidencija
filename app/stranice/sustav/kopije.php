<?php
/** Sigurnosne kopije: ručna/dnevna kopija baze, kompletna kopija (ZIP) i vraćanje. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'kopija') {
        $ime = napravi_kopiju('rucno');
        dnevnik('Ručna sigurnosna kopija', null, null, $ime);
        poruka(e("Kopija napravljena: $ime"));
    } elseif ($radnja === 'lozinka') {
        $k = red('SELECT LozinkaHash FROM Korisnici WHERE Id=?', [(int) korisnik()['Id']]);
        $nova = (string) ($_POST['nova'] ?? '');
        $greska = kopija_lozinka_greska($nova, (string) ($_POST['nova2'] ?? ''));
        if (!$k || !provjeri_lozinku((string) $k['LozinkaHash'], (string) ($_POST['moja'] ?? ''))) {
            usleep(500000);
            poruka('Vaša lozinka za prijavu nije točna.', 'danger');
        } elseif ($greska !== '') {
            poruka($greska, 'warning');
        } else {
            $bila = kopija_lozinka_postavljena();
            kopija_postavi_lozinku($nova);
            dnevnik($bila ? 'Promijenjena lozinka kopije' : 'Postavljena lozinka kopije');
            poruka('Lozinka kopije je spremljena. Zapišite je i čuvajte izvan poslužitelja – bez nje se kopija ne može otvoriti.');
        }
    } elseif ($radnja === 'vrati') {
        $f = $_FILES['kopija'] ?? null;
        if (!isset($_POST['razumijem'])) {
            poruka('Potvrdite da razumijete da se trenutni podaci zamjenjuju.', 'warning');
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            poruka('Odaberite ZIP datoteku kompletne kopije.' . upload_greska($f), 'warning');
        } else {
            try {
                $tko = korisnik()['KorisnickoIme'];
                vrati_kompletnu_kopiju($f['tmp_name'], (string) ($_POST['lozinka_kopije'] ?? ''));
                dnevnik('Vraćena kompletna kopija', null, null, 'vratio: ' . $tko, ['Id' => null, 'KorisnickoIme' => $tko]);
                odjavi();
                session_start();
                preusmjeri('prijava', ['vraceno' => 1]);
            } catch (Throwable $e) {
                poruka('Vraćanje nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    } elseif ($radnja === 'obrisi') {
        $ime = basename((string) ($_POST['ime'] ?? ''));
        if (preg_match('/^evidencija-.*\.db$/', $ime)) {
            @unlink(podaci('kopije/' . $ime));
        }
    }
    preusmjeri('sustav/kopije');
}
$kopije = popis_kopija();
$velicina = fn(int $b) => $b > 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
$fotoVel = array_sum(array_map('filesize', glob(podaci('foto/*')) ?: []));
ob_start(); ?>
<h1 class="h3 mb-1">Sigurnosne kopije</h1>
<p class="text-muted">Baza: <code><?= e(podaci('evidencija.db')) ?></code> (<?= $velicina((int) @filesize(podaci('evidencija.db'))) ?>)<br>
    Automatska kopija baze radi se jednom dnevno (čuva se zadnjih <?= BROJ_DNEVNIH_KOPIJA ?>). Fotografije (<?= $velicina($fotoVel) ?>) su u mapi <code><?= e(podaci('foto')) ?></code>.</p>
<?php $imaLoz = kopija_lozinka_postavljena(); ?>
<div class="d-flex flex-wrap gap-2 mb-2">
    <form method="post" action="<?= e(url('sustav/kopije')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="kopija"><button class="btn btn-primary">Napravi kopiju sada</button></form>
</div>
<div class="card mb-3" style="max-width:700px">
    <div class="card-header">🔒 Lozinka kopije</div>
    <div class="card-body">
        <p class="small mb-2">Svaka kopija koja se preuzima s poslužitelja <b>šifrirana je ovom lozinkom (AES-256)</b>. Tko dođe do datoteke bez lozinke, ne može je otvoriti.
            Na poslužitelju se lozinka ne čuva – <b>zapišite je</b> (papir u sefu udruge ili upravitelj lozinki). <b>Zaboravljena lozinka = kopija se ne može otvoriti.</b>
            Nije ista kao lozinka za prijavu.</p>
        <?php if ($imaLoz): ?><div class="small text-success mb-2">✓ Lozinka kopije je postavljena.</div><?php else: ?><div class="alert alert-warning small py-2">Lozinka kopije još nije postavljena – dok je ne postavite, kopije se ne mogu preuzeti.</div><?php endif; ?>
        <details<?= $imaLoz ? '' : ' open' ?>><summary class="small mb-2"><?= $imaLoz ? 'Promijeni lozinku kopije' : 'Postavi lozinku kopije' ?></summary>
        <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="row g-2"><?= csrf() ?><input type="hidden" name="radnja" value="lozinka">
            <div class="col-12"><label class="form-label small mb-0">Vaša lozinka za prijavu (potvrda)</label><input name="moja" type="password" class="form-control" required autocomplete="current-password"></div>
            <div class="col-sm-6"><label class="form-label small mb-0">Nova lozinka kopije (min. <?= KOPIJA_LOZINKA_MIN ?> znakova)</label><input name="nova" type="password" class="form-control" required minlength="<?= KOPIJA_LOZINKA_MIN ?>" autocomplete="new-password"></div>
            <div class="col-sm-6"><label class="form-label small mb-0">Ponovi lozinku kopije</label><input name="nova2" type="password" class="form-control" required minlength="<?= KOPIJA_LOZINKA_MIN ?>" autocomplete="new-password"></div>
            <div class="col-12"><button class="btn btn-outline-primary btn-sm">Spremi lozinku kopije</button>
                <?php if ($imaLoz): ?><span class="small text-muted ms-2">Starije kopije i dalje se otvaraju starom lozinkom.</span><?php endif; ?></div>
        </form></details>
    </div>
</div>
<?php if ($imaLoz): ?>
<form method="post" action="<?= e(url('sustav/kompletna-kopija')) ?>" class="row g-2 align-items-end mb-2" style="max-width:700px"><?= csrf() ?>
    <div class="col-sm-7"><label class="form-label small mb-0">Lozinka kopije</label><input name="lozinka_kopije" type="password" class="form-control" required autocomplete="off"></div>
    <div class="col-sm-5"><button class="btn btn-outline-primary w-100">Preuzmi kompletnu kopiju 🔒</button></div>
    <div class="col-12 form-text">Sadrži i mapu <b>PDF-za-inspekciju</b>: knjiga dezinfekcije po mjesecima i izjava udruge kao gotovi PDF-ovi. Izrada može potrajati do minute.
        <?= kopija_aes_zip() ? 'Datoteka je ZIP šifriran AES-256 – bez programa se otvara 7-Zipom s istom lozinkom.' : 'Ovaj poslužitelj nema AES-ZIP, pa je kopija u formatu <b>.evk</b> – otvara se samo u evidenciji (vraćanje kopije).' ?></div>
</form>
<?php endif; ?>
<p class="small text-muted" style="max-width:700px">Kompletna kopija sadrži bazu, sve fotografije i ključeve (npr. šifriranu lozinku e-pošte). Služi za preseljenje na drugi poslužitelj: tamo se kod prvog pokretanja odabere „Vrati iz kompletne kopije“ i upiše lozinka kopije.</p>
<div class="card border-danger mb-4" style="max-width:700px">
    <div class="card-header text-danger">Vrati kompletnu kopiju (ZIP)</div>
    <div class="card-body">
        <p class="small mb-2"><b>Svi trenutni podaci ovdje se zamjenjuju podacima iz kopije</b> (članovi, korisnici, fotografije, postavke). Trenutna baza se prije toga sprema u sigurnosne kopije.</p>
        <form method="post" action="<?= e(url('sustav/kopije')) ?>" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="radnja" value="vrati">
            <input type="file" name="kopija" accept=".zip,.evk" class="form-control mb-2" required>
            <input type="password" name="lozinka_kopije" class="form-control mb-2" placeholder="Lozinka kopije (prazno samo za stare nešifrirane kopije)" autocomplete="off">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="razumijem" id="razumijem" value="1" required>
                <label class="form-check-label small" for="razumijem">Razumijem – zamijeni trenutne podatke</label></div>
            <button class="btn btn-outline-danger" data-potvrda="Zamijeniti sve trenutne podatke podacima iz kopije?">Vrati iz kompletne kopije</button>
        </form>
        <div class="small text-muted mt-2">Najveća datoteka koju poslužitelj prima: <?= e(ini_get('upload_max_filesize')) ?> (post_max_size <?= e(ini_get('post_max_size')) ?>).</div>
    </div>
</div>
<?php if ($kopije && $imaLoz): ?>
<form method="post" action="<?= e(url('sustav/kopija')) ?>" id="preuzmi-kopiju"><?= csrf() ?>
    <div class="row g-2 align-items-end mb-2" style="max-width:700px">
        <div class="col-sm-7"><label class="form-label small mb-0">Lozinka kopije (za preuzimanje kopije baze)</label><input name="lozinka_kopije" type="password" class="form-control form-control-sm" autocomplete="off"></div>
    </div>
</form>
<?php endif; ?>
<table class="table table-sm" style="max-width:700px">
    <thead><tr><th>Datoteka</th><th>Veličina</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($kopije as $kp): ?>
        <tr><td class="small"><?= e($kp['ime']) ?></td><td class="small"><?= $velicina($kp['velicina']) ?></td>
            <td class="text-end text-nowrap"><?php if ($imaLoz): ?><button class="btn btn-sm btn-link" form="preuzmi-kopiju" name="ime" value="<?= e($kp['ime']) ?>">Preuzmi 🔒</button><?php endif; ?>
                <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="ime" value="<?= e($kp['ime']) ?>">
                    <button class="btn btn-sm btn-link text-danger" data-potvrda="Obrisati kopiju?">Obriši</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$kopije): ?><tr><td colspan="3" class="text-muted">Još nema kopija.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php stranica('Sigurnosne kopije', ob_get_clean());
