<?php
/**
 * Lovački psi članova. Za svakog aktivnog psa s uključenim bodovanjem (uključuje ovlaštena osoba, npr. uz ugovor s udrugom)
 * jednom godišnje na zadani datum (Sustav → Bodovi) automatski nastaje radna akcija za držanje psa – odmah odobrena,
 * bodovi iz Vrste radnih akcija. Svaki pas najviše jednom godišnje (PsiBodovi), i ručni upis iste vrste se ne duplicira.
 */
declare(strict_types=1);

/** Vrste radnih akcija za pse (bodovna lista; bodovi se mijenjaju u Sustav → Vrste radnih akcija). */
const VRSTE_PSI = [
    'godisnje' => ['Lovački pas', 0],               // posjedovanje psa – automatski 1.1.
    'lov' => ['Pas na lovu', 0],                    // prijavljen pas na zajedničkom lovu – automatski nakon lova
    'potraga' => ['Potraga s psom (krvni trag)', 1], // član sam prijavljuje (Moje radne akcije)
    // rad psa – član sam prijavljuje (Moje radne akcije), samo za psa koji taj rad radi
    'rad-pogon' => ['Rad psa: pogon (goniči)', 1],
    'rad-sitna' => ['Rad psa: lov na sitnu divljač', 1],
    'rad-ptic' => ['Rad psa: traženje i markiranje (ptičarenje)', 1],
    'rad-izgon' => ['Rad psa: pretraživanje i izgon iz šikare', 1],
    'rad-jazbina' => ['Rad psa: rad u jazbini', 1],
    'rad-donosenje' => ['Rad psa: donošenje (aport)', 1],
];

/** Što pas radi: ključ => [ikona, naziv, vrsta radne akcije (VRSTE_PSI)] */
const RADOVI_PSA = [
    'potraga' => ['🩸', 'potraga (krvni trag)', 'potraga'],
    'pogon' => ['📯', 'pogon (gonič)', 'rad-pogon'],
    'sitna' => ['🐇', 'lov na sitnu divljač', 'rad-sitna'],
    'ptic' => ['🪶', 'traženje i markiranje (ptičar)', 'rad-ptic'],
    'izgon' => ['🌿', 'pretraživanje i izgon iz šikare', 'rad-izgon'],
    'jazbina' => ['🦡', 'rad u jazbini', 'rad-jazbina'],
    'donosenje' => ['🦆', 'donošenje (aport)', 'rad-donosenje'],
];
/** Radovi psa kao niz ključeva. */
function radovi_psa(array $p): array
{
    $r = array_values(array_intersect(explode(',', (string) ($p['Radovi'] ?? '')), array_keys(RADOVI_PSA)));
    if (!$r && !empty($p['Potraga'])) {
        $r = ['potraga'];
    }
    return $r;
}
/** Oznake radova (HTML). */
function radovi_psa_oznake(array $p): string
{
    return implode(' ', array_map(fn($r) => '<span class="badge bg-success-subtle text-success-emphasis border fw-normal">' . RADOVI_PSA[$r][0] . ' ' . e(RADOVI_PSA[$r][1]) . '</span>', radovi_psa($p)));
}
/** [vrstaAkcijeId => ključ rada] za sve radove psa. */
function vrste_rada_psa(): array
{
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (RADOVI_PSA as $kl => $r) {
            $m[vrsta_akcije_psi($r[2])] = $kl;
        }
    }
    return $m;
}
function vrsta_akcije_psi(string $kljuc = 'godisnje'): int
{
    [$naziv, $aktivna] = VRSTE_PSI[$kljuc];
    $pk = 'Psi.Vrsta.' . $kljuc;
    $id = (int) postavka($pk, $kljuc === 'godisnje' ? postavka('Psi.VrstaAkcije', '0') : '0');
    if ($id && vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=?', [$id])) {
        return $id;
    }
    $id = (int) (vrijednost('SELECT Id FROM VrsteAkcija WHERE Naziv=? LIMIT 1', [$naziv]) ?: 0);
    if (!$id) {
        $id = umetni('VrsteAkcija', ['Naziv' => $naziv, 'StandardniBodovi' => '0.0', 'Aktivna' => $aktivna]);
    }
    spremi_postavku($pk, (string) $id);
    return $id;
}
/** [kljuc => ['naziv','bodovi','id']] */
function bodovna_lista_psi(): array
{
    $r = [];
    foreach (VRSTE_PSI as $kl => $x) {
        $id = vrsta_akcije_psi($kl);
        $v = red('SELECT Naziv, StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$id]);
        $r[$kl] = ['id' => $id, 'naziv' => $v['Naziv'] ?? $x[0], 'bodovi' => (float) ($v['StandardniBodovi'] ?? 0)];
    }
    return $r;
}
/** Dan godišnjih bodova za pse 'MM-DD' (Sustav → Bodovi, zadano 01-01). */
function psi_bodovi_dan(): string
{
    $d = (string) postavka('Psi.BodoviDan', '01-01');
    return preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $d) ? $d : '01-01';
}
/** Smije li trenutni korisnik uključiti / isključiti psa i bodovanje (ovlaštena osoba). */
function smije_bodovanje_psa(?array $clan = null): bool
{
    return $clan ? moze_clana(P_CLANOVI_UREDI, $clan) : ima(P_CLANOVI_UREDI);
}
function bodovi_psa_standard(): float
{
    return bodovna_lista_psi()['godisnje']['bodovi'];
}
/** Aktivni psi člana [Id => opis] */
function aktivni_psi(int $clanId): array
{
    $r = [];
    foreach (redovi('SELECT * FROM LovniPsi WHERE ClanId=? AND Aktivan=1 ORDER BY Ime', [$clanId]) as $p) {
        $r[(int) $p['Id']] = $p;
    }
    return $r;
}
/** "1,3" → [1,3] */
function psi_ids(?string $s): array
{
    return array_values(array_filter(array_map('intval', explode(',', (string) $s))));
}

