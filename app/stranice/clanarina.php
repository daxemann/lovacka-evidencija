<?php
/** Pregled članarine za godinu: skupno zaduženje, uplate, info članovima. */
trazi(P_CLANARINA_CITAJ);
$godina = (int) ($_GET['godina'] ?? date('Y'));
$filter = (string) ($_GET['filter'] ?? 'sve');
$moze = ima(P_CLANARINA_UREDI);
$op = opseg_sql('c');

if (je_post() && $moze) {
    if (($_POST['radnja'] ?? '') === 'skupno') {
        $izn = u_broj($_POST['iznos'] ?? '');
        if ($izn !== null && $izn > 0) {
            $osl = oslobodjeni_clanarine($godina);
            $bez = redovi("SELECT c.Id FROM Clanovi c WHERE c.Status=0 AND $op AND NOT EXISTS (SELECT 1 FROM Clanarine x WHERE x.ClanId=c.Id AND x.Godina=?)", [$godina]);
            $n = 0;
            transakcija(function () use ($bez, $osl, $godina, $izn, &$n) {
                foreach ($bez as $c) {
                    if (!isset($osl[(int) $c['Id']])) {
                        umetni('Clanarine', ['ClanId' => $c['Id'], 'Godina' => $godina, 'Iznos' => dec($izn), 'Valuta' => 'EUR', 'Napomena' => null]);
                        $n++;
                    }
                }
            });
            dnevnik('Skupno zaduženje članarine', null, null, "$godina: $n × " . novac($izn) . ' €');
            poruka("Zaduženo $n članova za $godina.");
        }
    } elseif (isset($_POST['placeno'])) {
        $cl = red("SELECT x.*, c.Ime, c.Prezime FROM Clanarine x JOIN Clanovi c ON c.Id=x.ClanId WHERE x.Id=? AND $op", [(int) $_POST['placeno']]);
        if ($cl) {
            $c2 = clanarina_clana((int) $cl['ClanId'], (int) $cl['Godina']);
            if ($c2['Preostalo'] > 0) {
                umetni('Uplate', ['ClanarinaId' => $cl['Id'], 'Datum' => danas(), 'Iznos' => dec($c2['Preostalo']), 'Napomena' => null]);
                dnevnik('Uplata članarine', 'Clan', (int) $cl['ClanId'], "$godina: " . novac($c2['Preostalo']));
            }
        }
    }
    preusmjeri('clanarina', ['godina' => $godina, 'filter' => $filter]);
}

