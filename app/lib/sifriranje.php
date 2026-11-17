<?php
/**
 * Šifriranje osjetljivih podataka članova u bazi (AES-256-GCM, ključ = tajni_kljuc()).
 *
 * „Prozirni sloj“: dok je šifriranje uključeno, za svaku tablicu iz SIFRIRANE_KOLONE na svakoj vezi nastaje
 * privremeni pogled (TEMP VIEW) istog imena – pri čitanju sam dešifrira, pri pisanju okidači (TRIGGER) šifriraju.
 * Zato pretraga, sortiranje i prikaz u ostatku programa rade bez promjene.
 *  - umetni() u te tablice piše izravno u pravu tablicu (main.) – preko pogleda bi Id novog retka bio pogrešan.
 *  - Pogledi nastaju tek nakon migracija baze (ALTER TABLE ne radi na pogledu).
 *  - Nešifrirane vrijednosti čitaju se normalno → (de)šifriranje ide u obrocima, i prekid je bezopasan.
 * Stanje (Postavke „Sifriranje.Stanje“): '' isključeno · 'sifriram' · 'ukljuceno' · 'desifriram'.
 */
declare(strict_types=1);

/** Stupci koji se šifriraju (1. faza – bez imena, GPS-a, poruka i datoteka). */
const SIFRIRANE_KOLONE = [
    'Clanovi' => ['Ulica', 'KucniBroj', 'PostanskiBroj', 'Mjesto', 'FiksniTelefon', 'MobilniTelefon', 'Email', 'Oib',
                  'DatumRodjenja', 'MjestoRodjenja', 'BrojIskaznice', 'BrojDiplome', 'Napomena'],
    'ClanVozila' => ['Oznaka'],
    'ClanOruzniListovi' => ['Broj'],
    'ClanDokumenti' => ['Broj', 'Napomena'],
    'DogadjajGosti' => ['Email', 'BrojIskaznice', 'OruzniList'],
    'DezUpisi' => ['Oznaka'],
];
const SIFRA_PREFIKS = 'php:';
const SIFRIRANJE_OBROK_REDAKA = 300;   // redaka po stupcu u jednom obroku
const SIFRIRANJE_OBROK_SEKUNDI = 4;    // najdulje po zahtjevu (jeftini hostinzi prekidaju nakon ~30 s)
const SIFRIRANJE_KOPIJA_SATI = 24;     // kompletna kopija smije biti stara najviše toliko

// ---------- Stanje ----------
/** Stanje izravno preko $pdo (postavka() bi ovdje pozvala db() – petlja). */
function sifriranje_stanje(?PDO $pdo = null): string
{
    try {
        return (string) ($pdo ?? db())->query("SELECT Vrijednost FROM Postavke WHERE Kljuc='Sifriranje.Stanje'")->fetchColumn();
    } catch (Throwable) {
        return '';
    }
}
function sifriranje_ukljuceno(): bool
{
    return sifriranje_stanje() === 'ukljuceno';
}
function sifriranje_u_tijeku(): bool
{
    return in_array(sifriranje_stanje(), ['sifriram', 'desifriram'], true);
}
/** Postavi stanje i otvori novu vezu (pogledi/okidači odgovaraju novom stanju). */
function sifriranje_postavi_stanje(string $stanje): void
{
    spremi_postavku('Sifriranje.Stanje', $stanje === '' ? null : $stanje);
    db(true);
    postavka('__osvjezi');
}

// ---------- Vrijednosti ----------
/** Šifrira jednu vrijednost (prazno i null ostaju takvi, već šifrirano se ne šifrira ponovno). */
function sif_vrijednost(mixed $v): mixed
{
    if ($v === null || $v === '' || (is_string($v) && str_starts_with($v, SIFRA_PREFIKS))) {
        return $v;
    }
    return sifriraj((string) $v);
}
/** Dešifrira jednu vrijednost; nešifrirano vraća kakvo jest. Ako ključ ne odgovara, vraća šifrat (bez gubitka podataka). */
function desif_vrijednost(mixed $v): mixed
{
    if (!is_string($v) || !str_starts_with($v, SIFRA_PREFIKS)) {
        return $v;
    }
    return desifriraj($v) ?? $v;
}

