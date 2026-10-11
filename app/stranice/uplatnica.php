<?php
/**
 * Moja uplatnica: član vidi samo svoje otvorene rate s velikim 2D kodom (pokazati u banci / skenirati) i gumbom za ispis.
 * Blagajnik (pravo Članarina – pregled, u svom opsegu) može otvoriti uplatnicu bilo kojeg člana: ?clan=ID
 */
$k = korisnik();
$clanId = ul_int('clan') ?? $k['ClanId'];
$svoj = $clanId !== null && $clanId === $k['ClanId'];
$clan = $clanId ? red('SELECT * FROM Clanovi WHERE Id=?', [$clanId]) : null;
if (!$clan) {
    nije_pronadjeno('Uplatnica');
}
$blagajnik = ima(P_CLANARINA_CITAJ) && u_opsegu($clan);
if (!$blagajnik && !($svoj && uplatnica_za_clanove())) {
    zabranjeno();
}
if (!uplatnica_spremna()) {
    if (ima(P_SUSTAV)) {
        poruka('Najprije upišite IBAN udruge.', 'warning');
        preusmjeri('sustav/uplatnica');
    }
    nije_pronadjeno('Uplatnica');
}
$godine = array_map('intval', array_column(redovi('SELECT Godina FROM Clanarine WHERE ClanId=? ORDER BY Godina DESC', [$clanId]), 'Godina'));
$godina = (int) ($_GET['godina'] ?? 0);
// bez zadane godine: sve godine s otvorenim iznosom (najstarija prva)
$poGodinama = [];
foreach ($godina ? [$godina] : array_reverse($godine) as $g) {
    $u = uplatnice_clana($clanId, $g);
    if ($u) {
        $poGodinama[$g] = $u;
    }
}
ob_start(); ?>
<div class="uplatnica-stranica" style="max-width:560px">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0 me-auto"><?= $svoj ? 'Moja uplatnica' : 'Uplatnica – ' . e(puno_ime($clan)) ?></h1>
    <?php if ($blagajnik && !$svoj): ?><a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('clanovi/uredi', ['id' => $clanId, 'kartica' => 'clanarina'])) ?>">← Član</a><?php endif; ?>
</div>
<?php if (!$poGodinama): ?>
    <div class="alert alert-success">Nema otvorene članarine – uplatnica nije potrebna. ✓</div>
<?php else: ?>
    <p class="small text-muted no-print mb-3">Plaćanje: u <b>mobilnom bankarstvu</b> odaberite „Skeniraj / Slikaj uplatnicu“ i skenirajte kod – ili u banci, pošti ili FINA-i <b>pokažite ovaj zaslon</b> (pojačajte svjetlinu) ili ispisanu uplatnicu.</p>
    <?php foreach ($poGodinama as $g => $lista): foreach ($lista as $i => $u): ?>
        <div class="card mb-3"><div class="card-body"><?= uplatnica_kartica($u) ?>
            <div class="d-flex gap-2 mt-2 no-print">
                <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(url('izvoz/uplatnica', ['clan' => $clanId, 'godina' => $g, 'rata' => $u['RedniBroj']])) ?>">🖨 Ispis / PDF</a>
            </div>
        </div></div>
    <?php endforeach; endforeach; ?>
    <?php $sve = array_merge(...array_values($poGodinama)); if (count($sve) > 1): ?>
        <a class="btn btn-outline-secondary btn-sm no-print" target="_blank" href="<?= e(url('izvoz/uplatnica', ['clan' => $clanId] + ($godina ? ['godina' => $godina] : ['sve-godine' => 1]))) ?>">🖨 Sve uplatnice (<?= count($sve) ?>) za ispis</a>
    <?php endif; ?>
<?php endif; ?>
<p class="small text-muted mt-3">Ako ste već platili, zanemarite – uplata možda još nije upisana. Pitanja: blagajnik udruge.</p>
</div>
<?php stranica($svoj ? 'Moja uplatnica' : 'Uplatnica', ob_get_clean());
