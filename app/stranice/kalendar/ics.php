<?php
/** Jedan termin kao .ics (dodaj u kalendar). */
$d = dogadjaj((int) ul_int('id'));
if (!$d) {
    nije_pronadjeno('Termin');
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="termin-' . (int) $d['Id'] . '.ics"');
echo ics([$d], udruga_kratko());
exit;
