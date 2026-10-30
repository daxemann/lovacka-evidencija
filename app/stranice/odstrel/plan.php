<?php
/** 📋 Plan odstrela po sekciji i lovnoj godini: planirano / ostvareno. Uređuje tko ima „Odstrel – upis“ za sekciju. */
$sekcije = odstrel_sekcije();
if (!$sekcije) {
    zabranjeno();
}
$godine = odstrel_godine();
$g = (int) ul('g', odstrel_lovna_godina());
if ($g < 2000 || $g > 2100) {
    $g = odstrel_lovna_godina();
}
if (!in_array($g, $godine, true)) {
    $godine[] = $g;
    rsort($godine);
}
$sid = (int) ul('s', '');
if (!array_key_exists($sid, $sekcije)) {
    $sid = (int) array_key_first($sekcije);
}
$moze = odstrel_moze_urediti($sid ?: null);
$sekSql = $sid ? 'SekcijaId=' . $sid : 'SekcijaId IS NULL';

if (je_post()) {
    if (!$moze) {
        zabranjeno();
    }
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'spremi') {
        foreach ((array) ($_POST['broj'] ?? []) as $pid => $b) {
            q("UPDATE OdstrelPlan SET Broj=? WHERE Id=? AND $sekSql AND LovnaGodina=?", [max(0, (int) $b), (int) $pid, $g]);
        }
        $vrsta = mb_substr(ul_str('nova_vrsta'), 0, 60);
        if ($vrsta !== '') {
            $kat = mb_substr(ul_str('nova_kategorija'), 0, 30);
            if (vrijednost("SELECT 1 FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=? AND Vrsta=? AND Kategorija=?", [$g, $vrsta, $kat])) {
                poruka('Taj red već postoji u planu.', 'warning');
            } else {
                umetni('OdstrelPlan', ['SekcijaId' => $sid ?: null, 'LovnaGodina' => $g, 'Vrsta' => $vrsta, 'Kategorija' => $kat, 'Broj' => max(0, (int) ($_POST['novi_broj'] ?? 0))]);
            }
        }
        dnevnik('Plan odstrela', null, null, odstrel_lg_naziv($g) . ' · ' . $sekcije[$sid]);
        poruka('Plan spremljen.');
    } elseif ($radnja === 'obrisi') {
        q("DELETE FROM OdstrelPlan WHERE Id=? AND $sekSql", [(int) ($_POST['id'] ?? 0)]);
    } elseif ($radnja === 'kopiraj') {
        // preuzmi plan iz prošle lovne godine (samo redovi koji još ne postoje)
        $n = 0;
        foreach (redovi("SELECT * FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=?", [$g - 1]) as $p) {
            if (!vrijednost("SELECT 1 FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=? AND Vrsta=? AND Kategorija=?", [$g, $p['Vrsta'], $p['Kategorija']])) {
                umetni('OdstrelPlan', ['SekcijaId' => $sid ?: null, 'LovnaGodina' => $g, 'Vrsta' => $p['Vrsta'], 'Kategorija' => $p['Kategorija'], 'Broj' => $p['Broj']]);
                $n++;
            }
        }
        poruka($n ? "Preuzeto redova iz prošle godine: $n." : 'Nema ništa za preuzeti.', $n ? 'success' : 'secondary');
    }
    preusmjeri('odstrel/plan', ['g' => $g, 's' => $sid]);
}
$plan = odstrel_plan($sid, $g);
$ukPlan = array_sum(array_map(fn($r) => $r['kategorija'] === '' ? (int) $r['plan'] : 0, $plan));
$imaProsle = (bool) vrijednost("SELECT 1 FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=?", [$g - 1]);
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('odstrel', ['g' => $g, 's' => $sid])) ?>">← Odstrel</a>
    <h1 class="h4 mb-0 me-auto">📋 Plan odstrela</h1>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/odstrel-pdf', ['g' => $g, 's' => $sid, 'plan' => 1])) ?>">📄 PDF plana</a>
