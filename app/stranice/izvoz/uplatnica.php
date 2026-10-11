<?php
/**
 * Uplatnice HUB-3A (PDF, 3 po A4).
 *  ?clan=ID[&godina=G][&rata=N][&sve-godine=1] – jedan član (sam član ili blagajnik u opsegu)
 *  ?sve=1&godina=G[&filter=dospjelo]          – svi članovi s otvorenim iznosom (blagajnik, opseg sekcija)
 *  POST clanovi[]=ID…&godina=G                 – odabrani članovi (blagajnik)
 */
$k = korisnik();
$godina = (int) ($_REQUEST['godina'] ?? date('Y'));
if (!uplatnica_spremna()) {
    if (ima(P_SUSTAV)) {
        poruka('Za uplatnice najprije upišite IBAN udruge (Sustav → Uplatnica).', 'warning');
        preusmjeri('sustav/uplatnica');
    }
    nije_pronadjeno('Uplatnica');
}
$sortiraj = function (array $c): array {
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    usort($c, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
    return $c;
};

$uplatnice = [];
$skupno = !empty($_GET['sve']) || isset($_POST['clanovi']);
if ($skupno) {
    trazi(P_CLANARINA_CITAJ);
    $op = opseg_sql('c');
    if (isset($_POST['clanovi'])) {
        $ids = array_values(array_filter(array_map('intval', (array) $_POST['clanovi'])));
        $clanovi = $ids ? redovi("SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c WHERE $op AND c.Id IN (" . implode(',', $ids) . ')') : [];
    } else {
        $clanovi = redovi("SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c JOIN Clanarine x ON x.ClanId=c.Id AND x.Godina=? WHERE $op", [$godina]);
    }
    $samoDospjelo = ($_GET['filter'] ?? '') === 'dospjelo';
    foreach ($sortiraj($clanovi) as $c) {
        foreach (uplatnice_clana((int) $c['Id'], $godina) as $u) {
            if ($samoDospjelo && (!$u['Dospijece'] || $u['Dospijece'] >= danas())) {
                continue;
            }
            $uplatnice[] = $u;
        }
    }
    $ime = "uplatnice-clanarina-$godina.pdf";
} else {
    $clanId = (int) ul_int('clan');
    $clan = red('SELECT * FROM Clanovi WHERE Id=?', [$clanId]);
    $svoj = $k && $k['ClanId'] === $clanId;
    if (!$clan || !(($svoj && uplatnica_za_clanove()) || (ima(P_CLANARINA_CITAJ) && u_opsegu($clan)))) {
        zabranjeno();
    }
    if (!empty($_GET['sve-godine'])) {
        foreach (array_reverse(array_map('intval', array_column(redovi('SELECT Godina FROM Clanarine WHERE ClanId=? ORDER BY Godina DESC', [$clanId]), 'Godina'))) as $g) {
            $uplatnice = array_merge($uplatnice, uplatnice_clana($clanId, $g));
        }
    } else {
        $rata = isset($_GET['rata']) && $_GET['rata'] !== '' ? (int) $_GET['rata'] : null;
        $uplatnice = uplatnice_clana($clanId, $godina, $rata);
    }
    $ime = (string) preg_replace('/[^a-z0-9\-.]+/', '-', 'uplatnica-' . kljuc($clan['Prezime'] . '-' . $clan['Ime']) . "-$godina" . (!empty($rata) ? "-rata$rata" : '') . '.pdf');
}
if (!$uplatnice) {
    poruka('Nema otvorenog iznosa – uplatnica nije potrebna.', 'info');
    if ($skupno) {
        preusmjeri('clanarina', ['godina' => $godina]);
    }
    preusmjeri('uplatnica', ['clan' => $clanId]);
}
$pdf = uplatnice_pdf($uplatnice, 'Uplatnice – članarina ' . $godina);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (($_GET['preuzmi'] ?? '') === '1' ? 'attachment' : 'inline') . '; filename="' . $ime . '"');
echo $pdf;
exit;
