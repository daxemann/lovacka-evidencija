<?php
/** PDF plan zauzeća lovačkog doma (od danas, najviše 12 mjeseci). Ostali vide „zauzeto · ime“, upravitelj i napomene. */
$d = dom_dom((int) ($_GET['id'] ?? 0));
if (!$d) {
    nije_pronadjeno('Lovački dom');
}
$upr = dom_upravlja($d);
$lista = dom_popis((int) $d['Id'], danas(), dom_najkasnije());

$logo = '';
if (ima_logo()) {
    $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
    $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
    $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
}
$redovi = '';
foreach ($lista as $i => $r) {
    $udr = $r['Vrsta'] === DOM_VRSTA_UDRUGA;
    $redovi .= '<tr class="' . ($udr ? 'udr' : $r['Status']) . '"><td>' . ($i + 1) . '.</td>'
        . '<td>' . e(dom_razdoblje($r)) . '</td><td style="text-align:center">' . dom_broj_dana($r) . '</td>'
        . '<td>' . ($udr ? 'Udruga' : 'Privatno') . '</td>'
        . '<td>' . ($udr ? '<b>' . e($r['Naslov']) . '</b>' : e($r['Ime']))
        . ($upr && $r['Napomena'] ? '<br><small>' . e($r['Napomena']) . '</small>' : '') . '</td>'
        . '<td>' . e(DOM_STATUSI[$r['Status']] ?? $r['Status']) . '</td></tr>';
}
$podaci = array_filter([dom_opseg_naziv($d), $d['Kapacitet'] ? 'do ' . (int) $d['Kapacitet'] . ' osoba' : '', $d['Kontakt'] ? 'ključ/kontakt: ' . $d['Kontakt'] : '',
    $d['Lat'] !== null ? 'GPS ' . $d['Lat'] . ', ' . $d['Lon'] : '']);
$html = '<html><head><meta charset="utf-8"><style>
    @page { margin: 16mm 14mm 16mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
    .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
    .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
    table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
    .t th, .t td { border: 0.5pt solid #999; padding: 4px; vertical-align: top; } .t small { color: #666; }
    .t tr.udr td { background: #e7f0fb; } .t tr.ceka td { background: #fff7df; }
    .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
</style></head><body>
<div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
<div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">Plan zauzeća – ' . e($d['Naziv'])
    . ' · ' . e(datum(danas())) . ' – ' . e(datum(dom_najkasnije())) . '</div><div class="p">' . e(implode(' · ', $podaci)) . '</div></div>
<table class="t"><thead><tr><th style="width:7mm">Rb.</th><th style="width:42mm">Razdoblje</th><th style="width:12mm">Dana</th><th style="width:20mm">Vrsta</th><th>Tko / događaj</th><th style="width:24mm">Status</th></tr></thead><tbody>'
    . ($redovi ?: '<tr><td colspan="6" style="color:#888">Nema rezervacija – dom je slobodan.</td></tr>') . '</tbody></table></body></html>';

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
header('Content-Disposition: inline; filename="dom-' . trim(preg_replace('/[^a-z0-9]+/', '-', kljuc($d['Naziv'])), '-') . '-' . date('Y-m-d') . '.pdf"');
echo $pdf->output();
