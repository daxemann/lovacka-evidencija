<?php
$k = korisnik();
$r = red('SELECT * FROM Korisnici WHERE Id=?', [$k['Id']]);
if (je_post()) {
    $stara = (string) ($_POST['stara'] ?? '');
    $loz = (string) ($_POST['lozinka'] ?? '');
    $g = !provjeri_lozinku((string) $r['LozinkaHash'], $stara) ? 'Trenutna lozinka nije točna.'
        : ($loz !== (string) ($_POST['lozinka2'] ?? '') ? 'Nove lozinke se ne podudaraju.'
        : ($loz === $stara ? 'Nova lozinka mora biti različita od stare.' : pravila_lozinke($loz)));
    if ($g) {
        poruka(e($g), 'danger');
        preusmjeri('promjena-lozinke');
    }
    $zig = novi_zig();
    azuriraj('Korisnici', (int) $r['Id'], ['LozinkaHash' => hash_lozinke($loz), 'MoraPromijenitiLozinku' => 0, 'SigurnosniZig' => $zig]);
    $r['SigurnosniZig'] = $zig;
    prijavi($r);
    dnevnik('Promjena lozinke');
    poruka('Lozinka je promijenjena.');
    preusmjeri();
}
ob_start(); ?>
<div style="max-width:460px">
<h1 class="h3 mb-3">Promjena lozinke</h1>
<?php if ($k['MoraPromijenitiLozinku']): ?>
    <div class="alert alert-warning">Prijavili ste se početnom lozinkom. Molimo postavite svoju lozinku.</div>
<?php endif; ?>
<form method="post" action="<?= e(url('promjena-lozinke')) ?>"><?= csrf() ?>
    <div class="mb-3"><label class="form-label">Trenutna lozinka</label>
        <input name="stara" type="password" class="form-control" required autocomplete="current-password"></div>
    <div class="mb-3"><label class="form-label">Nova lozinka (min. 8 znakova, slova i brojke)</label>
        <input name="lozinka" type="password" class="form-control" required autocomplete="new-password"></div>
    <div class="mb-3"><label class="form-label">Ponovi novu lozinku</label>
        <input name="lozinka2" type="password" class="form-control" required autocomplete="new-password"></div>
    <button type="submit" class="btn btn-primary">Spremi</button>
</form>
</div>
<?php stranica('Promjena lozinke', ob_get_clean());
