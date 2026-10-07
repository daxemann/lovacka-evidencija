<?php
trazi(P_SUSTAV);
$ime = basename((string) ($_GET['ime'] ?? ''));
$put = podaci('kopije/' . $ime);
if (!preg_match('/^evidencija-.*\.db$/', $ime) || !is_file($put)) {
    nije_pronadjeno('Kopija');
}
dnevnik('Preuzeta kopija baze', null, null, $ime);
posalji_datoteku($put, 0, $ime, 'application/octet-stream');
