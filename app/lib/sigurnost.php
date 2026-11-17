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
        'Ima2fa' => !empty($r['TotpTajna']),
    ];
    return $k;
}

function je_admin(): bool
{
    return (korisnik()['Prava'] ?? 0) !== 0;
}
function ima(int $pravo): bool
{
    // prava isključenih modula ne vrijede (Sustav → Moduli)
    if ($pravo & moduli_iskljucena_prava()) {
        return false;
    }
    return ((korisnik()['Prava'] ?? 0) & $pravo) === $pravo;
}
function trazi(int $pravo): void
{
    if (!ima($pravo)) {
        zabranjeno();
    }
}
/**
 * Opseg jednog prava: u kojim sekcijama korisnik ima baš to pravo (samo uloge koje ga daju).
 * Npr. Blagajnik „za sve sekcije“ ne proširuje lovište ni lovački dnevnik na sve sekcije. @return array{sve: bool, s: int[]}
 */
function opseg_prava(int $pravo): array
{
    static $pam = [];
    $k = korisnik();
    if (!$k) {
        return ['sve' => false, 's' => []];
    }
    $kl = $k['Id'] . ':' . $pravo;
    if (isset($pam[$kl])) {
        return $pam[$kl];
    }
    if (($k['Prava'] & $pravo) === 0 || (moduli_iskljucena_prava() & $pravo) === $pravo) {
        return $pam[$kl] = ['sve' => false, 's' => []];
    }
    $sve = false;
    $sek = [];
    foreach (redovi('SELECT ku.SekcijaId FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId WHERE ku.KorisnikId=? AND (u.Prava & ?) <> 0', [$k['Id'], $pravo]) as $r) {
        if ($r['SekcijaId'] === null) {
            $sve = true;
        } else {
            $sek[(int) $r['SekcijaId']] = true;
        }
    }
    return $pam[$kl] = ['sve' => $sve, 's' => array_keys($sek)];
}
function moze_sekciju_pravo(int $pravo, ?int $sekcijaId): bool
{
    $o = opseg_prava($pravo);
    return $o['sve'] || ($sekcijaId !== null && in_array($sekcijaId, $o['s'], true));
}

/** Opseg jednog od više prava (unija): npr. upis radnih akcija = „upis za druge“ ILI „odobravanje“. @return array{sve: bool, s: int[]} */
function opseg_prava_ili(array $prava): array
{
    $sve = false;
    $s = [];
    foreach ($prava as $p) {
        $o = opseg_prava((int) $p);
        $sve = $sve || $o['sve'];
        $s = array_merge($s, $o['s']);
    }
    return ['sve' => $sve, 's' => array_values(array_unique($s))];
}
/** SQL uvjet: članovi u sekcijama za koje korisnik ima (jedno od) prava. */
function opseg_sql_prava(int|array $prava, string $alias = 'c'): string
{
    $o = opseg_prava_ili((array) $prava);
    if ($o['sve']) {
        return '1=1';
    }
    if (!$o['s']) {
        return '1=0';
    }
    return $alias . '.SekcijaId IN (' . implode(',', array_map('intval', $o['s'])) . ')';
}
/** Smije li korisnik s ovim članom raditi baš ovo pravo (sekcija člana je u opsegu tog prava)? */
function moze_clana(int $pravo, ?array $clan): bool
{
    if (!$clan || !ima($pravo)) {
        return false;
    }
    return moze_sekciju_pravo($pravo, $clan['SekcijaId'] !== null ? (int) $clan['SekcijaId'] : null);
}
/** Sekcije u kojima korisnik ima pravo (za izbornike sekcija). */
function sekcije_prava(int $pravo): array
{
    $o = opseg_prava($pravo);
    return array_values(array_filter(sekcije(), fn($s) => $o['sve'] || in_array((int) $s['Id'], $o['s'], true)));
}
/** Smije li upisivati radne akcije drugima (upis za druge ili odobravanje). */
function smije_upis_akcija(): bool
{
    return ima(P_AKCIJE_UNOS) || ima(P_AKCIJE_ODOBRI);
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
    if (($k['Prava'] & P_OSNOVNA) === P_OSNOVNA && $k['SveSekcije']) {
        return ['Glavni admin', 'bg-danger'];
    }
    return [$k['Uloge'][0] ?? 'Admin', 'bg-success'];
}

