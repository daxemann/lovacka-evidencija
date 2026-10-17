<?php
/**
 * Lovački domovi: domovi za cijelu udrugu ili za sekciju, položaj za dolazak (navigacija),
 * rezervacija po cijelim danima. Privatna rezervacija ide na odobrenje upravitelju doma,
 * događaj udruge vrijedi odmah i ima prednost (privatne rezervacije u tom razdoblju se otkazuju uz obavijest).
 * Dvostruka rezervacija nije moguća. Ostali vide samo „zauzeto od – do · ime“ (bez razloga).
 */
declare(strict_types=1);

const DOM_VRSTA_UDRUGA = 'udruga';
const DOM_VRSTA_PRIVATNO = 'privatno';
const DOM_STATUSI = ['ceka' => 'Na čekanju', 'odobreno' => 'Odobreno', 'odbijeno' => 'Odbijeno', 'otkazano' => 'Otkazano'];
const DOM_MAX_MJESECI = 12; // najviše toliko unaprijed

// ---------- Opseg ----------
/** Vidi li trenutni korisnik dom (dom bez sekcije = cijela udruga; inače članovi sekcije i uloge za tu sekciju). */
function dom_vidi(array $d): bool
{
    if ($d['SekcijaId'] === null) {
        return korisnik() !== null;
    }
    $op = kalendar_opseg();
    return $op['sve'] || in_array((int) $d['SekcijaId'], $op['sekcije'], true);
}
/** Upravlja li trenutni korisnik domom (pravo Lovački domovi; dom cijele udruge samo uloga za sve sekcije). */
function dom_upravlja(?array $d = null): bool
{
    if (!ima(P_DOMOVI)) {
        return false;
    }
    $k = korisnik();
    if ($d === null || $k['SveSekcije']) {
        return true;
    }
    return $d['SekcijaId'] !== null && in_array((int) $d['SekcijaId'], $k['Sekcije'], true);
}
/** Sekcije za koje korisnik smije dodati dom: [id => naziv], ključ 0 = cijela udruga. */
function dom_sekcije_za_uredjivanje(): array
{
    if (!ima(P_DOMOVI)) {
        return [];
    }
    $k = korisnik();
    $out = $k['SveSekcije'] ? [0 => 'Cijela udruga (sve sekcije)'] : [];
    foreach (sekcije(true) as $s) {
        if ($k['SveSekcije'] || in_array((int) $s['Id'], $k['Sekcije'], true)) {
            $out[(int) $s['Id']] = $s['Naziv'];
        }
    }
    return $out;
}

// ---------- Domovi ----------
function dom_svi(bool $iNeaktivne = false): array
{
    $sve = redovi('SELECT d.*, s.Naziv AS Sekcija FROM LovackiDomovi d LEFT JOIN Sekcije s ON s.Id=d.SekcijaId'
        . ($iNeaktivne ? '' : ' WHERE d.Aktivan=1') . ' ORDER BY (d.SekcijaId IS NOT NULL), s.Redoslijed, s.Naziv, d.Redoslijed, d.Naziv');
    return array_values(array_filter($sve, fn($d) => dom_vidi($d) || dom_upravlja($d)));
}
function dom_dom(int $id): ?array
{
    $d = red('SELECT d.*, s.Naziv AS Sekcija FROM LovackiDomovi d LEFT JOIN Sekcije s ON s.Id=d.SekcijaId WHERE d.Id=?', [$id]);
    if (!$d || !(dom_vidi($d) || dom_upravlja($d)) || (!$d['Aktivan'] && !dom_upravlja($d))) {
        return null;
    }
    return $d;
}
function dom_opseg_naziv(array $d): string
{
    return $d['SekcijaId'] === null ? 'cijela udruga' : 'sekcija ' . ($d['Sekcija'] ?? naziv_sekcije((int) $d['SekcijaId']));
}
function dom_navigacija(array $d): ?string
{
    return $d['Lat'] !== null && $d['Lon'] !== null
        ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($d['Lat'] . ',' . $d['Lon']) : null;
}
/** Ima li korisnik ijedan dom za prikaz (stavka u izborniku). */
function dom_ima_vidljivih(): bool
{
    static $r = null;
    return $r ??= (bool) dom_svi();
}

