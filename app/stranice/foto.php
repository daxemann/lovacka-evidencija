<?php
// Fotografije su dostupne samo prijavljenima; fotografije članova samo onima koji smiju vidjeti tog člana.
$ime = basename((string) ($_GET['ime'] ?? ''));
$dat = podaci('foto/' . $ime);
if ($ime === '' || !is_file($dat)) {
    http_response_code(404);
    exit;
}
$k = korisnik();
$dozvoljeno = str_starts_with($ime, 'oglas-') || str_starts_with($ime, 'vrsta-') || $ime === basename((string) postavka('Udruga.Logo'));
if (!$dozvoljeno && str_starts_with($ime, 'dok-')) {
    $dozvoljeno = dok_smije_datoteku($ime);
    if ($dozvoljeno && str_ends_with($ime, '.pdf')) {
        $n = vrijednost('SELECT Naziv FROM DokumentDatoteke WHERE Datoteka=?', [$ime]);
        if (!empty($_GET['preuzmi'])) {
            posalji_datoteku($dat, 0, dok_naziv_datoteke((string) $n) . '.pdf');
        }
        header('Content-Disposition: inline; filename="' . str_replace('"', '', dok_naziv_datoteke((string) $n)) . '.pdf"');
        posalji_datoteku($dat, 0);
    }
} elseif (!$dozvoljeno && str_starts_with($ime, 'kvar-')) {
    $kv = red('SELECT SekcijaId FROM RevirKvarovi WHERE Foto=?', [$ime]);
    $dozvoljeno = $kv && revir_moze_vidjeti($kv['SekcijaId'] !== null ? (int) $kv['SekcijaId'] : null);
} elseif (!$dozvoljeno && str_starts_with($ime, 'naprava-')) {
    $n = red('SELECT SekcijaId FROM RevirNaprave WHERE Foto=?', [$ime]);
    $dozvoljeno = $n && revir_moze_vidjeti($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null);
} elseif (!$dozvoljeno) {
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
