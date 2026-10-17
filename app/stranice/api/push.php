<?php
/** Push obavijesti: javni ključ, pretplata / odjava uređaja, probna obavijest. */
$k = korisnik();
if (!push_podrzan()) {
    json(['ok' => false, 'greska' => 'Poslužitelj ne podržava push obavijesti (OpenSSL bez P-256).']);
}
if (!je_post()) {
    session_write_close();
    json(['ok' => true, 'kljuc' => push_vapid()['pub']]);
}
$radnja = (string) ($_POST['radnja'] ?? '');
$endpoint = (string) ($_POST['endpoint'] ?? '');
switch ($radnja) {
    case 'pretplati':
        $ok = push_pretplati((int) $k['Id'], $endpoint, (string) ($_POST['p256dh'] ?? ''), (string) ($_POST['auth'] ?? ''), push_uredjaj((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        if ($ok) {
            $_SESSION['push_endpoint'] = $endpoint;
        }
        json(['ok' => $ok, 'greska' => $ok ? null : 'Neispravna pretplata.']);
    case 'odjavi':
        push_odjavi($endpoint, (int) $k['Id']);
        unset($_SESSION['push_endpoint']);
        json(['ok' => true]);
    case 'proba':
        session_write_close();
        [$ok, $uk, $st] = push_proba((int) $k['Id']);
        json(['ok' => $ok > 0, 'uspjelo' => $ok, 'ukupno' => $uk, 'greska' => $uk === 0 ? 'Nijedan uređaj nije prijavljen.'
            : ($ok === 0 ? 'Push servis je odbio obavijest (HTTP ' . implode(', ', array_unique($st)) . ').' : null)]);
}
json(['ok' => false, 'greska' => 'Nepoznata radnja.']);
