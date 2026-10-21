<?php
/**
 * Početna stranica od blokova: administrator u Sustav → Početna za svaku ulogu (i za članove bez uloge) bira
 * koje blokove vide i kojim redom. Blok se i dalje prikazuje samo ako korisnik ima pravo i modul je uključen.
 */
declare(strict_types=1);

/** ključ => [naziv, opis, pločica (true = veliki gumb u mreži)] */
const POCETNA_BLOKOVI = [
    'sken' => ['🪪 Skeniraj iskaznice', 'Samo ovlaštenima dok je mobilna stanica aktivna.', true],
    'qr' => ['📷 Skeniraj QR – dezinfekcija', 'Veliki zeleni gumb (dolazak / odlazak).', true],
    'karta' => ['🗺️ Karta lovišta', 'Gumb – sjedim ovdje, kvarovi.', true],
    'danas' => ['🌲 Danas u lovištu', 'Gumb – tko gdje sjedi.', true],
    'skupni' => ['🎯 Skupni lov', 'Gumb – pokretanje / karta lova (organizatori, sudionici dok traje).', true],
    'mobilna' => ['📱 Mobilna stanica', 'Gumb – aktivacija dezinfekcije za skupni lov.', true],
    'kvarovi' => ['🔧 Kvarovi na napravama', 'Gumb s brojem otvorenih prijava.', true],
    'danas_popis' => ['🌲 Danas u lovištu – popis', 'Popis zauzetih naprava odmah na početnoj (osvježava se).', false],
    'uprava' => ['🗂️ Uprava – upozorenja', 'Naslov „Uprava“, + Nova radna akcija, članovi čekaju odobrenje, duplikati.', false],
    'termini' => ['📅 Nadolazeći termini', 'Kalendar – sljedeći termini.', false],
    'moje_akcije' => ['🪓 Moje radne akcije', 'Bodovi u lovnoj godini i zadnje akcije.', false],
    'clanarina' => ['💶 Moja članarina', 'Stanje članarine člana.', false],
    'brojke' => ['📊 Brojke', 'Aktivni članovi, neraspoređeni, akcije na čekanju, nepodmirene članarine.', false],
    'rodjendani' => ['🎂 Rođendani (14 dana)', 'S gumbom za čestitku e-poštom.', false],
    'iskaznice' => ['🪪 Lovačke iskaznice ističu', 'Ističu u 60 dana ili su istekle.', false],
    'precaci' => ['📇 Prečaci', 'Imenik · Iskaznica · Poruke · Moj profil.', false],
];
const POCETNA_ZADANO = [
    'clan' => ['sken', 'qr', 'karta', 'danas', 'termini', 'moje_akcije', 'precaci', 'clanarina'],
    'uloga' => ['sken', 'qr', 'karta', 'danas', 'uprava', 'termini', 'clanarina', 'brojke', 'rodjendani', 'iskaznice', 'precaci'],
    'glavni' => ['uprava', 'termini', 'clanarina', 'brojke', 'rodjendani', 'iskaznice'],
];

/** Postavke: ['red' => [ključevi redom], 'uloge' => ['clan' => [...], '<UlogaId>' => [...]]] */
function pocetna_postavke(): array
{
    $j = json_decode((string) postavka('Pocetna.Raspored', ''), true);
    $red = array_values(array_filter((array) ($j['red'] ?? []), fn($x) => isset(POCETNA_BLOKOVI[$x])));
    foreach (array_keys(POCETNA_BLOKOVI) as $x) {
        if (!in_array($x, $red, true)) {
            $red[] = $x;
        }
    }
    $uloge = ['clan' => POCETNA_ZADANO['clan']];
    foreach (redovi('SELECT Id, Sustavna FROM Uloge') as $u) {
        $uloge[(string) $u['Id']] = $u['Sustavna'] ? POCETNA_ZADANO['glavni'] : POCETNA_ZADANO['uloga'];
    }
    foreach ((array) ($j['uloge'] ?? []) as $kl => $b) {
        if (isset($uloge[(string) $kl])) {
            $uloge[(string) $kl] = array_values(array_filter((array) $b, fn($x) => isset(POCETNA_BLOKOVI[$x])));
        }
    }
    return ['red' => $red, 'uloge' => $uloge];
}
function pocetna_spremi(array $p): void
{
    spremi_postavku('Pocetna.Raspored', json_encode($p, JSON_UNESCAPED_UNICODE));
}

