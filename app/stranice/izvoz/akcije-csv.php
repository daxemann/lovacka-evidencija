<?php
trazi(P_IZVJESTAJI);
$f = filtar_iz_upita($_GET);
$csv = izvjestaj_csv(izvjestaj_akcija($f));
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="radne-akcije-' . date('Y-m-d') . '.csv"');
echo $csv;
