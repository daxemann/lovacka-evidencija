<?php
/**
 * PDF: Evidencija o izdanim i iskorištenim evidencijskim markicama (po uzoru na obrazac PPD-3, Prilog 6. Pravilnika NN 15/19)
 * za evidenciju i lovnu godinu. Neiskorištene = slobodne + već vraćene (vraćaju se Ministarstvu do 31.5.).
 */
$sekcije = odstrel_sekcije() + odstrel_markice_uredive();
if (!odstrel_markice_ukljucene() || !$sekcije) {
    zabranjeno();
}
$g = (int) ul('g', odstrel_lovna_godina());
$sid = (int) ul('s', '');
if (!array_key_exists($sid, $sekcije)) {
    $sid = (int) array_key_first($sekcije);
}
$gSql = odstrel_grupa_sql($sid);

/** Brojevi u raspone: 000101–000110, 000115 (isti prefiks, uzastopni brojevi). */
$rasponi = function (array $brojevi): string {
    natsort($brojevi);
    $out = [];
    $poc = $pre = null;
    $pPre = $pBr = null;
    $zatvori = function () use (&$out, &$poc, &$pre) {
        if ($poc !== null) {
            $out[] = $poc === $pre ? $poc : $poc . ' – ' . $pre;
        }
    };
    foreach ($brojevi as $b) {
        if (preg_match('/^(.*?)(\d+)$/', $b, $m) && $pPre === $m[1] && $pBr !== null && (int) $m[2] === $pBr + 1) {
            $pre = $b;
        } else {
            $zatvori();
            $poc = $pre = $b;
        }
        [$pPre, $pBr] = preg_match('/^(.*?)(\d+)$/', $b, $m) ? [$m[1], (int) $m[2]] : [null, null];
    }
    $zatvori();
    return implode(', ', $out);
};

$redovi1 = '';
foreach (redovi("SELECT Vrsta, COALESCE(Izdatnica,'') AS Izd, SUM(Status=1) AS Isk, SUM(Status<>1) AS Neisk FROM OdstrelMarkice WHERE $gSql AND LovnaGodina=? GROUP BY Vrsta, COALESCE(Izdatnica,'') ORDER BY Vrsta, Izd", [$g]) as $r) {
    $redovi1 .= '<tr><td>' . e($r['Vrsta']) . '</td><td>' . e($r['Izd']) . '</td><td class="b">' . (int) $r['Isk'] . '</td><td class="b">' . (int) $r['Neisk'] . '</td></tr>';
}
$redovi2 = '';
$poVrsti = [];
foreach (redovi("SELECT Vrsta, Broj FROM OdstrelMarkice WHERE $gSql AND LovnaGodina=? AND Status<>1", [$g]) as $r) {
    $poVrsti[$r['Vrsta']][] = $r['Broj'];
}
ksort($poVrsti);
foreach ($poVrsti as $v => $b) {
    $redovi2 .= '<tr><td>' . e($v) . '</td><td>' . e($rasponi($b)) . '</td></tr>';
}
$lovista = [];
if (modul('revir') && vrijednost("SELECT 1 FROM sqlite_master WHERE type='table' AND name='Lovista'")) {
    $cl = array_filter(odstrel_clanice($sid));
    foreach (redovi('SELECT Naziv, Broj, SekcijaId FROM Lovista WHERE Aktivno=1 ORDER BY Naziv') as $l) {
        if ($l['SekcijaId'] === null || in_array((int) $l['SekcijaId'], $cl, true)) {
            $lovista[] = trim(($l['Broj'] ? $l['Broj'] . ' ' : '') . $l['Naziv']);
        }
    }
}
$lovNaziv = implode(', ', $lovista);
$crta = '<span style="display:inline-block;min-width:60mm;border-bottom:0.5pt solid #444">&nbsp;</span>';
$html = '<html><head><meta charset="utf-8"><style>
    @page { margin: 16mm 14mm 20mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
    h1 { font-size: 12pt; text-align: center; margin: 4mm 0 5mm; }
    .glava td { padding: 2px 0; } .glava .l { width: 45mm; color: #555; }
    table.t { width: 100%; border-collapse: collapse; margin-top: 4mm; } .t th { background: #eee; text-align: left; }
    .t th, .t td { border: 0.5pt solid #777; padding: 4px; vertical-align: top; } .t .b { text-align: right; width: 28mm; }
    .podnozje { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 7pt; color: #888; }
    .potpis { margin-top: 12mm; width: 100%; } .potpis td { padding-top: 6mm; }
</style></head><body>
<div class="podnozje">' . e(udruga_naziv()) . ' · ' . e($sekcije[$sid]) . ' · ispisano ' . date('d.m.Y. H:i') . '<br>' . e(ODSTREL_SLE_NAPOMENA) . '</div>
<div style="text-align:right;font-size:8pt;color:#555">Obrazac PPD-3</div>
<table class="glava">
    <tr><td class="l">Ovlaštenik prava lova:</td><td><b>' . e(udruga_naziv()) . '</b>' . (udruga_adresa() ? '<br><small>' . e(udruga_adresa()) . '</small>' : '') . '</td></tr>
    <tr><td class="l">Lovište:</td><td>' . ($lovNaziv !== '' ? e($lovNaziv) : $crta) . (count(odstrel_sve_sekcije()) > 1 ? ' <small>(' . e($sekcije[$sid]) . ')</small>' : '') . '</td></tr>
    <tr><td class="l">Lovna godina:</td><td><b>' . e(odstrel_lg_naziv($g)) . '</b></td></tr>
</table>
<h1>EVIDENCIJA O IZDANIM I ISKORIŠTENIM EVIDENCIJSKIM MARKICAMA</h1>
<table class="t"><thead><tr><th>Vrsta divljači</th><th>Broj izdatnice</th><th class="b">Iskorištene (komada)</th><th class="b">Vraćene (komada)</th></tr></thead><tbody>'
    . ($redovi1 ?: '<tr><td colspan="4" style="color:#888">Nema markica u zalihi.</td></tr>') . '</tbody></table>
<h1 style="font-size:10pt;text-align:left;margin-top:8mm">Brojevi vraćenih (neiskorištenih) markica</h1>
<table class="t" style="margin-top:0"><thead><tr><th style="width:45mm">Vrsta divljači</th><th>Brojevi markica</th></tr></thead><tbody>'
    . ($redovi2 ?: '<tr><td colspan="2" style="color:#888">Sve markice su iskorištene.</td></tr>') . '</tbody></table>
<table class="potpis"><tr><td>Datum: ' . $crta . '</td><td style="text-align:right">Odgovorna osoba: ' . $crta . '<br><br>Potpis: ' . $crta . '</td></tr></table>
</body></html>';

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
header('Content-Disposition: inline; filename="markice-ppd3-' . $g . '-' . date('Y-m-d') . '.pdf"');
echo $pdf->output();
