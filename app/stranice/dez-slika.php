<?php
// Fotografija papirnate liste dezinfekcije: prijavljeni s pravom pregleda (opseg sekcija) ili prijavljena inspekcija.
$l = red('SELECT l.*, st.SekcijaId FROM DezListe l JOIN DezStanice st ON st.Id=l.StanicaId WHERE l.Id=?', [(int) ($_GET['id'] ?? 0)]);
if (!$l) {
    http_response_code(404);
    exit;
}
$k = korisnik();
$smije = $k && ima(P_DEZ_PREGLED) && moze_sekciju($l['SekcijaId'] !== null ? (int) $l['SekcijaId'] : null);
if (!$smije) {
    $tok = (string) ($_GET['k'] ?? '');
    $smije = $tok !== '' && hash_equals(dez_insp_token(), $tok) && dez_insp_prijavljen();
}
$put = podaci('foto/' . basename($l['Datoteka']));
if (!$smije || !is_file($put)) {
    http_response_code($smije ? 404 : 403);
    exit;
}
posalji_datoteku($put, 3600);
