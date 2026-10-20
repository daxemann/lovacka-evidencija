<?php
/** Sigurnosne kopije: dnevna kopija baze, kompletna kopija (ZIP) i vraćanje. */
declare(strict_types=1);

const BROJ_DNEVNIH_KOPIJA = 30;

// ---------- Lozinka kopije (šifriranje svake kopije koja napušta poslužitelj) ----------
// Na poslužitelju se čuva samo provjera (hash), nikad sama lozinka. Zaboravljena lozinka = kopija se ne može otvoriti.

const KOPIJA_LOZINKA_MIN = 10;
const KOPIJA_EVK_ZAGLAVLJE = "EVKOPIJA1\n";   // vlastiti format kad PHP nema AES-ZIP
const KOPIJA_EVK_ITERACIJE = 200000;
const KOPIJA_EVK_KOMAD = 1048576;             // 1 MB po komadu – malo memorije i za velike kopije

function kopija_lozinka_postavljena(): bool
{
    return (string) postavka('Kopije.Provjera', '') !== '';
}
function kopija_provjeri_lozinku(string $lozinka): bool
{
    $h = (string) postavka('Kopije.Provjera', '');
    return $h !== '' && $lozinka !== '' && password_verify($lozinka, $h);
}
/** Prazno = u redu, inače poruka o grešci. */
function kopija_lozinka_greska(string $l, string $l2): string
{
    if (mb_strlen($l) < KOPIJA_LOZINKA_MIN) {
        return 'Lozinka kopije mora imati najmanje ' . KOPIJA_LOZINKA_MIN . ' znakova.';
    }
    if ($l !== $l2) {
        return 'Lozinke se ne podudaraju.';
    }
    return '';
}
function kopija_postavi_lozinku(string $lozinka): void
{
    spremi_postavku('Kopije.Provjera', password_hash($lozinka, PASSWORD_DEFAULT));
}
/** Podržava li PHP na ovom poslužitelju AES-256 ZIP (otvara se i 7-Zipom)? */
function kopija_aes_zip(): bool
{
    return class_exists('ZipArchive') && method_exists('ZipArchive', 'isEncryptionMethodSupported')
        && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256);
}

/**
 * Šifrira gotov ZIP lozinkom kopije. Vraća ['put' => …, 'nastavak' => 'zip'|'evk'].
 * Prednost ima AES-256 ZIP (otvara se i 7-Zipom); bez njega vlastiti format .evk (AES-256-GCM po komadima).
 */
function kopija_sifriraj(string $zipPut, string $lozinka): array
{
    if ($lozinka === '') {
        throw new RuntimeException('Nedostaje lozinka kopije.');
    }
    if (kopija_aes_zip()) {
        $z = new ZipArchive();
        if ($z->open($zipPut) !== true) {
            throw new RuntimeException('ZIP se ne može otvoriti za šifriranje.');
        }
        for ($i = 0; $i < $z->numFiles; $i++) {
            if (!$z->setEncryptionIndex($i, ZipArchive::EM_AES_256, $lozinka)) {
                $z->close();
                throw new RuntimeException('Šifriranje ZIP-a nije uspjelo.');
            }
        }
        if (!$z->close()) {
            throw new RuntimeException('Šifriranje ZIP-a nije uspjelo.');
        }
        return ['put' => $zipPut, 'nastavak' => 'zip'];
    }
    $cilj = tempnam(sys_get_temp_dir(), 'evk');
    kopija_evk_sifriraj($zipPut, $cilj, $lozinka);
    @unlink($zipPut);
    return ['put' => $cilj, 'nastavak' => 'evk'];
}

