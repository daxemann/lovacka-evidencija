<?php
/**
 * Jedna trgovina s popustom: članska iskaznica na zaslonu + uputa (blagajna / javiti se prodavaču).
 * Traka „Aktivni član“ s datumom i satom koji teče – prodavač vidi da to nije stara snimka zaslona.
 */
header('Cache-Control: no-cache');
$k = korisnik();
$id = (int) ($_GET['id'] ?? 0);
$t = $id ? red('SELECT * FROM Popusti WHERE Id=?', [$id]) : null;
$uredi = pop_smije_uredjivati();
if (!$t || (!pop_vrijedi($t) && !$uredi)) {
    stranica('Popusti u trgovinama', '<p class="mb-2"><a href="' . e(url('popusti')) . '">← Popusti u trgovinama</a></p>'
        . '<div class="alert alert-secondary">Ova ponuda više ne vrijedi.</div>');
}
$n = pop_nacin($t['Nacin']);
$c = $k['ClanId'] ? red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id=?', [$k['ClanId']]) : null;
$aktivan = $c && (int) $c['Status'] === CLAN_AKTIVAN;
$web = pop_web_url($t['Web']);

ob_start(); ?>
<div class="isk-ekran">
    <p class="mb-2"><a href="<?= e(url('popusti')) ?>">← Popusti u trgovinama</a></p>
    <?php if (!pop_vrijedi($t)): ?><div class="alert alert-warning small py-2">Članovi ovu trgovinu ne vide (<?= (int) $t['Aktivno'] ? 'isteklo ' . e(datum($t['VrijediDo'])) : 'isključeno' ?>).</div><?php endif; ?>

    <div class="d-flex align-items-center gap-3 mb-3">
        <?= pop_logo_html($t, 'pop-logo pop-logo-veliki') ?>
        <div class="flex-grow-1 overflow-hidden">
            <h1 class="h4 mb-1"><?= e($t['Naziv']) ?></h1>
            <span class="badge pop-popust fs-6"><?= e($t['Popust']) ?></span>
        </div>
        <?php if ($uredi): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('popusti/uredi', ['id' => $t['Id']])) ?>">✏️</a><?php endif; ?>
    </div>

    <?php if ($aktivan): ?>
        <div class="alert alert-success d-flex gap-2 align-items-start py-2 mb-2 pop-uputa">
            <span class="fs-4 lh-1"><?= $n[0] ?></span><div><b><?= e($n[2]) ?></b></div>
        </div>
        <?= isk_kartica_html($c) ?>
        <div class="pop-vazi mb-3" aria-live="off">
            <span class="pop-vazi-tocka" aria-hidden="true"></span>
            <span class="fw-semibold">✓ Aktivni član</span>
            <span class="ms-auto text-nowrap" id="pop-sat" data-datum="<?= e(date('Y-m-d')) ?>"><?= e(date('d.m.Y.')) ?> · <?= e(date('H:i:s')) ?></span>
        </div>
    <?php elseif ($c): ?>
        <div class="alert alert-secondary">Popust vrijedi samo za aktivne članove udruge.</div>
    <?php else: ?>
        <div class="alert alert-secondary">Vaš račun nije povezan s članom udruge – iskaznica se ne može prikazati.</div>
    <?php endif; ?>

    <?php if ($t['Uvjeti']): ?>
        <div class="card mb-3"><div class="card-body py-2 small"><div class="text-muted mb-1">Uvjeti popusta</div><?= nl2br(e($t['Uvjeti'])) ?></div></div>
    <?php endif; ?>

    <?php if ($t['Telefon'] || $t['Email'] || $web || $t['Adresa'] || $t['KontaktOsoba']): ?>
    <div class="card mb-3"><div class="card-body py-2">
        <?php if ($t['KontaktOsoba']): ?><div class="small text-muted mb-1">Kontakt: <b class="text-body"><?= e($t['KontaktOsoba']) ?></b></div><?php endif; ?>
        <?php if ($t['Adresa']): ?><div class="small mb-2">📍 <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($t['Naziv'] . ', ' . $t['Adresa'])) ?>" target="_blank" rel="noopener"><?= e($t['Adresa']) ?></a></div><?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <?= gumbi_kontakta($t['Telefon']) ?>
            <?php if ($t['Email']): ?><a class="btn btn-sm btn-outline-secondary" href="mailto:<?= e($t['Email']) ?>?subject=<?= e(rawurlencode('Član ' . udruga_naziv())) ?>">✉️ E-pošta</a><?php endif; ?>
            <?php if ($web): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($web) ?>" target="_blank" rel="noopener">🌐 Web</a><?php endif; ?>
        </div>
    </div></div>
    <?php endif; ?>
</div>
<?php if ($aktivan): ?>
<script>
(function () {
    // sat koji teče: na snimci zaslona stoji, na pravom zaslonu se mijenja svake sekunde
    var s = document.getElementById('pop-sat');
    function d2(x) { return (x < 10 ? '0' : '') + x; }
    function tik() { var d = new Date(); s.textContent = d2(d.getDate()) + '.' + d2(d.getMonth() + 1) + '.' + d.getFullYear() + '. · ' + d2(d.getHours()) + ':' + d2(d.getMinutes()) + ':' + d2(d.getSeconds()); }
    tik(); setInterval(tik, 1000);
})();
</script>
<?php endif; ?>
<?php stranica($t['Naziv'] . ' – popust', ob_get_clean());
