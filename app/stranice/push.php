<?php
/** Push obavijesti: uključivanje na ovom uređaju, vrste obavijesti, popis uređaja, probna obavijest. */
$k = korisnik();
$kid = (int) $k['Id'];

// vrste koje ovaj korisnik uopće može dobiti
$revir = (bool) revir_sekcije();
$vrste = array_filter(PUSH_VRSTE, fn($v, $kljuc) => match ($kljuc) {
    'akcije' => $k['ClanId'] !== null || ima(P_AKCIJE_ODOBRI),
    'kvarovi' => $revir && (ima(P_REVIR_NADZOR) || ima(P_REVIR_UREDI)),
    'loviste' => $revir && ima(P_REVIR_NADZOR),
    default => true,
}, ARRAY_FILTER_USE_BOTH);

if (je_post()) {
    $r = (string) ($_POST['radnja'] ?? '');
    if ($r === 'vrste') {
        $ukljuceno = array_keys((array) ($_POST['v'] ?? []));
        // vrste koje se korisniku ne prikazuju ostaju kako jesu
        $iskljuceno = array_diff(push_iskljuceno($kid), array_keys($vrste));
        push_spremi_iskljuceno($kid, array_merge($iskljuceno, array_diff(array_keys($vrste), $ukljuceno)));
        poruka('Spremljeno.');
    } elseif ($r === 'ukloni') {
        q('DELETE FROM PushPretplate WHERE Id=? AND KorisnikId=?', [(int) ($_POST['id'] ?? 0), $kid]);
        poruka('Uređaj više ne prima obavijesti.');
    }
    preusmjeri('push');
}

$iskljuceno = push_iskljuceno($kid);
$uredjaji = redovi('SELECT * FROM PushPretplate WHERE KorisnikId=? ORDER BY Kreirano DESC', [$kid]);
$podrzan = push_podrzan();
ob_start(); ?>
<h1 class="h3 mb-1">🔔 Push obavijesti</h1>
<p class="text-muted">Obavijest stiže na mobitel ili računalo i kad je aplikacija zatvorena – kao poruka iz Vibera ili WhatsAppa.</p>

<?php if (!$podrzan): ?>
<div class="alert alert-warning">Poslužitelj ne podržava push obavijesti (PHP OpenSSL bez krivulje P-256). Javite administratoru.</div>
<?php else: ?>
<div class="card mb-3" id="push-kartica" data-api="<?= e(url('api/push')) ?>">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="flex-grow-1">
                <div class="fw-semibold">Ovaj uređaj</div>
                <div class="small" id="push-stanje">Provjeravam…</div>
            </div>
            <button type="button" class="btn btn-success" id="push-ukljuci" hidden>🔔 Uključi obavijesti</button>
            <button type="button" class="btn btn-outline-secondary" id="push-iskljuci" hidden>Isključi na ovom uređaju</button>
            <button type="button" class="btn btn-outline-primary" id="push-proba" <?= $uredjaji ? '' : 'hidden' ?>>Pošalji probnu</button>
        </div>
        <div class="alert alert-info small mt-3 mb-0" id="push-ios" hidden>
            <b>iPhone / iPad:</b> obavijesti rade samo iz aplikacije na početnom zaslonu (iOS 16.4 ili noviji).
            U Safariju dodirnite <b>Dijeli</b> <span aria-hidden="true">⎙</span> → <b>Dodaj na početni zaslon</b>, otvorite aplikaciju s ikone i ovdje uključite obavijesti.
        </div>
        <div class="alert alert-warning small mt-3 mb-0" id="push-blokirano" hidden>
            Obavijesti su <b>blokirane</b> u pregledniku. Dopustite ih u postavkama preglednika / mobitela
            (Android: dugi dodir na ikonu aplikacije → Informacije o aplikaciji → Obavijesti), pa osvježite stranicu.
        </div>
    </div>
</div>

<form method="post" class="card mb-3"><?= csrf() ?><input type="hidden" name="radnja" value="vrste">
    <div class="card-header">Što želim primati</div>
    <div class="list-group list-group-flush">
    <?php foreach ($vrste as $kljuc => [$naziv, $opis]): ?>
        <label class="list-group-item d-flex gap-3 align-items-start">
            <input class="form-check-input mt-1 flex-shrink-0" type="checkbox" name="v[<?= e($kljuc) ?>]" value="1"<?= in_array($kljuc, $iskljuceno, true) ? '' : ' checked' ?>>
            <span><span class="fw-semibold"><?= e($naziv) ?></span><br><span class="small text-muted"><?= e($opis) ?></span></span>
        </label>
    <?php endforeach; ?>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary btn-sm">Spremi</button></div>
</form>

<?php if ($uredjaji): ?>
<h2 class="h6 mt-4">Moji uređaji s obavijestima</h2>
<ul class="list-group mb-3">
    <?php foreach ($uredjaji as $u): ?>
        <li class="list-group-item d-flex align-items-center gap-2" data-endpoint="<?= e($u['Endpoint']) ?>">
            <div class="flex-grow-1"><?= e($u['Uredjaj'] ?: 'Uređaj') ?> <span class="badge bg-success d-none" data-ovaj>ovaj uređaj</span>
                <div class="small text-muted">od <?= e(datum($u['Kreirano'])) ?><?= $u['ZadnjeSlanje'] ? ' · zadnja obavijest ' . e(datum_vrijeme($u['ZadnjeSlanje'])) : '' ?><?= $u['Greske'] ? ' · <span class="text-danger">neuspjelo ' . (int) $u['Greske'] . '×</span>' : '' ?></div></div>
            <form method="post"><?= csrf() ?><input type="hidden" name="radnja" value="ukloni"><input type="hidden" name="id" value="<?= (int) $u['Id'] ?>">
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Ovaj uređaj više neće primati obavijesti. Nastaviti?">Ukloni</button></form>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="small text-muted">Obavijesti su besplatne – šalju se preko push servisa preglednika (Google, Apple, Mozilla), bez SMS-a i bez dodatne aplikacije.
    Pri odjavi s aplikacije uređaj prestaje primati obavijesti za vaš račun.</p>
<?php endif; ?>
<?php stranica('Push obavijesti', ob_get_clean());