function kopija_evk_kljuc(string $lozinka, string $sol, int $iter): string
{
    return hash_pbkdf2('sha256', $lozinka, $sol, $iter, 32, true);
}
/** Format .evk: zaglavlje | sol(16) | iteracije(4) | prefiks nonce(8) | komadi [duljina(4) | šifrat | tag(16)]. */
function kopija_evk_sifriraj(string $izvor, string $cilj, string $lozinka): void
{
    $sol = random_bytes(16);
    $prefiks = random_bytes(8);
    $kljuc = kopija_evk_kljuc($lozinka, $sol, KOPIJA_EVK_ITERACIJE);
    $ul = fopen($izvor, 'rb');
    $iz = fopen($cilj, 'wb');
    if (!$ul || !$iz) {
        throw new RuntimeException('Privremena datoteka se ne može otvoriti.');
    }
    fwrite($iz, KOPIJA_EVK_ZAGLAVLJE . $sol . pack('N', KOPIJA_EVK_ITERACIJE) . $prefiks);
    $n = 0;
    $komad = (string) fread($ul, KOPIJA_EVK_KOMAD);
    do {
        $sljedeci = feof($ul) ? '' : (string) fread($ul, KOPIJA_EVK_KOMAD);
        $zadnji = $sljedeci === '' ? "\x01" : "\x00";
        $c = openssl_encrypt($komad, 'aes-256-gcm', $kljuc, OPENSSL_RAW_DATA, $prefiks . pack('N', $n), $tag, pack('N', $n) . $zadnji, 16);
        if ($c === false) {
            throw new RuntimeException('Šifriranje nije uspjelo.');
        }
        fwrite($iz, pack('N', strlen($c)) . $c . $tag);
        $komad = $sljedeci;
        $n++;
    } while ($zadnji === "\x00");
    fclose($ul);
    fclose($iz);
}
/** Dešifrira .evk u privremeni ZIP. Kriva lozinka ili oštećena datoteka → iznimka. */
function kopija_evk_desifriraj(string $izvor, string $lozinka): string
{
    $ul = fopen($izvor, 'rb');
    if (!$ul || fread($ul, strlen(KOPIJA_EVK_ZAGLAVLJE)) !== KOPIJA_EVK_ZAGLAVLJE) {
        throw new RuntimeException('Datoteka nije šifrirana kopija evidencije.');
    }
    $sol = (string) fread($ul, 16);
    $iter = unpack('N', (string) fread($ul, 4))[1] ?? 0;
    $prefiks = (string) fread($ul, 8);
    if (strlen($sol) !== 16 || strlen($prefiks) !== 8 || $iter < 10000 || $iter > 10000000) {
        throw new RuntimeException('Šifrirana kopija je oštećena.');
    }
    $kljuc = kopija_evk_kljuc($lozinka, $sol, $iter);
    $cilj = tempnam(sys_get_temp_dir(), 'evzip');
    $iz = fopen($cilj, 'wb');
    $n = 0;
    $kraj = false;
    while (!$kraj) {
        $d = fread($ul, 4);
        if ($d === false || strlen($d) !== 4) {
            break; // datoteka prekinuta prije zadnjeg komada
        }
        $len = unpack('N', $d)[1];
        if ($len > KOPIJA_EVK_KOMAD + 16) {
            break;
        }
        $c = $len ? (string) fread($ul, $len) : '';
        $tag = (string) fread($ul, 16);
        $kraj = feof($ul) || fread($ul, 1) === '' ;
        if (!$kraj) {
            fseek($ul, -1, SEEK_CUR);
        }
        $p = openssl_decrypt($c, 'aes-256-gcm', $kljuc, OPENSSL_RAW_DATA, $prefiks . pack('N', $n), $tag, pack('N', $n) . ($kraj ? "\x01" : "\x00"));
        if ($p === false) {
            fclose($ul);
            fclose($iz);
            @unlink($cilj);
            throw new RuntimeException($n === 0 ? 'Pogrešna lozinka kopije (ili oštećena datoteka).' : 'Šifrirana kopija je oštećena.');
        }
        fwrite($iz, $p);
        $n++;
    }
    fclose($ul);
    fclose($iz);
    if (!$kraj) {
        @unlink($cilj);
        throw new RuntimeException('Šifrirana kopija je nepotpuna (prekinuto preuzimanje?).');
    }
    return $cilj;
}

/** Kopija baze (.db iz podaci/kopije) zapakirana i šifrirana za preuzimanje. */
function kopija_baze_za_preuzimanje(string $dbPut, string $lozinka): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na poslužitelju nedostaje PHP proširenje „zip“.');
    }
    $zipPut = tempnam(sys_get_temp_dir(), 'evzip');
    $z = new ZipArchive();
    if ($z->open($zipPut, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ZIP se ne može napraviti.');
    }
    $z->addFile($dbPut, basename($dbPut));
    $z->close();
    return kopija_sifriraj($zipPut, $lozinka);
}

