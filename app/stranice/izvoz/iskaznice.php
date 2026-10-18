<?php
/**
 * Članske iskaznice s QR kodom (PDF).
 *  ?clan=ID                    – jedan član (uprava u opsegu ili sam član)
 *  ?sve=1[&sekcija=ID|nije]    – svi aktivni članovi u opsegu (uprava)
 *  POST odabrani[]=ID…         – odabrani članovi (uprava, popis Članovi)
 *  &format=kartica             – svaka iskaznica na svojoj stranici 85,6 × 54 mm (pisač kartica); inače 10 na A4
 * Arhivirani članovi nemaju važeću iskaznicu – preskaču se.
 */
$k = korisnik();
$format = ($_REQUEST['format'] ?? '') === 'kartica' ? 'kartica' : 'a4';
$sql = 'SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Status=' . CLAN_AKTIVAN;

if (isset($_POST['odabrani']) || !empty($_GET['sve'])) {
    trazi(P_CLANOVI_UREDI);
    $op = opseg_sql('c');
    if (isset($_POST['odabrani'])) {
        $ids = array_values(array_filter(array_map('intval', (array) $_POST['odabrani'])));
        $clanovi = $ids ? redovi("$sql AND $op AND c.Id IN (" . implode(',', $ids) . ')') : [];
    } else {
        $sek = (string) ($_GET['sekcija'] ?? '');
        $uvjet = ctype_digit($sek) ? ' AND c.SekcijaId=' . (int) $sek : ($sek === 'nije' ? ' AND c.SekcijaId IS NULL' : '');
        $clanovi = redovi("$sql AND $op$uvjet");
    }
    if (!$clanovi) {
        poruka('Nema aktivnih članova za iskaznice (arhivirani članovi nemaju važeću iskaznicu).', 'warning');
        preusmjeri('clanovi');
    }
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
    $ime = 'iskaznice-' . date('Y-m-d') . '.pdf';
    dnevnik('Ispis članskih iskaznica', 'Clan', null, count($clanovi) . ' iskaznica (' . $format . ')');
} else {
    $id = (int) ul_int('clan');
    $c = red("$sql AND c.Id=?", [$id]);
    $svoj = $k['ClanId'] === $id;
    if (!$c) {
        nije_pronadjeno('Aktivni član');
    }
    if (!$svoj && !(ima(P_CLANOVI_UREDI) && u_opsegu($c))) {
        zabranjeno();
    }
    $clanovi = [$c];
    $ime = (string) preg_replace('/[^a-z0-9\-.]+/', '-', 'iskaznica-' . kljuc($c['Prezime'] . '-' . $c['Ime']) . '.pdf');
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $ime . '"');
echo isk_pdf($clanovi, $format);
exit;