/** Blokovi za korisnika: bez uloge → „Član“; s ulogama → unija blokova svih njegovih uloga. $pregled = ključ stupca za pregled (admin). */
function pocetna_blokovi(array $k, ?string $pregled = null): array
{
    $p = pocetna_postavke();
    if ($pregled !== null && isset($p['uloge'][$pregled])) {
        $skup = $p['uloge'][$pregled];
    } else {
        $ids = array_map('strval', array_column(redovi('SELECT DISTINCT UlogaId FROM KorisnikUloge WHERE KorisnikId=?', [$k['Id']]), 'UlogaId'));
        $skup = [];
        foreach ($ids ?: ['clan'] as $u) {
            $skup = array_merge($skup, $p['uloge'][$u] ?? []);
        }
        if (!$ids) {
            $skup = $p['uloge']['clan'];
        }
    }
    return array_values(array_filter($p['red'], fn($x) => in_array($x, $skup, true)));
}

/** Cijela početna: pozdrav + blokovi redom (uzastopne pločice u jednoj mreži). */
function pocetna_html(array $k, array $blokovi): string
{
    $o = '<h1 class="h4 mb-3">Pozdrav, ' . e(explode(' ', (string) $k['Naziv'])[0]) . '</h1>';
    $mreza = '';
    $zatvori = function () use (&$mreza, &$o) {
        if ($mreza !== '') {
            $o .= '<div class="lov-gumbi mb-3">' . $mreza . '</div>';
            $mreza = '';
        }
    };
    foreach ($blokovi as $b) {
        $html = pocetna_blok($b, $k);
        if ($html === '') {
            continue;
        }
        if (POCETNA_BLOKOVI[$b][2]) {
            $mreza .= $html;
        } else {
            $zatvori();
            $o .= $html;
        }
    }
    $zatvori();
    return $o;
}

function pocetna_plocica(string $href, string $ikona, string $naslov, string $opis, string $klasa = '', int $broj = 0): string
{
    return '<a class="lov-gumb ' . $klasa . '" href="' . e($href) . '"><span class="lov-gumb-ikona">' . $ikona . '</span><span><b>' . e($naslov)
        . ($broj > 0 ? ' <span class="badge rounded-pill bg-danger align-middle">' . $broj . '</span>' : '') . '</b><small>' . e($opis) . '</small></span></a>';
}

/** HTML jednog bloka ili '' ako ga korisnik ne smije / modul je isključen. */
function pocetna_blok(string $b, array $k): string
{
    $cid = $k['ClanId'];
    $revir = fn() => modul('revir') && revir_sekcije();
    switch ($b) {
        case 'sken':
            $o = '';
            foreach (modul('dezinfekcija') ? dez_moje_skeniranje() : [] as $sa) {
                $o .= pocetna_plocica(url('dez/iskaznice', ['a' => $sa['Id']]), '🪪', 'Skeniraj iskaznice', $sa['Naziv'] . ' · mobilna stanica do ' . date('H:i', strtotime($sa['Do'])), 'lov-gumb-qr lov-gumb-sken');
            }
            return $o;
        case 'qr':
            return modul('dezinfekcija') ? pocetna_plocica(url('skeniraj'), '📷', 'Skeniraj QR', 'Dezinfekcija – dolazak / odlazak', 'lov-gumb-qr') : '';
        case 'karta':
            return $revir() ? pocetna_plocica(url('revir'), '🗺️', 'Karta lovišta', 'sjedim ovdje · kvarovi') : '';
        case 'danas':
            if (!$revir()) {
                return '';
            }
            // broj = koliko ih trenutno sjedi (isto za sve); lovočuvari s nadzorom vide i nove obavijesti
            revir_zatvori_istekle();
            $sjede = count(array_filter(revir_stanje(revir_sekcije()), fn($n) => $n['zauzeto'] !== null));
            $nove = revir_broj_obavijesti();
            return pocetna_plocica(url('revir/danas'), '🌲', 'Danas u lovištu', ($sjede ? $sjede . ' u lovištu' : 'nitko u lovištu') . ($nove ? ' · 🔔 ' . $nove . ' novo' : ''), '', $sjede);
        case 'skupni':
            if (!modul('grupni') || !(gl_vidljivo() || ima(P_GRUPNI_LOV))) {
                return '';
            }
            $akt = gl_moj_aktivni() !== null || (ima(P_GRUPNI_LOV) && gl_aktivni_organizator());
            return pocetna_plocica(url('grupni-lov'), '🎯', 'Skupni lov', $akt ? 'u tijeku – karta lova' : 'pokreni / popis sudionika', $akt ? 'lov-gumb-aktivno' : '');
        case 'mobilna':
            return modul('dezinfekcija') && ima(P_DEZ_MOBILNA) ? pocetna_plocica(url('dezinfekcija/mobilna'), '📱', 'Mobilna stanica', 'dezinfekcija za skupni lov') : '';
        case 'kvarovi':
            return $revir() ? pocetna_plocica(url('revir/kvarovi'), '🔧', 'Kvarovi', 'prijave na napravama', '', revir_broj_kvarova()) : '';
        case 'danas_popis':
            return $revir() ? pocetna_danas_popis() : '';
        case 'uprava':
            return $k['Prava'] ? pocetna_uprava() : '';
        case 'termini':
            return modul('kalendar') ? pocetna_termini($k) : '';
        case 'moje_akcije':
            return $cid && modul('akcije') ? pocetna_moje_akcije((int) $cid) : '';
        case 'clanarina':
            return $cid && modul('clanarina') ? '<div class="mb-3">' . predlozak('moja-clanarina', ['clanId' => $cid]) . '</div>' : '';
        case 'brojke':
            return ima(P_CLANOVI_CITAJ) || ima(P_AKCIJE_CITAJ) || ima(P_CLANARINA_CITAJ) ? pocetna_brojke($k) : '';
        case 'rodjendani':
        case 'iskaznice':
            return ima(P_CLANOVI_CITAJ) ? pocetna_rod_isk($b) : '';
        case 'precaci':
            return '<div class="mt-2">' . predlozak('pocetna-precaci') . '</div>';
    }
    return '';
}

