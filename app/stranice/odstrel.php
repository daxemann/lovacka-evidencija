<?php
/** 🦌 Odstrel: popis po lovnoj godini i evidenciji (sekcija / spojene sekcije), filtar (vrsta, markica / lovac, SLE), sortiranje po markici ili datumu. */
$k = korisnik();
if (!odstrel_dostupno()) {
    zabranjeno();
}
$sle = odstrel_sle();
if (je_post() && ($_POST['radnja'] ?? '') === 'sle' && $sle) {
    // brza oznaka „upisano u SLE“ iz popisa
    $o = odstrel_red((int) ($_POST['id'] ?? 0));
    if (!$o || !odstrel_moze_urediti($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null)) {
        zabranjeno();
    }
    azuriraj('Odstrel', (int) $o['Id'], ['SleDatum' => danas(), 'Azurirano' => sada()]);
    dnevnik('Odstrel upisan u SLE', 'Odstrel', (int) $o['Id'], $o['Vrsta'] . ($o['Markica'] ? ', markica ' . $o['Markica'] : ''));
    poruka('Označeno: upisano u SLE (' . e($o['Vrsta']) . ($o['Markica'] ? ', markica ' . e($o['Markica']) : '') . ').');
    preusmjeri('odstrel', array_filter(['g' => $o['LovnaGodina'], 's' => (string) ($_POST['s'] ?? ''), 'sle' => (string) ($_POST['sle'] ?? '')], fn($x) => $x !== ''));
}
$sekcije = odstrel_sekcije();
$uredive = odstrel_sekcije(true);
$samoMoje = !$sekcije; // član bez prava: samo svoj odstrel
$godine = odstrel_godine();
$g = (int) ul('g', odstrel_lovna_godina());
if (!in_array($g, $godine, true)) {
    $g = odstrel_lovna_godina();
}
$sF = (string) ul('s', '');
$opseg = ($sF !== '' && array_key_exists((int) $sF, $sekcije)) ? [(int) $sF => $sekcije[(int) $sF]] : $sekcije;
$vrstaF = mb_substr(trim((string) ul('vrsta', '')), 0, 60);
$trazi = mb_substr(trim((string) ul('q', '')), 0, 60);
$sort = ul('sort', 'datum') === 'markica' ? 'markica' : 'datum';
$sleF = $sle ? (string) ul('sle', '') : '';
$viseSekcija = count(odstrel_sve_sekcije()) > 1;

