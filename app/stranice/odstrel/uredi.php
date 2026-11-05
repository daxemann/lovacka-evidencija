<?php
/** 🦌 Upis / izmjena odstrela (pravo „Odstrel – upis“ za evidenciju sekcije). Sekcija upisa = stvarna sekcija (lovca / mjesta odstrela). */
$k = korisnik();
$uredive = odstrel_upis_sekcije();
$poljaObr = odstrel_polja();
$sle = odstrel_sle();
if (!$uredive) {
    zabranjeno();
}
$o = null;
if ($id = (int) ul('id', 0)) {
    $o = odstrel_red($id) ?? nije_pronadjeno('Upis');
    if (!odstrel_moze_urediti($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null)) {
        zabranjeno();
    }
}
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    if ($radnja === 'obrisi' && $o) {
        odstrel_markica_povezi((int) $o['Id'], $o['Markica'], null); // markica iz zalihe opet slobodna
        obrisi_sliku($o['Foto']);
        q('DELETE FROM Odstrel WHERE Id=?', [$o['Id']]);
        dnevnik('Odstrel obrisan', 'Odstrel', (int) $o['Id'], $o['Vrsta'] . ($o['Markica'] ? ', markica ' . $o['Markica'] : ''));
        poruka('Upis obrisan.');
        preusmjeri('odstrel', ['g' => $o['LovnaGodina']]);
    }
    $sid = (int) ($_POST['sekcija'] ?? 0);
    if (!array_key_exists($sid, $uredive)) {
        poruka('Nemate pravo upisa za tu sekciju.', 'danger');
        preusmjeri('odstrel/uredi', $o ? ['id' => $o['Id']] : []);
    }
    $datum = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['datum'] ?? '')) ? $_POST['datum'] : danas();
    $vrsta = mb_substr(ul_str('vrsta'), 0, 60);
    if ($vrsta === '') {
        poruka('Upišite vrstu divljači.', 'danger');
        preusmjeri('odstrel/uredi', $o ? ['id' => $o['Id']] : []);
    }
    // lovac: član sekcije ili gost (upisano ime)
    $clanId = (int) ($_POST['clan'] ?? 0);
    $gost = $clanId === 0;
    $lovac = $gost ? mb_substr(ul_str('gost'), 0, 80) : puno_ime(clan($clanId));
    if ($lovac === '') {
        poruka('Odaberite lovca ili upišite ime gosta.', 'danger');
        preusmjeri('odstrel/uredi', $o ? ['id' => $o['Id']] : []);
    }
    $broj = fn(string $kl, int $dec = 1) => is_numeric(str_replace(',', '.', ul_str($kl))) ? round((float) str_replace(',', '.', ul_str($kl)), $dec) : null;
    $naprava = (int) ($_POST['naprava'] ?? 0);
    $polja = [
        'SekcijaId' => $sid ?: null, 'LovnaGodina' => odstrel_lovna_godina($datum), 'Datum' => $datum,
        'Vrijeme' => preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['vrijeme'] ?? '')) ? $_POST['vrijeme'] : null,
        'Vrsta' => $vrsta, 'Spol' => mb_substr(ul_str('spol'), 0, 30) ?: null, 'Dob' => mb_substr(ul_str('dob'), 0, 30) ?: null,
        'Masa' => $broj('masa'), 'Markica' => mb_substr(ul_str('markica'), 0, 40) ?: null,
        'ClanId' => $gost ? null : $clanId, 'Lovac' => $lovac, 'Gost' => $gost ? 1 : 0,
        'NapravaId' => $naprava && vrijednost('SELECT 1 FROM RevirNaprave WHERE Id=?', [$naprava]) ? $naprava : null,
        'Mjesto' => mb_substr(ul_str('mjesto'), 0, 120) ?: null,
        'Oruzje' => mb_substr(ul_str('oruzje'), 0, 80) ?: null, 'Kalibar' => mb_substr(ul_str('kalibar'), 0, 30) ?: null,
        'Udaljenost' => ($u = $broj('udaljenost', 0)) !== null ? (int) $u : null, 'Trofej' => $broj('trofej', 2),
        'Napomena' => mb_substr(ul_str('napomena'), 0, 500) ?: null, 'Azurirano' => sada(),
    ];
    // podaci za obrasce – samo uključena polja (isključena ostaju kakva jesu)
    foreach (['dopustenje' => ['BrojDopustenja', 'br_dopustenja'], 'zapisnik' => ['BrojZapisnika', 'br_zapisnika'], 'potvrda' => ['BrojPotvrde', 'br_potvrde']] as $pk => [$st, $ime]) {
        if (in_array($pk, $poljaObr, true)) {
            $polja[$st] = mb_substr(ul_str($ime), 0, 40) ?: null;
        }
    }
    if (in_array('pregled', $poljaObr, true)) {
        $polja['PregledOsoba'] = mb_substr(ul_str('pregled_osoba'), 0, 80) ?: null;
        $polja['PregledNalaz'] = mb_substr(ul_str('pregled_nalaz'), 0, 200) ?: null;
    }
    if ($sle) {
        $polja['SleDatum'] = isset($_POST['sle']) ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['sle_datum'] ?? '')) ? $_POST['sle_datum'] : danas()) : null;
    }
    // ista markica dvaput u istoj godini i sekciji – upozorenje (ne zabrana: neke vrste nemaju markicu, mogući su ispravci)
    if ($polja['Markica'] && vrijednost('SELECT 1 FROM Odstrel WHERE Markica=? AND LovnaGodina=? AND ' . odstrel_sek_sql([odstrel_grupa($sid ?: null) => true]) . ' AND Id<>?',
            [$polja['Markica'], $polja['LovnaGodina'], (int) ($o['Id'] ?? 0)])) {
        poruka('Pažnja: markica <b>' . e($polja['Markica']) . '</b> je u ovoj lovnoj godini već upisana – provjerite.', 'warning');
    }
    $f = $_FILES['foto'] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $polja['Foto'] = spremi_sliku($f, 'odstrel-', 1600);
            if ($o) {
                obrisi_sliku($o['Foto']);
            }
        } catch (RuntimeException $e) {
            poruka(e($e->getMessage()), 'danger');
        }
    } elseif ($o && isset($_POST['obrisiFoto'])) {
        obrisi_sliku($o['Foto']);
        $polja['Foto'] = null;
    }
    if ($o) {
        azuriraj('Odstrel', (int) $o['Id'], $polja);
        $nid = (int) $o['Id'];
        dnevnik('Odstrel izmijenjen', 'Odstrel', $nid, $vrsta . ($polja['Markica'] ? ', markica ' . $polja['Markica'] : ''));
    } else {
        $nid = umetni('Odstrel', $polja + ['Upisao' => $k['Naziv'], 'Kreirano' => sada()]);
        dnevnik('Odstrel upisan', 'Odstrel', $nid, $vrsta . ($polja['Markica'] ? ', markica ' . $polja['Markica'] : ''));
    }
    if ($upoz = odstrel_markica_povezi($nid, $o['Markica'] ?? null, $polja)) {
        poruka($upoz, 'warning');
    }
    poruka('Spremljeno: ' . e($vrsta) . ($polja['Markica'] ? ', markica ' . e($polja['Markica']) : '') . '.');
    preusmjeri(isset($_POST['jos']) ? 'odstrel/uredi' : 'odstrel', isset($_POST['jos']) ? ['s' => $sid, 'datum' => $datum] : ['g' => $polja['LovnaGodina']]);
}