// ---------- Rezervacije ----------
/** Aktivne (na čekanju + odobrene) rezervacije doma koje se preklapaju s [od, do] (uključivo). */
function dom_aktivne(int $domId, string $od, string $do, ?int $osimId = null): array
{
    return redovi("SELECT * FROM DomRezervacije WHERE DomId=? AND Status IN ('ceka','odobreno') AND Od<=? AND Do>=?"
        . ($osimId ? ' AND Id<>' . (int) $osimId : '') . ' ORDER BY Od', [$domId, $do, $od]);
}
function dom_rezervacija(int $id): ?array
{
    return red('SELECT * FROM DomRezervacije WHERE Id=?', [$id]);
}
function dom_najkasnije(): string
{
    return date('Y-m-d', strtotime('+' . DOM_MAX_MJESECI . ' months'));
}
/** Provjera razdoblja; vraća poruku greške ili null. */
function dom_provjeri_datume(?string $od, ?string $do): ?string
{
    if (!$od || !$do) {
        return 'Upišite datum od i do.';
    }
    if ($do < $od) {
        return 'Datum „do“ ne može biti prije datuma „od“.';
    }
    if ($od < danas()) {
        return 'Ne može se rezervirati za prošle dane.';
    }
    if ($do > dom_najkasnije()) {
        return 'Rezervirati se može najviše ' . DOM_MAX_MJESECI . ' mjeseci unaprijed (do ' . datum(dom_najkasnije()) . ').';
    }
    return null;
}
function dom_broj_dana(array $r): int
{
    return (int) ((strtotime($r['Do']) - strtotime($r['Od'])) / 86400) + 1;
}
function dom_razdoblje(array $r): string
{
    return $r['Od'] === $r['Do'] ? datum($r['Od']) : datum($r['Od']) . ' – ' . datum($r['Do']);
}
/** Što vide ostali: događaj udruge s nazivom, privatno samo „Zauzeto · ime“ (bez razloga). */
function dom_oznaka(array $r): string
{
    if ($r['Vrsta'] === DOM_VRSTA_UDRUGA) {
        return '🏛️ ' . ($r['Naslov'] ?: 'Događaj udruge');
    }
    return (['ceka' => 'Na čekanju · ', 'odobreno' => 'Zauzeto · '][$r['Status']] ?? '') . $r['Ime'];
}
function dom_moja(array $r): bool
{
    $k = korisnik();
    return $k && ((int) $r['KorisnikId'] === (int) $k['Id'] || ($k['ClanId'] && (int) $r['ClanId'] === (int) $k['ClanId']));
}
/** Korisnički računi koji primaju obavijest o rezervaciji (onaj tko je rezervirao). */
function dom_primatelji_rezervacije(array $r): array
{
    $ids = $r['ClanId'] ? push_korisnici_clanova([(int) $r['ClanId']]) : [];
    if ($r['KorisnikId']) {
        $ids[] = (int) $r['KorisnikId'];
    }
    return array_values(array_unique($ids));
}
function dom_moje_ime(): string
{
    $k = korisnik();
    return $k['ClanId'] ? puno_ime(red('SELECT Ime, Prezime FROM Clanovi WHERE Id=?', [$k['ClanId']])) : $k['Naziv'];
}

/** Transakcija koja odmah zaključava bazu za pisanje – dvije istodobne rezervacije ne mogu proći provjeru. */
function dom_zakljucano(callable $fn): mixed
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $r = $fn();
        $pdo->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

/**
 * Nova rezervacija. Privatna → na čekanju (upravitelji dobivaju obavijest).
 * Događaj udruge (samo upravitelj) → odmah vrijedi; privatne rezervacije u razdoblju otkazuju se –
 * bez $potvrdjeno vraća ['sukobi' => […]] da upravitelj najprije potvrdi.
 * Vraća ['id' => …] ili ['greska' => …] ili ['sukobi' => […]].
 */
