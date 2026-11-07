<?php
/** 📚 Arhiva bodova: svi članovi za odabranu bodovnu godinu ili jedan član kroz sve godine (preneseno / funkcije / radne akcije). PDF. */
$k = korisnik();
$cid = (int) ($_GET['clan'] ?? 0);
if (!ima(P_AKCIJE_CITAJ)) {
    // član bez prava vidi samo svoje bodove
    if (!$k['ClanId']) {
        zabranjeno();
    }
    $cid = (int) $k['ClanId'];
}
$godine = bodovne_godine_popis();
$stupci = ['Preneseno' => '↪️ Preneseno', 'Funkcije' => '🏅 Funkcije', 'Akcije' => '🔨 Radne akcije', 'Ukupno' => 'Ukupno'];

if ($cid) {
    $c = clan_ili_kraj($cid);
    $naslov = 'Bodovi po godinama – ' . puno_ime($c);
    $redovi = [];
    foreach ($godine as $g) {
        $r = bodovi_arhiva($g, 'c.Id=?', [$cid])[0] ?? null;
        $redovi[] = ['Naziv' => bodovna_godina_naziv($g), 'g' => $g] + ($r ?: ['Preneseno' => 0, 'Funkcije' => 0, 'Akcije' => 0, 'Ukupno' => 0]);
    }
    $prvi = 'Bodovna godina';
} else {
    $g = (int) ($_GET['g'] ?? bodovna_godina());
    if (!in_array($g, $godine, true)) {
        $g = bodovna_godina();
    }
    $sid = (int) ($_GET['s'] ?? 0);
    $uvjet = opseg_sql('c') . ($sid ? ' AND c.SekcijaId=' . $sid : '');
    $redovi = array_map(fn($r) => $r + ['Naziv' => prezime_ime($r)], bodovi_arhiva($g, $uvjet));
    [$od, $do] = bodovna_godina_raspon($g);
    $naslov = 'Bodovi ' . bodovna_godina_naziv($g) . ($sid ? ' – ' . (vrijednost('SELECT Naziv FROM Sekcije WHERE Id=?', [$sid]) ?: '') : '');
    $prvi = 'Član';
}
$zbroj = [];
foreach (array_keys($stupci) as $s) {
    $zbroj[$s] = array_sum(array_map(fn($r) => (float) $r[$s], $redovi));
}

if (($_GET['pdf'] ?? '') === '1') {
    $t = '<h2>' . e($naslov) . '</h2>' . (!$cid ? '<div style="color:#666;margin-bottom:6px">' . e(datum($od)) . ' – ' . e(datum($do)) . '</div>' : '')
        . '<table class="t"><thead><tr><th>' . e($prvi) . '</th>' . (!$cid ? '<th>Sekcija</th>' : '');
    foreach ($stupci as $n) {
        $t .= '<th style="text-align:right">' . e(trim(preg_replace('/^\S+\s/u', '', $n))) . '</th>';
    }
    $t .= '</tr></thead><tbody>';
    foreach ($redovi as $r) {
        $t .= '<tr><td>' . e($r['Naziv']) . '</td>' . (!$cid ? '<td>' . e($r['Sekcija'] ?? '') . '</td>' : '');
        foreach (array_keys($stupci) as $s) {
            $t .= '<td class="d"' . ($s === 'Ukupno' ? ' style="font-weight:bold"' : '') . '>' . e(broj((float) $r[$s], 2)) . '</td>';
        }
        $t .= '</tr>';
    }
    if (!$cid) {
        $t .= '<tr class="uk"><td colspan="2"><b>Ukupno (' . count($redovi) . ' članova)</b></td>';
        foreach (array_keys($stupci) as $s) {
            $t .= '<td class="d"><b>' . e(broj($zbroj[$s], 2)) . '</b></td>';
        }
        $t .= '</tr>';
    }
    $html = '<html><head><meta charset="utf-8"><style>@page { margin: 18mm 14mm 16mm 14mm; } body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; } .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; }
        h2 { font-size: 12pt; margin: 4px 0 4px; } table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #bbb; padding: 3px 4px; } .t td.d { text-align: right; white-space: nowrap; } .uk td { background: #f4f6f2; }
        .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }</style></head><body>
        <div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
        <div class="zag"><div class="n">' . e(udruga_naziv()) . '</div>' . (udruga_adresa() ? '<div style="color:#555">' . e(udruga_adresa()) . '</div>' : '') . '</div>' . $t . '</tbody></table></body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->getCanvas()->page_text(520, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="bodovi-' . ($cid ? 'clan-' . $cid : $g) . '.pdf"');
    echo $pdf->output();
    exit;
}

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto">📚 <?= e($naslov) ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('akcije/bodovi', array_filter(['clan' => $cid ?: null, 'g' => $cid ? null : $g, 's' => $cid ? null : ($sid ?: null), 'pdf' => 1]))) ?>" target="_blank">📄 PDF</a>
    <?php if (ima(P_SUSTAV)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('sustav/bodovi')) ?>">⚙️ Postavke</a><?php endif; ?>
