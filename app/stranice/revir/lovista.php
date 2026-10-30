<?php
/** 🗺️ Lovišta: popis i uređivanje (podaci, granica – KML / točku po točku / slobodno olovkom, linije: putevi, prosjeke …). */
$k = korisnik();
if (!ima(P_SUSTAV) && !ima(P_REVIR_UREDI)) {
    zabranjeno();
}
$sveSek = [];
foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
    $sveSek[(int) $s['Id']] = $s['Naziv'];
}
$mojeSek = array_filter($sveSek, fn($n, $id) => loviste_moze_urediti($id), ARRAY_FILTER_USE_BOTH);
$zaSve = loviste_moze_urediti(null);

/** Koordinate iz JSON-a: [[ [lat,lon], … ], …] – samo brojevi, razumna veličina. */
$koordinate = function (mixed $niz, int $min): array {
    $out = [];
    foreach (is_array($niz) ? $niz : [] as $t) {
        if (is_array($t) && count($t) >= 2 && is_numeric($t[0]) && is_numeric($t[1]) && abs((float) $t[0]) <= 90 && abs((float) $t[1]) <= 180) {
            $out[] = [round((float) $t[0], 7), round((float) $t[1], 7)];
        }
    }
    return count($out) >= $min ? array_slice($out, 0, 5000) : [];
};

if (je_post()) {
    $id = (int) ($_POST['id'] ?? 0);
    $l = $id ? (loviste($id) ?? nije_pronadjeno('Lovište')) : null;
    if ($l && !loviste_moze_urediti($l['SekcijaId'] !== null ? (int) $l['SekcijaId'] : null)) {
        zabranjeno();
    }
    if (($_POST['radnja'] ?? '') === 'obrisi' && $l) {
        q('DELETE FROM Lovista WHERE Id=?', [$l['Id']]);
        dnevnik('Lovište obrisano', null, null, $l['Naziv']);
        poruka('Lovište obrisano.');
        preusmjeri('revir/lovista');
    }
    $sid = (int) ($_POST['sekcija'] ?? 0);
    if ($sid ? !isset($mojeSek[$sid]) : !$zaSve) {
        poruka('Nemate pravo za tu sekciju.', 'danger');
        preusmjeri('revir/lovista', $id ? ['id' => $id] : ['novo' => 1]);
    }
    $naziv = mb_substr(ul_str('naziv'), 0, 80);
    if ($naziv === '') {
        poruka('Upišite naziv lovišta.', 'danger');
        preusmjeri('revir/lovista', $id ? ['id' => $id] : ['novo' => 1]);
    }
    $granica = [];
    foreach ((array) json_decode((string) ($_POST['granica'] ?? '[]'), true) as $p) {
        if ($k2 = $koordinate($p, 3)) {
            $granica[] = $k2;
        }
    }
    $linije = [];
    foreach ((array) json_decode((string) ($_POST['linije'] ?? '[]'), true) as $li) {
        if (is_array($li) && ($k2 = $koordinate($li['koord'] ?? null, 2))) {
            $linije[] = ['naziv' => mb_substr((string) ($li['naziv'] ?? ''), 0, 60), 'vrsta' => in_array($li['vrsta'] ?? '', LINIJA_VRSTE, true) ? $li['vrsta'] : 'Ostalo',
                'boja' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($li['boja'] ?? '')) ? $li['boja'] : '#ffffff', 'crtkano' => !empty($li['crtkano']) ? 1 : 0, 'koord' => $k2];
        }
    }
    $pov = str_replace(',', '.', ul_str('povrsina'));
    $polja = [
        'Naziv' => $naziv, 'Broj' => mb_substr(ul_str('broj'), 0, 30) ?: null,
        'Vrsta' => in_array(ul_str('vrsta'), LOVISTE_VRSTE, true) ? ul_str('vrsta') : null,
        'Ovlastenik' => mb_substr(ul_str('ovlastenik'), 0, 120) ?: null,
        'Zupanija' => in_array(ul_str('zupanija'), ZUPANIJE, true) ? ul_str('zupanija') : null,
        'Povrsina' => is_numeric($pov) ? round((float) $pov, 1) : ($granica ? round(loviste_povrsina($granica) / 10000, 1) : null),
        'Boja' => preg_match('/^#[0-9a-f]{6}$/i', ul_str('boja')) ? ul_str('boja') : '#ffd400',
        'SekcijaId' => $sid ?: null, 'Aktivno' => isset($_POST['aktivno']) ? 1 : 0,
        'Granica' => $granica ? json_encode($granica) : null, 'Linije' => $linije ? json_encode($linije, JSON_UNESCAPED_UNICODE) : null,
        'Napomena' => mb_substr(ul_str('napomena'), 0, 500) ?: null, 'Azurirano' => sada(),
    ];
    if ($l) {
        azuriraj('Lovista', (int) $l['Id'], $polja);
    } else {
        $id = umetni('Lovista', $polja + ['Kreirano' => sada()]);
    }
    dnevnik($l ? 'Lovište izmijenjeno' : 'Novo lovište', null, null, $naziv . ' · ' . count($granica) . ' granica, ' . count($linije) . ' linija');
    poruka('Lovište spremljeno.');
    preusmjeri('revir/lovista', ['id' => $id]);
}

