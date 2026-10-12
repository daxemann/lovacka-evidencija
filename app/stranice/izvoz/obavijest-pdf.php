<?php
/** PDF obavijesti s popisom potvrda čitanja (dokaz da su članovi obaviješteni). */
$o = obav_obavijest((int) ($_GET['id'] ?? 0));
if (!$o) {
    nije_pronadjeno('Obavijest');
}
if (!obav_smije_upravljati($o)) {
    zabranjeno();
}
$pdf = obav_pdf($o);
$ime = 'obavijest-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', kljuc($o['Naslov'])), '-') . '-' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ime . '"');
echo $pdf;