// zadane vrijednosti
$sid = $o ? odstrel_sid($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null) : (int) ul('s', '');
if (!array_key_exists($sid, $uredive)) {
    $moj = $k['ClanId'] ? clan((int) $k['ClanId']) : null;
    $sid = $moj && array_key_exists((int) $moj['SekcijaId'], $uredive) ? (int) $moj['SekcijaId'] : (int) array_key_first($uredive);
}
$v = $o ?? ['Datum' => (string) ul('datum', danas()), 'Vrijeme' => '', 'Vrsta' => '', 'Spol' => '', 'Dob' => '', 'Masa' => null, 'Markica' => '', 'ClanId' => $k['ClanId'], 'Lovac' => '',
    'Gost' => 0, 'NapravaId' => null, 'Mjesto' => '', 'Oruzje' => '', 'Kalibar' => '', 'Udaljenost' => null, 'Trofej' => null, 'Napomena' => '', 'Foto' => null,
    'BrojDopustenja' => '', 'BrojZapisnika' => '', 'BrojPotvrde' => '', 'PregledOsoba' => '', 'PregledNalaz' => '', 'SleDatum' => null];
$clanoviPoSek = [];
foreach ($uredive as $id => $n) {
    $clanoviPoSek[$id] = array_map(fn($c) => ['id' => (int) $c['Id'], 'n' => trim($c['Prezime'] . ' ' . $c['Ime'])], odstrel_clanovi($id ?: null));
}
$napravePoSek = [];
if (modul('revir')) {
    foreach (redovi('SELECT Id, Broj, Naziv, SekcijaId FROM RevirNaprave WHERE Aktivna=1 ORDER BY CAST(Broj AS INTEGER), Broj, Naziv') as $n) {
        $napravePoSek[odstrel_sid($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null)][] = ['id' => (int) $n['Id'], 'n' => ($n['Broj'] ? $n['Broj'] . ' · ' : '') . $n['Naziv']];
    }
}
$oruzje = array_column(redovi('SELECT DISTINCT Oruzje FROM ClanOruzniListovi WHERE Oruzje IS NOT NULL AND Oruzje<>\'\' UNION SELECT DISTINCT Oruzje FROM Odstrel WHERE Oruzje IS NOT NULL'), 'Oruzje');
$kalibri = array_column(redovi('SELECT DISTINCT Kalibar FROM Odstrel WHERE Kalibar IS NOT NULL ORDER BY Kalibar'), 'Kalibar');
$cfg = ['vrste' => odstrel_vrste(), 'grupe' => array_combine(array_map('strval', array_keys($uredive)), array_map(fn($x) => odstrel_grupa($x ?: null), array_keys($uredive))),
    'markice' => odstrel_slobodne_markice(odstrel_sekcije(true), odstrel_lovna_godina((string) $v['Datum'])), 'markicaSad' => (string) $v['Markica'], 'clanovi' => $clanoviPoSek, 'naprave' => $napravePoSek, 'clan' => $v['ClanId'] !== null ? (int) $v['ClanId'] : 0, 'naprava' => (int) $v['NapravaId'], 'spol' => (string) $v['Spol']];
