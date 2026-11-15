<?php
/** Pregled radnih akcija: odobravanje i odbijanje prijava članova. */
trazi(P_AKCIJE_CITAJ);
$k = korisnik();
$moze = ima(P_AKCIJE_ODOBRI);
// bodove ručno mijenja samo admin (Sustav); ostali odobravaju bodove iz postavki vrste (+ oprema)
$smijeBodove = ima(P_SUSTAV);
$prikaz = (int) ($_GET['status'] ?? AKCIJA_CEKA);
if (!isset(STATUSI_AKCIJE[$prikaz])) {
    $prikaz = AKCIJA_CEKA;
}
$vrste = redovi('SELECT * FROM VrsteAkcija ORDER BY Naziv');
$vrstaPoId = array_column($vrste, null, 'Id');

/** Akcija u opsegu ili null. */
$akcija = function (int $id) {
    return red('SELECT a.*, c.SekcijaId, c.Ime, c.Prezime FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId WHERE a.Id=? AND ' . opseg_sql('c'), [$id]);
};
/**
 * Izmjene odobravatelja prije odobrenja (samo akcija na čekanju): datum, sati, opis, vrsta i dodatna oprema.
 * Bodovi opreme: već upisana oprema zadržava svoje bodove, novo dodana uzima bodove iz postavki vrste.
 * Vraća ažurirani redak ili null ako su podaci neispravni (poruka je već postavljena).
 */
$primijeni = function (array $a) use ($vrstaPoId): ?array {
    $aid = (int) $a['Id'];
    if ((int) $a['Status'] !== AKCIJA_CEKA || !isset($_POST['izm'][$aid])) {
        return $a;
    }
    $u = (array) $_POST['izm'][$aid];
    $vrstaId = $a['VrstaAkcijeId'] !== null ? (int) $a['VrstaAkcijeId'] : null;
    $vU = $_POST['vrsta'][$aid] ?? null;
    if ($vU !== null && $vU !== '') {
        $vrstaId = (int) $vU ?: null;
    }
    $datum = u_datum((string) ($u['datum'] ?? '')) ?? substr((string) $a['Datum'], 0, 10);
    if ($datum > danas()) {
        poruka(prezime_ime($a) . ': datum ne može biti u budućnosti.', 'warning');
        return null;
    }
    $stara = [];
    foreach (oprema_lista($a['Oprema']) as $x) {
        $stara[(int) $x['id']] = $x['b'];
    }
    $opr = $vrstaId ? oprema_odabir($vrstaId, (array) ($u['oprema'] ?? [])) : [];
    foreach ($opr as &$x) {
        if (isset($stara[$x['id']])) {
            $x['b'] = (float) $stara[$x['id']];
        }
    }
    unset($x);
    $opis = trim((string) ($u['opis'] ?? ''));
    $nova = [
        'Datum' => $datum, 'Sati' => dec(u_broj($u['sati'] ?? '')), 'Opis' => $opis !== '' ? $opis : null,
        'VrstaAkcijeId' => $vrstaId, 'Oprema' => oprema_json($opr),
    ];
    if ($vrstaId && isset($vrstaPoId[$vrstaId])) {
        $nova['Bodovi'] = dec((float) $vrstaPoId[$vrstaId]['StandardniBodovi'] + oprema_zbroj($opr));
    }
    $promjene = [];
    foreach ($nova as $kljuc => $v) {
        if ((string) ($a[$kljuc] ?? '') !== (string) ($v ?? '')) {
            $promjene[$kljuc] = $v;
        }
    }
    if ($promjene) {
        azuriraj('RadneAkcije', $aid, $promjene);
        dnevnik('Izmijenjena radna akcija (prije odobrenja)', 'RadnaAkcija', $aid, prezime_ime($a) . ': ' . implode(', ', array_keys($promjene)));
        $a = array_merge($a, $promjene);
    }
    return $a;
};
$odobri = function (array $a, ?string $bodUnos, ?string $vrstaUnos) use ($k, $vrstaPoId, $smijeBodove): bool {
    $vrstaId = $a['VrstaAkcijeId'];
    if ($vrstaUnos !== null && $vrstaUnos !== '') {
        $vrstaId = (int) $vrstaUnos ?: null;
    }
    $izPostavki = $vrstaId && isset($vrstaPoId[$vrstaId]) ? (float) $vrstaPoId[$vrstaId]['StandardniBodovi'] + oprema_zbroj($a['Oprema']) : null;
    if ($smijeBodove) {
        $bod = u_broj($bodUnos) ?? ($a['Bodovi'] !== null ? (float) $a['Bodovi'] : $izPostavki);
    } else {
        $bod = $izPostavki; // upisani bodovi se ne uzimaju u obzir
    }
    if ($bod === null) {
        return false;
    }
    azuriraj('RadneAkcije', (int) $a['Id'], [
        'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec($bod), 'VrstaAkcijeId' => $vrstaId, 'RazlogOdbijanja' => null,
        'OdlucioKorisnikId' => $k['Id'], 'OdlucioIme' => $k['Naziv'], 'Odluceno' => sada(),
    ]);
    dnevnik('Odobrena radna akcija', 'RadnaAkcija', (int) $a['Id'], prezime_ime($a) . ' ' . datum($a['Datum']) . ': ' . broj($bod, 2) . ' b.');
    push_posalji(push_korisnici_clanova([(int) $a['ClanId']]), 'akcije', '✅ Radna akcija odobrena', datum($a['Datum']) . ' · ' . broj($bod, 2) . ' bodova', 'moje-akcije', [], 'akcija-' . $a['Id']);
    return true;
};

