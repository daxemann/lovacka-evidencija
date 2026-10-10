<?php
// PDF knjige dezinfekcije (pravo pregleda; opseg sekcija korisnika).
trazi(P_DEZ_PREGLED);
$f = dez_filtar($_GET);
$stanice = dez_stanice(true, $f['Vrsta']);
$upisi = dez_upisi($f, array_map(fn($s) => (int) $s['Id'], $stanice));
if ($f['SekcijaId']) {
    $stanice = array_values(array_filter($stanice, fn($s) => (int) $s['SekcijaId'] === $f['SekcijaId']));
}
$ids = $f['Vrsta'] === 'M' ? [] : array_map(fn($s) => (int) $s['Id'], $stanice);
$datoteke = dez_pdf_datoteke($upisi, $stanice, $ids, $f, opis_raspona($f), $f['SekcijaId'] ? 'sekcija ' . naziv_sekcije($f['SekcijaId']) : 'sve sekcije');
dez_posalji_pdf($datoteke, ($_GET['prikaz'] ?? '') === '1');
