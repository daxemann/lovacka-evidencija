<?php
/** 🦌 Odstrel – postavke evidencije: po sekcijama / zajednički / spojene sekcije, polja za obrasce, SLE. */
trazi(P_SUSTAV);
$sve = odstrel_sve_sekcije();
if (je_post()) {
    $vod = (string) ($_POST['vodjenje'] ?? 'sekcije');
    $vod = isset(ODSTREL_VODJENJE[$vod]) ? $vod : 'sekcije';
    spremi_postavku('Odstrel.Vodjenje', $vod === 'sekcije' ? null : $vod);
    // spajanja: [sekcija => vodeća]; vodeća mora sama voditi svoju evidenciju
    $sp = [];
    foreach ((array) ($_POST['vodi'] ?? []) as $sid => $vodeca) {
        $sid = (int) $sid;
        $vodeca = (int) $vodeca;
        if ($vodeca && $vodeca !== $sid && isset($sve[$sid], $sve[$vodeca])) {
            $sp[$sid] = $vodeca;
        }
    }
    $odbaceno = 0;
    foreach ($sp as $sid => $vodeca) {
        if (isset($sp[$vodeca])) {
            unset($sp[$sid]);
            $odbaceno++;
        }
    }
    spremi_postavku('Odstrel.Spajanja', $sp ? json_encode($sp) : null);
    $polja = array_values(array_intersect(array_keys(ODSTREL_POLJA), array_keys((array) ($_POST['polja'] ?? []))));
    spremi_postavku('Odstrel.Polja', $polja ? implode(',', $polja) : null);
    $sleBilo = odstrel_sle();
    $sle = isset($_POST['sle']);
    spremi_postavku('Odstrel.Sle', $sle ? '1' : null);
    if ($sle) {
        $od = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['sle_od'] ?? '')) ? $_POST['sle_od'] : null;
        spremi_postavku('Odstrel.SleOd', $od ?? ($sleBilo ? postavka('Odstrel.SleOd') : danas()) ?? danas());
    }
    dnevnik('Postavke odstrela', null, null, ODSTREL_VODJENJE[$vod] . ($polja ? ' · polja: ' . implode(', ', $polja) : '') . ($sle ? ' · SLE' : ''));
    poruka('Spremljeno.' . ($odbaceno ? ' Sekcija koja vodi evidenciju za drugu ne može i sama biti pridružena – to spajanje je poništeno.' : ''), $odbaceno ? 'warning' : 'success');
    preusmjeri('sustav/odstrel');
}
$vod = odstrel_vodjenje();
$sp = (array) json_decode((string) postavka('Odstrel.Spajanja', ''), true);
$polja = odstrel_polja();
$grupe = odstrel_grupe();
ob_start(); ?>
<div class="d-flex align-items-center gap-2 mb-1">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('sustav')) ?>">← Sustav</a>
    <h1 class="h3 mb-0">🦌 Odstrel – evidencija</h1>