if (je_post() && $moze) {
    $bodovi = (array) ($_POST['bodovi'] ?? []);
    $vrstaU = (array) ($_POST['vrsta'] ?? []);
    if (isset($_POST['odobri'])) {
        $a = $akcija((int) $_POST['odobri']);
        $a = $a ? $primijeni($a) : null;
        if ($a && !$odobri($a, $bodovi[$a['Id']] ?? null, $vrstaU[$a['Id']] ?? null)) {
            poruka($smijeBodove ? 'Za vlastitu akciju upišite bodove.' : 'Akciju bez vrste boduje admin – odaberite vrstu ili je ostavite adminu.', 'warning');
        }
    } elseif (isset($_POST['odbij'])) {
        $a = $akcija((int) $_POST['odbij']);
        if ($a) {
            $razlog = trim((string) ($_POST['razlog'][$a['Id']] ?? ''));
            azuriraj('RadneAkcije', (int) $a['Id'], ['Status' => AKCIJA_ODBIJENO, 'Bodovi' => null, 'RazlogOdbijanja' => $razlog !== '' ? $razlog : null,
                'OdlucioKorisnikId' => $k['Id'], 'OdlucioIme' => $k['Naziv'], 'Odluceno' => sada()]);
            dnevnik('Odbijena radna akcija', 'RadnaAkcija', (int) $a['Id'], prezime_ime($a) . ' ' . datum($a['Datum']) . ($razlog ? ": $razlog" : ''));
            push_posalji(push_korisnici_clanova([(int) $a['ClanId']]), 'akcije', '❌ Radna akcija odbijena', datum($a['Datum']) . ($razlog !== '' ? ' · ' . $razlog : ''), 'moje-akcije', [], 'akcija-' . $a['Id']);
        }
    } elseif (isset($_POST['spremi'])) {
        $a = $akcija((int) $_POST['spremi']);
        if ($a && (int) $a['Status'] === AKCIJA_CEKA && $primijeni($a)) {
            poruka('Izmjene spremljene – akcija je i dalje na čekanju.', 'success');
        }
    } elseif (isset($_POST['vrati'])) {
        $a = $akcija((int) $_POST['vrati']);
        if ($a) {
            azuriraj('RadneAkcije', (int) $a['Id'], ['Status' => AKCIJA_CEKA, 'OdlucioKorisnikId' => null, 'OdlucioIme' => null, 'Odluceno' => null]);
            dnevnik('Radna akcija vraćena na čekanje', 'RadnaAkcija', (int) $a['Id'], prezime_ime($a));
        }
    } elseif (($_POST['radnja'] ?? '') === 'odobri-odabrane') {
        $n = 0;
        $presk = 0;
        foreach ((array) ($_POST['odabrani'] ?? []) as $id) {
            $a = $akcija((int) $id);
            if ($a && (int) $a['Status'] === AKCIJA_CEKA && ($a = $primijeni($a))) {
                $odobri($a, $bodovi[$a['Id']] ?? null, $vrstaU[$a['Id']] ?? null) ? $n++ : $presk++;
            }
        }
        poruka("Odobreno: $n." . ($presk ? " Preskočeno $presk (" . ($smijeBodove ? 'vlastite akcije bez upisanih bodova' : 'akcije bez vrste – boduje admin') . ").": ''), $presk ? 'warning' : 'success');
    }
    preusmjeri('akcije', ['status' => $prikaz]);
}

