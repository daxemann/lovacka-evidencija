<?php
/**
 * Ažuriranje programa s GitHuba: provjera novog izdanja (Release), sigurnosna kopija, preuzimanje i zamjena programskih datoteka.
 * Podaci (mapa podaci/, config.php) se nikad ne diraju; baza se sama proširuje pri prvom zahtjevu nove verzije.
 * U Home Assistant add-onu program se ažurira preko Supervisora (cijeli add-on).
 */
declare(strict_types=1);

const AZURIRANJE_REPO = 'daxemann/lovacka-evidencija';
const AZURIRANJE_PROVJERA_SATI = 6;

function azuriranje_repo(): string
{
    $r = (string) cfg('azuriranje_repo', AZURIRANJE_REPO);
    return preg_match('#^[\w.-]+/[\w.-]+$#', $r) ? $r : AZURIRANJE_REPO;
}
function azuriranje_ukljuceno(): bool
{
    return cfg('azuriranje', true) !== false;
}
/** Smije ažurirati: sustav + sve sekcije (glavni admin). */
function smije_azurirati(): bool
{
    $k = korisnik();
    return $k && ima(P_SUSTAV) && $k['SveSekcije'];
}
/** Radi li program kao Home Assistant add-on (s pristupom Supervisoru)? */
function azuriranje_addon(): bool
{
    return is_file('/data/options.json') && realpath(KORIJEN) === '/app';
}

/** HTTP GET s User-Agentom (GitHub ga traži). */
function azuriranje_http(string $url, int $timeout = 8): ?string
{
    $ua = 'LovackaEvidencija/' . VERZIJA;
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json']]);
        $d = curl_exec($c);
        $kod = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return $kod === 200 && is_string($d) ? $d : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'user_agent' => $ua, 'header' => "Accept: application/vnd.github+json\r\n", 'follow_location' => 1]]);
    $d = @file_get_contents($url, false, $ctx);
    return $d === false ? null : $d;
}

/**
 * Zadnje izdanje s GitHuba (spremljeno u podaci/azuriranje.json, provjera najviše svakih nekoliko sati).
 * ['verzija','tag','naziv','biljeske','url','zip','objavljeno','provjereno'] ili null.
 */
function azuriranje_izdanje(bool $odmah = false): ?array
{
    if (!azuriranje_ukljuceno()) {
        return null;
    }
    $dat = podaci('azuriranje.json');
    $c = is_file($dat) ? json_decode((string) file_get_contents($dat), true) : null;
    $svjeze = is_array($c) && ($c['repo'] ?? '') === azuriranje_repo()
        && time() - (int) ($c['provjereno'] ?? 0) < ($c['greska'] ?? false ? 3600 : AZURIRANJE_PROVJERA_SATI * 3600);
    if ($svjeze && !$odmah) {
        return $c['izdanje'] ?? null;
    }
    $izd = null;
    $d = azuriranje_http('https://api.github.com/repos/' . azuriranje_repo() . '/releases/latest', $odmah ? 15 : 4);
    $j = $d ? json_decode($d, true) : null;
    if (is_array($j) && !empty($j['tag_name']) && empty($j['draft']) && empty($j['prerelease'])) {
        $izd = [
            'verzija' => ltrim((string) $j['tag_name'], 'vV'),
            'tag' => (string) $j['tag_name'],
            'naziv' => (string) ($j['name'] ?? $j['tag_name']),
            'biljeske' => mb_substr((string) ($j['body'] ?? ''), 0, 20000),
            'url' => (string) ($j['html_url'] ?? ''),
            'zip' => (string) ($j['zipball_url'] ?? ''),
            'objavljeno' => (string) ($j['published_at'] ?? ''),
        ];
    }
    $novo = ['repo' => azuriranje_repo(), 'provjereno' => time(), 'greska' => $izd === null, 'izdanje' => $izd ?? ($c['izdanje'] ?? null)];
    @file_put_contents($dat, json_encode($novo, JSON_UNESCAPED_UNICODE));
    return $novo['izdanje'];
}

/** Novo izdanje ako je novije od instalirane verzije, inače null. Samo za glavnog admina. */
function azuriranje_dostupno(): ?array
{
    if (!smije_azurirati()) {
        return null;
    }
    try {
        $i = azuriranje_izdanje();
    } catch (Throwable) {
        return null;
    }
    return $i && version_compare($i['verzija'], VERZIJA, '>') ? $i : null;
}

