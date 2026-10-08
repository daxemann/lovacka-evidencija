<?php
/** Članova vlastita članarina: jedna diskretna linija + detalji (rate, uplate, prethodne godine). Varijable: $clanId */
$sve = redovi('SELECT * FROM Clanarine WHERE ClanId=? ORDER BY Godina DESC LIMIT 4', [$clanId]);
$god = (int) date('Y');
$osl = isset(oslobodjeni_clanarine($god)[$clanId]);
$ova = null;
foreach ($sve as $x) {
    if ((int) $x['Godina'] === $god) {
        $ova = clanarina_clana($clanId, $god);
    }
}
$rate = $ova ? rate_clanarine($ova) : [];
$dosp = $ova && !$osl ? dospjele_rate($rate) : [];
if (!$sve && !$osl) {
    return;
}
?>
<details class="moja-clanarina">
    <summary class="small text-muted">
        Članarina <?= $god ?>.:
        <?php if ($osl): ?>oslobođen/a (počasni član)
        <?php elseif (!$ova): ?>još nije zadužena
        <?php else: ?>plaćeno <b class="text-body"><?= novac($ova['Uplaceno']) ?> €</b> od <?= novac($ova['Iznos']) ?> €
            <?php if ($ova['Preostalo'] > 0.004): ?> · otvoreno <?= novac($ova['Preostalo']) ?> €<?php else: ?> ✓<?php endif; ?>
        <?php endif; ?>
    </summary>
    <div class="small mt-2">
        <?php foreach ($sve as $x): $c2 = clanarina_clana($clanId, (int) $x['Godina']); $r2 = rate_clanarine($c2); ?>
            <div class="mb-2"><b><?= (int) $x['Godina'] ?>.</b> – <?= novac($c2['Iznos']) ?> €, plaćeno <?= novac($c2['Uplaceno']) ?> €
                <?php if ($r2 && count($r2) > 1 || ($r2 && $c2['Preostalo'] > 0.004)): ?>
                    <table class="table table-sm table-borderless mb-0 mt-1"><tbody>
                    <?php foreach ($r2 as $r): ?><tr><td class="ps-0"><?= count($r2) > 1 ? (int) $r['RedniBroj'] . '. rata' : 'rok' ?></td><td><?= e(datum($r['Dospijece'])) ?></td><td class="text-end"><?= novac($r['Iznos']) ?> €</td><td><?= oznaka_statusa_rate($r['Status']) ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
                <?php foreach ($c2['Uplate'] as $u): ?><div class="text-muted">uplata <?= e(datum($u['Datum'])) ?>: <?= novac($u['Iznos']) ?> €</div><?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="text-muted">Ako nešto ne odgovara, javite se blagajniku.</div>
    </div>
</details>
<?php if ($dosp): $r = $dosp[0]; ?>
    <div class="small text-muted mt-1 podsjetnik">🔔 Podsjetnik: <?= count($rate) > 1 ? (int) $r['RedniBroj'] . '. rata' : 'članarina' ?> (<?= novac($r['Iznos']) ?> €) dospjela je <?= e(rtrim(datum($r['Dospijece']), '.')) ?><?= $r['Placeno'] > 0 ? ', otvoreno još ' . novac($r['Preostalo']) . ' €' : '' ?>.
        Ako ste već platili, zanemarite – uplata možda još nije upisana.</div>
<?php endif;
