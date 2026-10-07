<?php
/** Prijava, lozinke, prava i opseg (sekcije). */
declare(strict_types=1);

// ---------- Lozinke (format kompatibilan s ASP.NET Identity v3) ----------
const MIN_DULJINA_LOZINKE = 8;

function hash_lozinke(string $lozinka): string
{
    $sol = random_bytes(16);
    $iter = 100000;
    $kljuc = hash_pbkdf2('sha512', $lozinka, $sol, $iter, 32, true);
    return base64_encode("\x01" . pack('N', 2) . pack('N', $iter) . pack('N', 16) . $sol . $kljuc);
}

function provjeri_lozinku(string $hash, string $lozinka): bool
{
    if ($hash === '') {
        return false;
    }
    $b = base64_decode($hash, true);
    if ($b === false || strlen($b) < 14) {
        return password_verify($lozinka, $hash);
    }
    if ($b[0] === "\x01") {
        $prf = unpack('N', substr($b, 1, 4))[1];
        $iter = unpack('N', substr($b, 5, 4))[1];
        $sl = unpack('N', substr($b, 9, 4))[1];
        $sol = substr($b, 13, $sl);
        $kljuc = substr($b, 13 + $sl);
        $alg = [0 => 'sha1', 1 => 'sha256', 2 => 'sha512'][$prf] ?? null;
        if (!$alg || strlen($kljuc) < 16) {
            return false;
        }
        return hash_equals($kljuc, hash_pbkdf2($alg, $lozinka, $sol, $iter, strlen($kljuc), true));
    }
    if ($b[0] === "\x00" && strlen($b) === 49) { // Identity v2
        return hash_equals(substr($b, 17), hash_pbkdf2('sha1', $lozinka, substr($b, 1, 16), 1000, 32, true));
    }
    return false;
}

function pravila_lozinke(?string $l): ?string
{
    if ($l === null || trim($l) === '' || mb_strlen($l) < MIN_DULJINA_LOZINKE) {
        return 'Lozinka mora imati najmanje ' . MIN_DULJINA_LOZINKE . ' znakova.';
    }
    if (!preg_match('/\d/', $l) || !preg_match('/\p{L}/u', $l)) {
        return 'Lozinka mora sadržavati slova i brojke.';
    }
    return null;
}

function pocetna_lozinka(): string
{
    $sl = 'abcdefghjkmnpqrstuvwxyz';
    return 'Lovac-' . random_int(1000, 9998) . '-' . $sl[random_int(0, 22)] . $sl[random_int(0, 22)];
}
function novi_token(): string
{
    return bin2hex(random_bytes(32));
}
function hash_tokena(string $t): string
{
    return strtoupper(hash('sha256', $t));
}
function novi_zig(): string
{
    return bin2hex(random_bytes(16));
}
function predlozi_korisnicko_ime(string $ime, string $prezime): string
{
    return str_replace(' ', '', mb_strtolower(bez_dijakritika($ime) . '.' . bez_dijakritika($prezime)));
}

// ---------- Sesija ----------
function pokreni_sesiju(): void
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('evidencija');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => bazni_put(),
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $mapa = podaci('sesije');
    if (!is_dir($mapa)) {
        @mkdir($mapa, 0770, true);
    }
    if (is_dir($mapa) && is_writable($mapa)) {
        session_save_path($mapa);
    }
    ini_set('session.gc_maxlifetime', '2592000');
    session_start();
    // "zapamti me": trajni kolačić 30 dana
    if (!empty($_SESSION['kid']) && empty($_SESSION['trajno'])) {
        $_SESSION['trajno'] = 1;
    }
    if (!empty($_SESSION['trajno'])) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 2592000, 'path' => bazni_put(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

function prijavi(array $k): void
{
    session_regenerate_id(true);
    $_SESSION['kid'] = (int) $k['Id'];
    $_SESSION['zig'] = $k['SigurnosniZig'];
    $_SESSION['trajno'] = 1;
}

function odjavi(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => bazni_put()]);
    }
    session_destroy();
}

/**
 * Trenutni korisnik s pravima:
 * Id, KorisnickoIme, Naziv, ClanId, Prava, SveSekcije, Sekcije[], Uloge[], MoraPromijenitiLozinku
 */
