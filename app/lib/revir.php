<?php
/**
 * Lovište: karta s granicama (KML), lovne naprave (čeke), zauzimanje, obavijesti lovočuvarima, lovački dnevnik.
 * Sekcije su strogo odvojene: član vidi i zauzima samo u svojoj sekciji; uloge sa „svim sekcijama“ vide sve.
 */
declare(strict_types=1);

const REVIR_VRSTE = ['Čeka', 'Visoka čeka', 'Zatvorena čeka', 'Zaklon', 'Hranilište', 'Solište', 'Ostalo'];
const REVIR_MIN_DNEVNIK = 5; // kraće zauzeće (minute) ne ide u dnevnik – npr. greškom dodirnuto

function revir_granice_datoteka(): string
{
    return podaci('revir-granice.json');
}

/** Sat (0–23) u kojem zauzeća automatski istječu. */
function revir_sat_isteka(): int
{
    $s = (int) postavka('Revir.SatIsteka', '3');
    return ($s >= 0 && $s <= 23) ? $s : 3;
}

/** Sljedeći trenutak automatskog isteka (danas ili sutra u zadani sat). */
function revir_vrijedi_do(): string
{
    $sat = revir_sat_isteka();
    $t = mktime($sat, 0, 0);
    if ($t <= time()) {
        $t = strtotime('+1 day', $t);
    }
    return date('Y-m-d H:i:s', $t);
}

// ---------- Opseg ----------
/** Sekcija člana trenutnog korisnika (false = korisnik nije član). */
function revir_moja_sekcija(): int|null|false
{
    static $s = 0;
    if ($s !== 0) {
        return $s;
    }
    $k = korisnik();
    if (!$k || !$k['ClanId']) {
        return $s = false;
    }
    $v = vrijednost('SELECT SekcijaId FROM Clanovi WHERE Id=?', [$k['ClanId']]);
    return $s = ($v === null ? null : (int) $v);
}

/** Sekcije koje korisnik smije vidjeti na karti: [id => naziv]; ključ 0 = bez sekcije. */
function revir_sekcije(): array
{
    $k = korisnik();
    if (!$k) {
        return [];
    }
    $sve = [];
    foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
        $sve[(int) $s['Id']] = $s['Naziv'];
    }
    if ($k['SveSekcije']) {
        return $sve ?: [0 => udruga_kratko()];
    }
    $r = [];
    $moja = revir_moja_sekcija();
    if ($moja === null) {
        $r[0] = $sve ? 'Bez sekcije' : udruga_kratko();
    } elseif ($moja !== false && isset($sve[$moja])) {
        $r[$moja] = $sve[$moja];
    }
    foreach ($k['Sekcije'] as $id) {
        if (isset($sve[$id])) {
            $r[$id] = $sve[$id];
        }
    }
    return $r;
}

function revir_sid(?int $sid): int
{
    return $sid ?? 0;
}
function revir_moze_vidjeti(?int $sid): bool
{
    return array_key_exists(revir_sid($sid), revir_sekcije());
}
function revir_moze_urediti(?int $sid): bool
{
    return ima(P_REVIR_UREDI) && (moze_sekciju($sid) || (korisnik()['SveSekcije'] ?? false));
}
function revir_moze_nadzor(?int $sid): bool
{
    return ima(P_REVIR_NADZOR) && (moze_sekciju($sid) || (korisnik()['SveSekcije'] ?? false));
}
/** Zauzimati se smije samo u vlastitoj sekciji (član). */
function revir_moze_zauzeti(?int $sid): bool
{
    $m = revir_moja_sekcija();
    return $m !== false && $m === $sid;
}
/** SQL uvjet za sekcije nadzora (stupac $stupac). */
function revir_nadzor_sql(string $stupac = 'SekcijaId'): string
{
    $k = korisnik();
    if (!$k || !ima(P_REVIR_NADZOR)) {
        return '1=0';
    }
    if ($k['SveSekcije']) {
        return '1=1';
    }
    return $k['Sekcije'] ? $stupac . ' IN (' . implode(',', array_map('intval', $k['Sekcije'])) . ')' : '1=0';
}