$osl = oslobodjeni_clanarine($godina);
$clanovi = redovi("SELECT c.Id, c.Ime, c.Prezime, s.Naziv AS Sekcija FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
    WHERE $op AND (c.Status=0 OR EXISTS (SELECT 1 FROM Clanarine x WHERE x.ClanId=c.Id AND x.Godina=?))", [$godina]);
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
$retci = [];
foreach ($clanovi as $c) {
    $cl = clanarina_clana((int) $c['Id'], $godina);
    $o = isset($osl[(int) $c['Id']]);
    $retci[] = ['c' => $c, 'cl' => $cl, 'osl' => $o, 'placa' => $cl && !$o];
}
$retci = array_values(array_filter($retci, fn($r) => match ($filter) {
    'neplaceno' => $r['placa'] && $r['cl']['Preostalo'] > 0.004,
    'placeno' => $r['placa'] && $r['cl']['Preostalo'] <= 0.004,
    'bez' => !$r['cl'] && !$r['osl'],
    'oslobodjeni' => $r['osl'],
    default => true,
}));
$sviZaSkupno = count(array_filter($retci, fn($r) => !$r['cl'] && !$r['osl']));
$god = array_map('intval', array_column(redovi('SELECT DISTINCT Godina FROM Clanarine ORDER BY Godina DESC'), 'Godina'));
$god = array_unique(array_merge([(int) date('Y') + 1, (int) date('Y')], $god));
rsort($god);
$zadnjiIznos = vrijednost('SELECT Iznos FROM Clanarine WHERE Godina<=? GROUP BY Godina, Iznos ORDER BY Godina DESC, COUNT(*) DESC LIMIT 1', [$godina]);
$placaju = array_filter($retci, fn($r) => $r['placa']);
ob_start(); ?>
<form method="get" action="<?= e(url()) ?>" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input type="hidden" name="p" value="clanarina">
    <h1 class="h3 mb-0 me-auto">Članarina</h1>
    <select name="godina" class="form-select no-print" style="width:auto" data-auto><?php foreach ($god as $g): ?><option<?= sel($g, $godina) ?>><?= $g ?></option><?php endforeach; ?></select>
    <select name="filter" class="form-select no-print" style="width:auto" data-auto>
        <option value="sve"<?= sel('sve', $filter) ?>>Svi</option>
        <option value="neplaceno"<?= sel('neplaceno', $filter) ?>>Nije plaćeno / djelomično</option>
        <option value="placeno"<?= sel('placeno', $filter) ?>>Plaćeno</option>
        <option value="bez"<?= sel('bez', $filter) ?>>Bez zaduženja</option>
        <option value="oslobodjeni"<?= sel('oslobodjeni', $filter) ?>>Oslobođeni (počasni članovi)</option>
    </select>
    <button type="button" class="btn btn-outline-secondary no-print" onclick="window.print()">Ispis</button>
</form>
<?php if ($moze && $filter === 'sve' || $moze && $filter === 'bez'): ?>
<div class="card mb-3 no-print"><div class="card-body">
    <form method="post" action="<?= e(url('clanarina', ['godina' => $godina, 'filter' => $filter])) ?>" class="row g-2 align-items-end"><?= csrf() ?>
        <input type="hidden" name="radnja" value="skupno">
        <div class="col-md-4"><label class="form-label">Zaduži sve aktivne članove bez zaduženja za <?= $godina ?>. (počasni članovi se preskaču) – iznos (EUR)</label>
            <input name="iznos" type="number" step="0.01" min="0" class="form-control" value="<?= $zadnjiIznos !== null ? e(number_format((float) $zadnjiIznos, 2, '.', '')) : '' ?>"></div>
        <div class="col-md-3"><button class="btn btn-primary w-100" <?= $sviZaSkupno ? '' : 'disabled' ?> data-potvrda="Zadužiti <?= $sviZaSkupno ?> članova?">Zaduži (<?= $sviZaSkupno ?> članova)</button></div>
    </form>
</div></div>
<?php endif; ?>
<div class="d-flex gap-4 mb-2">
    <span>Zaduženo: <b><?= novac(array_sum(array_map(fn($r) => (float) $r['cl']['Iznos'], $placaju))) ?> €</b></span>
    <span>Uplaćeno: <b><?= novac(array_sum(array_map(fn($r) => $r['cl']['Uplaceno'], $placaju))) ?> €</b></span>
    <span>Preostalo: <b class="text-danger"><?= novac(array_sum(array_map(fn($r) => max(0, $r['cl']['Preostalo']), $placaju))) ?> €</b></span>
</div>
<form method="post" action="<?= e(url('clanarina', ['godina' => $godina, 'filter' => $filter])) ?>"><?= csrf() ?>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Član</th><th>Sekcija</th><th class="text-end">Iznos</th><th class="text-end">Uplaćeno</th><th>Zadnja uplata</th><th>Status</th><th class="no-print"></th></tr></thead>
    <tbody>
    <?php foreach ($retci as $r): $c = $r['c']; $cl = $r['cl']; ?>
        <tr>
            <td><a href="<?= e(url('clanovi/uredi', ['id' => $c['Id'], 'kartica' => 'clanarina'])) ?>"><?= e(prezime_ime($c)) ?></a></td>
            <td><?= e($c['Sekcija'] ?? '—') ?></td>
            <td class="text-end"><?= $cl ? novac($cl['Iznos']) : '' ?></td>
            <td class="text-end"><?= $cl ? novac($cl['Uplaceno']) : '' ?></td>
            <td><?= $cl && $cl['Uplate'] ? e(datum(end($cl['Uplate'])['Datum'])) : '' ?></td>
            <td><?php if ($r['osl']): ?><span class="badge bg-info text-dark">Oslobođen – počasni član</span>
                <?php elseif (!$cl): ?><span class="text-muted">bez zaduženja</span>
                <?php elseif ($cl['Preostalo'] <= 0.004): ?><span class="badge bg-success">Plaćeno</span>
                <?php elseif ($cl['Uplaceno'] > 0): ?><span class="badge bg-warning text-dark">Djelomično</span>
                <?php else: ?><span class="badge bg-danger">Nije plaćeno</span><?php endif; ?></td>
            <td class="no-print text-end text-nowrap">
                <?php if ($r['placa'] && $cl['Preostalo'] > 0.004): ?>
                    <a class="btn btn-sm btn-outline-secondary me-1" href="<?= e(url('clanovi/uredi', ['id' => $c['Id'], 'posalji' => 'clanarina', 'godina' => $godina])) ?>">Info članu</a>
                    <?php if ($moze): ?><button class="btn btn-sm btn-outline-success" name="placeno" value="<?= (int) $cl['Id'] ?>">Plaćeno danas (<?= novac($cl['Preostalo']) ?>)</button><?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$retci): ?><tr><td colspan="7" class="text-muted text-center py-4">Nema zapisa.</td></tr><?php endif; ?>
    </tbody>
</table></div>
</form>
<?php stranica('Članarina', ob_get_clean());
