<?php
/** Statut i pravilnici – uređivanje (administrator). Članovi vide stranicu „dokumenti“. */
trazi(P_SUSTAV);

$id = (int) ($_GET['id'] ?? 0);
$novi = isset($_GET['novi']);
$dok = $id ? red('SELECT * FROM Dokumenti WHERE Id=?', [$id]) : null;
if ($id && !$dok) {
    nije_pronadjeno('Dokument');
}

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');

    if ($radnja === 'poredak') {
        // premještanje dokumenta u popisu
        $sve = array_map('intval', array_column(redovi('SELECT Id FROM Dokumenti ORDER BY Redoslijed, Id'), 'Id'));
        $i = array_search((int) ($_POST['dok'] ?? 0), $sve, true);
        $j = $i === false ? false : ($_POST['smjer'] === 'gore' ? $i - 1 : $i + 1);
        if ($i !== false && isset($sve[$j])) {
            [$sve[$i], $sve[$j]] = [$sve[$j], $sve[$i]];
        }
        foreach ($sve as $r => $did) {
            q('UPDATE Dokumenti SET Redoslijed=? WHERE Id=?', [$r, $did]);
        }
        preusmjeri('sustav/dokumenti');
    }

    if ($radnja === 'obrisi' && $dok) {
        foreach (dok_datoteke($id) as $d) {
            dok_obrisi_datoteku($d);
        }
        q('DELETE FROM Dokumenti WHERE Id=?', [$id]);
        dnevnik('Dokument obrisan', 'Dokument', $id, $dok['Naslov']);
        poruka('Dokument „' . e($dok['Naslov']) . '“ je obrisan.');
        preusmjeri('sustav/dokumenti');
    }

    if ($radnja === 'obrisi_datoteku' && $dok) {
        $d = red('SELECT * FROM DokumentDatoteke WHERE Id=? AND DokumentId=?', [(int) ($_POST['dat'] ?? 0), $id]);
        if ($d) {
            dok_obrisi_datoteku($d);
            q('DELETE FROM DokumentDatoteke WHERE Id=?', [$d['Id']]);
            q('UPDATE Dokumenti SET Azurirano=? WHERE Id=?', [sada(), $id]);
            dnevnik('Dokument – datoteka obrisana', 'Dokument', $id, $d['Naziv']);
            poruka('Datoteka „' . e($d['Naziv']) . '“ je obrisana.');
        }
        preusmjeri('sustav/dokumenti', ['id' => $id]);
    }

    if ($radnja === 'datoteka_poredak' && $dok) {
        $sve = array_map('intval', array_column(dok_datoteke($id), 'Id'));
        $i = array_search((int) ($_POST['dat'] ?? 0), $sve, true);
        $j = $i === false ? false : ($_POST['smjer'] === 'gore' ? $i - 1 : $i + 1);
        if ($i !== false && isset($sve[$j])) {
            [$sve[$i], $sve[$j]] = [$sve[$j], $sve[$i]];
        }
        foreach ($sve as $r => $did) {
            q('UPDATE DokumentDatoteke SET Redoslijed=? WHERE Id=?', [$r, $did]);
        }
        preusmjeri('sustav/dokumenti', ['id' => $id]);
    }

    // spremanje (novi ili postojeći)
    $naslov = mb_substr(ul_str('naslov'), 0, 150);
    if ($naslov === '') {
        poruka('Upišite naslov (npr. „Statut udruge“).', 'danger');
        preusmjeri('sustav/dokumenti', $id ? ['id' => $id] : ['novi' => 1]);
    }
    $polja = [
        'Naslov' => $naslov,
        'Opis' => ul_null('opis') !== null ? mb_substr(ul_str('opis'), 0, 300) : null,
        'Tekst' => trim((string) ($_POST['tekst'] ?? '')) !== '' ? (string) $_POST['tekst'] : null,
        'Objavljeno' => isset($_POST['objavljeno']) ? 1 : 0,
        'Azurirano' => sada(),
    ];
    if ($dok) {
        azuriraj('Dokumenti', $id, $polja);
        dnevnik('Dokument izmijenjen', 'Dokument', $id, $naslov);
    } else {
        $id = umetni('Dokumenti', $polja + ['Kreirano' => sada(), 'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),-1)+1 FROM Dokumenti')]);
        dnevnik('Dokument dodan', 'Dokument', $id, $naslov);
    }

    // učitane datoteke (PDF / slike, više odjednom)
    $f = $_FILES['datoteke'] ?? null;
    $dodano = 0;
    if ($f && is_array($f['name'])) {
        $r = (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),-1)+1 FROM DokumentDatoteke WHERE DokumentId=?', [$id]);
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $jedna = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i]];
            try {
                [$ime, $vrsta] = dok_spremi($jedna, $id);
                umetni('DokumentDatoteke', ['DokumentId' => $id, 'Datoteka' => $ime, 'Vrsta' => $vrsta, 'Naziv' => dok_naziv_datoteke((string) $f['name'][$i]), 'Redoslijed' => $r++]);
                $dodano++;
            } catch (Throwable $e) {
                poruka(e((string) $f['name'][$i]) . ': ' . e($e->getMessage()), 'warning');
            }
        }
    }
    poruka('Dokument je spremljen' . ($dodano ? ' (dodano datoteka: ' . $dodano . ')' : '') . '.');
    preusmjeri('sustav/dokumenti', ['id' => $id]);
}

