<?php
$token = (string) ul('token', '');
$reset = $token !== '' ? red('SELECT * FROM ResetiLozinki WHERE TokenHash=? AND Iskoristen=0 AND VrijediDo>?', [hash_tokena($token), sada()]) : null;
if (je_post()) {
    $loz = (string) ($_POST['lozinka'] ?? '');
    $g = pravila_lozinke($loz);
    if ($loz !== (string) ($_POST['lozinka2'] ?? '')) {
        $g = 'Lozinke se ne podudaraju.';
    }
    if (!$reset) {
        $g = 'Poveznica je istekla ili je već iskorištena.';
    }
    if ($g) {
        poruka(e($g), 'danger');
        preusmjeri('nova-lozinka', ['token' => $token]);
    }
    $k = red('SELECT * FROM Korisnici WHERE Id=?', [$reset['KorisnikId']]);
    if (!$k) {
        preusmjeri('prijava');
    }
    azuriraj('Korisnici', (int) $k['Id'], ['LozinkaHash' => hash_lozinke($loz), 'MoraPromijenitiLozinku' => 0, 'SigurnosniZig' => novi_zig(), 'ZakljucanDo' => null, 'NeuspjelePrijave' => 0]);
    azuriraj('ResetiLozinki', (int) $reset['Id'], ['Iskoristen' => 1]);
    dnevnik('Nova lozinka putem poveznice', null, null, null, $k);
    preusmjeri('prijava', ['lozinka' => 1]);
}
ob_start(); ?>
<h1 class="h4 mb-3">Nova lozinka</h1>
<?php if (!$reset): ?>
    <p>Poveznica nije valjana. <a href="<?= e(url('zaboravljena-lozinka')) ?>">Zatražite novu</a>.</p>
<?php else: ?>
    <form method="post" action="<?= e(url('nova-lozinka')) ?>"><?= csrf() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label">Nova lozinka (min. 8 znakova, slova i brojke)</label>
            <input name="lozinka" type="password" class="form-control" required autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label">Ponovi lozinku</label>
            <input name="lozinka2" type="password" class="form-control" required autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary w-100">Spremi lozinku</button>
    </form>
<?php endif; ?>
<?php stranica('Nova lozinka', ob_get_clean(), 'prazno');