/** Kopija baze u podaci/kopije (VACUUM INTO). Vraća ime datoteke. */
function napravi_kopiju(string $oznaka = 'rucno'): string
{
    revir_granice_podaci(); // stara datoteka granica → baza, da bude u kopiji
    $ime = 'evidencija-' . date('Y-m-d_His') . '-' . preg_replace('/[^a-z0-9\-]/', '', $oznaka) . '.db';
    $put = podaci('kopije/' . $ime);
    try {
        q('VACUUM INTO ' . db()->quote($put));
    } catch (Throwable) {
        copy(podaci('evidencija.db'), $put); // stariji SQLite
    }
    return $ime;
}

/** Jednom dnevno automatska kopija (poziva se pri radu aplikacije), čuva se zadnjih 30. */
function dnevna_kopija(): void
{
    $oznaka = podaci('kopije/.zadnja');
    if (is_file($oznaka) && file_get_contents($oznaka) === date('Y-m-d')) {
        return;
    }
    @file_put_contents($oznaka, date('Y-m-d'));
    if (!vrijednost('SELECT 1 FROM Clanovi LIMIT 1')) {
        return;
    }
    try {
        napravi_kopiju('dnevna');
    } catch (Throwable $e) {
        error_log('Dnevna kopija nije uspjela: ' . $e->getMessage());
        return;
    }
    $sve = glob(podaci('kopije/evidencija-*-dnevna.db')) ?: [];
    rsort($sve);
    foreach (array_slice($sve, BROJ_DNEVNIH_KOPIJA) as $stara) {
        @unlink($stara);
    }
}

function popis_kopija(): array
{
    $r = [];
    foreach (glob(podaci('kopije/*.db')) ?: [] as $f) {
        $r[] = ['ime' => basename($f), 'velicina' => filesize($f), 'vrijeme' => filemtime($f)];
    }
    usort($r, fn($a, $b) => $b['vrijeme'] <=> $a['vrijeme']);
    return $r;
}

/**
 * Kompletna kopija (ZIP): evidencija.db + foto/ + kljucevi/ – isti oblik kao u Windows / Home Assistant verziji.
 * Uvijek šifrirana lozinkom kopije (sadrži i ključ aplikacije). Vraća ['put' => …, 'nastavak' => 'zip'|'evk'].
 */
function napravi_kompletnu_kopiju(string $lozinka): array
{
    if ($lozinka === '') {
        throw new RuntimeException('Nedostaje lozinka kopije.');
    }
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na poslužitelju nedostaje PHP proširenje „zip“.');
    }
    revir_granice_podaci(); // stara datoteka granica → baza, da bude u kopiji
    $tmpDb = tempnam(sys_get_temp_dir(), 'evdb');
    @unlink($tmpDb);
    try {
        q('VACUUM INTO ' . db()->quote($tmpDb));
    } catch (Throwable) {
        copy(podaci('evidencija.db'), $tmpDb);
    }
    $zipPut = tempnam(sys_get_temp_dir(), 'evzip');
    $z = new ZipArchive();
    if ($z->open($zipPut, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ZIP se ne može napraviti.');
    }
    $z->addFile($tmpDb, 'evidencija.db');
    foreach (glob(podaci('foto/*')) ?: [] as $f) {
        if (is_file($f)) {
            $z->addFile($f, 'foto/' . basename($f));
        }
    }
    foreach (glob(podaci('kljucevi/*.xml')) ?: [] as $f) {
        $z->addFile($f, 'kljucevi/' . basename($f));
    }
    $z->addFromString('kljucevi/php-kljuc.txt', base64_encode(tajni_kljuc()));
    // gotovi PDF-ovi za inspekciju – za slučaj da program ne radi (otvoriti iz ZIP-a i pokazati / ispisati)
    foreach (kopija_pdf_inspekcija() as $ime => $sadrzaj) {
        $z->addFromString('PDF-za-inspekciju/' . $ime, $sadrzaj);
    }
    $z->addFromString('PROCITAJ.txt', "Kompletna kopija – Lovačka evidencija\n" . udruga_naziv() . "\nNapravljeno: " . date('d.m.Y. H:i') . "\n\n"
        . "POVJERLJIVO: sadrži osobne podatke članova. Kopija je šifrirana lozinkom kopije (AES-256).\nVraćanje: prvo pokretanje → „Vrati iz kompletne kopije“ ili Sustav → Sigurnosne kopije – upisati lozinku kopije.\nBez programa: ZIP otvoriti 7-Zipom i upisati istu lozinku.\n\n"
        . "Mapa PDF-za-inspekciju: cijela knjiga dezinfekcije po mjesecima (stalne i mobilne stanice) i izjava udruge kao gotovi PDF-ovi\n"
        . "na dan izrade kopije – ako program ne radi, otvorite ih i pokažite ili ispišite za inspekciju.\n");
    $z->close();
    @unlink($tmpDb);
    return kopija_sifriraj($zipPut, $lozinka);
}

