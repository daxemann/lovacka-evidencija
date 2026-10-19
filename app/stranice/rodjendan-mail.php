<?php
// Rođendanska čestitka e-poštom (gumb ✉️ na početnoj). Jednom po članu i godini – nakon slanja svi vide „Poslano“.
if (!je_post()) {
    preusmjeri();
}
trazi(P_CLANOVI_CITAJ);
$c = clan_ili_kraj((int) ($_POST['clan'] ?? 0));
$godina = (int) ($_POST['godina'] ?? 0);
$ova = (int) date('Y');
if ($godina !== $ova && $godina !== $ova + 1) {
    preusmjeri();
}
$vec = red('SELECT * FROM RodjendanCestitke WHERE ClanId=? AND Godina=?', [$c['Id'], $godina]);
if ($vec) {
    poruka(e('Čestitku je već poslao ' . ($vec['Poslao'] ?: 'netko drugi') . ' (' . date('d.m. H:i', strtotime($vec['Vrijeme'])) . ').'), 'info');
    preusmjeri();
}
if (!$c['Email']) {
    poruka('Član nema e-mail adresu.', 'warning');
    preusmjeri();
}
if (!posta_dostupna()) {
    poruka('Slanje e-pošte nije podešeno (Sustav → E-pošta i adresa).', 'warning');
    preusmjeri();
}
$godine = $c['DatumRodjenja'] ? $godina - (int) substr($c['DatumRodjenja'], 0, 4) : 0;
[$naslov, $tekst] = poruka_rodjendan($c, $godine);
try {
    posalji_mail($c['Email'], $naslov, tekst_u_html($tekst));
} catch (Throwable $e) {
    poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
    preusmjeri();
}
$k = korisnik();
q('INSERT OR IGNORE INTO RodjendanCestitke (ClanId, Godina, KorisnikId, Poslao, Vrijeme) VALUES (?,?,?,?,?)',
    [$c['Id'], $godina, $k['Id'] ?? null, $k['Naziv'] ?? ($k['KorisnickoIme'] ?? null), sada()]);
dnevnik('Poslana rođendanska čestitka', 'Clan', (int) $c['Id'], puno_ime($c) . ' (' . $godina . ')');
poruka(e('Čestitka poslana na ' . $c['Email'] . '.'));
preusmjeri();
