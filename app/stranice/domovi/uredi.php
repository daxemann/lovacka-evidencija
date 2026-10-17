<?php
/** Novi / uređivanje lovačkog doma: naziv, sekcija (ili cijela udruga), opis, kapacitet, kontakt, fotografija, položaj na karti. */
$sekcije = dom_sekcije_za_uredjivanje();
if (!$sekcije) {
    zabranjeno();
}
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$d = $id ? red('SELECT * FROM LovackiDomovi WHERE Id=?', [$id]) : null;
if ($id && (!$d || !dom_upravlja($d))) {
    zabranjeno();
}

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    if ($radnja === 'obrisi' && $d) {
        $buduce = (int) vrijednost("SELECT COUNT(*) FROM DomRezervacije WHERE DomId=? AND Status IN ('ceka','odobreno') AND Do>=?", [$id, danas()]);
        if ($buduce) {
            poruka("Dom ima $buduce nadolazećih rezervacija – najprije ih otkažite ili dom označite kao „nije u upotrebi“.", 'warning');
            preusmjeri('domovi/uredi', ['id' => $id]);
        }
        obrisi_sliku($d['Foto']);
        q('DELETE FROM LovackiDomovi WHERE Id=?', [$id]);
        dnevnik('Lovački dom – obrisan', 'LovackiDom', $id, $d['Naziv']);
        poruka('Lovački dom je obrisan.');
        preusmjeri('domovi');
    }
    $naziv = mb_substr(ul_str('Naziv'), 0, 80);
    $sid = (int) ($_POST['SekcijaId'] ?? -1);
    $greska = null;
    if ($naziv === '') {
        $greska = 'Upišite naziv doma.';
    } elseif (!array_key_exists($sid, $sekcije)) {
        $greska = 'Odaberite sekciju (ili cijelu udrugu) za koju imate pravo.';
    }
    $koord = null;
    if (ul_str('Lat') !== '' || ul_str('Lon') !== '') {
        $koord = revir_koord(u_broj(ul_str('Lat')), u_broj(ul_str('Lon')));
        if (!$koord) {
            $greska ??= 'Položaj nije ispravan – dodirnite kartu ili upišite npr. 45.8312 i 17.3851.';
        }
    }
    $foto = $d['Foto'] ?? null;
    if (!$greska && !empty($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $novo = spremi_sliku($_FILES['foto'], 'dom-', 1600);
            obrisi_sliku($foto);
            $foto = $novo;
        } catch (RuntimeException $e) {
            $greska = $e->getMessage();
        }
    }
    if (!$greska && !empty($_POST['bez_foto'])) {
        obrisi_sliku($foto);
        $foto = null;
    }
    if ($greska) {
        poruka(e($greska), 'danger');
        $_SESSION['dom_uredi'] = $_POST;
        preusmjeri('domovi/uredi', array_filter(['id' => $id ?: null]));
    }
    $kap = ul_int('Kapacitet');
    $polja = [
        'Naziv' => $naziv, 'SekcijaId' => $sid === 0 ? null : $sid,
        'Opis' => mb_substr(trim((string) ($_POST['Opis'] ?? '')), 0, 2000) ?: null,
        'Kapacitet' => $kap && $kap > 0 ? min($kap, 10000) : null,
        'Kontakt' => mb_substr(ul_str('Kontakt'), 0, 120) ?: null,
        'Lat' => $koord[0] ?? null, 'Lon' => $koord[1] ?? null, 'Foto' => $foto,
        'Aktivan' => isset($_POST['Aktivan']) ? 1 : 0, 'Azurirano' => sada(),
    ];
    if ($d) {
        azuriraj('LovackiDomovi', $id, $polja);
        dnevnik('Lovački dom – uređen', 'LovackiDom', $id, $naziv);
    } else {
        $id = umetni('LovackiDomovi', $polja + ['Redoslijed' => 0, 'Kreirano' => sada()]);
        dnevnik('Lovački dom – novi', 'LovackiDom', $id, $naziv);
    }
    poruka('Spremljeno.');
    preusmjeri('domovi/dom', ['id' => $id]);
}

