<?php
/** Grupni lov – JSON za kartu: stanje, položaj, zaslon ugašen, SOS, odlazim; organizator: SOS preuzet. */
$k = korisnik();
gl_zatvori_istekle();
$lov = gl_lov((int) ul('id', 0));
if (!$lov) {
    json(['ok' => false, 'greska' => 'Grupni lov ne postoji.']);
}
$org = gl_moze_upravljati($lov);
$ja = gl_sudionik((int) $lov['Id'], (int) $k['Id']);
if (!$org && !$ja) {
    json(['ok' => false, 'greska' => 'Niste pozvani u ovaj grupni lov.']);
}
session_write_close(); // dugotrajno praćenje – sesija se ne zaključava
// lovac koji ne dolazi / nije odgovorio / otišao ne vidi kartu ni položaje drugih – smije samo odgovoriti
$uLovu = $org || ($ja && (int) $ja['Odgovor'] === 1 && $ja['Otisao'] === null);
if (!$uLovu && !in_array((string) ($_POST['radnja'] ?? ''), ['dolazim', 'ne-dolazim'], true)) {
    json(['ok' => false, 'greska' => gl_aktivan($lov) ? 'Niste u ovom lovu.' : 'Grupni lov je završen.', 'stanje' => null]);
}
$u_br = fn(string $kljuc) => isset($_POST[$kljuc]) && is_numeric($_POST[$kljuc]) ? (float) $_POST[$kljuc] : null;

$greska = null;
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if (!gl_aktivan($lov) && $radnja !== '') {
        json(['ok' => false, 'greska' => 'Grupni lov je završen.', 'stanje' => gl_stanje($lov)]);
    }
    switch ($radnja) {
        case 'polozaj':
            if ($ja && (int) $ja['Odgovor'] === 1 && $ja['Otisao'] === null) {
                gl_polozaj($lov, $ja, $u_br('lat'), $u_br('lon'), $u_br('toc'));
            }
            break;
        case 'skriveno':
            if ($ja) {
                gl_skriveno($lov, $ja);
            }
            json(['ok' => true]); // sendBeacon – odgovor se ne čita
        case 'sos':
            $greska = $ja ? gl_sos($lov, $ja, $u_br('lat'), $u_br('lon')) : 'Niste sudionik lova.';
            break;
        case 'sos-otkazi':
            if ($ja) {
                gl_sos_otkazi($lov, $ja);
            }
            break;
        case 'sos-preuzmi':
            $greska = gl_u_lovu($lov) ? gl_sos_preuzmi($lov, (int) ($_POST['k'] ?? 0)) : 'Nemate pravo.';
            break;
        case 'odlazim':
            if ($ja) {
                gl_odlazim($lov, $ja);
            }
            break;
        case 'dolazim':
        case 'ne-dolazim':
            $greska = $ja ? gl_odgovori($lov, $ja, $radnja === 'dolazim' ? 1 : 0) : 'Niste pozvani.';
            if (!$org) {
                json(['ok' => $greska === null, 'greska' => $greska]);
            }
            break;
        default:
            $greska = 'Nepoznata radnja.';
    }
}
if (gl_aktivan($lov)) {
    gl_provjeri_signal($lov);
}
json(['ok' => $greska === null, 'greska' => $greska, 'stanje' => gl_stanje(gl_lov((int) $lov['Id']))]);
