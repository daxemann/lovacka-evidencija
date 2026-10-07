<?php
$k = korisnik();
if (!$k['Prava']) {
    preusmjeri($k['ClanId'] ? 'moje-akcije' : 'oglasnik');
}
$op = opseg_sql('c');
$clanovi = redovi("SELECT c.* FROM Clanovi c WHERE c.Status=0 AND $op");
$aktivni = count($clanovi);
$neraspore = $k['SveSekcije'] ? count(array_filter($clanovi, fn($c) => $c['SekcijaId'] === null)) : 0;
$naCekanju = ima(P_AKCIJE_CITAJ) ? broj_akcija_na_cekanju() : 0;
$cekaju = ima(P_SUSTAV) ? (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND Aktivan=1') : 0;
$duplikati = ima(P_SUSTAV) ? count(pronadji_duplikate()) : 0;
$neplaceno = 0;
if (ima(P_CLANARINA_CITAJ)) {
    $god = (int) date('Y');
    $osl = oslobodjeni_clanarine($god);
    foreach (redovi("SELECT cl.ClanId, CAST(cl.Iznos AS REAL) - COALESCE((SELECT SUM(CAST(u.Iznos AS REAL)) FROM Uplate u WHERE u.ClanarinaId=cl.Id),0) AS Preostalo
                     FROM Clanarine cl JOIN Clanovi c ON c.Id=cl.ClanId WHERE cl.Godina=? AND c.Status=0 AND $op", [$god]) as $r) {
        if ($r['Preostalo'] > 0.004 && !isset($osl[(int) $r['ClanId']])) {
            $neplaceno++;
        }
    }
}
// rođendani (14 dana) i iskaznice (60 dana)
$rodj = [];
$isk = [];
$danas = new DateTimeImmutable('today');
foreach ($clanovi as $c) {
    if ($c['DatumRodjenja']) {
        $dr = new DateTimeImmutable(substr($c['DatumRodjenja'], 0, 10));
        for ($i = 0; $i <= 1; $i++) {
            $g = (int) $danas->format('Y') + $i;
            $m = (int) $dr->format('m');
            $d = (int) $dr->format('d');
            if ($m === 2 && $d === 29 && !checkdate(2, 29, $g)) {
                $d = 28;
            }
            $rd = $danas->setDate($g, $m, $d);
            $raz = (int) $danas->diff($rd)->format('%r%a');
            if ($raz >= 0 && $raz <= 14) {
                $rodj[] = [$c, $rd, $g - (int) $dr->format('Y')];
                break;
            }
        }
    }
    if ($c['IskaznicaVrijediDo'] && substr($c['IskaznicaVrijediDo'], 0, 10) <= $danas->modify('+60 days')->format('Y-m-d')) {
        $isk[] = $c;
    }
}
usort($rodj, fn($a, $b) => $a[1] <=> $b[1]);
usort($isk, fn($a, $b) => strcmp($a['IskaznicaVrijediDo'], $b['IskaznicaVrijediDo']));
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0 me-auto">Dobro došli, <?= e($k['Naziv']) ?></h1>
    <?php if (ima(P_AKCIJE_ODOBRI)): ?><a href="<?= e(url('akcije/nova')) ?>" class="btn btn-primary">+ Nova radna akcija</a><?php endif; ?>
</div>
<?php if (ima(P_SUSTAV) && str_starts_with(str_replace('\\', '/', realpath(podaci()) ?: ''), str_replace('\\', '/', realpath(KORIJEN) ?: '-'))): ?>
    <div class="alert alert-danger d-none" id="izlozeno"><b>Upozorenje – baza je javno dostupna!</b> Poslužitelj ne poštuje datoteku .htaccess (npr. nginx), pa se mapa <code>podaci</code> može preuzeti s interneta.
        Premjestite mapu s podacima izvan javne mape weba (config.php → 'podaci') ili zabranite pristup mapi <code>podaci</code> u postavkama poslužitelja. Upute: README.</div>
    <script>fetch(<?= json_encode(bazni_put() . substr(str_replace('\\', '/', realpath(podaci())), strlen(str_replace('\\', '/', realpath(KORIJEN))) + 1) . '/evidencija.db') ?>, { method: 'HEAD', cache: 'no-store' })
        .then(function (r) { if (r.ok) document.getElementById('izlozeno').classList.remove('d-none'); }).catch(function () {});</script>
<?php endif; ?>
<?php if ($duplikati > 0): ?>
    <div class="alert alert-warning d-flex align-items-center"><span>Mogući duplikati članova: <b><?= $duplikati ?></b></span>
        <a href="<?= e(url('sustav/duplikati')) ?>" class="btn btn-sm btn-warning ms-auto">Provjeri</a></div>
<?php endif; ?>
<?php if ($cekaju > 0): ?>
    <div class="alert alert-warning d-flex align-items-center"><span><b><?= $cekaju ?></b> <?= $cekaju === 1 ? 'član čeka' : 'članova čeka' ?> odobrenje pristupa.</span>
        <a href="<?= e(url('sustav/korisnici')) ?>" class="btn btn-sm btn-warning ms-auto">Pregledaj</a></div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <?php if (ima(P_CLANOVI_CITAJ)): ?>
        <div class="col-6 col-lg-3"><a href="<?= e(url('clanovi')) ?>" class="card text-decoration-none h-100"><div class="card-body">
            <div class="kartica-broj"><?= $aktivni ?></div><div class="text-muted">aktivnih članova</div></div></a></div>
        <div class="col-6 col-lg-3"><a href="<?= e(url('clanovi', ['sekcija' => 'nije'])) ?>" class="card text-decoration-none h-100"><div class="card-body">
            <div class="kartica-broj <?= $neraspore > 0 ? 'text-warning' : '' ?>"><?= $neraspore ?></div><div class="text-muted">nije raspoređeno u sekciju</div></div></a></div>
    <?php endif; ?>
    <?php if (ima(P_AKCIJE_CITAJ)): ?>
        <div class="col-6 col-lg-3"><a href="<?= e(url('akcije')) ?>" class="card text-decoration-none h-100"><div class="card-body">
            <div class="kartica-broj <?= $naCekanju > 0 ? 'text-warning' : '' ?>"><?= $naCekanju ?></div><div class="text-muted">radnih akcija čeka odobrenje</div></div></a></div>
    <?php endif; ?>
    <?php if (ima(P_CLANARINA_CITAJ)): ?>
        <div class="col-6 col-lg-3"><a href="<?= e(url('clanarina')) ?>" class="card text-decoration-none h-100"><div class="card-body">
            <div class="kartica-broj <?= $neplaceno > 0 ? 'text-danger' : '' ?>"><?= $neplaceno ?></div><div class="text-muted">nepodmirenih članarina <?= date('Y') ?>.</div></div></a></div>
    <?php endif; ?>
</div>
<?php if (ima(P_CLANOVI_CITAJ)): ?>
<div class="row g-3">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Rođendani u sljedećih 14 dana</div>
        <ul class="list-group list-group-flush">
            <?php if (!$rodj): ?><li class="list-group-item text-muted">Nema rođendana.</li><?php endif; ?>
            <?php foreach ($rodj as [$c, $d, $god]): ?>
                <li class="list-group-item d-flex align-items-center gap-2"><?= avatar($c) ?>
                    <a href="<?= e(url('clanovi/uredi', ['id' => $c['Id']])) ?>"><?= e(puno_ime($c)) ?></a>
                    <span class="ms-auto text-muted"><?= $d->format('d.m.') ?> (<?= $god ?>. g.)</span></li>
            <?php endforeach; ?>
        </ul></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header">Lovačka iskaznica ističe (60 dana) ili je istekla</div>
        <ul class="list-group list-group-flush">
            <?php if (!$isk): ?><li class="list-group-item text-muted">Ništa ne ističe.</li><?php endif; ?>
            <?php foreach ($isk as $c): ?>
                <li class="list-group-item d-flex align-items-center gap-2"><?= avatar($c) ?>
                    <a href="<?= e(url('clanovi/uredi', ['id' => $c['Id']])) ?>"><?= e(puno_ime($c)) ?></a>
                    <span class="ms-auto <?= substr($c['IskaznicaVrijediDo'], 0, 10) < date('Y-m-d') ? 'text-danger' : 'text-warning' ?>"><?= e(date('d.m.Y', strtotime(substr($c['IskaznicaVrijediDo'], 0, 10)))) ?></span></li>
            <?php endforeach; ?>
        </ul></div></div>
</div>
<?php endif;
stranica('Početna', ob_get_clean());
