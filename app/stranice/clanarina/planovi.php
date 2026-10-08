<?php
/** Planovi plaćanja članarine za godinu: jednokratno ili 1–12 rata s vlastitim iznosom i datumom. */
trazi(P_CLANARINA_UREDI);
$godina = (int) ($_GET['godina'] ?? date('Y'));
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    if ($radnja === 'kopiraj') {
        $od = (int) $_POST['od'];
        $nove = [];
        foreach (planovi_clanarine($od) as $p) {
            foreach ($p['rate'] as &$r) {
                $t = strtotime($r['datum']);
                $r['datum'] = ($godina - $od + (int) date('Y', $t)) . date('-m-d', $t);
            }
            $nove[] = $p;
        }
        spremi_planove_clanarine($godina, $nove);
        poruka("Planovi su kopirani iz $od. – provjerite datume.");
    } else {
        $nove = [];
        $zad = (string) ($_POST['zadano'] ?? '');
        foreach ((array) ($_POST['plan'] ?? []) as $pid => $p) {
            $orig = (string) $pid;
            $naziv = mb_substr(trim((string) ($p['naziv'] ?? '')), 0, 40);
            $rate = [];
            foreach ((array) ($p['rate'] ?? []) as $r) {
                $iz = u_broj($r['iznos'] ?? '');
                $dt = u_datum($r['datum'] ?? '');
                if ($iz !== null && $iz > 0 && $dt) {
                    $rate[] = ['iznos' => round($iz, 2), 'datum' => $dt];
                }
            }
            if ($naziv === '' || !$rate) {
                continue;
            }
            usort($rate, fn($a, $b) => strcmp($a['datum'], $b['datum']));
            $pid = (string) $pid === 'novi' ? 'p' . bin2hex(random_bytes(3)) : (preg_replace('/[^a-z0-9]/', '', (string) $pid) ?: 'p' . bin2hex(random_bytes(3)));
            $nove[] = ['id' => $pid, 'naziv' => $naziv, 'zadano' => $zad === (string) $orig, 'rate' => array_slice($rate, 0, 12)];
        }
        if ($nove && !array_filter($nove, fn($p) => $p['zadano'])) {
            $nove[0]['zadano'] = true;
        }
        spremi_planove_clanarine($godina, $nove);
        $od = (int) ($_POST['odgoda'] ?? 7);
        spremi_postavku('Clanarina.Odgoda', (string) max(0, min(90, $od)));
        $iznosi = array_unique(array_map(fn($p) => novac(iznos_plana($p)), $nove));
        dnevnik('Planovi članarine', null, null, "$godina: " . implode('; ', array_map('opis_plana', $nove)));
        poruka(count($iznosi) > 1 ? 'Spremljeno. Pazite: planovi imaju različite ukupne iznose (' . implode(' / ', $iznosi) . ' €).' : 'Spremljeno.', count($iznosi) > 1 ? 'warning' : 'success');
    }
    preusmjeri('clanarina/planovi', ['godina' => $godina]);
}
$planovi = planovi_clanarine($godina);
if (!$planovi) {
    $planovi = [['id' => 'p1', 'naziv' => 'Jednokratno', 'zadano' => true, 'rate' => [['iznos' => '', 'datum' => "$godina-03-31"]]]];
}
$planovi[] = ['id' => 'novi', 'naziv' => '', 'zadano' => false, 'rate' => [['iznos' => '', 'datum' => '']], 'novi' => true];
$prosle = array_values(array_filter(array_map(fn($g) => $godina - $g, [1, 2]), fn($g) => planovi_clanarine($g)));
ob_start(); ?>
<div class="mb-2 small"><a href="<?= e(url('clanarina', ['godina' => $godina])) ?>">← Članarina <?= $godina ?>.</a></div>
<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-2"><input type="hidden" name="p" value="clanarina/planovi">
    <h1 class="h3 mb-0 me-auto">Planovi plaćanja</h1>
    <select name="godina" class="form-select" style="width:auto" data-auto><?php for ($g = (int) date('Y') + 1; $g >= (int) date('Y') - 3; $g--): ?><option<?= sel($g, $godina) ?>><?= $g ?></option><?php endfor; ?></select>
</form>
<p class="text-muted">Članarina je ista za sve; svaki član plaća po svom planu – jednokratno ili u ratama (do 12), svaka rata s vlastitim iznosom i datumom dospijeća.
    Zadani plan se koristi kod skupnog zaduženja; na kartici člana se plan može promijeniti ili prilagoditi samo za tog člana.</p>
