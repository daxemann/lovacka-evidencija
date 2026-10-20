<?php
/** Preuzimanje jedne kopije baze – samo šifrirano lozinkom kopije. */
trazi(P_SUSTAV);
if (!je_post()) {
    preusmjeri('sustav/kopije');
}
$ime = basename((string) ($_POST['ime'] ?? ''));
$put = podaci('kopije/' . $ime);
if (!preg_match('/^evidencija-.*\.db$/', $ime) || !is_file($put)) {
    nije_pronadjeno('Kopija');
}
$lozinka = (string) ($_POST['lozinka_kopije'] ?? '');
if (!kopija_provjeri_lozinku($lozinka)) {
    usleep(500000);
    dnevnik('Kopija baze: pogrešna lozinka kopije', null, null, $ime);
    poruka(kopija_lozinka_postavljena() ? 'Pogrešna lozinka kopije.' : 'Prvo postavite lozinku kopije.', 'danger');
    preusmjeri('sustav/kopije');
}
try {
    $k = kopija_baze_za_preuzimanje($put, $lozinka);
} catch (Throwable $e) {
    poruka('Kopija nije pripremljena: ' . e($e->getMessage()), 'danger');
    preusmjeri('sustav/kopije');
}
dnevnik('Preuzeta kopija baze (šifrirana)', null, null, $ime);
$naziv = preg_replace('/\.db$/', '', $ime) . '.' . $k['nastavak'];
header('Content-Type: ' . ($k['nastavak'] === 'zip' ? 'application/zip' : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $naziv . '"');
header('Content-Length: ' . filesize($k['put']));
header('Cache-Control: no-store');
readfile($k['put']);
@unlink($k['put']);
exit;