$id = (int) ul('id', 0);
if (!$id && !ul('novo', '')) {
    // ---------- popis ----------
    $lista = lovista(true);
    ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto">🗺️ Lovišta</h1>
    <a class="btn btn-success" href="<?= e(url('revir/lovista', ['novo' => 1])) ?>">➕ Novo lovište</a>
</div>
<p class="text-muted small" style="max-width:820px">Svako lovište ima svoje podatke, granicu i linije (putevi, prosjeke, staze). Na karti lovišta vide se samo <b>aktivna</b> lovišta
    (lovište sekcije samo članovi te sekcije, lovište „za sve sekcije“ svi). Granicu možete uvesti iz KML-a (Google My Maps) ili nacrtati – točku po točku ili slobodno olovkom.</p>
<?php if (!$lista): ?><div class="alert alert-secondary">Još nema lovišta.</div><?php else: ?>
<div class="list-group">
    <?php foreach ($lista as $l): $sid = $l['SekcijaId'] !== null ? (int) $l['SekcijaId'] : null; $gr = json_decode((string) $l['Granica'], true) ?: []; $li = json_decode((string) $l['Linije'], true) ?: []; ?>
    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3<?= $l['Aktivno'] ? '' : ' opacity-50' ?>" href="<?= e(url('revir/lovista', ['id' => $l['Id']])) ?>">
        <span class="lov-boja" style="background:<?= e($l['Boja']) ?>"></span>
        <span class="flex-grow-1"><b><?= e($l['Naziv']) ?></b><?= $l['Broj'] ? ' <span class="text-muted">' . e($l['Broj']) . '</span>' : '' ?>
            <?= $l['Aktivno'] ? '' : ' <span class="badge text-bg-secondary">neaktivno</span>' ?>
            <span class="d-block small text-muted"><?= e(implode(' · ', array_filter([$l['Vrsta'], $l['Zupanija'], $l['Povrsina'] ? broj((float) $l['Povrsina'], 0) . ' ha' : '',
                $sid ? ($sveSek[$sid] ?? '') : 'sve sekcije', $gr ? count($gr) . ' granica' : 'bez granice', $li ? count($li) . ' linija' : '']))) ?></span></span>
        <span aria-hidden="true">›</span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php stranica('Lovišta', ob_get_clean());
}

