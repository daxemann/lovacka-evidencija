<?php
/**
 * 🏷️ Popusti u trgovinama – otvara zajedničke Popuste na poslužitelju Oglasnika lovaca
 * (jednokratni potpisani token s iskaznicom člana; trgovine vide lovci svih udruga).
 */
if (!pop_dostupno()) {
    stranica('Popusti u trgovinama', '<h1 class="h3">🏷️ Popusti u trgovinama</h1><div class="alert alert-secondary">'
        . 'Popusti rade preko zajedničkog <b>Oglasnika lovaca</b> – udruga mora biti registrirana i odobrena.'
        . (ima(P_SUSTAV) ? ' Registracija: <a href="' . e(url('sustav/oglasnik')) . '">Sustav → Oglasnik lovaca</a> (sam Oglasnik ne mora biti uključen).' : ' Javite se administratoru udruge.')
        . '</div>');
}
$k = korisnik();
$clan = $k['ClanId'] ? red('SELECT Email, MobilniTelefon FROM Clanovi WHERE Id=?', [$k['ClanId']]) : null;
$sada = time();
$podaci = [
    'v' => 1,
    'iss' => ogl_kod(),
    'aud' => 'oglasnik',
    'iat' => $sada,
    'exp' => $sada + 120,
    'jti' => bin2hex(random_bytes(16)),
    'sub' => (string) $k['Id'],
    'name' => $k['Naziv'],
    'email' => $clan['Email'] ?? null,
    'phone' => $clan['MobilniTelefon'] ?? null,
    'role' => ima(P_SUSTAV) ? 'admin' : (ima(P_OGLASNIK_MOD) ? 'moderator' : 'member'),
    'club' => ['name' => udruga_naziv(), 'url' => ogl_baza(), 'contact' => ogl_kontakt()],
    'policy' => ogl_politika(),
    'perms' => ['popusti' => pop_smije_uredjivati()],
    'scope' => 'popusti',
];
if ($iskaznica = pop_iskaznica($k)) {
    $podaci['card'] = $iskaznica;
}
$token = ogl_potpisi($podaci);
if (!$token) {
    stranica('Popusti u trgovinama', '<div class="alert alert-danger mt-3">Ključ za Oglasnik nije moguće pročitati. Administrator treba u <b>Sustav → Oglasnik lovaca</b> napraviti novi ključ.</div>');
}
$povratak = ogl_povratak('popusti', ['retry' => isset($_GET['retry']) ? '1' : null]);
pop_otvori(ogl_adresa() . '/sso?' . http_build_query(['from' => $povratak]), ['token' => $token[0] . '.' . $token[1], 'r' => '/popusti']);
