<?php
/** PDF inventure: lista za popis (prazan stupac „Stvarno“) ili rezultat s manjkom / viškom. Jedna mapa ili sve vidljive. */
if (!ima(P_INVENTURA) && !ima(P_INVENTURA_UREDI)) {
    zabranjeno();
}
$id = (int) ($_GET['mapa'] ?? 0);
$mape = $id ? [inv_mapa($id)] : inv_mape();
$rezultat = ($_GET['rezultat'] ?? '') === '1';
$pdf = inv_pdf($mape, $rezultat);
$ime = 'inventura-' . ($id ? preg_replace('/[^a-z0-9]+/', '-', kljuc($mape[0]['Naziv'])) . '-' : '') . ($rezultat ? 'rezultat-' : '') . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ime . '"');
echo $pdf;
