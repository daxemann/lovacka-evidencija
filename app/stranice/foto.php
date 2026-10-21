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
} elseif (!$dozvoljeno && str_starts_with($ime, 'dom-')) {
    $dm = red('SELECT SekcijaId FROM LovackiDomovi WHERE Foto=?', [$ime]);
    $dozvoljeno = $dm && (dom_vidi($dm) || dom_upravlja($dm));
} elseif (!$dozvoljeno) {
    $c = red('SELECT Id, SekcijaId, Status FROM Clanovi WHERE FotoDatoteka=?', [$ime]);
    if ($c) {
        // fotografija aktivnog člana vidi se u Imeniku cijele udruge – svim prijavljenima
        $dozvoljeno = ($k && (int) $c['Status'] === 0) || ($k['ClanId'] === (int) $c['Id']) || (ima(P_CLANOVI_CITAJ) && u_opsegu($c));
        if (!$dozvoljeno && ima(P_GRUPNI_LOV)) {
            // lovnik kod skeniranja iskaznica: članovi sekcija koje organizira i oni na popisu njegovih grupnih lovova
            $sek = gl_sekcije_organizator();
            $dozvoljeno = $c['SekcijaId'] === null ? isset($sek[0]) : isset($sek[(int) $c['SekcijaId']]);
            foreach ($dozvoljeno ? [] : redovi('SELECT l.* FROM GrupniLovPopis p JOIN GrupniLov l ON l.Id=p.LovId WHERE p.ClanId=? ORDER BY l.Id DESC LIMIT 20', [$c['Id']]) as $l) {
                if (gl_moze_upravljati($l)) {
                    $dozvoljeno = true;
                    break;
                }
            }
        }
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
