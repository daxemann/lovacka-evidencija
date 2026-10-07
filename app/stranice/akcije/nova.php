<?php
/** Grupni unos radne akcije (admin / Domar): jedna akcija, više članova, isti bodovi, odmah odobreno. */
trazi(P_AKCIJE_ODOBRI);
$k = korisnik();
$vrste = redovi('SELECT * FROM VrsteAkcija WHERE Aktivna=1 ORDER BY Naziv');
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime, c.SekcijaId FROM Clanovi c WHERE c.Status=0 AND ' . opseg_sql('c'));
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
$v = ['datum' => danas(), 'vrsta' => '', 'naziv' => '', 'sati' => '', 'bodovi' => '', 'opis' => '', 'odabrani' => []];
if (($c0 = ul_int('clan')) !== null) {
    $v['odabrani'] = [$c0];
}

if (je_post()) {
    $v = [
        'datum' => u_datum(ul_str('datum')) ?? danas(), 'vrsta' => (string) ($_POST['vrsta'] ?? ''), 'naziv' => ul_str('naziv'),
        'sati' => ul_str('sati'), 'bodovi' => ul_str('bodovi'), 'opis' => ul_str('opis'),
        'odabrani' => array_map('intval', (array) ($_POST['odabrani'] ?? [])),
    ];
    $vrstaId = (int) $v['vrsta'];
    $bod = u_broj($v['bodovi']);
    $g = null;
    if ($v['vrsta'] === '' || $vrstaId === 0) {
        $g = 'Odaberite vrstu akcije.';
    } elseif ($vrstaId === -1 && $v['naziv'] === '') {
        $g = 'Upišite naziv akcije.';
    } elseif (!$v['odabrani']) {
        $g = 'Označite barem jednog člana.';
    } elseif ($v['datum'] > danas()) {
        $g = 'Datum ne može biti u budućnosti.';
    } elseif ($bod === null) {
        $g = 'Upišite bodove po članu.';
    }
    if (!$g) {
        $dozvoljeni = array_flip(array_map('intval', array_column($clanovi, 'Id')));
        $novi = [];
        $vec = [];
        $naziv = $vrstaId > 0 ? (string) vrijednost('SELECT Naziv FROM VrsteAkcija WHERE Id=?', [$vrstaId]) : $v['naziv'];
        transakcija(function () use ($v, $vrstaId, $bod, $dozvoljeni, $k, &$novi, &$vec) {
            foreach (array_unique($v['odabrani']) as $cid) {
                if (!isset($dozvoljeni[$cid])) {
                    continue;
                }
                $postoji = $vrstaId > 0
                    ? vrijednost('SELECT 1 FROM RadneAkcije WHERE ClanId=? AND substr(Datum,1,10)=? AND VrstaAkcijeId=?', [$cid, $v['datum'], $vrstaId])
                    : vrijednost('SELECT 1 FROM RadneAkcije WHERE ClanId=? AND substr(Datum,1,10)=? AND VrstaAkcijeId IS NULL AND lower(VrstaSlobodno)=lower(?)', [$cid, $v['datum'], $v['naziv']]);
                if ($postoji) {
                    $vec[] = $cid;
                    continue;
                }
                umetni('RadneAkcije', [
                    'ClanId' => $cid, 'Datum' => $v['datum'], 'VrstaAkcijeId' => $vrstaId > 0 ? $vrstaId : null, 'VrstaSlobodno' => $vrstaId > 0 ? null : $v['naziv'],
                    'Sati' => dec(u_broj($v['sati'])), 'Opis' => $v['opis'] !== '' ? $v['opis'] : null, 'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec($bod),
                    'RazlogOdbijanja' => null, 'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'], 'Kreirano' => sada(),
                    'OdlucioKorisnikId' => $k['Id'], 'OdlucioIme' => $k['Naziv'], 'Odluceno' => sada(),
                ]);
                $novi[] = $cid;
            }
        });
        $imena = fn(array $ids) => implode(', ', array_map(fn($c) => puno_ime($c), array_filter($clanovi, fn($c) => in_array((int) $c['Id'], $ids, true))));
        if (!$novi) {
            $g = 'Ništa nije spremljeno – svi označeni već imaju tu akciju za taj dan.';
        } else {
            dnevnik('Grupni unos radne akcije', null, null, date('d.m.Y', strtotime($v['datum'])) . " $naziv: " . count($novi) . ' članova × ' . broj($bod, 2) . ' b.');
            poruka(e("Spremljeno: $naziv, " . date('d.m.Y', strtotime($v['datum'])) . ' – ' . count($novi) . ' članova × ' . broj($bod, 2) . ' b.'
                . ($vec ? ' Preskočeno (već upisano za taj dan): ' . $imena($vec) . '.' : '')));
            preusmjeri('akcije/nova');
        }
    }
    poruka(e($g), 'danger');
}
$sekcije = moje_sekcije();
ob_start(); ?>
<h1 class="h3 mb-1">Nova radna akcija</h1>
<p class="text-muted">Upišite akciju jednom i označite sve koji su bili – svi dobiju iste bodove (odmah odobreno).</p>
<form method="post" action="<?= e(url('akcije/nova')) ?>" id="obrazac"><?= csrf() ?>
<div class="card mb-3">
    <div class="card-header"><b>1. Akcija</b></div>
    <div class="card-body"><div class="row g-2">
        <div class="col-6 col-md-2"><label class="form-label small">Datum</label><input type="date" name="datum" class="form-control" value="<?= e($v['datum']) ?>" max="<?= danas() ?>" required></div>
        <div class="col-6 col-md-4"><label class="form-label small">Vrsta</label>
            <select name="vrsta" id="vrsta" class="form-select" required>
                <option value="">— odaberi —</option>
                <?php foreach ($vrste as $vr): ?><option value="<?= (int) $vr['Id'] ?>" data-bodovi="<?= e(broj($vr['StandardniBodovi'], 1)) ?>"<?= sel($v['vrsta'], $vr['Id']) ?>><?= e($vr['Naziv']) ?> (<?= e(broj($vr['StandardniBodovi'], 1)) ?> b.)</option><?php endforeach; ?>
                <option value="-1"<?= sel($v['vrsta'], -1) ?>>Druga akcija (upiši naziv)…</option>
            </select></div>
        <div class="col-12 col-md-6" id="nazivPolje" style="<?= $v['vrsta'] === '-1' ? '' : 'display:none' ?>"><label class="form-label small">Naziv akcije</label><input name="naziv" class="form-control" maxlength="100" value="<?= e($v['naziv']) ?>"></div>
        <div class="col-4 col-md-2"><label class="form-label small">Sati</label><input name="sati" type="number" step="0.5" min="0" class="form-control" value="<?= e($v['sati']) ?>"></div>
        <div class="col-8 col-md-2"><label class="form-label small">Bodovi po članu</label><input name="bodovi" id="bodovi" type="number" step="0.5" min="0" class="form-control fw-bold" value="<?= e($v['bodovi']) ?>" required></div>
        <div class="col-12"><label class="form-label small">Opis – što je napravljeno, gdje</label><textarea name="opis" class="form-control" rows="2"><?= e($v['opis']) ?></textarea></div>
    </div></div>
