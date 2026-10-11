<?php
/** Šalje Oglasniku zahtjev za registraciju udruge (javni ključ + naziv + kontakt), potpisan privatnim ključem. */
trazi(P_SUSTAV);
if (je_demo() || !ogl_podrzano() || !ogl_javni_kljuc()) {
    preusmjeri('sustav/oglasnik');
}
$sada = time();
$zahtjev = ogl_potpisi([
    'v' => 1,
    'aud' => 'oglasnik-reg',
    'iat' => $sada,
    'exp' => $sada + 300,
    'pub' => ogl_javni_kljuc(),
    'name' => udruga_naziv(),
    'contact' => ogl_kontakt(),
    'app_url' => ogl_baza() . 'index.php?p=sustav/oglasnik',
    'rotate' => ogl_zamjena(),
]);
if (!$zahtjev) {
    poruka('Ključ nije moguće pročitati – napravite novi ključ.', 'danger');
    preusmjeri('sustav/oglasnik');
}
spremi_postavku('Oglasnik.Zahtjev', sada());
dnevnik('Oglasnik – zahtjev za registraciju poslan', null, null, ogl_kod());
$povratak = ogl_povratak('oglasnik-registracija', ['retry' => isset($_GET['retry']) ? '1' : null]);
ogl_salji_obrazac(ogl_adresa() . '/registracija?' . http_build_query(['from' => $povratak]), ['payload' => $zahtjev[0], 'sig' => $zahtjev[1]], 'Šaljem zahtjev Oglasniku…');
