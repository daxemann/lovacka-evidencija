<?php
trazi(P_SUSTAV);
@set_time_limit(300);
session_write_close();
$zip = napravi_kompletnu_kopiju();
dnevnik('Preuzeta kompletna kopija (ZIP)');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="evidencija-kompletna-' . date('Y-m-d_Hi') . '.zip"');
header('Content-Length: ' . filesize($zip));
header('Cache-Control: no-store');
readfile($zip);
@unlink($zip);
exit;
