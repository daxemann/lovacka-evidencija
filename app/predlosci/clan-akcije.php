<?php
/** Kartica Radne akcije. Varijable: $id, $clan */
$lg = pocetak_lovne_godine();
$kraj = $lg->modify('+1 year -1 day');
$akcije = redovi('SELECT a.*, v.Naziv AS VrstaNaziv FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.ClanId=? ORDER BY a.Datum DESC, a.Id DESC', [$id]);
$bodovi = 0.0;
foreach ($akcije as $a) {
    $d = substr($a['Datum'], 0, 10);
    if ((int) $a['Status'] === AKCIJA_ODOBRENO && $d >= $lg->format('Y-m-d') && $d <= $kraj->format('Y-m-d')) {
        $bodovi += (float) $a['Bodovi'];
    }
}
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div>Bodovi u tekućoj lovnoj godini (<?= $lg->format('d.m.Y.') ?> – <?= $kraj->format('d.m.Y.') ?>): <b class="fs-5"><?= broj($bodovi, 2) ?></b></div>
    <?php if (ima(P_AKCIJE_ODOBRI)): ?><a href="<?= e(url('akcije/nova', ['clan' => $id])) ?>" class="btn btn-sm btn-primary ms-auto no-print">+ Upiši radnu akciju</a><?php endif; ?>
</div>
<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead><tr><th>Datum</th><th>Vrsta</th><th>Sati</th><th>Opis</th><th>Status</th><th class="text-end">Bodovi</th></tr></thead>
    <tbody>
    <?php foreach ($akcije as $a): ?>
        <tr>
            <td class="text-nowrap"><?= e(datum($a['Datum'])) ?></td>
            <td><?= e($a['VrstaNaziv'] ?? $a['VrstaSlobodno'] ?? '—') ?></td>
            <td><?= e(broj($a['Sati'])) ?></td>
            <td class="small"><?= e($a['Opis']) ?><?php if ((int) $a['Status'] === AKCIJA_ODBIJENO && $a['RazlogOdbijanja']): ?><div class="text-danger">Razlog: <?= e($a['RazlogOdbijanja']) ?></div><?php endif; ?></td>
            <td><?= status_akcije_oznaka((int) $a['Status']) ?></td>
            <td class="text-end"><?= e(broj($a['Bodovi'], 2)) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$akcije): ?><tr><td colspan="6" class="text-muted">Nema radnih akcija.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