ob_start();

if ($dok || $novi):
    $datoteke = $dok ? dok_datoteke($id) : []; ?>
<p class="mb-2"><a href="<?= e(url('sustav/dokumenti')) ?>">← Svi dokumenti</a></p>
<h1 class="h3 mb-3"><?= $dok ? e($dok['Naslov']) : 'Novi dokument' ?></h1>

<form method="post" enctype="multipart/form-data" action="<?= e(url('sustav/dokumenti', $dok ? ['id' => $id] : ['novi' => 1])) ?>"><?= csrf() ?>
<div class="row g-4">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <label class="form-label">Naslov</label>
        <input name="naslov" class="form-control mb-3" required maxlength="150" value="<?= e($dok['Naslov'] ?? '') ?>" placeholder="npr. Statut udruge, Pravilnik o lovu, Kućni red lovačkog doma">
        <label class="form-label">Kratki opis <span class="text-muted small">(neobavezno)</span></label>
        <input name="opis" class="form-control mb-3" maxlength="300" value="<?= e($dok['Opis'] ?? '') ?>" placeholder="npr. usvojen na skupštini 15. 3. 2024.">
        <label class="form-label">Tekst <span class="text-muted small">(neobavezno – možete upisati ili zalijepiti tekst)</span></label>
        <textarea name="tekst" class="form-control" rows="16" placeholder="Prazan red = novi odlomak. Red koji počinje s # je podnaslov, npr.&#10;# Članak 1."><?= e($dok['Tekst'] ?? '') ?></textarea>
    </div></div>
</div>
<div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">Datoteke (PDF ili slike)</div><div class="card-body">
        <?php if ($datoteke): ?>
            <ul class="list-group mb-3">
            <?php foreach ($datoteke as $n => $d): ?>
                <li class="list-group-item d-flex align-items-center gap-2 px-2">
                    <?php if ($d['Vrsta'] === 'slika'): ?>
                        <img src="<?= e(dok_datoteka_url($d)) ?>" alt="" style="width:44px;height:44px;object-fit:cover" class="rounded border">
                    <?php else: ?>
                        <span class="badge bg-danger">PDF</span>
                    <?php endif; ?>
                    <a class="small text-truncate flex-grow-1" href="<?= e(dok_datoteka_url($d)) ?>" target="_blank" rel="noopener"><?= e($d['Naziv']) ?></a>
                    <span class="text-nowrap">
                        <?php if ($n > 0): ?><button class="btn btn-sm btn-link p-0" name="radnja" value="datoteka_poredak" formnovalidate onclick="this.form.dat.value=<?= (int) $d['Id'] ?>;this.form.smjer.value='gore'" title="Gore">▲</button><?php endif; ?>
                        <?php if ($n < count($datoteke) - 1): ?><button class="btn btn-sm btn-link p-0" name="radnja" value="datoteka_poredak" formnovalidate onclick="this.form.dat.value=<?= (int) $d['Id'] ?>;this.form.smjer.value='dolje'" title="Dolje">▼</button><?php endif; ?>
                        <button class="btn btn-sm btn-link text-danger p-0 ms-1" name="radnja" value="obrisi_datoteku" formnovalidate onclick="this.form.dat.value=<?= (int) $d['Id'] ?>" data-potvrda="Obrisati datoteku „<?= e($d['Naziv']) ?>“?" title="Obriši">✕</button>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <input type="hidden" name="dat" value=""><input type="hidden" name="smjer" value="">
        <input type="file" name="datoteke[]" class="form-control" accept="application/pdf,.pdf,image/*" multiple>
        <div class="form-text">PDF (do <?= DOK_MAX_PDF_MB ?> MB) ili fotografije papira – više stranica odjednom, redom kako trebaju biti. Najviše <?= e(ini_get('upload_max_filesize')) ?> po datoteci.</div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="objavljeno" id="obj" value="1"<?= chk(($dok['Objavljeno'] ?? 1) == 1) ?>>
            <label class="form-check-label" for="obj"><b>Objavljeno</b> – vide ga svi članovi</label></div>
        <button class="btn btn-primary w-100" name="radnja" value="spremi">Spremi</button>
    </div></div>

    <?php if ($dok): ?>
        <p class="small text-muted">Dodano <?= e(datum_vrijeme($dok['Kreirano'])) ?> · izmijenjeno <?= e(datum_vrijeme($dok['Azurirano'])) ?></p>
        <p><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('dokumenti', ['id' => $id])) ?>">Pogledaj kao član</a></p>
        <button class="btn btn-outline-danger btn-sm" name="radnja" value="obrisi" formnovalidate data-potvrda="Obrisati cijeli dokument „<?= e($dok['Naslov']) ?>“ sa svim datotekama?">Obriši dokument</button>
    <?php endif; ?>