// ---------- Naprave ----------
function revir_oznaka(array $n): string
{
    $b = trim((string) ($n['Broj'] ?? ''));
    return ($b !== '' ? $b . ' · ' : '') . $n['Naziv'];
}
function revir_naprava(int $id): ?array
{
    return red('SELECT * FROM RevirNaprave WHERE Id=?', [$id]);
}
function revir_naprave(array $sekcije, bool $iNeaktivne = false): array
{
    if (!$sekcije) {
        return [];
    }
    $uv = [];
    $ids = array_filter(array_keys($sekcije));
    if ($ids) {
        $uv[] = 'SekcijaId IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
    if (array_key_exists(0, $sekcije)) {
        $uv[] = 'SekcijaId IS NULL';
    }
    return redovi('SELECT * FROM RevirNaprave WHERE (' . implode(' OR ', $uv) . ')' . ($iNeaktivne ? '' : ' AND Aktivna=1')
        . ' ORDER BY CAST(Broj AS INTEGER), Broj, Naziv');
}

// ---------- Zauzeća ----------
/** Zatvara zauzeća kojima je prošao automatski istek (npr. 03:00) i upisuje ih u dnevnik. */
function revir_zatvori_istekle(): void
{
    static $gotovo = false;
    if ($gotovo) {
        return;
    }
    $gotovo = true;
    foreach (redovi('SELECT * FROM RevirZauzeca WHERE Kraj IS NULL AND VrijediDo<=?', [sada()]) as $z) {
        revir_zavrsi($z, 'auto', null, $z['VrijediDo']);
    }
}

function revir_aktivno(int $napravaId): ?array
{
    revir_zatvori_istekle();
    return red('SELECT * FROM RevirZauzeca WHERE NapravaId=? AND Kraj IS NULL', [$napravaId]);
}

/** Završava zauzeće. Član (ne gost) ide u lovački dnevnik, osim kad je zauzeće obrisano. */
function revir_zavrsi(array $z, string $nacin, ?string $obrisao = null, ?string $kraj = null): void
{
    $kraj ??= sada();
    q('UPDATE RevirZauzeca SET Kraj=?, KrajNacin=?, ObrisaoIme=? WHERE Id=? AND Kraj IS NULL', [$kraj, $nacin, $obrisao, $z['Id']]);
    $trajanje = (strtotime($kraj) - strtotime($z['Od'])) / 60;
    if ($z['Gost'] === null && $nacin !== 'obrisano' && $trajanje >= REVIR_MIN_DNEVNIK) {
        $n = revir_naprava((int) $z['NapravaId']);
        umetni('LovackiDnevnik', [
            'ZauzeceId' => $z['Id'], 'ClanId' => $z['ClanId'], 'Ime' => $z['Ime'], 'SekcijaId' => $z['SekcijaId'],
            'NapravaId' => $z['NapravaId'], 'Naprava' => $n ? revir_oznaka($n) : '?', 'Od' => $z['Od'], 'Do' => $kraj,
            'Automatski' => $nacin === 'auto' ? 1 : 0,
        ]);
    }
}

function revir_obavijest(?int $sid, string $vrsta, string $tekst): void
{
    umetni('RevirObavijesti', ['SekcijaId' => $sid, 'Vrijeme' => sada(), 'Vrsta' => $vrsta, 'Tekst' => $tekst, 'KorisnikId' => korisnik()['Id'] ?? null]);
}

/** Zauzimanje naprave za sebe ($gost === null) ili za gosta. Vraća poruku greške ili null. */
function revir_zauzmi(int $napravaId, ?string $gost): ?string
{
    $k = korisnik();
    $n = revir_naprava($napravaId);
    if (!$n || !$n['Aktivna']) {
        return 'Lovna naprava ne postoji.';
    }
    $sid = $n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null;
    if (!revir_moze_zauzeti($sid)) {
        return 'Zauzeti možete samo lovne naprave svoje sekcije.';
    }
    $gost = $gost !== null ? mb_substr(trim($gost), 0, 60) : null;
    $clan = red('SELECT Id, Ime, Prezime FROM Clanovi WHERE Id=?', [$k['ClanId']]);
    $ime = puno_ime($clan);
    revir_zatvori_istekle();
    try {
        return transakcija(function () use ($n, $napravaId, $sid, $gost, $k, $ime) {
            $z = red('SELECT * FROM RevirZauzeca WHERE NapravaId=? AND Kraj IS NULL', [$napravaId]);
            if ($z) {
                return 'Naprava je upravo zauzeta (' . ($z['Gost'] !== null ? 'gost ' . $z['Gost'] . ', ' : '') . $z['Ime'] . ').';
            }
            $preseli = null;
            if ($gost === null) {
                // za sebe može biti samo na jednoj napravi – stara se oslobađa
                $preseli = red('SELECT * FROM RevirZauzeca WHERE ClanId=? AND Gost IS NULL AND Kraj IS NULL', [$k['ClanId']]);
                if ($preseli) {
                    revir_zavrsi($preseli, 'premjesteno');
                }
            }
            $id = umetni('RevirZauzeca', [
                'NapravaId' => $napravaId, 'SekcijaId' => $sid, 'ClanId' => $k['ClanId'], 'KorisnikId' => $k['Id'], 'Ime' => $ime,
                'Gost' => $gost === null ? null : ($gost !== '' ? $gost : 'gost'), 'Od' => sada(), 'VrijediDo' => revir_vrijedi_do(),
            ]);
            $tko = $gost === null ? $ime : 'Gost' . ($gost !== '' ? ' ' . $gost : '') . ' (od ' . $ime . ')';
            revir_obavijest($sid, 'zauzeto', '🔴 ' . revir_oznaka($n) . ' – ' . $tko
                . ($preseli ? ' (prešao s ' . revir_oznaka(revir_naprava((int) $preseli['NapravaId']) ?? ['Naziv' => '?']) . ')' : ''));
            dnevnik('Lovište – zauzeta naprava', 'RevirZauzece', $id, revir_oznaka($n) . ($gost !== null ? ' · gost ' . $gost : ''));
            return null;
        });
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'UNIQUE')) {
            return 'Naprava je upravo zauzeta.';
        }
        throw $e;
    }
}