// ---------- Šifriranje (tajne i osjetljivi podaci članova) ----------
/**
 * Datoteka s ključem. Zadano podaci/kljuc.php; sigurnije izvan javne mape weba: config.php → 'kljuc' => '/putanja/kljuc.php'
 * (tada ključ i baza nisu na istom mjestu). Postojeći ključ se pri prvoj upotrebi sam premjesti na novo mjesto.
 */
function kljuc_datoteka(): string
{
    $stari = podaci('kljuc.php');
    $novi = (string) cfg('kljuc', '');
    if ($novi === '' || $novi === $stari) {
        return $stari;
    }
    if (!is_file($novi) && is_file($stari)) {
        @mkdir(dirname($novi), 0700, true);
        if (@copy($stari, $novi) && @file_get_contents($novi) === @file_get_contents($stari)) {
            @chmod($novi, 0600);
            @unlink($stari); // ključ više ne smije biti uz bazu
        } else {
            return $stari; // premještanje nije uspjelo – radi i dalje sa starim mjestom
        }
    }
    return $novi;
}
/** Je li ključ izvan mape s podacima (preporuka za webhosting)? */
function kljuc_odvojen(): bool
{
    return kljuc_datoteka() !== podaci('kljuc.php');
}
function tajni_kljuc(): string
{
    static $kljuc = null; // jednom po zahtjevu – šifriranje stupaca ga treba za svaki redak
    if ($kljuc !== null) {
        return $kljuc;
    }
    $dat = kljuc_datoteka();
    if (!is_file($dat)) {
        file_put_contents($dat, "<?php return '" . base64_encode(random_bytes(32)) . "';\n");
        @chmod($dat, 0600);
    }
    return $kljuc = base64_decode((string) require $dat);
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

// ---------- Zaštitna zaglavlja (HTTP) ----------
/** Zaglavlja kojima preglednik zabranjuje opasne stvari – npr. ugradnju programa u tuđu stranicu (clickjacking). */
function posalji_sigurnosna_zaglavlja(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    // namjerno bez pravila za skripte: program ima ugrađene skripte, stroža pravila bi ih blokirala
    header("Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin'); // tokeni iz adrese ne odlaze drugim stranicama
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=(), payment=(), usb=()');
    if (je_https()) {
        header('Strict-Transport-Security: max-age=15552000'); // 180 dana samo HTTPS
    }
}

// ---------- Javno dostupna baza (npr. nginx ne poštuje .htaccess) ----------
const IZLOZENOST_PROVJERA_SEKUNDI = 6 * 3600; // dok je sve u redu: provjera svakih 6 h
const IZLOZENOST_PONOVNO_SEKUNDI = 30;        // dok je baza izložena: brzo otključavanje nakon popravka

/** Put baze s interneta (npr. /evidencija/podaci/evidencija.db) ili null ako je mapa s podacima izvan javne mape. */
function baza_javni_put(): ?string
{
    $korijen = str_replace('\\', '/', realpath(KORIJEN) ?: '');
    $mapa = str_replace('\\', '/', realpath(podaci()) ?: '');
    if ($korijen === '' || $mapa === '' || !str_starts_with($mapa . '/', rtrim($korijen, '/') . '/')) {
        return null;
    }
    $rel = trim(substr($mapa, strlen(rtrim($korijen, '/'))), '/');
    return bazni_put() . ($rel !== '' ? $rel . '/' : '') . 'evidencija.db';
}

/** Vraća li adresa početak SQLite baze? Čita najviše 16 bajtova; svaka greška ili tuđi odgovor = ne. */
function adresa_vraca_bazu(string $url, ?string $ip = null): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }
    $primljeno = '';
    $c = curl_init($url);
    $opcije = [
        CURLOPT_RANGE => '0-15',
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false, // samo provjera vlastitog poslužitelja, ništa se ne šalje
        CURLOPT_SSL_VERIFYHOST => 0,
        // prekid nakon 16 bajtova – i kad poslužitelj zanemari Range i šalje cijelu bazu
        CURLOPT_WRITEFUNCTION => function ($c, string $dio) use (&$primljeno): int {
            $primljeno .= $dio;
            return strlen($primljeno) >= 16 ? -1 : strlen($dio);
        },
    ];
    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = (int) (parse_url($url, PHP_URL_PORT) ?: 80);
    if ($ip && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
        // ravno na ovaj poslužitelj, bez zaobilaska preko interneta
        $opcije[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)];
    }
    curl_setopt_array($c, $opcije);
    curl_exec($c);
    curl_close($c);
    return str_starts_with($primljeno, 'SQLite format 3');
}