/** Bilješke izdanja (Markdown s GitHuba) → jednostavan HTML. */
function azuriranje_biljeske_html(string $md): string
{
    $out = '';
    $lista = false;
    foreach (preg_split('/\R/', $md) as $red) {
        $t = e($red);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $t);
        $t = preg_replace('/`(.+?)`/', '<code>$1</code>', $t);
        if (preg_match('/^\s*[-*] (.*)$/', $t, $m)) {
            $out .= ($lista ? '' : '<ul class="mb-2">') . '<li>' . $m[1] . '</li>';
            $lista = true;
            continue;
        }
        if ($lista && preg_match('/^\s{2,}\S/', $red)) { // nastavak stavke
            $out = preg_replace('#</li>$#', ' ' . trim($t) . '</li>', $out);
            continue;
        }
        if ($lista) {
            $out .= '</ul>';
            $lista = false;
        }
        if (preg_match('/^#{1,6} (.*)$/', $t, $m)) {
            $out .= '<h3 class="h6 mt-3 mb-1">' . $m[1] . '</h3>';
        } elseif (trim($t) === '---') {
            $out .= '<hr class="my-2">';
        } elseif (trim($t) !== '') {
            $out .= '<p class="mb-2">' . $t . '</p>';
        }
    }
    return $out . ($lista ? '</ul>' : '');
}

/** Putanje koje se pri ažuriranju nikad ne prepisuju. */
function azuriranje_preskoci(string $rel): bool
{
    return $rel === 'config.php' || str_starts_with($rel, 'podaci/') || str_starts_with($rel, '.git/') || str_starts_with($rel, '.github/');
}

/** Kopija trenutnih programskih datoteka (bez podataka) – za vraćanje ako ažuriranje ne uspije. */
function azuriranje_kopija_programa(): string
{
    $zip = new ZipArchive();
    $put = podaci('kopije/program-' . VERZIJA . '-' . date('Y-m-d_His') . '.zip');
    if ($zip->open($put, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Ne mogu napraviti kopiju programa.');
    }
    $kor = realpath(KORIJEN);
    $podaciPut = realpath(podaci());
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kor, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = $f->getPathname();
        if ($podaciPut && str_starts_with($p, $podaciPut . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($p, strlen($kor) + 1));
        if ($f->isFile() && !azuriranje_preskoci($rel)) {
            $zip->addFile($p, $rel);
        }
    }
    $zip->close();
    return $put;
}

/** Raspakira ZIP (korijen $prefiks) preko programske mape. */
function azuriranje_raspakiraj(ZipArchive $zip, string $prefiks): int
{
    $kor = rtrim(str_replace('\\', '/', (string) realpath(KORIJEN)), '/');
    $n = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $ime = (string) $zip->getNameIndex($i);
        if (!str_starts_with($ime, $prefiks) || str_ends_with($ime, '/')) {
            continue;
        }
        $rel = substr($ime, strlen($prefiks));
        if ($rel === '' || str_contains($rel, '..') || azuriranje_preskoci($rel)) {
            continue;
        }
        $cilj = $kor . '/' . $rel;
        if (!is_dir(dirname($cilj)) && !@mkdir(dirname($cilj), 0775, true)) {
            throw new RuntimeException('Ne mogu napraviti mapu ' . dirname($rel));
        }
        $sadrzaj = $zip->getFromIndex($i);
        if ($sadrzaj === false) {
            throw new RuntimeException('Oštećen ZIP: ' . $rel);
        }
        $tmp = $cilj . '.novo-' . getmypid();
        if (@file_put_contents($tmp, $sadrzaj) === false || !@rename($tmp, $cilj)) {
            @unlink($tmp);
            throw new RuntimeException('Ne mogu zapisati ' . $rel . ' (prava pristupa?)');
        }
        $n++;
    }
    return $n;
}

/** Provjera prije ažuriranja: što nedostaje (prazno = sve u redu). */
function azuriranje_preduvjeti(): array
{
    $g = [];
    if (!class_exists('ZipArchive')) {
        $g[] = 'PHP proširenje zip (ZipArchive) nije instalirano.';
    }
    if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
        $g[] = 'Ni cURL ni allow_url_fopen nisu dostupni – program ne može preuzeti novu verziju.';
    }
    foreach (['', 'app', 'app/lib', 'app/stranice', 'assets'] as $m) {
        $p = KORIJEN . '/' . $m;
        if (is_dir($p) && !is_writable($p)) {
            $g[] = 'Web poslužitelj ne smije pisati u mapu „' . ($m ?: '/') . '“.';
            break;
        }
    }
    return $g;
}

/**
 * Ažuriranje na izdanje: 1) kopija baze, 2) kopija programa, 3) preuzimanje i provjera ZIP-a, 4) zamjena datoteka.
 * Ako zamjena ne uspije, vraća se stari program. Vraća poruku o uspjehu.
 */
