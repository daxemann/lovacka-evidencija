<?php
/**
 * Mobilna stanica – upis skeniranjem iskaznica (JSON), samo ovlašteni skeneri te aktivacije.
 *  GET  ?a=A                                  – zadnji upisi skeniranjem
 *  POST ?a=A  JSON {stavke:[{k,q,smjer,vozilo,lat,lon,acc,v,o}]}
 */
$k = korisnik();
$a = dez_aktivacija_po_id((int) ul('a', 0));
$st = $a ? dez_stanica((int) $a['StanicaId']) : null;
if (!$a || !$st || $st['Vrsta'] !== 'M') {
    json(['ok' => false, 'greska' => 'Mobilna stanica ne postoji.']);
}
if (!dez_smije_skenirati($a, $k)) {
    json(['ok' => false, 'zatvoreno' => true, 'greska' => 'Stanica nije aktivna ili više niste ovlašteni za skeniranje.']);
}
session_write_close();
if (je_post()) {
    $tijelo = json_decode((string) file_get_contents('php://input'), true);
    $rez = [];
    foreach (array_slice(is_array($tijelo['stavke'] ?? null) ? $tijelo['stavke'] : [], 0, 300) as $s) {
        if (is_array($s)) {
            $rez[] = ['k' => (string) ($s['k'] ?? '')] + dez_skeniraj_iskaznicu($a, $st, $k, $s);
        }
    }
    json(['ok' => true, 'rezultati' => $rez, 'upisi' => dez_skenirani_json($a)]);
}
json(['ok' => true, 'upisi' => dez_skenirani_json($a)]);