</div>
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <b>2. Tko je bio?</b> <span class="badge bg-success"><span id="broj">0</span> označeno</span>
        <input id="trazi" class="form-control form-control-sm ms-auto" style="max-width:220px" placeholder="Traži ime" autocomplete="off">
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-2" id="sekcijeFilter">
            <button type="button" class="btn btn-sm btn-secondary" data-sek="">Svi</button>
            <?php foreach ($sekcije as $s): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-sek="<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></button><?php endforeach; ?>
            <?php if (array_filter($clanovi, fn($c) => $c['SekcijaId'] === null)): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-sek="0">Nije raspoređeno</button><?php endif; ?>
            <span class="ms-auto"></span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="sviPrikazani">Označi sve prikazane</button>
            <button type="button" class="btn btn-sm btn-link" id="ponisti">Poništi</button>
        </div>
        <div class="row g-1">
            <?php foreach ($clanovi as $c): $o = in_array((int) $c['Id'], $v['odabrani'], true); ?>
                <div class="col-6 col-md-4 col-lg-3 clan" data-sek="<?= (int) $c['SekcijaId'] ?>" data-ime="<?= e(kljuc(puno_ime($c))) ?>">
                    <label class="d-flex align-items-center gap-2 border rounded px-2 py-2 klikabilno <?= $o ? 'bg-success-subtle border-success' : '' ?>">
                        <input type="checkbox" class="form-check-input m-0" name="odabrani[]" value="<?= (int) $c['Id'] ?>"<?= chk($o) ?>>
                        <span><?= e(prezime_ime($c)) ?></span></label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="small mt-2" id="oznaceni"></div>
    </div>
