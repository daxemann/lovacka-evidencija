<?php
/**
 * Demo podaci (izmišljena udruga) za javnu probnu instalaciju – pokreće se SAMO iz naredbenog retka:
 *   php app/demo-podaci.php
 * Nakon postavljanja (prvi administrator već postoji) dodaje sekcije, članove, radne akcije, članarinu, kalendar, oglase,
 * poruke, knjigu dezinfekcije i lovište, demo račune (lozinka "demo") i uključuje demo način (Demo.Aktivno = 1).
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('KORIJEN_DEMO', dirname(__DIR__));
require KORIJEN_DEMO . '/vendor/autoload.php';
require KORIJEN_DEMO . '/app/osnova.php';
date_default_timezone_set('Europe/Zagreb');
mb_internal_encoding('UTF-8');
mt_srand(20261011);

$LOZINKA = 'demo';
$danas = strtotime(date('Y-m-d'));
$dan = fn(int $d, string $sat = '') => date('Y-m-d', $danas + $d * 86400) . ($sat !== '' ? ' ' . $sat : '');
$sada = sada();
$izaberi = fn(array $a) => $a[array_rand($a)];

if (!vrijednost('SELECT 1 FROM Korisnici LIMIT 1')) {
    exit("Prvo postavite program (prvi administrator).\n");
}
if (vrijednost('SELECT COUNT(*) FROM Clanovi') > 2) {
    exit("Baza već ima članove – demo podaci se dodaju samo u praznu instalaciju.\n");
}

// ---------- udruga ----------
spremi_postavku('Udruga.Naziv', 'Lovačka udruga „Srndać“ Novo Selo');
spremi_postavku('Udruga.Kratko', 'LU Srndać');
spremi_postavku('Udruga.Podnaslov', 'Demo verzija – izmišljena udruga');
q('DELETE FROM Sekcije');
$sek = [];
foreach (['Sjever', 'Jug', 'Lug'] as $i => $n) {
    $sek[$n] = umetni('Sekcije', ['Naziv' => $n, 'Aktivna' => 1, 'Redoslijed' => $i + 1]);
}

// ---------- članovi ----------
$imena = [
    ['Tomislav', 'Horvat', 'Sjever'], ['Ivan', 'Kovačević', 'Sjever'], ['Marko', 'Babić', 'Sjever'], ['Josip', 'Marić', 'Sjever'], ['Stjepan', 'Novak', 'Sjever'],
    ['Zdravko', 'Jurić', 'Sjever'], ['Dražen', 'Knežević', 'Sjever'], ['Mario', 'Vuković', 'Sjever'], ['Damir', 'Matić', 'Sjever'], ['Krešimir', 'Pavić', 'Sjever'],
    ['Ante', 'Blažević', 'Jug'], ['Nikola', 'Grgić', 'Jug'], ['Željko', 'Pavlović', 'Jug'], ['Dino', 'Šarić', 'Jug'], ['Goran', 'Lovrić', 'Jug'],
    ['Branko', 'Perić', 'Jug'], ['Dario', 'Kolar', 'Jug'], ['Igor', 'Radić', 'Jug'], ['Mladen', 'Filipović', 'Jug'], ['Vlado', 'Mikulić', 'Jug'],
    ['Petar', 'Božić', 'Lug'], ['Luka', 'Tomić', 'Lug'], ['Hrvoje', 'Kovač', 'Lug'], ['Dubravko', 'Šimić', 'Lug'], ['Robert', 'Bošnjak', 'Lug'],
    ['Saša', 'Petrović', 'Lug'], ['Franjo', 'Jukić', 'Lug'], ['Ivica', 'Kralj', 'Lug'], ['Antun', 'Galić', 'Lug'], ['Marijan', 'Varga', 'Lug'],
];
$mjesta = ['Sjever' => ['Novo Selo', '33405'], 'Jug' => ['Donja Bukovica', '33404'], 'Lug' => ['Lugarje', '33406']];
$ulice = ['Glavna', 'Lovačka', 'Vinogradska', 'Šumska', 'Kolodvorska', 'Ulica bana Jelačića', 'Školska', 'Poljska'];
$clanovi = [];
foreach ($imena as $i => [$ime, $prez, $s]) {
    $mail = bez_dijakritika(mb_strtolower($ime . '.' . $prez)) . '@primjer.hr';
    $id = umetni('Clanovi', [
        'Ime' => $ime, 'Prezime' => $prez, 'SekcijaId' => $sek[$s], 'Ulica' => $izaberi($ulice), 'KucniBroj' => (string) mt_rand(1, 120),
        'PostanskiBroj' => $mjesta[$s][1], 'Mjesto' => $mjesta[$s][0], 'MobilniTelefon' => sprintf('09%d %03d %04d', mt_rand(1, 9), mt_rand(100, 999), mt_rand(1000, 9999)),
        'Email' => $i % 7 === 6 ? null : $mail, 'DatumRodjenja' => sprintf('%d-%02d-%02d', mt_rand(1952, 1998), mt_rand(1, 12), mt_rand(1, 28)),
        'ClanOd' => sprintf('%d-01-01', mt_rand(1985, 2023)), 'BrojIskaznice' => sprintf('LI-%05d', 10000 + $i * 37),
        'IspitDatum' => sprintf('%d-%02d-15', mt_rand(1980, 2020), mt_rand(3, 10)), 'IspitMjesto' => 'Virovitica',
        'Status' => 0, 'Kreirano' => $sada, 'Azurirano' => $sada,
    ]);
    $clanovi[] = ['Id' => $id, 'Ime' => $ime, 'Prezime' => $prez, 'Sekcija' => $s];
    umetni('ClanVidljivost', ['ClanId' => $id, 'Mobitel' => $i % 3 !== 0 ? 1 : 0, 'Fiksni' => 0, 'Email' => $i % 2, 'Mjesto' => 1, 'Adresa' => 0, 'Azurirano' => $sada]);
    if ($i % 4 !== 3) {
        umetni('ClanVozila', ['ClanId' => $id, 'Oznaka' => sprintf('VT %03d-%s', mt_rand(100, 999), $izaberi(['AB', 'CD', 'EF', 'GH', 'IK', 'LM', 'NP', 'RS'])), 'Zadano' => 1]);
    }
}
// arhivirani član
umetni('Clanovi', ['Ime' => 'Mirko', 'Prezime' => 'Stari', 'SekcijaId' => $sek['Jug'], 'Status' => 1, 'DatumIzlaska' => $dan(-200), 'RazlogIzlaska' => 'Preseljenje', 'Kreirano' => $sada, 'Azurirano' => $sada]);

// funkcije
$fun = fn(string $n) => (int) vrijednost('SELECT Id FROM Funkcije WHERE Naziv=?', [$n]);
foreach ([[0, 'Predsjednik', null], [10, 'Dopredsjednik', null], [1, 'Tajnik', null], [20, 'Blagajnik', null], [2, 'Lovnik', null],
          [3, 'Lovočuvar', 'Sjever'], [12, 'Lovočuvar', 'Jug'], [22, 'Lovočuvar', 'Lug'], [4, 'Upravni odbor', null], [13, 'Nadzorni odbor', null]] as [$ci, $fn, $fs]) {
    if ($fid = $fun($fn)) {
        umetni('ClanFunkcije', ['ClanId' => $clanovi[$ci]['Id'], 'FunkcijaId' => $fid, 'Od' => '2023-04-01', 'SekcijaId' => $fs ? $sek[$fs] : null]);
    }
}

// ---------- demo računi (lozinka "demo") ----------
$uloga = fn(string $n) => (int) vrijednost('SELECT Id FROM Uloge WHERE Naziv=?', [$n]);
$admin = red('SELECT * FROM Korisnici ORDER BY Id LIMIT 1');
azuriraj('Korisnici', (int) $admin['Id'], ['KorisnickoIme' => 'admin', 'LozinkaHash' => hash_lozinke($LOZINKA), 'ClanId' => $clanovi[0]['Id'], 'PrikaznoIme' => null,
    'MoraPromijenitiLozinku' => 0, 'Aktivan' => 1, 'Odobren' => 1, 'SigurnosniZig' => bin2hex(random_bytes(8))]);
$racuni = [
    ['k' => 'admin', 'naziv' => 'Glavni administrator', 'opis' => 'Sve: članovi, članarina, izvještaji, lovište, dezinfekcija, postavke', 'id' => (int) $admin['Id']],
];
foreach ([['tajnik', 1, 'Predsjednik / Tajnik', null, 'Tajnik', 'Članovi, članarina, radne akcije, izvještaji – sve sekcije'],
          ['lovocuvar', 3, 'Lovočuvar', 'Sjever', 'Lovočuvar (sekcija Sjever)', 'Lovište, lovački dnevnik, knjiga dezinfekcije – samo svoja sekcija'],
          ['clan', 5, null, null, 'Član (bez funkcije)', 'Što vidi običan član: svoje akcije, članarina, kalendar, imenik, oglasnik, poruke']] as [$kor, $ci, $ul, $us, $naz, $opis]) {
    $kid = umetni('Korisnici', ['KorisnickoIme' => $kor, 'LozinkaHash' => hash_lozinke($LOZINKA), 'ClanId' => $clanovi[$ci]['Id'], 'Aktivan' => 1,
        'MoraPromijenitiLozinku' => 0, 'Kreirano' => $sada, 'NeuspjelePrijave' => 0, 'SigurnosniZig' => bin2hex(random_bytes(8)), 'Odobren' => 1, 'OdobrenDatum' => $sada]);
    if ($ul && ($uid = $uloga($ul))) {
        umetni('KorisnikUloge', ['KorisnikId' => $kid, 'UlogaId' => $uid, 'SekcijaId' => $us ? $sek[$us] : null]);
    }
    $racuni[] = ['k' => $kor, 'naziv' => $naz, 'opis' => $opis, 'id' => $kid];
}
$kor = array_column($racuni, 'id', 'k');
spremi_postavku('Demo.Racuni', json_encode(array_map(fn($r) => ['k' => $r['k'], 'naziv' => $r['naziv'], 'opis' => $r['opis']], $racuni), JSON_UNESCAPED_UNICODE));
spremi_postavku('Demo.Lozinka', $LOZINKA);

// ---------- radne akcije ----------
$vrste = redovi('SELECT * FROM VrsteAkcija WHERE Aktivna=1');
$opisi = ['Košnja oko hranilišta', 'Popravak krova na čeki', 'Prihrana divljači – kukuruz', 'Čišćenje lovačke kuće', 'Sadnja remiza', 'Postavljanje solišta',
    'Izrada nove čeke', 'Popravak puta prema lugu', 'Dovoz sijena', 'Uređenje okoliša lovačke kuće'];
foreach ($clanovi as $i => $c) {
    $n = $i % 5 === 4 ? 1 : mt_rand(2, 7);
    for ($j = 0; $j < $n; $j++) {
        $v = $izaberi($vrste);
        $d = -mt_rand(3, 300);
        $st = $d > -14 && mt_rand(0, 2) === 0 ? AKCIJA_CEKA : (mt_rand(0, 25) === 0 ? AKCIJA_ODBIJENO : AKCIJA_ODOBRENO);
        $sati = mt_rand(2, 8);
        umetni('RadneAkcije', ['ClanId' => $c['Id'], 'Datum' => $dan($d), 'VrstaAkcijeId' => $v['Id'], 'Sati' => $sati, 'Opis' => $izaberi($opisi),
            'Status' => $st, 'Bodovi' => $st === AKCIJA_ODOBRENO ? (float) $v['StandardniBodovi'] * max(1, intdiv($sati, 4)) : null,
            'RazlogOdbijanja' => $st === AKCIJA_ODBIJENO ? 'Nije bio prisutan prema popisu lovnika.' : null,
            'UnioKorisnikId' => $kor['admin'], 'Kreirano' => $dan($d, '18:00:00'),
            'OdlucioKorisnikId' => $st !== AKCIJA_CEKA ? $kor['admin'] : null, 'OdlucioIme' => $st !== AKCIJA_CEKA ? 'Tomislav Horvat' : null,
            'Odluceno' => $st !== AKCIJA_CEKA ? $dan($d + 2, '19:00:00') : null]);
    }
}

// ---------- članarina (3 rate) ----------
$god = (int) date('Y');
foreach ($clanovi as $i => $c) {
    $cid = umetni('Clanarine', ['ClanId' => $c['Id'], 'Godina' => $god, 'Iznos' => 180, 'Valuta' => 'EUR']);
    foreach ([[1, 80, "$god-03-31"], [2, 50, "$god-06-30"], [3, 50, "$god-09-30"]] as [$rb, $iz, $do]) {
        umetni('ClanarinaRate', ['ClanarinaId' => $cid, 'RedniBroj' => $rb, 'Iznos' => $iz, 'Dospijece' => $do]);
    }
    $placeno = $i % 6 === 5 ? 0 : ($i % 4 === 3 ? 80 : ($i % 5 === 2 ? 130 : 180));
    if ($placeno >= 80) {
        umetni('Uplate', ['ClanarinaId' => $cid, 'Datum' => "$god-03-" . sprintf('%02d', mt_rand(1, 28)), 'Iznos' => 80, 'Napomena' => '1. rata']);
    }
    if ($placeno >= 130) {
        umetni('Uplate', ['ClanarinaId' => $cid, 'Datum' => "$god-06-" . sprintf('%02d', mt_rand(1, 28)), 'Iznos' => 50, 'Napomena' => '2. rata']);
    }
    if ($placeno >= 180) {
        umetni('Uplate', ['ClanarinaId' => $cid, 'Datum' => "$god-09-" . sprintf('%02d', mt_rand(1, 28)), 'Iznos' => 50, 'Napomena' => '3. rata']);
    }
}

// ---------- kalendar ----------
$dog = [
    ['Radna akcija – košnja hranilišta', 'Radna akcija', 5, '08:00:00', '13:00:00', 'Hranilište Hrastik', [$sek['Sjever']]],
    ['Godišnja skupština udruge', 'Sastanak / Skupština', 12, '18:00:00', '20:00:00', 'Lovačka kuća Novo Selo', []],
    ['Zajednički lov na divlje svinje', 'Zajednički lov', 19, '07:00:00', '15:00:00', 'Zborno mjesto kod lovačke kuće', []],
    ['Gađanje glinenih golubova', 'Gađanje', 26, '09:00:00', '13:00:00', 'Streljana Lugarje', [$sek['Lug']]],
    ['Radna akcija – popravak čeka', 'Radna akcija', 9, '08:00:00', '12:00:00', 'Lug, revir 3', [$sek['Lug']]],
    ['Lovačka večer', 'Druženje', 33, '19:00:00', '23:00:00', 'Lovačka kuća Novo Selo', []],
    ['Sastanak upravnog odbora', 'Sastanak / Skupština', -6, '18:00:00', '19:30:00', 'Lovačka kuća Novo Selo', []],
    ['Radna akcija – prihrana', 'Radna akcija', -13, '08:00:00', '11:00:00', 'Jug – hranilišta', [$sek['Jug']]],
];
foreach ($dog as [$nas, $vr, $d, $od, $do, $mj, $sekc]) {
    $did = umetni('Dogadjaji', ['Naslov' => $nas, 'Vrsta' => $vr, 'Pocetak' => $dan($d, $od), 'Kraj' => $dan($d, $do), 'CijeliDan' => 0, 'Mjesto' => $mj,
        'Opis' => 'Molimo potvrdite dolazak u aplikaciji.', 'ZaSve' => $sekc ? 0 : 1, 'KreiraoId' => $kor['tajnik'], 'KreiraoIme' => 'Ivan Kovačević', 'Kreirano' => $dan(-20, '10:00:00')]);
    foreach ($sekc as $s) {
        umetni('DogadjajSekcije', ['DogadjajId' => $did, 'SekcijaId' => $s]);
    }
    foreach (array_rand($clanovi, 9) as $ci) {
        umetni('DogadjajOdgovori', ['DogadjajId' => $did, 'ClanId' => $clanovi[$ci]['Id'], 'Dolazi' => mt_rand(0, 4) ? 1 : 0, 'Vrijeme' => $dan(-3, '20:15:00')]);
    }
}

// ---------- oglasnik ----------
foreach ([['admin', 'Optika', 'Dalekozor 10x42, kao nov', 'Dalekozor 10x42 s torbicom i remenom, korišten jednu sezonu. Oštra slika i u sumrak.', 320, 'Kao novo'],
          ['clan', 'Pribor i oprema', 'Prsluk za lov, narančasti, XL', 'Signalni prsluk veličine XL, nekorišten.', 25, 'Novo'],
          ['tajnik', 'Odjeća i obuća', 'Lovačke čizme br. 44', 'Gumene čizme s postavom, br. 44, nošene nekoliko puta.', 45, 'Rabljeno'],
          ['lovocuvar', 'Lovački psi', 'Štenci njemačkog lovnog terijera', 'Leglo od 4 štenca, roditelji s rodovnikom i radnim ispitom. Cijena po dogovoru.', null, 'Novo'],
          ['clan', 'Ostalo', 'Hranilica za fazane', 'Samogradnja, pocinčani lim, kapacitet 25 kg.', 40, 'Rabljeno']] as $i => [$kk, $kat, $nas, $op, $cij, $stanje]) {
    umetni('Oglasi', ['KorisnikId' => $kor[$kk], 'Kategorija' => $kat, 'Naslov' => $nas, 'Opis' => $op, 'Cijena' => $cij, 'PoDogovoru' => $cij === null ? 1 : 0,
        'Stanje' => $stanje, 'Mjesto' => 'Novo Selo', 'PrikaziTelefon' => 1, 'Status' => 0, 'Pregleda' => mt_rand(5, 60), 'Kreirano' => $dan(-$i * 4 - 1, '12:00:00'), 'Azurirano' => $dan(-$i * 4 - 1, '12:00:00')]);
}

// ---------- poruke ----------
$raz = function (string $a, string $b, array $poruke) use ($kor, $dan) {
    $zadnja = $dan(-1, '20:40:00');
    $rid = umetni('Razgovori2', ['KorisnikA' => $kor[$a], 'KorisnikB' => $kor[$b], 'Kreirano' => $dan(-2, '09:00:00'), 'ZadnjaPoruka' => $zadnja]);
    foreach ($poruke as $i => [$tko, $tekst]) {
        umetni('Poruke2', ['RazgovorId' => $rid, 'PosiljateljId' => $kor[$tko], 'Tekst' => $tekst, 'Vrijeme' => $dan(-2 + intdiv($i, 2), sprintf('%02d:%02d:00', 9 + $i, 10 + $i * 3)), 'Procitano' => $i < count($poruke) - 1 ? 1 : 0]);
    }
};
$raz('admin', 'clan', [['admin', 'Pozdrav! Možeš li u subotu na radnu akciju kod hranilišta?'], ['clan', 'Mogu, dolazim oko 8.'], ['admin', 'Super, ponesi kosu ako imaš.'], ['clan', 'Hoću. Vidimo se!']]);
$raz('lovocuvar', 'clan', [['lovocuvar', 'Na čeki 7 je slomljena prečka, ne penji se dok je ne popravimo.'], ['clan', 'Hvala na upozorenju!']]);

// ---------- dezinfekcija ----------
dez_osiguraj_stanice();
$koord = ['Sjever' => [45.8455, 17.3721], 'Jug' => [45.8012, 17.3894], 'Lug' => [45.8230, 17.4310]];
foreach ($sek as $n => $sid) {
    q("UPDATE DezStanice SET Lat=?, Lon=?, Radijus=100, Sredstvo='Virkon S 1 %', Naziv=? WHERE Vrsta='F' AND SekcijaId=?", [$koord[$n][0], $koord[$n][1], 'Dezinfekcija ' . $n . ' – lovačka kuća', $sid]);
}
$razlozi = redovi('SELECT * FROM DezRazlozi');
foreach (range(-20, -1) as $d) {
    foreach (array_rand($clanovi, mt_rand(2, 5)) as $ci) {
        $c = $clanovi[$ci];
        $st = red("SELECT * FROM DezStanice WHERE Vrsta='F' AND SekcijaId=?", [$sek[$c['Sekcija']]]);
        $oz = vrijednost('SELECT Oznaka FROM ClanVozila WHERE ClanId=?', [$c['Id']]);
        $raz2 = $izaberi($razlozi);
        $h = mt_rand(5, 16);
        foreach (['D' => $h, 'O' => $h + mt_rand(2, 5)] as $smjer => $sat) {
            if ($smjer === 'O' && mt_rand(0, 3) === 0) {
                continue; // odlazak se ne upisuje uvijek (član živi u lovištu)
            }
            umetni('DezUpisi', ['StanicaId' => $st['Id'], 'SekcijaId' => $st['SekcijaId'], 'Vrijeme' => $dan($d, sprintf('%02d:%02d:00', $sat, mt_rand(0, 59))), 'Smjer' => $smjer,
                'ClanId' => $c['Id'], 'Ime' => $c['Ime'], 'Prezime' => $c['Prezime'], 'Gost' => 0, 'Oznaka' => $oz ?: null, 'Razlog' => $raz2['Naziv'], 'Vozilo' => $oz ? 1 : 0,
                'Obuca' => 1, 'Oprema' => 1, 'UpisaoIme' => $c['Ime'] . ' ' . $c['Prezime'], 'Lat' => $st['Lat'] + mt_rand(-30, 30) / 100000, 'Lon' => $st['Lon'] + mt_rand(-30, 30) / 100000,
                'Tocnost' => mt_rand(5, 25), 'Udaljenost' => mt_rand(3, 40), 'Lokacija' => DEZ_LOK_OK, 'Naknadno' => 0, 'Ponisteno' => 0,
                'Grupa' => bin2hex(random_bytes(8)), 'Kreirano' => $dan($d, sprintf('%02d:00:00', $sat)), 'Izvanmrezno' => mt_rand(0, 6) === 0 ? 1 : 0]);
        }
    }
}
spremi_postavku('Dez.InspekcijaLozinka', sifriraj('demo123'));

// ---------- lovište: granica, čeke, zauzeća, dnevnik ----------
$granica = [[45.862, 17.335], [45.866, 17.392], [45.851, 17.448], [45.826, 17.462], [45.796, 17.441], [45.786, 17.402], [45.792, 17.352], [45.818, 17.328]];
spremi_postavku('Revir.Granice', json_encode(['oblici' => [['tip' => 'poligon', 'ime' => 'Lovište „Srndać“ (izmišljeno)', 'koord' => $granica]], 'tocaka' => 0], JSON_UNESCAPED_UNICODE));
$vrstaId = fn(string $n) => (int) vrijednost('SELECT Id FROM RevirVrste WHERE Naziv=?', [$n]);
$naprave = [];
$nazivi = ['Hrastik', 'Kod potoka', 'Velika livada', 'Bukova kosa', 'Kukuruzište', 'Stara pruga', 'Lug 1', 'Lug 2', 'Brdo', 'Jasenik', 'Pod vinogradom', 'Kraj šume'];
foreach ($nazivi as $i => $n) {
    $s = ['Sjever', 'Jug', 'Lug'][$i % 3];
    $vr = $i % 5 === 4 ? 'Hranilište' : ($i % 4 === 1 ? 'Visoka čeka' : ($i % 6 === 5 ? 'Zatvorena čeka' : 'Čeka'));
    $naprave[] = ['Id' => umetni('RevirNaprave', ['Broj' => (string) ($i + 1), 'Naziv' => $n, 'Vrsta' => $vr, 'VrstaId' => $vrstaId($vr), 'SekcijaId' => $sek[$s],
        'Lat' => $koord[$s][0] + mt_rand(-90, 90) / 10000, 'Lon' => $koord[$s][1] + mt_rand(-140, 140) / 10000, 'Aktivna' => 1, 'KreiraoIme' => 'Tomislav Horvat', 'Kreirano' => $sada]),
        'Naziv' => $n, 'Sekcija' => $s, 'Vrsta' => $vr];
}
$ceke = array_values(array_filter($naprave, fn($n) => $n['Vrsta'] !== 'Hranilište'));
foreach (range(-25, -1) as $d) {
    foreach (array_rand($ceke, 2) as $ni) {
        $n = $ceke[$ni];
        $kand = array_values(array_filter($clanovi, fn($c) => $c['Sekcija'] === $n['Sekcija']));
        $c = $izaberi($kand);
        $od = $dan($d, sprintf('%02d:%02d:00', mt_rand(16, 18), mt_rand(0, 59)));
        $do = $dan($d, sprintf('%02d:%02d:00', mt_rand(20, 22), mt_rand(0, 59)));
        $zid = umetni('RevirZauzeca', ['NapravaId' => $n['Id'], 'SekcijaId' => $sek[$n['Sekcija']], 'ClanId' => $c['Id'], 'Ime' => $c['Ime'] . ' ' . $c['Prezime'],
            'Od' => $od, 'VrijediDo' => $dan($d + 1, '03:00:00'), 'Kraj' => $do, 'KrajNacin' => 'odjava']);
        umetni('LovackiDnevnik', ['ZauzeceId' => $zid, 'ClanId' => $c['Id'], 'Ime' => $c['Ime'] . ' ' . $c['Prezime'], 'SekcijaId' => $sek[$n['Sekcija']],
            'NapravaId' => $n['Id'], 'Naprava' => $n['Naziv'], 'Od' => $od, 'Do' => $do, 'Automatski' => 1]);
    }
}
// trenutno zauzete (vrijedi do sutra 03:00 – datumi se kod noćnog povratka pomiču)
foreach ([[0, 2], [4, 11]] as [$ni, $ci]) {
    $n = $ceke[$ni];
    umetni('RevirZauzeca', ['NapravaId' => $n['Id'], 'SekcijaId' => $sek[$n['Sekcija']], 'ClanId' => $clanovi[$ci]['Id'], 'Ime' => $clanovi[$ci]['Ime'] . ' ' . $clanovi[$ci]['Prezime'],
        'Od' => $dan(0, '06:30:00'), 'VrijediDo' => $dan(1, '03:00:00')]);
}

// ---------- demo uključen ----------
spremi_postavku('Demo.Aktivno', '1');
spremi_postavku('Demo.Kljuc', $kljuc = bin2hex(random_bytes(12)));
q('DELETE FROM Dnevnik');
echo "Demo podaci su dodani.\nRačuni: admin, tajnik, lovocuvar, clan – lozinka: $LOZINKA\nUpravljanje demom: index.php?p=demo-vlasnik&k=$kljuc\n";