function psi_clana(int $clanId): array
{
    $psi = redovi('SELECT * FROM LovniPsi WHERE ClanId=? ORDER BY Aktivan DESC, Ime', [$clanId]);
    foreach ($psi as &$p) {
        $p['Ispiti'] = redovi('SELECT * FROM PsiIspiti WHERE PasId=? ORDER BY Datum DESC, Id DESC', [$p['Id']]);
        $p['Bodovano'] = array_column(redovi('SELECT Godina, AkcijaId FROM PsiBodovi WHERE PasId=? ORDER BY Godina DESC', [$p['Id']]), 'AkcijaId', 'Godina');
        $p['Akcije'] = redovi('SELECT a.*, v.Naziv AS VrstaNaziv FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.PasId=? ORDER BY a.Datum DESC, a.Id DESC LIMIT 30', [$p['Id']]);
    }
    return $psi;
}
/** Pasmine koje su uobičajeno krvosljednici (kvačica „potraga“ se predlaže sama; uvijek se može promijeniti). */
const PASMINE_POTRAGA = ['krvoslijed', 'krvosljed', 'jazavčar', 'jazavcar', 'oštrodlaki ptičar', 'ostrodlaki pticar', 'drahthaar', 'kratkodlaki ptičar', 'kratkodlaki pticar', 'brak', 'jagdterijer', 'vižla', 'vizla', 'weimaraner'];
function pasmina_za_potragu(?string $pasmina): bool
{
    $k = kljuc($pasmina);
    foreach (PASMINE_POTRAGA as $x) {
        if ($x !== '' && str_contains($k, kljuc($x))) {
            return true;
        }
    }
    return false;
}
function opis_psa(array $p): string
{
    return $p['Ime'] . ($p['Pasmina'] ? ' (' . $p['Pasmina'] . ')' : '');
}

/**
 * Godišnji bodovi za držanje psa: za aktivne pse s uključenim bodovanjem, kad je dan (Sustav → Bodovi) u godini $godina prošao.
 * Odmah odobreno, bodovi iz vrste. Datum upisa = zadani dan, ili kasnije ako je pas / bodovanje uključeno tek nakon tog dana.
 * Svaki pas najviše jednom godišnje; postojeći (i ručni) upis iste vrste za tog člana u toj godini se samo poveže – nema duplikata.
 * $clanId = samo za jednog člana. Vraća broj novih upisa.
 */