</div>
<?php if ($cid): ?>
    <p class="text-muted"><?php if (ima(P_AKCIJE_CITAJ)): ?><a href="<?= e(url('akcije/bodovi')) ?>">← svi članovi</a> · <?php endif; ?>Bodovna godina počinje <?= e(bodovi_dan_prikaz(bodovi_dan())) ?></p>
<?php else: ?>
    <form method="get" action="<?= e(url()) ?>" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="p" value="akcije/bodovi">
        <select name="g" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <?php foreach ($godine as $x): ?><option value="<?= $x ?>"<?= sel($x, $g) ?>><?= e(bodovna_godina_naziv($x)) ?><?= $x === bodovna_godina() ? ' (tekuća)' : '' ?></option><?php endforeach; ?>
        </select>
        <select name="s" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="0">sve sekcije</option>
            <?php foreach (moje_sekcije() as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $sid) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
        </select>
        <span class="small text-muted align-self-center"><?= e(datum($od)) ?> – <?= e(datum($do)) ?> · klik na člana = sve njegove godine</span>
    </form>
<?php endif; ?>
<div class="table-responsive" style="max-width:860px"><table class="table table-sm table-hover align-middle">
    <thead><tr><th><?= e($prvi) ?></th><?php if (!$cid): ?><th class="d-none d-md-table-cell">Sekcija</th><?php endif; ?>
        <?php foreach ($stupci as $s => $n): ?><th class="text-end<?= $s === 'Ukupno' ? '' : ' d-none d-sm-table-cell' ?>"><?= e($n) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($redovi as $r): ?>
        <tr<?= !$cid && (int) $r['Status'] !== 0 ? ' class="text-muted"' : '' ?>>
            <td><?php if ($cid): ?><a href="<?= e(url(ima(P_AKCIJE_CITAJ) ? 'akcije/bodovi' : 'moje-akcije', ima(P_AKCIJE_CITAJ) ? ['g' => $r['g']] : [])) ?>"><?= e($r['Naziv']) ?></a>
                <?php else: ?><a href="<?= e(url('akcije/bodovi', ['clan' => $r['Id']])) ?>"><?= e($r['Naziv']) ?></a><?= (int) $r['Status'] !== 0 ? ' <span class="small">(arhiviran)</span>' : '' ?><?php endif; ?></td>
            <?php if (!$cid): ?><td class="d-none d-md-table-cell small"><?= e($r['Sekcija'] ?? '') ?></td><?php endif; ?>
            <?php foreach (array_keys($stupci) as $s): ?><td class="text-end<?= $s === 'Ukupno' ? ' fw-semibold' : ' d-none d-sm-table-cell' ?>"><?= (float) $r[$s] ? e(broj((float) $r[$s], 2)) : '<span class="text-muted">0</span>' ?></td><?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$redovi): ?><tr><td colspan="6" class="text-muted">Nema bodova.</td></tr><?php endif; ?>
    </tbody>
    <?php if (!$cid && $redovi): ?><tfoot><tr class="fw-semibold"><td>Ukupno (<?= count($redovi) ?>)</td><td class="d-none d-md-table-cell"></td>
        <?php foreach (array_keys($stupci) as $s): ?><td class="text-end<?= $s === 'Ukupno' ? '' : ' d-none d-sm-table-cell' ?>"><?= e(broj($zbroj[$s], 2)) ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table></div>
<?php stranica('Arhiva bodova', ob_get_clean());
