<?php
/** Glavni okvir: bočni izbornik + zaglavlje. Varijable: $naslov, $sadrzaj */
$k = korisnik();
$trenutna = (string) ($_GET['p'] ?? 'pocetna');
$stavka = function (string $href, string $tekst, int $broj = 0, bool $tocno = false) use ($trenutna) {
    $akt = $tocno ? $trenutna === $href : ($trenutna === $href || str_starts_with($trenutna, $href . '/'));
    if ($href === 'pocetna' && $trenutna === '') {
        $akt = true;
    }
    return '<div class="nav-item px-3"><a class="nav-link' . ($akt ? ' active' : '') . '" href="' . e(url($href)) . '">'
        . '<span class="flex-grow-1">' . e($tekst) . '</span>'
        . ($broj > 0 ? '<span class="badge rounded-pill bg-warning text-dark">' . $broj . '</span>' : '') . '</a></div>';
};
$naslovIzb = fn(string $t) => '<div class="px-3 pt-3 small text-white-50 text-uppercase">' . e($t) . '</div>';
$neprocitano = $k ? broj_neprocitanih((int) $k['Id']) : 0;
[$uloga, $boja] = oznaka_uloge();
?><!doctype html>
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
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<div class="page">
    <div class="sidebar">
        <div class="top-row ps-3 navbar navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url()) ?>">
                    <?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" class="nav-logo" alt=""><?php endif; ?>
                    <span class="text-truncate"><?= e(udruga_kratko()) ?></span>
                </a>
            </div>
        </div>
        <input type="checkbox" title="Izbornik" class="navbar-toggler">
        <div class="nav-scrollable" onclick="document.querySelector('.navbar-toggler').click()">
            <nav class="nav flex-column">
                <?php if ($k): ?>
                    <?php if ($k['Prava']): ?><?= $stavka('pocetna', 'Početna', 0, true) ?><?php endif; ?>
                    <?php if ($k['ClanId']): ?>
                        <?= $naslovIzb('Moje') ?>
                        <?= $stavka('moje-akcije', 'Moje radne akcije') ?>
                        <?= $stavka('moj-profil', 'Moj profil') ?>
                        <?= $stavka('moja-prava', 'Moja prava') ?>
                    <?php endif; ?>
                    <?= $naslovIzb('Oglasnik') ?>
                    <?= $stavka('oglasnik', 'Kupujem / prodajem') ?>
                    <?= $stavka('poruke', 'Poruke', $neprocitano) ?>
                    <?php if ($k['Prava']): ?>
                        <?= $naslovIzb('Udruga') ?>
                        <?php if (ima(P_CLANOVI_CITAJ)) echo $stavka('clanovi', 'Članovi'); ?>
                        <?php if (ima(P_CLANARINA_CITAJ)) echo $stavka('clanarina', 'Članarina'); ?>
                        <?php if (ima(P_AKCIJE_ODOBRI)) echo $stavka('akcije/nova', '+ Nova radna akcija', 0, true); ?>
                        <?php if (ima(P_AKCIJE_CITAJ)) echo $stavka('akcije', 'Radne akcije', broj_akcija_na_cekanju(), true); ?>
                        <?php if (ima(P_IZVJESTAJI)) echo $stavka('izvjestaji/akcije', 'Izvještaj radnih akcija'); ?>
                    <?php endif; ?>
                    <?php if (ima(P_SUSTAV)): ?>
                        <?= $naslovIzb('Sustav') ?>
                        <?= $stavka('sustav/udruga', 'Podaci o udruzi') ?>
                        <?= $stavka('sustav/postavke', 'E-pošta i adresa') ?>
                        <?= $stavka('sustav/korisnici', 'Korisnici', (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND Aktivan=1')) ?>
                        <?= $stavka('sustav/samoprijava', 'Samoprijava', (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND ClanId IS NULL')) ?>
                        <?= $stavka('sustav/uloge', 'Uloge i prava') ?>
                        <?= $stavka('sustav/sekcije', 'Sekcije') ?>
                        <?= $stavka('akcije/vrste', 'Vrste radnih akcija') ?>
                        <?= $stavka('sustav/poruke', 'Predlošci poruka') ?>
                        <?= $stavka('sustav/uvoz', 'Uvoz (Google kontakti)') ?>
                        <?= $stavka('sustav/duplikati', 'Provjera duplikata') ?>
                        <?= $stavka('sustav/kopije', 'Sigurnosne kopije') ?>
                        <?= $stavka('sustav/dnevnik', 'Dnevnik promjena') ?>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    <main>
        <div class="top-row px-4">
            <?php if ($k): ?>
                <a href="<?= e(url('moja-prava')) ?>" class="text-decoration-none d-flex align-items-center gap-1 me-3" title="Moja prava">
                    <span class="small text-body d-none d-sm-inline"><?= e($k['Naziv']) ?></span>
                    <span class="badge <?= e($boja) ?>"><?= e($uloga) ?></span>
                </a>
                <a href="<?= e(url('promjena-lozinke')) ?>">Lozinka</a>
                <form method="post" action="<?= e(url('odjava')) ?>" class="ms-3 d-inline"><?= csrf() ?>
                    <button type="submit" class="btn btn-link p-0">Odjava</button>
                </form>
            <?php endif; ?>
        </div>
        <article class="content px-4 pb-5">
            <?php foreach (poruke() as [$vrsta, $tekst]): ?>
                <div class="alert alert-<?= e($vrsta) ?> alert-dismissible fade show no-print" role="alert">
                    <?= $tekst ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zatvori" onclick="this.parentElement.remove()"></button>
                </div>
            <?php endforeach; ?>
            <?= $sadrzaj ?>
        </article>
    </main>
</div>
<div id="obavijest" class="lf-toast" style="display:none"></div>
<script>window.EV = { api: <?= json_encode(url('api/obavijesti')) ?>, poruke: <?= json_encode(url('poruke')) ?>, n: <?= (int) $neprocitano ?> };</script>
<script src="<?= e(asset('assets/app.js')) ?>"></script>
</body>
</html>
