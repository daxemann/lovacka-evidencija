<?php
/** Javna adresa i SMTP (e-pošta) – spremaju se u bazu, lozinka šifrirano. */
trazi(P_SUSTAV);
$pruzatelji = ['Gmail' => ['smtp.gmail.com', 587], 'GMX' => ['mail.gmx.net', 587], 'WEB.DE' => ['smtp.web.de', 587], 'Office 365' => ['smtp.office365.com', 587],
    'freenet' => ['mx.freenet.de', 587], 'T-Com / T-Mobile HR' => ['mail.t-com.hr', 587]];
$iz = function (): array {
    return [
        'Posluzitelj' => ul_str('posluzitelj'), 'Port' => (int) ($_POST['port'] ?? 587) ?: 587, 'Ssl' => isset($_POST['ssl']),
        'Korisnik' => ul_str('korisnik'), 'Posiljatelj' => ul_str('posiljatelj'), 'NazivPosiljatelja' => ul_str('naziv'),
    ];
};
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    $p = $iz();
    $novaLoz = (string) ($_POST['lozinka'] ?? '');
    if ($radnja === 'proba') {
        $pp = $p + ['Lozinka' => $novaLoz !== '' ? $novaLoz : (posta_postavke()['Lozinka'] ?? '')];
        $prima = ul_str('proba');
        try {
            posalji_mail($prima, 'Probna poruka – ' . udruga_kratko(), '<p>Slanje e-pošte iz evidencije radi. ✔</p>', true, $pp);
            poruka(e("Probna poruka poslana na $prima. Ako je stigla, kliknite „Spremi postavke“."));
        } catch (Throwable $e) {
            poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
        }
        $_SESSION['smtp_obrazac'] = $_POST;
        preusmjeri('sustav/postavke');
    }
    if ($radnja === 'iskljuci') {
        foreach (['Posluzitelj', 'Port', 'Ssl', 'Korisnik', 'Lozinka', 'Posiljatelj', 'NazivPosiljatelja'] as $kl) {
            spremi_postavku('Server.' . $kl, null);
        }
        dnevnik('E-pošta isključena');
        poruka('E-pošta je isključena.');
        preusmjeri('sustav/postavke');
    }
    $adr = ul_str('javna');
    if ($adr !== '') {
        if (!preg_match('#^https?://#i', $adr)) {
            $adr = 'https://' . $adr;
        }
        $adr = rtrim($adr, '/') . '/';
    }
    spremi_postavku('Server.JavnaAdresa', $adr ?: null);
    spremi_postavku('Server.Posluzitelj', $p['Posluzitelj'] ?: null);
    spremi_postavku('Server.Port', (string) $p['Port']);
    spremi_postavku('Server.Ssl', $p['Ssl'] ? '1' : '0');
    spremi_postavku('Server.Korisnik', $p['Korisnik'] ?: null);
    spremi_postavku('Server.Posiljatelj', $p['Posiljatelj'] ?: null);
    spremi_postavku('Server.NazivPosiljatelja', $p['NazivPosiljatelja'] ?: null);
    if ($novaLoz !== '') {
        spremi_postavku('Server.Lozinka', sifriraj($novaLoz));
    }
    dnevnik('Postavke e-pošte i adrese');
    poruka('Postavke su spremljene.');
    preusmjeri('sustav/postavke');
}
$p = posta_postavke();
$obr = $_SESSION['smtp_obrazac'] ?? null;
unset($_SESSION['smtp_obrazac']);
if ($obr) {
    $p = array_merge($p, ['Posluzitelj' => $obr['posluzitelj'] ?? '', 'Port' => (int) ($obr['port'] ?? 587), 'Ssl' => isset($obr['ssl']), 'Korisnik' => $obr['korisnik'] ?? '',
        'Posiljatelj' => $obr['posiljatelj'] ?? '', 'NazivPosiljatelja' => $obr['naziv'] ?? '']);
}
$imaLoz = (bool) postavka('Server.Lozinka');
$lozNET = $imaLoz && !str_starts_with((string) postavka('Server.Lozinka'), 'php:');
ob_start(); ?>
<h1 class="h3 mb-3">Postavke e-pošte i adrese</h1>
<p class="text-muted">Sve se sprema u bazu i vrijedi odmah.</p>
<div class="row g-4">
<div class="col-lg-8">
<form method="post" action="<?= e(url('sustav/postavke')) ?>" autocomplete="off"><?= csrf() ?>
    <div class="card mb-3"><div class="card-header">1. Javna adresa aplikacije</div><div class="card-body">
        <label class="form-label">Internetska adresa</label>
        <div class="input-group"><input name="javna" class="form-control" placeholder="https://evidencija.moja-udruga.hr" value="<?= e(postavka('Server.JavnaAdresa', '')) ?>">
            <button type="button" class="btn btn-outline-secondary" onclick="this.previousElementSibling.value=<?= e(json_encode(trenutna_adresa())) ?>">Trenutna adresa</button></div>
        <div class="form-text">Koristi se u pozivnicama, poveznicama za novu lozinku i u e-pošti. Ako je prazno, uzima se adresa kojom je aplikacija upravo otvorena.</div>
    </div></div>
    <div class="card mb-3"><div class="card-header">2. Slanje e-pošte (SMTP)</div><div class="card-body">
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Brzi odabir</label>
                <div class="d-flex flex-wrap gap-2"><?php foreach ($pruzatelji as $n => [$h, $port]): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('posl').value='<?= e($h) ?>';document.getElementById('port').value='<?= $port ?>';document.getElementById('ssl').checked=true"><?= e($n) ?></button>
                <?php endforeach; ?></div></div>
            <div class="col-md-8"><label class="form-label">Poslužitelj (SMTP)</label><input id="posl" name="posluzitelj" class="form-control" placeholder="smtp.gmail.com" value="<?= e($p['Posluzitelj']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Port</label><input id="port" name="port" type="number" class="form-control" value="<?= (int) $p['Port'] ?>"></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="ssl" id="ssl" value="1"<?= chk($p['Ssl']) ?>>
                <label class="form-check-label" for="ssl">Šifrirana veza (STARTTLS, kod porta 465 SSL) – preporučeno</label></div></div>
            <div class="col-md-6"><label class="form-label">Korisničko ime</label><input name="korisnik" class="form-control" value="<?= e($p['Korisnik']) ?>" autocomplete="off"></div>
            <div class="col-md-6"><label class="form-label">Lozinka <?php if ($imaLoz && !$lozNET): ?><span class="badge bg-success">spremljena</span><?php elseif ($lozNET): ?><span class="badge bg-warning text-dark">upišite ponovno</span><?php endif; ?></label>
                <input name="lozinka" type="password" class="form-control" autocomplete="new-password" placeholder="<?= $imaLoz && !$lozNET ? 'ostavite prazno za postojeću' : '' ?>"></div>
            <div class="col-md-6"><label class="form-label">Adresa pošiljatelja</label><input name="posiljatelj" type="email" class="form-control" value="<?= e($p['Posiljatelj']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Naziv pošiljatelja</label><input name="naziv" class="form-control" value="<?= e($p['NazivPosiljatelja']) ?>" placeholder="<?= e(udruga_kratko()) ?>"></div>
        </div>
        <?php if (str_contains($p['Posluzitelj'], 'gmail')): ?>
        <div class="alert alert-info small mt-3 mb-0"><b>Gmail:</b> obična lozinka ne radi. U Google računu uključite potvrdu u 2 koraka, zatim
            <i>Sigurnost → Lozinke aplikacija</i> → napravite lozinku (16 znakova) i upišite je ovdje.</div>
        <?php endif; ?>
        <div class="input-group mt-3" style="max-width:480px">
            <input name="proba" type="email" class="form-control" placeholder="probno pošalji na…">
            <button class="btn btn-outline-primary" name="radnja" value="proba">Pošalji probnu poruku</button>
        </div>
    </div></div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" name="radnja" value="spremi">Spremi postavke</button>
        <?php if (posta_dostupna()): ?><button class="btn btn-outline-danger" name="radnja" value="iskljuci" data-potvrda="Isključiti slanje e-pošte?">Isključi e-poštu</button><?php endif; ?>
    </div>
