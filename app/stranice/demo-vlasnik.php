<?php
/**
 * Upravljanje demo instalacijom – samo za vlasnika (tajni ključ Demo.Kljuc u adresi: index.php?p=demo-vlasnik&k=…).
 * Spremi trenutno stanje kao početno · vrati početno stanje · zamijeni demo podatke kompletnom kopijom (ZIP) · isključi demo.
 */
$kljuc = (string) postavka('Demo.Kljuc', '');
$dano = (string) ($_GET['k'] ?? $_POST['k'] ?? '');
if ($kljuc === '' || strlen($kljuc) < 16 || !hash_equals($kljuc, $dano)) {
    usleep(500000);
    nije_pronadjeno();
}
if (je_post()) {
    $r = (string) ($_POST['radnja'] ?? '');
    if ($r === 'spremi') {
        demo_spremi_izvor();
        poruka('Trenutno stanje spremljeno je kao početno demo stanje.');
    } elseif ($r === 'vrati') {
        demo_vrati_izvor();
        odjavi();
        session_start();
        poruka('Početno demo stanje je vraćeno.');
    } elseif ($r === 'zip') {
        $f = $_FILES['kopija'] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            try {
                vrati_kompletnu_kopiju($f['tmp_name']);
                odjavi();
                session_start();
                // nova baza: ključ i demo zastavica ostaju, spremi kao početno stanje u sljedećem zahtjevu
                @unlink(demo_izvor('evidencija.db'));
                poruka('Kompletna kopija je učitana. Ako u njoj nije Demo.Aktivno = 1, demo je isključen.');
            } catch (Throwable $e) {
                poruka('Nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    } elseif ($r === 'iskljuci') {
        spremi_postavku('Demo.Aktivno', '0');
        poruka('Demo je isključen – instalacija radi normalno.');
    } elseif ($r === 'ukljuci') {
        spremi_postavku('Demo.Aktivno', '1');
        poruka('Demo je uključen.');
    }
    preusmjeri('demo-vlasnik', ['k' => $kljuc]);
}
$zadnji = (string) @file_get_contents(podaci('demo-reset.txt'));
$f = fn(string $r, string $t, string $kl = 'btn-outline-primary', string $pot = '') => '<form method="post" action="' . e(url('demo-vlasnik', ['k' => $kljuc])) . '" class="d-inline">' . csrf()
    . '<input type="hidden" name="radnja" value="' . $r . '"><button class="btn ' . $kl . ' mb-2"' . ($pot ? ' data-potvrda="' . e($pot) . '"' : '') . '>' . e($t) . '</button></form> ';
ob_start(); ?>
<h1 class="h4 mb-3">Demo – vlasnik</h1>
<p class="small">Demo: <b><?= je_demo() ? 'uključen' : 'isključen' ?></b> · početno stanje: <?= is_file(demo_izvor('evidencija.db')) ? 'spremljeno' : 'nije spremljeno' ?> · zadnji povratak: <?= e($zadnji ?: '–') ?> (svake noći nakon <?= DEMO_SATI_RESETA ?>:00)</p>
<?= $f('spremi', 'Spremi trenutno stanje kao početno', 'btn-primary', 'Trenutni podaci postaju početno demo stanje?') ?>
<?= $f('vrati', 'Vrati početno stanje sada', 'btn-outline-primary', 'Vratiti početno demo stanje?') ?>
<?= je_demo() ? $f('iskljuci', 'Isključi demo', 'btn-outline-danger', 'Isključiti demo način?') : $f('ukljuci', 'Uključi demo', 'btn-outline-success') ?>
<hr>
<form method="post" action="<?= e(url('demo-vlasnik', ['k' => $kljuc])) ?>" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="radnja" value="zip">
    <label class="form-label small">Zamijeni demo podatke kompletnom kopijom (ZIP)</label>
    <input type="file" name="kopija" accept=".zip" class="form-control mb-2" required>
    <button class="btn btn-outline-danger" data-potvrda="Zamijeniti sve demo podatke?">Učitaj</button>
</form>
<p class="mt-3"><a href="<?= e(url('prijava')) ?>">Prijava</a></p>
<?php stranica('Demo – vlasnik', ob_get_clean(), 'prazno');