function upisi_bodove_psa(int $godina, ?int $clanId = null, ?array $k = null): int
{
    $dan = $godina . '-' . psi_bodovi_dan();
    if ($dan > danas()) {
        return 0;
    }
    $vrsta = vrsta_akcije_psi();
    $bod = (float) vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$vrsta]);
    if ($bod <= 0) {
        return 0; // bez bodova u vrsti nema upisa (upozorenje na kartici psa i u Sustav → Bodovi)
    }
    $uvjet = $clanId ? ' AND p.ClanId=' . (int) $clanId : '';
    $psi = redovi("SELECT p.* FROM LovniPsi p JOIN Clanovi c ON c.Id=p.ClanId
        WHERE p.Aktivan=1 AND p.Bodovanje=1 AND c.Status=0 $uvjet
          AND NOT EXISTS (SELECT 1 FROM PsiBodovi b WHERE b.PasId=p.Id AND b.Godina=?) ORDER BY p.ClanId, p.Id", [$godina]);
    $n = 0;
    transakcija(function () use ($psi, $godina, $dan, $vrsta, $bod, $k, &$n) {
        foreach ($psi as $p) {
            $pid = (int) $p['Id'];
            $cid = (int) $p['ClanId'];
            // već postoji upis te vrste u toj godini (npr. ručno upisan): za ovog psa ili bez psa i još nepovezan
            $postoji = vrijednost('SELECT Id FROM RadneAkcije a WHERE a.ClanId=? AND a.VrstaAkcijeId=? AND a.Status<>? AND substr(a.Datum,1,4)=?
                AND (a.PasId=? OR (a.PasId IS NULL AND NOT EXISTS (SELECT 1 FROM PsiBodovi b WHERE b.AkcijaId=a.Id))) ORDER BY (a.PasId IS NULL), a.Id LIMIT 1',
                [$cid, $vrsta, AKCIJA_ODBIJENO, (string) $godina, $pid]);
            if ($postoji) {
                q('UPDATE RadneAkcije SET PasId=COALESCE(PasId, ?) WHERE Id=?', [$pid, (int) $postoji]);
                umetni('PsiBodovi', ['PasId' => $pid, 'Godina' => $godina, 'AkcijaId' => (int) $postoji]);
                continue;
            }
            $datum = max($dan, substr((string) ($p['BodovanjeOd'] ?? ''), 0, 10), substr((string) ($p['Kreirano'] ?? ''), 0, 10));
            $datum = min($datum, danas());
            $aid = umetni('RadneAkcije', [
                'ClanId' => $cid, 'Datum' => $datum, 'VrstaAkcijeId' => $vrsta, 'VrstaSlobodno' => null, 'Sati' => null,
                'Opis' => '🐕 ' . opis_psa($p) . ' – ' . $godina . '.', 'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec($bod), 'RazlogOdbijanja' => null,
                'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(), 'PasId' => $pid,
                'OdlucioKorisnikId' => $k['Id'] ?? null, 'OdlucioIme' => $k['Naziv'] ?? 'automatski', 'Odluceno' => sada(),
            ]);
            umetni('PsiBodovi', ['PasId' => $pid, 'Godina' => $godina, 'AkcijaId' => $aid]);
            $n++;
        }
    });
    if ($n) {
        dnevnik('Bodovi za lovačke pse', null, null, "$godina.: $n × " . broj($bod, 2) . ' b. (odmah odobreno)');
    }
    return $n;
}

/**
 * Zajednički lov sa psima: za prošle termine (vrsta „lov sa psima“) svakom prijavljenom psu radna akcija „Pas na lovu“ na čekanju.
 * Vraća broj novih upisa.
 */
