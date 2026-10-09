<?php
/**
 * Knjiga dezinfekcije (ASK – afrička svinjska kuga): dolasci i odlasci na dezinfekcijskim stanicama.
 * Član skenira QR oznaku stanice → provjera položaja (GPS) → upis (vrijeme poslužitelja).
 */
declare(strict_types=1);

const DEZ_SMJER = ['D' => 'Dolazak', 'O' => 'Odlazak'];
const DEZ_LOK_NAKNADNO = 0, DEZ_LOK_OK = 1, DEZ_LOK_NEPOUZDANO = 2, DEZ_LOK_BEZ = 3;
const DEZ_LOKACIJA = [
    DEZ_LOK_NAKNADNO => 'naknadni upis',
    DEZ_LOK_OK => 'u krugu stanice',
    DEZ_LOK_NEPOUZDANO => 'lokacija nepouzdana',
    DEZ_LOK_BEZ => 'bez lokacije',
];
const DEZ_MAX_SUPUTNIKA = 3;
const DEZ_MAX_GOSTIJU = 6;

// ---------- Stanice ----------
function dez_stanica(int $id): ?array
{
    return red('SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE st.Id=?', [$id]);
}
function dez_stanica_po_tokenu(string $t): ?array
{
    if (!preg_match('/^[a-z0-9]{6,40}$/', $t)) {
        return null;
    }
    return red('SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE st.Token=?', [$t]);
}
function dez_novi_token(): string
{
    return substr(bin2hex(random_bytes(8)), 0, 12);
}

/**
 * Svaka aktivna sekcija dobiva svoju fiksnu stanicu (F, bez koordinata dok se ne upišu)
 * i jednu mobilnu stanicu (M, QR se koristi za svaki skupni lov, položaj se zadaje pri aktivaciji).
 */
function dez_osiguraj_stanice(): void
{
    $naziv = ['F' => 'Dezinfekcija ', 'M' => 'Mobilna stanica '];
    foreach (['F', 'M'] as $v) {
        foreach (sekcije(true) as $s) {
            if (!vrijednost('SELECT 1 FROM DezStanice WHERE Vrsta=? AND SekcijaId=?', [$v, $s['Id']])) {
                umetni('DezStanice', ['Naziv' => $naziv[$v] . $s['Naziv'], 'SekcijaId' => (int) $s['Id'], 'Vrsta' => $v, 'Radijus' => $v === 'M' ? 150 : 100,
                    'Aktivna' => 1, 'Token' => dez_novi_token(), 'Kreirano' => sada()]);
            }
        }
        if (!sekcije(true) && !vrijednost('SELECT 1 FROM DezStanice WHERE Vrsta=?', [$v])) {
            umetni('DezStanice', ['Naziv' => $v === 'M' ? 'Mobilna stanica' : 'Dezinfekcijska stanica', 'SekcijaId' => null, 'Vrsta' => $v,
                'Radijus' => $v === 'M' ? 150 : 100, 'Aktivna' => 1, 'Token' => dez_novi_token(), 'Kreirano' => sada()]);
        }
    }
}

