<?php
/** Predlošci poruka članovima. */
trazi(P_SUSTAV);
if (je_post()) {
    if (($_POST['radnja'] ?? '') === 'zadano') {
        foreach (array_keys(PREDLOSCI_ZADANO) as $kl) {
            spremi_postavku($kl, null);
        }
        poruka('Vraćeni su zadani tekstovi.');
    } else {
        foreach (PREDLOSCI_ZADANO as $kl => $zad) {
            $v = str_replace("\r\n", "\n", (string) ($_POST[str_replace('.', '_', $kl)] ?? ''));
            spremi_postavku($kl, trim($v) === '' || $v === $zad ? null : $v);
        }
        dnevnik('Uređeni predlošci poruka');
        poruka('Spremljeno.');
    }
    preusmjeri('sustav/poruke');
}
$p = predlosci_poruka();
$polje = fn(string $kl, int $redaka = 0) => $redaka
    ? '<textarea name="' . str_replace('.', '_', $kl) . '" class="form-control mb-3" rows="' . $redaka . '">' . e($p[$kl]) . '</textarea>'
    : '<input name="' . str_replace('.', '_', $kl) . '" class="form-control mb-2" value="' . e($p[$kl]) . '">';
ob_start(); ?>
<h1 class="h3 mb-1">Predlošci poruka članovima</h1>
<p class="text-muted">Tekstovi za „Pošalji popis bodova“, „Info o članarini“, pozivnicu i obavijest o odobrenju. Prije slanja se tekst uvijek još može izmijeniti.</p>
<div class="row g-4">
<div class="col-lg-8">
<form method="post" action="<?= e(url('sustav/poruke')) ?>"><?= csrf() ?>
    <h2 class="h6 mt-2">Popis bodova</h2><?= $polje('Predlozak.Bodovi.Naslov') ?><?= $polje('Predlozak.Bodovi', 10) ?>
    <h2 class="h6">Info o članarini (prijateljski, bez opomene)</h2><?= $polje('Predlozak.Clanarina.Naslov') ?><?= $polje('Predlozak.Clanarina', 9) ?>
    <h2 class="h6">Pozivnica (poveznica za postavljanje lozinke)</h2><?= $polje('Predlozak.Pozivnica', 8) ?>
    <h2 class="h6">Pristup odobren</h2><?= $polje('Predlozak.Odobreno', 5) ?>
    <h2 class="h6">Potpis</h2><?= $polje('Predlozak.Potpis', 2) ?>
    <div class="d-flex gap-2"><button class="btn btn-primary">Spremi</button>
        <button class="btn btn-outline-secondary" name="radnja" value="zadano" data-potvrda="Vratiti sve zadane tekstove?">Vrati zadane tekstove</button></div>
</form>
</div>
<div class="col-lg-4"><div class="card sticky-top" style="top:4rem"><div class="card-header">Oznake (zamjenjuju se automatski)</div>
    <ul class="list-group list-group-flush small">
        <?php foreach (OZNAKE_PREDLOZAKA as $o => $opis): ?><li class="list-group-item"><code><?= e($o) ?></code> – <?= e($opis) ?></li><?php endforeach; ?>
    </ul></div></div>
</div>
<?php stranica('Predlošci poruka', ob_get_clean());
