<?php
if (korisnik()) {
    preusmjeri();
}
if (je_post()) {
    $ime = mb_strtolower(ul_str('korisnik'));
    $loz = (string) ($_POST['lozinka'] ?? '');
    $k = red('SELECT k.* FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId
              WHERE k.KorisnickoIme=? OR (c.Email IS NOT NULL AND lower(trim(c.Email))=?) ORDER BY (k.KorisnickoIme=?) DESC LIMIT 1', [$ime, $ime, $ime]);
    $greska = null;
    if (!$k || !$k['Aktivan']) {
        $greska = '1';
    } elseif ($k['ZakljucanDo'] && strtotime(substr($k['ZakljucanDo'], 0, 19)) > time()) {
        $greska = 'zakljucano';
    } elseif (!$k['Odobren']) {
        $greska = provjeri_lozinku((string) $k['LozinkaHash'], $loz) ? 'odobrenje' : '1';
    } elseif (!provjeri_lozinku((string) $k['LozinkaHash'], $loz)) {
        $n = (int) $k['NeuspjelePrijave'] + 1;
        if ($n >= 5) {
            azuriraj('Korisnici', (int) $k['Id'], ['NeuspjelePrijave' => 0, 'ZakljucanDo' => date('Y-m-d H:i:s', time() + 600)]);
        } else {
            azuriraj('Korisnici', (int) $k['Id'], ['NeuspjelePrijave' => $n]);
        }
        $greska = '1';
    }
    if ($greska) {
        usleep(300000);
        preusmjeri('prijava', ['greska' => $greska, 'povratak' => ul_str('povratak')]);
    }
    azuriraj('Korisnici', (int) $k['Id'], ['NeuspjelePrijave' => 0, 'ZakljucanDo' => null, 'ZadnjaPrijava' => sada()]);
    prijavi($k);
    dnevnik('Prijava', null, null, null, $k);
    $pov = ul_str('povratak');
    if ($pov !== '' && str_starts_with($pov, bazni_put()) && !str_starts_with($pov, '//') && !str_contains($pov, 'p=prijava')) {
        header('Location: ' . $pov);
        exit;
    }
    preusmjeri();
}
$g = (string) ($_GET['greska'] ?? '');
ob_start(); ?>
<h1 class="h4 mb-3">Prijava</h1>
<?php if (isset($_GET['postavljeno'])): ?><div class="alert alert-success">Glavni administrator je postavljen. Prijavite se.</div><?php endif; ?>
<?php if (isset($_GET['lozinka'])): ?><div class="alert alert-success">Nova lozinka je postavljena. Prijavite se.</div><?php endif; ?>
<?php if (isset($_GET['vraceno'])): ?><div class="alert alert-success">Podaci su vraćeni iz kopije. Prijavite se dosadašnjim korisničkim imenom i lozinkom.</div><?php endif; ?>
<?php if ($g === '1'): ?><div class="alert alert-danger">Pogrešno korisničko ime ili lozinka.</div><?php endif; ?>
<?php if ($g === 'odobrenje'): ?><div class="alert alert-warning">Vaš račun još čeka odobrenje administratora. Čim bude odobren, moći ćete se prijaviti.</div><?php endif; ?>
<?php if ($g === 'zakljucano'): ?><div class="alert alert-danger">Previše neuspjelih pokušaja. Pokušajte ponovno za 10 minuta.</div><?php endif; ?>
<form method="post" action="<?= e(url('prijava')) ?>">
    <?= csrf() ?>
    <input type="hidden" name="povratak" value="<?= e($_GET['povratak'] ?? '') ?>">
    <div class="mb-3"><label class="form-label">Korisničko ime ili e-mail</label>
        <input name="korisnik" class="form-control" autocomplete="username" autofocus required></div>
    <div class="mb-3"><label class="form-label">Lozinka</label>
        <input name="lozinka" type="password" class="form-control" autocomplete="current-password" required></div>
    <button type="submit" class="btn btn-primary w-100">Prijava</button>
</form>
<div class="text-center mt-3"><a href="<?= e(url('zaboravljena-lozinka')) ?>">Zaboravljena lozinka?</a></div>
<?php stranica('Prijava', ob_get_clean(), 'prazno');