/** Stanice vrste F (stalne) ili M (mobilne). $samoOpseg: samo sekcije trenutnog korisnika. */
function dez_stanice(bool $samoOpseg = true, string $vrsta = 'F'): array
{
    $r = redovi("SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId
        WHERE st.Vrsta=? ORDER BY COALESCE(s.Redoslijed, 9999), s.Naziv, st.Naziv", [$vrsta === 'M' ? 'M' : 'F']);
    return $samoOpseg ? array_values(array_filter($r, fn($st) => moze_sekciju($st['SekcijaId'] !== null ? (int) $st['SekcijaId'] : null))) : $r;
}

// ---------- Mobilna stanica: aktivacije ----------
/** Aktivacija mobilne stanice koja vrijedi u zadanom trenutku (zadano: sada). */
function dez_aktivacija(int $stanicaId, ?string $vrijeme = null): ?array
{
    $v = $vrijeme ?? sada();
    return red('SELECT * FROM DezAktivacije WHERE StanicaId=? AND Od<=? AND Do>=? AND (Zatvoreno IS NULL OR Zatvoreno>=?) ORDER BY Od DESC, Id DESC LIMIT 1',
        [$stanicaId, $v, $v, $v]);
}
function dez_aktivacija_po_id(int $id): ?array
{
    return red('SELECT a.*, st.Naziv AS Stanica, st.SekcijaId, st.Radijus, st.Token FROM DezAktivacije a JOIN DezStanice st ON st.Id=a.StanicaId WHERE a.Id=?', [$id]);
}
/** Stanica s položajem: za mobilnu stanicu koordinate aktivacije (ili null ako nije aktivna). */
function dez_stanica_s_polozajem(array $st, ?string $vrijeme = null): ?array
{
    if ($st['Vrsta'] !== 'M') {
        return $st + ['AktivacijaId' => null, 'AktivacijaNaziv' => null, 'AktivacijaRazlog' => null];
    }
    $a = dez_aktivacija((int) $st['Id'], $vrijeme);
    if (!$a) {
        return null;
    }
    return array_merge($st, ['Lat' => (float) $a['Lat'], 'Lon' => (float) $a['Lon'], 'AktivacijaId' => (int) $a['Id'],
        'AktivacijaNaziv' => $a['Naziv'], 'AktivacijaRazlog' => $a['Razlog'], 'AktivnaDo' => $a['Zatvoreno'] ?? $a['Do']]);
}

function dez_razlozi(bool $samoAktivni = true): array
{
    return redovi('SELECT * FROM DezRazlozi' . ($samoAktivni ? ' WHERE Aktivan=1' : '') . ' ORDER BY Redoslijed, Naziv');
}

// ---------- Položaj ----------
/** Udaljenost dvije točke u metrima (haversine). */
function udaljenost_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371000.0;
    $f1 = deg2rad($lat1);
    $f2 = deg2rad($lat2);
    $df = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($df / 2) ** 2 + cos($f1) * cos($f2) * sin($dl / 2) ** 2;
    return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Procjena položaja: [status, udaljenost|null].
 * Unutar radijusa → OK. Izvan, ali unutar nepreciznosti mobitela → nepouzdano (upis ide, označen).
 * Sigurno izvan (i uz nepreciznost) → null = odbijeno.
 */
function dez_procijeni_lokaciju(array $st, ?float $lat, ?float $lon, ?float $tocnost): array
{
    if ($lat === null || $lon === null || $st['Lat'] === null || $st['Lon'] === null) {
        return [DEZ_LOK_BEZ, null];
    }
    $d = udaljenost_m((float) $st['Lat'], (float) $st['Lon'], $lat, $lon);
    $rad = max(10, (int) $st['Radijus']);
    if ($d <= $rad) {
        return [DEZ_LOK_OK, $d];
    }
    $t = $tocnost !== null ? min(max($tocnost, 0), 2000) : 0;
    if ($d - $t <= $rad) {
        return [DEZ_LOK_NEPOUZDANO, $d];
    }
    return [null, $d];
}

function dez_oznaka_lokacije(array $u, bool $tekst = false): string
{
    $off = '';
    if (!empty($u['Izvanmrezno'])) {
        $prim = 'izvanmrežno, primljeno ' . date('d.m. H:i', strtotime($u['Kreirano']));
        $off = $tekst ? ' (' . $prim . ')' : ' <span class="badge bg-info text-dark" title="' . e($prim) . '">offline</span>';
    }
    return dez_oznaka_lokacije_osnovno($u, $tekst) . $off;
}
function dez_oznaka_lokacije_osnovno(array $u, bool $tekst): string
{
    $s = (int) $u['Lokacija'];
    $opis = DEZ_LOKACIJA[$s] ?? '';
    if ($s === DEZ_LOK_OK) {
        return $tekst ? '✓' : '<span class="text-success" title="' . e($opis . ($u['Udaljenost'] !== null ? ' (' . round((float) $u['Udaljenost']) . ' m)' : '')) . '">✓</span>';
    }
    if ($s === DEZ_LOK_NAKNADNO) {
        return $tekst ? 'naknadno' : '<span class="badge bg-secondary" title="' . e((string) $u['NaknadnoRazlog']) . '">naknadno</span>';
    }
    $det = $opis . ($u['Udaljenost'] !== null ? ', ' . round((float) $u['Udaljenost']) . ' m' : '') . ($u['Tocnost'] !== null ? ', ±' . round((float) $u['Tocnost']) . ' m' : '');
    return $tekst ? '⚠ ' . $det : '<span class="text-warning-emphasis" title="' . e($det) . '">⚠ ' . e($opis) . '</span>';
}

// ---------- Vozila člana ----------
function vozila_clana(int $clanId): array
{
    return redovi('SELECT * FROM ClanVozila WHERE ClanId=? ORDER BY Zadano DESC, Id', [$clanId]);
}
function normaliziraj_oznaku(?string $s): string
{
    $s = mb_strtoupper(trim((string) $s));
    $s = (string) preg_replace('/\s+/', ' ', $s);
    return mb_substr($s, 0, 20);
}
function dodaj_vozilo(int $clanId, string $oznaka, bool $zadano = false): void
{
    $oznaka = normaliziraj_oznaku($oznaka);
    if ($oznaka === '') {
        return;
    }
    $postoji = red('SELECT Id FROM ClanVozila WHERE ClanId=? AND replace(upper(Oznaka),\' \',\'\')=?', [$clanId, str_replace(' ', '', $oznaka)]);
    $prvo = !vrijednost('SELECT 1 FROM ClanVozila WHERE ClanId=?', [$clanId]);
    if ($zadano || $prvo) {
        q('UPDATE ClanVozila SET Zadano=0 WHERE ClanId=?', [$clanId]);
    }
    if ($postoji) {
        if ($zadano || $prvo) {
            q('UPDATE ClanVozila SET Zadano=1 WHERE Id=?', [$postoji['Id']]);
        }
        return;
    }
    if ((int) vrijednost('SELECT COUNT(*) FROM ClanVozila WHERE ClanId=?', [$clanId]) >= 5) {
        return;
    }
    umetni('ClanVozila', ['ClanId' => $clanId, 'Oznaka' => $oznaka, 'Zadano' => ($zadano || $prvo) ? 1 : 0]);
}

// ---------- Spremanje upisa (online i izvanmrežno) ----------
/**
 * Sprema upis s QR stanice. $u = podaci obrasca (smjer, razlog, razlog_opis, vozilo, oznaka, spremi_vozilo, lat, lon, acc,
 * suputnik[], gost_ime[], gost_prezime[], gost_oznaka[], potvrda).
 * $klijentVrijeme: vrijeme s mobitela (unix) za izvanmrežni upis; null = vrijeme poslužitelja.
 * $grupa: oznaka upisa (izvanmrežni upis je šalje sam – ponovno slanje se ne upisuje dvaput).
 * @return array{ok:bool, poruka?:string, grupa?:string, ponovljeno?:bool}
 */
function dez_spremi_upis(array $st, array $k, array $u, ?int $klijentVrijeme = null, ?string $grupa = null): array
{
    $greska = fn(string $p) => ['ok' => false, 'poruka' => $p];
    if (!$st['Aktivna']) {
        return $greska('Stanica nije aktivna.');
    }
    if ($grupa !== null && ($g = vrijednost('SELECT Grupa FROM DezUpisi WHERE Grupa=? LIMIT 1', [$grupa]))) {
        return ['ok' => true, 'grupa' => $g, 'ponovljeno' => true];
    }
    $izvan = $klijentVrijeme !== null;
    $t = time();
    if ($izvan) {
        $t = min($klijentVrijeme, time());
        if ($t < time() - 14 * 86400) {
            return $greska('Upis je stariji od 14 dana – upišite ga naknadno preko lovočuvara.');
        }
    }
    $vrijeme = date('Y-m-d H:i:s', $t);
    $stP = dez_stanica_s_polozajem($st, $vrijeme);
    if (!$stP) {
        return $greska('Mobilna stanica ' . ($izvan ? 'u to vrijeme' : 'trenutno') . ' nije aktivna. Javite se voditelju lova / lovočuvaru.');
    }
    $smjer = (string) ($u['smjer'] ?? '');
    if (!isset(DEZ_SMJER[$smjer])) {
        return $greska('Odaberite DOLAZAK ili ODLAZAK.');
    }
    if (empty($u['potvrda'])) {
        return $greska('Potvrdite da je dezinfekcija provedena.');
    }
    $raz = red('SELECT * FROM DezRazlozi WHERE Id=?', [(int) ($u['razlog'] ?? 0)]);
    if (!$raz) {
        return $greska('Odaberite razlog.');
    }
    $razlog = $raz['Naziv'];
    $opis = mb_substr(trim((string) ($u['razlog_opis'] ?? '')), 0, 80);
    if ($raz['Slobodno'] && $opis !== '') {
        $razlog .= ': ' . $opis;
    }
    $clan = $k['ClanId'] ? clan((int) $k['ClanId']) : null;
    $ja = $clan ? puno_ime($clan) : $k['Naziv'];
    // upis za drugog lovca (bez mobitela) – samo s pravom, i dalje na samoj stanici
    $zaDrugog = null;
    if (!empty($u['za_clana']) && (int) $u['za_clana'] !== (int) ($clan['Id'] ?? 0)) {
        if (!ima(P_DEZ_ZA_DRUGE)) {
            return $greska('Nemate pravo upisivati druge lovce.');
        }
        $zaDrugog = red('SELECT * FROM Clanovi WHERE Id=? AND Status=0', [(int) $u['za_clana']]);
        if (!$zaDrugog) {
            return $greska('Lovac nije pronađen.');
        }
        $clan = $zaDrugog;
    }
    // vozilo
    $v = (string) ($u['vozilo'] ?? '');
    $saVozilom = $v !== 'bez';
    $oznaka = null;
    if (str_starts_with($v, 'v') && $clan) {
        $oznaka = (string) vrijednost('SELECT Oznaka FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) substr($v, 1), $clan['Id']]) ?: null;
    } elseif ($saVozilom) {
        $oznaka = normaliziraj_oznaku((string) ($u['oznaka'] ?? '')) ?: null;
    }
    if ($saVozilom && $oznaka === null) {
        return $greska('Upišite registarsku oznaku vozila (ili odaberite „bez vozila“).');
    }
    if ($saVozilom && $oznaka !== null && $clan && !empty($u['spremi_vozilo']) && !str_starts_with($v, 'v')) {
        dodaj_vozilo((int) $clan['Id'], $oznaka);
    }
    // položaj
    $f = fn($n) => isset($u[$n]) && is_numeric($u[$n]) ? (float) $u[$n] : null;
    $lat = $f('lat');
    $lon = $f('lon');
    $toc = $f('acc');
    if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
        $lat = $lon = null;
    }
    [$lok, $udalj] = dez_procijeni_lokaciju($stP, $lat, $lon, $toc);
    if ($lok === null) {
        dnevnik('Dezinfekcija – odbijen upis (predaleko)' . ($izvan ? ', izvanmrežno' : ''), 'DezStanica', (int) $st['Id'],
            $ja . ': ' . round((float) $udalj) . ' m, ±' . round((float) $toc) . ' m', $k);
        return $greska('Predaleko od dezinfekcijske stanice (' . number_format((float) $udalj / 1000, 1, ',', '.') . ' km). Upis je moguć samo na samoj stanici.');
    }
    // dvostruki dodir: isti smjer u 2 minute
    if ($clan && !$izvan) {
        $isti = vrijednost('SELECT Grupa FROM DezUpisi WHERE ClanId=? AND StanicaId=? AND Smjer=? AND Ponisteno=0 AND Vrijeme>=? ORDER BY Id DESC LIMIT 1',
            [$clan['Id'], $st['Id'], $smjer, date('Y-m-d H:i:s', time() - 120)]);
        if ($isti) {
            return ['ok' => true, 'grupa' => $isti, 'ponovljeno' => true];
        }
    }
    $suputnici = [];
    foreach (array_slice(array_unique(array_map('intval', (array) ($u['suputnik'] ?? []))), 0, DEZ_MAX_SUPUTNIKA) as $sid) {
        if ($sid && $sid !== (int) ($clan['Id'] ?? 0) && ($c = red('SELECT * FROM Clanovi WHERE Id=? AND Status=0', [$sid]))) {
            $suputnici[] = $c;
        }
    }
    $gosti = [];
    $gp = (array) ($u['gost_prezime'] ?? []);
    $go = (array) ($u['gost_oznaka'] ?? []);
    foreach ((array) ($u['gost_ime'] ?? []) as $i => $ime) {
        $ime = mb_substr(trim((string) $ime), 0, 60);
        $prez = mb_substr(trim((string) ($gp[$i] ?? '')), 0, 60);
        if ($ime === '' && $prez === '') {
            continue;
        }
        if ($ime === '' || $prez === '') {
            return $greska('Za gosta upišite ime i prezime.');
        }
        $gosti[] = [$ime, $prez, normaliziraj_oznaku((string) ($go[$i] ?? '')) ?: null];
        if (count($gosti) >= DEZ_MAX_GOSTIJU) {
            break;
        }
    }
    $grupa ??= bin2hex(random_bytes(8));
    $zajedno = [
        'StanicaId' => (int) $st['Id'], 'SekcijaId' => $st['SekcijaId'], 'Vrijeme' => $vrijeme, 'Smjer' => $smjer, 'Razlog' => $razlog,
        'Obuca' => 1, 'Oprema' => 1, 'UpisaoKorisnikId' => $k['Id'], 'UpisaoIme' => $ja, 'Lat' => $lat, 'Lon' => $lon, 'Tocnost' => $toc,
        'Udaljenost' => $udalj, 'Lokacija' => $lok, 'Naknadno' => 0, 'Grupa' => $grupa, 'Kreirano' => sada(),
        'AktivacijaId' => $stP['AktivacijaId'], 'Izvanmrezno' => $izvan ? 1 : 0,
    ];
    transakcija(function () use ($zajedno, $clan, $k, $ja, $oznaka, $saVozilom, $suputnici, $gosti, $zaDrugog) {
        umetni('DezUpisi', $zajedno + ['ClanId' => $clan['Id'] ?? null, 'Ime' => $clan['Ime'] ?? $k['Naziv'], 'Prezime' => $clan['Prezime'] ?? '',
            'Gost' => 0, 'Oznaka' => $oznaka, 'Vozilo' => $saVozilom ? 1 : 0, 'PozvaoIme' => $zaDrugog ? $ja : null]);
        foreach ($suputnici as $c) {
            umetni('DezUpisi', $zajedno + ['ClanId' => (int) $c['Id'], 'Ime' => $c['Ime'], 'Prezime' => $c['Prezime'], 'Gost' => 0,
                'Oznaka' => $oznaka, 'Vozilo' => $saVozilom ? 1 : 0, 'PozvaoIme' => $ja]);
        }
        foreach ($gosti as [$ime, $prez, $oz]) {
            umetni('DezUpisi', $zajedno + ['ClanId' => null, 'Ime' => $ime, 'Prezime' => $prez, 'Gost' => 1,
                'Oznaka' => $oz ?? $oznaka, 'Vozilo' => ($oz !== null || $saVozilom) ? 1 : 0, 'PozvaoIme' => $ja]);
        }
    });
    dnevnik('Dezinfekcija – ' . mb_strtolower(DEZ_SMJER[$smjer]) . ($izvan ? ' (izvanmrežno)' : ''), 'DezStanica', (int) $st['Id'],
        ($zaDrugog ? puno_ime($zaDrugog) . ' (upisao ' . $ja . ')' : $ja) . ' + ' . count($suputnici) . ' suputnika, ' . count($gosti) . ' gostiju · ' . DEZ_LOKACIJA[$lok], $k);
    return ['ok' => true, 'grupa' => $grupa];
}

// ---------- Upisi ----------
/** Zadnji upis člana na stanici u zadnjih 24 sata (za prijedlog dolazak/odlazak). */
function dez_zadnji_upis_clana(int $clanId, int $stanicaId): ?array
{
    return red('SELECT * FROM DezUpisi WHERE ClanId=? AND StanicaId=? AND Ponisteno=0 AND Vrijeme>=? ORDER BY Vrijeme DESC, Id DESC LIMIT 1',
        [$clanId, $stanicaId, date('Y-m-d H:i:s', time() - 86400)]);
}

/**
 * Filtar knjige: Razdoblje/Od/Do (kao izvještaji), SekcijaId (null = sve), StanicaId, Smjer, Trazi, Ponisteni.
 */
function dez_filtar(array $q): array
{
    $int = fn($k) => isset($q[$k]) && $q[$k] !== '' && is_numeric($q[$k]) ? (int) $q[$k] : null;
    $dat = fn($k) => isset($q[$k]) && $q[$k] !== '' ? u_datum((string) $q[$k]) : null;
    return [
        'Razdoblje' => isset(RAZDOBLJA[$q['Razdoblje'] ?? '']) ? $q['Razdoblje'] : 'OvajMjesec',
        'Od' => $dat('Od'),
        'Do' => $dat('Do'),
        'SekcijaId' => $int('SekcijaId'),
        'StanicaId' => $int('StanicaId'),
        'Smjer' => in_array($q['Smjer'] ?? '', ['D', 'O'], true) ? $q['Smjer'] : null,
        'Trazi' => mb_substr(trim((string) ($q['Trazi'] ?? '')), 0, 60),
        'Ponisteni' => !empty($q['Ponisteni']),
        'Vrsta' => ($q['Vrsta'] ?? '') === 'M' ? 'M' : 'F',
        'AktivacijaId' => $int('AktivacijaId'),
    ];
}
function dez_filtar_upit(array $f): array
{
    return array_filter([
        'Razdoblje' => $f['Razdoblje'], 'Od' => $f['Od'], 'Do' => $f['Do'], 'SekcijaId' => $f['SekcijaId'], 'StanicaId' => $f['StanicaId'],
        'Smjer' => $f['Smjer'], 'Trazi' => $f['Trazi'], 'Ponisteni' => $f['Ponisteni'] ? 1 : null,
        'Vrsta' => $f['Vrsta'] === 'M' ? 'M' : null, 'AktivacijaId' => $f['AktivacijaId'],
    ], fn($v) => $v !== null && $v !== '');
}
function dez_opis_razdoblja(array $f): string
{
    return opis_raspona($f + ['Razdoblje' => 'OvajMjesec']);
}

/**
 * Upisi prema filtru. $stanice = dopuštene stanice (Id-ovi) – opseg korisnika ili inspekcije.
 */
function dez_upisi(array $f, array $staniceIds, int $limit = 5000): array
{
    if (!$staniceIds) {
        return [];
    }
    [$od, $do] = raspon($f);
    $w = ['u.StanicaId IN (' . implode(',', array_map('intval', $staniceIds)) . ')', 'substr(u.Vrijeme,1,10) BETWEEN ? AND ?'];
    $p = [$od, $do];
    if ($f['SekcijaId'] !== null) {
        $w[] = 'st.SekcijaId=?';
        $p[] = $f['SekcijaId'];
    }
    if ($f['StanicaId'] !== null) {
        $w[] = 'u.StanicaId=?';
        $p[] = $f['StanicaId'];
    }
    if (($f['AktivacijaId'] ?? null) !== null) {
        $w[] = 'u.AktivacijaId=?';
        $p[] = $f['AktivacijaId'];
    }
    if ($f['Smjer'] !== null) {
        $w[] = 'u.Smjer=?';
        $p[] = $f['Smjer'];
    }
    if (!$f['Ponisteni']) {
        $w[] = 'u.Ponisteno=0';
    }
    if ($f['Trazi'] !== '') {
        $w[] = '(kljuc(u.Ime || \' \' || u.Prezime) LIKE ? OR kljuc(u.Prezime || \' \' || u.Ime) LIKE ? OR replace(upper(COALESCE(u.Oznaka,\'\')),\' \',\'\') LIKE ?)';
        $t = '%' . kljuc($f['Trazi']) . '%';
        array_push($p, $t, $t, '%' . str_replace(' ', '', mb_strtoupper($f['Trazi'])) . '%');
    }
    return redovi('SELECT u.*, st.Naziv AS Stanica, st.SekcijaId AS StSekcijaId, s.Naziv AS SekcijaNaziv, a.Naziv AS Akcija FROM DezUpisi u JOIN DezStanice st ON st.Id=u.StanicaId
        LEFT JOIN Sekcije s ON s.Id=st.SekcijaId LEFT JOIN DezAktivacije a ON a.Id=u.AktivacijaId WHERE ' . implode(' AND ', $w) . ' ORDER BY u.Vrijeme, u.Id LIMIT ' . $limit, $p);
}

/**
 * Tko je trenutno u lovištu: zadnji upis osobe u zadnja 24 h je Dolazak.
 * @return array<int, array> upisi dolaska
 */
function dez_trenutno_unutra(array $staniceIds): array
{
    if (!$staniceIds) {
        return [];
    }
    $r = redovi('SELECT u.*, st.Naziv AS Stanica FROM DezUpisi u JOIN DezStanice st ON st.Id=u.StanicaId
        WHERE u.Ponisteno=0 AND u.Vrijeme>=? AND u.StanicaId IN (' . implode(',', array_map('intval', $staniceIds)) . ') ORDER BY u.Vrijeme, u.Id',
        [date('Y-m-d H:i:s', time() - 86400)]);
    $zadnji = [];
    foreach ($r as $u) {
        $kl = $u['ClanId'] ? 'c' . $u['ClanId'] : 'g' . kljuc($u['Ime'] . ' ' . $u['Prezime']);
        $zadnji[$kl] = $u;
    }
    return array_values(array_filter($zadnji, fn($u) => $u['Smjer'] === 'D'));
}

function dez_ime(array $u): string
{
    return trim($u['Ime'] . ' ' . $u['Prezime']);
}

/** Tko je upisao / pozvao gosta – stupac u knjizi. */
function dez_upisao(array $u): string
{
    if ($u['Naknadno']) {
        return 'naknadno: ' . ($u['UpisaoIme'] ?? '');
    }
    if ($u['Gost']) {
        return 'gost – pozvao: ' . ($u['PozvaoIme'] ?? $u['UpisaoIme'] ?? '');
    }
    if ($u['PozvaoIme']) {
        return 'upisao: ' . $u['PozvaoIme'];
    }
    return 'osobno';
}

function dez_dezinficirano(array $u): string
{
    $d = [];
    if ($u['Vozilo']) {
        $d[] = 'vozilo';
    }
    if ($u['Obuca']) {
        $d[] = 'obuća';
    }
    if ($u['Oprema']) {
        $d[] = 'oprema';
    }
    return $d ? implode(', ', $d) : '—';
}

// ---------- Ispis / PDF ----------
function dez_html(array $upisi, string $naslov, string $podnaslov, bool $zaPdf = false, bool $stanicaStupac = true): string
{
    $mob = $upisi && !empty($upisi[0]['AktivacijaId']);
    $o = '<h2>' . e($naslov) . '</h2><div class="pod">' . e($podnaslov) . '</div>';
    $o .= "<table class='t'><thead><tr><th>Datum</th><th>Vrijeme</th><th>Ime i prezime</th><th>Član / gost</th><th>Reg. oznaka</th><th>Razlog</th><th>Smjer</th>"
        . "<th>Dezinficirano</th>" . ($stanicaStupac ? '<th>' . ($mob ? 'Akcija' : 'Stanica') . '</th>' : '') . "<th>Napomena</th><th>Lokacija</th></tr></thead><tbody>";
    foreach ($upisi as $u) {
        $t = strtotime($u['Vrijeme']);
        $pon = $u['Ponisteno'] ? " class='pon'" : '';
        $o .= "<tr$pon><td>" . date('d.m.Y.', $t) . '</td><td>' . date('H:i', $t) . '</td><td>' . e(dez_ime($u)) . '</td><td>' . ($u['Gost'] ? 'gost' : 'član') . '</td><td>'
            . e($u['Oznaka'] ?? '') . '</td><td>' . e($u['Razlog'] ?? '') . '</td><td>' . DEZ_SMJER[$u['Smjer']] . '</td><td>' . e(dez_dezinficirano($u)) . '</td>'
            . ($stanicaStupac ? '<td>' . e($u['Akcija'] ?? $u['Stanica']) . '</td>' : '') . '<td>' . e(dez_upisao($u)) . ($u['Ponisteno'] ? ' · PONIŠTENO: ' . e($u['PonistenoRazlog'] ?? '') : '')
            . ($u['Naknadno'] && $u['NaknadnoRazlog'] ? ' · ' . e($u['NaknadnoRazlog']) : '') . '</td><td>' . e(dez_oznaka_lokacije($u, true)) . '</td></tr>';
    }
    if (!$upisi) {
        $o .= "<tr><td colspan='" . ($stanicaStupac ? 11 : 10) . "'>Nema upisa za odabrano razdoblje.</td></tr>";
    }
    $o .= '</tbody></table>';
    return $o;
}

/** Zaglavlje protokola: stanice s koordinatama i sredstvom; za mobilne stanice popis akcija (aktivacija) iz upisa. */
function dez_opis_stanica(array $stanice, array $upisi = []): string
{
    $d = [];
    if ($stanice && $stanice[0]['Vrsta'] === 'M') {
        $ids = array_unique(array_filter(array_map(fn($u) => (int) $u['AktivacijaId'], $upisi)));
        foreach ($ids as $aid) {
            $a = dez_aktivacija_po_id($aid);
            if ($a) {
                $d[] = $a['Naziv'] . ' · ' . $a['Stanica'] . ' · ' . number_format((float) $a['Lat'], 5, '.', '') . ', ' . number_format((float) $a['Lon'], 5, '.', '')
                    . ' · ' . date('d.m.Y. H:i', strtotime($a['Od'])) . ' – ' . date('H:i', strtotime($a['Zatvoreno'] ?? $a['Do'])) . ' · aktivirao: ' . $a['AktiviraoIme'];
            }
        }
        $sred = array_unique(array_filter(array_map(fn($s) => $s['Sredstvo'], $stanice)));
        if ($sred) {
            $d[] = 'Sredstvo: ' . implode(', ', $sred);
        }
        return implode("\n", $d ?: ['Mobilne stanice']);
    }
    foreach ($stanice as $st) {
        $d[] = $st['Naziv'] . ($st['SekcijaNaziv'] ? ' (' . $st['SekcijaNaziv'] . ')' : '')
            . ($st['Lat'] !== null ? ' · ' . number_format((float) $st['Lat'], 5, '.', '') . ', ' . number_format((float) $st['Lon'], 5, '.', '') : '')
            . ($st['Sredstvo'] ? ' · sredstvo: ' . $st['Sredstvo'] : '');
    }
    return implode("\n", $d);
}

function dez_pdf(array $upisi, array $stanice, string $razdoblje, string $sekcijaOpis, array $liste = []): string
{
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
        $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $loviste = postavka('Dez.Loviste', '');
    $odg = postavka('Dez.OdgovornaOsoba', '');
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 8px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #555; white-space: pre-line; }
        h2 { font-size: 11.5pt; margin: 2px 0 2px; } .pod { color: #555; margin-bottom: 6px; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #bbb; padding: 2px 3px; vertical-align: top; }
        tr.pon td { color: #999; text-decoration: line-through; }
        .potpis { margin-top: 18px; } .podnozje { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · knjiga dezinfekcije · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">'
        . e(($loviste ? 'Lovište: ' . $loviste . "\n" : '') . dez_opis_stanica($stanice, $upisi)) . '</div></div>'
        . dez_html($upisi, 'Evidencija dezinfekcije vozila, obuće i opreme (ASK)' . ($stanice && $stanice[0]['Vrsta'] === 'M' ? ' – mobilna stanica' : ''), 'Razdoblje: ' . $razdoblje . ' · ' . $sekcijaOpis . ' · upisa: ' . count($upisi), true, count($stanice) > 1 || ($stanice && $stanice[0]['Vrsta'] === 'M'))
        . '<div class="potpis">Odgovorna osoba: ' . ($odg !== '' ? e($odg) : '______________________') . ' &nbsp;&nbsp;&nbsp; Potpis: ______________________</div>'
        . ($liste ? '<div class="pod" style="margin-top:6px">Prilog: ' . count($liste) . ' fotografija papirnatih lista (sljedeće stranice).</div>' . dez_liste_pdf_html($liste) : '')
        . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();
    $pdf->getCanvas()->page_text(770, 570, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}

function dez_mail_html(array $upisi, string $razdoblje, string $sekcijaOpis): string
{
    return "<p style='font-family:Arial'>U privitku je evidencija dezinfekcije (PDF) – " . e(udruga_naziv()) . ', razdoblje ' . e($razdoblje) . ', ' . e($sekcijaOpis)
        . ', ' . count($upisi) . ' upisa.</p>';
}

// ---------- Inspekcija ----------
function dez_insp_token(bool $novi = false): string
{
    $t = postavka('Dez.InspekcijaToken');
    if (!$t || $novi) {
        $t = substr(bin2hex(random_bytes(8)), 0, 12);
        spremi_postavku('Dez.InspekcijaToken', $t);
    }
    return $t;
}
function dez_insp_lozinka(): ?string
{
    return desifriraj(postavka('Dez.InspekcijaLozinka'));
}
/** Otisak lozinke u sesiji – promjena lozinke odmah odjavljuje inspekciju. */
function dez_insp_otisak(): string
{
    return substr(hash('sha256', (string) postavka('Dez.InspekcijaLozinka') . '|' . dez_insp_token()), 0, 24);
}
function dez_insp_prijavljen(): bool
{
    return !empty($_SESSION['insp']) && $_SESSION['insp']['o'] === dez_insp_otisak() && $_SESSION['insp']['do'] > time();
}

function dez_qr_url(array $st): string
{
    return apsolutni_url('dez', ['s' => $st['Token']]);
}
function dez_insp_url(): string
{
    return apsolutni_url('inspekcija', ['k' => dez_insp_token()]);
}
function karta_tocke_url(float $lat, float $lon): string
{
    return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=17/' . $lat . '/' . $lon;
}

// ---------- Papirnate liste (fotografije) ----------
/** Fotografirane papirnate liste za stanice $ids koje se preklapaju s razdobljem filtra (i sekcijom). */
function dez_liste(array $f, array $ids): array
{
    if (!$ids) {
        return [];
    }
    [$od, $do] = raspon($f);
    $w = ['l.StanicaId IN (' . implode(',', array_map('intval', $ids)) . ')', 'l.Od<=?', 'l.Do>=?'];
    $p = [$do, $od];
    if ($f['SekcijaId'] !== null) {
        $w[] = 'st.SekcijaId=?';
        $p[] = $f['SekcijaId'];
    }
    return redovi('SELECT l.*, st.Naziv AS Stanica FROM DezListe l JOIN DezStanice st ON st.Id=l.StanicaId WHERE ' . implode(' AND ', $w) . ' ORDER BY l.Od, l.Id', $p);
}
function dez_lista_opis(array $l): string
{
    return $l['Od'] === $l['Do'] ? datum($l['Od']) : datum($l['Od']) . ' – ' . datum($l['Do']);
}
function dez_lista_url(array $l, ?string $inspToken = null): string
{
    return url('dez-slika', array_filter(['id' => $l['Id'], 'k' => $inspToken]));
}
/** HTML popis sličica (knjiga i inspekcija). */
function dez_liste_html(array $liste, ?string $inspToken = null, bool $brisanje = false, array $povratak = []): string
{
    if (!$liste) {
        return '';
    }
    $o = '<div class="card mt-4"><div class="card-header">📄 Papirnate liste (fotografije) <span class="badge bg-secondary">' . count($liste) . '</span>'
        . '<span class="small text-muted ms-2">lovci bez mobitela – sastavni dio evidencije</span></div><div class="card-body d-flex flex-wrap gap-3">';
    foreach ($liste as $l) {
        $u = dez_lista_url($l, $inspToken);
        $o .= '<div class="dez-lista"><a href="' . e($u) . '" target="_blank"><img src="' . e($u) . '" alt="" loading="lazy"></a>'
            . '<div class="small fw-semibold">' . e(dez_lista_opis($l)) . '</div><div class="small text-muted">' . e($l['Stanica']) . '</div>'
            . ($l['Napomena'] ? '<div class="small">' . e($l['Napomena']) . '</div>' : '')
            . '<div class="small text-muted">učitao: ' . e($l['UcitaoIme']) . '</div>';
        if ($brisanje) {
            $o .= '<form method="post" action="' . e(url('dezinfekcija/liste', $povratak)) . '" class="d-inline">' . csrf()
                . '<input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="' . (int) $l['Id'] . '">'
                . '<button class="btn btn-sm btn-link text-danger p-0" data-potvrda="Obrisati fotografiju liste?">Obriši</button></form>';
        }
        $o .= '</div>';
    }
    return $o . '</div></div>';
}
/** Stranice PDF-a s fotografijama papirnatih lista. */
function dez_liste_pdf_html(array $liste): string
{
    $o = '';
    foreach ($liste as $l) {
        $put = podaci('foto/' . basename($l['Datoteka']));
        if (!is_file($put)) {
            continue;
        }
        $mime = str_ends_with($put, '.png') ? 'image/png' : 'image/jpeg';
        $o .= '<div style="page-break-before: always"><h2>Papirnata lista – ' . e($l['Stanica']) . ', ' . e(dez_lista_opis($l)) . '</h2>'
            . '<div class="pod">' . e(trim(($l['Napomena'] ?? '') . ' · učitao: ' . $l['UcitaoIme'] . ', ' . datum_vrijeme($l['Kreirano']), ' ·')) . '</div>'
            . '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($put)) . '" style="max-width:100%; max-height:165mm"></div>';
    }
    return $o;
}