function azuriraj_program(array $izd): string
{
    @set_time_limit(300);
    @ignore_user_abort(true);
    if ($g = azuriranje_preduvjeti()) {
        throw new RuntimeException(implode(' ', $g));
    }
    if (!preg_match('#^https://(api\.github\.com|codeload\.github\.com|github\.com)/#', $izd['zip'])) {
        throw new RuntimeException('Neispravna adresa preuzimanja.');
    }
    $kopijaBaze = napravi_kopiju('prije-' . str_replace('.', '-', VERZIJA));
    $kopijaProg = azuriranje_kopija_programa();

    $d = azuriranje_http($izd['zip'], 180);
    if ($d === null || strlen($d) < 1000) {
        throw new RuntimeException('Preuzimanje s GitHuba nije uspjelo. Ništa nije promijenjeno.');
    }
    $tmp = podaci('azuriranje-' . bin2hex(random_bytes(4)) . '.zip');
    file_put_contents($tmp, $d);
    unset($d);
    $zip = new ZipArchive();
    try {
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('Preuzeta datoteka nije ispravan ZIP. Ništa nije promijenjeno.');
        }
        $prvi = (string) $zip->getNameIndex(0);
        $prefiks = str_contains($prvi, '/') ? substr($prvi, 0, strpos($prvi, '/') + 1) : '';
        $osnova = $zip->getFromName($prefiks . 'app/osnova.php');
        if ($zip->locateName($prefiks . 'index.php') === false || !is_string($osnova)
            || !preg_match("/const VERZIJA = '([^']+)';/", $osnova, $m)) {
            throw new RuntimeException('ZIP ne sadrži Lovačku evidenciju. Ništa nije promijenjeno.');
        }
        if ($m[1] !== $izd['verzija']) {
            throw new RuntimeException('Verzija u ZIP-u (' . $m[1] . ') ne odgovara izdanju ' . $izd['verzija'] . '. Ništa nije promijenjeno.');
        }
        try {
            $n = azuriranje_raspakiraj($zip, $prefiks);
        } catch (Throwable $e) {
            // vrati stari program
            $stari = new ZipArchive();
            if ($stari->open($kopijaProg) === true) {
                try {
                    azuriranje_raspakiraj($stari, '');
                } catch (Throwable) {
                }
                $stari->close();
            }
            throw new RuntimeException('Ažuriranje prekinuto, vraćen je stari program: ' . $e->getMessage());
        }
    } finally {
        $zip->close();
        @unlink($tmp);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    // stare kopije programa: čuvaju se zadnje 3
    $sve = glob(podaci('kopije/program-*.zip')) ?: [];
    usort($sve, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($sve, 3) as $s) {
        @unlink($s);
    }
    @unlink(podaci('azuriranje.json'));
    dnevnik('Ažuriranje programa', null, null, VERZIJA . ' → ' . $izd['verzija'] . " ($n datoteka, kopija baze: $kopijaBaze)");
    return "Program je ažuriran na verziju {$izd['verzija']} ($n datoteka). Prije ažuriranja napravljena je kopija baze ($kopijaBaze) i kopija programa.";
}

// ---------- Home Assistant add-on: ažuriranje preko Supervisora ----------
function supervisor_api(string $metoda, string $put, ?array $tijelo = null, int $timeout = 10): ?array
{
    $token = getenv('SUPERVISOR_TOKEN') ?: (string) @file_get_contents('/run/evidencija/supervisor_token');
    if ($token === '' || !function_exists('curl_init')) {
        return null;
    }
    $c = curl_init('http://supervisor' . $put);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metoda, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($token), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $tijelo !== null ? json_encode($tijelo) : null]);
    $d = curl_exec($c);
    curl_close($c);
    $j = is_string($d) ? json_decode($d, true) : null;
    return is_array($j) ? $j : null;
}
function addon_supervisor_dostupan(): bool
{
    return azuriranje_addon() && (supervisor_api('GET', '/addons/self/info')['result'] ?? '') === 'ok';
}
/** Kopija baze, osvježi trgovinu, pokreni ažuriranje add-ona (add-on se pritom ponovno pokreće). */
function azuriraj_addon(array $izd): string
{
    $info = supervisor_api('GET', '/addons/self/info');
    $slug = (string) ($info['data']['slug'] ?? '');
    if ($slug === '') {
        throw new RuntimeException('Nema pristupa Home Assistant Supervisoru.');
    }
    $kopijaBaze = napravi_kopiju('prije-' . str_replace('.', '-', VERZIJA));
    supervisor_api('POST', '/store/reload', null, 60);
    $st = supervisor_api('GET', '/store/addons/' . $slug);
    $nova = (string) ($st['data']['version_latest'] ?? '');
    if ($nova === '' || !version_compare($nova, VERZIJA, '>')) {
        throw new RuntimeException('Home Assistant još ne nudi novu verziju add-ona (' . ($nova ?: '?') . '). Pokušajte za nekoliko minuta.');
    }
    dnevnik('Ažuriranje programa (add-on)', null, null, VERZIJA . ' → ' . $nova . " (kopija baze: $kopijaBaze)");
    // add-on se gasi tijekom ažuriranja – odgovor se ne čeka
    supervisor_api('POST', '/store/addons/' . $slug . '/update', ['backup' => false], 3);
    return "Ažuriranje na {$nova} je pokrenuto. Kopija baze: $kopijaBaze. Program se ponovno pokreće – osvježite stranicu za 1–3 minute.";
}
