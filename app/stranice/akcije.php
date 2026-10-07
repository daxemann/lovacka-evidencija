<?php
/** Pregled radnih akcija: odobravanje i odbijanje prijava članova. */
trazi(P_AKCIJE_CITAJ);
$k = korisnik();
$moze = ima(P_AKCIJE_ODOBRI);
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
$odobri = function (array $a, ?string $bodUnos, ?string $vrstaUnos) use ($k, $vrstaPoId): bool {
    $vrstaId = $a['VrstaAkcijeId'];
    if ($vrstaUnos !== null && $vrstaUnos !== '') {
        $vrstaId = (int) $vrstaUnos ?: null;
    }
    $bod = u_broj($bodUnos);
    if ($bod === null) {
        $bod = $a['Bodovi'] !== null ? (float) $a['Bodovi'] : ($vrstaId && isset($vrstaPoId[$vrstaId]) ? (float) $vrstaPoId[$vrstaId]['StandardniBodovi'] : null);
    }
    if ($bod === null) {
        return false;
    }
    azuriraj('RadneAkcije', (int) $a['Id'], [
        'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec($bod), 'VrstaAkcijeId' => $vrstaId, 'RazlogOdbijanja' => null,
        'OdlucioKorisnikId' => $k['Id'], 'OdlucioIme' => $k['Naziv'], 'Odluceno' => sada(),
    ]);
    dnevnik('Odobrena radna akcija', 'RadnaAkcija', (int) $a['Id'], prezime_ime($a) . ' ' . datum($a['Datum']) . ': ' . broj($bod, 2) . ' b.');
    return true;
};

if (je_post() && $moze) {
    $bodovi = (array) ($_POST['bodovi'] ?? []);
    $vrstaU = (array) ($_POST['vrsta'] ?? []);
    if (isset($_POST['odobri'])) {
        $a = $akcija((int) $_POST['odobri']);
        if ($a && !$odobri($a, $bodovi[$a['Id']] ?? null, $vrstaU[$a['Id']] ?? null)) {
            poruka('Za vlastitu akciju upišite bodove.', 'warning');
        }
    } elseif (isset($_POST['odbij'])) {
        $a = $akcija((int) $_POST['odbij']);
        if ($a) {
            $razlog = trim((string) ($_POST['razlog'][$a['Id']] ?? ''));
            azuriraj('RadneAkcije', (int) $a['Id'], ['Status' => AKCIJA_ODBIJENO, 'Bodovi' => null, 'RazlogOdbijanja' => $razlog !== '' ? $razlog : null,
                'OdlucioKorisnikId' => $k['Id'], 'OdlucioIme' => $k['Naziv'], 'Odluceno' => sada()]);
            dnevnik('Odbijena radna akcija', 'RadnaAkcija', (int) $a['Id'], prezime_ime($a) . ' ' . datum($a['Datum']) . ($razlog ? ": $razlog" : ''));
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
            if ($a && (int) $a['Status'] === AKCIJA_CEKA) {
                $odobri($a, $bodovi[$a['Id']] ?? null, $vrstaU[$a['Id']] ?? null) ? $n++ : $presk++;
            }
        }
        poruka("Odobreno: $n." . ($presk ? " Preskočeno $presk (vlastite akcije bez upisanih bodova)." : ''), $presk ? 'warning' : 'success');
    }
    preusmjeri('akcije', ['status' => $prikaz]);
}

