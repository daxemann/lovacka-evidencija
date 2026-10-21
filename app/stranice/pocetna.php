<?php
/**
 * Početna: blokovi po ulozi (Sustav → Početna). Bez uloge = „Član“, s ulogama = unija blokova njegovih uloga.
 * Administrator može pregledati početnu kao bilo koja uloga: ?pregled=<UlogaId|clan> (stari ?pogled=clan i dalje radi).
 */
$k = korisnik();
$pregled = null;
if (ima(P_SUSTAV)) {
    $pregled = ($_GET['pogled'] ?? '') === 'clan' ? 'clan' : ((string) ($_GET['pregled'] ?? '') ?: null);
}
$blokovi = pocetna_blokovi($k, $pregled);
ob_start();
if ($pregled !== null) {
    $naziv = $pregled === 'clan' ? 'Član (bez uloge)' : (string) vrijednost('SELECT Naziv FROM Uloge WHERE Id=?', [(int) $pregled]);
    echo '<div class="alert alert-info d-flex flex-wrap align-items-center gap-2 py-2 no-print"><span class="me-auto">👁 Pregled početne kao: <b>' . e($naziv) . '</b> (prava i podaci su vaši)</span>'
        . '<a class="btn btn-sm btn-outline-primary" href="' . e(url('sustav/pocetna')) . '">← Postavke početne</a><a class="btn btn-sm btn-outline-secondary" href="' . e(url('pocetna')) . '">Moja početna</a></div>';
}
if ($pregled === null) {
    echo php2shell_obavijest();
}
echo pocetna_html($k, $blokovi);
if (in_array('danas_popis', $blokovi, true)) {
    echo '<script>setInterval(function () { if (document.visibilityState === "visible" && (!document.activeElement || document.activeElement === document.body)) location.reload(); }, 60000);</script>';
}
stranica('Početna', ob_get_clean());