// ---------- uređivanje ----------
$l = $id ? (loviste($id) ?? nije_pronadjeno('Lovište')) : null;
if ($l && !loviste_moze_urediti($l['SekcijaId'] !== null ? (int) $l['SekcijaId'] : null)) {
    zabranjeno();
}
$v = $l ?? ['Naziv' => '', 'Broj' => '', 'Vrsta' => 'Otvoreno', 'Ovlastenik' => udruga_naziv(), 'Zupanija' => '', 'Povrsina' => null, 'Boja' => '#ffd400',
    'SekcijaId' => null, 'Aktivno' => 1, 'Granica' => null, 'Linije' => null, 'Napomena' => ''];
$ostala = [];
foreach (lovista(true) as $o) {
    if ($l && (int) $o['Id'] === (int) $l['Id']) {
        continue;
    }
    $ostala[] = ['naziv' => $o['Naziv'], 'boja' => $o['Boja'], 'granica' => json_decode((string) $o['Granica'], true) ?: []];
}
$naprave = array_map(fn($n) => [(float) $n['Lat'], (float) $n['Lon'], ($n['Broj'] ? $n['Broj'] . ' · ' : '') . $n['Naziv']],
    redovi('SELECT Broj, Naziv, Lat, Lon FROM RevirNaprave WHERE Aktivna=1 AND Lat IS NOT NULL'));
$cfg = ['granica' => json_decode((string) $v['Granica'], true) ?: [], 'linije' => json_decode((string) $v['Linije'], true) ?: [], 'ostala' => $ostala,
    'naprave' => $naprave, 'centar' => revir_centar(), 'vrste' => LINIJA_VRSTE, 'boja' => $v['Boja']];
ob_start(); ?>
<link rel="stylesheet" href="<?= e(asset('assets/leaflet/leaflet.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/geoman/leaflet-geoman.css')) ?>">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir/lovista')) ?>">← Lovišta</a>
    <h1 class="h4 mb-0 me-auto"><?= $l ? e($l['Naziv']) : 'Novo lovište' ?></h1>
</div>
<form method="post" id="lov-obrazac"><?= csrf() ?><input type="hidden" name="id" value="<?= (int) ($l['Id'] ?? 0) ?>">
<input type="hidden" name="granica" id="lov-granica"><input type="hidden" name="linije" id="lov-linije">
<div class="row g-3">
<div class="col-lg-8 order-lg-1">
    <div class="lov-alati mb-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Što crtam">
            <input type="radio" class="btn-check" name="lov-sto" id="lov-sto-g" value="granica" checked><label class="btn btn-outline-primary" for="lov-sto-g">▱ Granica</label>
            <input type="radio" class="btn-check" name="lov-sto" id="lov-sto-l" value="linija"><label class="btn btn-outline-primary" for="lov-sto-l">〰 Linija</label>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-alat="tocke">📍 Točku po točku</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-alat="olovka">🖍️ Slobodno (olovka)</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-alat="uredi">✋ Pomakni točke</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-alat="brisi">🗑 Briši oblik</button>
        <button type="button" class="btn btn-sm btn-outline-warning" data-alat="zakljucaj">🔒 Zaključaj kartu</button>
        <label class="btn btn-sm btn-outline-secondary mb-0">📂 Uvezi KML<input type="file" accept=".kml,application/vnd.google-earth.kml+xml" hidden data-alat="kml"></label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-alat="izvoz">⬇ KML</button>
    </div>
    <div class="lov-karta-okvir"><div id="lov-karta" class="lov-karta"></div><div class="lov-zakljucano-oznaka" hidden>🔒 Karta zaključana – crtanje</div></div>
    <div class="small text-muted mt-1" id="lov-info"></div>
    <div class="small text-muted mt-1"><b>Točku po točku:</b> dodirujte kartu, zadnju točku dodirnite još jednom za kraj. <b>Slobodno:</b> karta se zaključa, vucite prstom – pri puštanju se crta izgladi
        (granica se sama zatvori), zatim je možete popraviti s „Pomakni točke“. <b>🔒</b> zaključava kartu dok crtate, ponovni dodir je otključava.</div>