function pocetna_danas_popis(): string
{
    revir_zatvori_istekle();
    $sek = revir_sekcije();
    $zauzete = array_values(array_filter(revir_stanje($sek), fn($n) => $n['zauzeto'] !== null));
    usort($zauzete, fn($a, $b) => [$a['sekcija'], $a['zauzeto']['ime']] <=> [$b['sekcija'], $b['zauzeto']['ime']]);
    $o = '<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">🌲 Danas u lovištu <span class="badge bg-danger">' . count($zauzete) . '</span></b>'
        . '<a class="small" href="' . e(url('revir/danas')) . '">sve →</a></div><ul class="list-group list-group-flush small">';
    foreach ($zauzete as $n) {
        $z = $n['zauzeto'];
        $tko = $z['gost'] ? 'Gost' . ($z['gost'] !== 'gost' ? ' ' . $z['gost'] : '') . ' (od ' . $z['ime'] . ')' : $z['ime'];
        $o .= '<li class="list-group-item d-flex align-items-center gap-2"><span class="revir-tocka ' . ($z['moje'] ? 'moje' : 'zauzeto') . '"></span>'
            . '<span class="flex-grow-1"><b>' . e($tko) . '</b> · <a class="text-decoration-none" href="' . e(url('revir', ['naprava' => $n['id']])) . '">'
            . e(($n['broj'] !== '' ? $n['broj'] . ' · ' : '') . $n['naziv']) . '</a>' . (count($sek) > 1 ? ' <span class="text-muted">' . e($sek[$n['sekcija']] ?? '') . '</span>' : '') . '</span>'
            . '<span class="text-muted text-nowrap">od ' . e($z['od']) . '</span></li>';
    }
    if (!$zauzete) {
        $o .= '<li class="list-group-item text-muted">Trenutno nitko nije u lovištu.</li>';
    }
    return $o . '</ul></div>';
}