// ---------- Prozirni sloj (na svakoj vezi) ----------
/** Poziva db() nakon migracija: SQL funkcije, a dok šifriranje nije isključeno i pogledi + okidači. */
function sifriranje_pripremi(PDO $pdo): void
{
    $pdo->sqliteCreateFunction('sif_e', 'sif_vrijednost', 1);
    $pdo->sqliteCreateFunction('sif_d', 'desif_vrijednost', 1);
    if (sifriranje_stanje($pdo) === '') {
        return;
    }
    $pdo->exec('PRAGMA secure_delete = ON'); // obrisani/prepisani podaci se prebrišu nulama (bez ostataka u datoteci)
    foreach (SIFRIRANE_KOLONE as $t => $sif) {
        $stupci = array_column($pdo->query('PRAGMA main.table_info("' . $t . '")')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!$stupci) {
            continue;
        }
        $citaj = $upisi = $vrijednosti = $postavi = [];
        foreach ($stupci as $s) {
            $q = '"' . $s . '"';
            $tajno = in_array($s, $sif, true);
            $citaj[] = $tajno ? "sif_d($q) AS $q" : $q;
            $upisi[] = $q;
            $vrijednosti[] = $tajno ? "sif_e(NEW.$q)" : "NEW.$q";
            // nepromijenjeni stupac ostaje točno kakav je bio (nema ponovnog šifriranja ni gubitka)
            $postavi[] = $tajno ? "$q = CASE WHEN NEW.$q IS OLD.$q THEN $q ELSE sif_e(NEW.$q) END" : "$q = NEW.$q";
        }
        $pdo->exec("CREATE TEMP VIEW \"$t\" AS SELECT " . implode(', ', $citaj) . " FROM main.\"$t\"");
        $pdo->exec("CREATE TEMP TRIGGER \"{$t}_sif_ins\" INSTEAD OF INSERT ON \"$t\" BEGIN INSERT INTO main.\"$t\" (" . implode(', ', $upisi) . ') VALUES (' . implode(', ', $vrijednosti) . '); END');
        $pdo->exec("CREATE TEMP TRIGGER \"{$t}_sif_upd\" INSTEAD OF UPDATE ON \"$t\" BEGIN UPDATE main.\"$t\" SET " . implode(', ', $postavi) . ' WHERE "Id" = OLD."Id"; END');
        $pdo->exec("CREATE TEMP TRIGGER \"{$t}_sif_del\" INSTEAD OF DELETE ON \"$t\" BEGIN DELETE FROM main.\"$t\" WHERE \"Id\" = OLD.\"Id\"; END");
    }
}
/** Ide li umetni() u ovu tablicu izravno u main. (s šifriranjem u PHP-u)? */
function sifriranje_tablica(string $tablica): bool
{
    return isset(SIFRIRANE_KOLONE[$tablica]) && sifriranje_stanje() !== '';
}
function sifriraj_polja(string $tablica, array $polja): array
{
    foreach (SIFRIRANE_KOLONE[$tablica] ?? [] as $s) {
        if (array_key_exists($s, $polja)) {
            $polja[$s] = sif_vrijednost($polja[$s]);
        }
    }
    return $polja;
}

