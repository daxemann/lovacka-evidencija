<?php
/** Izgled: zadana tema (svijetla / tamna / kao uređaj). Svaki korisnik može je promijeniti za sebe (☀️/🌙 gore i Moj profil). */
trazi(P_SUSTAV);
if (je_post()) {
    $t = (string) ($_POST['tema'] ?? '');
    if (!in_array($t, TEME, true)) {
        $t = 'svijetla';
    }
    spremi_postavku('Izgled.Tema', $t === 'svijetla' ? null : $t);
    dnevnik('Izgled – zadana tema', null, null, TEME_NAZIVI[$t]);
    poruka('Spremljeno. Korisnici koji nisu sami odabrali temu vide je odmah.');
    preusmjeri('sustav/izgled');
}
$tema = tema_zadana();
ob_start(); ?>
<h1 class="h3 mb-3">🎨 Izgled</h1>
<form method="post" class="card" style="max-width:640px"><?= csrf() ?>
    <div class="card-header">Zadana tema za sve korisnike</div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <?php foreach (['svijetla' => '☀️', 'tamna' => '🌙', 'uredaj' => '📱'] as $v => $ik): ?>
            <div class="col-4">
                <input type="radio" class="btn-check" name="tema" id="tema-<?= $v ?>" value="<?= $v ?>"<?= $tema === $v ? ' checked' : '' ?>>
                <label class="btn btn-outline-primary w-100 py-3" for="tema-<?= $v ?>"><span class="d-block fs-3"><?= $ik ?></span><?= e(TEME_NAZIVI[$v]) ?></label>
            </div>
            <?php endforeach; ?>
        </div>
        <ul class="small text-muted mb-0">
            <li><b>Kao uređaj</b> – prati postavku mobitela/računala (noću tamno, ako je tako podešeno).</li>
            <li>Svaki korisnik može temu promijeniti za sebe gumbom <b>☀️ / 🌙</b> gore ili u <b>Moj profil</b> – vrijedi na tom uređaju.</li>
            <li>Tamna tema štedi bateriju i ne zasljepljuje na čeki u sumrak. Ispis i PDF uvijek su svijetli.</li>
        </ul>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<?php stranica('Izgled', ob_get_clean());
