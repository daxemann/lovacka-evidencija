<?php
/** Član: vlastite radne akcije (upis, izmjena dok čeka, brisanje). */
$k = korisnik();
if (!$k['ClanId']) {
    preusmjeri();
}
$cid = $k['ClanId'];
$vrste = redovi('SELECT * FROM VrsteAkcija WHERE Aktivna=1 ORDER BY Naziv');
$uredjuje = ul_int('uredi');
$moja = fn(int $id) => red('SELECT * FROM RadneAkcije WHERE Id=? AND ClanId=? AND Status=0', [$id, $cid]);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'obrisi') {
        $a = $moja((int) $_POST['aid']);
        if ($a) {
            obrisi_sliku($a['FotoDatoteka']);
            q('DELETE FROM RadneAkcije WHERE Id=?', [$a['Id']]);
            dnevnik('Član obrisao svoju radnu akciju', 'RadnaAkcija', (int) $a['Id']);
            poruka('Obrisano.');
        }
        preusmjeri('moje-akcije');
    }
    $datum = u_datum(ul_str('datum')) ?? danas();
    $vrsta = (int) ($_POST['vrsta'] ?? 0);
    $naziv = ul_str('naziv');
    $g = null;
    if ($vrsta === 0) {
        $g = 'Odaberite vrstu akcije.';
    } elseif ($vrsta === -1 && $naziv === '') {
        $g = 'Upišite naziv akcije.';
    } elseif ($datum > danas()) {
        $g = 'Datum ne može biti u budućnosti.';
    }
    if ($g) {
        poruka(e($g), 'danger');
        $_SESSION['moja_akcija'] = $_POST;
        preusmjeri('moje-akcije', ['uredi' => $_POST['aid'] ?? null]);
    }
    $polja = [
        'Datum' => $datum, 'VrstaAkcijeId' => $vrsta > 0 ? $vrsta : null, 'VrstaSlobodno' => $vrsta > 0 ? null : mb_substr($naziv, 0, 100),
        'Sati' => dec(u_broj($_POST['sati'] ?? '')), 'Opis' => ul_null('opis'),
    ];
    $foto = null;
    if (!empty($_FILES['foto']['name'])) {
        try {
            $foto = spremi_sliku($_FILES['foto'], 'akcija-' . $cid . '-', 1600);
        } catch (Throwable $e) {
            poruka(e($e->getMessage()), 'warning');
        }
    }
    if (!empty($_POST['aid'])) {
        $a = $moja((int) $_POST['aid']);
        if ($a) {
            if ($foto) {
                obrisi_sliku($a['FotoDatoteka']);
                $polja['FotoDatoteka'] = $foto;
            }
            azuriraj('RadneAkcije', (int) $a['Id'], $polja);
            poruka('Izmjene su spremljene.');
        }
    } else {
        $id = umetni('RadneAkcije', $polja + ['ClanId' => $cid, 'Status' => AKCIJA_CEKA, 'Bodovi' => null, 'RazlogOdbijanja' => null, 'FotoDatoteka' => $foto,
            'UnioKorisnikId' => $k['Id'], 'Kreirano' => sada(), 'OdlucioKorisnikId' => null, 'OdlucioIme' => null, 'Odluceno' => null]);
        dnevnik('Član prijavio radnu akciju', 'RadnaAkcija', $id, $k['Naziv']);
        poruka('Poslano na odobrenje. Bodove dodjeljuje administrator.');
    }
    preusmjeri('moje-akcije');
}