</div>
<p class="text-muted">Kako udruga vodi odstrel, markice i „papire“ (plan, PDF, kasnije markice i obrasci). Svaka udruga to radi malo drukčije.</p>
<div class="alert alert-secondary small">ℹ️ <?= e(ODSTREL_SLE_NAPOMENA) ?></div>
<form method="post" style="max-width:820px"><?= csrf() ?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">🗂️ Vođenje evidencije</h2>
    <?php if (!$sve): ?>
        <p class="small text-muted mb-0">Udruga nema sekcija – vodi se jedna evidencija za cijelu udrugu.</p>
        <input type="hidden" name="vodjenje" value="sekcije">
    <?php else: foreach (ODSTREL_VODJENJE as $kl => $opis): ?>
        <div class="form-check"><input class="form-check-input" type="radio" name="vodjenje" id="vod-<?= $kl ?>" value="<?= $kl ?>"<?= chk($vod === $kl) ?> data-vodjenje>
            <label class="form-check-label" for="vod-<?= $kl ?>"><?= e($opis) ?></label></div>
    <?php endforeach; ?>
    <div class="mt-3" data-spajanje<?= $vod === 'mjesovito' ? '' : ' hidden' ?>>
        <div class="form-label">Tko vodi evidenciju za koju sekciju</div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-1">
            <thead><tr><th>Sekcija</th><th>Evidenciju vodi</th></tr></thead><tbody>
            <?php foreach ($sve as $sid => $n): $cur = (int) ($sp[$sid] ?? 0); ?>
            <tr><td><?= e($n) ?></td><td><select name="vodi[<?= (int) $sid ?>]" class="form-select form-select-sm">
                <option value="0">vlastita evidencija</option>
                <?php foreach ($sve as $sid2 => $n2): if ($sid2 === $sid) continue; ?><option value="<?= (int) $sid2 ?>"<?= sel($sid2, $cur) ?>>pridruži sekciji: <?= e($n2) ?></option><?php endforeach; ?>
            </select></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <div class="small text-muted">Pridružena sekcija upisuje se pod svojim imenom, ali markice, plan odstrela i PDF vode se zajedno s vodećom sekcijom.</div>
    </div>
    <div class="small text-muted mt-2">
        <b>Prava:</b> tko ima pravo <i>Odstrel – pregled / upis / upozorenja</i> za jednu sekciju evidencije, ima ga za cijelu evidenciju
        (spojene sekcije, odnosno kod zajedničke evidencije cijela udruga).<br>
        <b>Plan odstrela</b> sprema se po evidenciji: plan pridružene sekcije ostaje spremljen, ali se ne prikazuje dok je sekcija spojena.
    </div>
    <div class="mt-2 small">Sada: <?php foreach ($grupe as $n): ?><span class="badge text-bg-light border me-1"><?= e($n) ?></span><?php endforeach; ?></div>
    <?php endif; ?>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">📝 Dodatna polja za obrasce</h2>
    <p class="small text-muted">Polja u upisu odstrela – uključite samo ono što udruga stvarno vodi. Prikazuju se i u popisu i u PDF-u.</p>
    <?php foreach (ODSTREL_POLJA as $kl => $n): ?>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="polja[<?= $kl ?>]" id="po-<?= $kl ?>" value="1"<?= chk(in_array($kl, $polja, true)) ?>>
            <label class="form-check-label" for="po-<?= $kl ?>"><?= e($n) ?></label></div>
    <?php endforeach; ?>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">🏛️ Središnja lovna evidencija (SLE)</h2>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="sle" id="sle" value="1"<?= chk(odstrel_sle()) ?> data-sle>
        <label class="form-check-label" for="sle"><b>Udruga koristi SLE</b> – kod odstrela kvačica „upisano u SLE“ i upozorenje za rok od 7 dana (razduženje markice)</label></div>
    <div class="mt-2 d-flex flex-wrap align-items-center gap-2" data-sle-od<?= odstrel_sle() ? '' : ' hidden' ?>>
        <label class="small" for="sle-od">Upozoravati za odstrel od</label>
        <input type="date" class="form-control form-control-sm" style="max-width:11rem" name="sle_od" id="sle-od" value="<?= e((string) postavka('Odstrel.SleOd', danas())) ?>">
        <span class="small text-muted">(stariji upisi bez upozorenja)</span>
    </div>
    <div class="small text-muted mt-2">Od 15.4. do 31.5. upozorenje i bez SLE: povrat neiskorištenih markica (PPD-3) i izvršenje plana.
        Tko prima upozorenja (početna → Uprava i stranica Odstrel): pravo <b>Odstrel – upozorenja</b> u <a href="<?= e(url('sustav/uloge')) ?>">Uloge i prava</a>, za sekcije uloge.</div>
</div></div>
<button class="btn btn-primary">Spremi</button>
</form>
<script>
(function () {
    var sp = document.querySelector('[data-spajanje]'), od = document.querySelector('[data-sle-od]'), sle = document.querySelector('[data-sle]');
    document.querySelectorAll('[data-vodjenje]').forEach(function (r) { r.addEventListener('change', function () { if (sp) sp.hidden = !document.getElementById('vod-mjesovito').checked; }); });
    sle.addEventListener('change', function () { od.hidden = !sle.checked; });
})();
</script>
<?php stranica('Odstrel – evidencija', ob_get_clean());