/** Oslobađanje: vlasnik zauzeća. */
function revir_oslobodi(int $zauzeceId): ?string
{
    $k = korisnik();
    $z = red('SELECT * FROM RevirZauzeca WHERE Id=? AND Kraj IS NULL', [$zauzeceId]);
    if (!$z) {
        return null; // već slobodno
    }
    if ((int) $z['ClanId'] !== (int) $k['ClanId']) {
        return 'To nije vaše zauzeće.';
    }
    revir_zavrsi($z, 'odjava');
    $n = revir_naprava((int) $z['NapravaId']);
    revir_obavijest($z['SekcijaId'] !== null ? (int) $z['SekcijaId'] : null, 'slobodno',
        '🟢 ' . ($n ? revir_oznaka($n) : '?') . ' – slobodno (' . ($z['Gost'] !== null ? 'gost ' . $z['Gost'] . ', ' : '') . $z['Ime'] . ' otišao)');
    return null;
}

/** Brisanje zauzeća: lovočuvar / nadzor (npr. zloupotreba, zaboravljeno). Ne ide u dnevnik. */
function revir_obrisi(int $zauzeceId): ?string
{
    $k = korisnik();
    $z = red('SELECT * FROM RevirZauzeca WHERE Id=? AND Kraj IS NULL', [$zauzeceId]);
    if (!$z) {
        return null;
    }
    $sid = $z['SekcijaId'] !== null ? (int) $z['SekcijaId'] : null;
    if (!revir_moze_nadzor($sid)) {
        return 'Nemate pravo brisati zauzeća u ovoj sekciji.';
    }
    revir_zavrsi($z, 'obrisano', $k['Naziv']);
    $n = revir_naprava((int) $z['NapravaId']);
    revir_obavijest($sid, 'obrisano', '⚪ ' . ($n ? revir_oznaka($n) : '?') . ' – zauzeće (' . ($z['Gost'] !== null ? 'gost ' . $z['Gost'] . ', ' : '') . $z['Ime'] . ') obrisao ' . $k['Naziv']);
    dnevnik('Lovište – obrisano zauzeće', 'RevirZauzece', (int) $z['Id'], ($n ? revir_oznaka($n) : '') . ' · ' . $z['Ime']);
    return null;
}

