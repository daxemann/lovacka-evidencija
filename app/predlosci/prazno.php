<?php /** Okvir za prijavu i postavljanje. Varijable: $naslov, $sadrzaj */ ?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!dopusti_trazilice()): ?><meta name="robots" content="noindex, nofollow, noarchive"><?php endif; ?>
    <meta name="theme-color" content="#1f3d1f">
    <link rel="manifest" href="<?= e(url('manifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('ikona', ['v' => 180, 'x' => postavka('Udruga.Logo')])) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e(udruga_kratko()) ?>">
    <title><?= e($naslov) ?> – <?= e(udruga_kratko()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
    <link rel="icon" type="image/png" href="<?= e(ima_logo() ? url('logo') : asset('assets/favicon.png')) ?>">
</head>
<body class="bg-light">
<div class="container">
    <div class="auth-okvir">
        <div class="text-center mb-4">
            <?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" class="auth-logo-slika mb-2" alt=""><?php endif; ?>
            <div class="auth-logo"><?= e(udruga_naziv()) ?></div>
            <div class="text-muted"><?= e(udruga_podnaslov()) ?></div>
        </div>
        <?php if (je_demo() && ($_GET['p'] ?? '') !== 'dez'): ?><div class="demo-traka mb-3">🎯 <b>Demo verzija</b> – izmišljeni podaci, ništa se ne može pokvariti.</div><?php endif; ?>
        <div class="card shadow-sm"><div class="card-body p-4">
            <?php foreach (poruke() as [$vrsta, $tekst]): ?>
                <div class="alert alert-<?= e($vrsta) ?>"><?= $tekst ?></div>
            <?php endforeach; ?>
            <?= $sadrzaj ?>
        </div></div>
        <div class="program-potpis-auth"><img src="<?= e(asset('assets/logo-evidencija.svg')) ?>" alt="" width="20" height="20"> Lovačka Evidencija</div>
    </div>
</div>
<?php if (($_GET['p'] ?? '') === 'prijava' && !korisnik()): ?>
<script>/* odjava / nova prijava: stranice stanica spremljene na mobitelu sadrže podatke prethodnog korisnika */
try { ['ev-dez-predmem', 'ev-dez-stanice', 'ev-dez-kid', 'ev-dez-provjera'].forEach(function (k) { localStorage.removeItem(k); }); } catch (x) {}
if ('serviceWorker' in navigator && navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ tip: 'dez-brisi' });</script>
<?php endif; ?>
</body>
</html>
