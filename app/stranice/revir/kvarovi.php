<?php
/** Kvarovi na lovnim napravama: popis za sve lovce sekcije, obrada (preuzmi / riješeno / obriši), nova prijava, PDF. */
$k = korisnik();
$sekcije = revir_sekcije();
if (!$sekcije) {
    zabranjeno();
}

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $natrag = ['status' => ul_str('f_status'), 's' => ul_str('f_s')];
    if ($radnja === 'prijavi') {
        $g = revir_kvar_prijavi((int) ($_POST['naprava'] ?? 0), (string) ($_POST['opis'] ?? ''), !empty($_POST['nesigurno']), $_FILES['foto'] ?? null);
        poruka($g === null ? 'Kvar je prijavljen – hvala!' : e($g), $g === null ? 'success' : 'danger');
    } else {
        $g = revir_kvar_radnja((int) ($_POST['kid'] ?? 0), $radnja, (string) ($_POST['napomena'] ?? ''));
        $ok = ['preuzmi' => 'Kvar je preuzet.', 'rijeseno' => 'Kvar je označen kao riješen.', 'otvori' => 'Kvar je ponovno otvoren.', 'obrisi' => 'Prijava je obrisana.'];
        poruka($g === null ? ($ok[$radnja] ?? 'Spremljeno.') : e($g), $g === null ? 'success' : 'danger');
    }
    preusmjeri('revir/kvarovi', array_filter($natrag, fn($v) => $v !== ''));
}

$statusF = (string) ($_GET['status'] ?? 'aktivni');
if (!in_array($statusF, ['aktivni', 'rijeseno', 'sve'], true)) {
    $statusF = 'aktivni';
}
$sF = (string) ($_GET['s'] ?? '');
$opseg = ($sF !== '' && array_key_exists((int) $sF, $sekcije)) ? [(int) $sF => $sekcije[(int) $sF]] : $sekcije;
if ($opseg === $sekcije) {
    $sF = '';
}
$w = [revir_kvar_sql($opseg)];
if ($statusF === 'aktivni') {
    $w[] = "Status<>'rijeseno'";
} elseif ($statusF === 'rijeseno') {
    $w[] = "Status='rijeseno'";
}
$kvarovi = redovi('SELECT * FROM RevirKvarovi WHERE ' . implode(' AND ', $w)
    . " ORDER BY CASE Status WHEN 'rijeseno' THEN 1 ELSE 0 END, Nesigurno DESC, COALESCE(Rijeseno, Prijavljeno) DESC LIMIT 500");
