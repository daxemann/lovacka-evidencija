<?php
/** ↪️ Prijenos bodova iz prošle u novu bodovnu godinu – prijedlog po pravilu (Sustav → Bodovi), iznos se može promijeniti po članu. */
trazi(P_SUSTAV);
$k = korisnik();
$g = (int) ($_GET['g'] ?? $_POST['g'] ?? bodovna_godina());
if ($g < 2000 || $g > bodovna_godina()) {
    $g = bodovna_godina();
}
$sid = (int) ($_GET['s'] ?? 0);
if (je_post()) {
    $n = 0;
    transakcija(function () use ($g, $k, &$n) {
        foreach ((array) ($_POST['b'] ?? []) as $cid => $v) {
            $v = trim((string) $v);
            if ($v === '' || !isset($_POST['o'][$cid])) {
                continue;
            }
            $c = clan((int) $cid);
            if ($c && u_opsegu($c)) {
                bodovi_prenesi((int) $cid, $g, (float) (u_broj($v) ?? 0), $k);
                $n++;
            }
        }
    });
    dnevnik('Prijenos bodova', null, null, bodovna_godina_naziv($g - 1) . ' → ' . bodovna_godina_naziv($g) . ": $n članova");
    poruka("Preneseno za $n " . ($n === 1 ? 'člana' : 'članova') . '.');
    preusmjeri('akcije/prijenos', array_filter(['g' => $g, 's' => $sid ?: null]));
}
$pr = bodovi_prijenos_pravilo();
$staro = bodovi_godine($g - 1);
$preneseno = array_column(redovi('SELECT ClanId, Bodovi, UnioIme, Kreirano FROM BodoviPrijenos WHERE Godina=?', [$g]), null, 'ClanId');
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime, s.Naziv AS Sekcija FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
    WHERE c.Status=0 AND ' . opseg_sql('c') . ($sid ? ' AND c.SekcijaId=' . $sid : '') . ' ORDER BY c.Prezime, c.Ime');
ob_start(); ?>
<h1 class="h3 mb-1">↪️ Prijenos bodova</h1>
<p class="text-muted" style="max-width:820px">Iz <b><?= e(bodovna_godina_naziv($g - 1)) ?></b> u <b><?= e(bodovna_godina_naziv($g)) ?></b> (upisuje se na <?= e(datum(bodovna_godina_raspon($g)[0])) ?>, odmah odobreno).
    Pravilo: <b><?= e(BODOVI_PRIJENOS[$pr['nacin']]) ?><?= in_array($pr['nacin'], ['max', 'fiksno'], true) ? ' ' . e(broj($pr['broj'], 2)) : '' ?></b>
    – <a href="<?= e(url('sustav/bodovi')) ?>">promijeni</a>. Prijenos se može ponoviti – stari iznos se zamijeni.</p>
<form method="get" action="<?= e(url()) ?>" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="p" value="akcije/prijenos">
    <select name="g" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
        <?php foreach (bodovne_godine_popis() as $x): ?><option value="<?= $x ?>"<?= sel($x, $g) ?>>u <?= e(bodovna_godina_naziv($x)) ?></option><?php endforeach; ?>
    </select>
    <select name="s" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="0">sve sekcije</option>
        <?php foreach (moje_sekcije() as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $sid) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
    </select>
</form>
<form method="post"><?= csrf() ?><input type="hidden" name="g" value="<?= $g ?>">
<div class="table-responsive" style="max-width:820px"><table class="table table-sm align-middle">
    <thead><tr><th><input type="checkbox" class="form-check-input" checked onclick="document.querySelectorAll('.pr-o').forEach(x=>x.checked=this.checked)"></th>
        <th>Član</th><th class="d-none d-md-table-cell">Sekcija</th><th class="text-end">Bodovi <?= e(bodovna_godina_naziv($g - 1)) ?></th><th class="text-end">Prijedlog</th><th style="width:8rem">Prenesi</th><th class="small text-muted">preneseno</th></tr></thead>
    <tbody>
    <?php foreach ($clanovi as $c): $cid = (int) $c['Id']; $u = $staro[$cid] ?? 0.0; $prij = bodovi_prijenos_prijedlog($u, $pr); $p = $preneseno[$cid] ?? null; ?>
        <tr><td><input type="checkbox" class="form-check-input pr-o" name="o[<?= $cid ?>]" value="1"<?= $p ? '' : ' checked' ?>></td>
            <td><a href="<?= e(url('akcije/bodovi', ['clan' => $cid])) ?>"><?= e(prezime_ime($c)) ?></a></td>
            <td class="d-none d-md-table-cell small"><?= e($c['Sekcija'] ?? '') ?></td>
            <td class="text-end"><?= e(broj($u, 2)) ?></td>
            <td class="text-end text-muted"><?= e(broj($prij, 2)) ?></td>
            <td><input name="b[<?= $cid ?>]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" value="<?= e((string) (float) ($p ? $p['Bodovi'] : $prij)) ?>"
                       oninput="this.closest('tr').querySelector('.pr-o').checked=true"></td>
            <td class="small"><?= $p ? '<span class="text-success">✓ ' . e(broj((float) $p['Bodovi'], 2)) . '</span>' : '<span class="text-muted">—</span>' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$clanovi): ?><tr><td colspan="7" class="text-muted">Nema aktivnih članova.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<div class="matrica-spremi"><button class="btn btn-primary" data-potvrda="Prenijeti bodove označenim članovima?">↪️ Prenesi označenima</button>
    <span class="small text-muted ms-2">Već preneseni nisu označeni – označite ih ako želite promijeniti iznos.</span></div>
</form>
<?php stranica('Prijenos bodova', ob_get_clean());
