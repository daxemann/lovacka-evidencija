<?php
trazi(P_SUSTAV);
$trazi = trim((string) ($_GET['trazi'] ?? ''));
$w = $trazi !== '' ? 'WHERE Radnja LIKE ? OR Detalji LIKE ? OR Korisnik LIKE ?' : '';
$p = $trazi !== '' ? array_fill(0, 3, "%$trazi%") : [];
$zapisi = redovi("SELECT * FROM Dnevnik $w ORDER BY Id DESC LIMIT 500", $p);
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><h1 class="h3 mb-0 me-auto">Dnevnik promjena</h1>
    <form method="get" action="<?= e(url()) ?>"><input type="hidden" name="p" value="sustav/dnevnik"><input name="trazi" class="form-control" placeholder="Traži…" value="<?= e($trazi) ?>"></form></div>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Vrijeme</th><th>Korisnik</th><th>Radnja</th><th>Detalji</th></tr></thead>
    <tbody>
    <?php foreach ($zapisi as $z): ?>
        <tr><td class="text-nowrap small"><?= e(datum_vrijeme($z['Vrijeme'])) ?></td><td class="small"><?= e($z['Korisnik']) ?></td><td><?= e($z['Radnja']) ?></td>
            <td class="small"><?= e($z['Detalji']) ?>
                <?php if ($z['Entitet'] === 'Clan' && $z['EntitetId'] && ima(P_CLANOVI_CITAJ)): ?><a href="<?= e(url('clanovi/uredi', ['id' => $z['EntitetId']])) ?>">otvori</a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="small text-muted">Prikazano zadnjih 500 zapisa.</p>
<?php stranica('Dnevnik promjena', ob_get_clean());