$broj = [];
foreach (redovi('SELECT Status, COUNT(*) AS n FROM RevirKvarovi WHERE ' . revir_kvar_sql($opseg) . ' GROUP BY Status') as $r) {
    $broj[$r['Status']] = (int) $r['n'];
}
$aktivnih = ($broj['otvoreno'] ?? 0) + ($broj['u_radu'] ?? 0);
$naprave = revir_naprave($opseg);
$visePolja = count($sekcije) > 1;
$filtar = fn(array $p) => url('revir/kvarovi', array_filter(['status' => $statusF, 's' => $sF] + $p, fn($v) => $v !== '' && $v !== 'aktivni'));

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">🔧 Kvarovi na lovnim napravama</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir')) ?>">🗺️ Karta lovišta</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/kvarovi-pdf', array_filter(['status' => $statusF, 's' => $sF]))) ?>">📄 PDF</a>
</div>
<p class="text-muted small">Prijave vide svi lovci sekcije. Kvar prijavljujete na karti (dodir na napravu → <b>Prijavi kvar</b>) ili ovdje. Lovočuvar dobiva obavijest.</p>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Status">
        <a class="btn btn-outline-secondary<?= $statusF === 'aktivni' ? ' active' : '' ?>" href="<?= e($filtar(['status' => 'aktivni'])) ?>">Otvoreni <span class="badge bg-warning text-dark"><?= $aktivnih ?></span></a>
        <a class="btn btn-outline-secondary<?= $statusF === 'rijeseno' ? ' active' : '' ?>" href="<?= e($filtar(['status' => 'rijeseno'])) ?>">Riješeni <span class="badge bg-secondary"><?= $broj['rijeseno'] ?? 0 ?></span></a>
        <a class="btn btn-outline-secondary<?= $statusF === 'sve' ? ' active' : '' ?>" href="<?= e($filtar(['status' => 'sve'])) ?>">Svi</a>
    </div>
    <?php if ($visePolja): ?>
        <form method="get" action="<?= e(bazni_put() . 'index.php') ?>" class="ms-auto">
            <input type="hidden" name="p" value="revir/kvarovi"><input type="hidden" name="status" value="<?= e($statusF) ?>">
            <select name="s" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Sekcija">
                <option value="">Sve sekcije</option>
                <?php foreach ($sekcije as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel((string) $id, $sF) ?>><?= e($n) ?></option><?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<details class="card mb-3"<?= !$kvarovi && $statusF === 'aktivni' ? ' open' : '' ?>>
    <summary class="card-header">➕ Prijavi kvar</summary>
    <div class="card-body">
        <?php if (!$naprave): ?><p class="text-muted small mb-0">U sekciji još nema lovnih naprava.</p><?php else: ?>
        <form method="post" enctype="multipart/form-data" class="row g-2" data-kvar-forma><?= csrf() ?>
            <input type="hidden" name="radnja" value="prijavi"><input type="hidden" name="f_status" value="<?= e($statusF) ?>"><input type="hidden" name="f_s" value="<?= e($sF) ?>">
            <div class="col-md-5"><label class="form-label small mb-0">Lovna naprava *</label>
                <select name="naprava" class="form-select form-select-sm" required>
                    <option value="">– odaberite –</option>
                    <?php foreach ($naprave as $n): ?><option value="<?= (int) $n['Id'] ?>"><?= e(revir_oznaka($n)) ?><?= $visePolja ? ' · ' . e(naziv_sekcije($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null)) : '' ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-7"><label class="form-label small mb-0">Što je oštećeno? *</label>
                <input name="opis" class="form-control form-control-sm" maxlength="500" required placeholder="npr. slomljena prečka na ljestvama, krov propušta…"></div>
            <div class="col-md-5"><label class="form-label small mb-0">Fotografija (neobavezno)</label><input type="file" name="foto" accept="image/*" class="form-control form-control-sm"></div>
            <div class="col-md-4 d-flex align-items-end"><label class="form-check small mb-1"><input type="checkbox" name="nesigurno" value="1" class="form-check-input"> <b>Nesigurno</b> – ne sjedati</label></div>
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-warning btn-sm w-100">Pošalji prijavu</button></div>
        </form>
        <?php endif; ?>
    </div>
</details>

<?php if (!$kvarovi): ?>
    <div class="alert alert-<?= $statusF === 'aktivni' ? 'success' : 'secondary' ?>"><?= $statusF === 'aktivni' ? '✅ Nema otvorenih kvarova.' : 'Nema prijava.' ?></div>
<?php endif; ?>
<div class="list-group mb-4">
<?php foreach ($kvarovi as $kv):
    $sid = revir_kvar_sid($kv);
    $obrada = revir_moze_kvar($sid);
    $moj = revir_kvar_moj($kv);
    $cl = $kv['Status'] === 'rijeseno' ? 'rijeseno' : ($kv['Nesigurno'] ? 'nesigurno' : $kv['Status']); ?>
    <div class="list-group-item kvar-red <?= e($cl) ?>">
        <div class="d-flex gap-3">
            <?php if ($kv['Foto']): ?><a href="<?= e(foto_url($kv['Foto'])) ?>" target="_blank" rel="noopener"><img src="<?= e(foto_url($kv['Foto'])) ?>" class="kvar-foto" alt="" loading="lazy"></a><?php endif; ?>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($kv['NapravaId']): ?><a class="fw-semibold text-decoration-none" href="<?= e(url('revir', ['naprava' => $kv['NapravaId']])) ?>">📍 <?= e($kv['Naprava']) ?></a>
                    <?php else: ?><span class="fw-semibold"><?= e($kv['Naprava']) ?> <span class="small text-muted">(naprava obrisana)</span></span><?php endif; ?>
                    <?php if ($visePolja): ?><span class="small text-muted"><?= e(naziv_sekcije($sid)) ?></span><?php endif; ?>
                    <?php if ($kv['Nesigurno'] && $kv['Status'] !== 'rijeseno'): ?><span class="badge bg-danger">⚠️ nesigurno</span><?php endif; ?>
                    <span class="badge <?= ['otvoreno' => 'bg-warning text-dark', 'u_radu' => 'bg-primary', 'rijeseno' => 'bg-success'][$kv['Status']] ?? 'bg-secondary' ?> ms-auto"><?= e(KVAR_STATUSI[$kv['Status']] ?? $kv['Status']) ?></span>
                </div>
                <div class="my-1"><?= nl2br(e($kv['Opis'])) ?></div>
                <div class="small text-muted">
                    Prijavio <?= e($kv['PrijavioIme']) ?>, <?= e(datum_vrijeme($kv['Prijavljeno'])) ?>
                    <?php if ($kv['PreuzeoIme']): ?> · preuzeo <?= e($kv['PreuzeoIme']) ?> <?= e(datum($kv['Preuzeto'])) ?><?php endif; ?>
                    <?php if ($kv['Status'] === 'rijeseno'): ?> · <b class="text-success">riješio <?= e($kv['RijesioIme']) ?>, <?= e(datum_vrijeme($kv['Rijeseno'])) ?></b><?php endif; ?>
                </div>
                <?php if ($kv['Napomena']): ?><div class="small mt-1">📝 <?= e($kv['Napomena']) ?></div><?php endif; ?>

                <?php if ($obrada || $moj): ?>
                <form method="post" class="d-flex flex-wrap gap-1 mt-2"><?= csrf() ?>
                    <input type="hidden" name="kid" value="<?= (int) $kv['Id'] ?>"><input type="hidden" name="f_status" value="<?= e($statusF) ?>"><input type="hidden" name="f_s" value="<?= e($sF) ?>">
                    <?php if ($kv['Status'] !== 'rijeseno'): ?>
                        <?php if ($obrada && $kv['Status'] === 'otvoreno'): ?><button class="btn btn-sm btn-outline-primary" name="radnja" value="preuzmi">🛠️ Preuzimam</button><?php endif; ?>
                        <input name="napomena" class="form-control form-control-sm" style="max-width:260px" maxlength="500" placeholder="napomena (npr. zamijenjena prečka)" aria-label="Napomena">
                        <button class="btn btn-sm btn-success" name="radnja" value="rijeseno">✅ Riješeno</button>
                    <?php elseif ($obrada): ?>
                        <button class="btn btn-sm btn-outline-secondary" name="radnja" value="otvori">↩ Ponovno otvori</button>
                    <?php endif; ?>
                    <?php if ($obrada || ($moj && $kv['Status'] === 'otvoreno')): ?>
                        <button class="btn btn-sm btn-outline-danger ms-auto" name="radnja" value="obrisi" formnovalidate data-potvrda="Obrisati ovu prijavu kvara?">🗑 Obriši</button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
<script src="<?= e(asset('assets/revir-foto.js')) ?>"></script>
<script>
// fotografija se smanjuje prije slanja (slab signal u lovištu)
document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-kvar-forma]');
    if (!f || f.dataset.poslano || !window.RevirFoto || !f.foto.files[0] || !window.DataTransfer) return;
    e.preventDefault();
    RevirFoto.smanji(f.foto.files[0]).then(function (m) {
        try { var dt = new DataTransfer(); dt.items.add(m); f.foto.files = dt.files; } catch (x) {}
        f.dataset.poslano = '1'; f.submit();
    });
});
</script>
<?php stranica('Kvarovi na lovnim napravama', ob_get_clean());
