<?php
/** Prijava u zajednički Oglasnik lovaca jednokratnim potpisanim tokenom. */
if (!ogl_aktivan()) {
    preusmjeri('oglasnik'); // vanjski Oglasnik nije uključen → interni oglasnik udruge
}
$k = korisnik();
$clan = $k['ClanId'] ? red('SELECT Email, MobilniTelefon FROM Clanovi WHERE Id=?', [$k['ClanId']]) : null;
$uloga = ima(P_SUSTAV) ? 'admin' : (ima(P_OGLASNIK_MOD) ? 'moderator' : 'member');
$cilj = (string) ($_GET['r'] ?? '/');
if (!preg_match('#^/[a-z0-9/_-]*$#i', $cilj)) {
    $cilj = '/';
}
$sada = time();
$token = ogl_potpisi([
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
    'role' => $uloga,
    'club' => ['name' => udruga_naziv(), 'url' => trenutna_adresa(), 'contact' => ogl_kontakt()],
    'policy' => ogl_politika(),
]);
if (!$token) {
    stranica('Oglasnik', '<div class="alert alert-danger mt-3">Ključ za Oglasnik nije moguće pročitati. Administrator treba u <b>Sustav → Oglasnik</b> napraviti novi ključ.</div>');
}
$povratak = ogl_povratak('oglasnik-sso', ['r' => $cilj !== '/' ? $cilj : null, 'retry' => isset($_GET['retry']) ? '1' : null]);
ogl_salji_obrazac(ogl_adresa() . '/sso?' . http_build_query(['from' => $povratak]), ['token' => $token[0] . '.' . $token[1], 'r' => $cilj], 'Otvaram Oglasnik…');