$w = ['LovnaGodina=?'];
$par = [$g];
if ($samoMoje) {
    $w[] = 'ClanId=?';
    $par[] = (int) $k['ClanId'];
} else {
    $w[] = odstrel_sek_sql($opseg);
}
if ($vrstaF !== '') {
    $w[] = 'Vrsta=?';
    $par[] = $vrstaF;
}
if ($sleF === '0') {
    $w[] = 'SleDatum IS NULL';
} elseif ($sleF === '1') {
    $w[] = 'SleDatum IS NOT NULL';
}
if ($trazi !== '') {
    $w[] = '(Markica LIKE ? OR Lovac LIKE ?)';
    $par[] = '%' . $trazi . '%';
    $par[] = '%' . $trazi . '%';
}
$order = $sort === 'markica' ? "Markica IS NULL OR Markica='', CAST(Markica AS INTEGER), Markica, Datum" : 'Datum DESC, Vrijeme DESC, Id DESC';
$lista = redovi('SELECT * FROM Odstrel WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $order . ' LIMIT 2000', $par);
$poVrsti = [];
foreach ($lista as $o) {
    $poVrsti[$o['Vrsta']] = ($poVrsti[$o['Vrsta']] ?? 0) + 1;
}
$vrsteSve = array_column(redovi('SELECT DISTINCT Vrsta FROM Odstrel WHERE LovnaGodina=? ORDER BY Vrsta', [$g]), 'Vrsta');
$naprave = modul('revir') ? array_column(redovi('SELECT Id, Broj, Naziv FROM RevirNaprave'), null, 'Id') : [];
$filtar = fn(array $x) => url('odstrel', array_filter(array_merge(['g' => $g, 's' => $sF, 'vrsta' => $vrstaF, 'q' => $trazi, 'sort' => $sort, 'sle' => $sleF], $x), fn($v) => $v !== '' && $v !== null));
$polja = odstrel_polja();
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto">🦌 <?= $samoMoje ? 'Moj odstrel' : 'Odstrel' ?></h1>
    <?php if ($uredive): ?><a class="btn btn-success" href="<?= e(url('odstrel/uredi', ['s' => $sF])) ?>">➕ Upiši odstrel</a><?php endif; ?>
    <?php if (!$samoMoje): ?><a class="btn btn-outline-primary" href="<?= e(url('odstrel/plan', ['g' => $g, 's' => $sF])) ?>">📋 Plan odstrela</a><?php endif; ?>
    <a class="btn btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/odstrel-pdf', array_filter(['g' => $g, 's' => $sF, 'vrsta' => $vrstaF, 'q' => $trazi, 'sort' => $sort, 'sle' => $sleF]))) ?>">📄 PDF</a>
    <?php if (ima(P_SUSTAV)): ?><a class="btn btn-outline-secondary" href="<?= e(url('sustav/odstrel')) ?>" title="Postavke odstrela">⚙️</a><?php endif; ?>
</div>
<?= $samoMoje ? '' : odstrel_upozorenja_html() ?>
<form class="row g-2 align-items-end mb-3" method="get"><input type="hidden" name="p" value="odstrel"><input type="hidden" name="sort" value="<?= e($sort) ?>">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Lovna godina</label>
        <select name="g" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($godine as $x): ?><option value="<?= $x ?>"<?= sel($x, $g) ?>><?= e(odstrel_lg_naziv($x)) ?></option><?php endforeach; ?>
        </select></div>
    <?php if (count($sekcije) > 1): ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-0"><?= odstrel_vodjenje() === 'mjesovito' ? 'Evidencija' : 'Sekcija' ?></label>
        <select name="s" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Sve moje <?= odstrel_vodjenje() === 'mjesovito' ? 'evidencije' : 'sekcije' ?></option>
            <?php foreach ($sekcije as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel((string) $id, $sF) ?>><?= e($n) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Vrsta</label>
        <select name="vrsta" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Sve vrste</option>
            <?php foreach ($vrsteSve as $v): ?><option<?= sel($v, $vrstaF) ?>><?= e($v) ?></option><?php endforeach; ?>
        </select></div>
    <?php if ($sle && !$samoMoje): ?>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">SLE</label>
        <select name="sle" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">svi upisi</option>
            <option value="0"<?= sel('0', $sleF) ?>>✗ nije upisano u SLE</option><option value="1"<?= sel('1', $sleF) ?>>✓ upisano u SLE</option></select></div>
    <?php endif; ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Markica ili lovac</label>
        <input name="q" class="form-control form-control-sm" value="<?= e($trazi) ?>" placeholder="traži…"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Prikaži</button></div>
</form>
<?php if ($poVrsti): ?>
<div class="d-flex flex-wrap gap-1 mb-2">
    <?php foreach ($poVrsti as $v => $n): ?><a class="badge rounded-pill text-bg-light border text-decoration-none" href="<?= e($filtar(['vrsta' => $v])) ?>"><?= e($v) ?> <b><?= $n ?></b></a><?php endforeach; ?>
    <span class="badge rounded-pill text-bg-success">Ukupno <?= count($lista) ?></span>
</div>
<?php endif; ?>
<?php if (!$lista): ?>
    <div class="alert alert-secondary">Nema upisanog odstrela za <?= e(odstrel_lg_naziv($g)) ?><?= $vrstaF || $trazi ? ' (uz ovaj filtar)' : '' ?>.</div>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle odstrel-tablica">
    <thead><tr>
        <th><a class="text-decoration-none" href="<?= e($filtar(['sort' => 'datum'])) ?>">Datum<?= $sort === 'datum' ? ' ▼' : '' ?></a></th>
        <th><a class="text-decoration-none" href="<?= e($filtar(['sort' => 'markica'])) ?>">Markica<?= $sort === 'markica' ? ' ▲' : '' ?></a></th>
        <th>Divljač</th><th class="text-end d-none d-md-table-cell">Masa</th><th>Lovac</th><th class="d-none d-md-table-cell">Mjesto</th><?= $viseSekcija ? '<th class="d-none d-lg-table-cell">Sekcija</th>' : '' ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $o): $sid = $o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null; $n = $o['NapravaId'] ? ($naprave[(int) $o['NapravaId']] ?? null) : null; ?>
        <tr>
            <td class="text-nowrap"><?= e(datum($o['Datum'])) ?><?= $o['Vrijeme'] ? '<div class="small text-muted">' . e($o['Vrijeme']) . '</div>' : '' ?></td>
            <td class="fw-semibold"><?= e((string) $o['Markica']) ?: '<span class="text-muted small">–</span>' ?>
                <?php if ($sle && !$samoMoje): ?><div><?= $o['SleDatum'] ? '<span class="badge text-bg-success" title="Upisano u SLE ' . e(datum($o['SleDatum'])) . '">✓ SLE</span>'
                    : '<span class="badge text-bg-' . ($o['Datum'] < date('Y-m-d', strtotime('-7 days')) ? 'danger' : 'warning') . '">✗ SLE</span>' ?></div><?php endif; ?>
                <?php foreach (['dopustenje' => ['BrojDopustenja', 'dop.'], 'zapisnik' => ['BrojZapisnika', 'zap.'], 'potvrda' => ['BrojPotvrde', 'potv.']] as $pk => [$st, $kr]): if (in_array($pk, $polja, true) && $o[$st]): ?>
                    <div class="small text-muted fw-normal"><?= $kr ?> <?= e($o[$st]) ?></div><?php endif; endforeach; ?></td>
            <td><b><?= e($o['Vrsta']) ?></b><?php if ($op = odstrel_opis($o)): ?><div class="small text-muted"><?= e($op) ?></div><?php endif; ?>
                <?= $o['Masa'] !== null ? '<div class="small d-md-none">' . e(broj((float) $o['Masa'], 1)) . ' kg</div>' : '' ?>
                <?= $o['Trofej'] ? '<div class="small">🏆 ' . e(broj((float) $o['Trofej'], 2)) . ' CIC</div>' : '' ?></td>
            <td class="text-end text-nowrap d-none d-md-table-cell"><?= $o['Masa'] !== null ? e(broj((float) $o['Masa'], 1)) . ' kg' : '' ?></td>
            <td><?= e($o['Lovac']) ?><?= $o['Gost'] ? ' <span class="badge text-bg-secondary">gost</span>' : '' ?>
                <?= $o['Oruzje'] || $o['Kalibar'] ? '<div class="small text-muted">' . e(trim($o['Oruzje'] . ' ' . $o['Kalibar'])) . ($o['Udaljenost'] ? ' · ' . (int) $o['Udaljenost'] . ' m' : '') . '</div>' : '' ?></td>
            <td class="small d-none d-md-table-cell"><?= $n ? '<a href="' . e(url('revir', ['naprava' => $n['Id']])) . '">' . e(($n['Broj'] ? $n['Broj'] . ' · ' : '') . $n['Naziv']) . '</a>' : e((string) $o['Mjesto']) ?></td>
            <?= $viseSekcija ? '<td class="small d-none d-lg-table-cell">' . e(odstrel_sekcija_naziv($sid)) . '</td>' : '' ?>
            <td class="text-end text-nowrap">
                <?php if ($o['Foto']): ?><a href="<?= e(foto_url($o['Foto'])) ?>" target="_blank" title="Fotografija">📷</a><?php endif; ?>
                <?php if ($sle && !$o['SleDatum'] && odstrel_moze_urediti($sid)): ?><form method="post" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="sle"><input type="hidden" name="id" value="<?= (int) $o['Id'] ?>">
                    <input type="hidden" name="s" value="<?= e($sF) ?>"><input type="hidden" name="sle" value="<?= e($sleF) ?>">
                    <button class="btn btn-sm btn-outline-success" title="Označi: upisano u SLE (danas)" data-potvrda="Označiti da je ovaj odstrel upisan u SLE?">✓ SLE</button></form><?php endif; ?>
                <?php if (odstrel_moze_urediti($sid)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('odstrel/uredi', ['id' => $o['Id']])) ?>" aria-label="Uredi">✏️</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php endif; ?>
<?php if ($samoMoje): ?><p class="small text-muted">Vidite samo svoj odstrel. Odstrel upisuje lovnik ili tko ima pravo „Odstrel – upis“.</p><?php endif; ?>
<p class="small text-muted mt-3 border-top pt-2">ℹ️ <?= e(ODSTREL_SLE_NAPOMENA) ?></p>
<?php stranica('Odstrel', ob_get_clean());
