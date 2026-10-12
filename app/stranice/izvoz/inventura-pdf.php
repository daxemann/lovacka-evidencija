<?php
/**
 * PDF inventure: lista za popis (prazan stupac „Stvarno“) ili rezultat s manjkom / viškom.
 * Opseg: jedna mapa (mapa=), odabrane mape (mape[]=), sekcija / cijela udruga (sekcija=) ili sve vidljive.
 */
if (!ima(P_INVENTURA) && !ima(P_INVENTURA_UREDI)) {
    zabranjeno();
}
$id = (int) ($_GET['mapa'] ?? 0);
$odabrane = array_values(array_unique(array_filter(array_map('intval', (array) ($_GET['mape'] ?? [])))));
$filtar = inv_filtar_iz_upita();
if ($id) {
    $mape = [inv_mapa($id)];
    $opseg = $mape[0]['Sekcija'] ? 'Sekcija ' . $mape[0]['Sekcija'] : 'Cijela udruga';
} elseif ($odabrane) {
    $mape = array_values(array_filter(inv_mape(), fn($m) => in_array((int) $m['Id'], $odabrane, true)));
    $sk = array_unique(array_map('inv_skupina', $mape));
    $opseg = count($sk) === 1 ? inv_filtar_naziv($sk[0]) : 'odabrane mape';
} else {
    $mape = inv_mape($filtar);
    $opseg = inv_filtar_naziv($filtar);
}
$rezultat = ($_GET['rezultat'] ?? '') === '1';
$pdf = inv_pdf($mape, $rezultat, $opseg);
$ime = 'inventura-' . trim(preg_replace('/[^a-z0-9]+/', '-', kljuc($id ? $mape[0]['Naziv'] : $opseg)), '-') . '-' . ($rezultat ? 'rezultat-' : '') . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ime . '"');
echo $pdf;
