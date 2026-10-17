<?php
/** Grupni unos radne akcije (admin / Domar): jedna akcija, više članova – ide na odobrenje. Bodovi samo iz postavki (Vrste radnih akcija), ovdje se ne upisuju. */
trazi(P_AKCIJE_ODOBRI);
$k = korisnik();
$vrste = redovi('SELECT * FROM VrsteAkcija WHERE Aktivna=1 ORDER BY Naziv');
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime, c.SekcijaId FROM Clanovi c WHERE c.Status=0 AND ' . opseg_sql('c'));
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
$v = ['datum' => danas(), 'vrsta' => '', 'naziv' => '', 'sati' => '', 'opis' => '', 'odabrani' => [], 'oprema' => []];
if (($c0 = ul_int('clan')) !== null) {
    $v['odabrani'] = [$c0];
}
$dogId = ul_int('dogadjaj');
$dog = $dogId ? dogadjaj($dogId) : null;
if ($dog && !je_post()) {
    $v['datum'] = min(substr($dog['Pocetak'], 0, 10), danas());
    $v['opis'] = $dog['Naslov'] . ($dog['Mjesto'] ? ' – ' . $dog['Mjesto'] : '');
    $v['odabrani'] = array_map('intval', array_column(redovi('SELECT ClanId FROM DogadjajOdgovori WHERE DogadjajId=? AND Dolazi=1', [$dogId]), 'ClanId'));
    foreach ($vrste as $vr) {
        if (kljuc($vr['Naziv']) === kljuc($dog['Naslov'])) {
            $v['vrsta'] = (string) $vr['Id'];
        }
    }
}