$lista = redovi('SELECT a.*, c.Ime, c.Prezime, s.Naziv AS Sekcija, v.Naziv AS VrstaNaziv, p.Ime AS PasIme, uk.ClanId AS UnioClanId FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId
    LEFT JOIN Korisnici uk ON uk.Id=a.UnioKorisnikId
    LEFT JOIN Sekcije s ON s.Id=c.SekcijaId LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId LEFT JOIN LovniPsi p ON p.Id=a.PasId
    WHERE a.Status=? AND ' . opseg_sql('c') . ' ORDER BY ' . ($prikaz === AKCIJA_CEKA ? 'a.Datum, a.Id' : 'a.Odluceno DESC, a.Id DESC LIMIT 300'), [$prikaz]);
$brojCeka = broj_akcija_na_cekanju();
$uredi = $moze && $prikaz === AKCIJA_CEKA;
$tab = fn(int $s, string $t) => '<li class="nav-item"><a class="nav-link ' . ($prikaz === $s ? 'active' : '') . '" href="' . e(url('akcije', ['status' => $s])) . '">' . $t . '</a></li>';
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">Radne akcije</h1>
    <?php if ($moze): ?><a class="btn btn-primary" href="<?= e(url('akcije/nova')) ?>">+ Nova radna akcija</a><?php endif; ?>
    <?php if (ima(P_IZVJESTAJI)): ?><a href="<?= e(url('izvjestaji/akcije')) ?>" class="btn btn-outline-secondary">Izvještaj / ispis</a><?php endif; ?>
    <a href="<?= e(url('akcije/bodovi')) ?>" class="btn btn-outline-secondary">📚 Arhiva bodova</a>
    <?php if (ima(P_SUSTAV)): ?><a href="<?= e(url('akcije/prijenos')) ?>" class="btn btn-outline-secondary">↪️ Prijenos</a><?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3">
    <?= $tab(AKCIJA_CEKA, 'Na čekanju <span class="badge ' . ($brojCeka ? 'bg-warning text-dark' : 'bg-secondary') . '">' . $brojCeka . '</span>') ?>
    <?= $tab(AKCIJA_ODOBRENO, 'Odobreno') ?>
    <?= $tab(AKCIJA_ODBIJENO, 'Odbijeno') ?>
</ul>
<form method="post" action="<?= e(url('akcije', ['status' => $prikaz])) ?>"><?= csrf() ?>
<?php if ($uredi && $lista): ?>
    <div class="mb-2 d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-success" name="radnja" value="odobri-odabrane" id="odobriOdabrane" disabled>Odobri odabrane (<span id="brojOdabranih">0</span>) <?= $smijeBodove ? 's upisanim bodovima' : 's bodovima iz postavki' ?></button>
        <label class="btn btn-sm btn-link mb-0"><input type="checkbox" class="d-none" data-sve="odabrani[]">Odaberi sve</label>
    </div>
<?php endif; ?>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr>
        <?php if ($uredi): ?><th></th><?php endif; ?>
        <th>Datum</th><th>Član</th><th>Vrsta</th><th>Sati</th><th>Opis</th><th>Bodovi</th>
        <?php if ($prikaz !== AKCIJA_CEKA): ?><th>Odlučio</th><?php endif; ?><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($lista as $a):
        $aid = (int) $a['Id'];
        $vlastita = $a['VrstaAkcijeId'] === null;
        $trebaBodove = $vlastita && $a['Bodovi'] === null;
        $oprBod = oprema_zbroj($a['Oprema']);
        $bodZadano = $a['Bodovi'] ?? ($a['VrstaAkcijeId'] && isset($vrstaPoId[$a['VrstaAkcijeId']]) ? (float) $vrstaPoId[$a['VrstaAkcijeId']]['StandardniBodovi'] + $oprBod : null); ?>
        <tr>
            <?php if ($uredi): ?><td><input type="checkbox" class="form-check-input" name="odabrani[]" value="<?= $aid ?>"></td><?php endif; ?>
            <td class="text-nowrap"><?= e(datum($a['Datum'])) ?></td>
            <td><a href="<?= e(url('clanovi/uredi', ['id' => $a['ClanId'], 'kartica' => 'akcije'])) ?>"><?= e(prezime_ime($a)) ?></a><div class="small text-muted"><?= e($a['Sekcija']) ?></div>
                <?php if ($a['UnioKorisnikId'] !== null && $a['UnioClanId'] !== null ? (int) $a['UnioClanId'] !== (int) $a['ClanId'] : $a['UnioKorisnikId'] !== null): ?><div class="small text-muted">upisao <?= e(ime_korisnika((int) $a['UnioKorisnikId'])) ?></div><?php endif; ?></td>
            <td>
                <?php if ($uredi): ?>
                    <select name="vrsta[<?= $aid ?>]" class="form-select form-select-sm vrsta-izbor" data-id="<?= $aid ?>" data-oprema="<?= e((string) $oprBod) ?>">
                        <?php if ($vlastita): ?><option value="0">Vlastita: <?= e($a['VrstaSlobodno']) ?></option><?php endif; ?>
                        <?php foreach ($vrste as $v): if (!$v['Aktivna'] && $v['Id'] != $a['VrstaAkcijeId']) continue; ?>
                            <option value="<?= (int) $v['Id'] ?>" data-bodovi="<?= e((string) (float) $v['StandardniBodovi']) ?>"<?= sel($v['Id'], $a['VrstaAkcijeId']) ?>><?= e($v['Naziv']) ?> (<?= e(broj($v['StandardniBodovi'], 1)) ?> b.)</option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?= e($a['VrstaNaziv'] ?? $a['VrstaSlobodno'] ?? '—') ?><?php if ($vlastita && $a['VrstaSlobodno']): ?><span class="badge bg-light text-dark border ms-1">vlastita</span><?php endif; ?>
                <?php endif; ?>
            </td>
            <td><?= e(broj($a['Sati'])) ?></td>
            <td class="small"><?= $a['Oprema'] ? '<div>' . oprema_html($a['Oprema']) . '</div>' : '' ?><?php if ($a['PasIme'] && !str_contains((string) $a['Opis'], (string) $a['PasIme'])): ?>🐕 <?= e($a['PasIme']) ?> <?php endif; ?><?= e($a['Opis']) ?>
                <?php if ($a['FotoDatoteka']): ?><div><a href="<?= e(foto_url($a['FotoDatoteka'])) ?>" target="_blank">fotografija</a></div><?php endif; ?>
                <?php if ($a['RazlogOdbijanja']): ?><div class="text-danger">Razlog: <?= e($a['RazlogOdbijanja']) ?></div><?php endif; ?></td>
            <td style="width:7rem">
                <?php if ($uredi): ?>
                    <?php if ($smijeBodove): ?>
                    <input name="bodovi[<?= $aid ?>]" id="bod<?= $aid ?>" type="number" step="0.5" min="0" placeholder="bodovi" class="form-control form-control-sm <?= $trebaBodove ? 'border-warning' : '' ?>" value="<?= $trebaBodove ? '' : e((string) (float) $bodZadano) ?>">
                    <?php else: ?>
                    <input id="bod<?= $aid ?>" class="form-control form-control-sm bg-light" readonly tabindex="-1" title="Bodovi iz postavki – mijenja samo admin" placeholder="admin" value="<?= $trebaBodove ? '' : e((string) (float) ($a['VrstaAkcijeId'] && isset($vrstaPoId[$a['VrstaAkcijeId']]) ? (float) $vrstaPoId[$a['VrstaAkcijeId']]['StandardniBodovi'] + $oprBod : $bodZadano)) ?>">
                    <?php endif; ?>
                <?php else: ?><?= e(broj($a['Bodovi'], 2)) ?><?php endif; ?>
            </td>
            <?php if ($prikaz !== AKCIJA_CEKA): ?><td class="small"><?= e($a['OdlucioIme']) ?><div class="text-muted"><?= e(datum($a['Odluceno'])) ?></div></td><?php endif; ?>
            <td class="text-end text-nowrap">
                <?php if ($uredi): ?>
                    <button class="btn btn-sm btn-success" name="odobri" value="<?= $aid ?>">Odobri</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-1" title="Ispravi prije odobrenja" onclick="document.getElementById('izm<?= $aid ?>').classList.toggle('d-none')">✏️ Uredi</button>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="var d=document.getElementById('odb<?= $aid ?>');d.classList.toggle('d-none');d.querySelector('input').focus()">Odbij</button>
                    <div class="input-group input-group-sm mt-1 d-none" id="odb<?= $aid ?>">
                        <input name="razlog[<?= $aid ?>]" class="form-control" placeholder="Razlog odbijanja">
                        <button class="btn btn-danger" name="odbij" value="<?= $aid ?>">Potvrdi</button>
                    </div>
                <?php elseif ($moze): ?>
                    <button class="btn btn-sm btn-link" name="vrati" value="<?= $aid ?>">Vrati na čekanje</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ($uredi):
            $oprIds = array_map(fn($x) => (int) $x['id'], oprema_lista($a['Oprema'])); ?>
        <tr class="d-none izmjena" id="izm<?= $aid ?>"><td colspan="9" class="bg-light">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Datum</label><input type="date" name="izm[<?= $aid ?>][datum]" class="form-control form-control-sm" value="<?= e(substr((string) $a['Datum'], 0, 10)) ?>" max="<?= danas() ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label small mb-0">Sati</label><input type="number" step="0.5" min="0" name="izm[<?= $aid ?>][sati]" class="form-control form-control-sm" value="<?= e($a['Sati'] !== null ? (string) (float) $a['Sati'] : '') ?>"></div>
                <div class="col-12 col-md-8"><label class="form-label small mb-0">Opis</label><input name="izm[<?= $aid ?>][opis]" class="form-control form-control-sm" value="<?= e((string) $a['Opis']) ?>"></div>
                <div class="col-12">
                    <div class="small fw-semibold">🧰 Dodatna oprema <span class="fw-normal text-muted">(bodovi iz postavki)</span></div>
                    <div class="d-flex flex-wrap gap-1 izm-oprema" data-id="<?= $aid ?>" data-odabrano="<?= e(json_encode($oprIds)) ?>" data-stara="<?= e(json_encode(array_column(oprema_lista($a['Oprema']), 'b', 'id'))) ?>"></div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-sm btn-primary" name="spremi" value="<?= $aid ?>">💾 Spremi izmjene</button>
                    <button class="btn btn-sm btn-success" name="odobri" value="<?= $aid ?>">Spremi i odobri</button>
                </div>
            </div>
        </td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="9" class="text-muted text-center py-4">Nema zapisa.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
</form>
<?php if ($prikaz !== AKCIJA_CEKA): ?><p class="small text-muted">Prikazano zadnjih 300 zapisa. Za odabir razdoblja koristite izvještaj.</p><?php endif; ?>
<?php if ($uredi): ?><script id="oprema-cfg" type="application/json"><?= json_encode(oprema_js(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?>
<script>
(function () {
    var osvj = function () {
        var n = document.querySelectorAll('input[name="odabrani[]"]:checked').length, b = document.getElementById('odobriOdabrane');
        if (!b) return; b.disabled = n === 0; document.getElementById('brojOdabranih').textContent = n;
    };
    document.addEventListener('change', osvj); document.addEventListener('ev-oznaceno', osvj);
    var cfg = document.getElementById('oprema-cfg'), OPR = cfg ? JSON.parse(cfg.textContent) : {};
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var oprBod = function (id) {
        var d = document.querySelector('.izm-oprema[data-id="' + id + '"]'), st = d ? JSON.parse(d.dataset.stara || '{}') : {}, z = 0;
        if (!d) return null;
        d.querySelectorAll('input:checked').forEach(function (c) { z += st[c.value] !== undefined ? parseFloat(st[c.value]) : parseFloat(c.dataset.b) || 0; });
        return z;
    };
    var bodovi = function (id) {
        var s = document.querySelector('.vrsta-izbor[data-id="' + id + '"]'), o = s ? s.options[s.selectedIndex] : null, z = oprBod(id);
        if (!o || !o.dataset.bodovi) return;
        document.getElementById('bod' + id).value = Math.round((parseFloat(o.dataset.bodovi) + (z === null ? (parseFloat(s.dataset.oprema) || 0) : z)) * 100) / 100;
    };
    var crtaj = function (id) {
        var d = document.querySelector('.izm-oprema[data-id="' + id + '"]'), s = document.querySelector('.vrsta-izbor[data-id="' + id + '"]');
        if (!d || !s) return;
        var odab = d.querySelectorAll('input').length ? Array.prototype.map.call(d.querySelectorAll('input:checked'), function (c) { return +c.value; }) : JSON.parse(d.dataset.odabrano || '[]');
        var l = OPR[s.value] || [];
        d.innerHTML = l.length ? l.map(function (o) {
            var on = odab.indexOf(o.id) >= 0;
            return '<label class="btn btn-sm py-0 px-2 ' + (on ? 'btn-warning' : 'btn-outline-secondary') + '"><input type="checkbox" class="d-none" name="izm[' + id + '][oprema][]" value="' + o.id + '" data-b="' + o.b + '"' + (on ? ' checked' : '') + '>🧰 ' + esc(o.n) + ' +' + o.b + '</label>';
        }).join('') : '<span class="small text-muted">Ova vrsta nema dodatne opreme.</span>';
    };
    document.querySelectorAll('.izm-oprema').forEach(function (d) { crtaj(d.dataset.id); });
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t.closest && t.closest('.izm-oprema')) {
            var l = t.closest('label'); l.classList.toggle('btn-warning', t.checked); l.classList.toggle('btn-outline-secondary', !t.checked);
            bodovi(t.closest('.izm-oprema').dataset.id);
        }
    });
    document.querySelectorAll('.vrsta-izbor').forEach(function (s) {
        // nova vrsta: standardni bodovi + bodovi opreme (oprema se nudi prema vrsti)
        s.addEventListener('change', function () { crtaj(s.dataset.id); bodovi(s.dataset.id); });
    });
})();
</script>
<?php stranica('Radne akcije', ob_get_clean());
