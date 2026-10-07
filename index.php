<?php
/**
 * Lovačka evidencija – ulazna točka.
 * Sve stranice idu preko index.php?p=<stranica>.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Potreban je PHP 8.1 ili noviji (trenutno ' . PHP_VERSION . ').');
}
foreach (['pdo_sqlite', 'mbstring', 'openssl'] as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        exit("Na poslužitelju nedostaje PHP proširenje: $ext");
    }
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/osnova.php';

date_default_timezone_set((string) cfg('vremenska_zona', 'Europe/Zagreb'));
mb_internal_encoding('UTF-8');
if (!cfg('prikazi_greske', false)) {
    ini_set('display_errors', '0');
}
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    @file_put_contents(podaci('greske.log'), date('c') . ' ' . $e . "\n\n", FILE_APPEND);
    http_response_code(500);
    $detalji = cfg('prikazi_greske', false) ? '<pre class="small">' . e((string) $e) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;max-width:640px;margin:3rem auto">'
        . '<h3>Došlo je do greške.</h3><p>Pokušajte ponovno. Ako se ponavlja, administrator može pogledati <code>podaci/greske.log</code>.</p>'
        . '<p><a href="' . e(url()) . '">Početna</a></p>' . $detalji . '</div>';
});

pokreni_sesiju();
provjeri_csrf();

$p = (string) ($_GET['p'] ?? '');
if ($p === '' || !preg_match('#^[a-z0-9\-]+(/[a-z0-9\-]+)*$#', $p)) {
    $p = 'pocetna';
}

$javne = ['prijava', 'odjava', 'postavljanje', 'pozivnica', 'zaboravljena-lozinka', 'nova-lozinka', 'logo', 'vrati', 'manifest'];
$nemaKorisnika = !vrijednost('SELECT 1 FROM Korisnici LIMIT 1');
if ($nemaKorisnika && !in_array($p, ['postavljanje', 'vrati', 'logo', 'manifest'], true)) {
    preusmjeri('postavljanje');
}

if (!in_array($p, $javne, true)) {
    $k = korisnik();
    if (!$k) {
        if (str_starts_with($p, 'api/')) {
            http_response_code(401);
            json(['greska' => 'prijava']);
        }
        $povratak = $_SERVER['REQUEST_URI'] ?? '';
        preusmjeri('prijava', ['povratak' => $povratak]);
    }
    if ($k['MoraPromijenitiLozinku'] && !in_array($p, ['promjena-lozinke', 'odjava'], true)) {
        preusmjeri('promjena-lozinke');
    }
    dnevna_kopija();
}

$dat = __DIR__ . '/app/stranice/' . $p . '.php';
if (!is_file($dat)) {
    nije_pronadjeno('Stranica');
}
require $dat;
