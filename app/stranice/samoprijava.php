<?php
/** Javna stranica: član se sam prijavljuje preko zajedničke poveznice, administrator zatim odobrava. */
$token = (string) ($_GET['k'] ?? $_POST['k'] ?? '');
$vazeci = samoprijava_token();
$valjan = $vazeci !== null && $token !== '' && hash_equals($vazeci, $token);
$sekcije = sekcije(true);

if (je_post() && $valjan) {
    $natrag = fn() => preusmjeri('samoprijava', ['k' => $token]);
    $_SESSION['samoprijava_unos'] = array_intersect_key($_POST, array_flip(['Ime', 'Prezime', 'Mobilni', 'Email', 'SekcijaId', 'Napomena', 'korisnik']));
    $sada = time();
    $_SESSION['samoprijava_pokusaji'] = array_values(array_filter($_SESSION['samoprijava_pokusaji'] ?? [], fn($t) => $t > $sada - 3600));
    if (trim((string) ($_POST['web'] ?? '')) !== '') { // zamka za robote
        preusmjeri('samoprijava', ['k' => $token, 'gotovo' => 1]);
    }
    $ime = mb_substr(ul_str('Ime'), 0, 60);
    $prezime = mb_substr(ul_str('Prezime'), 0, 60);
    $mob = mb_substr(ul_str('Mobilni'), 0, 30);
    $email = mb_substr(mb_strtolower(ul_str('Email')), 0, 120);
    $sekcija = (int) ($_POST['SekcijaId'] ?? 0);
    $napomena = mb_substr(ul_str('Napomena'), 0, 300);
    $kor = mb_strtolower(ul_str('korisnik'));
    $loz = (string) ($_POST['lozinka'] ?? '');
    $g = null;
    if (count($_SESSION['samoprijava_pokusaji']) >= 5) {
        $g = 'Previše pokušaja. Pokušajte ponovno za sat vremena.';
    } elseif ((int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0') >= SAMOPRIJAVA_MAX_NA_CEKANJU) {
        $g = 'Trenutno je previše prijava na čekanju. Javite se administratoru udruge.';
    } elseif ($ime === '' || $prezime === '') {
        $g = 'Upišite ime i prezime.';
    } elseif (!preg_match('/\d{6,}/', preg_replace('/\D/', '', $mob))) {
        $g = 'Upišite broj mobitela (po njemu vas administrator prepoznaje).';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $g = 'E-mail adresa nije ispravna.';
    } elseif ($sekcija && !in_array($sekcija, array_map('intval', array_column($sekcije, 'Id')), true)) {
        $g = 'Odaberite sekciju.';
    } elseif (mb_strlen($kor) < 3 || preg_match('/\s/u', $kor)) {
        $g = 'Korisničko ime mora imati najmanje 3 znaka i bez razmaka.';
    } elseif (vrijednost('SELECT 1 FROM Korisnici WHERE KorisnickoIme=?', [$kor])) {
        $g = 'To korisničko ime je zauzeto – odaberite drugo.';
    } elseif ($loz !== (string) ($_POST['lozinka2'] ?? '')) {
        $g = 'Lozinke se ne podudaraju.';
    } elseif (($_POST['suglasnost'] ?? '') !== 'da') {
        $g = 'Potrebna je suglasnost za obradu podataka.';
    } else {
        $g = pravila_lozinke($loz);
    }
    if ($g) {
        poruka(e($g), 'danger');
        $natrag();
    }
    $_SESSION['samoprijava_pokusaji'][] = $sada;
    $id = transakcija(function () use ($ime, $prezime, $mob, $email, $sekcija, $napomena, $kor, $loz) {
        $id = umetni('Korisnici', [
            'KorisnickoIme' => $kor, 'LozinkaHash' => hash_lozinke($loz), 'ClanId' => null, 'PrikaznoIme' => $ime . ' ' . $prezime,
            'Aktivan' => 1, 'MoraPromijenitiLozinku' => 0, 'Kreirano' => sada(), 'NeuspjelePrijave' => 0,
            'SigurnosniZig' => novi_zig(), 'Odobren' => 0, 'SuglasnostDatum' => sada(),
        ]);
        samoprijava_spremi($id, ['Ime' => $ime, 'Prezime' => $prezime, 'Mobilni' => $mob, 'Email' => $email ?: null,
            'SekcijaId' => $sekcija ?: null, 'Napomena' => $napomena ?: null, 'Kreirano' => sada()]);
        return $id;
    });
    dnevnik('Samoprijava – čeka odobrenje', null, null, "$ime $prezime ($kor)", ['Id' => $id, 'KorisnickoIme' => $kor]);
    unset($_SESSION['samoprijava_unos']);
    preusmjeri('samoprijava', ['k' => $token, 'gotovo' => 1]);
}

$u = $_SESSION['samoprijava_unos'] ?? [];
$v = fn(string $k) => e((string) ($u[$k] ?? ''));
ob_start();
if (!$valjan): ?>
    <h1 class="h4 mb-3">Prijava članova</h1>
    <div class="alert alert-warning mb-0">Ova poveznica nije valjana ili je prijava trenutno zatvorena. Javite se administratoru udruge.</div>
<?php elseif (isset($_GET['gotovo'])): ?>
    <h1 class="h4 mb-3">Hvala!</h1>
    <div class="alert alert-success mb-0">
        Vaša prijava je zaprimljena i <b>čeka odobrenje administratora</b>. Kad bude odobrena, moći ćete se
        <a href="<?= e(url('prijava')) ?>">prijaviti</a> svojim korisničkim imenom i lozinkom.
    </div>
<?php else: ?>
    <h1 class="h4 mb-1">Prijava članova</h1>
    <p class="text-muted small">Za članove udruge. Administrator će provjeriti podatke i odobriti pristup.</p>
    <form method="post" action="<?= e(url('samoprijava', ['k' => $token])) ?>" autocomplete="on"><?= csrf() ?>
        <input type="hidden" name="k" value="<?= e($token) ?>">
        <div class="d-none" aria-hidden="true"><input name="web" tabindex="-1" autocomplete="off"></div>
        <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Ime</label><input name="Ime" class="form-control" value="<?= $v('Ime') ?>" required autocomplete="given-name"></div>
            <div class="col-6"><label class="form-label">Prezime</label><input name="Prezime" class="form-control" value="<?= $v('Prezime') ?>" required autocomplete="family-name"></div>
        </div>
        <div class="mb-2"><label class="form-label">Mobitel</label><input name="Mobilni" type="tel" class="form-control" value="<?= $v('Mobilni') ?>" required autocomplete="tel" placeholder="091 234 5678"></div>
        <div class="mb-2"><label class="form-label">E-mail <span class="text-muted small">(nije obavezno)</span></label><input name="Email" type="email" class="form-control" value="<?= $v('Email') ?>" autocomplete="email"></div>
        <?php if ($sekcije): ?>
            <div class="mb-2"><label class="form-label">Sekcija</label>
                <select name="SekcijaId" class="form-select"><option value="0">– ne znam –</option>
                    <?php foreach ($sekcije as $s): ?><option value="<?= (int) $s['Id'] ?>" <?= (int) ($u['SekcijaId'] ?? 0) === (int) $s['Id'] ? 'selected' : '' ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <div class="mb-3"><label class="form-label">Napomena <span class="text-muted small">(nije obavezno)</span></label><input name="Napomena" class="form-control" value="<?= $v('Napomena') ?>" maxlength="300"></div>
        <hr>
        <div class="mb-2"><label class="form-label">Korisničko ime</label><input name="korisnik" class="form-control" value="<?= $v('korisnik') ?>" required autocomplete="username"></div>
        <div class="mb-2"><label class="form-label">Lozinka (min. 8 znakova, slova i brojke)</label><input name="lozinka" type="password" class="form-control" required autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label">Ponovi lozinku</label><input name="lozinka2" type="password" class="form-control" required autocomplete="new-password"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="suglasnost" value="da" id="sugl" required>
            <label class="form-check-label small" for="sugl">Suglasan/na sam da udruga moje podatke (kontakt, lovački podaci, fotografija, radne akcije, članarina)
                vodi u ovoj evidenciji isključivo za potrebe udruge.</label></div>
        <button type="submit" class="btn btn-primary w-100">Pošalji prijavu</button>
    </form>
    <p class="small text-center mt-3 mb-0">Već imate račun? <a href="<?= e(url('prijava')) ?>">Prijava</a></p>
<?php endif;
stranica('Prijava članova', ob_get_clean(), 'prazno');
