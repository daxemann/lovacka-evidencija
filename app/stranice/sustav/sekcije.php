<?php
/** Sekcije / lovne jedinice. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'dodaj') {
        $n = mb_substr(ul_str('Naziv'), 0, 100);
        if ($n !== '' && !vrijednost('SELECT 1 FROM Sekcije WHERE lower(Naziv)=lower(?)', [$n])) {
            umetni('Sekcije', ['Naziv' => $n, 'Aktivna' => 1, 'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0)+1 FROM Sekcije')]);
            dnevnik('Nova sekcija', null, null, $n);
        } else {
            poruka('Sekcija s tim nazivom već postoji.', 'warning');
        }
    } elseif ($radnja === 'obrisi') {
        $id = (int) $_POST['id'];
        if (vrijednost('SELECT 1 FROM Clanovi WHERE SekcijaId=?', [$id])) {
            poruka('Sekcija ima članove – ne može se obrisati.', 'warning');
        } else {
            $n = naziv_sekcije($id);
            q('DELETE FROM Sekcije WHERE Id=?', [$id]);
            dnevnik('Obrisana sekcija', null, null, $n);
        }
    } else {
        try {
            foreach ((array) ($_POST['s'] ?? []) as $id => $s) {
                $n = mb_substr(trim((string) ($s['Naziv'] ?? '')), 0, 100);
                if ($n !== '') {
                    azuriraj('Sekcije', (int) $id, ['Naziv' => $n, 'Redoslijed' => (int) ($s['Redoslijed'] ?? 0), 'Aktivna' => isset($s['Aktivna']) ? 1 : 0]);
                }
            }
            dnevnik('Uređene sekcije');
            poruka('Spremljeno.');
        } catch (PDOException) {
            poruka('Dvije sekcije ne mogu imati isti naziv.', 'danger');
        }
    }
    preusmjeri('sustav/sekcije');
}
$sek = redovi('SELECT s.*, (SELECT COUNT(*) FROM Clanovi c WHERE c.SekcijaId=s.Id AND c.Status=0) AS Broj FROM Sekcije s ORDER BY s.Redoslijed, s.Naziv');
ob_start(); ?>
<h1 class="h3 mb-3">Sekcije</h1>
<form method="post" action="<?= e(url('sustav/sekcije')) ?>" style="max-width:720px"><?= csrf() ?>
<table class="table align-middle">
    <thead><tr><th style="width:7rem">Redoslijed</th><th>Naziv</th><th>Članova</th><th>Aktivna</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sek as $s): $i = (int) $s['Id']; ?>
        <tr><td><input name="s[<?= $i ?>][Redoslijed]" type="number" class="form-control form-control-sm" value="<?= (int) $s['Redoslijed'] ?>"></td>
            <td><input name="s[<?= $i ?>][Naziv]" class="form-control form-control-sm" value="<?= e($s['Naziv']) ?>" maxlength="100"></td>
            <td><a href="<?= e(url('clanovi', ['sekcija' => $i])) ?>"><?= (int) $s['Broj'] ?></a></td>
            <td><input type="checkbox" class="form-check-input" name="s[<?= $i ?>][Aktivna]" value="1"<?= chk($s['Aktivna']) ?>></td>
            <td><?php if (!$s['Broj']): ?><button class="btn btn-sm btn-link text-danger" form="obrisi<?= $i ?>" data-potvrda="Obrisati sekciju?">Obriši</button><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<button class="btn btn-primary">Spremi</button>
</form>
<?php foreach ($sek as $s): ?><form method="post" action="<?= e(url('sustav/sekcije')) ?>" id="obrisi<?= (int) $s['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= (int) $s['Id'] ?>"></form><?php endforeach; ?>
<form method="post" action="<?= e(url('sustav/sekcije')) ?>" class="row g-2 align-items-end mt-3" style="max-width:720px"><?= csrf() ?><input type="hidden" name="radnja" value="dodaj">
    <div class="col-8"><label class="form-label">Nova sekcija</label><input name="Naziv" class="form-control" maxlength="100" required></div>
    <div class="col-4"><button class="btn btn-outline-primary w-100">Dodaj</button></div>
</form>
<p class="small text-muted mt-2 mb-0">Sekcija s članovima se ne može obrisati – možete je deaktivirati ili članove premjestiti (Članovi → odabir → Premjesti).</p>
<?php stranica('Sekcije', ob_get_clean());