$v = $_SESSION['dom_uredi'] ?? $d ?? ['Aktivan' => 1];
unset($_SESSION['dom_uredi']);
$sidOdabran = isset($v['SekcijaId']) && $v['SekcijaId'] !== null && $v['SekcijaId'] !== '' ? (int) $v['SekcijaId'] : ($d ? 0 : array_key_first($sekcije));
$cfg = ['karta' => 'dom-karta', 'lat' => 'dom-lat', 'lon' => 'dom-lon', 'granice' => revir_granice(), 'centar' => revir_centar()];

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e($d ? url('domovi/dom', ['id' => $id]) : url('domovi')) ?>">‹</a>
    <h1 class="h3 mb-0"><?= $d ? 'Uredi lovački dom' : 'Novi lovački dom' ?></h1>
</div>
<form method="post" enctype="multipart/form-data" class="row g-3" style="max-width:900px"><?= csrf() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="col-md-7"><label class="form-label">Naziv *</label><input name="Naziv" class="form-control" maxlength="80" required value="<?= e($v['Naziv'] ?? '') ?>" placeholder="npr. Lovačka kuća Bukovica"></div>
    <div class="col-md-5"><label class="form-label">Za koga *</label>
        <select name="SekcijaId" class="form-select">
            <?php foreach ($sekcije as $sId => $n): ?><option value="<?= (int) $sId ?>"<?= sel($sId, $sidOdabran) ?>><?= e($n) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">Dom sekcije vide i rezerviraju samo članovi te sekcije.</div></div>
    <div class="col-12"><label class="form-label">Opis</label><textarea name="Opis" class="form-control" rows="3" maxlength="2000" placeholder="oprema, kuhinja, roštilj, struja, voda, pravila korištenja…"><?= e($v['Opis'] ?? '') ?></textarea></div>
    <div class="col-md-3"><label class="form-label">Kapacitet (osoba)</label><input name="Kapacitet" type="number" min="1" class="form-control" value="<?= e($v['Kapacitet'] ?? '') ?>"></div>
    <div class="col-md-9"><label class="form-label">Ključ / kontakt</label><input name="Kontakt" class="form-control" maxlength="120" value="<?= e($v['Kontakt'] ?? '') ?>" placeholder="npr. ključ kod domara, 091 234 5678"></div>
    <div class="col-md-7"><label class="form-label">Fotografija</label><input type="file" name="foto" accept="image/*" class="form-control">
        <?php if (!empty($d['Foto'])): ?><div class="d-flex align-items-center gap-2 mt-2"><img src="<?= e(foto_url($d['Foto'])) ?>" class="dom-foto-mala" alt="">
            <label class="form-check small"><input type="checkbox" name="bez_foto" value="1" class="form-check-input"> ukloni fotografiju</label></div><?php endif; ?></div>
    <div class="col-md-5 d-flex align-items-end"><label class="form-check"><input type="checkbox" name="Aktivan" value="1" class="form-check-input"<?= chk($v['Aktivan'] ?? 1) ?>> U upotrebi (može se rezervirati)</label></div>

    <div class="col-12">
        <label class="form-label mb-1">Položaj (za dolazak – gumb 🧭 Navigacija)</label>
        <div class="small text-muted mb-2">Dodirnite kartu ili povucite pin na mjesto doma. Na licu mjesta: <b>📍 Moja pozicija</b>.</div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <input id="dom-lat" name="Lat" class="form-control form-control-sm" style="max-width:150px" placeholder="širina (lat)" value="<?= e($v['Lat'] ?? '') ?>" inputmode="decimal">
            <input id="dom-lon" name="Lon" class="form-control form-control-sm" style="max-width:150px" placeholder="dužina (lon)" value="<?= e($v['Lon'] ?? '') ?>" inputmode="decimal">
            <button type="button" class="btn btn-sm btn-outline-success" id="dom-ovdje">📍 Moja pozicija</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="dom-bez">Bez položaja</button>
        </div>
        <div id="dom-karta" class="dom-karta"></div>
    </div>
    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Spremi</button>
        <?php if ($d): ?><button class="btn btn-outline-danger ms-auto" name="radnja" value="obrisi" formnovalidate data-potvrda="Obrisati lovački dom i sve njegove prošle rezervacije?">🗑 Obriši dom</button><?php endif; ?>
    </div>
</form>
<link rel="stylesheet" href="<?= e(asset('assets/leaflet/leaflet.css')) ?>">
<script id="dom-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(asset('assets/domovi.js')) ?>"></script>
<?php stranica($d ? 'Uredi lovački dom' : 'Novi lovački dom', ob_get_clean());
