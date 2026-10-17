<?php
/** PDF popis kvarova na lovnim napravama (za obilazak / radnu akciju). status=aktivni|rijeseno|sve, s=sekcija. */
$sekcije = revir_sekcije();
if (!$sekcije) {
    zabranjeno();
}
$statusF = (string) ($_GET['status'] ?? 'aktivni');
if (!in_array($statusF, ['aktivni', 'rijeseno', 'sve'], true)) {
    $statusF = 'aktivni';
}
$sF = (string) ($_GET['s'] ?? '');
$opseg = ($sF !== '' && array_key_exists((int) $sF, $sekcije)) ? [(int) $sF => $sekcije[(int) $sF]] : $sekcije;
$w = [revir_kvar_sql($opseg)];
if ($statusF === 'aktivni') {
    $w[] = "Status<>'rijeseno'";
} elseif ($statusF === 'rijeseno') {
    $w[] = "Status='rijeseno'";
}
$kvarovi = redovi('SELECT * FROM RevirKvarovi WHERE ' . implode(' AND ', $w)
    . ' ORDER BY SekcijaId, Nesigurno DESC, CAST(Naprava AS INTEGER), Naprava, Prijavljeno LIMIT 1000');
$naslovOpseg = count($opseg) === 1 ? reset($opseg) : 'sve sekcije';
$naslov = ['aktivni' => 'Otvoreni kvarovi', 'rijeseno' => 'Riješeni kvarovi', 'sve' => 'Kvarovi'][$statusF];

$logo = '';
if (ima_logo()) {
    $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
    $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
    $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
}
$redovi = '';
foreach ($kvarovi as $i => $kv) {
    $status = KVAR_STATUSI[$kv['Status']] ?? $kv['Status'];
    if ($kv['Status'] === 'u_radu' && $kv['PreuzeoIme']) {
        $status .= '<br><small>' . e($kv['PreuzeoIme']) . '</small>';
    } elseif ($kv['Status'] === 'rijeseno') {
        $status .= '<br><small>' . e($kv['RijesioIme']) . ', ' . e(datum($kv['Rijeseno'])) . '</small>';
    }
    $redovi .= '<tr' . ($kv['Nesigurno'] && $kv['Status'] !== 'rijeseno' ? ' class="nes"' : '') . '><td>' . ($i + 1) . '.</td>'
        . '<td><b>' . e($kv['Naprava']) . '</b>' . (count($opseg) > 1 ? '<br><small>' . e(naziv_sekcije(revir_kvar_sid($kv))) . '</small>' : '') . '</td>'
        . '<td>' . ($kv['Nesigurno'] && $kv['Status'] !== 'rijeseno' ? '<b>NESIGURNO</b> – ' : '') . nl2br(e($kv['Opis']))
        . ($kv['Napomena'] ? '<br><small>Napomena: ' . e($kv['Napomena']) . '</small>' : '') . '</td>'
        . '<td>' . e($kv['PrijavioIme']) . '<br><small>' . e(datum($kv['Prijavljeno'])) . '</small></td>'
        . '<td>' . $status . '</td>'
        . ($statusF !== 'rijeseno' ? '<td class="upis"></td>' : '') . '</tr>';
}
$html = '<html><head><meta charset="utf-8"><style>
    @page { margin: 16mm 14mm 16mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
    .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
    .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
    table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
    .t th, .t td { border: 0.5pt solid #999; padding: 4px; vertical-align: top; } .t small { color: #666; }
    .t tr.nes td { background: #fdecea; } .t td.upis { background: #fbfbf8; }
    .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
</style></head><body>
<div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
<div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">' . e($naslov) . ' na lovnim napravama · ' . e($naslovOpseg) . ' · ' . count($kvarovi) . '</div></div>
<table class="t"><thead><tr><th style="width:7mm">Rb.</th><th style="width:38mm">Naprava</th><th>Kvar</th><th style="width:30mm">Prijavio</th><th style="width:24mm">Status</th>'
    . ($statusF !== 'rijeseno' ? '<th style="width:30mm">Popravljeno (datum, potpis)</th>' : '') . '</tr></thead><tbody>'
    . ($redovi ?: '<tr><td colspan="6" style="color:#888">Nema kvarova.</td></tr>') . '</tbody></table></body></html>';

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
header('Content-Disposition: inline; filename="kvarovi-' . trim(preg_replace('/[^a-z0-9]+/', '-', kljuc($naslovOpseg)), '-') . '-' . date('Y-m-d') . '.pdf"');
echo $pdf->output();