function korisnik(bool $osvjezi = false): ?array
{
    static $k = false;
    if ($k !== false && !$osvjezi) {
        return $k;
    }
    $k = null;
    $id = (int) ($_SESSION['kid'] ?? 0);
    if (!$id) {
        return null;
    }
    $r = red('SELECT k.*, c.Ime, c.Prezime FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Id=?', [$id]);
    if (!$r || !$r['Aktivan'] || !$r['Odobren'] || !hash_equals((string) $r['SigurnosniZig'], (string) ($_SESSION['zig'] ?? ''))) {
        odjavi();
        return null;
    }
    $prava = 0;
    $sve = false;
    $sek = [];
    $nazivi = [];
    foreach (redovi('SELECT ku.SekcijaId, u.Prava, u.Naziv, s.Naziv AS Sekcija FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId LEFT JOIN Sekcije s ON s.Id=ku.SekcijaId WHERE ku.KorisnikId=?', [$id]) as $u) {
        $prava |= (int) $u['Prava'];
        if ($u['SekcijaId'] === null) {
            $sve = true;
        } else {
            $sek[(int) $u['SekcijaId']] = true;
        }
        $nazivi[] = $u['Naziv'] . ($u['Sekcija'] ? ' · ' . $u['Sekcija'] : '');
    }
    $naziv = $r['ClanId'] ? trim($r['Ime'] . ' ' . $r['Prezime']) : ($r['PrikaznoIme'] ?: $r['KorisnickoIme']);
    $k = [
        'Id' => $id,
        'KorisnickoIme' => $r['KorisnickoIme'],
        'Naziv' => $naziv,
        'ClanId' => $r['ClanId'] !== null ? (int) $r['ClanId'] : null,
        'Prava' => $prava,
        'SveSekcije' => $sve,
        'Sekcije' => array_keys($sek),
        'Uloge' => $nazivi,
        'MoraPromijenitiLozinku' => (bool) $r['MoraPromijenitiLozinku'],
    ];
    return $k;
}

function je_admin(): bool
{
    return (korisnik()['Prava'] ?? 0) !== 0;
}
function ima(int $pravo): bool
{
    return ((korisnik()['Prava'] ?? 0) & $pravo) === $pravo;
}
function trazi(int $pravo): void
{
    if (!ima($pravo)) {
        zabranjeno();
    }
}
function moze_sekciju(?int $sekcijaId): bool
{
    $k = korisnik();
    if (!$k) {
        return false;
    }
    if ($k['SveSekcije']) {
        return true;
    }
    return $sekcijaId !== null && in_array($sekcijaId, $k['Sekcije'], true);
}
/** SQL uvjet za opseg članova (alias tablice Clanovi). */
function opseg_sql(string $alias = 'c'): string
{
    $k = korisnik();
    if (!$k || $k['SveSekcije']) {
        return '1=1';
    }
    if (!$k['Sekcije']) {
        return '1=0';
    }
    return $alias . '.SekcijaId IN (' . implode(',', array_map('intval', $k['Sekcije'])) . ')';
}
/** Smije li trenutni korisnik raditi s ovim članom (opseg sekcija). */
function u_opsegu(?array $clan): bool
{
    if (!$clan) {
        return false;
    }
    return moze_sekciju($clan['SekcijaId'] !== null ? (int) $clan['SekcijaId'] : null);
}

/** Opis uloge za oznaku u zaglavlju. */
function oznaka_uloge(): array
{
    $k = korisnik();
    if (!$k || !$k['Prava']) {
        return ['Član', 'bg-secondary'];
    }
    if (($k['Prava'] & P_SVE) === P_SVE && $k['SveSekcije']) {
        return ['Glavni admin', 'bg-danger'];
    }
    return [$k['Uloge'][0] ?? 'Admin', 'bg-success'];
}

// ---------- Šifriranje (lozinka SMTP-a) ----------
function tajni_kljuc(): string
{
    $dat = podaci('kljuc.php');
    if (!is_file($dat)) {
        file_put_contents($dat, "<?php return '" . base64_encode(random_bytes(32)) . "';\n");
        @chmod($dat, 0600);
    }
    return base64_decode((string) require $dat);
}
function sifriraj(string $tekst): string
{
    $iv = random_bytes(12);
    $c = openssl_encrypt($tekst, 'aes-256-gcm', tajni_kljuc(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'php:' . base64_encode($iv . $tag . $c);
}
function desifriraj(?string $s): ?string
{
    if (!$s || !str_starts_with($s, 'php:')) {
        return null; // vrijednost iz .NET verzije se ne može pročitati – unesite lozinku ponovno
    }
    $b = base64_decode(substr($s, 4));
    $r = openssl_decrypt(substr($b, 28), 'aes-256-gcm', tajni_kljuc(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    return $r === false ? null : $r;
}
