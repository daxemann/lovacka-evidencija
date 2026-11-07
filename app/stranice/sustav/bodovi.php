<?php
/** 🏅 Bodovi: dan resetiranja (početak bodovne godine) i pravilo prijenosa bodova u novu godinu. */
trazi(P_SUSTAV);
if (je_post()) {
    $dan = bodovi_dan_unos(ul_str('Pocetak'));
    if (!$dan) {
        poruka('Dan resetiranja nije ispravan – upišite dan.mjesec. (npr. 1.4.).', 'warning');
        preusmjeri('sustav/bodovi');
    }
    $nacin = isset(BODOVI_PRIJENOS[$_POST['Prijenos'] ?? '']) ? (string) $_POST['Prijenos'] : 'nista';
    $broj = max(0.0, (float) (u_broj($_POST['PrijenosBroj'] ?? '') ?? 0));
    $auto = isset($_POST['Auto']) && $nacin !== 'nista';
    $bioAuto = bodovi_prijenos_pravilo()['auto'];
    spremi_postavku('Bodovi.Pocetak', $dan === '04-01' ? null : $dan);
    spremi_postavku('Bodovi.Prijenos', $nacin === 'nista' ? null : $nacin);
    spremi_postavku('Bodovi.PrijenosBroj', in_array($nacin, ['max', 'fiksno'], true) ? dec($broj) : null);
    spremi_postavku('Bodovi.PrijenosAuto', $auto ? '1' : null);
    if ($auto && !$bioAuto) {
        // automatski tek od sljedeće bodovne godine – za tekuću je ručni prijenos
        spremi_postavku('Bodovi.Preneseno', (string) bodovna_godina());
    }
    dnevnik('Bodovi – postavke', null, null, 'reset ' . bodovi_dan_prikaz($dan) . ', prijenos: ' . BODOVI_PRIJENOS[$nacin] . (in_array($nacin, ['max', 'fiksno'], true) ? ' ' . broj($broj, 2) : '') . ($auto ? ', automatski' : ''));
    poruka('Spremljeno.');
    preusmjeri('sustav/bodovi');
}
$pr = bodovi_prijenos_pravilo();
$bg = bodovna_godina();
[$od, $do] = bodovna_godina_raspon($bg);
$fun = redovi("SELECT Naziv, Bodovi, BodoviDatum FROM Funkcije WHERE BodoviDatum IS NOT NULL AND CAST(COALESCE(Bodovi,'0') AS REAL) > 0 ORDER BY Redoslijed, Naziv");
ob_start(); ?>
<h1 class="h3 mb-1">🏅 Bodovi</h1>
<p class="text-muted" style="max-width:760px">Tekuća bodovna godina: <b><?= e(bodovna_godina_naziv($bg)) ?></b> (<?= e(datum($od)) ?> – <?= e(datum($do)) ?>).
    <a class="ms-2" href="<?= e(url('akcije/bodovi')) ?>">📚 Arhiva bodova</a> · <a href="<?= e(url('akcije/prijenos')) ?>">↪️ Prijenos u novu godinu</a></p>
<form method="post" class="card mb-4" style="max-width:760px"><?= csrf() ?>
    <div class="card-body">
        <div class="mb-4">
            <label class="form-label fw-semibold">📅 Dan resetiranja bodova</label>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input name="Pocetak" class="form-control" style="width:8rem" value="<?= e(bodovi_dan_prikaz(bodovi_dan())) ?>" placeholder="dan.mjesec." required>
                <?php foreach (['1.4.' => 'lovna godina', '1.1.' => 'kalendarska', '1.8.' => '1. kolovoza'] as $v => $n): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.form.Pocetak.value='<?= $v ?>'"><?= $v ?> <span class="text-muted"><?= $n ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="form-text">Od tog dana bodovi kreću ispočetka (stari ostaju u arhivi).</div>
        </div>

        <label class="form-label fw-semibold">↪️ Prijenos bodova iz prošle godine</label>
        <?php foreach (BODOVI_PRIJENOS as $v => $n): ?>
            <div class="form-check d-flex flex-wrap align-items-center gap-2 mb-1">
                <input class="form-check-input" type="radio" name="Prijenos" id="pr-<?= $v ?>" value="<?= $v ?>"<?= chk($pr['nacin'] === $v) ?>>
                <label class="form-check-label" for="pr-<?= $v ?>"><?= e($n) ?></label>
                <?php if ($v === 'max' || $v === 'fiksno'): ?>
                    <input type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" style="width:6rem" data-za="<?= $v ?>"
                           value="<?= $pr['nacin'] === $v ? e((string) $pr['broj']) : '' ?>" placeholder="bodova" onfocus="document.getElementById('pr-<?= $v ?>').checked=true"> <span class="small text-muted">bodova</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <input type="hidden" name="PrijenosBroj" id="PrijenosBroj">
        <div class="form-text mb-2">Primjer „fiksni iznos 10“: tko je imao 10 ili više bodova, prenosi 10 – tko je imao manje, kreće od 0.</div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="Auto" id="auto" value="1"<?= chk($pr['auto']) ?>>
            <label class="form-check-label" for="auto">Prenesi automatski na dan resetiranja</label>
            <div class="form-text">Bez kvačice prenosite ručno (<a href="<?= e(url('akcije/prijenos')) ?>">Prijenos u novu godinu</a>) i možete svakom članu promijeniti iznos.</div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<script>document.currentScript.previousElementSibling.addEventListener('submit', function () {
    var c = this.querySelector('[name=Prijenos]:checked'), i = c ? this.querySelector('[data-za="' + c.value + '"]') : null;
    document.getElementById('PrijenosBroj').value = i ? i.value : '';
});</script>

<div class="card" style="max-width:760px">
    <div class="card-header">🏅 Bodovi za funkciju (jednom godišnje, odmah odobreno)</div>
    <div class="card-body">
        <?php if ($fun): ?><ul class="mb-2"><?php foreach ($fun as $f): ?><li><?= e($f['Naziv']) ?>: <b><?= e(broj((float) $f['Bodovi'], 2)) ?></b> b. svake godine <?= e(bodovi_dan_prikaz($f['BodoviDatum'])) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="text-muted mb-2">Nijedna funkcija nema bodove.</p><?php endif; ?>
        <a href="<?= e(url('sustav/uloge')) ?>#funkcije">Uredi u Uloge i prava → Funkcije u udruzi →</a>
    </div>
</div>
<?php stranica('Bodovi', ob_get_clean());