if (je_post()) {
    $v = [
        'datum' => u_datum(ul_str('datum')) ?? danas(), 'vrsta' => (string) ($_POST['vrsta'] ?? ''), 'naziv' => ul_str('naziv'),
        'sati' => ul_str('sati'), 'opis' => ul_str('opis'),
        'odabrani' => array_map('intval', (array) ($_POST['odabrani'] ?? [])),
        'oprema' => array_map(fn($x) => array_map('intval', (array) $x), (array) ($_POST['oprema'] ?? [])), // [clanId => [opremaId, ...]]
    ];
    $vrstaId = (int) $v['vrsta'];
    // bodovi isključivo iz postavki vrste; „druga akcija“ bez vrste – bodove određuje admin pri odobrenju
    $bod = $vrstaId > 0 ? (float) vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=? AND Aktivna=1', [$vrstaId]) : null;
    $g = null;
    if ($v['vrsta'] === '' || $vrstaId === 0) {
        $g = 'Odaberite vrstu akcije.';
    } elseif ($vrstaId === -1 && $v['naziv'] === '') {
        $g = 'Upišite naziv akcije.';
    } elseif (!$v['odabrani']) {
        $g = 'Označite barem jednog člana.';
    } elseif ($v['datum'] > danas()) {
        $g = 'Datum ne može biti u budućnosti.';
    } elseif ($vrstaId > 0 && !vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=? AND Aktivna=1', [$vrstaId])) {
        $g = 'Odaberite vrstu akcije.';
    }
    if (!$g) {
        $dozvoljeni = array_flip(array_map('intval', array_column($clanovi, 'Id')));
        $novi = [];
        $vec = [];
        $ukupno = 0.0;
        $sOpremom = 0;
        $naziv = $vrstaId > 0 ? (string) vrijednost('SELECT Naziv FROM VrsteAkcija WHERE Id=?', [$vrstaId]) : $v['naziv'];
        transakcija(function () use ($v, $vrstaId, $bod, $dozvoljeni, $k, &$novi, &$vec, &$ukupno, &$sOpremom) {
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
                $opr = $vrstaId > 0 ? oprema_odabir($vrstaId, $v['oprema'][$cid] ?? []) : [];
                $ukupno += (float) $bod + oprema_zbroj($opr);
                $sOpremom += $opr ? 1 : 0;
                umetni('RadneAkcije', [
                    'ClanId' => $cid, 'Datum' => $v['datum'], 'VrstaAkcijeId' => $vrstaId > 0 ? $vrstaId : null, 'VrstaSlobodno' => $vrstaId > 0 ? null : $v['naziv'],
                    'Sati' => dec(u_broj($v['sati'])), 'Opis' => $v['opis'] !== '' ? $v['opis'] : null, 'Status' => AKCIJA_CEKA, 'Bodovi' => $bod === null ? null : dec($bod + oprema_zbroj($opr)),
                    'Oprema' => oprema_json($opr),
                    'RazlogOdbijanja' => null, 'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'], 'Kreirano' => sada(),
                    'OdlucioKorisnikId' => null, 'OdlucioIme' => null, 'Odluceno' => null,
                ]);
                $novi[] = $cid;
            }
        });
        $imena = fn(array $ids) => implode(', ', array_map(fn($c) => puno_ime($c), array_filter($clanovi, fn($c) => in_array((int) $c['Id'], $ids, true))));
        if (!$novi) {
            $g = 'Ništa nije spremljeno – svi označeni već imaju tu akciju za taj dan.';
        } else {
            $sazetak = count($novi) . ' članova × ' . ($bod === null ? 'bodove određuje admin' : broj($bod, 2) . ' b.') . ($sOpremom ? ' + oprema kod ' . $sOpremom . ' (ukupno ' . broj($ukupno, 2) . ' b.)' : '');
            dnevnik('Grupni unos radne akcije (na odobrenje)', null, null, date('d.m.Y', strtotime($v['datum'])) . " $naziv: " . $sazetak);
            $odobravatelji = [];
            foreach (array_unique(array_map(fn($c) => $c['SekcijaId'] === null ? null : (int) $c['SekcijaId'], array_filter($clanovi, fn($c) => in_array((int) $c['Id'], $novi, true)))) as $sid) {
                $odobravatelji = array_merge($odobravatelji, push_korisnici_s_pravom(P_AKCIJE_ODOBRI, $sid));
            }
            push_posalji($odobravatelji, 'akcije', '🛠️ Grupna radna akcija čeka odobrenje',
                $k['Naziv'] . ' · ' . $naziv . ' ' . date('d.m.Y', strtotime($v['datum'])) . ' · ' . count($novi) . ' članova', 'akcije', [], 'akcije-cekaju');
            poruka(e("Poslano na odobrenje: $naziv, " . date('d.m.Y', strtotime($v['datum'])) . ' – ' . $sazetak . '. Bodovi vrijede tek kad se odobre (Radne akcije → Na čekanju).'
                . ($vec ? ' Preskočeno (već upisano za taj dan): ' . $imena($vec) . '.' : '')));
            if ($dog) {
                q('UPDATE Dogadjaji SET AkcijeUpisane=? WHERE Id=?', [sada(), $dog['Id']]);
                preusmjeri('kalendar/termin', ['id' => $dog['Id']]);
            }
            preusmjeri('akcije/nova');
        }
    }
    poruka(e($g), 'danger');
}
$sekcije = moje_sekcije();
ob_start(); ?>
<h1 class="h3 mb-1">Nova radna akcija</h1>
<p class="text-muted">Upišite akciju jednom i označite sve koji su bili. Akcija ide <b>na odobrenje</b> s predloženim bodovima – bodovi vrijede tek kad se odobre (Radne akcije → Na čekanju).</p>
<?php if ($dog): ?><div class="alert alert-info py-2">Iz kalendara: <b><?= e($dog['Naslov']) ?></b> (<?= e(datum($dog['Pocetak'])) ?>) – označeni su članovi koji su potvrdili dolazak.</div><?php endif; ?>
<form method="post" action="<?= e(url('akcije/nova', $dog ? ['dogadjaj' => $dog['Id']] : [])) ?>" id="obrazac"><?= csrf() ?>
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
        <div class="col-8 col-md-2"><label class="form-label small">Bodovi po članu</label><input id="bodovi" class="form-control fw-bold bg-light" readonly tabindex="-1" value="" title="Iz postavki (Sustav → Vrste radnih akcija)"></div>
        <div class="col-12 small text-muted" id="bodoviNapomena">Bodovi se uzimaju iz postavki (Sustav → Vrste radnih akcija) i ovdje se ne mijenjaju.<span id="bodoviAdmin" hidden> Za „drugu akciju“ bodove određuje admin pri odobrenju.</span></div>
        <div class="col-12" id="opremaPolje" hidden>
            <div class="border rounded p-2 bg-light">
                <div class="small fw-semibold mb-1">🧰 Dodatna oprema – bodovi povrh osnovnih <span class="fw-normal text-muted">(iz postavki)</span></div>
                <div class="d-flex flex-wrap gap-2" id="opremaBodovi"></div>
                <div class="small text-muted mt-1">Kod svakog označenog člana kvačicom odaberite što je donio.</div>
            </div>
        </div>
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
                        <span><?= e(prezime_ime($c)) ?></span><b class="ms-auto small text-success clan-ukupno"></b></label>
                    <div class="clan-oprema d-flex flex-wrap gap-1 mt-1" data-cid="<?= (int) $c['Id'] ?>" hidden></div>
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
<script id="oprema-cfg" type="application/json"><?= json_encode(['oprema' => oprema_js(), 'odabrano' => $v['oprema']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
(function () {
    var vrsta = document.getElementById('vrsta'), bodovi = document.getElementById('bodovi'), naziv = document.getElementById('nazivPolje');
    var OC = JSON.parse(document.getElementById('oprema-cfg').textContent), OPR = OC.oprema || {};
    var odabrano = OC.odabrano || {};
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var br = function (x) { return String(Math.round(x * 100) / 100).replace('.', ','); };
    var lista = function () { return OPR[vrsta.value] || []; };
    var bodOpreme = function (id) { var o = lista().filter(function (x) { return String(x.id) === String(id); })[0]; return o ? o.b : 0; };
    // pamti kvačice opreme dok se mijenja vrsta / osvježava
    var zapamti = function () {
        document.querySelectorAll('.clan-oprema input').forEach(function (c) {
            var cid = c.dataset.cid, l = odabrano[cid] = (odabrano[cid] || []).filter(function (x) { return String(x) !== c.value; });
            if (c.checked) l.push(+c.value);
        });
    };
    var crtajOpremu = function () {
        var l = lista();
        document.getElementById('opremaPolje').hidden = !l.length;
        document.getElementById('opremaBodovi').innerHTML = l.map(function (o) {
            return '<span class="badge rounded-pill text-bg-light border fs-6 fw-normal">' + esc(o.n) + ' <b>+' + br(o.b) + ' b.</b></span>';
        }).join('');
        document.querySelectorAll('.clan-oprema').forEach(function (d) {
            var cid = d.dataset.cid, moje = (odabrano[cid] || []).map(String);
            d.innerHTML = l.map(function (o) {
                var on = moje.indexOf(String(o.id)) !== -1;
                return '<label class="btn btn-sm py-0 px-2 ' + (on ? 'btn-warning' : 'btn-outline-secondary') + '"><input type="checkbox" class="d-none" name="oprema[' + cid + '][]" data-cid="' + cid + '" value="' + o.id + '"' + (on ? ' checked' : '') + '>🧰 ' + esc(o.n) + '</label>';
            }).join('');
        });
    };
    var postaviBodove = function () {
        var o = vrsta.options[vrsta.selectedIndex];
        bodovi.value = o && o.dataset.bodovi ? o.dataset.bodovi.replace(',', '.') : '';
        document.getElementById('bodoviAdmin').hidden = vrsta.value !== '-1';
    };
    vrsta.addEventListener('change', function () {
        var o = vrsta.options[vrsta.selectedIndex];
        naziv.style.display = vrsta.value === '-1' ? '' : 'none';
        postaviBodove();
        zapamti(); crtajOpremu();
        osvjezi();
    });
    var norm = function (s) { return s.toLowerCase().replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'd'); };
    var sek = '', polja = Array.prototype.slice.call(document.querySelectorAll('.clan'));
    var filtriraj = function () {
        var t = norm(document.getElementById('trazi').value.trim());
        polja.forEach(function (p) { p.style.display = ((sek === '' || p.dataset.sek === sek) && p.dataset.ime.indexOf(t) >= 0) ? '' : 'none'; });
    };
    var osvjezi = function () {
        var imaOpr = lista().length > 0, osn = parseFloat(bodovi.value) || 0, zbroj = 0, sOpr = 0;
        var oz = polja.filter(function (p) {
            var c = p.querySelector('input[name="odabrani[]"]'), lab = p.querySelector('label'), d = p.querySelector('.clan-oprema'), uk = p.querySelector('.clan-ukupno');
            lab.classList.toggle('bg-success-subtle', c.checked); lab.classList.toggle('border-success', c.checked);
            d.hidden = !(c.checked && imaOpr);
            var dod = 0;
            d.querySelectorAll('input').forEach(function (x) {
                x.parentNode.classList.toggle('btn-warning', x.checked); x.parentNode.classList.toggle('btn-outline-secondary', !x.checked);
                if (x.checked && c.checked) dod += bodOpreme(x.value);
            });
            uk.textContent = c.checked && dod ? br(osn + dod) + ' b.' : '';
            if (c.checked) { zbroj += osn + dod; if (dod) sOpr++; }
            return c.checked;
        });
        var n = oz.length;
        document.getElementById('broj').textContent = n;
        document.getElementById('oznaceni').innerHTML = n ? '<b>Označeni:</b> ' + oz.map(function (p) { return esc(p.querySelector('span').textContent); }).join(', ') : '';
        document.getElementById('spremi').textContent = 'Pošalji na odobrenje za ' + n + ' ' + (n === 1 ? 'člana' : 'članova') + ' – ' + br(osn) + ' b. svakome'
            + (sOpr ? ' + oprema kod ' + sOpr + ' (ukupno ' + br(zbroj) + ' b.)' : '');
        if (vrsta.value === '-1') document.getElementById('spremi').textContent = 'Pošalji na odobrenje za ' + n + ' ' + (n === 1 ? 'člana' : 'članova') + ' – bodove određuje admin';
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
    postaviBodove();
    crtajOpremu();
    osvjezi();
})();
</script>
<?php stranica('Nova radna akcija', ob_get_clean());