<?php if ($prosle && !planovi_clanarine($godina)): ?>
    <form method="post" class="mb-3"><?= csrf() ?><input type="hidden" name="radnja" value="kopiraj"><input type="hidden" name="od" value="<?= $prosle[0] ?>">
        <button class="btn btn-outline-primary btn-sm">Preuzmi planove iz <?= $prosle[0] ?>. (datumi +<?= $godina - $prosle[0] ?> god.)</button></form>
<?php endif; ?>
<form method="post" id="planovi"><?= csrf() ?>
<?php foreach ($planovi as $p): $pid = e($p['id']); ?>
    <div class="card mb-3 plan<?= !empty($p['novi']) ? ' border-dashed' : '' ?>"><div class="card-body">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-sm-5"><label class="form-label small"><?= !empty($p['novi']) ? 'Novi plan – naziv' : 'Naziv plana' ?></label>
                <input name="plan[<?= $pid ?>][naziv]" class="form-control" value="<?= e($p['naziv']) ?>" maxlength="40" placeholder="npr. 3 rate"></div>
            <div class="col-sm-3"><div class="form-check"><input class="form-check-input" type="radio" name="zadano" value="<?= $pid ?>" id="z<?= $pid ?>"<?= chk($p['zadano']) ?>>
                <label class="form-check-label" for="z<?= $pid ?>">zadani plan</label></div></div>
            <div class="col-sm-4 text-sm-end">Ukupno: <b class="ukupno">0,00</b> €</div>
        </div>
        <table class="table table-sm mb-1"><thead><tr><th style="width:3rem">Rata</th><th>Iznos (€)</th><th>Dospijeće</th><th></th></tr></thead><tbody class="rate">
            <?php foreach ($p['rate'] as $i => $r): ?>
                <tr><td class="rb"><?= $i + 1 ?>.</td>
                    <td><input name="plan[<?= $pid ?>][rate][<?= $i ?>][iznos]" type="number" step="0.01" min="0" class="form-control form-control-sm iznos" value="<?= e($r['iznos'] === '' ? '' : number_format((float) $r['iznos'], 2, '.', '')) ?>"></td>
                    <td><input name="plan[<?= $pid ?>][rate][<?= $i ?>][datum]" type="date" class="form-control form-control-sm" value="<?= e($r['datum']) ?>"></td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger ukloni">×</button></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <button type="button" class="btn btn-sm btn-outline-secondary dodaj" data-pid="<?= $pid ?>">+ rata</button>
        <?php if (empty($p['novi'])): ?><span class="small text-muted ms-2">Plan se briše tako da obrišete naziv.</span><?php endif; ?>
    </div></div>
<?php endforeach; ?>
<div class="card mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-sm-4"><label class="form-label small">Podsjetnik članu nakon (dana od dospijeća)</label>
        <input name="odgoda" type="number" min="0" max="90" class="form-control" value="<?= (int) postavka('Clanarina.Odgoda', '7') ?>"></div>
    <div class="col-sm-8 small text-muted">Član tada na svojoj početnoj stranici vidi diskretan podsjetnik (samo on). Bankovni podaci se ne prikazuju.</div>
</div></div>
<button class="btn btn-primary">Spremi planove</button>
</form>
<script>
(function () {
    var f = document.getElementById('planovi');
    function prebroji() {
        f.querySelectorAll('.plan').forEach(function (p) {
            var s = 0; p.querySelectorAll('.iznos').forEach(function (i) { s += parseFloat(i.value) || 0; });
            p.querySelector('.ukupno').textContent = s.toFixed(2).replace('.', ',');
            p.querySelectorAll('.rate tr').forEach(function (tr, i) { tr.querySelector('.rb').textContent = (i + 1) + '.'; });
            p.querySelector('.dodaj').disabled = p.querySelectorAll('.rate tr').length >= 12;
        });
    }
    f.addEventListener('input', prebroji);
    f.addEventListener('click', function (e) {
        if (e.target.classList.contains('ukloni')) { var tb = e.target.closest('tbody'); if (tb.rows.length > 1) e.target.closest('tr').remove(); prebroji(); }
        if (e.target.classList.contains('dodaj')) {
            var tb = e.target.closest('.plan').querySelector('.rate'), zadnji = tb.rows[tb.rows.length - 1], n = zadnji.cloneNode(true), idx = Date.now();
            n.querySelectorAll('input').forEach(function (i) { i.name = i.name.replace(/\[rate\]\[\d+\]/, '[rate][' + idx + ']'); if (i.classList.contains('iznos')) i.value = ''; });
            var d = n.querySelector('input[type=date]');
            if (d.value) { var x = new Date(d.value); x.setMonth(x.getMonth() + 3); d.value = x.toISOString().slice(0, 10); }
            tb.appendChild(n); prebroji();
        }
    });
    prebroji();
})();
</script>
<?php stranica('Planovi plaćanja', ob_get_clean());