</div>
</div>
</form>

<?php else:
    $svi = redovi('SELECT d.*, (SELECT COUNT(*) FROM DokumentDatoteke f WHERE f.DokumentId=d.Id) AS BrojDat FROM Dokumenti d ORDER BY Redoslijed, Id'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Statut i pravilnici</h1>
    <a class="btn btn-primary" href="<?= e(url('sustav/dokumenti', ['novi' => 1])) ?>">+ Novi dokument</a>
</div>
<p class="text-muted">Statut, pravilnici, odluke, kućni red… Dokument može imati upisani tekst, PDF i/ili fotografije papira. Članovi ih vide u izborniku <b>Članovi → 📜 Statut i pravilnici</b> i ne mogu ih mijenjati.</p>

<?php if (!$svi): ?>
    <div class="alert alert-info">Još nema dokumenata. Kliknite „+ Novi dokument“.</div>
<?php else: ?>
<form method="post" action="<?= e(url('sustav/dokumenti')) ?>"><?= csrf() ?>
<input type="hidden" name="radnja" value="poredak"><input type="hidden" name="dok" value=""><input type="hidden" name="smjer" value="">
<div class="list-group">
<?php foreach ($svi as $n => $d): ?>
    <div class="list-group-item d-flex align-items-center gap-2">
        <div class="flex-grow-1">
            <a class="fw-semibold" href="<?= e(url('sustav/dokumenti', ['id' => $d['Id']])) ?>"><?= e($d['Naslov']) ?></a>
            <?php if (!$d['Objavljeno']): ?><span class="badge bg-secondary ms-1">skriveno</span><?php endif; ?>
            <div class="small text-muted"><?= $d['Opis'] ? e($d['Opis']) . ' · ' : '' ?><?= $d['Tekst'] ? 'tekst · ' : '' ?><?= (int) $d['BrojDat'] ?> datoteka · izmijenjeno <?= e(datum_vrijeme($d['Azurirano'])) ?></div>
        </div>
        <span class="text-nowrap">
            <?php if ($n > 0): ?><button class="btn btn-sm btn-outline-secondary" onclick="this.form.dok.value=<?= (int) $d['Id'] ?>;this.form.smjer.value='gore'" title="Gore">▲</button><?php endif; ?>
            <?php if ($n < count($svi) - 1): ?><button class="btn btn-sm btn-outline-secondary" onclick="this.form.dok.value=<?= (int) $d['Id'] ?>;this.form.smjer.value='dolje'" title="Dolje">▼</button><?php endif; ?>
        </span>
    </div>
<?php endforeach; ?>
</div>
</form>
<?php endif; ?>
<?php endif;
stranica('Statut i pravilnici', ob_get_clean());
