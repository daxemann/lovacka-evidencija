<?php
/**
 * "Skeniraj QR stanice" – radi i bez interneta (service worker je sprema na mobitel).
 * Ista stranica je i početna aplikacije bez signala: skener QR oznake (kamera unutar aplikacije – na iPhoneu
 * tako upis ostaje u aplikaciji, a ne u Safariju), upisi koji čekaju slanje i stanice spremljene na mobitelu.
 * Stranica je javna i NE sadrži podatke korisnika (sprema se na mobitel) – popis stanica dolazi iz memorije mobitela.
 */
header('Cache-Control: no-cache');
$ikona = url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')]);
?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f3d1f">
    <link rel="manifest" href="<?= e(url('manifest')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Skeniraj QR – <?= e(udruga_kratko()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
</head>
<body class="bg-light">
<div class="container skener-okvir py-3">
    <div class="d-flex align-items-center gap-2 mb-3">
        <img src="<?= e($ikona) ?>" alt="" width="36" height="36" class="rounded">
        <div class="flex-grow-1 lh-sm"><div class="fw-semibold"><?= e(udruga_kratko()) ?></div><div class="small text-muted">Dezinfekcija</div></div>
        <span id="sk-mreza" class="badge rounded-pill text-bg-secondary">…</span>
    </div>

    <div id="sk-nema-stanice" class="alert alert-warning" hidden>
        <b>Ova stanica nije spremljena na mobitelu.</b><br>Bez interneta se može upisati samo na stanici koju ste na ovom mobitelu već jednom otvorili s internetom.
        Upišite se na papirnatu listu ili ponovno kad bude signala.
    </div>
    <div id="dez-sat" class="alert alert-warning small" hidden></div>
    <div id="dez-greska" class="alert alert-danger small" hidden></div>

    <button type="button" class="btn btn-primary btn-lg w-100 py-3 mb-2" id="sk-start">📷 Skeniraj QR oznaku stanice</button>
    <div id="sk-kamera" class="skener-kamera mb-2" hidden>
        <video id="sk-video" playsinline muted></video>
        <div class="skener-okvir-ciljnik"></div>
        <button type="button" class="btn btn-light btn-sm skener-zatvori" id="sk-stop">✕ Zatvori</button>
    </div>
    <div id="sk-poruka" class="small text-center text-muted mb-3">Usmjerite kameru na QR oznaku na dezinfekcijskoj stanici.</div>

    <div class="card mb-3"><div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h6 mb-0 flex-grow-1">Upisi na ovom mobitelu</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" id="sk-posalji" hidden>Pošalji sada</button>
        </div>
        <div id="dez-red-popis" class="small text-muted">Nema upisa koji čekaju slanje.</div>
    </div></div>

    <div class="card mb-3"><div class="card-body p-3">
        <h2 class="h6 mb-2">Stanice spremljene za rad bez signala</h2>
        <div id="sk-stanice" class="small text-muted">Popis se sprema kad aplikaciju otvorite s internetom (prijavljeni).</div>
    </div></div>

    <a class="btn btn-outline-success w-100 mb-2" href="<?= e(url('moja-iskaznica')) ?>" id="sk-iskaznica" hidden>🪪 Moja iskaznica (QR za popis lova)</a>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary flex-grow-1" href="<?= e(url()) ?>" id="sk-aplikacija">Otvori aplikaciju</a>
        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()" title="Osvježi">⟳</button>
    </div>
    <p class="small text-muted text-center mt-3 mb-0">Upisi bez interneta spremaju se na mobitel s vremenom mobitela i šalju se automatski kad bude signala.</p>
</div>
<script>window.SKENER = <?= json_encode(['baza' => bazni_put(), 'stanica' => url('dez', ['s' => '__S__'])], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(asset('assets/jsqr.min.js')) ?>"></script>
<script src="<?= e(asset('assets/dez.js')) ?>" data-api="<?= e(url('api/dez-sync')) ?>" data-sw="<?= e(bazni_put() . 'sw.js') ?>"></script>
<script src="<?= e(asset('assets/skener.js')) ?>"></script>
</body>
</html>
<?php exit;
