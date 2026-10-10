<?php
/** Lovački dnevnik: automatski upisi (tko, kada, gdje). Gosti se ne upisuju. */
$k = korisnik();
revir_zatvori_istekle();
$f = dez_filtar($_GET) + ['ClanId' => null];
if (!isset($_GET['Razdoblje'])) {
    $f['Razdoblje'] = 'LovnaGodina';
}
[$od, $do] = raspon($f);

// Opseg: uloge za sve sekcije vide sve; nadzor vidi svoje sekcije; ostali samo svoje upise.
$sekcijeOpseg = [];
if ($k['SveSekcije']) {
    $uvjet = '1=1';
    foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
        $sekcijeOpseg[(int) $s['Id']] = $s['Naziv'];
    }
} elseif (ima(P_REVIR_NADZOR) && $k['Sekcije']) {
    $uvjet = '(' . revir_nadzor_sql('d.SekcijaId') . ($k['ClanId'] ? ' OR d.ClanId=' . (int) $k['ClanId'] : '') . ')';
    foreach ($k['Sekcije'] as $sid) {
        $sekcijeOpseg[$sid] = naziv_sekcije($sid);
    }
} else {
    $uvjet = 'd.ClanId=' . (int) ($k['ClanId'] ?? 0);
}
$samoJa = $uvjet === 'd.ClanId=' . (int) ($k['ClanId'] ?? 0);

$sekcijaF = $f['SekcijaId'] !== null && isset($sekcijeOpseg[$f['SekcijaId']]) ? $f['SekcijaId'] : null;
$w = [$uvjet, 'substr(d.Od,1,10) BETWEEN ? AND ?'];
$p = [$od, $do];
if ($sekcijaF !== null) {
    $w[] = 'd.SekcijaId=?';
    $p[] = $sekcijaF;
}
$upisi = array_map(fn($u) => $u + ['Aktivnost' => 'Lov', 'Mjesto' => $u['Naprava'], 'Izvor' => 'lov', 'Napomena' => ''],
    redovi('SELECT d.* FROM LovackiDnevnik d WHERE ' . implode(' AND ', $w) . ' ORDER BY d.Od DESC LIMIT 5000', $p));
// posjeti iz knjige dezinfekcije (radna akcija, hranjenje… – lov dolazi iz zauzeća naprava)
$upisi = array_merge($upisi, revir_dnevnik_dez($uvjet, $od, $do, $sekcijaF));

$aktivnosti = ['Lov' => 'Lov'];
foreach (dez_razlozi() as $dr) {
    if (!revir_je_lov($dr['Naziv'])) {
        $aktivnosti[$dr['Naziv']] = $dr['Naziv'];
    }
}
foreach ($upisi as $u) {
    $aktivnosti[$u['Aktivnost']] ??= $u['Aktivnost'];
}
$aktivnost = (string) ($_GET['Aktivnost'] ?? '');
if ($aktivnost !== '' && isset($aktivnosti[$aktivnost])) {
    $upisi = array_values(array_filter($upisi, fn($u) => $u['Aktivnost'] === $aktivnost));
} else {
    $aktivnost = '';
}
if ($f['Trazi'] !== '') {
    $t = kljuc($f['Trazi']);
    $upisi = array_values(array_filter($upisi, fn($u) => str_contains(kljuc($u['Ime']), $t) || str_contains(kljuc((string) $u['Mjesto']), $t)));
}
usort($upisi, fn($a, $b) => strcmp($b['Od'], $a['Od']));
$minuta = fn(array $u): ?float => $u['Do'] ? (strtotime($u['Do']) - strtotime($u['Od'])) / 60 : null;
$ukupnoMin = array_sum(array_map(fn($u) => $minuta($u) ?? 0, $upisi));
$poAktivnosti = [];
foreach ($upisi as $u) {
    $poAktivnosti[$u['Aktivnost']] = ($poAktivnosti[$u['Aktivnost']] ?? 0) + 1;
}
$trajanje = function (int|float $min): string {
    $min = (int) round($min);
    return intdiv($min, 60) . ' h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
};
$upit = dez_filtar_upit($f) + ($aktivnost !== '' ? ['Aktivnost' => $aktivnost] : []);

if (($_GET['izvoz'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lovacki-dnevnik-' . $od . '-' . $do . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Datum', 'Od', 'Do', 'Trajanje (min)', 'Lovac', 'Aktivnost', 'Mjesto', 'Sekcija', 'Napomena'], ';');
    foreach ($upisi as $u) {
        $m = $minuta($u);
        fputcsv($o, [datum($u['Od']), date('H:i', strtotime($u['Od'])), $u['Do'] ? datum_vrijeme($u['Do']) : '', $m !== null ? (int) round($m) : '',
            $u['Ime'], $u['Aktivnost'], $u['Mjesto'], naziv_sekcije($u['SekcijaId'] !== null ? (int) $u['SekcijaId'] : null),
            trim(($u['Automatski'] ? 'automatski oslobođeno' : '') . ' ' . ($u['Do'] ? '' : 'bez odlaska') . ' ' . $u['Napomena'])], ';');
    }
    exit;
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">Lovački dnevnik</h1>
    <a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('revir')) ?>">Karta lovišta</a>