function dom_rezerviraj(int $domId, string $vrsta, ?string $od, ?string $do, string $naslov, string $napomena, bool $potvrdjeno = false): array
{
    $k = korisnik();
    $d = dom_dom($domId);
    if (!$d || !$d['Aktivan']) {
        return ['greska' => 'Lovački dom ne postoji ili nije u upotrebi.'];
    }
    if ($vrsta === DOM_VRSTA_UDRUGA) {
        if (!dom_upravlja($d)) {
            return ['greska' => 'Događaj udruge upisuje samo upravitelj doma.'];
        }
        $naslov = mb_substr(trim($naslov), 0, 100);
        if ($naslov === '') {
            return ['greska' => 'Upišite naziv događaja (npr. „Godišnja skupština“, „Lovačka zabava“).'];
        }
    } else {
        $vrsta = DOM_VRSTA_PRIVATNO;
        if (!$k['ClanId']) {
            return ['greska' => 'Privatno mogu rezervirati samo članovi (korisnički račun nije povezan s članom).'];
        }
        if (!dom_vidi($d)) {
            return ['greska' => 'Ovaj dom mogu rezervirati samo članovi njegove sekcije.'];
        }
        $naslov = '';
    }
    if ($g = dom_provjeri_datume($od, $do)) {
        return ['greska' => $g];
    }
    $napomena = mb_substr(trim($napomena), 0, 500);
    $ime = dom_moje_ime();

    $rez = dom_zakljucano(function () use ($d, $vrsta, $od, $do, $naslov, $napomena, $potvrdjeno, $k, $ime) {
        $sukobi = dom_aktivne((int) $d['Id'], $od, $do);
        $udruge = array_filter($sukobi, fn($r) => $r['Vrsta'] === DOM_VRSTA_UDRUGA);
        if ($udruge) {
            $u = reset($udruge);
            return ['greska' => 'Dom je u tom razdoblju zauzet: ' . ($u['Naslov'] ?: 'događaj udruge') . ' (' . dom_razdoblje($u) . ').'];
        }
        if ($sukobi && $vrsta === DOM_VRSTA_PRIVATNO) {
            $s = reset($sukobi);
            return ['greska' => 'Dom je u tom razdoblju već rezerviran (' . dom_razdoblje($s) . ($s['Status'] === 'ceka' ? ', na čekanju' : '') . '). Odaberite druge dane.'];
        }
        if ($sukobi && !$potvrdjeno) {
            return ['sukobi' => $sukobi];
        }
        $id = umetni('DomRezervacije', [
            'DomId' => (int) $d['Id'], 'Od' => $od, 'Do' => $do, 'Vrsta' => $vrsta,
            'Status' => $vrsta === DOM_VRSTA_UDRUGA ? 'odobreno' : 'ceka',
            'Naslov' => $naslov !== '' ? $naslov : null, 'Napomena' => $napomena !== '' ? $napomena : null,
            'ClanId' => $k['ClanId'], 'KorisnikId' => $k['Id'], 'Ime' => $ime, 'Kreirano' => sada(),
            'ObradioIme' => $vrsta === DOM_VRSTA_UDRUGA ? $ime : null, 'Obradjeno' => $vrsta === DOM_VRSTA_UDRUGA ? sada() : null,
        ]);
        $otkazane = [];
        foreach ($sukobi as $s) { // samo privatne – događaj udruge ima prednost
            azuriraj('DomRezervacije', (int) $s['Id'], ['Status' => 'otkazano', 'ObradioIme' => $ime, 'Obradjeno' => sada(),
                'Razlog' => 'Prednost ima događaj udruge: ' . $naslov . ' (' . dom_razdoblje(['Od' => $od, 'Do' => $do]) . ')']);
            $otkazane[] = $s;
        }
        return ['id' => $id, 'otkazane' => $otkazane];
    });
    if (isset($rez['greska']) || isset($rez['sukobi'])) {
        return $rez;
    }

    $raz = dom_razdoblje(['Od' => $od, 'Do' => $do]);
    if ($vrsta === DOM_VRSTA_UDRUGA) {
        dnevnik('Lovački dom – događaj udruge', 'DomRezervacija', $rez['id'], $d['Naziv'] . ' · ' . $raz . ' · ' . $naslov
            . ($rez['otkazane'] ? ' · otkazano privatnih: ' . count($rez['otkazane']) : ''));
        foreach ($rez['otkazane'] as $s) {
            push_posalji(dom_primatelji_rezervacije($s), 'domovi', '🏡 Rezervacija otkazana – ' . $d['Naziv'],
                dom_razdoblje($s) . ': prednost ima događaj udruge „' . $naslov . '“.', 'domovi/dom', ['id' => $d['Id']]);
        }
    } else {
        dnevnik('Lovački dom – zahtjev za rezervaciju', 'DomRezervacija', $rez['id'], $d['Naziv'] . ' · ' . $raz);
        $sid = $d['SekcijaId'] !== null ? (int) $d['SekcijaId'] : null;
        push_posalji(push_korisnici_s_pravom(P_DOMOVI, $sid), 'domovi', '🏡 Zahtjev – ' . $d['Naziv'],
            $ime . ' želi rezervirati ' . $raz . '. Čeka vaše odobrenje.', 'domovi/dom', ['id' => $d['Id']]);
    }
    return $rez;
}