function pocetna_uprava(): string
{
    $cekaju = ima(P_SUSTAV) ? (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND Aktivan=1') : 0;
    $dupl = ima(P_SUSTAV) ? count(pronadji_duplikate()) : 0;
    $o = '<div class="d-flex flex-wrap align-items-center gap-2 mb-3 pt-2 border-top"><h2 class="h5 mb-0 me-auto">🗂️ Uprava</h2>'
        . (ima(P_AKCIJE_ODOBRI) ? '<a href="' . e(url('akcije/nova')) . '" class="btn btn-sm btn-primary">+ Nova radna akcija</a>' : '') . '</div>';
    if (ima(P_SUSTAV) && str_starts_with(str_replace('\\', '/', realpath(podaci()) ?: ''), str_replace('\\', '/', realpath(KORIJEN) ?: '-'))) {
        $o .= '<div class="alert alert-danger d-none" id="izlozeno"><b>Upozorenje – baza je javno dostupna!</b> Poslužitelj ne poštuje datoteku .htaccess (npr. nginx), pa se mapa <code>podaci</code> može preuzeti s interneta.
            Premjestite mapu s podacima izvan javne mape weba (config.php → \'podaci\') ili zabranite pristup mapi <code>podaci</code> u postavkama poslužitelja. Upute: README.</div>'
            . '<script>fetch(' . json_encode(bazni_put() . substr(str_replace('\\', '/', realpath(podaci())), strlen(str_replace('\\', '/', realpath(KORIJEN))) + 1) . '/evidencija.db') . ', { cache: \'no-store\', headers: { Range: \'bytes=0-15\' } })'
            . '.then(function (r) { return r.ok ? r.text() : \'\'; }).then(function (t) { if (t.indexOf(\'SQLite format 3\') === 0) document.getElementById(\'izlozeno\').classList.remove(\'d-none\'); }).catch(function () {});</script>';
    }
    if ($dupl > 0) {
        $o .= '<div class="alert alert-warning d-flex align-items-center"><span>Mogući duplikati članova: <b>' . $dupl . '</b></span><a href="' . e(url('sustav/duplikati')) . '" class="btn btn-sm btn-warning ms-auto">Provjeri</a></div>';
    }
    if ($cekaju > 0) {
        $o .= '<div class="alert alert-warning d-flex align-items-center"><span><b>' . $cekaju . '</b> ' . ($cekaju === 1 ? 'član čeka' : 'članova čeka') . ' odobrenje pristupa.</span><a href="' . e(url('sustav/korisnici')) . '" class="btn btn-sm btn-warning ms-auto">Pregledaj</a></div>';
    }
    return $o;
}

function pocetna_termini(array $k): string
{
    $cid = $k['ClanId'];
    $termini = dogadjaji(danas(), null, null, 4);
    $moji = [];
    if ($cid && $termini) {
        foreach (redovi('SELECT DogadjajId, Dolazi FROM DogadjajOdgovori WHERE ClanId=?', [$cid]) as $r) {
            $moji[(int) $r['DogadjajId']] = (int) $r['Dolazi'];
        }
    }
    $o = '<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">📅 Nadolazeći termini</b>'
        . (ima(P_KALENDAR) ? '<a class="btn btn-sm btn-outline-primary me-2" href="' . e(url('kalendar/uredi')) . '">+ Novi termin</a>' : '')
        . '<a class="small" href="' . e(url('kalendar')) . '">kalendar →</a></div><div class="list-group list-group-flush">';
    foreach ($termini as $d) {
        $v = vrsta_dogadjaja($d['Vrsta']);
        $o .= '<a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="' . e(url('kalendar/termin', ['id' => $d['Id']])) . '" style="border-left:4px solid ' . e($v['boja']) . '">'
            . '<div class="text-center text-nowrap" style="min-width:3.2rem"><div class="fw-bold lh-1">' . date('d.m.', strtotime($d['Pocetak'])) . '</div><div class="small text-muted">' . ($d['CijeliDan'] ? '' : date('H:i', strtotime($d['Pocetak']))) . '</div></div>'
            . '<div class="flex-grow-1 overflow-hidden"><div class="text-truncate fw-semibold">' . e($d['Naslov']) . '</div><div class="small text-muted text-truncate">' . e($d['Vrsta']) . ($d['Mjesto'] ? ' · ' . e($d['Mjesto']) : '') . '</div></div>'
            . (isset($moji[(int) $d['Id']]) ? '<span class="badge ' . ($moji[(int) $d['Id']] ? 'bg-success' : 'bg-secondary') . '">' . ($moji[(int) $d['Id']] ? '✔' : '✖') . '</span>' : '')
            . (ima(P_KALENDAR) ? '<span class="small text-muted text-nowrap">👥 ' . (int) ($d['Dolazi'] ?? 0) . '</span>' : '') . '</a>';
    }
    if (!$termini) {
        $o .= '<div class="list-group-item small text-muted">Nema najavljenih termina.</div>';
    }
    return $o . '</div></div>';
}

function pocetna_moje_akcije(int $cid): string
{
    $lg = pocetak_lovne_godine();
    $bodovi = (float) vrijednost('SELECT COALESCE(SUM(CAST(Bodovi AS REAL)),0) FROM RadneAkcije WHERE ClanId=? AND Status=1 AND substr(Datum,1,10) BETWEEN ? AND ?', [$cid, $lg->format('Y-m-d'), $lg->modify('+1 year -1 day')->format('Y-m-d')]);
    $ceka = (int) vrijednost('SELECT COUNT(*) FROM RadneAkcije WHERE ClanId=? AND Status=0', [$cid]);
    $o = '<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">🪓 Moje radne akcije</b><a class="small" href="' . e(url('moje-akcije')) . '">sve →</a></div>'
        . '<div class="card-body pb-2"><div class="d-flex align-items-center gap-3 mb-2"><div><div class="kartica-broj lh-1">' . e(broj($bodovi, 2)) . '</div><div class="small text-muted">bodova u lovnoj godini' . ($ceka ? " · $ceka čeka odobrenje" : '') . '</div></div>'
        . '<a class="btn btn-primary ms-auto" href="' . e(url('moje-akcije')) . '#nova">+ Prijavi akciju</a></div>';
    foreach (redovi('SELECT a.*, v.Naziv AS Vrsta FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.ClanId=? ORDER BY a.Datum DESC, a.Id DESC LIMIT 3', [$cid]) as $a) {
        $o .= '<div class="small d-flex border-top py-1"><span class="text-muted me-2">' . e(date('d.m.', strtotime($a['Datum']))) . '</span><span class="flex-grow-1 text-truncate">'
            . e($a['Vrsta'] ?? $a['VrstaSlobodno']) . '</span><span>' . status_akcije_oznaka((int) $a['Status']) . '</span></div>';
    }
    return $o . '</div></div>';
}

function pocetna_brojke(array $k): string
{
    $op = opseg_sql('c');
    $o = '<div class="row g-3 mb-3">';
    $kartica = fn(string $href, int $n, string $tekst, string $boja = '') => '<div class="col-6 col-lg-3"><a href="' . e($href) . '" class="card text-decoration-none h-100"><div class="card-body">'
        . '<div class="kartica-broj ' . $boja . '">' . $n . '</div><div class="text-muted">' . e($tekst) . '</div></div></a></div>';
    if (ima(P_CLANOVI_CITAJ)) {
        $clanovi = redovi("SELECT c.SekcijaId FROM Clanovi c WHERE c.Status=0 AND $op");
        $ner = $k['SveSekcije'] ? count(array_filter($clanovi, fn($c) => $c['SekcijaId'] === null)) : 0;
        $o .= $kartica(url('clanovi'), count($clanovi), 'aktivnih članova') . $kartica(url('clanovi', ['sekcija' => 'nije']), $ner, 'nije raspoređeno u sekciju', $ner > 0 ? 'text-warning' : '');
    }
    if (ima(P_AKCIJE_CITAJ)) {
        $n = broj_akcija_na_cekanju();
        $o .= $kartica(url('akcije'), $n, 'radnih akcija čeka odobrenje', $n > 0 ? 'text-warning' : '');
    }
    if (ima(P_CLANARINA_CITAJ) && modul('clanarina')) {
        $god = (int) date('Y');
        $osl = oslobodjeni_clanarine($god);
        $nep = 0;
        foreach (redovi("SELECT cl.ClanId, CAST(cl.Iznos AS REAL) - COALESCE((SELECT SUM(CAST(u.Iznos AS REAL)) FROM Uplate u WHERE u.ClanarinaId=cl.Id),0) AS Preostalo
                         FROM Clanarine cl JOIN Clanovi c ON c.Id=cl.ClanId WHERE cl.Godina=? AND c.Status=0 AND $op", [$god]) as $r) {
            if ($r['Preostalo'] > 0.004 && !isset($osl[(int) $r['ClanId']])) {
                $nep++;
            }
        }
        $o .= $kartica(url('clanarina'), $nep, 'nepodmirenih članarina ' . $god . '.', $nep > 0 ? 'text-danger' : '');
    }
    return $o . '</div>';
}

/** Rođendani (14 dana) ili iskaznice koje ističu (60 dana). */
function pocetna_rod_isk(string $b): string
{
    $op = opseg_sql('c');
    $clanovi = redovi("SELECT c.* FROM Clanovi c WHERE c.Status=0 AND $op");
    $danas = new DateTimeImmutable('today');
    if ($b === 'iskaznice') {
        $isk = array_values(array_filter($clanovi, fn($c) => $c['IskaznicaVrijediDo'] && substr($c['IskaznicaVrijediDo'], 0, 10) <= $danas->modify('+60 days')->format('Y-m-d')));
        usort($isk, fn($a, $b) => strcmp($a['IskaznicaVrijediDo'], $b['IskaznicaVrijediDo']));
        $o = '<div class="card mb-3"><div class="card-header">Lovačka iskaznica ističe (60 dana) ili je istekla</div><ul class="list-group list-group-flush">';
        foreach ($isk as $c) {
            $o .= '<li class="list-group-item d-flex align-items-center gap-2">' . avatar($c) . '<a href="' . e(url('clanovi/uredi', ['id' => $c['Id']])) . '">' . e(puno_ime($c)) . '</a>'
                . '<span class="ms-auto ' . (substr($c['IskaznicaVrijediDo'], 0, 10) < date('Y-m-d') ? 'text-danger' : 'text-warning') . '">' . e(date('d.m.Y', strtotime(substr($c['IskaznicaVrijediDo'], 0, 10)))) . '</span></li>';
        }
        return $o . ($isk ? '' : '<li class="list-group-item text-muted">Ništa ne ističe.</li>') . '</ul></div>';
    }
    $rodj = [];
    foreach ($clanovi as $c) {
        if (!$c['DatumRodjenja']) {
            continue;
        }
        $dr = new DateTimeImmutable(substr($c['DatumRodjenja'], 0, 10));
        for ($i = 0; $i <= 1; $i++) {
            $g = (int) $danas->format('Y') + $i;
            $m = (int) $dr->format('m');
            $d = (int) $dr->format('d');
            if ($m === 2 && $d === 29 && !checkdate(2, 29, $g)) {
                $d = 28;
            }
            $rd = $danas->setDate($g, $m, $d);
            $raz = (int) $danas->diff($rd)->format('%r%a');
            if ($raz >= 0 && $raz <= 14) {
                $rodj[] = [$c, $rd, $g - (int) $dr->format('Y')];
                break;
            }
        }
    }
    usort($rodj, fn($a, $b) => $a[1] <=> $b[1]);
    $cest = poslane_cestitke(array_map(fn($r) => [(int) $r[0]['Id'], (int) $r[1]->format('Y')], $rodj));
    $mail = posta_dostupna();
    $o = '<div class="card mb-3"><div class="card-header">Rođendani u sljedećih 14 dana</div><ul class="list-group list-group-flush">';
    foreach ($rodj as [$c, $d, $god]) {
        $o .= '<li class="list-group-item d-flex align-items-center gap-2">' . avatar($c) . '<a href="' . e(url('clanovi/uredi', ['id' => $c['Id']])) . '">' . e(puno_ime($c)) . '</a>'
            . '<span class="ms-auto text-muted text-nowrap">' . $d->format('d.m.') . ' (' . $god . '. g.)</span>';
        if ($x = $cest[(int) $c['Id']] ?? null) {
            $o .= '<span class="badge text-bg-success" title="' . e(trim(($x['Poslao'] ?? '') . ' · ' . date('d.m.Y H:i', strtotime($x['Vrijeme'])), ' ·')) . '">✓ Poslano</span>';
        } elseif ($c['Email'] && $mail) {
            $o .= '<form method="post" action="' . e(url('rodjendan-mail')) . '" class="m-0">' . csrf() . '<input type="hidden" name="clan" value="' . (int) $c['Id'] . '"><input type="hidden" name="godina" value="' . $d->format('Y') . '">'
                . '<button class="btn btn-sm btn-outline-primary py-0" title="Pošalji rođendansku čestitku e-poštom" data-potvrda="' . e('Poslati rođendansku čestitku – ' . puno_ime($c) . ' (' . $c['Email'] . ')?') . '">✉️</button></form>';
        }
        $o .= '</li>';
    }
    return $o . ($rodj ? '' : '<li class="list-group-item text-muted">Nema rođendana.</li>') . '</ul></div>';
}
