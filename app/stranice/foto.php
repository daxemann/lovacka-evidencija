<?php
// Fotografije su dostupne samo prijavljenima; fotografije članova samo onima koji smiju vidjeti tog člana.
$ime = basename((string) ($_GET['ime'] ?? ''));
$dat = podaci('foto/' . $ime);
if ($ime === '' || !is_file($dat)) {
    http_response_code(404);
    exit;
}
$k = korisnik();
$dozvoljeno = str_starts_with($ime, 'oglas-') || $ime === basename((string) postavka('Udruga.Logo'));
if (!$dozvoljeno) {
    $c = red('SELECT Id, SekcijaId FROM Clanovi WHERE FotoDatoteka=?', [$ime]);
    if ($c) {
        $dozvoljeno = ($k['ClanId'] === (int) $c['Id']) || (ima(P_CLANOVI_CITAJ) && u_opsegu($c));
    } else {
        $a = red('SELECT a.ClanId, c.SekcijaId FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId WHERE a.FotoDatoteka=?', [$ime]);
        if ($a) {
            $dozvoljeno = ($k['ClanId'] === (int) $a['ClanId']) || (ima(P_AKCIJE_CITAJ) && moze_sekciju($a['SekcijaId'] !== null ? (int) $a['SekcijaId'] : null));
        }
    }
}
if (!$dozvoljeno) {
    http_response_code(403);
    exit;
}
posalji_datoteku($dat, 3600);
