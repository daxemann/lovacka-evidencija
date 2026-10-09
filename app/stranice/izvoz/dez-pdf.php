<?php
// PDF knjige dezinfekcije (pravo pregleda; opseg sekcija korisnika).
trazi(P_DEZ_PREGLED);
$f = dez_filtar($_GET);
$stanice = dez_stanice(true, $f['Vrsta']);
$upisi = dez_upisi($f, array_map(fn($s) => (int) $s['Id'], $stanice));
if ($f['SekcijaId']) {
    $stanice = array_values(array_filter($stanice, fn($s) => (int) $s['SekcijaId'] === $f['SekcijaId']));
}
$pdf = dez_pdf($upisi, $stanice, opis_raspona($f), $f['SekcijaId'] ? 'sekcija ' . naziv_sekcije($f['SekcijaId']) : 'sve sekcije');
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (($_GET['prikaz'] ?? '') === '1' ? 'inline' : 'attachment') . '; filename="dezinfekcija-' . date('Y-m-d') . '.pdf"');
echo $pdf;