$lista = redovi('SELECT a.*, v.Naziv AS VrstaNaziv FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.ClanId=? ORDER BY a.Datum DESC, a.Id DESC', [$cid]);
$lg = pocetak_lovne_godine();
$lgKraj = $lg->modify('+1 year -1 day')->format('Y-m-d');
$bodoviLg = array_sum(array_map(fn($a) => (int) $a['Status'] === AKCIJA_ODOBRENO && substr($a['Datum'], 0, 10) >= $lg->format('Y-m-d') && substr($a['Datum'], 0, 10) <= $lgKraj ? (float) $a['Bodovi'] : 0, $lista));
$v = ['datum' => danas(), 'vrsta' => '', 'naziv' => '', 'sati' => '', 'opis' => ''];
$ur = $uredjuje ? $moja($uredjuje) : null;
if ($ur) {
    $v = ['datum' => substr($ur['Datum'], 0, 10), 'vrsta' => $ur['VrstaAkcijeId'] ?? -1, 'naziv' => $ur['VrstaSlobodno'] ?? '', 'sati' => $ur['Sati'] !== null ? (string) (float) $ur['Sati'] : '', 'opis' => $ur['Opis'] ?? ''];
}
if (isset($_SESSION['moja_akcija'])) {
    $v = array_merge($v, array_intersect_key($_SESSION['moja_akcija'], $v));
    unset($_SESSION['moja_akcija']);
}
ob_start(); ?>
<h1 class="h4 mb-3">Moje radne akcije</h1>
<div class="card mb-4" id="nova">
    <div class="card-header"><?= $ur ? 'Izmjena radne akcije' : 'Nova radna akcija' ?></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('moje-akcije')) ?>" enctype="multipart/form-data"><?= csrf() ?>
            <?php if ($ur): ?><input type="hidden" name="aid" value="<?= (int) $ur['Id'] ?>"><?php endif; ?>
            <div class="row g-2">
                <div class="col-6 col-md-3"><label class="form-label small">Datum</label><input type="date" name="datum" class="form-control" value="<?= e($v['datum']) ?>" max="<?= danas() ?>" required></div>
                <div class="col-6 col-md-3"><label class="form-label small">Vrsta</label>
                    <select name="vrsta" class="form-select" required onchange="document.getElementById('naziv').style.display=this.value==='-1'?'':'none'">
                        <option value="">— odaberi —</option>
                        <?php foreach ($vrste as $vr): ?><option value="<?= (int) $vr['Id'] ?>"<?= sel($v['vrsta'], $vr['Id']) ?>><?= e($vr['Naziv']) ?></option><?php endforeach; ?>
                        <option value="-1"<?= sel($v['vrsta'], -1) ?>>Vlastita akcija (upiši sam)…</option>
                    </select></div>
                <div class="col-12 col-md-6" id="naziv" style="<?= (string) $v['vrsta'] === '-1' ? '' : 'display:none' ?>"><label class="form-label small">Naziv akcije</label>
                    <input name="naziv" class="form-control" maxlength="100" placeholder="npr. popravak puta do čeke" value="<?= e($v['naziv']) ?>"></div>
                <div class="col-4 col-md-2"><label class="form-label small">Sati</label><input name="sati" type="number" step="0.5" min="0" inputmode="decimal" class="form-control" value="<?= e($v['sati']) ?>"></div>
                <div class="col-8 col-md-4"><label class="form-label small">Fotografija (neobavezno)</label><input type="file" name="foto" accept="image/*" class="form-control"></div>
                <div class="col-12"><label class="form-label small">Opis – što je napravljeno, gdje</label><textarea name="opis" rows="2" class="form-control"><?= e($v['opis']) ?></textarea></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary flex-grow-1"><?= $ur ? 'Spremi izmjene' : 'Pošalji na odobrenje' ?></button>
                <?php if ($ur): ?><a href="<?= e(url('moje-akcije')) ?>" class="btn btn-outline-secondary">Odustani</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>
<p class="small text-muted">Ti upisuješ što si napravio – bodove dodjeljuje i akciju odobrava administrator.</p>
<p class="mb-2">Bodovi u tekućoj lovnoj godini: <b class="fs-5"><?= e(broj($bodoviLg, 2)) ?></b></p>
<?php foreach ($lista as $a): ?>
    <div class="card mb-2"><div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="text-nowrap"><?= e(datum($a['Datum'])) ?></span>
            <?php if ($a['Sati'] !== null): ?><span class="text-muted small text-nowrap"><?= e(broj($a['Sati'])) ?> h</span><?php endif; ?>
            <span class="ms-auto d-flex gap-1 align-items-center"><?= status_akcije_oznaka((int) $a['Status']) ?>
                <?php if ($a['Bodovi'] !== null): ?><span class="badge bg-light text-dark border"><?= e(broj($a['Bodovi'], 2)) ?> b.</span><?php endif; ?></span>
        </div>
        <div class="fw-semibold"><?= e($a['VrstaNaziv'] ?? $a['VrstaSlobodno'] ?? '—') ?></div>
        <?php if ($a['Opis']): ?><div class="small mt-1"><?= e($a['Opis']) ?></div><?php endif; ?>
        <?php if ($a['FotoDatoteka']): ?><div class="small mt-1"><a href="<?= e(foto_url($a['FotoDatoteka'])) ?>" target="_blank">fotografija</a></div><?php endif; ?>
        <?php if ($a['RazlogOdbijanja']): ?><div class="small text-danger mt-1">Razlog odbijanja: <?= e($a['RazlogOdbijanja']) ?></div><?php endif; ?>
        <?php if ((int) $a['Status'] === AKCIJA_CEKA): ?>
            <div class="mt-1">
                <a class="btn btn-sm btn-link px-0" href="<?= e(url('moje-akcije', ['uredi' => $a['Id']])) ?>">Uredi</a>
                <form method="post" action="<?= e(url('moje-akcije')) ?>" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="aid" value="<?= (int) $a['Id'] ?>">
                    <button class="btn btn-sm btn-link text-danger" data-potvrda="Obrisati ovu radnu akciju?">Obriši</button></form>
            </div>
        <?php endif; ?>
    </div></div>
<?php endforeach; ?>
<?php if (!$lista): ?><p class="text-muted">Još nemate unesenih radnih akcija.</p><?php endif; ?>
<?php stranica('Moje radne akcije', ob_get_clean());