$lista = redovi('SELECT a.*, c.Ime, c.Prezime, s.Naziv AS Sekcija, v.Naziv AS VrstaNaziv FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId
    LEFT JOIN Sekcije s ON s.Id=c.SekcijaId LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId
    WHERE a.Status=? AND ' . opseg_sql('c') . ' ORDER BY ' . ($prikaz === AKCIJA_CEKA ? 'a.Datum, a.Id' : 'a.Odluceno DESC, a.Id DESC LIMIT 300'), [$prikaz]);
$brojCeka = broj_akcija_na_cekanju();
$uredi = $moze && $prikaz === AKCIJA_CEKA;
$tab = fn(int $s, string $t) => '<li class="nav-item"><a class="nav-link ' . ($prikaz === $s ? 'active' : '') . '" href="' . e(url('akcije', ['status' => $s])) . '">' . $t . '</a></li>';
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">Radne akcije</h1>
    <?php if ($moze): ?><a class="btn btn-primary" href="<?= e(url('akcije/nova')) ?>">+ Nova radna akcija</a><?php endif; ?>
    <?php if (ima(P_IZVJESTAJI)): ?><a href="<?= e(url('izvjestaji/akcije')) ?>" class="btn btn-outline-secondary">Izvještaj / ispis</a><?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3">
    <?= $tab(AKCIJA_CEKA, 'Na čekanju <span class="badge ' . ($brojCeka ? 'bg-warning text-dark' : 'bg-secondary') . '">' . $brojCeka . '</span>') ?>
    <?= $tab(AKCIJA_ODOBRENO, 'Odobreno') ?>
    <?= $tab(AKCIJA_ODBIJENO, 'Odbijeno') ?>
</ul>
<form method="post" action="<?= e(url('akcije', ['status' => $prikaz])) ?>"><?= csrf() ?>
<?php if ($uredi && $lista): ?>
    <div class="mb-2 d-flex gap-2 align-items-center">
        <button class="btn btn-sm btn-success" name="radnja" value="odobri-odabrane" id="odobriOdabrane" disabled>Odobri odabrane (<span id="brojOdabranih">0</span>) s upisanim bodovima</button>
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
        $bodZadano = $a['Bodovi'] ?? ($a['VrstaAkcijeId'] ? ($vrstaPoId[$a['VrstaAkcijeId']]['StandardniBodovi'] ?? null) : null); ?>
        <tr>
            <?php if ($uredi): ?><td><input type="checkbox" class="form-check-input" name="odabrani[]" value="<?= $aid ?>"></td><?php endif; ?>
            <td class="text-nowrap"><?= e(datum($a['Datum'])) ?></td>
            <td><a href="<?= e(url('clanovi/uredi', ['id' => $a['ClanId'], 'kartica' => 'akcije'])) ?>"><?= e(prezime_ime($a)) ?></a><div class="small text-muted"><?= e($a['Sekcija']) ?></div></td>
            <td>
                <?php if ($uredi): ?>
                    <select name="vrsta[<?= $aid ?>]" class="form-select form-select-sm vrsta-izbor" data-id="<?= $aid ?>">
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
            <td class="small"><?= e($a['Opis']) ?>
                <?php if ($a['FotoDatoteka']): ?><div><a href="<?= e(foto_url($a['FotoDatoteka'])) ?>" target="_blank">fotografija</a></div><?php endif; ?>
                <?php if ($a['RazlogOdbijanja']): ?><div class="text-danger">Razlog: <?= e($a['RazlogOdbijanja']) ?></div><?php endif; ?></td>
            <td style="width:7rem">
                <?php if ($uredi): ?>
                    <input name="bodovi[<?= $aid ?>]" id="bod<?= $aid ?>" type="number" step="0.5" min="0" placeholder="bodovi" class="form-control form-control-sm <?= $trebaBodove ? 'border-warning' : '' ?>" value="<?= $trebaBodove ? '' : e((string) (float) $bodZadano) ?>">
                <?php else: ?><?= e(broj($a['Bodovi'], 2)) ?><?php endif; ?>
            </td>
            <?php if ($prikaz !== AKCIJA_CEKA): ?><td class="small"><?= e($a['OdlucioIme']) ?><div class="text-muted"><?= e(datum($a['Odluceno'])) ?></div></td><?php endif; ?>
            <td class="text-end text-nowrap">
                <?php if ($uredi): ?>
                    <button class="btn btn-sm btn-success" name="odobri" value="<?= $aid ?>">Odobri</button>
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
    <?php endforeach; ?>
    <?php if (!$lista): ?><tr><td colspan="9" class="text-muted text-center py-4">Nema zapisa.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
</form>
<?php if ($prikaz !== AKCIJA_CEKA): ?><p class="small text-muted">Prikazano zadnjih 300 zapisa. Za odabir razdoblja koristite izvještaj.</p><?php endif; ?>
<script>
(function () {
    var osvj = function () {
        var n = document.querySelectorAll('input[name="odabrani[]"]:checked').length, b = document.getElementById('odobriOdabrane');
        if (!b) return; b.disabled = n === 0; document.getElementById('brojOdabranih').textContent = n;
    };
    document.addEventListener('change', osvj); document.addEventListener('ev-oznaceno', osvj);
    document.querySelectorAll('.vrsta-izbor').forEach(function (s) {
        s.addEventListener('change', function () { var o = s.options[s.selectedIndex]; if (o.dataset.bodovi) document.getElementById('bod' + s.dataset.id).value = o.dataset.bodovi; });
    });
})();
</script>
<?php stranica('Radne akcije', ob_get_clean());