/**
 * Vraća kompletnu kopiju (ZIP iz PHP, Windows ili Home Assistant verzije, šifrirani ZIP ili .evk). Trenutni podaci se zamjenjuju.
 * Starije nešifrirane kopije i dalje se mogu vratiti.
 */
function vrati_kompletnu_kopiju(string $put, string $lozinka = ''): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na poslužitelju nedostaje PHP proširenje „zip“.');
    }
    $privremeni = null;
    $f = @fopen($put, 'rb');
    $pocetak = $f ? (string) fread($f, strlen(KOPIJA_EVK_ZAGLAVLJE)) : '';
    if ($f) {
        fclose($f);
    }
    if ($pocetak === KOPIJA_EVK_ZAGLAVLJE) {
        if ($lozinka === '') {
            throw new RuntimeException('Kopija je šifrirana – upišite lozinku kopije.');
        }
        $privremeni = kopija_evk_desifriraj($put, $lozinka);
        $put = $privremeni;
    }
    try {
        vrati_kompletnu_kopiju_zip($put, $lozinka);
    } finally {
        if ($privremeni) {
            @unlink($privremeni);
        }
    }
}

function vrati_kompletnu_kopiju_zip(string $zipPut, string $lozinka): void
{
    $z = new ZipArchive();
    if ($z->open($zipPut) !== true) {
        throw new RuntimeException('Datoteka nije ispravan ZIP.');
    }
    $idx = $z->locateName('evidencija.db');
    if ($idx === false) {
        $z->close();
        throw new RuntimeException('U ZIP-u nema datoteke evidencija.db – to nije kompletna kopija.');
    }
    $sifrirano = (int) ($z->statIndex($idx)['encryption_method'] ?? 0) !== 0;
    if ($sifrirano) {
        if ($lozinka === '') {
            $z->close();
            throw new RuntimeException('Kopija je šifrirana – upišite lozinku kopije.');
        }
        $z->setPassword($lozinka);
    }
    $db = $z->getFromIndex($idx);
    if ($db === false) {
        $z->close();
        throw new RuntimeException($sifrirano ? 'Pogrešna lozinka kopije.' : 'evidencija.db se ne može pročitati.');
    }
    if (substr($db, 0, 15) !== 'SQLite format 3') {
        $z->close();
        throw new RuntimeException('evidencija.db nije ispravna baza.');
    }
    // provjera da je to baza evidencije
    $tmp = tempnam(sys_get_temp_dir(), 'evvr');
    file_put_contents($tmp, $db);
    $probna = new PDO('sqlite:' . $tmp);
    $tablice = $probna->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $probna = null;
    foreach (['Clanovi', 'Korisnici', 'Uloge', 'RadneAkcije'] as $t) {
        if (!in_array($t, $tablice, true)) {
            @unlink($tmp);
            $z->close();
            throw new RuntimeException('Baza u kopiji nije baza evidencije (nema tablice ' . $t . ').');
        }
    }
    if (vrijednost('SELECT 1 FROM Korisnici LIMIT 1')) {
        try {
            napravi_kopiju('prije-vracanja');
        } catch (Throwable) {
        }
    }
    // fotografije i ključevi
    for ($i = 0; $i < $z->numFiles; $i++) {
        $ime = (string) $z->getNameIndex($i);
        if (preg_match('#^(foto|kljucevi)/([^/]+)$#', $ime, $m) && $m[2] !== '' && !str_starts_with($m[2], '.')) {
            $sadrzaj = $z->getFromIndex($i);
            if ($m[1] === 'kljucevi' && $m[2] === 'php-kljuc.txt') {
                $k = base64_decode(trim((string) $sadrzaj), true);
                if ($k !== false && strlen($k) === 32) {
                    file_put_contents(podaci('kljuc.php'), "<?php return '" . base64_encode($k) . "';\n");
                }
                continue;
            }
            if ($m[1] === 'foto' && !preg_match('/\.(jpe?g|png|webp|gif)$/i', $m[2]) && !preg_match('/^dok-\d+-[0-9a-f]+\.pdf$/', $m[2])) {
                continue;
            }
            if ($m[1] === 'kljucevi' && !preg_match('/\.xml$/i', $m[2])) {
                continue;
            }
            $mapa = podaci($m[1]);
            if (!is_dir($mapa)) {
                @mkdir($mapa, 0775, true);
            }
            file_put_contents($mapa . '/' . basename($m[2]), $sadrzaj);
        }
    }
    $z->close();
    // zamjena baze
    $cilj = podaci('evidencija.db');
    foreach (['-wal', '-shm'] as $s) {
        @unlink($cilj . $s);
    }
    if (!@rename($tmp, $cilj)) {
        copy($tmp, $cilj);
        @unlink($tmp);
    }
    @unlink(podaci('kopije/.zadnja'));
}

