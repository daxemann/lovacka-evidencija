<?php
/**
 * Dvofaktorska prijava (TOTP, RFC 6238) – Google Authenticator, Microsoft Authenticator, Aegis…
 * Obavezna samo za administratore (pravo „Sustav“); ostali članovi se prijavljuju samo lozinkom.
 * Isključivanje u nuždi: 'dvofaktorska' => false u config.php.
 */
declare(strict_types=1);

function dvofaktor_ukljucen(): bool
{
    return !je_demo() && cfg('dvofaktorska', true) !== false;
}

/** Prava korisnika iz baze (prije prijave). */
function prava_korisnika(int $korisnikId): int
{
    $p = 0;
    foreach (redovi('SELECT u.Prava FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId WHERE ku.KorisnikId=?', [$korisnikId]) as $r) {
        $p |= (int) $r['Prava'];
    }
    return $p;
}
/** Mora li korisnik imati dvofaktorsku prijavu? (administratori = pravo „Sustav“) */
function treba_2fa(int $korisnikId): bool
{
    return dvofaktor_ukljucen() && (prava_korisnika($korisnikId) & P_SUSTAV) === P_SUSTAV;
}
function ima_2fa(array $racun): bool
{
    return dvofaktor_ukljucen() && !empty($racun['TotpTajna']);
}

// ---------- Base32 ----------
function base32_kodiraj(string $b): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bitovi = '';
    foreach (str_split($b) as $z) {
        $bitovi .= str_pad(decbin(ord($z)), 8, '0', STR_PAD_LEFT);
    }
    $o = '';
    foreach (str_split($bitovi, 5) as $dio) {
        $o .= $abc[bindec(str_pad($dio, 5, '0'))];
    }
    return $o;
}
function base32_dekodiraj(string $s): string
{
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $s));
    $bitovi = '';
    foreach (str_split($s) as $z) {
        $bitovi .= str_pad(decbin((int) strpos($abc, $z)), 5, '0', STR_PAD_LEFT);
    }
    $o = '';
    foreach (str_split($bitovi, 8) as $dio) {
        if (strlen($dio) === 8) {
            $o .= chr(bindec($dio));
        }
    }
    return $o;
}

// ---------- TOTP ----------
function totp_nova_tajna(): string
{
    return base32_kodiraj(random_bytes(20));
}
function totp_kod(string $tajna, int $korak): string
{
    $h = hash_hmac('sha1', pack('J', $korak), base32_dekodiraj($tajna), true);
    $o = ord($h[19]) & 0x0f;
    $br = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($br % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Vraća korak (vremenski prozor) ako je kod ispravan (±1 prozor = ±30 s), inače null. */
function totp_provjeri(string $tajna, string $kod, int $zadnji = 0): ?int
{
    $kod = (string) preg_replace('/\D/', '', $kod);
    if (strlen($kod) !== 6) {
        return null;
    }
    $sad = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) {
        $korak = $sad + $d;
        if ($korak > $zadnji && hash_equals(totp_kod($tajna, $korak), $kod)) {
            return $korak;
        }
    }
    return null;
}
function totp_uri(string $tajna, string $korisnik): string
{
    $izdavac = udruga_kratko();
    return 'otpauth://totp/' . rawurlencode($izdavac . ':' . $korisnik) . '?secret=' . $tajna . '&issuer=' . rawurlencode($izdavac) . '&algorithm=SHA1&digits=6&period=30';
}

// ---------- Rezervni kodovi ----------
/** @return array{0: string[], 1: string} [čitljivi kodovi, JSON hasheva za bazu] */
function rezervni_kodovi_novi(int $n = 8): array
{
    $kodovi = [];
    for ($i = 0; $i < $n; $i++) {
        $k = strtolower(bin2hex(random_bytes(4)));
        $kodovi[] = substr($k, 0, 4) . '-' . substr($k, 4);
    }
    return [$kodovi, json_encode(array_map(fn($k) => password_hash(str_replace('-', '', $k), PASSWORD_DEFAULT), $kodovi))];
}
/** Provjerava i troši rezervni kod. */
function rezervni_kod_iskoristi(array $racun, string $kod): bool
{
    $kod = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', $kod));
    if (strlen($kod) !== 8) {
        return false;
    }
    $hashevi = json_decode((string) ($racun['TotpRezervni'] ?? ''), true) ?: [];
    foreach ($hashevi as $i => $h) {
        if (password_verify($kod, $h)) {
            unset($hashevi[$i]);
            azuriraj('Korisnici', (int) $racun['Id'], ['TotpRezervni' => json_encode(array_values($hashevi))]);
            return true;
        }
    }
    return false;
}
function broj_rezervnih(array $racun): int
{
    return count(json_decode((string) ($racun['TotpRezervni'] ?? ''), true) ?: []);
}

/** Tajna iz baze (šifrirana). */
function totp_tajna(array $racun): ?string
{
    return $racun['TotpTajna'] ? desifriraj($racun['TotpTajna']) : null;
}