// ---------- Prebacivanje u obrocima ----------
/** SQL uvjet za vrijednosti koje još treba obraditi u smjeru $smjer. */
function sifriranje_uvjet(string $q, string $smjer): string
{
    return $smjer === 'sifriram' ? "$q IS NOT NULL AND $q <> '' AND substr($q, 1, 4) <> 'php:'" : "substr($q, 1, 4) = 'php:'";
}
/** Koliko vrijednosti je još preostalo (za traku napretka). */
function sifriranje_preostalo(string $smjer): int
{
    $n = 0;
    foreach (SIFRIRANE_KOLONE as $t => $sif) {
        foreach ($sif as $s) {
            $n += (int) vrijednost("SELECT COUNT(*) FROM main.\"$t\" WHERE " . sifriranje_uvjet('"' . $s . '"', $smjer));
        }
    }
    return $n;
}
/** Jedan obrok izravno nad main. tablicama, u transakciji. Vraća broj obrađenih vrijednosti (0 = gotovo). */
function sifriranje_obrok(string $smjer): int
{
    $pdo = db();
    $n = 0;
    $pdo->exec('BEGIN IMMEDIATE'); // drugi zahtjevi čekaju (busy_timeout) – nema dvostrukog obrađivanja
    try {
        foreach (SIFRIRANE_KOLONE as $t => $sif) {
            foreach ($sif as $s) {
                $q = '"' . $s . '"';
                $izraz = $smjer === 'sifriram' ? "sif_e($q)" : "sif_d($q)";
                $st = $pdo->prepare("UPDATE main.\"$t\" SET $q = $izraz WHERE \"Id\" IN (SELECT \"Id\" FROM main.\"$t\" WHERE " . sifriranje_uvjet($q, $smjer) . ' LIMIT ?)');
                $st->execute([SIFRIRANJE_OBROK_REDAKA]);
                $n += $st->rowCount();
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    return $n;
}
/**
 * Nastavi prebacivanje (poziva traka napretka i početna administratora). Radi najviše SIFRIRANJE_OBROK_SEKUNDI.
 * Vraća ['stanje' => …, 'preostalo' => …].
 */
function sifriranje_nastavi(): array
{
    $smjer = sifriranje_stanje();
    if (!in_array($smjer, ['sifriram', 'desifriram'], true)) {
        return ['stanje' => $smjer, 'preostalo' => 0];
    }
    @set_time_limit(60);
    $do = microtime(true) + SIFRIRANJE_OBROK_SEKUNDI;
    do {
        $n = sifriranje_obrok($smjer);
    } while ($n > 0 && microtime(true) < $do);
    if ($n === 0) {
        sifriranje_zavrsi($smjer);
        return ['stanje' => sifriranje_stanje(), 'preostalo' => 0];
    }
    return ['stanje' => $smjer, 'preostalo' => sifriranje_preostalo($smjer)];
}
/** Kraj prebacivanja: novo stanje, stare kopije, VACUUM (bez ostataka nešifriranih podataka u datoteci). */
function sifriranje_zavrsi(string $smjer): void
{
    if ($smjer === 'sifriram') {
        // kopije na poslužitelju su nešifrirane → brišu se i odmah nastaje nova (šifrirana) kopija
        foreach (glob(podaci('kopije/*.db')) ?: [] as $f) {
            @unlink($f);
        }
        sifriranje_postavi_stanje('ukljuceno');
        sifriranje_vacuum();
        napravi_kopiju('nakon-sifriranja');
        dnevnik('Šifriranje podataka članova: uključeno (sve šifrirano, stare kopije na poslužitelju obrisane)');
    } else {
        sifriranje_postavi_stanje('');
        sifriranje_vacuum();
        dnevnik('Šifriranje podataka članova: isključeno (sve dešifrirano)');
    }
    spremi_postavku('Sifriranje.SigurnosnaKopija', null);
}
/**
 * Zasebna „gola“ veza na bazu – bez pogleda i okidača ovog sloja. VACUUM i VACUUM INTO na vezi s pogledima ne rade
 * („views may not be indexed“), jer pogled istog imena zaklanja tablicu pri ponovnoj izradi indeksa.
 */
function db_gola(): PDO
{
    $pdo = new PDO('sqlite:' . podaci('evidencija.db'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 15000; PRAGMA secure_delete = ON;');
    return $pdo;
}
/** Kopija baze u datoteku (VACUUM INTO) – sažeta, bez ostataka obrisanih podataka. */
function baza_kopija_u(string $cilj): void
{
    $pdo = db_gola();
    $pdo->exec('VACUUM INTO ' . $pdo->quote($cilj));
}
/** Prepiše cijelu datoteku baze – nakon (de)šifriranja ne ostaju stare vrijednosti u slobodnim stranicama. */
function sifriranje_vacuum(): void
{
    try {
        $pdo = db_gola();
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $pdo->exec('VACUUM');
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    } catch (Throwable $e) {
        error_log('Šifriranje – VACUUM nije uspio: ' . $e->getMessage());
    }
}

// ---------- Uključivanje / isključivanje ----------
/** Zašto se šifriranje (još) ne može uključiti – '' = može. */
function sifriranje_prepreka(): string
{
    if (je_demo()) {
        return 'U demo verziji šifriranje se ne može mijenjati.';
    }
    if (sifriranje_stanje() !== '') {
        return 'Šifriranje je već uključeno ili je prebacivanje u tijeku.';
    }
    if (!kopija_lozinka_postavljena()) {
        return 'Prvo postavite lozinku kopije.';
    }
    $zadnja = kopija_zadnje_preuzimanje();
    if (!$zadnja || strtotime($zadnja) < time() - SIFRIRANJE_KOPIJA_SATI * 3600) {
        return 'Prvo preuzmite kompletnu kopiju (najviše ' . SIFRIRANJE_KOPIJA_SATI . ' h staru) i spremite je izvan poslužitelja.';
    }
    return '';
}
function sifriranje_ukljuci(): void
{
    if (($p = sifriranje_prepreka()) !== '') {
        throw new RuntimeException($p);
    }
    $kopija = napravi_kopiju('prije-sifriranja'); // za slučaj greške; briše se kad je sve šifrirano
    spremi_postavku('Sifriranje.SigurnosnaKopija', $kopija);
    sifriranje_postavi_stanje('sifriram');
    dnevnik('Šifriranje podataka članova: pokrenuto', null, null, 'kopija prije: ' . $kopija);
}
/** Isključivanje: lozinka za prijavu + 2FA. E-mail svima s pravom „Sustav“. */
function sifriranje_iskljuci(array $racun, string $lozinka, string $kod2fa): void
{
    if (je_demo()) {
        throw new RuntimeException('U demo verziji šifriranje se ne može mijenjati.');
    }
    if (sifriranje_stanje() !== 'ukljuceno') {
        throw new RuntimeException('Šifriranje nije uključeno.');
    }
    if (!provjeri_lozinku((string) $racun['LozinkaHash'], $lozinka)) {
        usleep(500000);
        dnevnik('Šifriranje: isključivanje – pogrešna lozinka za prijavu');
        throw new RuntimeException('Vaša lozinka za prijavu nije točna.');
    }
    if (dvofaktor_ukljucen() && !ima_2fa($racun)) {
        throw new RuntimeException('Za isključivanje je potrebna dvofaktorska prijava – prvo je uključite (Moj profil → Dvofaktorska prijava).');
    }
    if (!kopija_provjeri_2fa($racun, $kod2fa)) {
        usleep(500000);
        dnevnik('Šifriranje: isključivanje – pogrešan 2FA kod');
        throw new RuntimeException('2FA kod nije točan (ili je već iskorišten).');
    }
    sifriranje_postavi_stanje('desifriram');
    $ime = kopija_ime_korisnika($racun);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    dnevnik('Šifriranje podataka članova: isključivanje pokrenuto', null, null, 'IP ' . $ip);
    $tijelo = '<p>Šifriranje podataka članova za <b>' . e(udruga_kratko()) . '</b> je <b>isključio/la ' . e($ime) . '</b> ('
        . e(date('d.m.Y. H:i')) . ', IP ' . e($ip) . '). Podaci se ponovno spremaju nešifrirano.</p>'
        . '<p>Ako to niste bili vi ili ne znate za tu promjenu, prijavite se i u <b>Sustav → Sigurnosne kopije</b> ponovno uključite šifriranje, zatim promijenite lozinke za prijavu.</p>';
    foreach (kopija_admini_emailovi() as $em) {
        try {
            posalji_mail($em, '🔓 Šifriranje isključeno – ' . udruga_kratko(), $tijelo);
        } catch (Throwable $e) {
            error_log('Obavijest o šifriranju nije poslana: ' . $e->getMessage());
        }
    }
}

// ---------- Čišćenje nešifriranih kopija na poslužitelju ----------
/**
 * Dok je šifriranje uključeno, briše nešifrirane kopije baze u podaci/kopije (npr. stare kopije koje je drugi poslužitelj
 * donio sa sobom). Jednom dnevno, pri prvom zahtjevu – radi i bez prijave.
 */
function sifriranje_pocisti_kopije(): void
{
    if (sifriranje_stanje() !== 'ukljuceno') {
        return;
    }
    $oznaka = podaci('kopije/.sif-ocisceno');
    if (is_file($oznaka) && file_get_contents($oznaka) === date('Y-m-d')) {
        return;
    }
    @file_put_contents($oznaka, date('Y-m-d'));
    $obrisano = [];
    foreach (glob(podaci('kopije/*.db')) ?: [] as $f) {
        if (kopija_je_sifrirana($f) === false && @unlink($f)) {
            @unlink($f . '-wal'); // pomoćne datoteke SQLite-a
            @unlink($f . '-shm');
            $obrisano[] = basename($f);
        }
    }
    if ($obrisano) {
        dnevnik('Šifriranje: obrisane nešifrirane kopije na poslužitelju', null, null, count($obrisano) . ': ' . implode(', ', $obrisano));
    }
}
/** Je li kopija nastala dok je šifriranje bilo uključeno? null = ne može se pročitati (tada se ne briše). */
function kopija_je_sifrirana(string $datoteka): ?bool
{
    try {
        $pdo = new PDO('sqlite:' . $datoteka, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $sifrirana = $pdo->query("SELECT Vrijednost FROM Postavke WHERE Kljuc='Sifriranje.Stanje'")->fetchColumn() === 'ukljuceno';
        $pdo = null; // zatvoriti prije brisanja (Windows ne briše otvorenu datoteku)
        return $sifrirana;
    } catch (Throwable) {
        return null;
    }
}

/** Početna (administratori): prebacivanje je u tijeku – nastavlja se otvaranjem Sigurnosnih kopija. */
function sifriranje_obavijest_html(): string
{
    if (!ima(P_SUSTAV) || !sifriranje_u_tijeku()) {
        return '';
    }
    $tekst = sifriranje_stanje() === 'sifriram' ? '🔐 <b>Šifriranje podataka članova je u tijeku.</b>' : '🔓 <b>Dešifriranje podataka članova je u tijeku.</b>';
    return '<div class="alert alert-info d-flex flex-wrap align-items-center gap-2 no-print"><span class="me-auto">' . $tekst
        . ' Program radi normalno; prebacivanje se nastavlja dok je otvorena stranica Sigurnosne kopije.</span>'
        . '<a class="btn btn-sm btn-info" href="' . e(url('sustav/kopije')) . '#sifriranje">Nastavi</a></div>';
}
