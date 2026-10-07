<?php
/**
 * Lovačka evidencija – instalacija jednom datotekom.
 * Učitajte SAMO ovu datoteku na webhosting (npr. u public_html/) i otvorite je u pregledniku:
 *   https://vasa-domena.hr/instaliraj.php
 * Preuzima zadnju verziju s GitHuba, raspakira je u mapu "evidencija" i sama se briše.
 */
declare(strict_types=1);
@set_time_limit(300);
$izvor = 'https://codeload.github.com/daxemann/lovacka-evidencija/zip/refs/heads/main';
$cilj = __DIR__ . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $_POST['mapa'] ?? 'evidencija');
$poruke = [];
$ok = false;

function preuzmi(string $url): string
{
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'lovacka-evidencija-instalacija']);
        $d = curl_exec($c);
        $kod = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $g = curl_error($c);
        curl_close($c);
        if ($d === false || $kod !== 200) {
            throw new RuntimeException("Preuzimanje nije uspjelo (HTTP $kod $g).");
        }
        return $d;
    }
    $d = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'lovacka-evidencija-instalacija']]));
    if ($d === false) {
        throw new RuntimeException('Preuzimanje nije uspjelo (poslužitelj ne dopušta vanjske veze).');
    }
    return $d;
}

$provjere = [
    'PHP 8.1 ili noviji (' . PHP_VERSION . ')' => PHP_VERSION_ID >= 80100,
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'zip (ZipArchive)' => class_exists('ZipArchive'),
    'dom (za PDF)' => extension_loaded('dom'),
    'gd (smanjivanje slika, preporučeno)' => extension_loaded('gd'),
    'mapa zapisiva' => is_writable(__DIR__),
];
$sveOk = !in_array(false, array_slice($provjere, 0, 6, true), true) && $provjere['mapa zapisiva'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $sveOk) {
    try {
        if (is_dir($cilj) && glob($cilj . '/*')) {
            throw new RuntimeException('Mapa ' . basename($cilj) . ' već postoji i nije prazna.');
        }
        $zipPut = tempnam(sys_get_temp_dir(), 'ev') ?: __DIR__ . '/ev-tmp.zip';
        file_put_contents($zipPut, preuzmi($izvor));
        $z = new ZipArchive();
        if ($z->open($zipPut) !== true) {
            throw new RuntimeException('Preuzeta datoteka nije ispravan ZIP.');
        }
        $prefiks = '';
        $prvi = (string) $z->getNameIndex(0);
        if (preg_match('#^([^/]+/)#', $prvi, $m)) {
            $prefiks = $m[1];
        }
        @mkdir($cilj, 0755, true);
        $n = 0;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $ime = (string) $z->getNameIndex($i);
            if ($prefiks && !str_starts_with($ime, $prefiks)) {
                continue;
            }
            $rel = substr($ime, strlen($prefiks));
            if ($rel === '' || $rel === 'instaliraj.php' || str_contains($rel, '..') || str_starts_with($rel, 'docs/')) {
                continue;
            }
            $put = $cilj . '/' . $rel;
            if (str_ends_with($ime, '/')) {
                @mkdir($put, 0755, true);
                continue;
            }
            @mkdir(dirname($put), 0755, true);
            file_put_contents($put, $z->getFromIndex($i));
            $n++;
        }
        $z->close();
        @unlink($zipPut);
        $ok = true;
        $poruke[] = "Raspakirano $n datoteka u mapu „" . basename($cilj) . '“.';
        @unlink(__FILE__);
    } catch (Throwable $e) {
        $poruke[] = 'Greška: ' . $e->getMessage();
    }
}
$adresa = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/' . basename($cilj) . '/';
?><!doctype html>
<html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalacija – Lovačka evidencija</title>
<style>body{font-family:system-ui,Arial,sans-serif;max-width:620px;margin:2rem auto;padding:0 1rem;color:#222}h1{color:#2f5d23}
li{margin:.2rem 0}.da{color:#2f7d32}.ne{color:#c62828}button{background:#3d6b2f;color:#fff;border:0;padding:.7rem 1.4rem;border-radius:6px;font-size:1rem;cursor:pointer}
.okvir{background:#f4f6f2;border-radius:8px;padding:1rem;margin:1rem 0}a.gumb{display:inline-block;background:#3d6b2f;color:#fff;padding:.7rem 1.4rem;border-radius:6px;text-decoration:none}</style></head>
<body>
<h1>Lovačka evidencija – instalacija</h1>
<?php foreach ($poruke as $p): ?><div class="okvir"><?= htmlspecialchars($p) ?></div><?php endforeach; ?>
<?php if ($ok): ?>
    <p>Gotovo! Ova instalacijska datoteka je obrisana.</p>
    <p><a class="gumb" href="<?= htmlspecialchars($adresa) ?>">Otvori evidenciju → prvo pokretanje</a></p>
<?php else: ?>
    <h2>Provjera poslužitelja</h2>
    <ul><?php foreach ($provjere as $n => $v): ?><li class="<?= $v ? 'da' : 'ne' ?>"><?= $v ? '✔' : '✘' ?> <?= htmlspecialchars($n) ?></li><?php endforeach; ?></ul>
    <?php if ($sveOk): ?>
        <form method="post">
            <p>Aplikacija će se preuzeti s GitHuba i raspakirati u mapu:
                <input name="mapa" value="evidencija" style="padding:.3rem;width:10rem"></p>
            <button>Instaliraj</button>
        </form>
    <?php else: ?>
        <p class="ne">Poslužitelj ne ispunjava zahtjeve (✘). Uključite proširenja u postavkama PHP-a ili pitajte podršku hostinga.</p>
    <?php endif; ?>
<?php endif; ?>
</body></html>
