<?php
/** Vrste radnih akcija i standardni bodovi. */
trazi(P_SUSTAV);
if (je_post()) {
    if (($_POST['radnja'] ?? '') === 'dodaj') {
        $n = ul_str('Naziv');
        $b = u_broj($_POST['Bodovi'] ?? '') ?? 0.0;
        if ($n !== '') {
            umetni('VrsteAkcija', ['Naziv' => mb_substr($n, 0, 100), 'StandardniBodovi' => dec($b), 'Aktivna' => 1]);
            dnevnik('Nova vrsta radne akcije', null, null, $n);
        }
    } else {
        foreach ((array) ($_POST['v'] ?? []) as $id => $v) {
            $n = trim((string) ($v['Naziv'] ?? ''));
            if ($n === '') {
                continue;
            }
            azuriraj('VrsteAkcija', (int) $id, ['Naziv' => mb_substr($n, 0, 100), 'StandardniBodovi' => dec(u_broj($v['Bodovi'] ?? '') ?? 0.0), 'Aktivna' => isset($v['Aktivna']) ? 1 : 0]);
        }
        dnevnik('Uređene vrste radnih akcija');
        poruka('Spremljeno.');
    }
    preusmjeri('akcije/vrste');
}
$vrste = redovi('SELECT v.*, (SELECT COUNT(*) FROM RadneAkcije a WHERE a.VrstaAkcijeId=v.Id) AS Broj FROM VrsteAkcija v ORDER BY v.Aktivna DESC, v.Naziv');
ob_start(); ?>
<h1 class="h3 mb-1">Vrste radnih akcija</h1>
<p class="text-muted">Standardni bodovi se predlažu kod odobravanja – admin ih uvijek može promijeniti.</p>
<form method="post" action="<?= e(url('akcije/vrste')) ?>" style="max-width:760px"><?= csrf() ?>
<table class="table align-middle">
    <thead><tr><th>Naziv</th><th style="width:9rem">Standardni bodovi</th><th>Aktivna</th><th class="text-muted small">korišteno</th></tr></thead>
    <tbody>
    <?php foreach ($vrste as $v): $i = (int) $v['Id']; ?>
        <tr>
            <td><input name="v[<?= $i ?>][Naziv]" class="form-control form-control-sm" value="<?= e($v['Naziv']) ?>" maxlength="100"></td>
            <td><input name="v[<?= $i ?>][Bodovi]" type="number" step="0.5" min="0" class="form-control form-control-sm" value="<?= e((string) (float) $v['StandardniBodovi']) ?>"></td>
            <td><input type="checkbox" class="form-check-input" name="v[<?= $i ?>][Aktivna]" value="1"<?= chk($v['Aktivna']) ?>></td>
            <td class="small text-muted"><?= (int) $v['Broj'] ?>×</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<button class="btn btn-primary">Spremi</button>
</form>
<form method="post" action="<?= e(url('akcije/vrste')) ?>" class="row g-2 align-items-end mt-4" style="max-width:760px"><?= csrf() ?>
    <input type="hidden" name="radnja" value="dodaj">
    <div class="col-sm-6"><label class="form-label">Nova vrsta</label><input name="Naziv" class="form-control" maxlength="100" required></div>
    <div class="col-sm-3"><label class="form-label">Bodovi</label><input name="Bodovi" type="number" step="0.5" min="0" class="form-control" value="5"></div>
    <div class="col-sm-3"><button class="btn btn-outline-primary w-100">Dodaj</button></div>
</form>
<p class="small text-muted mt-3">Vrsta koja više nije aktivna ne nudi se kod unosa, ali ostaje u starim zapisima.</p>
<?php stranica('Vrste radnih akcija', ob_get_clean());