/** Odobri / odbij (upravitelj) ili otkaži (upravitelj ili onaj tko je rezervirao). Vraća poruku greške ili null. */
function dom_obradi(int $id, string $radnja, string $razlog = ''): ?string
{
    $r = dom_rezervacija($id);
    if (!$r) {
        return 'Rezervacija ne postoji.';
    }
    $d = red('SELECT * FROM LovackiDomovi WHERE Id=?', [(int) $r['DomId']]);
    $upr = $d && dom_upravlja($d);
    $moja = dom_moja($r);
    $razlog = mb_substr(trim($razlog), 0, 300);
    $ime = dom_moje_ime();
    $raz = dom_razdoblje($r);

    if ($radnja === 'odobri' || $radnja === 'odbij') {
        if (!$upr) {
            return 'Rezervacije odobrava upravitelj doma.';
        }
        if ($r['Status'] !== 'ceka') {
            return 'Rezervacija je već obrađena.';
        }
        if ($radnja === 'odobri') {
            if ($r['Do'] < danas()) {
                return 'Razdoblje je već prošlo – rezervaciju odbijte.';
            }
            if (dom_aktivne((int) $r['DomId'], $r['Od'], $r['Do'], (int) $r['Id'])) {
                return 'U tom razdoblju dom je već zauzet.';
            }
        }
        azuriraj('DomRezervacije', $id, ['Status' => $radnja === 'odobri' ? 'odobreno' : 'odbijeno', 'ObradioIme' => $ime, 'Obradjeno' => sada(),
            'Razlog' => $razlog !== '' ? $razlog : null]);
        dnevnik($radnja === 'odobri' ? 'Lovački dom – rezervacija odobrena' : 'Lovački dom – rezervacija odbijena', 'DomRezervacija', $id,
            $d['Naziv'] . ' · ' . $raz . ' · ' . $r['Ime'] . ($razlog !== '' ? ' · ' . $razlog : ''));
        push_posalji(dom_primatelji_rezervacije($r), 'domovi',
            ($radnja === 'odobri' ? '✅ Rezervacija odobrena – ' : '❌ Rezervacija odbijena – ') . $d['Naziv'],
            $raz . ($razlog !== '' ? ' · ' . $razlog : ''), 'domovi/dom', ['id' => $d['Id']]);
        return null;
    }
    if ($radnja === 'otkazi') {
        if (!$upr && !$moja) {
            return 'Rezervaciju može otkazati onaj tko ju je upisao ili upravitelj doma.';
        }
        if (!in_array($r['Status'], ['ceka', 'odobreno'], true)) {
            return 'Rezervacija nije aktivna.';
        }
        if (!$upr && $r['Do'] < danas()) {
            return 'Prošla rezervacija se ne može otkazati.';
        }
        azuriraj('DomRezervacije', $id, ['Status' => 'otkazano', 'ObradioIme' => $ime, 'Obradjeno' => sada(), 'Razlog' => $razlog !== '' ? $razlog : null]);
        dnevnik('Lovački dom – rezervacija otkazana', 'DomRezervacija', $id, $d['Naziv'] . ' · ' . $raz . ' · ' . ($r['Naslov'] ?: $r['Ime']));
        if (!$moja) {
            push_posalji(dom_primatelji_rezervacije($r), 'domovi', '🏡 Rezervacija otkazana – ' . $d['Naziv'],
                $raz . ($razlog !== '' ? ' · ' . $razlog : ''), 'domovi/dom', ['id' => $d['Id']]);
        } elseif ($r['Vrsta'] === DOM_VRSTA_PRIVATNO) {
            push_posalji(push_korisnici_s_pravom(P_DOMOVI, $d['SekcijaId'] !== null ? (int) $d['SekcijaId'] : null), 'domovi',
                '🏡 Otkazano – ' . $d['Naziv'], $r['Ime'] . ' je otkazao/la rezervaciju ' . $raz . '.', 'domovi/dom', ['id' => $d['Id']]);
        }
        return null;
    }
    return 'Nepoznata radnja.';
}

/** Zahtjevi na čekanju koje trenutni korisnik smije obraditi. */
function dom_na_cekanju(): array
{
    if (!ima(P_DOMOVI)) {
        return [];
    }
    $out = [];
    foreach (redovi("SELECT r.*, d.Naziv AS Dom, d.SekcijaId FROM DomRezervacije r JOIN LovackiDomovi d ON d.Id=r.DomId
            WHERE r.Status='ceka' ORDER BY r.Od") as $r) {
        if (dom_upravlja(['SekcijaId' => $r['SekcijaId']])) {
            $out[] = $r;
        }
    }
    return $out;
}
function dom_broj_na_cekanju(): int
{
    static $n = null;
    return $n ??= count(dom_na_cekanju());
}

/** Rezervacije doma (sve statuse) za ispis / popis. */
function dom_popis(int $domId, string $od, ?string $do = null, bool $samoAktivne = true): array
{
    return redovi('SELECT * FROM DomRezervacije WHERE DomId=? AND Do>=?' . ($do ? ' AND Od<=?' : '')
        . ($samoAktivne ? " AND Status IN ('ceka','odobreno')" : '') . ' ORDER BY Od, Id', array_values(array_filter([$domId, $od, $do], fn($v) => $v !== null)));
}