</div>
<div class="col-lg-4 order-lg-2">
    <div class="card"><div class="card-body row g-2">
        <div class="col-12"><label class="form-label small mb-0">Naziv *</label><input name="naziv" class="form-control" required maxlength="80" value="<?= e($v['Naziv']) ?>" placeholder="npr. Bukovac"></div>
        <div class="col-6"><label class="form-label small mb-0">Broj lovišta</label><input name="broj" class="form-control" maxlength="30" value="<?= e((string) $v['Broj']) ?>" placeholder="npr. VII/118"></div>
        <div class="col-6"><label class="form-label small mb-0">Vrsta</label><select name="vrsta" class="form-select"><option value=""></option>
            <?php foreach (LOVISTE_VRSTE as $x): ?><option<?= sel($x, $v['Vrsta']) ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label small mb-0">Lovoovlaštenik</label><input name="ovlastenik" class="form-control" maxlength="120" value="<?= e((string) $v['Ovlastenik']) ?>"></div>
        <div class="col-7"><label class="form-label small mb-0">Županija</label><select name="zupanija" class="form-select"><option value=""></option>
            <?php foreach (ZUPANIJE as $x): ?><option<?= sel($x, $v['Zupanija']) ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
        <div class="col-5"><label class="form-label small mb-0">Površina (ha)</label><input name="povrsina" class="form-control" inputmode="decimal" id="lov-povrsina"
            value="<?= e($v['Povrsina'] !== null ? broj((float) $v['Povrsina'], 1) : '') ?>" placeholder="iz granice"></div>
        <div class="col-8"><label class="form-label small mb-0">Sekcija</label><select name="sekcija" class="form-select">
            <?php if ($zaSve): ?><option value="0">Sve sekcije (cijela udruga)</option><?php endif; ?>
            <?php foreach ($mojeSek as $sid => $n): ?><option value="<?= (int) $sid ?>"<?= sel($sid, $v['SekcijaId']) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-4"><label class="form-label small mb-0">Boja</label><input type="color" name="boja" id="lov-boja" class="form-control form-control-color w-100" value="<?= e($v['Boja']) ?>"></div>
        <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="aktivno" value="1"<?= $v['Aktivno'] ? ' checked' : '' ?>> Aktivno – vidi se na karti lovišta</label></div>
        <div class="col-12"><label class="form-label small mb-0">Napomena</label><textarea name="napomena" class="form-control" rows="2" maxlength="500"><?= e((string) $v['Napomena']) ?></textarea></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-primary flex-grow-1">💾 Spremi lovište</button>
        <?php if ($l): ?><button class="btn btn-outline-danger" name="radnja" value="obrisi" formnovalidate data-potvrda="Obrisati lovište „<?= e($l['Naziv']) ?>“ s granicom i linijama?">🗑</button><?php endif; ?></div></div>
    <div class="card mt-3" id="lov-linija-panel" hidden><div class="card-header small fw-semibold">Odabrana linija</div><div class="card-body row g-2">
        <div class="col-12"><input class="form-control form-control-sm" data-li="naziv" maxlength="60" placeholder="naziv (npr. Put uz potok)"></div>
        <div class="col-7"><select class="form-select form-select-sm" data-li="vrsta"><?php foreach (LINIJA_VRSTE as $x): ?><option><?= e($x) ?></option><?php endforeach; ?></select></div>
        <div class="col-5"><input type="color" class="form-control form-control-sm form-control-color w-100" data-li="boja"></div>
        <div class="col-7"><label class="form-check small"><input type="checkbox" class="form-check-input" data-li="crtkano"> crtkano</label></div>
        <div class="col-5 small text-muted text-end" data-li="duljina"></div>
    </div></div>
</div>
</div>
</form>
<script id="lov-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(asset('assets/geoman/leaflet-geoman.js')) ?>"></script>
<script src="<?= e(asset('assets/lovista.js')) ?>"></script>
<?php stranica($l ? 'Lovište ' . $l['Naziv'] : 'Novo lovište', ob_get_clean());