</form>
</div>
<div class="col-lg-4">
    <div class="card"><div class="card-header">Stanje</div>
        <ul class="list-group list-group-flush small">
            <li class="list-group-item"><span class="text-muted">Javna adresa</span><br><?= e(javna_adresa()) ?></li>
            <li class="list-group-item"><span class="text-muted">E-pošta</span><br><?= posta_dostupna() ? '<span class="badge bg-success">uključena</span> ' . e($p['Posluzitelj']) : '<span class="badge bg-secondary">nije podešena</span>' ?></li>
            <li class="list-group-item"><span class="text-muted">PHP</span><br><?= e(PHP_VERSION) ?> · SQLite <?= e((string) vrijednost('select sqlite_version()')) ?> · <?= extension_loaded('gd') ? 'GD ✓' : 'GD ✗ (slike se ne smanjuju)' ?></li>
            <li class="list-group-item"><span class="text-muted">Verzija aplikacije</span><br><?= e(VERZIJA) ?></li>
        </ul>
        <div class="card-body small text-muted">Bez e-pošte i dalje radi sve ostalo: poruke se šalju preko WhatsAppa, SMS-a ili kopiranjem teksta.
            S e-poštom rade i „Zaboravljena lozinka“, slanje PDF-a i obavijesti iz oglasnika.</div>
    </div>
</div>
</div>
<?php stranica('Postavke e-pošte i adrese', ob_get_clean());
