<?php
/** Danas u lovištu: tko sjedi gdje (po sekciji) i obavijesti za lovočuvare. */
$k = korisnik();
$sekcije = revir_sekcije();
if (!$sekcije) {
    zabranjeno();
}
revir_zatvori_istekle();
$nadzor = ima(P_REVIR_NADZOR);
$naprave = revir_stanje($sekcije);
$zauzete = array_values(array_filter($naprave, fn($n) => $n['zauzeto'] !== null));
usort($zauzete, fn($a, $b) => [$a['sekcija'], $a['zauzeto']['ime']] <=> [$b['sekcija'], $b['zauzeto']['ime']]);
$poSekciji = [];
foreach ($zauzete as $n) {
    $poSekciji[$n['sekcija']][] = $n;
}
$obavijesti = [];
$procitanoDo = 0;
if ($nadzor) {
    $procitanoDo = (int) vrijednost('SELECT DoId FROM RevirProcitano WHERE KorisnikId=?', [$k['Id']]);
    $obavijesti = redovi('SELECT * FROM RevirObavijesti WHERE ' . revir_nadzor_sql() . ' AND Vrijeme>=? ORDER BY Id DESC LIMIT 200', [date('Y-m-d H:i:s', strtotime('-3 days'))]);
    revir_oznaci_procitano();
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">Danas u lovištu</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir')) ?>">Karta lovišta</a>
</div>
<p class="text-muted small">Trenutno zauzete lovne naprave<?= count($sekcije) > 1 ? ' po sekcijama' : '' ?>. Osvježava se svakih 30 s.</p>
<?php if (!$zauzete): ?><div class="alert alert-secondary">Trenutno nitko nije u lovištu.</div><?php endif; ?>
<?php foreach ($poSekciji as $sid => $lista): ?>
    <?php if (count($sekcije) > 1): ?><h2 class="h6 mt-3 mb-1"><?= e($sekcije[$sid] ?? '') ?> <span class="badge bg-danger"><?= count($lista) ?></span></h2><?php endif; ?>
    <ul class="list-group mb-3">
    <?php foreach ($lista as $n): $z = $n['zauzeto']; ?>
        <li class="list-group-item d-flex align-items-center gap-2">
            <span class="revir-tocka <?= $z['moje'] ? 'moje' : 'zauzeto' ?>"></span>
            <div class="flex-grow-1"><b><?= e($z['gost'] ? 'Gost' . ($z['gost'] !== 'gost' ? ' ' . $z['gost'] : '') : $z['ime']) ?></b>
                <?= $z['gost'] ? '<span class="small text-muted">(gost od ' . e($z['ime']) . ')</span>' : '' ?>
                <div class="small"><a href="<?= e(url('revir', ['naprava' => $n['id']])) ?>" class="text-decoration-none"><?= e(($n['broj'] !== '' ? $n['broj'] . ' · ' : '') . $n['naziv']) ?></a></div></div>
            <span class="text-muted small text-nowrap">od <?= e($z['od']) ?><?= $z['odDatum'] !== datum(danas()) ? ' (' . e($z['odDatum']) . ')' : '' ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<?php if ($nadzor): ?>
<h2 class="h5 mt-4">🔔 Obavijesti <span class="small text-muted fw-normal">(zadnja 3 dana)</span></h2>
<?php if (!$obavijesti): ?><p class="text-muted small">Nema obavijesti.</p><?php else: ?>
<ul class="list-group list-group-flush small">
    <?php foreach ($obavijesti as $o): $nova = (int) $o['Id'] > $procitanoDo && (int) $o['KorisnikId'] !== (int) $k['Id']; ?>
        <li class="list-group-item px-0 d-flex gap-2<?= $nova ? ' fw-semibold' : '' ?>">
            <span class="text-muted text-nowrap"><?= e(substr($o['Vrijeme'], 0, 10) === danas() ? date('H:i', strtotime($o['Vrijeme'])) : date('d.m. H:i', strtotime($o['Vrijeme']))) ?></span>
            <span class="flex-grow-1"><?= e($o['Tekst']) ?></span>
            <?php if (count($sekcije) > 1): ?><span class="text-muted text-nowrap"><?= e(naziv_sekcije($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null)) ?></span><?php endif; ?>
            <?= $nova ? '<span class="badge bg-warning text-dark">novo</span>' : '' ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php endif; ?>
<script>setInterval(function () { if (document.visibilityState === 'visible') location.reload(); }, 30000);</script>
<?php stranica('Danas u lovištu', ob_get_clean());
