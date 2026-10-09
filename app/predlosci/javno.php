<?php /** Široki okvir bez izbornika (npr. pregled za inspekciju). Varijable: $naslov, $sadrzaj */ ?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f3d1f">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($naslov) ?> – <?= e(udruga_kratko()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
    <link rel="icon" type="image/png" href="<?= e(ima_logo() ? url('logo') : asset('assets/favicon.png')) ?>">
</head>
<body class="bg-light">
<div class="container-xl py-3">
    <div class="d-flex align-items-center gap-2 mb-3 no-print">
        <?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" alt="" style="height:44px"><?php endif; ?>
        <div><div class="auth-logo lh-1"><?= e(udruga_naziv()) ?></div><div class="text-muted small"><?= e($naslov) ?></div></div>
    </div>
    <?php foreach (poruke() as [$vrsta, $tekst]): ?>
        <div class="alert alert-<?= e($vrsta) ?> no-print"><?= $tekst ?></div>
    <?php endforeach; ?>
    <?= $sadrzaj ?>
</div>
<script src="<?= e(asset('assets/app.js')) ?>"></script>
</body>
</html>