/** Provjeri sada: pokušaj preuzeti bazu s vlastitog poslužitelja, kao što bi to učinio netko s interneta. */
function provjeri_izlozenost_sada(): bool
{
    $put = baza_javni_put();
    if ($put === null) {
        return false;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST);
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    return adresa_vraca_bazu(($https ? 'https' : 'http') . '://' . $host . ':' . $port . $put, $_SERVER['SERVER_ADDR'] ?? null);
}

/** Je li baza javno dostupna? Rezultat se pamti (Postavke), da se provjera ne radi kod svakog otvaranja stranice. */
function baza_izlozena(): bool
{
    if (baza_javni_put() === null) {
        return false;
    }
    $izlozena = postavka('Sigurnost.BazaIzlozena') === '1';
    $zadnja = (int) postavka('Sigurnost.BazaProvjerena', '0');
    if (time() - $zadnja < ($izlozena ? IZLOZENOST_PONOVNO_SEKUNDI : IZLOZENOST_PROVJERA_SEKUNDI)) {
        return $izlozena;
    }
    spremi_postavku('Sigurnost.BazaProvjerena', (string) time()); // odmah – da istodobni zahtjevi ne provjeravaju svi
    $sada = provjeri_izlozenost_sada();
    if ($sada !== $izlozena) {
        spremi_postavku('Sigurnost.BazaIzlozena', $sada ? '1' : null);
        error_log($sada ? 'Evidencija: baza je javno dostupna – program zaključan.' : 'Evidencija: baza više nije javno dostupna – program otključan.');
    }
    return $sada;
}

/** Dok je baza javno dostupna, program je zaključan za sve – administrator to mora odmah primijetiti i popraviti. */
function zakljucaj_ako_je_baza_izlozena(string $stranica): void
{
    if (in_array($stranica, ['logo', 'ikona', 'manifest'], true) || !baza_izlozena()) {
        return;
    }
    http_response_code(503);
    header('Retry-After: ' . IZLOZENOST_PONOVNO_SEKUNDI);
    if (str_starts_with($stranica, 'api/')) {
        json(['greska' => 'zakljucano']);
    }
    stranica('Program je zaključan', '<h1 class="h4 mb-3">🔒 Program je privremeno zaključan</h1>'
        . '<p>Poslužitelj nije ispravno podešen, pa podaci udruge nisu zaštićeni. Program je zaključan dok administrator to ne popravi.</p>'
        . '<p class="small text-muted">Za administratora: mapa <code>podaci</code> mora biti zaštićena na poslužitelju (nginx ne poštuje datoteku .htaccess) '
        . 'ili premještena izvan javne mape weba (<code>config.php</code> → <code>\'podaci\'</code>). Upute: README, odjeljak „Zaštita podataka na nginxu“. '
        . 'Nakon popravka pričekajte pola minute i osvježite stranicu.</p>'
        . '<a href="' . e(url()) . '" class="btn btn-primary">Pokušaj ponovno</a>', 'prazno');
}
