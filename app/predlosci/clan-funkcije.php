<?php
/** Kartica Funkcije. Varijable iz uredi.php: $id, $clan */
$moze = ima(P_CLANOVI_UREDI) && ima(P_SUSTAV);
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
        <select name="SekcijaId" class="form-select"><option value="0">— (cijela udruga)</option>
            <?php foreach (moje_sekcije() as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $clan['SekcijaId']) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="col-md-3"><label class="form-label">Od</label><input type="date" name="Od" class="form-control" value="<?= danas() ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Dodaj</button></div>
</form>
<p class="small text-muted mt-2 mb-0">Npr. Lovočuvar → određena jedinica / sekcija. Povijest funkcija ostaje sačuvana. Popis funkcija: Sustav → Uloge i prava.</p>
<?php endif;
