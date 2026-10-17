<?php
/**
 * Push obavijesti na mobitel / računalo (Web Push) – stižu i kad je aplikacija zatvorena.
 * Bez vanjskih biblioteka: VAPID (RFC 8292) i šifriranje aes128gcm (RFC 8291) s PHP-ovim openssl.
 * Ključevi VAPID nastaju sami pri prvoj upotrebi i spremaju se u bazu (dio sigurnosne kopije).
 * iPhone: radi od iOS 16.4, samo kad je aplikacija dodana na početni zaslon.
 */
declare(strict_types=1);

/** Vrste obavijesti – član svaku može isključiti za sebe. */
const PUSH_VRSTE = [
    'poruke' => ['💬 Nove poruke', 'poruka od člana ili iz oglasnika'],
    'obavijesti' => ['📢 Obavijesti udruge', 'nova obavijest uprave'],
    'akcije' => ['🛠️ Radne akcije', 'vaša prijava odobrena / odbijena; prijave koje čekaju vaše odobrenje'],
    'kvarovi' => ['🔧 Kvarovi na napravama', 'nova prijava kvara, preuzeto, riješeno (lovočuvar / lovne naprave)'],
    'domovi' => ['🏡 Lovački domovi', 'vaša rezervacija odobrena / odbijena / otkazana; novi zahtjevi za rezervaciju (upravitelj doma)'],
    'loviste' => ['🌲 Zauzimanje čeka', 'tko je zauzeo ili oslobodio napravu u vašoj sekciji (nadzor lovišta)'],
];

const PUSH_DER_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

function b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64u_dek(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

/** Je li Web Push moguć na ovom poslužitelju (openssl s krivuljom P-256). */
function push_podrzan(): bool
{
    // demo: svi dijele isti račun – push bi stizao strancima
    return !je_demo() && function_exists('openssl_pkey_derive') && in_array('prime256v1', openssl_get_curve_names() ?: [], true);
}

/** Novi par ključeva P-256: [privatni PEM, javni 65 B nekomprimirano]. */
function push_novi_par(): array
{
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$k) {
        throw new RuntimeException('OpenSSL ne može stvoriti ključ (P-256).');
    }
    openssl_pkey_export($k, $pem);
    $d = openssl_pkey_get_details($k)['ec'];
    return [$pem, "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT), $k];
}

/** VAPID ključevi udruge: ['priv' => PEM, 'pub' => base64url]. */
function push_vapid(): array
{
    $priv = postavka('Push.VapidPrivatni');
    $pub = postavka('Push.VapidJavni');
    if (!$priv || !$pub) {
        [$priv, $raw] = push_novi_par();
        $pub = b64u($raw);
        spremi_postavku('Push.VapidPrivatni', $priv);
        spremi_postavku('Push.VapidJavni', $pub);
    }
    return ['priv' => (string) $priv, 'pub' => (string) $pub];
}

/** Javni ključ (65 B) u PEM za openssl. */
function push_pem_javni(string $raw): string
{
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(PUSH_DER_P256) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** DER potpis ECDSA → 64 B (r||s) za JWT ES256. */
function push_der_u_raw(string $der): string
{
    $p = 2;
    if (ord($der[1]) & 0x80) {
        $p += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $len = ord($der[$p + 1]);
        $int = ltrim(substr($der, $p + 2, $len), "\0");
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        $p += 2 + $len;
    }
    return $out;
}

function push_jwt(string $endpoint, array $vapid): string
{
    $u = parse_url($endpoint);
    $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $sub = postavka('Udruga.Email') ?: cfg('push_kontakt');
    $sub = $sub ? 'mailto:' . $sub : rtrim(javna_adresa(), '/');
    if (!str_starts_with($sub, 'mailto:') && !str_starts_with($sub, 'https://')) {
        $sub = 'mailto:' . KONTAKT_EMAIL;
    }
    $p = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])) . '.' . b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $sub], JSON_UNESCAPED_SLASHES));
    if (!openssl_sign($p, $der, $vapid['priv'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID potpis nije uspio.');
    }
    return $p . '.' . b64u(push_der_u_raw($der));
}

/** Šifrira sadržaj za jednu pretplatu (aes128gcm). */
function push_sifriraj(string $tekst, string $p256dh, string $auth): string
{
    $uaJavni = b64u_dek($p256dh);
    $tajna = b64u_dek($auth);
    if (strlen($uaJavni) !== 65 || strlen($tajna) !== 16) {
        throw new RuntimeException('Neispravna pretplata.');
    }
    [, $asJavni, $asKljuc] = push_novi_par();
    $zajednicki = openssl_pkey_derive(openssl_pkey_get_public(push_pem_javni($uaJavni)), $asKljuc, 32);
    if ($zajednicki === false) {
        throw new RuntimeException('ECDH nije uspio.');
    }
    $ikm = hash_hkdf('sha256', $zajednicki, 32, "WebPush: info\0" . $uaJavni . $asJavni, $tajna);
    $sol = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sol);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sol);
    $sifra = openssl_encrypt($tekst . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $oznaka);
    return $sol . pack('N', 4096) . chr(65) . $asJavni . $sifra . $oznaka;
}

