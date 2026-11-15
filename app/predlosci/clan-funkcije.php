<?php
/** Kartica Funkcije. Varijable iz uredi.php: $id, $clan */
$moze = $uredi; // Članovi – upis i uređivanje za sekciju člana
$fun = redovi('SELECT cf.*, f.Naziv, f.OslobodjenClanarine, s.Naziv AS Sekcija FROM ClanFunkcije cf JOIN Funkcije f ON f.Id=cf.FunkcijaId
               LEFT JOIN Sekcije s ON s.Id=cf.SekcijaId WHERE cf.ClanId=? ORDER BY (cf.Do IS NOT NULL), f.Redoslijed, cf.Od DESC', [$id]);
$akcija = fn(string $r, int $fid, string $tekst, string $klasa, string $potvrda = '') =>
    '<form method="post" class="d-inline" action="' . e(url('clanovi/uredi', ['id' => $id])) . '">' . csrf() . '<input type="hidden" name="radnja" value="' . $r . '"><input type="hidden" name="fid" value="' . $fid . '">'
    . '<button class="btn btn-sm ' . $klasa . '"' . ($potvrda ? ' data-potvrda="' . e($potvrda) . '"' : '') . '>' . $tekst . '</button></form>';
?>
<div class="table-responsive">
<table class="table align-middle">
    <thead><tr><th>Funkcija</th><th>Jedinica / sekcija</th><th>Od</th><th>Do</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($fun as $f): ?>
        <tr class="<?= $f['Do'] ? 'text-muted' : '' ?>">
            <td><?= e($f['Naziv']) ?><?php if ($f['OslobodjenClanarine']): ?> <span class="badge bg-info text-dark ms-1">ne plaća članarinu</span><?php endif; ?></td>
            <td><?= e($f['Sekcija'] ?? '') ?></td>
            <td><?= e(datum($f['Od'])) ?></td>
            <td><?= e(datum($f['Do'])) ?></td>
            <td class="text-end text-nowrap"><?php if ($moze): ?>
                <?php if (!$f['Do']) echo $akcija('funkcija-zavrsi', (int) $f['Id'], 'Završi danas', 'btn-outline-secondary'); ?>
                <?= $akcija('funkcija-obrisi', (int) $f['Id'], 'Obriši', 'btn-link text-danger', 'Obrisati funkciju iz povijesti?') ?>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$fun): ?><tr><td colspan="5" class="text-muted">Nema funkcija.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php if ($moze): ?>
