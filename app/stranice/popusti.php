<?php
/** 🏷️ Popusti u trgovinama: popis trgovina s popustom za članove. Dodir na trgovinu → iskaznica na zaslonu. */
$uredi = pop_smije_uredjivati();

if (je_post()) {
    if (!$uredi) {
        zabranjeno();
    }
    if (($_POST['radnja'] ?? '') === 'poredak') {
        $sve = array_map('intval', array_column(pop_trgovine(true), 'Id'));
        $i = array_search((int) ($_POST['id'] ?? 0), $sve, true);
        $j = $i === false ? false : (($_POST['smjer'] ?? '') === 'gore' ? $i - 1 : $i + 1);
        if ($i !== false && isset($sve[$j])) {
            [$sve[$i], $sve[$j]] = [$sve[$j], $sve[$i]];
        }
        transakcija(function () use ($sve) {
            foreach ($sve as $r => $tid) {
                q('UPDATE Popusti SET Redoslijed=? WHERE Id=?', [$r, $tid]);
            }
        });
    }
    preusmjeri('popusti');
}

$trgovine = pop_trgovine($uredi);
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto">🏷️ Popusti u trgovinama</h1>
    <?php if ($uredi): ?><a class="btn btn-success" href="<?= e(url('popusti/uredi')) ?>">+ Nova trgovina</a><?php endif; ?>
</div>
<p class="text-muted small mb-3">Ove trgovine daju popust članovima <?= e(udruga_naziv()) ?>. Dodirnite trgovinu – otvara se vaša članska iskaznica koju pokažete na blagajni.</p>

<?php if (!$trgovine): ?>
    <div class="alert alert-secondary"><?= $uredi ? 'Još nema trgovina. Dodajte prvu: <b>+ Nova trgovina</b>.' : 'Trenutno nema trgovina s popustom.' ?></div>
<?php endif; ?>

<div class="list-group mb-3">
<?php foreach ($trgovine as $i => $t): $vrijedi = pop_vrijedi($t); $n = pop_nacin($t['Nacin']); ?>
    <div class="list-group-item d-flex align-items-center gap-3 pop-red<?= $vrijedi ? '' : ' opacity-50' ?>">
        <a class="d-flex align-items-center gap-3 flex-grow-1 text-decoration-none text-body overflow-hidden" href="<?= e(url('popusti/trgovina', ['id' => $t['Id']])) ?>">
            <?= pop_logo_html($t) ?>
            <span class="flex-grow-1 overflow-hidden">
                <span class="fw-semibold d-block text-truncate"><?= e($t['Naziv']) ?></span>
                <span class="small text-muted d-block text-truncate"><?= $t['Adresa'] ? '📍 ' . e($t['Adresa']) : $n[0] . ' ' . e($n[1]) ?></span>
                <?php if (!$vrijedi): ?><span class="badge bg-secondary"><?= (int) $t['Aktivno'] ? 'isteklo ' . e(datum($t['VrijediDo'])) : 'isključeno' ?> – članovi ne vide</span><?php endif; ?>
            </span>
            <span class="badge pop-popust"><?= e($t['Popust']) ?></span>
        </a>
        <?php if ($uredi): ?>
        <form method="post" class="d-flex flex-column gap-1"><?= csrf() ?><input type="hidden" name="radnja" value="poredak"><input type="hidden" name="id" value="<?= (int) $t['Id'] ?>">
            <button class="btn btn-sm btn-outline-secondary py-0" name="smjer" value="gore"<?= $i === 0 ? ' disabled' : '' ?> title="Gore" aria-label="Gore">▲</button>
            <button class="btn btn-sm btn-outline-secondary py-0" name="smjer" value="dolje"<?= $i === count($trgovine) - 1 ? ' disabled' : '' ?> title="Dolje" aria-label="Dolje">▼</button>
        </form>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('popusti/uredi', ['id' => $t['Id']])) ?>" title="Uredi" aria-label="Uredi">✏️</a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php if ($uredi && $trgovine): ?><p class="small text-muted">Sivo = članovi ne vide (isključeno ili isteklo). Redoslijed mijenjate strelicama.</p><?php endif; ?>
<?php stranica('Popusti u trgovinama', ob_get_clean());
