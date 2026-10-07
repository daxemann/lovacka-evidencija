<?php
// PDF radnih akcija: admin s pravom izvještaja, ili član za vlastiti popis.
$k = korisnik();
$f = filtar_iz_upita($_GET);
if (!ima(P_IZVJESTAJI)) {
    if (!$k['ClanId'] || $f['ClanId'] !== $k['ClanId']) {
        zabranjeno();
    }
}
if ($f['ClanId'] !== null && ima(P_IZVJESTAJI)) {
    clan_ili_kraj($f['ClanId']);
}
$grupe = izvjestaj_akcija($f, !ima(P_IZVJESTAJI));
$naslov = 'Radne akcije';
if ($f['ClanId']) {
    $naslov .= ' – ' . puno_ime(clan($f['ClanId']));
}
$pdf = izvjestaj_pdf($grupe, $naslov, opis_raspona($f), $f['ClanId'] === null);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (($_GET['prikaz'] ?? '') === '1' ? 'inline' : 'attachment') . '; filename="radne-akcije-' . date('Y-m-d') . '.pdf"');
echo $pdf;
