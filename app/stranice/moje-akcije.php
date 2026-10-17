<?php
/** Član: vlastite radne akcije (upis, izmjena dok čeka, brisanje). */
$k = korisnik();
if (!$k['ClanId']) {
    preusmjeri();
}
$cid = $k['ClanId'];
vrsta_akcije_psi('potraga'); // stvara vrstu „Potraga s psom“ ako je nema
$vrste = redovi('SELECT * FROM VrsteAkcija WHERE Aktivna=1 ORDER BY Naziv');
$uredjuje = ul_int('uredi');
$mojiPsi = aktivni_psi((int) $cid);
// član mijenja / briše samo prijave koje je sam upisao (grupni unos uprave ne)
$moja = fn(int $id) => red('SELECT * FROM RadneAkcije WHERE Id=? AND ClanId=? AND Status=0 AND (UnioKorisnikId IS NULL OR UnioKorisnikId=?)', [$id, $cid, $k['Id']]);

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
        'PasId' => isset($mojiPsi[(int) ($_POST['pas'] ?? 0)]) ? (int) $_POST['pas'] : null,
        'Oprema' => $vrsta > 0 ? oprema_json(oprema_odabir($vrsta, (array) ($_POST['oprema'] ?? []))) : null,
    ];
    if ($vrsta === vrsta_akcije_psi('potraga') && ($polja['PasId'] === null || empty($mojiPsi[$polja['PasId']]['Potraga']))) {
        poruka('Za potragu odaberite psa koji radi potragu (kvačica „🩸 radi potragu“ u Moj profil → Moji lovački psi).', 'danger');
        $_SESSION['moja_akcija'] = $_POST;
        preusmjeri('moje-akcije', ['uredi' => $_POST['aid'] ?? null]);
    }
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
        $sekC = vrijednost('SELECT SekcijaId FROM Clanovi WHERE Id=?', [$cid]);
        push_posalji(push_korisnici_s_pravom(P_AKCIJE_ODOBRI, $sekC !== null ? (int) $sekC : null), 'akcije', '🛠️ Radna akcija čeka odobrenje',
            $k['Naziv'] . ' · ' . datum((string) ($polja['Datum'] ?? danas())) . (!empty($polja['Opis']) ? ' · ' . mb_substr((string) $polja['Opis'], 0, 100) : ''), 'akcije', [], 'akcije-cekaju');
        poruka('Poslano na odobrenje. Bodove dodjeljuje administrator.');
    }
    preusmjeri('moje-akcije');
}

