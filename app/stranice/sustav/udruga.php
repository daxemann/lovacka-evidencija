<?php
/** Naziv udruge, kratki naziv, podnaslov i logo. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'logo') {
        try {
            $ime = spremi_sliku($_FILES['logo'] ?? [], 'logo-', 512, true);
            obrisi_sliku(postavka('Udruga.Logo'));
            spremi_postavku('Udruga.Logo', $ime);
            dnevnik('Novi logo udruge');
        } catch (Throwable $e) {
            poruka(e($e->getMessage()), 'danger');
        }
    } elseif ($radnja === 'ukloni-logo') {
        obrisi_sliku(postavka('Udruga.Logo'));
        spremi_postavku('Udruga.Logo', null);
    } else {
        $n = ul_str('naziv');
        if ($n === '') {
            poruka('Upišite naziv udruge.', 'danger');
            preusmjeri('sustav/udruga');
        }
        spremi_postavku('Udruga.Naziv', mb_substr($n, 0, 150));
        spremi_postavku('Udruga.Kratko', mb_substr(ul_str('kratko') !== '' ? ul_str('kratko') : $n, 0, 30));
        spremi_postavku('Udruga.Podnaslov', mb_substr(ul_str('podnaslov'), 0, 100) ?: null);
        dnevnik('Podaci o udruzi', null, null, $n);
        poruka('Spremljeno.');
    }
    preusmjeri('sustav/udruga');
}
ob_start(); ?>
<h1 class="h3 mb-3">Podaci o udruzi</h1>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <form method="post" action="<?= e(url('sustav/udruga')) ?>"><?= csrf() ?>
                <div class="mb-3"><label class="form-label">Puni naziv udruge</label>
                    <input name="naziv" class="form-control" maxlength="150" required value="<?= e(postavka('Udruga.Naziv', '')) ?>" placeholder="Lovačka udruga">
                    <div class="form-text">Prijava, ispisi (PDF), e-pošta, potpis u porukama.</div></div>
                <div class="mb-3"><label class="form-label">Kratki naziv</label>
                    <input name="kratko" class="form-control" maxlength="30" value="<?= e(postavka('Udruga.Kratko', '')) ?>" placeholder="Evidencija">
                    <div class="form-text">Gore lijevo u izborniku i u nazivu kartice preglednika.</div></div>
                <div class="mb-3"><label class="form-label">Podnaslov na prijavi</label>
                    <input name="podnaslov" class="form-control" maxlength="100" value="<?= e(postavka('Udruga.Podnaslov', '')) ?>" placeholder="Evidencija članova"></div>
                <button class="btn btn-primary">Spremi</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Logo</div><div class="card-body d-flex align-items-center gap-3">
            <div class="logo-okvir"><?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>?v=<?= e(postavka('Udruga.Logo')) ?>" alt=""><?php else: ?><span class="text-muted small">nema loga</span><?php endif; ?></div>
            <div>
                <form method="post" action="<?= e(url('sustav/udruga')) ?>" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="radnja" value="logo">
                    <label class="btn btn-sm btn-outline-primary mb-2">Učitaj logo<input type="file" name="logo" accept="image/*" class="d-none" onchange="this.form.submit()"></label></form>
                <?php if (ima_logo()): ?><form method="post" action="<?= e(url('sustav/udruga')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="ukloni-logo">
                    <button class="btn btn-sm btn-link text-danger p-0">Ukloni</button></form><?php endif; ?>
            </div>
        </div>
        <div class="card-body pt-0"><div class="form-text">Najbolje PNG s prozirnom pozadinom, kvadratno. Prikazuje se u izborniku, na prijavi, na PDF ispisima i kao ikona kartice.</div></div></div>
        <div class="card"><div class="card-header">Pregled</div><div class="card-body">
            <div class="pregled-izbornik d-flex align-items-center gap-2"><?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" class="nav-logo" alt=""><?php endif; ?><?= e(udruga_kratko()) ?></div>
            <div class="text-center mt-3"><?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" class="auth-logo-slika mb-2" alt=""><?php endif; ?>
                <div class="auth-logo"><?= e(udruga_naziv()) ?></div><div class="text-muted"><?= e(udruga_podnaslov()) ?></div></div>
        </div></div>
    </div>
</div>
<p class="small text-muted mt-3 mb-0">Sekcije (lovne jedinice), funkcije, vrste radnih akcija i predloške poruka udruga uređuje u ostalim stavkama izbornika Sustav.</p>
<?php stranica('Podaci o udruzi', ob_get_clean());
