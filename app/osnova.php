<?php
/**
 * Lovačka evidencija – jezgra (konfiguracija, baza, pomoćne funkcije).
 * Učitava se iz index.php; sve ostalo ide preko ove datoteke.
 */
declare(strict_types=1);

const VERZIJA = '1.2.2';
const KORIJEN = __DIR__ . '/..';

// ---------- Prava (bit-zastavice, iste kao u .NET verziji) ----------
const P_CLANOVI_CITAJ = 1;
const P_CLANOVI_UREDI = 2;
const P_CLANARINA_CITAJ = 4;
const P_CLANARINA_UREDI = 8;
const P_AKCIJE_CITAJ = 16;
const P_AKCIJE_ODOBRI = 32;
const P_IZVJESTAJI = 64;
const P_SUSTAV = 128;
const P_KALENDAR = 256;
const P_IMENIK_SVI = 512;
const P_OSNOVNA = 255; // prava iz .NET verzije
const P_SVE = 1023;

const PRAVA_OPIS = [
    P_CLANOVI_CITAJ => ['Članovi – pregled', 'vidi popis članova i njihove podatke'],
    P_CLANOVI_UREDI => ['Članovi – uređivanje', 'dodaje i uređuje članove, sekcije članova, funkcije, fotografije, arhivira'],
    P_CLANARINA_CITAJ => ['Članarina – pregled', 'vidi članarinu i uplate'],
    P_CLANARINA_UREDI => ['Članarina – uređivanje', 'zadužuje članarinu i upisuje uplate'],
    P_AKCIJE_CITAJ => ['Radne akcije – pregled', 'vidi radne akcije svih članova'],
    P_AKCIJE_ODOBRI => ['Radne akcije – unos i odobravanje', 'upisuje radne akcije i bodove (i za grupu), odobrava i odbija prijave članova'],
    P_IZVJESTAJI => ['Izvještaji i poruke', 'izvještaji, ispis, PDF, Excel, slanje poruka'],
    P_SUSTAV => ['Sustav', 'pristup članova (pozivnice, odobrenje, uloge), sekcije, uloge, uvoz, duplikati, sigurnosne kopije, dnevnik'],
    P_KALENDAR => ['Kalendar – uređivanje', 'dodaje, mijenja i briše termine u kalendaru (za sekcije u svom opsegu)'],
    P_IMENIK_SVI => ['Imenik – vidi sve kontakte', 'u imeniku vidi telefon, e-mail i adresu svih članova, i kad ih član nije podijelio'],
];

// Statusi (enum vrijednosti kao u .NET verziji)
const CLAN_AKTIVAN = 0, CLAN_ARHIVIRAN = 1;
const AKCIJA_CEKA = 0, AKCIJA_ODOBRENO = 1, AKCIJA_ODBIJENO = 2;

// ---------- Konfiguracija ----------
function cfg(string $kljuc, mixed $zadano = null): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [];
        $dat = KORIJEN . '/config.php';
        if (is_file($dat)) {
            $c = (array) require $dat;
        }
    }
    return $c[$kljuc] ?? $zadano;
}

function podaci(string $pod = ''): string
{
    static $mapa = null;
    if ($mapa === null) {
        $m = (string) cfg('podaci', KORIJEN . '/podaci');
        if (!preg_match('#^(/|[A-Za-z]:)#', $m)) {
            $m = KORIJEN . '/' . $m;
        }
        $mapa = rtrim($m, '/\\');
        foreach (['', '/foto', '/kopije'] as $d) {
            if (!is_dir($mapa . $d)) {
                @mkdir($mapa . $d, 0775, true);
            }
        }
        $ht = $mapa . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
    }
    return $pod === '' ? $mapa : $mapa . '/' . ltrim($pod, '/');
}

// ---------- Baza ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $dat = podaci('evidencija.db');
    $nova = !is_file($dat) || filesize($dat) === 0;
    $pdo = new PDO('sqlite:' . $dat, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    try {
        $pdo->exec('PRAGMA journal_mode = WAL;');
    } catch (Throwable) {
        // neki hostinzi ne dopuštaju WAL – radi i bez njega
    }
    if ($nova) {
        $pdo->exec((string) file_get_contents(__DIR__ . '/shema.sql'));
    }
    nadogradi_bazu($pdo);
    $pdo->sqliteCreateFunction('kljuc', fn($s) => kljuc((string) $s), 1);
    return $pdo;
}

/**
 * Dodatne tablice PHP verzije (PRAGMA user_version). .NET verzija ih ne poznaje i zanemaruje,
 * pa baza i dalje radi u oba smjera.
 */