ob_start(); ?>
<div class="d-flex align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('odstrel')) ?>">← Odstrel</a>
    <h1 class="h4 mb-0"><?= $o ? '✏️ Izmjena odstrela' : '➕ Upis odstrela' ?></h1>
</div>
<form method="post" enctype="multipart/form-data" class="card" style="max-width:820px" id="odstrel-obrazac"><?= csrf() ?>
<div class="card-body">
    <div class="row g-2">
        <?php if (count($uredive) > 1): ?>
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Sekcija *</label>
            <select name="sekcija" class="form-select" data-od-sekcija><?php foreach ($uredive as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel($id, $sid) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <?php else: ?><input type="hidden" name="sekcija" value="<?= (int) $sid ?>" data-od-sekcija><?php endif; ?>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Datum *</label><input type="date" name="datum" class="form-control" required value="<?= e($v['Datum']) ?>" max="<?= e(danas()) ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Vrijeme</label><input type="time" name="vrijeme" class="form-control" value="<?= e((string) $v['Vrijeme']) ?>"></div>

        <div class="col-12"><hr class="my-2"><b class="small text-uppercase text-muted">Divljač</b></div>
        <div class="col-12 col-md-5"><label class="form-label small mb-0">Vrsta *</label>
            <input name="vrsta" class="form-control" required list="od-vrste" autocomplete="off" value="<?= e($v['Vrsta']) ?>" data-od-vrsta placeholder="npr. Divlja svinja">
            <datalist id="od-vrste"><?php foreach (array_keys(odstrel_vrste()) as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Spol / kategorija</label>
            <select name="spol" class="form-select" data-od-spol><option value=""></option></select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Dob</label>
            <select name="dob" class="form-select"><option value=""></option><?php foreach (ODSTREL_DOBI as $x): ?><option<?= sel($x, $v['Dob']) ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Broj markice</label>
            <input name="markica" class="form-control fw-semibold" maxlength="40" value="<?= e((string) $v['Markica']) ?>" placeholder="npr. HR-DS-001234" list="od-markice" autocomplete="off" data-od-markica>
            <datalist id="od-markice"></datalist>
            <?php if (odstrel_markice_ukljucene()): ?><div class="small text-muted" data-od-markice-info></div><?php endif; ?></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Masa (kg)</label>
            <input name="masa" class="form-control" inputmode="decimal" value="<?= e($v['Masa'] !== null ? broj((float) $v['Masa'], 1) : '') ?>" placeholder="u koži / perju"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Trofej (CIC)</label>
            <input name="trofej" class="form-control" inputmode="decimal" value="<?= e($v['Trofej'] !== null ? broj((float) $v['Trofej'], 2) : '') ?>" placeholder="neobavezno"></div>

        <div class="col-12"><hr class="my-2"><b class="small text-uppercase text-muted">Lovac i mjesto</b></div>
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Lovac *</label>
            <select name="clan" class="form-select" data-od-clan></select></div>
        <div class="col-12 col-md-6" data-od-gost-polje hidden><label class="form-label small mb-0">Ime i prezime gosta *</label>
            <input name="gost" class="form-control" maxlength="80" value="<?= e($v['Gost'] ? $v['Lovac'] : '') ?>"></div>
        <?php if (modul('revir')): ?>
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Lovna naprava (čeka)</label>
            <select name="naprava" class="form-select" data-od-naprava></select></div>
        <?php endif; ?>
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Mjesto (opis)</label>
            <input name="mjesto" class="form-control" maxlength="120" value="<?= e((string) $v['Mjesto']) ?>" placeholder="npr. Hrastova greda, uz kukuruz"></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Oružje</label>
            <input name="oruzje" class="form-control" maxlength="80" list="od-oruzje" value="<?= e((string) $v['Oruzje']) ?>">
            <datalist id="od-oruzje"><?php foreach ($oruzje as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Kalibar</label>
            <input name="kalibar" class="form-control" maxlength="30" list="od-kalibri" value="<?= e((string) $v['Kalibar']) ?>" placeholder="npr. .308 Win">
            <datalist id="od-kalibri"><?php foreach ($kalibri as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Udaljenost (m)</label>
            <input name="udaljenost" class="form-control" inputmode="numeric" value="<?= e($v['Udaljenost'] !== null ? (string) $v['Udaljenost'] : '') ?>"></div>

        <?php if ($poljaObr || $sle): ?>
        <div class="col-12"><hr class="my-2"><b class="small text-uppercase text-muted">Obrasci<?= $sle ? ' i SLE' : '' ?></b>
            <div class="small text-muted">Samo za evidenciju udruge – ne zamjenjuje SLE ni propisane obrasce.</div></div>
        <?php if (in_array('dopustenje', $poljaObr, true)): ?>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Broj dopuštenja za lov</label>
            <input name="br_dopustenja" class="form-control" maxlength="40" value="<?= e((string) $v['BrojDopustenja']) ?>"></div>
        <?php endif; if (in_array('zapisnik', $poljaObr, true)): ?>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Broj zapisnika o obavljenom lovu</label>
            <input name="br_zapisnika" class="form-control" maxlength="40" value="<?= e((string) $v['BrojZapisnika']) ?>"></div>
        <?php endif; if (in_array('potvrda', $poljaObr, true)): ?>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Broj potvrde o podrijetlu</label>
            <input name="br_potvrde" class="form-control" maxlength="40" value="<?= e((string) $v['BrojPotvrde']) ?>" placeholder="npr. A 0000123"></div>
        <?php endif; if (in_array('pregled', $poljaObr, true)): ?>
        <div class="col-12 col-md-5"><label class="form-label small mb-0">Prvi pregled – pregledao</label>
            <input name="pregled_osoba" class="form-control" maxlength="80" list="od-pregledali" value="<?= e((string) $v['PregledOsoba']) ?>" placeholder="osposobljena osoba">
            <datalist id="od-pregledali"><?php foreach (array_column(redovi('SELECT DISTINCT PregledOsoba FROM Odstrel WHERE PregledOsoba IS NOT NULL ORDER BY PregledOsoba LIMIT 50'), 'PregledOsoba') as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-12 col-md-7"><label class="form-label small mb-0">Nalaz pregleda</label>
            <input name="pregled_nalaz" class="form-control" maxlength="200" list="od-nalazi" value="<?= e((string) $v['PregledNalaz']) ?>">
            <datalist id="od-nalazi"><option value="Bez promjena"><option value="Uočene promjene – upućeno veterinaru"></datalist></div>
        <?php endif; if ($sle): ?>
        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="sle" id="od-sle" value="1"<?= chk((bool) $v['SleDatum']) ?>>
                <label class="form-check-label" for="od-sle"><b>Upisano u SLE</b> <span class="small text-muted">(rok 7 dana od odstrela)</span></label></div>
            <input type="date" name="sle_datum" class="form-control form-control-sm" style="max-width:11rem" value="<?= e((string) ($v['SleDatum'] ?: danas())) ?>" max="<?= e(danas()) ?>">
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="col-12"><label class="form-label small mb-0">Napomena</label>
            <textarea name="napomena" class="form-control" rows="2" maxlength="500" placeholder="pogodak, potraga, pas…"><?= e((string) $v['Napomena']) ?></textarea></div>
        <div class="col-12"><label class="form-label small mb-0">Fotografija (trofej, markica)</label>
            <input type="file" name="foto" accept="image/*" class="form-control" data-smanji="1600">
            <?php if ($v['Foto']): ?><div class="mt-1 d-flex align-items-center gap-2"><img src="<?= e(foto_url($v['Foto'])) ?>" alt="" style="height:60px;border-radius:6px">
                <label class="small"><input type="checkbox" name="obrisiFoto" value="1"> obriši</label></div><?php endif; ?></div>
    </div>
</div>
<div class="card-footer d-flex flex-wrap gap-2">
    <button class="btn btn-success">💾 Spremi</button>
    <?php if (!$o): ?><button class="btn btn-outline-success" name="jos" value="1">💾 Spremi i upiši još jedan</button><?php endif; ?>
    <?php if ($o): ?><button class="btn btn-outline-danger ms-auto" name="radnja" value="obrisi" data-potvrda="Obrisati ovaj upis odstrela?" formnovalidate>🗑 Obriši</button><?php endif; ?>
</div>
</form>
<?php if ($o): ?><p class="small text-muted mt-2">Upisao <?= e((string) $o['Upisao']) ?>, <?= e(datum_vrijeme($o['Kreirano'])) ?><?= $o['Azurirano'] && $o['Azurirano'] !== $o['Kreirano'] ? ' · izmijenjeno ' . e(datum_vrijeme($o['Azurirano'])) : '' ?></p><?php endif; ?>
<script id="odstrel-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var cfg = JSON.parse(document.getElementById('odstrel-cfg').textContent), f = document.getElementById('odstrel-obrazac');
    var sekEl = f.querySelector('[data-od-sekcija]'), vrstaEl = f.querySelector('[data-od-vrsta]'), spolEl = f.querySelector('[data-od-spol]');
    var clanEl = f.querySelector('[data-od-clan]'), napEl = f.querySelector('[data-od-naprava]'), gostPolje = f.querySelector('[data-od-gost-polje]');
    function opt(v, t, s) { var o = document.createElement('option'); o.value = v; o.textContent = t; if (s) o.selected = true; return o; }
    function spol() {
        var cur = spolEl.value || cfg.spol, k = cfg.vrste[vrstaEl.value];
        var l = k && k.length ? k : ['muško', 'žensko'];
        spolEl.innerHTML = ''; spolEl.appendChild(opt('', ''));
        l.forEach(function (x) { spolEl.appendChild(opt(x, x, x === cur)); });
        if (cur && l.indexOf(cur) < 0) spolEl.appendChild(opt(cur, cur, true));
    }
    function sekcija() {
        var s = sekEl.value, cc = clanEl.value !== '' ? +clanEl.value : cfg.clan;
        clanEl.innerHTML = ''; clanEl.appendChild(opt('', '— odaberite lovca —'));
        (cfg.clanovi[s] || []).forEach(function (c) { clanEl.appendChild(opt(c.id, c.n, c.id === cc)); });
        clanEl.appendChild(opt('0', '👤 Gost (upisati ime)', cc === 0 && f.gost.value !== ''));
        gost();
        if (napEl) {
            var nc = napEl.value !== '' ? +napEl.value : cfg.naprava;
            napEl.innerHTML = ''; napEl.appendChild(opt('', '—'));
            (cfg.naprave[s] || []).forEach(function (n) { napEl.appendChild(opt(n.id, n.n, n.id === nc)); });
        }
    }
    var mEl = f.querySelector('[data-od-markica]'), mList = document.getElementById('od-markice'), mInfo = f.querySelector('[data-od-markice-info]');
    function markice() {
        var g = cfg.grupe[sekEl.value], po = (cfg.markice[g] || {}), l = po[vrstaEl.value] || [];
        mList.innerHTML = '';
        l.forEach(function (m) { var o = document.createElement('option'); o.value = m[0]; if (m[1]) o.label = 'zadužena: ' + m[1]; mList.appendChild(o); });
        if (mInfo) mInfo.textContent = vrstaEl.value && po && Object.keys(po).length ? (l.length ? 'Slobodnih markica u zalihi: ' + l.length : 'Nema slobodnih markica za ovu vrstu u zalihi.') : '';
    }
    vrstaEl.addEventListener('change', markice); vrstaEl.addEventListener('input', markice);
    if (sekEl.tagName === 'SELECT') sekEl.addEventListener('change', markice);
    function gost() { var g = clanEl.value === '0'; gostPolje.hidden = !g; f.gost.required = g; clanEl.required = !g; }
    vrstaEl.addEventListener('input', spol); vrstaEl.addEventListener('change', spol);
    if (sekEl.tagName === 'SELECT') sekEl.addEventListener('change', sekcija);
    clanEl.addEventListener('change', gost);
    spol(); sekcija(); markice();
})();
</script>
<?php stranica($o ? 'Izmjena odstrela' : 'Upis odstrela', ob_get_clean());
