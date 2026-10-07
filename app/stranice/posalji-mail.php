<?php
// Slanje poruke članu e-poštom (iz "kanala slanja"); za popis bodova prilaže se PDF.
if (!je_post()) {
    preusmjeri();
}
if (!ima(P_IZVJESTAJI) && !ima(P_SUSTAV) && !ima(P_CLANARINA_CITAJ) && !ima(P_AKCIJE_CITAJ)) {
    zabranjeno();
}
$c = clan_ili_kraj((int) ($_POST['clan'] ?? 0));
$pov = json_decode((string) ($_POST['povratak'] ?? ''), true);
$natrag = fn() => is_array($pov) && isset($pov[0]) && preg_match('#^[a-z0-9/\-]+$#', (string) $pov[0]) ? preusmjeri($pov[0], (array) ($pov[1] ?? [])) : preusmjeri();
$naslov = trim((string) ($_POST['naslov'] ?? ''));
$tekst = (string) ($_POST['tekst'] ?? '');
if (!$c['Email']) {
    poruka('Član nema e-mail adresu.', 'warning');
    $natrag();
}
$privitci = [];
if (!empty($_POST['pdf']) && is_array($_POST['pdf'])) {
    $f = filtar_iz_upita($_POST['pdf']);
    $f['ClanId'] = (int) $c['Id'];
    $grupe = izvjestaj_akcija($f, true);
    $privitci['radne-akcije-' . bez_dijakritika($c['Prezime']) . '.pdf'] = izvjestaj_pdf($grupe, 'Radne akcije – ' . puno_ime($c), opis_raspona($f));
}
try {
    posalji_mail($c['Email'], $naslov !== '' ? $naslov : udruga_kratko(), tekst_u_html($tekst), true, null, $privitci);
    dnevnik('Poslana poruka e-poštom', 'Clan', (int) $c['Id'], puno_ime($c) . ': ' . $naslov);
    poruka(e('Poslano na ' . $c['Email'] . '.'));
} catch (Throwable $e) {
    poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
}
$natrag();