const SHEMA_PHP = 2;
function nadogradi_bazu(PDO $pdo): void
{
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= SHEMA_PHP) {
        return;
    }
    $pdo->beginTransaction();
    if ($v < 1) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "Dogadjaji" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Naslov" TEXT NOT NULL,
    "Vrsta" TEXT NOT NULL,
    "Pocetak" TEXT NOT NULL,
    "Kraj" TEXT NULL,
    "CijeliDan" INTEGER NOT NULL DEFAULT 0,
    "Mjesto" TEXT NULL,
    "Opis" TEXT NULL,
    "ZaSve" INTEGER NOT NULL DEFAULT 1,
    "KreiraoId" INTEGER NULL,
    "KreiraoIme" TEXT NULL,
    "Kreirano" TEXT NOT NULL,
    "Azurirano" TEXT NULL,
    "AkcijeUpisane" TEXT NULL
);
CREATE INDEX IF NOT EXISTS "IX_Dogadjaji_Pocetak" ON "Dogadjaji" ("Pocetak");
CREATE TABLE IF NOT EXISTS "DogadjajSekcije" (
    "DogadjajId" INTEGER NOT NULL REFERENCES "Dogadjaji" ("Id") ON DELETE CASCADE,
    "SekcijaId" INTEGER NOT NULL REFERENCES "Sekcije" ("Id") ON DELETE CASCADE,
    PRIMARY KEY ("DogadjajId", "SekcijaId")
);
CREATE TABLE IF NOT EXISTS "DogadjajOdgovori" (
    "DogadjajId" INTEGER NOT NULL REFERENCES "Dogadjaji" ("Id") ON DELETE CASCADE,
    "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Dolazi" INTEGER NOT NULL,
    "Vrijeme" TEXT NOT NULL,
    PRIMARY KEY ("DogadjajId", "ClanId")
);
CREATE TABLE IF NOT EXISTS "ClanarinaRate" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ClanarinaId" INTEGER NOT NULL REFERENCES "Clanarine" ("Id") ON DELETE CASCADE,
    "RedniBroj" INTEGER NOT NULL,
    "Iznos" TEXT NOT NULL,
    "Dospijece" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "IX_ClanarinaRate_ClanarinaId" ON "ClanarinaRate" ("ClanarinaId");
CREATE TABLE IF NOT EXISTS "ClanVidljivost" (
    "ClanId" INTEGER NOT NULL PRIMARY KEY REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Mobitel" INTEGER NOT NULL DEFAULT 0,
    "Fiksni" INTEGER NOT NULL DEFAULT 0,
    "Email" INTEGER NOT NULL DEFAULT 0,
    "Mjesto" INTEGER NOT NULL DEFAULT 0,
    "Adresa" INTEGER NOT NULL DEFAULT 0,
    "Azurirano" TEXT NULL
);
CREATE TABLE IF NOT EXISTS "Razgovori2" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "KorisnikA" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    "KorisnikB" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    "Kreirano" TEXT NOT NULL,
    "ZadnjaPoruka" TEXT NOT NULL,
    UNIQUE ("KorisnikA", "KorisnikB")
);
CREATE TABLE IF NOT EXISTS "Poruke2" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "RazgovorId" INTEGER NOT NULL REFERENCES "Razgovori2" ("Id") ON DELETE CASCADE,
    "PosiljateljId" INTEGER NOT NULL,
    "Tekst" TEXT NOT NULL,
    "Vrijeme" TEXT NOT NULL,
    "Procitano" INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS "IX_Poruke2_RazgovorId" ON "Poruke2" ("RazgovorId");