<form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" class="row g-2 align-items-end"><?= csrf() ?>
    <input type="hidden" name="radnja" value="funkcija-dodaj">
    <div class="col-md-4"><label class="form-label">Dodaj funkciju</label>
        <select name="FunkcijaId" class="form-select" required><option value="">— odaberi —</option>
            <?php foreach (redovi('SELECT * FROM Funkcije ORDER BY Redoslijed, Naziv') as $f): ?><option value="<?= (int) $f['Id'] ?>"><?= e($f['Naziv']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3"><label class="form-label">Jedinica / sekcija</label>
        <select name="SekcijaId" class="form-select"><?php if ($uredSve): ?><option value="0">— (cijela udruga)</option><?php endif; ?>
            <?php foreach ($sekUredi as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $clan['SekcijaId']) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3"><label class="form-label">Od</label><input type="date" name="Od" class="form-control" value="<?= danas() ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Dodaj</button></div>
</form>
<p class="small text-muted mt-2 mb-0">Npr. Lovočuvar → određena jedinica / sekcija. Povijest funkcija ostaje sačuvana. Popis funkcija: Sustav → Uloge i prava.</p>
<?php endif; ?>

<?php
// ---------- 📅 Godišnje obveze (bodovi 1× godišnje, odmah odobreno) ----------
$sveObveze = redovi('SELECT * FROM GodisnjeObveze WHERE Aktivna=1 ORDER BY Naziv');
$godO = (int) date('Y');
$mojeObveze = redovi('SELECT oc.*, o.Naziv, o.Bodovi, o.Dan, o.Aktivna AS ObvezaAktivna, (SELECT 1 FROM ObvezaBodovi b WHERE b.ObvezaClanId=oc.Id AND b.Godina=?) AS Upisano
    FROM ObvezaClanovi oc JOIN GodisnjeObveze o ON o.Id=oc.ObvezaId WHERE oc.ClanId=? ORDER BY oc.Aktivan DESC, o.Naziv', [$godO, $id]);
if ($sveObveze || $mojeObveze):
    $ob = fn(string $r, int $oid, string $t, string $kl, string $p = '') => '<form method="post" class="d-inline" action="' . e(url('clanovi/uredi', ['id' => $id])) . '">' . csrf()
        . '<input type="hidden" name="radnja" value="obveza-' . $r . '"><input type="hidden" name="id" value="' . $oid . '"><button class="btn btn-sm ' . $kl . '"' . ($p ? ' data-potvrda="' . e($p) . '"' : '') . '>' . $t . '</button></form>'; ?>
<h2 class="h5 mt-4 mb-1">📅 Godišnje obveze <span class="fw-normal text-muted small">(bodovi 1× godišnje, odmah odobreno)</span></h2>
<p class="small text-muted mb-2">Npr. briga o hranilištu, održavanje čeke. Lovački psi: kartica Lovački psi.</p>
<div class="table-responsive">
<table class="table align-middle">
    <tbody>
    <?php foreach ($mojeObveze as $u): ?>
        <tr class="<?= $u['Aktivan'] && $u['ObvezaAktivna'] ? '' : 'text-muted' ?>">
            <td><?= e($u['Naziv']) ?><?php if ($u['Opis']): ?> <span class="small text-muted">· <?= e($u['Opis']) ?></span><?php endif; ?></td>
            <td class="small text-nowrap"><?= e(broj((float) $u['Bodovi'], 2)) ?> b. · <?= e(bodovi_dan_prikaz($u['Dan'])) ?></td>
            <td class="small"><?= $u['Upisano'] ? '<span class="badge bg-success-subtle text-success-emphasis border">bodovi ' . $godO . '. upisani</span>' : ($u['Aktivan'] ? '<span class="badge text-bg-light border">upisuju se ' . e(bodovi_dan_prikaz($u['Dan'])) . '</span>' : '<span class="badge text-bg-light border">isključeno</span>') ?></td>
            <td class="text-end text-nowrap"><?php if ($moze): ?>
                <?= $u['Aktivan'] ? $ob('iskljuci', (int) $u['Id'], '⏸️ isključi', 'btn-link p-0', 'Isključiti? Već upisani bodovi ostaju.') : $ob('ukljuci', (int) $u['Id'], '✅ uključi', 'btn-outline-success py-0', 'Uključiti? Ako je dan već prošao, bodovi za ' . $godO . '. upisuju se odmah (odobreno).') ?>
                <?= $ob('ukloni', (int) $u['Id'], 'Ukloni', 'btn-link text-danger ms-2', 'Ukloniti obvezu? Već upisani bodovi ostaju.') ?>
            <?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$mojeObveze): ?><tr><td class="text-muted">Nema obveza.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php if ($moze && $sveObveze): ?>
<form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" class="row g-2 align-items-end"><?= csrf() ?>
    <input type="hidden" name="radnja" value="obveza-dodaj">
    <div class="col-md-4"><label class="form-label">Dodaj obvezu</label>
        <select name="obveza" class="form-select" required><option value="">— odaberi —</option>
            <?php foreach ($sveObveze as $o): ?><option value="<?= (int) $o['Id'] ?>"><?= e($o['Naziv']) ?> (<?= e(broj((float) $o['Bodovi'], 1)) ?> b.)</option><?php endforeach; ?>
        </select></div>
    <div class="col-md-6"><label class="form-label">Napomena</label><input name="opis" class="form-control" maxlength="120" placeholder="npr. hranilište Bukovica 3"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100" data-potvrda="Dodati obvezu? Ako je dan već prošao, bodovi za <?= $godO ?>. upisuju se odmah (odobreno).">Dodaj</button></div>
</form>
<p class="small text-muted mt-2 mb-0">Popis obveza, bodovi i dan: Sustav → Bodovi. Pregled svih: Radne akcije → 📅 Godišnje obveze.</p>
<?php endif; ?>
<?php endif;