function psi_bodovi_lova(): int
{
    $vrste = array_column(array_filter(vrste_dogadjaja(), fn($v) => !empty($v['psi'])), 'naziv');
    if (!$vrste) {
        return 0;
    }
    $dog = redovi('SELECT * FROM Dogadjaji WHERE PsiBodovano IS NULL AND substr(COALESCE(Kraj, Pocetak),1,10) < ? AND Vrsta IN (' . implode(',', array_fill(0, count($vrste), '?')) . ')',
        array_merge([danas()], $vrste));
    $vrstaId = vrsta_akcije_psi('lov');
    $n = 0;
    foreach ($dog as $d) {
        transakcija(function () use ($d, $vrstaId, &$n) {
            $datum = substr($d['Pocetak'], 0, 10);
            foreach (redovi('SELECT o.ClanId, o.PsiIds FROM DogadjajOdgovori o JOIN Clanovi c ON c.Id=o.ClanId WHERE o.DogadjajId=? AND o.Dolazi=1 AND o.PsiIds IS NOT NULL', [$d['Id']]) as $o) {
                foreach (psi_ids($o['PsiIds']) as $pid) {
                    $p = red('SELECT * FROM LovniPsi WHERE Id=? AND ClanId=?', [$pid, $o['ClanId']]);
                    if (!$p || vrijednost('SELECT 1 FROM RadneAkcije WHERE PasId=? AND VrstaAkcijeId=? AND substr(Datum,1,10)=?', [$pid, $vrstaId, $datum])) {
                        continue;
                    }
                    umetni('RadneAkcije', [
                        'ClanId' => (int) $o['ClanId'], 'Datum' => $datum, 'VrstaAkcijeId' => $vrstaId, 'VrstaSlobodno' => null, 'Sati' => null,
                        'Opis' => '🐕 ' . opis_psa($p) . ' – ' . $d['Naslov'], 'Status' => AKCIJA_CEKA, 'Bodovi' => null, 'RazlogOdbijanja' => null,
                        'FotoDatoteka' => null, 'UnioKorisnikId' => null, 'Kreirano' => sada(), 'PasId' => $pid,
                    ]);
                    $n++;
                }
            }
            q('UPDATE Dogadjaji SET PsiBodovano=? WHERE Id=?', [sada(), $d['Id']]);
        });
    }
    return $n;
}

/** Jednom dnevno: psi na prošlim zajedničkim lovovima + godišnji bodovi za držanje psa (od zadanog dana). */
function psi_godisnji_bodovi(): void
{
    $oznaka = podaci('.psi-dan');
    if (is_file($oznaka) && file_get_contents($oznaka) === danas()) {
        return;
    }
    @file_put_contents($oznaka, danas());
    try {
        bodovna_lista_psi(); // vrste radnih akcija za pse postoje u bodovnoj listi
        $n = psi_bodovi_lova();
        if ($n) {
            dnevnik('Bodovi za pse na zajedničkom lovu', null, null, "$n upisa na čekanju");
        }
    } catch (Throwable $e) {
        error_log('Psi na lovu: ' . $e->getMessage());
    }
    try {
        // svaki dan nakon zadanog dana: novi / tek uključeni psi dobivaju bodove odmah (svaki pas jednom godišnje)
        upisi_bodove_psa((int) date('Y'));
    } catch (Throwable $e) {
        error_log('Bodovi za pse: ' . $e->getMessage());
    }
}

/**
 * Spremanje psa / ispita (kartica člana i Moj profil). Prava provjerava pozivatelj.
 * $ovlasten = smije uključiti / isključiti psa i bodovanje (član na Moj profil bez tog prava ne mijenja ta dva polja; novi pas čeka uključenje).
 */
