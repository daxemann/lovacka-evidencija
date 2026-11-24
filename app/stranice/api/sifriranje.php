<?php
/** Traka napretka: nastavlja (de)šifriranje u obrocima i vraća stanje. Samo pravo „Sustav“, POST s CSRF zaglavljem. */
trazi(P_SUSTAV);
if (!je_post()) {
    http_response_code(405);
    json(['greska' => 'POST']);
}
session_write_close(); // ostale stranice ne čekaju na ovaj zahtjev
try {
    json(datoteke_u_tijeku() ? datoteke_nastavi() : sifriranje_nastavi()); // najprije datoteke, zatim baza
} catch (Throwable $e) {
    error_log('Šifriranje – obrok nije uspio: ' . $e->getMessage());
    http_response_code(500);
    json(['greska' => 'Obrok nije uspio – pokušajte ponovno. Podaci su i dalje čitljivi.']);
}
