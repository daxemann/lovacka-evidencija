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
        // Korak 1: lozinka za prijavu + 2FA + nova lozinka kopije
        unset($_SESSION['kopija_zahtjev']);
        $k = red('SELECT * FROM Korisnici WHERE Id=?', [(int) korisnik()['Id']]);
        $nova = (string) ($_POST['nova'] ?? '');
        $greska = kopija_lozinka_greska($nova, (string) ($_POST['nova2'] ?? ''));
        $email = $k ? kopija_email_korisnika((int) $k['Id']) : '';
        if (!$k || !provjeri_lozinku((string) $k['LozinkaHash'], (string) ($_POST['moja'] ?? ''))) {
            usleep(500000);
            dnevnik('Lozinka kopije: pogrešna lozinka za prijavu');
            poruka('Vaša lozinka za prijavu nije točna.', 'danger');
        } elseif (dvofaktor_ukljucen() && !ima_2fa($k)) {
            poruka('Za lozinku kopije potrebna je dvofaktorska prijava – prvo je uključite (Moj profil → Dvofaktorska prijava).', 'warning');
        } elseif (!kopija_provjeri_2fa($k, (string) ($_POST['kod2fa'] ?? ''))) {
            usleep(500000);
            dnevnik('Lozinka kopije: pogrešan 2FA kod');
            poruka('2FA kod nije točan (ili je već iskorišten).', 'danger');
        } elseif ($greska !== '') {
            poruka($greska, 'warning');
        } elseif (kopija_treba_email() && (!posta_dostupna() || $email === '')) {
            poruka(!posta_dostupna()
                ? 'Za promjenu lozinke kopije mora biti podešeno slanje e-pošte (Sustav → E-pošta i adresa).'
                : 'Vaš račun nema e-mail adresu (upisuje se kod člana). Bez nje se lozinka kopije ne može promijeniti.', 'warning');
        } elseif (!kopija_treba_email()) {
            kopija_primijeni_novu(password_hash($nova, PASSWORD_DEFAULT), $k);
            poruka('Lozinka kopije je spremljena. Zapišite je i čuvajte izvan poslužitelja – bez nje se kopija ne može otvoriti.');
        } else {
            $kod = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                posalji_mail($email, '🔒 Kod za lozinku kopije – ' . udruga_kratko(),
                    '<p>Kod za ' . (kopija_lozinka_postavljena() ? 'promjenu' : 'postavljanje') . ' lozinke kopije:</p><p style="font-size:24px;font-weight:bold;letter-spacing:4px">' . $kod . '</p>'
                    . '<p>Vrijedi ' . KOPIJA_KOD_MINUTA . ' minuta. Ako to niste tražili vi, nemojte nikome davati kod i odmah promijenite lozinku za prijavu.</p>');
                $_SESSION['kopija_zahtjev'] = ['hash' => password_hash($nova, PASSWORD_DEFAULT), 'kod' => password_hash($kod, PASSWORD_DEFAULT),
                    'do' => time() + KOPIJA_KOD_MINUTA * 60, 'pokusaji' => 0, 'korisnik' => (int) $k['Id'], 'email' => $email];
                dnevnik('Lozinka kopije: poslan kod e-poštom', null, null, $email);
                poruka('Poslali smo 6-znamenkasti kod na ' . e($email) . '. Upišite ga ispod.');
            } catch (Throwable $e) {
                poruka('Kod nije poslan: ' . e($e->getMessage()), 'danger');
            }
        }
    } elseif ($radnja === 'lozinka_kod') {
        // Korak 2: kod iz e-pošte
        $z = $_SESSION['kopija_zahtjev'] ?? null;
        if (!$z || $z['korisnik'] !== (int) korisnik()['Id'] || $z['do'] < time()) {
            unset($_SESSION['kopija_zahtjev']);
            poruka('Kod je istekao – počnite ispočetka.', 'warning');
        } elseif (!password_verify((string) preg_replace('/\D/', '', (string) ($_POST['kod'] ?? '')), $z['kod'])) {
            usleep(500000);
            $_SESSION['kopija_zahtjev']['pokusaji'] = ++$z['pokusaji'];
            if ($z['pokusaji'] >= 5) {
                unset($_SESSION['kopija_zahtjev']);
                dnevnik('Lozinka kopije: 5 pogrešnih kodova – zahtjev poništen');
                poruka('Previše pogrešnih pokušaja – zahtjev je poništen.', 'danger');
            } else {
                poruka('Kod nije točan.', 'danger');
            }
        } else {
            unset($_SESSION['kopija_zahtjev']);
            $bila = kopija_lozinka_postavljena();
            kopija_primijeni_novu($z['hash'], korisnik());
            poruka('Lozinka kopije je spremljena. Zapišite je i čuvajte izvan poslužitelja – bez nje se kopija ne može otvoriti.'
                . ($bila ? ' Preuzimanje kopija je zaključano ' . KOPIJA_ZAKLJUCANO_SATI . ' h; svi administratori su obaviješteni e-poštom.' : ''));
        }
    } elseif ($radnja === 'lozinka_odustani') {
        unset($_SESSION['kopija_zahtjev']);
    } elseif ($radnja === 'ponisti') {
        $ok = kopija_ponisti_promjenu(korisnik());
        poruka($ok ? 'Promjena je poništena – vrijedi prethodna lozinka kopije.' : 'Nema promjene koja se može poništiti.', $ok ? 'success' : 'warning');
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
<?php
kopija_pocisti_zakljucavanje();
$imaLoz = kopija_lozinka_postavljena();
$zakljucano = kopija_zakljucano_do();
$smijePreuzeti = $imaLoz && !$zakljucano;
$zahtjev = $_SESSION['kopija_zahtjev'] ?? null;
if ($zahtjev && ($zahtjev['do'] < time() || $zahtjev['korisnik'] !== (int) korisnik()['Id'])) {
    unset($_SESSION['kopija_zahtjev']);
    $zahtjev = null;
}
?>
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
        <?php if ($zakljucano): $tkoP = red('SELECT * FROM Korisnici WHERE Id=?', [kopija_promijenio()]); ?>
        <div class="alert alert-warning small py-2">
            ⏳ Lozinka kopije je promijenjena<?= $tkoP ? ' (<b>' . e(kopija_ime_korisnika($tkoP)) . '</b>)' : '' ?> – <b>preuzimanje je zaključano do <?= e(date('d.m.Y. H:i', $zakljucano)) ?></b>.
            Svi s pravom „Sustav“ obaviješteni su e-poštom. Ako promjena nije dogovorena, poništite je – vraća se prethodna lozinka kopije.
            <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="mt-2"><?= csrf() ?><input type="hidden" name="radnja" value="ponisti">
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Poništiti promjenu i vratiti prethodnu lozinku kopije?">Poništi promjenu</button></form>
        </div>
        <?php endif; ?>
        <?php if ($zahtjev): ?>
        <div class="border rounded p-2 mb-2">
            <div class="small mb-2">📧 Korak 2/2: upišite 6-znamenkasti kod poslan na <b><?= e($zahtjev['email']) ?></b> (vrijedi do <?= e(date('H:i', $zahtjev['do'])) ?>).</div>
            <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="d-flex gap-2 flex-wrap"><?= csrf() ?><input type="hidden" name="radnja" value="lozinka_kod">
                <input name="kod" class="form-control" style="max-width:160px" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus placeholder="123456">
                <button class="btn btn-primary btn-sm">Potvrdi</button></form>
            <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="mt-1"><?= csrf() ?><input type="hidden" name="radnja" value="lozinka_odustani"><button class="btn btn-link btn-sm p-0">Odustani</button></form>
        </div>
        <?php endif; ?>
        <details<?= ($imaLoz || $zahtjev) ? '' : ' open' ?>><summary class="small mb-2"><?= $imaLoz ? 'Promijeni lozinku kopije (i ako je zaboravljena)' : 'Postavi lozinku kopije' ?></summary>
        <p class="small text-muted mb-2">Potvrda: lozinka za prijavu<?= dvofaktor_ukljucen() ? ' + <b>2FA kod</b> (aplikacija ili rezervni kod)' : '' ?><?= kopija_treba_email() ? ' + <b>kod e-poštom</b> na e-mail vašeg računa' : '' ?>.
            <?php if ($imaLoz): ?>Stara lozinka kopije nije potrebna. Nakon promjene preuzimanje je <?= KOPIJA_ZAKLJUCANO_SATI ?> h zaključano, a svi administratori dobivaju e-mail i mogu promjenu poništiti.<?php endif; ?></p>
        <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="row g-2"><?= csrf() ?><input type="hidden" name="radnja" value="lozinka">
            <div class="col-sm-<?= dvofaktor_ukljucen() ? '7' : '12' ?>"><label class="form-label small mb-0">Vaša lozinka za prijavu</label><input name="moja" type="password" class="form-control" required autocomplete="current-password"></div>
            <?php if (dvofaktor_ukljucen()): ?><div class="col-sm-5"><label class="form-label small mb-0">2FA kod</label><input name="kod2fa" class="form-control" required inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="123456"></div><?php endif; ?>
            <div class="col-sm-6"><label class="form-label small mb-0">Nova lozinka kopije (min. <?= KOPIJA_LOZINKA_MIN ?> znakova)</label><input name="nova" type="password" class="form-control" required minlength="<?= KOPIJA_LOZINKA_MIN ?>" autocomplete="new-password"></div>
            <div class="col-sm-6"><label class="form-label small mb-0">Ponovi lozinku kopije</label><input name="nova2" type="password" class="form-control" required minlength="<?= KOPIJA_LOZINKA_MIN ?>" autocomplete="new-password"></div>
            <div class="col-12"><button class="btn btn-outline-primary btn-sm"><?= kopija_treba_email() ? 'Dalje – pošalji kod e-poštom' : 'Spremi lozinku kopije' ?></button>
                <?php if ($imaLoz): ?><span class="small text-muted ms-2">Već preuzete kopije i dalje se otvaraju starom lozinkom.</span><?php endif; ?></div>
        </form></details>
    </div>
</div>
<?php if ($smijePreuzeti): ?>
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
<?php if ($kopije && $smijePreuzeti): ?>
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
            <td class="text-end text-nowrap"><?php if ($smijePreuzeti): ?><button class="btn btn-sm btn-link" form="preuzmi-kopiju" name="ime" value="<?= e($kp['ime']) ?>">Preuzmi 🔒</button><?php endif; ?>
                <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="ime" value="<?= e($kp['ime']) ?>">
                    <button class="btn btn-sm btn-link text-danger" data-potvrda="Obrisati kopiju?">Obriši</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$kopije): ?><tr><td colspan="3" class="text-muted">Još nema kopija.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php stranica('Sigurnosne kopije', ob_get_clean());