</div>
<div class="sticky-bottom bg-white py-2 border-top">
    <button class="btn btn-primary btn-lg w-100" id="spremi">Spremi</button>
</div>
</form>
<script>
(function () {
    var vrsta = document.getElementById('vrsta'), bodovi = document.getElementById('bodovi'), naziv = document.getElementById('nazivPolje');
    vrsta.addEventListener('change', function () {
        var o = vrsta.options[vrsta.selectedIndex];
        naziv.style.display = vrsta.value === '-1' ? '' : 'none';
        if (o.dataset.bodovi) bodovi.value = o.dataset.bodovi.replace(',', '.');
        osvjezi();
    });
    var norm = function (s) { return s.toLowerCase().replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'd'); };
    var sek = '', polja = Array.prototype.slice.call(document.querySelectorAll('.clan'));
    var filtriraj = function () {
        var t = norm(document.getElementById('trazi').value.trim());
        polja.forEach(function (p) { p.style.display = ((sek === '' || p.dataset.sek === sek) && p.dataset.ime.indexOf(t) >= 0) ? '' : 'none'; });
    };
    var osvjezi = function () {
        var oz = polja.filter(function (p) { var c = p.querySelector('input'); p.querySelector('label').classList.toggle('bg-success-subtle', c.checked); p.querySelector('label').classList.toggle('border-success', c.checked); return c.checked; });
        var n = oz.length, b = (bodovi.value || '0').replace('.', ',');
        document.getElementById('broj').textContent = n;
        document.getElementById('oznaceni').innerHTML = n ? '<b>Označeni:</b> ' + oz.map(function (p) { return p.querySelector('span').textContent; }).join(', ') : '';
        document.getElementById('spremi').textContent = 'Spremi za ' + n + ' ' + (n === 1 ? 'člana' : 'članova') + ' – ' + b + ' b. svakome';
    };
    document.getElementById('trazi').addEventListener('input', filtriraj);
    document.querySelectorAll('#sekcijeFilter [data-sek]').forEach(function (b) {
        b.addEventListener('click', function () {
            sek = b.dataset.sek;
            document.querySelectorAll('#sekcijeFilter [data-sek]').forEach(function (x) { x.classList.toggle('btn-secondary', x === b); x.classList.toggle('btn-outline-secondary', x !== b); });
            filtriraj();
        });
    });
    document.getElementById('sviPrikazani').addEventListener('click', function () { polja.forEach(function (p) { if (p.style.display !== 'none') p.querySelector('input').checked = true; }); osvjezi(); });
    document.getElementById('ponisti').addEventListener('click', function () { polja.forEach(function (p) { p.querySelector('input').checked = false; }); osvjezi(); });
    document.addEventListener('change', osvjezi);
    bodovi.addEventListener('input', osvjezi);
    osvjezi();
})();
</script>
<?php stranica('Nova radna akcija', ob_get_clean());