function psi_obradi(int $id, string $radnja, string $ime, bool $ovlasten = true): void
{
    if ($radnja === 'pas-spremi') {
        $pid = (int) ($_POST['pid'] ?? 0);
        $pod = ['Ime' => mb_substr(ul_str('Ime'), 0, 60), 'Pasmina' => mb_substr(ul_str('Pasmina'), 0, 80) ?: null,
            'Spol' => in_array(ul_str('Spol'), ['pas', 'kuja'], true) ? ul_str('Spol') : null, 'DatumRodjenja' => u_datum(ul_str('DatumRodjenja')),
            'Cip' => mb_substr(ul_str('Cip'), 0, 40) ?: null,
            'RodnaKnjiga' => mb_substr(ul_str('RodnaKnjiga'), 0, 40) ?: null, 'Boja' => mb_substr(ul_str('Boja'), 0, 60) ?: null, 'Dlaka' => mb_substr(ul_str('Dlaka'), 0, 40) ?: null,
            'Napomena' => mb_substr(ul_str('Napomena'), 0, 200) ?: null, 'UPopisu' => isset($_POST['UPopisu']) ? 1 : 0];
        if ($ovlasten) {
            $pod['Bodovanje'] = isset($_POST['Bodovanje']) ? 1 : 0;
        }
        $radovi = array_values(array_intersect(array_keys(RADOVI_PSA), (array) ($_POST['Radovi'] ?? [])));
        $pod['Radovi'] = $radovi ? implode(',', $radovi) : null;
        $pod['Potraga'] = in_array('potraga', $radovi, true) ? 1 : 0;
        if ($pod['Ime'] === '') {
            return;
        }
        $staro = $pid ? red('SELECT * FROM LovniPsi WHERE Id=? AND ClanId=?', [$pid, $id]) : null;
        if ($staro) {
            if ($ovlasten) {
                $pod['Aktivan'] = isset($_POST['Aktivan']) ? 1 : 0;
                if ($pod['Bodovanje'] && $pod['Aktivan'] && !($staro['Bodovanje'] && $staro['Aktivan'])) {
                    $pod['BodovanjeOd'] = danas();
                }
            }
            azuriraj('LovniPsi', $pid, $pod);
            dnevnik('Lovački pas uređen', 'Clan', $id, $ime . ': ' . $pod['Ime'] . ($ovlasten ? ' (' . (($pod['Aktivan'] && $pod['Bodovanje']) ? 'bodovanje uključeno' : 'bez bodova') . ')' : ''));
            poruka('Spremljeno.');
        } elseif (!$pid) {
            $pod += ['Bodovanje' => 0]; // član sam dodaje psa – bodovanje uključuje ovlaštena osoba
            umetni('LovniPsi', $pod + ['ClanId' => $id, 'Aktivan' => 1, 'Kreirano' => sada(), 'BodovanjeOd' => $pod['Bodovanje'] ? danas() : null]);
            dnevnik('Lovački pas dodan', 'Clan', $id, $ime . ': ' . $pod['Ime']);
            poruka($ovlasten || $pod['Bodovanje'] ? 'Pas je dodan.' : 'Pas je dodan. Bodove za psa uključuje udruga (ovlaštena osoba).');
        }
        if ($ovlasten) {
            upisi_bodove_psa((int) date('Y'), $id, korisnik()); // ako je dan već prošao – bodovi odmah
        }
    } elseif ($radnja === 'pas-bodovanje') {
        // brzi gumb ovlaštene osobe: uključi / isključi psa za bodove
        $pid = (int) ($_POST['pid'] ?? 0);
        $p = red('SELECT * FROM LovniPsi WHERE Id=? AND ClanId=?', [$pid, $id]);
        if ($p && $ovlasten) {
            $ukljuci = ($_POST['ukljuci'] ?? '') === '1';
            azuriraj('LovniPsi', $pid, $ukljuci ? ['Aktivan' => 1, 'Bodovanje' => 1, 'BodovanjeOd' => danas()] : ['Bodovanje' => 0]);
            dnevnik($ukljuci ? 'Lovački pas – bodovanje uključeno' : 'Lovački pas – bodovanje isključeno', 'Clan', $id, $ime . ': ' . $p['Ime']);
            $n = $ukljuci ? upisi_bodove_psa((int) date('Y'), $id, korisnik()) : 0;
            poruka($ukljuci ? ($n ? 'Uključeno – bodovi za ' . date('Y') . '. su upisani (odobreno).' : 'Uključeno – bodovi se upisuju sami ' . bodovi_dan_prikaz(psi_bodovi_dan()) . ' (ili su već upisani ove godine).') : 'Bodovanje isključeno – od iduće godine nema bodova za tog psa.');
        }
    } elseif ($radnja === 'pas-obrisi') {
        q('DELETE FROM LovniPsi WHERE Id=? AND ClanId=?', [(int) ($_POST['pid'] ?? 0), $id]);
        dnevnik('Lovački pas obrisan', 'Clan', $id, $ime);
    } elseif ($radnja === 'pas-ispit') {
        $pid = (int) ($_POST['pid'] ?? 0);
        if (ul_str('Naziv') !== '' && vrijednost('SELECT 1 FROM LovniPsi WHERE Id=? AND ClanId=?', [$pid, $id])) {
            umetni('PsiIspiti', ['PasId' => $pid, 'Naziv' => mb_substr(ul_str('Naziv'), 0, 100), 'Datum' => u_datum(ul_str('Datum')), 'Ocjena' => mb_substr(ul_str('Ocjena'), 0, 40) ?: null]);
            dnevnik('Ispit lovačkog psa', 'Clan', $id, $ime . ': ' . ul_str('Naziv'));
        }
    } elseif ($radnja === 'pas-ispit-obrisi') {
        q('DELETE FROM PsiIspiti WHERE Id=? AND PasId IN (SELECT Id FROM LovniPsi WHERE ClanId=?)', [(int) ($_POST['iid'] ?? 0), $id]);
    }
}