// ---------- Pretplate ----------
function push_pretplati(int $korisnikId, string $endpoint, string $p256dh, string $auth, string $uredjaj): bool
{
    if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 1000 || strlen(b64u_dek($p256dh)) !== 65 || strlen(b64u_dek($auth)) !== 16) {
        return false;
    }
    q('INSERT INTO PushPretplate (KorisnikId, Endpoint, P256dh, Auth, Uredjaj, Kreirano, Greske) VALUES (?,?,?,?,?,?,0)
        ON CONFLICT(Endpoint) DO UPDATE SET KorisnikId=excluded.KorisnikId, P256dh=excluded.P256dh, Auth=excluded.Auth, Uredjaj=excluded.Uredjaj, Greske=0',
        [$korisnikId, $endpoint, $p256dh, $auth, mb_substr($uredjaj, 0, 80), sada()]);
    return true;
}
function push_odjavi(string $endpoint, ?int $korisnikId = null): void
{
    q('DELETE FROM PushPretplate WHERE Endpoint=?' . ($korisnikId ? ' AND KorisnikId=' . $korisnikId : ''), [$endpoint]);
}
/** Kratki naziv uređaja iz preglednika („Android · Chrome“). */
function push_uredjaj(string $ua): string
{
    $s = match (true) {
        (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iPhone/iPad',
        (bool) preg_match('/Android/i', $ua) => 'Android',
        (bool) preg_match('/Windows/i', $ua) => 'Windows',
        (bool) preg_match('/Macintosh/i', $ua) => 'Mac',
        (bool) preg_match('/Linux/i', $ua) => 'Linux',
        default => 'Uređaj',
    };
    $p = match (true) {
        (bool) preg_match('/Edg\//', $ua) => 'Edge',
        (bool) preg_match('/Firefox\//', $ua) => 'Firefox',
        (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung',
        (bool) preg_match('/Chrome\//', $ua) => 'Chrome',
        (bool) preg_match('/Safari\//', $ua) => 'Safari',
        default => '',
    };
    return $s . ($p ? ' · ' . $p : '');
}
function push_iskljuceno(int $korisnikId): array
{
    return array_column(redovi('SELECT Vrsta FROM PushIskljuceno WHERE KorisnikId=?', [$korisnikId]), 'Vrsta');
}
function push_spremi_iskljuceno(int $korisnikId, array $iskljuceno): void
{
    q('DELETE FROM PushIskljuceno WHERE KorisnikId=?', [$korisnikId]);
    foreach (array_intersect(array_keys(PUSH_VRSTE), $iskljuceno) as $v) {
        umetni('PushIskljuceno', ['KorisnikId' => $korisnikId, 'Vrsta' => $v]);
    }
}

// ---------- Primatelji ----------
/** Korisnici (Id) koji imaju pravo $pravo za sekciju ($sid null = bez sekcije – samo uloge za sve sekcije). */
function push_korisnici_s_pravom(int $pravo, ?int $sid): array
{
    $po = [];
    foreach (redovi('SELECT ku.KorisnikId, ku.SekcijaId, u.Prava FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId
            JOIN Korisnici k ON k.Id=ku.KorisnikId WHERE k.Aktivan=1 AND k.Odobren=1') as $r) {
        $id = (int) $r['KorisnikId'];
        $po[$id] ??= ['p' => 0, 'sve' => false, 's' => []];
        $po[$id]['p'] |= (int) $r['Prava'];
        if ($r['SekcijaId'] === null) {
            $po[$id]['sve'] = true;
        } else {
            $po[$id]['s'][(int) $r['SekcijaId']] = true;
        }
    }
    $out = [];
    foreach ($po as $id => $x) {
        if (($x['p'] & $pravo) === $pravo && ($x['sve'] || ($sid !== null && isset($x['s'][$sid])))) {
            $out[] = $id;
        }
    }
    return $out;
}
/** Korisnički računi članova. */
function push_korisnici_clanova(array $clanIds): array
{
    $clanIds = array_values(array_filter(array_map('intval', $clanIds)));
    $out = [];
    foreach (array_chunk($clanIds, 500) as $dio) {
        foreach (redovi('SELECT Id FROM Korisnici WHERE Aktivan=1 AND Odobren=1 AND ClanId IN (' . implode(',', $dio) . ')') as $r) {
            $out[] = (int) $r['Id'];
        }
    }
    return $out;
}

// ---------- Slanje ----------
/**
 * Stavlja obavijest u red; šalje se na kraju zahtjeva (nakon odgovora pregledniku), paralelno.
 * Trenutni korisnik ne dobiva obavijest o vlastitoj radnji.
 * $dodatno: 'gumbi' => [['a' => radnja, 't' => tekst, 'url' => adresa koju poziva service worker]] (Android),
 *           'ostaje' => true (ne nestaje sama), 'vibracija' => [ms, …], 'poKorisniku' => [korisnikId => dodatna polja].
 * Vrste koje nisu u PUSH_VRSTE (npr. SOS, poziv na grupni lov) član ne može isključiti.
 */
function push_posalji(array $korisnikIds, string $vrsta, string $naslov, string $tekst, string $str = '', array $par = [], ?string $oznaka = null, array $dodatno = []): void
{
    static $registrirano = false;
    if (!push_podrzan() || !postavka('Push.VapidJavni')) {
        return; // nitko se još nije pretplatio – nema ključeva ni pretplata
    }
    $ja = korisnik()['Id'] ?? null;
    $korisnikIds = array_values(array_unique(array_filter(array_map('intval', $korisnikIds), fn($id) => $id && $id !== $ja)));
    if (!$korisnikIds) {
        return;
    }
    $pretplate = [];
    foreach (array_chunk($korisnikIds, 500) as $dio) {
        $pretplate = array_merge($pretplate, redovi('SELECT p.* FROM PushPretplate p WHERE p.KorisnikId IN (' . implode(',', $dio) . ')
            AND NOT EXISTS (SELECT 1 FROM PushIskljuceno i WHERE i.KorisnikId=p.KorisnikId AND i.Vrsta=?)', [$vrsta]));
    }
    if (!$pretplate) {
        return;
    }
    $osnova = [
        't' => mb_substr($naslov, 0, 80), 'b' => mb_substr($tekst, 0, 240), 'u' => apsolutni_url($str !== '' ? $str : 'pocetna', $par),
        'g' => $oznaka ?? $vrsta, 'i' => apsolutni_url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')]),
    ] + array_filter(['r' => !empty($dodatno['ostaje']) ? 1 : null, 'v' => $dodatno['vibracija'] ?? null, 'a' => $dodatno['gumbi'] ?? null]);
    $tema = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $oznaka ?? $vrsta), 0, 32);
    if (!empty($dodatno['poKorisniku'])) {
        // različit sadržaj po primatelju (npr. osobni gumbi „Dolazim / Ne dolazim“)
        $po = [];
        foreach ($pretplate as $p) {
            $po[(int) $p['KorisnikId']][] = $p;
        }
        foreach ($po as $kid => $lista) {
            $GLOBALS['__push_red'][] = ['pretplate' => $lista, 'tema' => $tema,
                'sadrzaj' => json_encode(array_replace($osnova, $dodatno['poKorisniku'][$kid] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
    } else {
        $GLOBALS['__push_red'][] = ['pretplate' => $pretplate, 'tema' => $tema, 'sadrzaj' => json_encode($osnova, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }
    if (!$registrirano) {
        $registrirano = true;
        register_shutdown_function('push_isprazni_red');
    }
}

function push_isprazni_red(): void
{
    $red = $GLOBALS['__push_red'] ?? [];
    $GLOBALS['__push_red'] = [];
    if (!$red) {
        return;
    }
    // odgovor pregledniku prvo, slanje poslije
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
    }
    try {
        $vapid = push_vapid();
        $zahtjevi = [];
        foreach ($red as $r) {
            foreach ($r['pretplate'] as $p) {
                try {
                    $zahtjevi[] = ['id' => (int) $p['Id'], 'url' => $p['Endpoint'], 'tijelo' => push_sifriraj($r['sadrzaj'], $p['P256dh'], $p['Auth']),
                        'zaglavlja' => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: high',
                            'Authorization: vapid t=' . push_jwt($p['Endpoint'], $vapid) . ', k=' . $vapid['pub']]
                            + ($r['tema'] !== '' ? [99 => 'Topic: ' . $r['tema']] : [])];
                } catch (Throwable $e) {
                    error_log('Push: ' . $e->getMessage());
                }
            }
        }
        push_http($zahtjevi);
    } catch (Throwable $e) {
        error_log('Push: ' . $e->getMessage());
    }
}

/** Šalje zahtjeve (curl paralelno, inače redom) i čisti istekle pretplate. Vraća [id => HTTP status]. */
function push_http(array $zahtjevi): array
{
    $status = [];
    if (!$zahtjevi) {
        return $status;
    }
    if (function_exists('curl_multi_init')) {
        $mh = curl_multi_init();
        $h = [];
        foreach ($zahtjevi as $i => $z) {
            $c = curl_init($z['url']);
            curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $z['tijelo'], CURLOPT_HTTPHEADER => array_values($z['zaglavlja']),
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8]);
            curl_multi_add_handle($mh, $c);
            $h[$i] = $c;
        }
        do {
            $s = curl_multi_exec($mh, $aktivno);
            if ($aktivno) {
                curl_multi_select($mh, 1.0);
            }
        } while ($aktivno && $s === CURLM_OK);
        foreach ($h as $i => $c) {
            $status[$zahtjevi[$i]['id']] = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($mh, $c);
            curl_close($c);
        }
        curl_multi_close($mh);
    } else {
        foreach ($zahtjevi as $z) {
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $z['zaglavlja']), 'content' => $z['tijelo'], 'timeout' => 6, 'ignore_errors' => true]]);
            @file_get_contents($z['url'], false, $ctx);
            $status[$z['id']] = isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0] . ' ', $m) ? (int) $m[1] : 0;
        }
    }
    foreach ($status as $id => $kod) {
        if ($kod === 404 || $kod === 410) {
            q('DELETE FROM PushPretplate WHERE Id=?', [$id]); // pretplata više ne postoji (odjava, nova instalacija)
        } elseif ($kod >= 200 && $kod < 300) {
            q('UPDATE PushPretplate SET ZadnjeSlanje=?, Greske=0 WHERE Id=?', [sada(), $id]);
        } else {
            q('UPDATE PushPretplate SET Greske=Greske+1 WHERE Id=?', [$id]);
            q('DELETE FROM PushPretplate WHERE Id=? AND Greske>=20', [$id]);
        }
    }
    return $status;
}

/** Probna obavijest odmah (na sve uređaje korisnika) – vraća broj uspješnih / ukupno. */
function push_proba(int $korisnikId): array
{
    $vapid = push_vapid();
    $sadrzaj = json_encode(['t' => udruga_kratko(), 'b' => '✅ Push obavijesti rade na ovom uređaju.', 'u' => apsolutni_url('push'), 'g' => 'proba',
        'i' => apsolutni_url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $z = [];
    foreach (redovi('SELECT * FROM PushPretplate WHERE KorisnikId=?', [$korisnikId]) as $p) {
        $z[] = ['id' => (int) $p['Id'], 'url' => $p['Endpoint'], 'tijelo' => push_sifriraj($sadrzaj, $p['P256dh'], $p['Auth']),
            'zaglavlja' => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 600', 'Urgency: high',
                'Authorization: vapid t=' . push_jwt($p['Endpoint'], $vapid) . ', k=' . $vapid['pub']]];
    }
    $s = push_http($z);
    return [count(array_filter($s, fn($k) => $k >= 200 && $k < 300)), count($s), $s];
}
