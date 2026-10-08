<?php
/** Javni iCal feed po osobnom tokenu (pretplata u kalendaru mobitela). */
$kid = korisnik_po_kalendar_tokenu((string) ($_GET['t'] ?? ''));
if (!$kid) {
    http_response_code(404);
    exit('Nepoznata poveznica.');
}
$lista = dogadjaji(date('Y-m-d', strtotime('-90 days')), null, kalendar_opseg($kid));
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="kalendar.ics"');
header('Cache-Control: no-cache');
echo ics($lista, udruga_kratko());
exit;
