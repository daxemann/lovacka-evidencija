<?php
/** Kartica Članarina. Varijable: $id, $clan */
$moze = ima(P_CLANARINA_UREDI);
$sve = redovi('SELECT * FROM Clanarine WHERE ClanId=? ORDER BY Godina DESC', [$id]);
$pocasniSada = isset(oslobodjeni_clanarine((int) date('Y'))[$id]);
$zadnjiIznos = vrijednost('SELECT Iznos FROM Clanarine WHERE Godina=(SELECT MAX(Godina) FROM Clanarine) GROUP BY Iznos ORDER BY COUNT(*) DESC LIMIT 1');
$f = fn(string $r, array $skriveno, string $tekst, string $klasa, string $potvrda = '') =>
    '<form method="post" class="d-inline" action="' . e(url('clanovi/uredi', ['id' => $id])) . '">' . csrf() . '<input type="hidden" name="radnja" value="' . $r . '">'
    . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . $k . '" value="' . e($v) . '">', array_keys($skriveno), $skriveno))
    . '<button class="btn btn-sm ' . $klasa . '"' . ($potvrda ? ' data-potvrda="' . e($potvrda) . '"' : '') . '>' . $tekst . '</button></form>';
?>
<?php if ($pocasniSada): ?>
    <div class="alert alert-info">Počasni član – <b>oslobođen članarine</b>. Članarina se ne zadužuje i ne pojavljuje se kao nepodmirena.</div>
<?php endif; ?>
<?php foreach ($sve as $cl):
    $c2 = clanarina_clana($id, (int) $cl['Godina']);
    $osl = isset(oslobodjeni_clanarine((int) $cl['Godina'])[$id]); ?>
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <h2 class="h5 mb-0"><?= (int) $cl['Godina'] ?>.</h2>
            <span>Iznos: <b><?= novac($c2['Iznos']) ?> €</b></span>
            <span>Uplaćeno: <b><?= novac($c2['Uplaceno']) ?> €</b></span>
            <?php if ($osl): ?><span class="badge bg-info text-dark ms-auto">Oslobođen (počasni član)</span>
            <?php elseif ($c2['Preostalo'] <= 0.004): ?><span class="badge bg-success ms-auto">Plaćeno</span>
            <?php elseif ($c2['Uplaceno'] > 0): ?><span class="badge bg-warning text-dark ms-auto">Djelomično – preostalo <?= novac($c2['Preostalo']) ?> €</span>
            <?php else: ?><span class="badge bg-danger ms-auto">Nije plaćeno</span><?php endif; ?>
            <?php if ($moze && !$c2['Uplate']) echo $f('clanarina-obrisi', ['cid' => $cl['Id']], 'Ukloni zaduženje', 'btn-link text-danger', 'Ukloniti zaduženje za ' . $cl['Godina'] . '.?'); ?>
        </div>
        <?php if ($c2['Uplate']): ?>
            <ul class="list-group list-group-flush mb-2">
            <?php foreach ($c2['Uplate'] as $u): ?>
                <li class="list-group-item d-flex align-items-center gap-2 px-0">
                    <span><?= e(datum($u['Datum'])) ?></span><b><?= novac($u['Iznos']) ?> €</b><span class="text-muted small"><?= e($u['Napomena']) ?></span>
                    <?php if ($moze) echo '<span class="ms-auto">' . $f('uplata-obrisi', ['uid' => $u['Id']], 'Obriši', 'btn-link text-danger', 'Obrisati uplatu?') . '</span>'; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($moze && !$osl): ?>
            <form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" class="row g-2 align-items-end no-print"><?= csrf() ?>
                <input type="hidden" name="radnja" value="uplata-dodaj"><input type="hidden" name="cid" value="<?= (int) $cl['Id'] ?>">
                <div class="col-sm-3"><label class="form-label small">Datum uplate</label><input type="date" name="Datum" class="form-control form-control-sm" value="<?= danas() ?>"></div>
                <div class="col-sm-3"><label class="form-label small">Iznos</label><input name="Iznos" type="number" step="0.01" class="form-control form-control-sm" value="<?= $c2['Preostalo'] > 0 ? number_format($c2['Preostalo'], 2, '.', '') : '' ?>" required></div>
                <div class="col-sm-4"><label class="form-label small">Napomena</label><input name="Napomena" class="form-control form-control-sm"></div>
                <div class="col-sm-2"><button class="btn btn-sm btn-outline-primary w-100">+ Uplata</button></div>
            </form>
        <?php endif; ?>
    </div></div>
<?php endforeach; ?>
<?php if (!$sve): ?><p class="text-muted">Nema evidentirane članarine.</p><?php endif; ?>
<?php if ($moze): ?>
<form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" class="row g-2 align-items-end no-print"><?= csrf() ?>
    <input type="hidden" name="radnja" value="clanarina-dodaj">
    <div class="col-sm-3"><label class="form-label">Godina</label><input type="number" name="Godina" class="form-control" value="<?= date('Y') ?>" min="2000" max="2100"></div>
    <div class="col-sm-3"><label class="form-label">Iznos (EUR)</label><input type="number" step="0.01" name="Iznos" class="form-control" value="<?= $zadnjiIznos !== null ? number_format((float) $zadnjiIznos, 2, '.', '') : '' ?>" required></div>
    <div class="col-sm-3"><button class="btn btn-primary">Dodaj članarinu</button></div>
</form>
<?php endif;
