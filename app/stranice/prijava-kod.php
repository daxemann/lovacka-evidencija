<?php
/** Drugi korak prijave: 6-znamenkasti kod iz aplikacije (Google Authenticator) ili rezervni kod. */
$c = $_SESSION['2fa'] ?? null;
if (!$c || $c['do'] < time()) {
    unset($_SESSION['2fa']);
    preusmjeri('prijava', $c ? ['greska' => 'isteklo'] : []);
}
$racun = red('SELECT * FROM Korisnici WHERE Id=?', [$c['id']]);
if (!$racun || !$racun['Aktivan'] || !$racun['Odobren'] || !ima_2fa($racun)) {
    unset($_SESSION['2fa']);
    preusmjeri('prijava');
}
$greska = false;
if (je_post()) {
    $kod = ul_str('kod');
    $tajna = totp_tajna($racun);
    $korak = $tajna ? totp_provjeri($tajna, $kod, (int) $racun['TotpZadnji']) : null;
    $rezervni = false;
    if ($korak === null && strlen((string) preg_replace('/[^0-9a-fA-F]/', '', $kod)) === 8) {
        $rezervni = rezervni_kod_iskoristi($racun, $kod);
    }
    if ($korak !== null || $rezervni) {
        unset($_SESSION['2fa']);
        azuriraj('Korisnici', (int) $racun['Id'], ['NeuspjelePrijave' => 0, 'ZakljucanDo' => null, 'ZadnjaPrijava' => sada()]
            + ($korak !== null ? ['TotpZadnji' => $korak] : []));
        prijavi($racun);
        dnevnik('Prijava (2FA' . ($rezervni ? ', rezervni kod' : '') . ')', null, null, null, $racun);
        if ($rezervni) {
            poruka('Prijavljeni ste rezervnim kodom. Preostalo rezervnih kodova: ' . broj_rezervnih(red('SELECT * FROM Korisnici WHERE Id=?', [$racun['Id']])) . '.', 'warning');
        }
        $pov = (string) ($c['povratak'] ?? '');
        if ($pov !== '' && str_starts_with($pov, bazni_put()) && !str_starts_with($pov, '//') && !str_contains($pov, 'p=prijava')) {
            header('Location: ' . $pov);
            exit;
        }
        preusmjeri();
    }
    $_SESSION['2fa']['pokusaji'] = ($c['pokusaji'] ?? 0) + 1;
    usleep(400000);
    if ($_SESSION['2fa']['pokusaji'] >= 5) {
        unset($_SESSION['2fa']);
        azuriraj('Korisnici', (int) $racun['Id'], ['ZakljucanDo' => date('Y-m-d H:i:s', time() + 600)]);
        dnevnik('Neuspjela 2FA prijava (5 pokušaja)', null, null, $racun['KorisnickoIme'], $racun);
        preusmjeri('prijava', ['greska' => 'zakljucano']);
    }
    $greska = true;
}
ob_start(); ?>
<h1 class="h4 mb-2">Potvrda prijave</h1>
<p class="text-muted small">Otvorite aplikaciju <b>Google Authenticator</b> (ili sličnu) na mobitelu i upišite 6-znamenkasti kod za <b><?= e(udruga_kratko()) ?></b>.</p>
<?php if ($greska): ?><div class="alert alert-danger py-2">Kod nije točan. Provjerite da je sat na mobitelu točan i upišite novi kod.</div><?php endif; ?>
<form method="post" action="<?= e(url('prijava-kod')) ?>"><?= csrf() ?>
    <input name="kod" class="form-control form-control-lg text-center mb-3" style="letter-spacing:.4em;font-family:monospace" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="123456" autofocus required>
    <button class="btn btn-primary w-100">Potvrdi</button>
</form>
<details class="mt-3 small text-muted"><summary>Nemate mobitel?</summary>
    Upišite jedan od <b>rezervnih kodova</b> (oblik <code>ab12-cd34</code>) koje ste spremili pri uključivanju. Svaki vrijedi jednom.
    Ako nemate ni njih, drugi administrator može poništiti dvofaktorsku prijavu za vaš račun (Sustav → Korisnici).</details>
<div class="text-center mt-3"><a href="<?= e(url('prijava')) ?>" class="small">Odustani</a></div>
<?php stranica('Potvrda prijave', ob_get_clean(), 'prazno');
