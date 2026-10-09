<?php
/** Potvrda upisa na dezinfekcijskoj stanici – može se pokazati kontroli. */
$k = korisnik();
$g = (string) ($_GET['g'] ?? '');
$upisi = preg_match('/^[a-f0-9]{16}$/', $g)
    ? redovi('SELECT u.*, st.Naziv AS Stanica, st.Token, st.Sredstvo FROM DezUpisi u JOIN DezStanice st ON st.Id=u.StanicaId WHERE u.Grupa=? ORDER BY u.Id', [$g])
    : [];
if (!$upisi || ((int) $upisi[0]['UpisaoKorisnikId'] !== (int) $k['Id'] && !ima(P_DEZ_PREGLED))) {
    nije_pronadjeno('Upis');
}
$u0 = $upisi[0];
$dol = $u0['Smjer'] === 'D';
ob_start(); ?>
<div class="text-center">
    <div class="dez-kvacica <?= $dol ? 'dez-dolazak' : 'dez-odlazak' ?>">✓</div>
    <h1 class="h4 mt-2 mb-0"><?= $dol ? 'Dolazak upisan' : 'Odlazak upisan' ?></h1>
    <div class="display-6 fw-bold my-1"><?= e(date('H:i', strtotime($u0['Vrijeme']))) ?></div>
    <div class="text-muted"><?= e(datum($u0['Vrijeme'])) ?> · <?= e($u0['Stanica']) ?></div>
</div>
<?php if ($u0['Ponisteno']): ?><div class="alert alert-danger mt-3 mb-0">Ovaj upis je poništen: <?= e($u0['PonistenoRazlog']) ?></div><?php endif; ?>
<ul class="list-group mt-3">
    <?php foreach ($upisi as $u): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><b><?= e(dez_ime($u)) ?></b><?= $u['Gost'] ? ' <span class="badge bg-secondary">gost</span>' : '' ?></span>
            <span class="text-muted small"><?= e($u['Oznaka'] ?? 'bez vozila') ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<div class="small text-muted mt-2">Razlog: <?= e($u0['Razlog']) ?> · dezinficirano: <?= e(dez_dezinficirano($u0)) ?><?= $u0['Sredstvo'] ? ' (' . e($u0['Sredstvo']) . ')' : '' ?>
    · lokacija: <?= e(DEZ_LOKACIJA[(int) $u0['Lokacija']] ?? '') ?></div>
<?php if ($dol): ?><div class="alert alert-light border small mt-3 mb-0">Pri odlasku ponovno skenirajte QR oznaku i odaberite <b>ODLAZAK</b>.</div><?php endif; ?>
<div class="d-grid gap-2 mt-3">
    <a class="btn btn-outline-secondary" href="<?= e(url('dez', ['s' => $u0['Token']])) ?>">Novi upis</a>
    <a class="btn btn-link" href="<?= e(url()) ?>">Početna</a>
</div>
<?php stranica('Upisano', ob_get_clean(), 'prazno');
