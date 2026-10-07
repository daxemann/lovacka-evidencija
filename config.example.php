<?php
/*
 * Lovačka evidencija – konfiguracija (nije obavezna).
 * Kopirajte ovu datoteku u config.php ako želite nešto promijeniti.
 * Bez config.php sve radi sa zadanim vrijednostima.
 */
return [
    // Mapa s bazom, fotografijama i kopijama. Najsigurnije IZVAN javne mape weba,
    // npr. '/home/korisnik/evidencija-podaci'. Zadano: mapa "podaci" pored index.php
    // (zaštićena datotekom .htaccess).
    'podaci' => __DIR__ . '/podaci',

    // Javna adresa aplikacije (za poveznice u pozivnicama i e-pošti).
    // Može se postaviti i u aplikaciji: Sustav → E-pošta i adresa.
    'javna_adresa' => '',

    'vremenska_zona' => 'Europe/Zagreb',

    // Samo za traženje grešaka – na stvarnom poslužitelju ostavite false.
    'prikazi_greske' => false,
];
