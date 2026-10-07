<?php /** Okvir za prijavu i postavljanje. Varijable: $naslov, $sadrzaj */ ?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f3d1f">
    <link rel="manifest" href="<?= e(url('manifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(ima_logo() ? url('logo') : asset('assets/favicon.png')) ?>">
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
        <div class="card shadow-sm"><div class="card-body p-4">
            <?php foreach (poruke() as [$vrsta, $tekst]): ?>
                <div class="alert alert-<?= e($vrsta) ?>"><?= $tekst ?></div>
            <?php endforeach; ?>
            <?= $sadrzaj ?>
        </div></div>
    </div>
</div>
</body>
</html>
