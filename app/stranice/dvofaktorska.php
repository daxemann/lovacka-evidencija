<?php
/** Dvofaktorska prijava za vlastiti račun: uključivanje (QR za Google Authenticator), rezervni kodovi, isključivanje. */
$k = korisnik();
if (!dvofaktor_ukljucen()) {
    poruka('Dvofaktorska prijava je isključena na ovom poslužitelju.', 'info');
    preusmjeri();
}
$racun = red('SELECT * FROM Korisnici WHERE Id=?', [$k['Id']]);
$obavezno = ($k['Prava'] & P_SUSTAV) === P_SUSTAV;
$ukljuceno = !empty($racun['TotpTajna']);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'ukljuci') {
        $tajna = (string) ($_SESSION['2fa_nova'] ?? '');
        $korak = $tajna !== '' ? totp_provjeri($tajna, ul_str('kod')) : null;
        if ($korak === null) {
            poruka('Kod nije točan – upišite trenutni kod iz aplikacije (provjerite da je sat na mobitelu točan).', 'danger');
            preusmjeri('dvofaktorska');
        }
        [$kodovi, $hashevi] = rezervni_kodovi_novi();
        azuriraj('Korisnici', (int) $k['Id'], ['TotpTajna' => sifriraj($tajna), 'TotpRezervni' => $hashevi, 'TotpZadnji' => $korak]);
        unset($_SESSION['2fa_nova']);
        $_SESSION['2fa_kodovi'] = $kodovi;
        dnevnik('Uključena dvofaktorska prijava');
        poruka('Dvofaktorska prijava je uključena. Spremite rezervne kodove!');
    } elseif ($radnja === 'novi-kodovi' && $ukljuceno) {
        [$kodovi, $hashevi] = rezervni_kodovi_novi();
        azuriraj('Korisnici', (int) $k['Id'], ['TotpRezervni' => $hashevi]);
        $_SESSION['2fa_kodovi'] = $kodovi;
        dnevnik('Novi rezervni kodovi (2FA)');
    } elseif ($radnja === 'iskljuci' && $ukljuceno) {
        if (!provjeri_lozinku((string) $racun['LozinkaHash'], (string) ($_POST['lozinka'] ?? ''))) {
            poruka('Lozinka nije točna.', 'danger');
            preusmjeri('dvofaktorska');
        }
        azuriraj('Korisnici', (int) $k['Id'], ['TotpTajna' => null, 'TotpRezervni' => null, 'TotpZadnji' => 0]);
        dnevnik('Isključena dvofaktorska prijava');
        poruka($obavezno ? 'Dvofaktorska prijava je poništena – za administratore je obavezna, postavite novi mobitel.' : 'Dvofaktorska prijava je isključena.', $obavezno ? 'warning' : 'success');
    }
    preusmjeri('dvofaktorska');
}
$kodovi = $_SESSION['2fa_kodovi'] ?? null;
unset($_SESSION['2fa_kodovi']);
if (!$ukljuceno) {
    $_SESSION['2fa_nova'] ??= totp_nova_tajna();
    $tajna = $_SESSION['2fa_nova'];
}
ob_start(); ?>
<div style="max-width:560px">
<h1 class="h3 mb-2">Dvofaktorska prijava</h1>
<?php if ($kodovi): ?>
    <div class="alert alert-warning">
        <b>Rezervni kodovi</b> – zapišite ih ili ispišite i čuvajte odvojeno od mobitela. Svaki vrijedi jednom, ako mobitel nije pri ruci. Više se neće prikazati.
        <div class="row row-cols-2 g-1 mt-2" style="font-family:monospace;font-size:1.1rem"><?php foreach ($kodovi as $x): ?><div class="col"><?= e($x) ?></div><?php endforeach; ?></div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 no-print" onclick="window.print()">Ispiši</button>
        <a class="btn btn-sm btn-primary mt-2 no-print" href="<?= e(url()) ?>">Spremio/la sam – dalje</a>
    </div>
<?php endif; ?>
<?php if ($ukljuceno): ?>
    <div class="alert alert-success">✓ Uključeno. Pri prijavi se uz lozinku traži kod iz aplikacije. Preostalo rezervnih kodova: <b><?= broj_rezervnih($racun) ?></b>.</div>
    <form method="post" class="mb-3"><?= csrf() ?><input type="hidden" name="radnja" value="novi-kodovi">
        <button class="btn btn-outline-secondary btn-sm" data-potvrda="Stvoriti nove rezervne kodove? Stari prestaju vrijediti.">Novi rezervni kodovi</button></form>
    <details><summary class="small">Novi mobitel / <?= $obavezno ? 'poništi' : 'isključi' ?></summary>
        <form method="post" class="row g-2 align-items-end mt-1"><?= csrf() ?><input type="hidden" name="radnja" value="iskljuci">
            <div class="col-sm-7"><label class="form-label small">Lozinka za potvrdu</label><input type="password" name="lozinka" class="form-control form-control-sm" required autocomplete="current-password"></div>
            <div class="col-sm-5"><button class="btn btn-sm btn-outline-danger w-100"><?= $obavezno ? 'Poništi i postavi ponovno' : 'Isključi' ?></button></div>
        </form>
        <?php if ($obavezno): ?><div class="form-text">Nakon poništavanja odmah postavite novi mobitel – bez toga administrator ne može raditi.</div><?php endif; ?>
    </details>
<?php else: ?>
    <?php if ($obavezno): ?><div class="alert alert-info">Za <b>administratore</b> je dvofaktorska prijava obavezna (zaštita podataka članova). Postavljanje traje minutu.</div><?php endif; ?>
    <ol class="ps-3">
        <li class="mb-2">Na mobitel instalirajte <b>Google Authenticator</b> (Google Play / App Store) – ili Microsoft Authenticator, Aegis…</li>
        <li class="mb-2">U aplikaciji dodirnite <b>+</b> → <b>Skeniraj QR kod</b> i skenirajte:
            <div id="qr2fa" class="my-2 p-2 bg-white border rounded d-inline-block"></div>
            <div class="small text-muted">Ne možete skenirati (otvoreno na istom mobitelu)? Odaberite „Unesi ključ“ i upišite:<br>
                <code class="user-select-all" style="font-size:1rem;word-break:break-all"><?= e(trim(chunk_split($tajna, 4, ' '))) ?></code></div></li>
        <li class="mb-2">Upišite 6-znamenkasti kod koji aplikacija pokazuje:
            <form method="post" class="d-flex gap-2 mt-1" style="max-width:320px"><?= csrf() ?><input type="hidden" name="radnja" value="ukljuci">
                <input name="kod" class="form-control text-center" style="letter-spacing:.3em;font-family:monospace" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required>
                <button class="btn btn-primary">Uključi</button></form></li>
    </ol>
    <script src="<?= e(asset('assets/qrcode.js')) ?>"></script>
    <script>(function () { var q = qrcode(0, 'M'); q.addData(<?= json_encode(totp_uri($tajna, $racun['KorisnickoIme'])) ?>); q.make();
        document.getElementById('qr2fa').innerHTML = q.createSvgTag({ cellSize: 5, margin: 2 }); })();</script>
    <?php if ($obavezno): ?>
        <form method="post" action="<?= e(url('odjava')) ?>" class="mt-3"><?= csrf() ?><button class="btn btn-link btn-sm p-0">Odjava</button></form>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php stranica('Dvofaktorska prijava', ob_get_clean());
