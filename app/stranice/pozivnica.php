<?php
$token = (string) ul('token', '');
$p = pronadji_pozivnicu($token);
if (je_post()) {
    if (!$p) {
        poruka('Pozivnica nije valjana, istekla je ili je već iskorištena.', 'danger');
        preusmjeri('pozivnica');
    }
    $ime = mb_strtolower(ul_str('korisnik'));
    $loz = (string) ($_POST['lozinka'] ?? '');
    if (mb_strlen($ime) < 3 || preg_match('/\s/u', $ime)) {
        $g = 'Korisničko ime mora imati najmanje 3 znaka i bez razmaka.';
    } elseif (vrijednost('SELECT 1 FROM Korisnici WHERE KorisnickoIme=?', [$ime])) {
        $g = 'To korisničko ime je zauzeto – odaberite drugo.';
    } elseif (vrijednost('SELECT 1 FROM Korisnici WHERE ClanId=?', [$p['ClanId']])) {
        $g = 'Za vas već postoji račun. Obratite se administratoru.';
    } elseif ($loz !== (string) ($_POST['lozinka2'] ?? '')) {
        $g = 'Lozinke se ne podudaraju.';
    } elseif (($_POST['suglasnost'] ?? '') !== 'da') {
        $g = 'Potrebna je suglasnost za obradu podataka.';
    } else {
        $g = pravila_lozinke($loz);
    }
    if ($g) {
        poruka(e($g), 'danger');
        preusmjeri('pozivnica', ['token' => $token]);
    }
    $novi = transakcija(function () use ($p, $ime, $loz) {
        $id = umetni('Korisnici', [
            'KorisnickoIme' => $ime, 'LozinkaHash' => hash_lozinke($loz), 'ClanId' => $p['ClanId'], 'PrikaznoIme' => null,
            'Aktivan' => 1, 'MoraPromijenitiLozinku' => 0, 'Kreirano' => sada(), 'NeuspjelePrijave' => 0,
            'SigurnosniZig' => novi_zig(), 'Odobren' => 0, 'SuglasnostDatum' => sada(),
        ]);
        q('UPDATE Pozivnice SET Iskoristena=? WHERE Id=?', [sada(), $p['Id']]);
        foreach (redovi('SELECT * FROM PlaniraneUloge WHERE ClanId=?', [$p['ClanId']]) as $pu) {
            umetni('KorisnikUloge', ['KorisnikId' => $id, 'UlogaId' => $pu['UlogaId'], 'SekcijaId' => $pu['SekcijaId']]);
        }
        q('DELETE FROM PlaniraneUloge WHERE ClanId=?', [$p['ClanId']]);
        return $id;
    });
    dnevnik('Registracija putem pozivnice – čeka odobrenje', 'Clan', (int) $p['ClanId'], puno_ime($p), ['Id' => $novi, 'KorisnickoIme' => $ime]);
    preusmjeri('pozivnica', ['gotovo' => 1]);
}
ob_start();
if (isset($_GET['gotovo'])): ?>
    <h1 class="h4 mb-3">Hvala!</h1>
    <div class="alert alert-success mb-0">
        Lozinka je postavljena. Vaš račun sada <b>čeka odobrenje administratora</b>.
        Kad bude odobren, javit ćemo vam se i moći ćete se <a href="<?= e(url('prijava')) ?>">prijaviti</a>.
    </div>
<?php else: ?>
    <h1 class="h4 mb-3">Pozivnica</h1>
    <?php if (!$p): ?>
        <div class="alert alert-warning mb-0">Pozivnica nije valjana, istekla je ili je već iskorištena. Zatražite novu od administratora udruge.</div>
    <?php else: ?>
        <form method="post" action="<?= e(url('pozivnica')) ?>"><?= csrf() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3"><label class="form-label">Ime i prezime</label>
                <input class="form-control" value="<?= e(puno_ime($p)) ?>" disabled></div>
            <div class="mb-3"><label class="form-label">Korisničko ime</label>
                <input name="korisnik" class="form-control" value="<?= e(predlozi_korisnicko_ime($p['Ime'], $p['Prezime'])) ?>" required autocomplete="username">
                <div class="form-text">Prijaviti se možete i svojom e-mail adresom (ako je upisana kod udruge).</div></div>
            <div class="mb-3"><label class="form-label">Lozinka (min. 8 znakova, slova i brojke)</label>
                <input name="lozinka" type="password" class="form-control" required autocomplete="new-password"></div>
            <div class="mb-3"><label class="form-label">Ponovi lozinku</label>
                <input name="lozinka2" type="password" class="form-control" required autocomplete="new-password"></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="suglasnost" value="da" id="sugl" required>
                <label class="form-check-label small" for="sugl">
                    Suglasan/na sam da udruga moje podatke (kontakt, lovački podaci, fotografija, radne akcije, članarina)
                    vodi u ovoj evidenciji isključivo za potrebe udruge.
                </label></div>
            <button type="submit" class="btn btn-primary w-100">Postavi lozinku</button>
        </form>
    <?php endif; ?>
<?php endif;
stranica('Pozivnica', ob_get_clean(), 'prazno');
