<?php
/** Novi oglas / uređivanje oglasa (vlasnik). */
$k = korisnik();
$id = ul_int('id');
$o = $id ? oglas($id) : null;
if ($id && (!$o || (int) $o['KorisnikId'] !== $k['Id'])) {
    stranica('Uredi oglas', '<h1 class="h4 mb-3">Uredi oglas</h1><div class="alert alert-warning">Ovaj oglas ne možete uređivati.</div>');
}
$slike = $id ? slike_oglasa($id) : [];
$mobitel = $k['ClanId'] ? (string) vrijednost('SELECT MobilniTelefon FROM Clanovi WHERE Id=?', [$k['ClanId']]) : '';
$v = $o ?? ['Kategorija' => 0, 'Stanje' => 2, 'Naslov' => '', 'Cijena' => null, 'PoDogovoru' => 0, 'Mjesto' => '', 'Opis' => '', 'PrikaziTelefon' => 0];

if (je_post()) {
    $naslov = mb_substr(ul_str('Naslov'), 0, 120);
    $p = [
        'Kategorija' => max(0, min(6, (int) ($_POST['Kategorija'] ?? 6))), 'Stanje' => max(0, min(3, (int) ($_POST['Stanje'] ?? 2))),
        'Naslov' => $naslov, 'Opis' => ul_null('Opis'), 'Cijena' => dec(u_broj($_POST['Cijena'] ?? '')), 'PoDogovoru' => isset($_POST['PoDogovoru']) ? 1 : 0,
        'Mjesto' => ul_null('Mjesto'), 'PrikaziTelefon' => isset($_POST['PrikaziTelefon']) ? 1 : 0, 'Azurirano' => sada(),
    ];
    if ($naslov === '') {
        poruka('Upišite naslov oglasa.', 'danger');
        preusmjeri('oglasnik/uredi', ['id' => $id]);
    }
    if ($id) {
        azuriraj('Oglasi', $id, $p);
    } else {
        $id = umetni('Oglasi', $p + ['KorisnikId' => $k['Id'], 'Status' => 0, 'Pregleda' => 0, 'Kreirano' => sada()]);
        dnevnik('Novi oglas', 'Oglas', $id, $naslov);
    }
    // slike
    $slike = slike_oglasa($id);
    if (!empty($_POST['slika_ukloni'])) {
        $s = basename((string) $_POST['slika_ukloni']);
        if (in_array($s, $slike, true)) {
            q('DELETE FROM OglasSlike WHERE OglasId=? AND Datoteka=?', [$id, $s]);
            obrisi_sliku($s);
        }
    }
    if (!empty($_POST['slika_naslovna'])) {
        $s = basename((string) $_POST['slika_naslovna']);
        $novi = array_values(array_unique(array_merge([$s], $slike)));
        foreach ($novi as $i => $d) {
            q('UPDATE OglasSlike SET Redoslijed=? WHERE OglasId=? AND Datoteka=?', [$i, $id, $d]);
        }
    }
    $f = $_FILES['slike'] ?? null;
    if ($f && is_array($f['name'])) {
        $ima = count(slike_oglasa($id));
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($ima >= MAX_SLIKA_OGLASA) {
                poruka('Najviše ' . MAX_SLIKA_OGLASA . ' slika po oglasu.', 'warning');
                break;
            }
            try {
                $ime = spremi_sliku(['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i]], 'oglas-' . $id . '-', 1600);
                umetni('OglasSlike', ['OglasId' => $id, 'Datoteka' => $ime, 'Redoslijed' => $ima++]);
            } catch (Throwable $e) {
                poruka(e($f['name'][$i] . ': ' . $e->getMessage()), 'warning');
            }
        }
    }
    if (!empty($_POST['slika_ukloni']) || !empty($_POST['slika_naslovna']) || ($_POST['ostani'] ?? '') === '1') {
        preusmjeri('oglasnik/uredi', ['id' => $id]);
    }
    poruka('Oglas je spremljen.');
    preusmjeri('oglasnik/oglas', ['id' => $id]);
}
$naslovStr = $id ? 'Uredi oglas' : 'Novi oglas';
ob_start(); ?>
<h1 class="h4 mb-3"><?= $naslovStr ?></h1>
<form method="post" action="<?= e(url('oglasnik/uredi', ['id' => $id])) ?>" enctype="multipart/form-data" style="max-width:820px"><?= csrf() ?>
<div class="card mb-3">
    <div class="card-header">Slike (najviše <?= MAX_SLIKA_OGLASA ?>) – prva je naslovna</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($slike as $i => $s): ?>
                <div class="oglas-mini"><img src="<?= e(foto_url($s)) ?>" alt="">
                    <div class="d-flex justify-content-between">
                        <?php if ($i > 0): ?><button class="btn btn-sm btn-link p-0" name="slika_naslovna" value="<?= e($s) ?>">★ naslovna</button>
                        <?php else: ?><span class="small text-success">naslovna</span><?php endif; ?>
                        <button class="btn btn-sm btn-link text-danger p-0" name="slika_ukloni" value="<?= e($s) ?>" data-potvrda="Ukloniti sliku?">✕</button>
                    </div></div>
            <?php endforeach; ?>
            <?php if (count($slike) < MAX_SLIKA_OGLASA): ?>
                <label class="oglas-mini oglas-dodaj">+ Dodaj slike
                    <input type="file" name="slike[]" accept="image/*" multiple class="d-none" id="slikeUnos"></label>
            <?php endif; ?>
        </div>
        <div class="small text-muted mt-2" id="odabraneSlike"></div>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Kategorija</label>
        <select name="Kategorija" class="form-select" id="kategorija"><?php foreach (KATEGORIJE_OGLASA as $i => $n): ?><option value="<?= $i ?>"<?= sel($i, $v['Kategorija']) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><label class="form-label">Stanje</label>
        <select name="Stanje" class="form-select"><?php foreach (STANJA_ARTIKLA as $i => $n): ?><option value="<?= $i ?>"<?= sel($i, $v['Stanje']) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div class="col-12" id="dozvola" style="<?= treba_dozvolu((int) $v['Kategorija']) ? '' : 'display:none' ?>"><div class="alert alert-warning small mb-0"><b>Oružje i streljivo:</b> prodaja samo osobi koja ima važeće odobrenje za nabavu / oružni list.
        Prijenos se obavlja prema Zakonu o nabavi i posjedovanju oružja (prijava nadležnoj policijskoj upravi).
        Udruga samo omogućuje kontakt među članovima i nije strana u kupoprodaji.</div></div>
    <div class="col-12"><label class="form-label">Naslov</label><input name="Naslov" class="form-control" maxlength="120" required value="<?= e($v['Naslov']) ?>"></div>
    <div class="col-6 col-md-4"><label class="form-label">Cijena (€)</label><input name="Cijena" type="number" step="0.01" min="0" class="form-control" value="<?= $v['Cijena'] !== null ? e((string) (float) $v['Cijena']) : '' ?>"></div>
    <div class="col-6 col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="PoDogovoru" id="dogovor" value="1"<?= chk($v['PoDogovoru']) ?>>
        <label class="form-check-label" for="dogovor">po dogovoru</label></div></div>
    <div class="col-md-4"><label class="form-label">Mjesto</label><input name="Mjesto" class="form-control" value="<?= e($v['Mjesto']) ?>"></div>
    <div class="col-12"><label class="form-label">Opis</label><textarea name="Opis" class="form-control" rows="5"><?= e($v['Opis']) ?></textarea></div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="PrikaziTelefon" id="tel" value="1"<?= chk($v['PrikaziTelefon']) ?><?= $mobitel === '' ? ' disabled' : '' ?>>
        <label class="form-check-label" for="tel">Prikaži moj broj mobitela (poziv / WhatsApp)<?= $mobitel === '' ? ' – u profilu nemate upisan broj' : ' – ' . e($mobitel) ?></label></div>
        <div class="small text-muted">Članovi vam uvijek mogu pisati porukom ovdje u aplikaciji.</div></div>
</div>
<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary"><?= $id ? 'Spremi' : 'Objavi oglas' ?></button>
    <a href="<?= e($id ? url('oglasnik/oglas', ['id' => $id]) : url('oglasnik')) ?>" class="btn btn-outline-secondary">Odustani</a>
</div>
</form>
<script>
document.getElementById('kategorija').addEventListener('change', function () { document.getElementById('dozvola').style.display = (this.value === '0' || this.value === '1') ? '' : 'none'; });
var su = document.getElementById('slikeUnos');
if (su) su.addEventListener('change', function () {
    var n = su.files.length;
    document.getElementById('odabraneSlike').textContent = n ? ('Odabrano slika: ' + n + ' – spremaju se kod spremanja oglasa.') : '';
    <?php if ($id): ?>if (n) { var h = document.createElement('input'); h.type = 'hidden'; h.name = 'ostani'; h.value = '1'; su.form.appendChild(h); su.form.submit(); }<?php endif; ?>
});
</script>
<?php stranica($naslovStr, ob_get_clean());
