<?php
/**
 * Odgovor na poziv za skupni lov izravno iz push obavijesti (gumb „Ne dolazim“) – bez otvaranja aplikacije.
 * Javna stranica: vrijedi osobni jednokratni ključ iz obavijesti. Vraća JSON za service worker.
 */
$x = gl_po_tokenu((string) ($_GET['t'] ?? ''));
if (!$x || !$x['lov']) {
    json(['ok' => false, 'greska' => 'Poziv nije pronađen.']);
}
gl_zatvori_istekle();
$lov = gl_lov((int) $x['lov']['Id']);
$o = (int) ($_GET['o'] ?? 0) === 1 ? 1 : 0;
$greska = gl_odgovori($lov, $x['ja'], $o);
if ($greska) {
    json(['ok' => false, 'greska' => $greska]);
}
dnevnik('Skupni lov – odgovor iz obavijesti', 'GrupniLov', (int) $lov['Id'], $x['ja']['Ime'] . ': ' . ($o ? 'dolazi' : 'ne dolazi'), ['Id' => $x['ja']['KorisnikId'], 'KorisnickoIme' => $x['ja']['Ime']]);
json(['ok' => true, 'naslov' => $o ? '✔ Dolazite – ' . $lov['Naziv'] : '✖ Ne dolazite – ' . $lov['Naziv'],
    'tekst' => $o ? 'Otvorite aplikaciju da se vaš položaj vidi na karti.' : 'Organizator vidi vaš odgovor. Predomislili ste se? Otvorite aplikaciju → Skupni lov.']);