</div>
<p class="text-muted small">Upisuje se automatski: <b>lov</b> kad oslobodite lovnu napravu (ili u <?= sprintf('%02d:00', revir_sat_isteka()) ?> ujutro),
    <b>ostalo</b> (radna akcija, hranjenje…) iz knjige dezinfekcije – dolazak do odlaska. Gosti se ne upisuju.
    <?= $samoJa ? 'Vidite samo svoje upise.' : '' ?></p>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="revir/dnevnik">
    <div class="row g-2">
        <div class="col-6 col-md-3"><label class="form-label small">Razdoblje</label>
            <select name="Razdoblje" class="form-select form-select-sm" data-auto>
                <?php foreach (RAZDOBLJA as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['Razdoblje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <?php if ($f['Razdoblje'] === 'Mjeseci'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od mjeseca</label><input type="month" name="Od" class="form-control form-control-sm" value="<?= e(substr($f['Od'] ?? date('Y-m'), 0, 7)) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do mjeseca</label><input type="month" name="Do" class="form-control form-control-sm" value="<?= e(substr($f['Do'] ?? date('Y-m'), 0, 7)) ?>"></div>
        <?php elseif ($f['Razdoblje'] === 'Slobodno'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>"></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small">Aktivnost</label>
            <select name="Aktivnost" class="form-select form-select-sm" data-auto><option value="">Sve</option>
                <?php foreach ($aktivnosti as $a): ?><option<?= sel($a, $aktivnost) ?>><?= e($a) ?></option><?php endforeach; ?>
            </select></div>
        <?php if (count($sekcijeOpseg) > 1): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Sekcija</label>
                <select name="SekcijaId" class="form-select form-select-sm"><option value="">Sve sekcije</option>
                    <?php foreach ($sekcijeOpseg as $sid => $n): ?><option value="<?= $sid ?>"<?= sel($sid, $f['SekcijaId']) ?>><?= e($n) ?></option><?php endforeach; ?>
                </select></div>
        <?php endif; ?>
        <?php if (!$samoJa): ?><div class="col-6 col-md-2"><label class="form-label small">Lovac ili mjesto</label><input name="Trazi" class="form-control form-control-sm" value="<?= e($f['Trazi']) ?>"></div><?php endif; ?>
        <div class="col-6 col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir/dnevnik', $upit + ['izvoz' => 'csv'])) ?>">Excel (CSV)</a>
    </div>
</form>
<div class="d-flex gap-4 mb-2">
    <div><div class="kartica-broj"><?= count($upisi) ?></div><div class="small text-muted">izlazaka</div></div>
    <div><div class="kartica-broj"><?= e($trajanje($ukupnoMin)) ?></div><div class="small text-muted">ukupno</div></div>
</div>
<?php if (count($poAktivnosti) > 1): ?><div class="small mb-2"><?php foreach ($poAktivnosti as $a => $n): ?><span class="badge text-bg-light border me-1"><?= e($a) ?>: <?= $n ?></span><?php endforeach; ?></div><?php endif; ?>
<div class="small text-muted mb-2">Razdoblje: <?= e(opis_raspona($f)) ?></div>
<?php if (!$upisi): ?><div class="alert alert-secondary">Nema upisa u ovom razdoblju.</div><?php else: ?>
<div class="table-responsive"><table class="table table-sm table-striped align-middle">
    <thead><tr><th>Datum</th><th>Od – do</th><th>Trajanje</th><?php if (!$samoJa): ?><th>Lovac</th><?php endif; ?><th>Aktivnost</th><th>Mjesto</th><?php if (count($sekcijeOpseg) > 1): ?><th>Sekcija</th><?php endif; ?></tr></thead>
    <tbody><?php foreach ($upisi as $u): $min = $minuta($u); ?>
        <tr><td class="text-nowrap"><?= e(datum($u['Od'])) ?></td>
            <td class="text-nowrap"><?= e(date('H:i', strtotime($u['Od']))) ?> –
                <?php if ($u['Do']): ?><?= e(substr($u['Do'], 0, 10) !== substr($u['Od'], 0, 10) ? date('d.m. H:i', strtotime($u['Do'])) : date('H:i', strtotime($u['Do']))) ?><?php else: ?><span class="text-muted" title="U knjizi dezinfekcije nema odlaska">?</span><?php endif; ?>
                <?= $u['Automatski'] ? '<span class="badge bg-secondary-subtle text-secondary-emphasis" title="Nije dodirnuo „Odlazim“ – automatski oslobođeno">auto</span>' : '' ?></td>
            <td class="text-nowrap small"><?= $min !== null ? e($trajanje($min)) : '' ?></td>
            <?php if (!$samoJa): ?><td><?= e($u['Ime']) ?></td><?php endif; ?>
            <td><span class="badge <?= $u['Izvor'] === 'lov' ? 'bg-success' : 'bg-secondary' ?>"><?= e($u['Aktivnost']) ?></span><?= $u['Napomena'] !== '' ? '<div class="small text-muted">' . e($u['Napomena']) . '</div>' : '' ?></td>
            <td><?php if ($u['NapravaId']): ?><a href="<?= e(url('revir', ['naprava' => $u['NapravaId']])) ?>" class="text-decoration-none"><?= e($u['Mjesto']) ?></a><?php else: ?><span class="small"><?= $u['Izvor'] === 'dez' ? '🧴 ' : '' ?><?= e($u['Mjesto']) ?></span><?php endif; ?></td>
            <?php if (count($sekcijeOpseg) > 1): ?><td class="small"><?= e(naziv_sekcije($u['SekcijaId'] !== null ? (int) $u['SekcijaId'] : null)) ?></td><?php endif; ?></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php stranica('Lovački dnevnik', ob_get_clean());