/** Trenutno stanje za kartu: naprave sekcija + aktivna zauzeća. */
function revir_stanje(array $sekcije): array
{
    revir_zatvori_istekle();
    $k = korisnik();
    $naprave = revir_naprave($sekcije);
    $zauz = [];
    if ($naprave) {
        foreach (redovi('SELECT * FROM RevirZauzeca WHERE Kraj IS NULL AND NapravaId IN (' . implode(',', array_map(fn($n) => (int) $n['Id'], $naprave)) . ')') as $z) {
            $zauz[(int) $z['NapravaId']] = $z;
        }
    }
    $out = [];
    foreach ($naprave as $n) {
        $sid = $n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null;
        $z = $zauz[(int) $n['Id']] ?? null;
        $out[] = [
            'id' => (int) $n['Id'], 'broj' => (string) $n['Broj'], 'naziv' => $n['Naziv'], 'vrsta' => (string) $n['Vrsta'],
            'sekcija' => revir_sid($sid), 'lat' => $n['Lat'] !== null ? (float) $n['Lat'] : null, 'lon' => $n['Lon'] !== null ? (float) $n['Lon'] : null,
            'foto' => $n['Foto'] ? foto_url($n['Foto']) : null, 'napomena' => (string) $n['Napomena'],
            'zauzeto' => $z ? [
                'id' => (int) $z['Id'], 'ime' => $z['Ime'], 'gost' => $z['Gost'], 'od' => date('H:i', strtotime($z['Od'])),
                'odDatum' => datum($z['Od']), 'moje' => $k['ClanId'] !== null && (int) $z['ClanId'] === (int) $k['ClanId'],
            ] : null,
            'mozeZauzeti' => revir_moze_zauzeti($sid), 'mozeObrisati' => revir_moze_nadzor($sid), 'mozeUrediti' => revir_moze_urediti($sid),
        ];
    }
    return $out;
}

// ---------- Obavijesti ----------
function revir_broj_obavijesti(): int
{
    $k = korisnik();
    if (!$k || !ima(P_REVIR_NADZOR)) {
        return 0;
    }
    $do = (int) vrijednost('SELECT DoId FROM RevirProcitano WHERE KorisnikId=?', [$k['Id']]);
    return (int) vrijednost('SELECT COUNT(*) FROM RevirObavijesti WHERE Id>? AND (KorisnikId IS NULL OR KorisnikId<>?) AND ' . revir_nadzor_sql(), [$do, $k['Id']]);
}
function revir_oznaci_procitano(): void
{
    $k = korisnik();
    $max = (int) vrijednost('SELECT COALESCE(MAX(Id),0) FROM RevirObavijesti');
    q('INSERT INTO RevirProcitano (KorisnikId, DoId) VALUES (?, ?) ON CONFLICT(KorisnikId) DO UPDATE SET DoId=excluded.DoId', [$k['Id'], $max]);
}

