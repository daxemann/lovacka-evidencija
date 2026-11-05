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
    spremi_postavku('Odstrel.Markice', isset($_POST['markice']) ? '1' : null);
    // vrste divljači: naziv, kategorije (zarezom), markica, aktivna; prazan naziv = red se briše
    $vrste = [];
    $vidjeno = [];
    foreach ((array) ($_POST['vrste'] ?? []) as $r) {
        $n = mb_substr(trim((string) ($r['naziv'] ?? '')), 0, 60);
        if ($n === '' || isset($vidjeno[mb_strtolower($n)])) {
            continue;
        }
        $vidjeno[mb_strtolower($n)] = true;
        $kat = array_slice(array_values(array_unique(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 30), explode(',', (string) ($r['kategorije'] ?? ''))), 'strlen'))), 0, 12);
        $vrste[] = ['naziv' => $n, 'kategorije' => $kat, 'markica' => !empty($r['markica']), 'aktivna' => !empty($r['aktivna'])];
    }
    if (isset($_POST['vrste_zadano'])) {
        spremi_postavku('Odstrel.Vrste', null);
    } elseif ($vrste) {
        spremi_postavku('Odstrel.Vrste', json_encode($vrste, JSON_UNESCAPED_UNICODE));
    }
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
    <h2 class="h5">🦌 Vrste divljači</h2>
    <p class="small text-muted">Druga područja – druge vrste: dodajte svoje, a one kojih u lovištu nema isključite (<i>aktivna</i>). Kategorije (spol / dob) odvojite zarezom.
        <i>Markica</i> = vrsta s evidencijskom markicom (krupna divljač) – nudi se u zalihi markica. Prazan naziv briše red.</p>
    <div class="table-responsive"><table class="table table-sm align-middle mb-2">
        <thead><tr><th>Vrsta</th><th>Kategorije</th><th class="text-center">Markica</th><th class="text-center">Aktivna</th></tr></thead><tbody>
        <?php $vp = odstrel_vrste_popis(); foreach (array_merge($vp, array_fill(0, 3, ['naziv' => '', 'kategorije' => [], 'markica' => false, 'aktivna' => true])) as $i => $vr): ?>
        <tr><td><input class="form-control form-control-sm" name="vrste[<?= $i ?>][naziv]" maxlength="60" value="<?= e($vr['naziv']) ?>"<?= $vr['naziv'] === '' ? ' placeholder="+ nova vrsta"' : '' ?>></td>
            <td><input class="form-control form-control-sm" name="vrste[<?= $i ?>][kategorije]" value="<?= e(implode(', ', $vr['kategorije'])) ?>" placeholder="npr. Mužjak, Ženka, Mlado"></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="vrste[<?= $i ?>][markica]" value="1"<?= chk($vr['markica']) ?>></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="vrste[<?= $i ?>][aktivna]" value="1"<?= chk($vr['aktivna']) ?>></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="vrste_zadano" id="vrste-zadano" value="1">
        <label class="form-check-label small" for="vrste-zadano">Vrati zadani popis vrsta</label></div>
    <div class="small text-muted mt-1">Već upisani odstrel i plan zadržavaju naziv vrste i kad je vrsta isključena ili preimenovana.</div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">🏷️ Zaliha markica</h2>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="markice" id="markice" value="1"<?= chk(odstrel_markice_ukljucene()) ?>>
        <label class="form-check-label" for="markice"><b>Vodi zalihu markica</b> – originalni brojevi s izdatnice po evidenciji i lovnoj godini, zaduženje lovcu, povrat Ministarstvu, PDF (PPD-3)</label></div>
    <div class="small text-muted mt-1">Kod upisa odstrela nude se slobodne markice; s upisom odstrela markica je sama <i>iskorištena</i>. Tko vodi zalihu: pravo <b>Odstrel – markice</b>.</div>
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