/** Knjiga dezinfekcije (sve od početka) i izjava kao PDF – za kompletnu kopiju. Greška ne smije spriječiti kopiju. */
function kopija_pdf_inspekcija(): array
{
    $out = [];
    $dan = date('Y-m-d');
    @ini_set('memory_limit', '512M');
    @set_time_limit(600);
    try {
        foreach (['F' => 'stalne-stanice', 'M' => 'mobilne-stanice'] as $vrsta => $naziv) {
            $stanice = dez_stanice(false, $vrsta);
            if (!$stanice) {
                continue;
            }
            $f = dez_filtar(['Razdoblje' => 'Sve', 'Vrsta' => $vrsta]);
            $ids = array_map(fn($st) => (int) $st['Id'], $stanice);
            $upisi = dez_upisi($f, $ids, 100000);
            if (!$upisi && $vrsta === 'M') {
                continue;
            }
            // po mjesecu – PDF velike knjige (tisuće redaka) troši previše memorije, mjesečni su mali i brzi
            $poMjesecu = [];
            foreach ($upisi as $u) {
                $poMjesecu[substr($u['Vrijeme'], 0, 7)][] = $u;
            }
            if (!$poMjesecu) {
                $poMjesecu[date('Y-m')] = [];
            }
            ksort($poMjesecu);
            foreach ($poMjesecu as $mj => $redovi) {
                $fm = dez_filtar(['Razdoblje' => 'Slobodno', 'Od' => "$mj-01", 'Do' => date('Y-m-t', strtotime("$mj-01")), 'Vrsta' => $vrsta]);
                $liste = $vrsta === 'M' ? [] : dez_liste($fm, $ids);
                $dijelovi = array_chunk($redovi, DEZ_PDF_MAX) ?: [[]]; // jako puni mjeseci (skupni lovovi) u više dijelova – memorija
                foreach ($dijelovi as $i => $dio) {
                    $dod = count($dijelovi) > 1 ? '-dio' . ($i + 1) : '';
                    $out["$mj-dezinfekcija-$naziv$dod.pdf"] = dez_pdf($dio, $stanice, mjesec_godina("$mj-01") . ($dod ? ' – ' . ($i + 1) . '. dio' : '') . ' (stanje ' . date('d.m.Y.') . ')',
                        'sve sekcije', $i === count($dijelovi) - 1 ? $liste : []);
                    gc_collect_cycles();
                }
            }
        }
        $out["izjava-evidencija-dezinfekcije-$dan.pdf"] = izjava_pdf(dez_stanice(false, 'F'));
    } catch (Throwable $e) {
        $out['GRESKA-PDF.txt'] = 'PDF-ovi nisu napravljeni: ' . $e->getMessage();
    }
    return $out;
}