</div>
<form class="row g-2 align-items-end mb-3" method="get"><input type="hidden" name="p" value="odstrel/plan">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Lovna godina</label>
        <select name="g" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach (array_unique(array_merge([$g + 1], $godine)) as $x): ?><option value="<?= $x ?>"<?= sel($x, $g) ?>><?= e(odstrel_lg_naziv($x)) ?></option><?php endforeach; ?>
        </select></div>
    <?php if (count($sekcije) > 1): ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Sekcija</label>
        <select name="s" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($sekcije as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel($id, $sid) ?>><?= e($n) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
</form>
<p class="small text-muted"><?= e($sekcije[$sid]) ?> · lovna godina <?= e(odstrel_lg_naziv($g)) ?> (1.4.–31.3.). Ostvareno se računa iz upisanog odstrela: red bez kategorije broji sva grla vrste, red s kategorijom (npr. Vepar) samo ta grla.</p>

<form method="post"><?= csrf() ?><input type="hidden" name="radnja" value="spremi">
<div class="table-responsive"><table class="table table-sm align-middle odstrel-plan">
    <thead><tr><th>Vrsta</th><th>Kategorija</th><th class="text-end" style="width:7rem">Plan</th><th class="text-end">Ostvareno</th><th style="width:30%"></th><th></th></tr></thead>
    <tbody>
    <?php $preth = null; foreach ($plan as $r): $novaVrsta = $r['vrsta'] !== $preth; $preth = $r['vrsta']; $pct = $r['plan'] ? min(100, round($r['ostvareno'] / $r['plan'] * 100)) : null;
        $boja = $r['plan'] === null ? 'bg-secondary' : ($r['ostvareno'] > $r['plan'] ? 'bg-danger' : ($pct >= 100 ? 'bg-success' : 'bg-warning')); ?>
        <tr class="<?= $r['kategorija'] !== '' ? 'odstrel-plan-kat' : 'fw-semibold' ?>">
            <td><?= $r['kategorija'] === '' || $novaVrsta ? e($r['vrsta']) : '' ?></td>
            <td><?= $r['kategorija'] !== '' ? '↳ ' . e($r['kategorija']) : '<span class="text-muted small">ukupno</span>' ?></td>
            <td class="text-end"><?php if ($r['id'] && $moze): ?><input type="number" min="0" name="broj[<?= $r['id'] ?>]" value="<?= (int) $r['plan'] ?>" class="form-control form-control-sm text-end"><?php else: ?><?= $r['plan'] === null ? '<span class="text-muted small">nije u planu</span>' : (int) $r['plan'] ?><?php endif; ?></td>
            <td class="text-end"><?= (int) $r['ostvareno'] ?><?= $r['plan'] ? ' <span class="text-muted small">/ ' . (int) $r['plan'] . '</span>' : '' ?></td>
            <td><?php if ($r['plan']): ?><div class="progress" role="progressbar" aria-valuenow="<?= (int) $pct ?>" aria-valuemin="0" aria-valuemax="100" style="height:.8rem"><div class="progress-bar <?= $boja ?>" style="width:<?= (int) $pct ?>%"></div></div>
                <div class="small text-muted"><?= $r['ostvareno'] > $r['plan'] ? '⚠ prekoračeno za ' . ($r['ostvareno'] - $r['plan']) : ($r['plan'] - $r['ostvareno'] > 0 ? 'preostalo ' . ($r['plan'] - $r['ostvareno']) : '✓ ispunjeno') ?></div><?php endif; ?></td>
            <td class="text-end"><?php if ($r['id'] && $moze): ?><button class="btn btn-sm btn-outline-danger" form="obrisi-<?= $r['id'] ?>" data-potvrda="Obrisati red plana?" aria-label="Obriši">🗑</button><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$plan): ?><tr><td colspan="6" class="text-muted">Plan za ovu godinu još nije upisan.</td></tr><?php endif; ?>
    </tbody>
    <?php if ($moze): ?>
    <tfoot><tr class="table-light"><td><input name="nova_vrsta" class="form-control form-control-sm" list="od-vrste" placeholder="+ vrsta" autocomplete="off" data-plan-vrsta>
            <datalist id="od-vrste"><?php foreach (array_keys(ODSTREL_VRSTE) as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></td>
        <td><select name="nova_kategorija" class="form-select form-select-sm" data-plan-kat><option value="">ukupno (sva grla)</option></select></td>
        <td><input type="number" min="0" name="novi_broj" class="form-control form-control-sm text-end" placeholder="0"></td><td colspan="3"></td></tr></tfoot>
    <?php endif; ?>
</table></div>
<?php if ($moze): ?>
<div class="d-flex flex-wrap gap-2">
    <button class="btn btn-primary">💾 Spremi plan</button>
    <?php if ($imaProsle): ?><button class="btn btn-outline-secondary" name="radnja" value="kopiraj" data-potvrda="Preuzeti plan iz <?= e(odstrel_lg_naziv($g - 1)) ?>? Postojeći redovi ostaju.">↻ Preuzmi iz <?= e(odstrel_lg_naziv($g - 1)) ?></button><?php endif; ?>
</div>
<?php endif; ?>
</form>
<?php foreach ($plan as $r): if ($r['id'] && $moze): ?>
<form method="post" id="obrisi-<?= $r['id'] ?>" class="d-none"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= $r['id'] ?>"></form>
<?php endif; endforeach; ?>
<?php if ($moze): ?>
<script>
(function () {
    var vrste = <?= json_encode(ODSTREL_VRSTE, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, v = document.querySelector('[data-plan-vrsta]'), k = document.querySelector('[data-plan-kat]');
    function puni() { var cur = k.value; k.innerHTML = '<option value="">ukupno (sva grla)</option>'; (vrste[v.value] || []).forEach(function (x) { var o = document.createElement('option'); o.value = o.textContent = x; o.selected = x === cur; k.appendChild(o); }); }
    v.addEventListener('input', puni); v.addEventListener('change', puni);
})();
</script>
<?php endif; ?>
<?php stranica('Plan odstrela', ob_get_clean());
