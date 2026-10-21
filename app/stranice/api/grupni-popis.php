<?php
/**
 * Skupni lov – popis sudionika (JSON), samo organizator / lovnik s pravom za lov.
 *  GET  ?id=L[&imenik=1]                      – popis (i članovi sekcija lova za skeniranje bez signala)
 *  POST ?id=L  JSON {stavke:[{k,q,v}]}        – skenirane iskaznice (k = id na mobitelu, q = sadržaj QR-a, v = vrijeme ms)
 *  POST ?id=L  radnja=rucno (clan | gost)     – ručni upis
 *  POST ?id=L  radnja=ukloni (pid)            – uklanjanje s popisa
 */
$lov = gl_lov((int) ul('id', 0));
if (!$lov) {
    json(['ok' => false, 'greska' => 'Skupni lov ne postoji.']);
}
if (!gl_moze_upravljati($lov)) {
    json(['ok' => false, 'greska' => 'Popis sudionika vodi organizator lova (pravo „Skupni lov – organizacija“).']);
}
session_write_close();

if (je_post()) {
    $tijelo = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') ? json_decode((string) file_get_contents('php://input'), true) : null;
    if (is_array($tijelo) && isset($tijelo['stavke']) && is_array($tijelo['stavke'])) {
        $rez = [];
        foreach (array_slice($tijelo['stavke'], 0, 300) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $r = gl_popis_skeniraj($lov, (string) ($s['q'] ?? ''), isset($s['v']) && is_numeric($s['v']) ? (int) $s['v'] : null, isset($s['k']) ? (string) $s['k'] : null);
            $rez[] = ['k' => (string) ($s['k'] ?? '')] + $r;
        }
        json(['ok' => true, 'rezultati' => $rez, 'popis' => gl_popis_json($lov)]);
    }
    $radnja = (string) ($_POST['radnja'] ?? '');
    $greska = null;
    if ($radnja === 'rucno') {
        $greska = gl_popis_rucno($lov, ($_POST['clan'] ?? '') !== '' ? (int) $_POST['clan'] : null, (string) ($_POST['gost'] ?? ''));
    } elseif ($radnja === 'ukloni') {
        gl_popis_ukloni($lov, (int) ($_POST['pid'] ?? 0));
    } else {
        $greska = 'Nepoznata radnja.';
    }
    json(['ok' => $greska === null, 'greska' => $greska, 'popis' => gl_popis_json($lov)]);
}

$out = ['ok' => true, 'popis' => gl_popis_json($lov)];
if (!empty($_GET['imenik'])) {
    $out['imenik'] = gl_popis_imenik($lov);
}
json($out);