UPDATE "Uloge" SET "Prava" = "Prava" | 768 WHERE ("Prava" & 255) = 255;
UPDATE "Uloge" SET "Prava" = "Prava" | 256 WHERE "Naziv" LIKE 'Predsjednik%' OR "Naziv" LIKE 'Lovnik%' OR "Naziv" = 'Domar';
SQL);
    }
    if ($v < 2) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS "SkriveniRazgovori" ("Vrsta" TEXT NOT NULL, "RazgovorId" INTEGER NOT NULL, "KorisnikId" INTEGER NOT NULL,
            "DoPorukeId" INTEGER NOT NULL, PRIMARY KEY ("Vrsta", "RazgovorId", "KorisnikId"))');
    }
    $pdo->exec('PRAGMA user_version = ' . SHEMA_PHP);
    $pdo->commit();
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function red(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}
function redovi(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}
function vrijednost(string $sql, array $p = []): mixed
{
    $v = q($sql, $p)->fetchColumn();
    return $v === false ? null : $v;
}
function umetni(string $tablica, array $polja): int
{
    $k = array_keys($polja);
    $sql = 'INSERT INTO "' . $tablica . '" ("' . implode('","', $k) . '") VALUES (' . implode(',', array_fill(0, count($k), '?')) . ')';
    q($sql, array_values($polja));
    return (int) db()->lastInsertId();
}
function azuriraj(string $tablica, int $id, array $polja): void
{
    $set = implode(',', array_map(fn($k) => '"' . $k . '"=?', array_keys($polja)));
    q('UPDATE "' . $tablica . '" SET ' . $set . ' WHERE Id=?', [...array_values($polja), $id]);
}
function transakcija(callable $fn): mixed
{
    db()->beginTransaction();
    try {
        $r = $fn();
        db()->commit();
        return $r;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function sada(): string
{
    return date('Y-m-d H:i:s');
}
function danas(): string
{
    return date('Y-m-d');
}

// ---------- Postavke (tablica Postavke) ----------
function postavka(string $k, ?string $zadano = null): ?string
{
    static $sve = null;
    if ($sve === null || $k === '__osvjezi') {
        $sve = [];
        foreach (redovi('SELECT Kljuc, Vrijednost FROM Postavke') as $r) {
            $sve[$r['Kljuc']] = $r['Vrijednost'];
        }
        if ($k === '__osvjezi') {
            return null;
        }
    }
    $v = $sve[$k] ?? null;
    return ($v === null || $v === '') ? $zadano : $v;
}
function spremi_postavku(string $k, ?string $v): void
{
    if ($v === null || $v === '') {
        q('DELETE FROM Postavke WHERE Kljuc=?', [$k]);
    } else {
        q('INSERT INTO Postavke (Kljuc, Vrijednost) VALUES (?, ?) ON CONFLICT(Kljuc) DO UPDATE SET Vrijednost=excluded.Vrijednost', [$k, $v]);
    }
    postavka('__osvjezi');
}

// ---------- Udruga ----------
function udruga_naziv(): string
{
    return postavka('Udruga.Naziv', 'Lovačka udruga');
}
function udruga_kratko(): string
{
    return postavka('Udruga.Kratko', 'Evidencija');
}
function udruga_podnaslov(): string
{
    return postavka('Udruga.Podnaslov', 'Evidencija članova');
}
function ima_logo(): bool
{
    $l = postavka('Udruga.Logo');
    return $l && is_file(podaci('foto/' . basename($l)));
}

// ---------- Tekst ----------
function e(mixed $s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function bez_dijakritika(?string $s): string
{
    if ($s === null || $s === '') {
        return '';
    }
    $s = strtr($s, ['č' => 'c', 'ć' => 'c', 'Č' => 'C', 'Ć' => 'C', 'š' => 's', 'Š' => 'S', 'ž' => 'z', 'Ž' => 'Z', 'đ' => 'd', 'Đ' => 'D']);
    if (class_exists('Normalizer')) {
        $s = (string) Normalizer::normalize($s, Normalizer::FORM_D);
        $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
    }
    return $s;
}
function kljuc(?string $s): string
{
    return mb_strtolower(trim(bez_dijakritika($s)));
}
function ispravan_oib(?string $oib): bool
{
    if (!$oib || !preg_match('/^\d{11}$/', $oib)) {
        return false;
    }
    $a = 10;
    for ($i = 0; $i < 10; $i++) {
        $a = ($a + (int) $oib[$i]) % 10;
        if ($a === 0) {
            $a = 10;
        }
        $a = ($a * 2) % 11;
    }
    $k = 11 - $a;
    if ($k === 10) {
        $k = 0;
    }
    return $k === (int) $oib[10];
}
function broj(mixed $v, int $dec = 1): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $f = (float) $v;
    $s = number_format($f, $dec, ',', '.');
    if ($dec > 0 && str_contains($s, ',')) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s;
}
function novac(mixed $v): string
{
    return number_format((float) $v, 2, ',', '.');
}
/** Pretvara unos "6,5" / "6.5" u broj ili null. */
function u_broj(mixed $s): ?float
{
    $s = trim(str_replace([' ', "\u{a0}"], '', (string) $s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float) $s : null;
}
/** Decimalni zapis kao u EF Core (TEXT, invariant). */
function dec(?float $v): ?string
{
    if ($v === null) {
        return null;
    }
    $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    return str_contains($s, '.') ? $s : $s . '.0';
}
function datum(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime(substr($d, 0, 19));
    return $t ? date('d.m.Y.', $t) : '';
}
function datum_vrijeme(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime(substr($d, 0, 19));
    return $t ? date('d.m.Y. H:i', $t) : '';
}
/** Datum iz <input type=date> → 'Y-m-d' ili null. */
function u_datum(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}$/', $s)) {
        return $s . '-01';
    }
    if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}
function puno_ime(?array $c): string
{
    return $c ? trim(($c['Ime'] ?? '') . ' ' . ($c['Prezime'] ?? '')) : '';
}
function prezime_ime(?array $c): string
{
    return $c ? trim(($c['Prezime'] ?? '') . ' ' . ($c['Ime'] ?? '')) : '';
}
function adresa(array $c): string
{
    $d = array_filter([trim(($c['Ulica'] ?? '') . ' ' . ($c['KucniBroj'] ?? '')), trim(($c['PostanskiBroj'] ?? '') . ' ' . ($c['Mjesto'] ?? ''))]);
    return implode(', ', $d);
}
function inicijali(?array $c): string
{
    if (!$c) {
        return '?';
    }
    return mb_strtoupper(mb_substr((string) ($c['Ime'] ?? ''), 0, 1) . mb_substr((string) ($c['Prezime'] ?? ''), 0, 1)) ?: '?';
}

// ---------- URL, preusmjeravanje, poruke ----------
function bazni_put(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/') . '/';
    }
    return $b;
}
/** url('clanovi/uredi', ['id'=>5]) → index.php?p=clanovi/uredi&id=5 */
function url(string $str = '', array $par = []): string
{
    $q = $str === '' ? $par : ['p' => $str] + $par;
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return bazni_put() . ($q ? 'index.php?' . str_replace('%2F', '/', http_build_query($q)) : '');
}
function asset(string $dat): string
{
    $put = KORIJEN . '/' . $dat;
    return bazni_put() . $dat . (is_file($put) ? '?v=' . filemtime($put) : '');
}
/** Javna apsolutna adresa (za poveznice u porukama). */
function javna_adresa(): string
{
    $a = postavka('Server.JavnaAdresa') ?: cfg('javna_adresa');
    if ($a) {
        return rtrim((string) $a, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . bazni_put();
}
function apsolutni_url(string $str, array $par = []): string
{
    $q = ['p' => $str] + $par;
    return javna_adresa() . 'index.php?' . str_replace('%2F', '/', http_build_query($q));
}
function preusmjeri(string $str = '', array $par = []): never
{
    header('Location: ' . url($str, $par));
    exit;
}
function poruka(string $tekst, string $vrsta = 'success'): void
{
    $_SESSION['poruke'][] = [$vrsta, $tekst];
}
function poruke(): array
{
    $p = $_SESSION['poruke'] ?? [];
    unset($_SESSION['poruke']);
    return $p;
}
function je_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
function ul(string $k, mixed $zadano = ''): mixed
{
    return $_POST[$k] ?? $_GET[$k] ?? $zadano;
}
function ul_str(string $k): string
{
    return trim((string) ul($k, ''));
}
function ul_null(string $k): ?string
{
    $v = trim((string) ($_POST[$k] ?? ''));
    return $v === '' ? null : $v;
}
function ul_int(string $k): ?int
{
    $v = ul($k, null);
    return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function provjeri_csrf(): void
{
    if (je_post() && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
        http_response_code(400);
        exit('Sigurnosna provjera nije uspjela (istekla stranica). Vratite se i pokušajte ponovno.');
    }
}

// ---------- Prikaz ----------
/** Učitava predložak iz app/predlosci s varijablama. */
function predlozak(string $ime, array $var = []): string
{
    extract($var, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/predlosci/' . $ime . '.php';
    return (string) ob_get_clean();
}
/** Ispisuje stranicu u glavnom okviru. $sadrzaj je HTML. */
function stranica(string $naslov, string $sadrzaj, string $okvir = 'okvir'): never
{
    echo predlozak($okvir, ['naslov' => $naslov, 'sadrzaj' => $sadrzaj]);
    exit;
}
function zabranjeno(): never
{
    http_response_code(403);
    stranica('Zabranjeno', '<div class="alert alert-warning mt-3">Nemate pravo pristupa ovoj stranici.</div>');
}
function nije_pronadjeno(string $sto = 'Stranica'): never
{
    http_response_code(404);
    stranica('Nije pronađeno', '<div class="alert alert-secondary mt-3">' . e($sto) . ' nije pronađen(a).</div>');
}
function json(mixed $podaci): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($podaci, JSON_UNESCAPED_UNICODE);
    exit;
}
function sel(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}
function chk(mixed $v): string
{
    return $v ? ' checked' : '';
}

// ---------- Dnevnik ----------
function dnevnik(string $radnja, ?string $entitet = null, ?int $entitetId = null, ?string $detalji = null, ?array $tko = null): void
{
    $k = $tko ?? korisnik();
    umetni('Dnevnik', [
        'Vrijeme' => sada(),
        'KorisnikId' => $k['Id'] ?? null,
        'Korisnik' => $k['KorisnickoIme'] ?? null,
        'Radnja' => $radnja,
        'Entitet' => $entitet,
        'EntitetId' => $entitetId,
        'Detalji' => $detalji !== null ? mb_substr($detalji, 0, 2000) : null,
    ]);
}

// ---------- Učitavanje ostalih modula ----------
foreach (glob(__DIR__ . '/lib/*.php') as $__dat) {
    require_once $__dat;
}
unset($__dat);
