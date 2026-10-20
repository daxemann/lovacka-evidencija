<?php
/** Preuzimanje kompletne kopije – samo šifrirano lozinkom kopije. */
trazi(P_SUSTAV);
if (!je_post()) {
    preusmjeri('sustav/kopije');
}
$lozinka = (string) ($_POST['lozinka_kopije'] ?? '');
if (!kopija_lozinka_postavljena()) {
    poruka('Prvo postavite lozinku kopije.', 'warning');
    preusmjeri('sustav/kopije');
}
if (!kopija_provjeri_lozinku($lozinka)) {
    usleep(500000);
    dnevnik('Kompletna kopija: pogrešna lozinka kopije');
    poruka('Pogrešna lozinka kopije.', 'danger');
    preusmjeri('sustav/kopije');
}
@set_time_limit(300);
session_write_close();
try {
    $k = napravi_kompletnu_kopiju($lozinka);
} catch (Throwable $e) {
    session_start();
    poruka('Kopija nije napravljena: ' . e($e->getMessage()), 'danger');
    preusmjeri('sustav/kopije');
}
dnevnik('Preuzeta kompletna kopija (šifrirana, ' . $k['nastavak'] . ')');
header('Content-Type: ' . ($k['nastavak'] === 'zip' ? 'application/zip' : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="evidencija-kompletna-' . date('Y-m-d_Hi') . '.' . $k['nastavak'] . '"');
header('Content-Length: ' . filesize($k['put']));
header('Cache-Control: no-store');
readfile($k['put']);
@unlink($k['put']);
exit;
