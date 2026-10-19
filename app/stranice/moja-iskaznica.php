<?php
/**
 * Moja iskaznica: članska iskaznica u aplikaciji (QR za popis sudionika grupnog lova).
 * Stranica se sprema na mobitel (service worker) – otvara se i bez signala u lovištu.
 */
header('Cache-Control: no-cache');
$k = korisnik();
$c = $k['ClanId'] ? red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id=?', [$k['ClanId']]) : null;
if (!$c) {
    stranica('Moja iskaznica', '<h1 class="h3">🪪 Moja iskaznica</h1><div class="alert alert-secondary">Vaš račun nije povezan s članom udruge.</div>');
}
if ((int) $c['Status'] !== CLAN_AKTIVAN) {
    stranica('Moja iskaznica', '<h1 class="h3">🪪 Moja iskaznica</h1><div class="alert alert-secondary">Iskaznica vrijedi samo za aktivne članove.</div>');
}
$qr = qr_svg(isk_qr_tekst(isk_kljuc((int) $c['Id'])));
ob_start(); ?>
<div class="isk-ekran">
    <h1 class="h4 mb-3">🪪 Moja iskaznica</h1>
    <?= isk_kartica_html($c, $qr) ?>

    <button type="button" class="btn btn-success btn-lg w-100 mb-2" id="isk-veliki-gumb">🔍 Veliki QR za skeniranje</button>
    <div class="isk-qr-veliki mb-3" id="isk-veliki" hidden><?= $qr ?>
        <div class="text-center small text-muted mt-2"><?= e(prezime_ime($c)) ?> – pojačajte svjetlinu zaslona</div></div>

    <p class="small text-muted">Kod grupnog lova lovnik skenira ovaj QR kod (ili vašu plastičnu iskaznicu) i upisuje vas u popis sudionika.
        QR služi samo za taj popis – u njemu nema vaših podataka. Stranica radi i bez signala ako ste je jednom otvorili s internetom.</p>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('izvoz/iskaznice', ['clan' => $c['Id']])) ?>" target="_blank">📄 PDF za ispis</a>
</div>
<script>
(function () {
    var g = document.getElementById('isk-veliki-gumb'), v = document.getElementById('isk-veliki');
    g.addEventListener('click', function () { v.hidden = !v.hidden; g.textContent = v.hidden ? '🔍 Veliki QR za skeniranje' : '✕ Zatvori veliki QR'; if (!v.hidden) v.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
})();
</script>
<?php stranica('Moja iskaznica', ob_get_clean());
