<?php
/** PDF: popis odstrela (filtar kao na stranici Odstrel) ili plan odstrela (plan=1) za lovnu godinu i sekciju. */
$k = korisnik();
if (!odstrel_dostupno()) {
    zabranjeno();
}
$sekcije = odstrel_sekcije();
$samoMoje = !$sekcije;
$g = (int) ul('g', odstrel_lovna_godina());
$sF = (string) ul('s', '');
$opseg = ($sF !== '' && array_key_exists((int) $sF, $sekcije)) ? [(int) $sF => $sekcije[(int) $sF]] : $sekcije;
$jePlan = !empty($_GET['plan']) && !$samoMoje;

$logo = '';
if (ima_logo()) {
    $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
    $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
    $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
}
$opsegNaziv = $samoMoje ? 'moj odstrel' : (count($opseg) === 1 ? reset($opseg) : 'sve sekcije');

if ($jePlan) {
    $tijelo = '';
    foreach ($opseg as $sid => $sn) {
        $redovi = '';
        foreach (odstrel_plan((int) $sid, $g) as $r) {
            $redovi .= '<tr' . ($r['kategorija'] === '' ? ' class="uk"' : '') . '><td>' . e($r['vrsta']) . '</td><td>' . ($r['kategorija'] !== '' ? e($r['kategorija']) : 'ukupno') . '</td>'
                . '<td class="b">' . ($r['plan'] === null ? '–' : (int) $r['plan']) . '</td><td class="b">' . (int) $r['ostvareno'] . '</td>'
                . '<td class="b">' . ($r['plan'] ? (int) max(0, $r['plan'] - $r['ostvareno']) : '') . '</td>'
                . '<td>' . ($r['plan'] && $r['ostvareno'] > $r['plan'] ? 'prekoračeno' : ($r['plan'] && $r['ostvareno'] >= $r['plan'] ? 'ispunjeno' : '')) . '</td></tr>';
        }
        $tijelo .= (count($opseg) > 1 ? '<h3>' . e($sn) . '</h3>' : '') . '<table class="t"><thead><tr><th>Vrsta</th><th>Kategorija</th><th class="b">Plan</th><th class="b">Ostvareno</th><th class="b">Preostalo</th><th>Napomena</th></tr></thead><tbody>'
            . ($redovi ?: '<tr><td colspan="6" style="color:#888">Plan nije upisan.</td></tr>') . '</tbody></table>';
    }
    $podnaslov = 'Plan odstrela – ostvarenje · lovna godina ' . odstrel_lg_naziv($g) . ' · ' . $opsegNaziv;
    $ime = 'plan-odstrela';
} else {
    $w = ['LovnaGodina=?'];
    $par = [$g];
    if ($samoMoje) {
        $w[] = 'ClanId=?';
        $par[] = (int) $k['ClanId'];
    } else {
        $w[] = odstrel_sek_sql($opseg);
    }
    if (($v = trim((string) ul('vrsta', ''))) !== '') {
        $w[] = 'Vrsta=?';
        $par[] = $v;
    }
    if (($q = trim((string) ul('q', ''))) !== '') {
        $w[] = '(Markica LIKE ? OR Lovac LIKE ?)';
        $par[] = "%$q%";
        $par[] = "%$q%";
    }
    $order = ul('sort', '') === 'markica' ? "Markica IS NULL OR Markica='', CAST(Markica AS INTEGER), Markica, Datum" : 'Datum, Vrijeme, Id';
    $lista = redovi('SELECT * FROM Odstrel WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $order . ' LIMIT 3000', $par);
    $naprave = modul('revir') ? array_column(redovi('SELECT Id, Broj, Naziv FROM RevirNaprave'), null, 'Id') : [];
    $redovi = '';
    foreach ($lista as $i => $o) {
        $n = $o['NapravaId'] ? ($naprave[(int) $o['NapravaId']] ?? null) : null;
        $redovi .= '<tr><td>' . ($i + 1) . '.</td><td>' . e(datum($o['Datum'])) . '</td><td><b>' . e((string) $o['Markica']) . '</b></td>'
            . '<td>' . e($o['Vrsta']) . ($o['Spol'] || $o['Dob'] ? '<br><small>' . e(odstrel_opis($o)) . '</small>' : '') . '</td>'
            . '<td class="b">' . ($o['Masa'] !== null ? e(broj((float) $o['Masa'], 1)) : '') . '</td>'
            . '<td>' . e($o['Lovac']) . ($o['Gost'] ? ' <small>(gost)</small>' : '') . '</td>'
            . '<td>' . e($n ? ($n['Broj'] ? $n['Broj'] . ' · ' : '') . $n['Naziv'] : (string) $o['Mjesto']) . (count($opseg) > 1 ? '<br><small>' . e($sekcije[odstrel_sid($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null)] ?? '') . '</small>' : '') . '</td>'
            . '<td>' . e(trim($o['Oruzje'] . ' ' . $o['Kalibar'])) . '</td></tr>';
    }
    $tijelo = '<table class="t"><thead><tr><th style="width:7mm">Rb.</th><th style="width:19mm">Datum</th><th style="width:28mm">Markica</th><th>Divljač</th><th class="b" style="width:12mm">kg</th><th>Lovac</th><th>Mjesto</th><th>Oružje</th></tr></thead><tbody>'
        . ($redovi ?: '<tr><td colspan="8" style="color:#888">Nema upisa.</td></tr>') . '</tbody></table>';
    $podnaslov = 'Odstrel · lovna godina ' . odstrel_lg_naziv($g) . ' · ' . $opsegNaziv . ' · ' . count($lista) . ' grla';
    $ime = 'odstrel';
}
$html = '<html><head><meta charset="utf-8"><style>
    @page { margin: 16mm 12mm 16mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #222; }
    .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
    .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
    h3 { font-size: 10pt; margin: 10px 0 4px; color: #2f5d23; }
    table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
    .t th, .t td { border: 0.5pt solid #999; padding: 3px 4px; vertical-align: top; } .t small { color: #666; } .t .b { text-align: right; }
    .t tr.uk td { font-weight: bold; background: #f6f8f4; }
    .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
</style></head><body>
<div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
<div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">' . e($podnaslov) . '</div></div>' . $tijelo . '</body></html>';

$opt = new Dompdf\Options();
$opt->set('defaultFont', 'DejaVu Sans');
$opt->set('isRemoteEnabled', false);
$opt->set('tempDir', sys_get_temp_dir());
$opt->set('fontCache', podaci());
$pdf = new Dompdf\Dompdf($opt);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', $jePlan ? 'portrait' : 'landscape');
$pdf->render();
$pdf->getCanvas()->page_text($jePlan ? 520 : 770, $jePlan ? 815 : 570, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ime . '-' . $g . '-' . date('Y-m-d') . '.pdf"');
echo $pdf->output();
