<?php
/** Sustav → Moduli: udruga uključuje samo dijelove programa koje koristi. */
trazi(P_SUSTAV);
$k = korisnik();
if (je_post()) {
    if (!$k['SveSekcije']) {
        poruka('Module uključuje / isključuje samo administrator za sve sekcije.', 'warning');
        preusmjeri('sustav/moduli');
    }
    $prije = [];
    $poslije = [];
    foreach (moduli() as $kl => $d) {
        $bilo = modul($kl);
        $sada = isset($_POST['m'][$kl]);
        if ($bilo !== $sada) {
            if ($sada) {
                $poslije[] = $d[1];
            } else {
                $prije[] = $d[1];
            }
        }
        spremi_postavku('Modul.' . $kl, $sada ? null : '0'); // zadano: uključeno
    }
    if ($prije || $poslije) {
        dnevnik('Moduli', null, null, trim(($poslije ? 'uključeno: ' . implode(', ', $poslije) : '') . ($prije ? ' · isključeno: ' . implode(', ', $prije) : ''), ' ·'));
    }
    poruka('Moduli su spremljeni. Isključeni dijelovi nestaju iz izbornika svih korisnika – podaci ostaju sačuvani.');
    preusmjeri('sustav/moduli');
}
ob_start(); ?>
<div class="d-flex align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('sustav')) ?>">← Sustav</a>
    <h1 class="h3 mb-0">🧩 Moduli</h1>
</div>
<p class="text-muted">Uključite samo ono što vaša udruga stvarno koristi – lovci i lovočuvari tada vide jednostavniji izbornik.
    Isključeni modul nestaje iz izbornika, početne stranice i uputa, a njegove stranice i prava ne vrijede.
    <b>Podaci se ne brišu</b> – kad modul ponovno uključite, sve je opet tu.</p>
<p class="small text-muted">Uvijek uključeno: članovi, imenik, poruke, profil, push obavijesti, sustav.</p>
<form method="post"><?= csrf() ?>
    <div class="row g-2 g-md-3">
        <?php foreach (moduli() as $kl => [$ik, $naz, $opis]): $on = modul($kl); ?>
        <div class="col-12 col-md-6 col-xl-4">
            <label class="card modul-kartica h-100<?= $on ? ' ukljuceno' : '' ?>">
                <div class="card-body p-3 d-flex gap-3 align-items-start">
                    <span class="sustav-ikona" aria-hidden="true"><?= $ik ?></span>
                    <span class="flex-grow-1">
                        <span class="fw-semibold d-block"><?= e($naz) ?></span>
                        <span class="small text-muted"><?= e($opis) ?></span>
                    </span>
                    <span class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="m[<?= e($kl) ?>]" value="1"<?= $on ? ' checked' : '' ?><?= $k['SveSekcije'] ? '' : ' disabled' ?>
                            onchange="this.closest('.modul-kartica').classList.toggle('ukljuceno', this.checked)" aria-label="<?= e($naz) ?>">
                    </span>
                </div>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($k['SveSekcije']): ?><button class="btn btn-primary mt-3">Spremi</button><?php endif; ?>
</form>
<?php stranica('Moduli', ob_get_clean());