$lista = redovi('SELECT a.*, v.Naziv AS VrstaNaziv, p.Ime AS PasIme FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId LEFT JOIN LovniPsi p ON p.Id=a.PasId WHERE a.ClanId=? ORDER BY a.Datum DESC, a.Id DESC', [$cid]);
$lg = pocetak_lovne_godine();
$lgKraj = $lg->modify('+1 year -1 day')->format('Y-m-d');
$bodoviLg = array_sum(array_map(fn($a) => (int) $a['Status'] === AKCIJA_ODOBRENO && substr($a['Datum'], 0, 10) >= $lg->format('Y-m-d') && substr($a['Datum'], 0, 10) <= $lgKraj ? (float) $a['Bodovi'] : 0, $lista));
$v = ['datum' => danas(), 'vrsta' => '', 'naziv' => '', 'sati' => '', 'opis' => '', 'pas' => '', 'oprema' => []];
$ur = $uredjuje ? $moja($uredjuje) : null;
if ($ur) {
    $v = ['datum' => substr($ur['Datum'], 0, 10), 'vrsta' => $ur['VrstaAkcijeId'] ?? -1, 'naziv' => $ur['VrstaSlobodno'] ?? '', 'sati' => $ur['Sati'] !== null ? (string) (float) $ur['Sati'] : '', 'opis' => $ur['Opis'] ?? '', 'pas' => (string) ($ur['PasId'] ?? ''),
        'oprema' => array_column(oprema_lista($ur['Oprema']), 'id')];
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
                <?php if ($mojiPsi): ?><div class="col-8 col-md-3"><label class="form-label small">🐕 Pas (ako je sudjelovao)</label>
                    <select name="pas" class="form-select"><option value="">— bez psa —</option><?php foreach ($mojiPsi as $pid => $p): ?><option value="<?= $pid ?>" data-potraga="<?= (int) $p['Potraga'] ?>"<?= sel((string) $pid, $v['pas']) ?>><?= e(opis_psa($p)) ?><?= $p['Potraga'] ? ' 🩸' : '' ?></option><?php endforeach; ?></select>
                    <div class="form-text d-none" id="potragaInfo">Za potragu samo psi s kvačicom „🩸 radi potragu“ (<a href="<?= e(url('moj-profil', ['psi' => 1])) ?>">Moj profil</a>).</div></div>
                    <script>(function () {
                        var vr = document.querySelector('[name=vrsta]'), ps = document.querySelector('[name=pas]'), pot = '<?= (int) vrsta_akcije_psi('potraga') ?>';
                        function f() { var je = vr.value === pot; document.getElementById('potragaInfo').classList.toggle('d-none', !je);
                            ps.querySelectorAll('option[data-potraga]').forEach(function (o) { o.disabled = je && o.dataset.potraga !== '1'; if (o.disabled && o.selected) ps.value = ''; }); }
                        vr.addEventListener('change', f); f(); })();</script><?php endif; ?>
                <div class="col-12" id="opremaClan" hidden>
                    <div class="border rounded p-2 bg-light">
                        <div class="small fw-semibold mb-1">🧰 Donio/la sam opremu <span class="fw-normal text-muted">(dodatni bodovi)</span></div>
                        <div class="d-flex flex-wrap gap-2" id="opremaIzbor"></div>
                    </div>
                </div>
                <script id="oprema-cfg" type="application/json"><?= json_encode(['oprema' => oprema_js(), 'odabrano' => array_map('intval', (array) $v['oprema'])], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
                <script>(function () {
                    var vr = document.querySelector('[name=vrsta]'), C = JSON.parse(document.getElementById('oprema-cfg').textContent), oz = (C.odabrano || []).map(String);
                    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
                    function f() {
                        document.querySelectorAll('#opremaIzbor input:checked').forEach(function (x) { if (oz.indexOf(x.value) === -1) oz.push(x.value); });
                        document.querySelectorAll('#opremaIzbor input:not(:checked)').forEach(function (x) { oz = oz.filter(function (y) { return y !== x.value; }); });
                        var l = C.oprema[vr.value] || [];
                        document.getElementById('opremaClan').hidden = !l.length;
                        document.getElementById('opremaIzbor').innerHTML = l.map(function (o) {
                            return '<label class="d-inline-flex align-items-center gap-2 border rounded px-2 py-1 m-0 bg-white"><input class="form-check-input m-0" type="checkbox" name="oprema[]" value="' + o.id + '"'
                                + (oz.indexOf(String(o.id)) !== -1 ? ' checked' : '') + '> ' + esc(o.n) + (o.b ? ' <span class="text-success fw-semibold">+' + String(o.b).replace('.', ',') + ' b.</span>' : '') + '</label>';
                        }).join('');
                    }
                    vr.addEventListener('change', f); f(); })();</script>
                <div class="col-8 col-md-4"><label class="form-label small">Fotografija (neobavezno)</label><input type="file" name="foto" accept="image/*" data-smanji="1600" class="form-control"></div>
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
                <?php if ($a['Bodovi'] !== null): ?><span class="badge bg-light <?= (int) $a['Status'] === AKCIJA_ODOBRENO ? 'text-dark' : 'text-muted' ?> border"<?= (int) $a['Status'] === AKCIJA_CEKA ? ' title="predloženo – vrijedi nakon odobrenja"' : '' ?>><?= e(broj($a['Bodovi'], 2)) ?> b.<?= (int) $a['Status'] === AKCIJA_CEKA ? ' ?' : '' ?></span><?php endif; ?></span>
        </div>
        <div class="fw-semibold"><?= e($a['VrstaNaziv'] ?? $a['VrstaSlobodno'] ?? '—') ?><?php if ($a['PasIme'] && !str_contains((string) $a['Opis'], (string) $a['PasIme'])): ?> <span class="fw-normal small">🐕 <?= e($a['PasIme']) ?></span><?php endif; ?></div>
        <?php if ($a['Oprema']): ?><div class="small mt-1"><?= oprema_html($a['Oprema']) ?></div><?php endif; ?>
        <?php if ($a['Opis']): ?><div class="small mt-1"><?= e($a['Opis']) ?></div><?php endif; ?>
        <?php if ($a['FotoDatoteka']): ?><div class="small mt-1"><a href="<?= e(foto_url($a['FotoDatoteka'])) ?>" target="_blank">fotografija</a></div><?php endif; ?>
        <?php if ($a['RazlogOdbijanja']): ?><div class="small text-danger mt-1">Razlog odbijanja: <?= e($a['RazlogOdbijanja']) ?></div><?php endif; ?>
        <?php if ((int) $a['Status'] === AKCIJA_CEKA && $a['UnioKorisnikId'] !== null && (int) $a['UnioKorisnikId'] !== (int) $k['Id']): ?>
            <div class="small text-muted mt-1">Upisao <?= e(ime_korisnika((int) $a['UnioKorisnikId'])) ?> – čeka odobrenje<?= $a['Bodovi'] !== null ? ' (predloženo ' . e(broj($a['Bodovi'], 2)) . ' b.)' : '' ?>.</div>
        <?php elseif ((int) $a['Status'] === AKCIJA_CEKA): ?>
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
