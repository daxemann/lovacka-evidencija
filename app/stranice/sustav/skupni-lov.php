<?php
/** Skupni lov – izgled PDF-ova: popis sudionika i dodatni zapisnik o obavljenom lovu. */
trazi(P_SUSTAV);
if (je_post()) {
    if (($_POST['radnja'] ?? '') === 'zadano') {
        spremi_postavku('SkupniLov.Pdf', null);
    } else {
        $t = fn(string $k, int $max) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        $z = gl_pdf_postavke();
        $stupci = implode("\n", array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 60), explode("\n", str_replace("\r", '', (string) ($_POST['zapisnik_stupci'] ?? '')))))), 0, 12));
        spremi_postavku('SkupniLov.Pdf', json_encode([
            'popis_naslov' => $t('popis_naslov', 200) ?: $z['popis_naslov'],
            'ovlastenik' => $t('ovlastenik', 80) ?: $z['ovlastenik'],
            'st_iskaznica' => isset($_POST['st_iskaznica']), 'st_oruzni' => isset($_POST['st_oruzni']),
            'st_upis' => isset($_POST['st_upis']), 'st_potpis' => isset($_POST['st_potpis']),
            'potpis_voditelja' => isset($_POST['potpis_voditelja']), 'broj_po_sekciji' => isset($_POST['broj_po_sekciji']),
            'zapisnik_naslov' => $t('zapisnik_naslov', 250) ?: $z['zapisnik_naslov'],
            'zapisnik_stupci' => $stupci ?: $z['zapisnik_stupci'],
            'zapisnik_redaka' => max(1, min(200, (int) ($_POST['zapisnik_redaka'] ?? 18))),
        ], JSON_UNESCAPED_UNICODE));
    }
    dnevnik('Uređen izgled PDF-a skupnog lova');
    poruka('Spremljeno.');
    preusmjeri('sustav/skupni-lov');
}
$p = gl_pdf_postavke();
$zadnji = red('SELECT * FROM GrupniLov ORDER BY Id DESC LIMIT 1');
$kv = fn(string $k, string $t) => '<div class="form-check"><input class="form-check-input" type="checkbox" name="' . $k . '" id="' . $k . '" value="1"' . chk($p[$k]) . '><label class="form-check-label" for="' . $k . '">' . $t . '</label></div>';
ob_start(); ?>
<h1 class="h3 mb-1">🎯 Skupni lov – PDF</h1>
<p class="text-muted">Izgled popisa sudionika i dodatnog zapisnika. <b>Broj</b> je redni broj lova u lovnoj godini (od 1. travnja, 0001, 0002 …) –
    obrišete li lov, brojevi se slažu bez rupe.</p>
<form method="post" style="max-width:720px"><?= csrf() ?>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">🔢 Broj lova</h2>
    <?= $kv('broj_po_sekciji', '<b>Broj po sekciji</b> – svaka sekcija broji svoje lovove (0001, 0002 …), npr. kad svaka sekcija ima svoje dopuštenje i love istog dana') ?>
    <div class="small text-muted mt-1">Isključeno: jedan niz brojeva za cijelu udrugu. Uključeno: u PDF-u stoji npr. <i>0003 (Sjever)</i>; zajednički lov više sekcija dobiva broj u svakoj sekciji.</div>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">📋 Popis sudionika</h2>
    <label class="form-label">Naslov (iza njega ide broj)</label>
    <input class="form-control mb-2" name="popis_naslov" maxlength="200" value="<?= e($p['popis_naslov']) ?>">
    <label class="form-label">Oznaka ispod naslova (iza nje ime voditelja koji je pokrenuo lov)</label>
    <input class="form-control mb-3" name="ovlastenik" maxlength="80" value="<?= e($p['ovlastenik']) ?>">
    <div class="form-label">Stupci tablice <span class="small text-muted">(Rb. i Prezime i ime uvijek)</span></div>
    <div class="d-flex flex-wrap gap-3 mb-3">
        <?= $kv('st_iskaznica', 'Broj lovačke iskaznice') ?><?= $kv('st_oruzni', 'Oružni list') ?><?= $kv('st_upis', 'Upis (iskaznica / ručno)') ?><?= $kv('st_potpis', 'Potpis') ?>
    </div>
    <?= $kv('potpis_voditelja', 'Na dnu: Lovnik / voditelj lova + potpis (oba PDF-a)') ?>
</div></div>
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">📝 Dodatni zapisnik o obavljenom lovu</h2>
    <label class="form-label">Naslov (iza njega ide isti broj kao lov)</label>
    <input class="form-control mb-2" name="zapisnik_naslov" maxlength="250" value="<?= e($p['zapisnik_naslov']) ?>">
    <label class="form-label">Stupci – jedan po retku <span class="small text-muted">(RB uvijek prvi, najviše 12)</span></label>
    <textarea class="form-control mb-2" name="zapisnik_stupci" rows="7"><?= e($p['zapisnik_stupci']) ?></textarea>
    <label class="form-label">Broj praznih redaka</label>
    <input class="form-control" type="number" name="zapisnik_redaka" min="1" max="200" value="<?= (int) $p['zapisnik_redaka'] ?>" style="max-width:120px">
</div></div>
<div class="d-flex flex-wrap gap-2"><button class="btn btn-primary">Spremi</button>
    <button class="btn btn-outline-secondary" name="radnja" value="zadano" data-potvrda="Vratiti zadani izgled?">Zadano</button>
    <?php if ($zadnji): ?>
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(url('grupni-popis', ['id' => $zadnji['Id'], 'pdf' => 1])) ?>">👁 Probni popis</a>
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(url('grupni-popis', ['id' => $zadnji['Id'], 'pdf' => 'zapisnik'])) ?>">👁 Probni zapisnik</a>
    <?php endif; ?>
</div>
</form>
<?php stranica('Skupni lov – PDF', ob_get_clean());