// ---------- Granice (KML / KMZ) ----------
/**
 * Čita granice iz KML-a (ili KMZ-a): poligoni i linije. Točke (pinovi) se ne uvoze.
 * Vraća ['oblici' => [['tip' => 'poligon'|'linija', 'ime' => ..., 'koord' => [[lat, lon], ...]], ...], 'tocaka' => n].
 */
function revir_citaj_kml(string $sadrzaj): array
{
    if (str_starts_with($sadrzaj, "PK")) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('KMZ nije podržan na ovom poslužitelju – u Google My Maps / Google Earth izvezite kao .kml.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'kmz');
        file_put_contents($tmp, $sadrzaj);
        $zip = new ZipArchive();
        $sadrzaj = '';
        if ($zip->open($tmp) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.kml')) {
                    $sadrzaj = (string) $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
        }
        @unlink($tmp);
        if ($sadrzaj === '') {
            throw new RuntimeException('U KMZ datoteci nema KML-a.');
        }
    }
    $dom = new DOMDocument();
    if (!@$dom->loadXML($sadrzaj, LIBXML_NONET)) {
        throw new RuntimeException('Datoteka nije ispravan KML.');
    }
    $xp = new DOMXPath($dom);
    $oblici = [];
    $tocaka = 0;
    $parse = function (string $txt): array {
        $r = [];
        foreach (preg_split('/\s+/', trim($txt)) as $t) {
            $d = explode(',', $t);
            if (count($d) >= 2 && is_numeric($d[0]) && is_numeric($d[1])) {
                $r[] = [round((float) $d[1], 7), round((float) $d[0], 7)];
            }
        }
        return $r;
    };
    foreach ($xp->query('//*[local-name()="Placemark"]') as $pm) {
        $imeN = $xp->query('./*[local-name()="name"]', $pm)->item(0);
        $ime = $imeN ? trim($imeN->textContent) : '';
        foreach ($xp->query('.//*[local-name()="Polygon"]', $pm) as $pg) {
            $c = $xp->query('.//*[local-name()="outerBoundaryIs"]//*[local-name()="coordinates"]', $pg)->item(0);
            if ($c && count($k = $parse($c->textContent)) >= 3) {
                $oblici[] = ['tip' => 'poligon', 'ime' => $ime, 'koord' => $k];
            }
        }
        foreach ($xp->query('.//*[local-name()="LineString" or local-name()="LinearRing"][not(ancestor::*[local-name()="Polygon"])]', $pm) as $ls) {
            $c = $xp->query('./*[local-name()="coordinates"]', $ls)->item(0);
            if ($c && count($k = $parse($c->textContent)) >= 2) {
                $oblici[] = ['tip' => 'linija', 'ime' => $ime, 'koord' => $k];
            }
        }
        $tocaka += $xp->query('.//*[local-name()="Point"]', $pm)->length;
    }
    return ['oblici' => $oblici, 'tocaka' => $tocaka];
}

function revir_granice(): array
{
    $d = revir_granice_datoteka();
    if (!is_file($d)) {
        return [];
    }
    $j = json_decode((string) file_get_contents($d), true);
    return is_array($j) ? ($j['oblici'] ?? []) : [];
}

/** Početni prikaz karte: ručno zadan [lat, lon, zoom] ili null. */
function revir_centar(): ?array
{
    $c = postavka('Revir.Centar');
    if ($c && preg_match('/^(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(\d+)$/', $c, $m)) {
        return [(float) $m[1], (float) $m[2], (int) $m[3]];
    }
    return null;
}

/** Spremanje koordinata iz obrasca/JSON-a (null ako neispravno). */
function revir_koord(mixed $lat, mixed $lon): ?array
{
    if (!is_numeric($lat) || !is_numeric($lon)) {
        return null;
    }
    $lat = (float) $lat;
    $lon = (float) $lon;
    if (abs($lat) > 90 || abs($lon) > 180 || ($lat == 0.0 && $lon == 0.0)) {
        return null;
    }
    return [round($lat, 7), round($lon, 7)];
}
