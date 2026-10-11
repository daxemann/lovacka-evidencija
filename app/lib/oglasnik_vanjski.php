<?php
/**
 * Zajednički Oglasnik lovaca (vanjski, npr. https://oglasnik.free.je).
 * Članovi ulaze preko jednokratnog potpisanog tokena (Ed25519) – bez posebne lozinke.
 * Privatni ključ udruge ostaje samo u ovoj bazi (šifrirano), Oglasnik zna samo javni ključ.
 */
declare(strict_types=1);

const OGL_ZADANA_ADRESA = 'https://oglasnik.free.je';

function ogl_adresa(): string
{
    return rtrim((string) postavka('Oglasnik.Adresa', OGL_ZADANA_ADRESA), '/');
}
function ogl_podrzano(): bool
{
    return extension_loaded('sodium') && function_exists('sodium_crypto_sign_detached');
}
function ogl_javni_kljuc(): ?string
{
    return postavka('Oglasnik.JavniKljuc');
}
/** Kod udruge u Oglasniku = otisak javnog ključa. */
function ogl_kod(): ?string
{
    $pk = ogl_javni_kljuc();
    return $pk ? substr(hash('sha256', (string) base64_decode($pk)), 0, 24) : null;
}
function ogl_generiraj_kljuc(): void
{
    // stari ključ se čuva da Oglasnik prepozna zamjenu (novi ključ potpisan starim) – udruga ostaje ista
    if (postavka('Oglasnik.JavniKljuc') && postavka('Oglasnik.TajniKljuc')) {
        spremi_postavku('Oglasnik.StariJavniKljuc', postavka('Oglasnik.JavniKljuc'));
        spremi_postavku('Oglasnik.StariTajniKljuc', postavka('Oglasnik.TajniKljuc'));
    }
    $kp = sodium_crypto_sign_keypair();
    spremi_postavku('Oglasnik.TajniKljuc', sifriraj(base64_encode(sodium_crypto_sign_secretkey($kp))));
    spremi_postavku('Oglasnik.JavniKljuc', base64_encode(sodium_crypto_sign_publickey($kp)));
    sodium_memzero($kp);
}
function ogl_tajni_kljuc(): ?string
{
    $t = desifriraj(postavka('Oglasnik.TajniKljuc'));
    $b = $t ? base64_decode($t, true) : false;
    return $b !== false && strlen($b) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $b : null;
}
/** Potvrda zamjene ključa: novi javni ključ potpisan starim privatnim ključem. */
function ogl_zamjena(): ?array
{
    $stariPk = postavka('Oglasnik.StariJavniKljuc');
    $t = desifriraj(postavka('Oglasnik.StariTajniKljuc'));
    $sk = $t ? base64_decode($t, true) : false;
    if (!$stariPk || $sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        return null;
    }
    $sig = ogl_b64url(sodium_crypto_sign_detached('rotate:' . ogl_javni_kljuc(), $sk));
    sodium_memzero($sk);
    return ['old_pub' => $stariPk, 'sig' => $sig];
}

/** Je li Oglasnik uključen za članove ove udruge? (Demo verzija nikad nije spojena na pravi Oglasnik.) */
function ogl_aktivan(): bool
{
    return !je_demo() && ogl_podrzano() && postavka('Oglasnik.Aktivan') === '1' && ogl_javni_kljuc() !== null;
}

function ogl_b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
/** Potpisuje JSON: base64url(payload) . "." . base64url(potpis). */
function ogl_potpisi(array $payload): ?array
{
    $sk = ogl_tajni_kljuc();
    if (!$sk) {
        return null;
    }
    $p64 = ogl_b64url(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $sig = ogl_b64url(sodium_crypto_sign_detached($p64, $sk));
    sodium_memzero($sk);
    return [$p64, $sig];
}

/** Pravila udruge za Oglasnik (šalju se uz svaku prijavu, Oglasnik ih provodi). */
function ogl_politika(): array
{
    return [
        'active' => ogl_aktivan(),
        'publish' => postavka('Oglasnik.Objava', 'internal') === 'public' ? 'public' : 'internal',
        'see_public' => postavka('Oglasnik.VidiJavne', '1') === '1',
        'phone_allowed' => postavka('Oglasnik.Telefon', '1') === '1',
        'public_approval' => postavka('Oglasnik.Odobravanje', 'instant') === 'approval' ? 'approval' : 'instant',
        'cross_chat' => postavka('Oglasnik.Poruke', '1') === '1',
    ];
}
function ogl_kontakt(): array
{
    return [
        'name' => (string) postavka('Oglasnik.KontaktIme', ''),
        'phone' => (string) postavka('Oglasnik.KontaktTelefon', ''),
        'email' => (string) postavka('Oglasnik.KontaktEmail', ''),
    ];
}

/** Stranica koja preglednikom šalje (POST) podatke Oglasniku. */
function ogl_salji_obrazac(string $akcija, array $polja, string $poruka): never
{
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="referrer" content="no-referrer"><title>Oglasnik</title></head>'
        . '<body style="font-family:system-ui,sans-serif;text-align:center;padding:3rem 1rem;color:#1f3d1f">'
        . '<p>' . e($poruka) . '</p>'
        . '<form id="f" method="post" action="' . e($akcija) . '">';
    foreach ($polja as $k => $v) {
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<noscript><button>Nastavi</button></noscript></form>'
        . '<script>document.getElementById("f").submit();</script></body></html>';
    exit;
}

/** Povratna adresa (za slučaj da Oglasnikova zaštita od botova pri prvom posjetu "pojede" zahtjev). */
function ogl_povratak(string $stranica, array $par = []): string
{
    $par = array_filter($par, fn($v) => $v !== null && $v !== '');
    return ogl_baza() . 'index.php?' . http_build_query(['p' => $stranica] + $par);
}

/**
 * Javna HTTPS adresa ove aplikacije za poveznice prema Oglasniku.
 * Iza Tailscale Funnela / proxyja PHP vidi "http", pa se prednost daje postavljenoj javnoj adresi.
 */
function ogl_baza(): string
{
    $j = javna_adresa();
    if (str_starts_with($j, 'https://')) {
        return $j;
    }
    $t = trenutna_adresa();
    if (str_starts_with($t, 'http://') && !adresa_privatna($t)) {
        return 'https://' . substr($t, 7);
    }
    return $t;
}
