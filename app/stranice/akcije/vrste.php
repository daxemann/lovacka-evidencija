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
        // dodatna oprema: postojeće (izmjena / brisanje) i nove stavke
        foreach ((array) ($_POST['o'] ?? []) as $oid => $o) {
            $n = mb_substr(trim((string) ($o['Naziv'] ?? '')), 0, 60);
            if (isset($o['Obrisi']) || $n === '') {
                q('DELETE FROM VrstaOprema WHERE Id=?', [(int) $oid]);
            } else {
                azuriraj('VrstaOprema', (int) $oid, ['Naziv' => $n, 'Bodovi' => dec(u_broj($o['Bodovi'] ?? '') ?? 0.0), 'Redoslijed' => (int) ($o['Red'] ?? 0)]);
            }
        }
        foreach ((array) ($_POST['on'] ?? []) as $vid => $redovi) {
            if (!vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=?', [(int) $vid])) {
                continue;
            }
            $red = (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0) FROM VrstaOprema WHERE VrstaAkcijeId=?', [(int) $vid]);
            foreach ((array) $redovi as $o) {
                $n = mb_substr(trim((string) ($o['Naziv'] ?? '')), 0, 60);
                if ($n !== '') {
                    umetni('VrstaOprema', ['VrstaAkcijeId' => (int) $vid, 'Naziv' => $n, 'Bodovi' => dec(u_broj($o['Bodovi'] ?? '') ?? 0.0), 'Redoslijed' => ++$red]);
                }
            }
        }
        dnevnik('Uređene vrste radnih akcija');
        poruka('Spremljeno.');
    }
    preusmjeri('akcije/vrste');
}
$vrste = redovi('SELECT v.*, (SELECT COUNT(*) FROM RadneAkcije a WHERE a.VrstaAkcijeId=v.Id) AS Broj FROM VrsteAkcija v ORDER BY v.Aktivna DESC, v.Naziv');
ob_start(); ?>
<h1 class="h3 mb-1">Vrste radnih akcija</h1>
<p class="text-muted">Standardni bodovi se predlažu kod odobravanja – admin ih uvijek može promijeniti.
    <b>Dodatna oprema</b> (npr. kod košnje traktor, trimer, motorna pila) daje dodatne bodove onome tko ju je donio – nudi se kao kvačica kod upisa akcije.</p>
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
        <?php $opr = oprema_vrste($i); ?>
        <tr class="oprema-red"><td colspan="4" class="pt-0 border-top-0">
            <details<?= $opr ? ' open' : '' ?>><summary class="small text-muted">🧰 Dodatna oprema<?= $opr ? ': ' . e(implode(', ', array_map(fn($o) => $o['Naziv'] . ' +' . broj($o['Bodovi'], 1), $opr))) : ' (nema)' ?></summary>
                <div class="ps-3 pt-2" style="max-width:520px">
                <?php foreach ($opr as $o): $oi = (int) $o['Id']; ?>
                    <div class="input-group input-group-sm mb-1">
                        <input name="o[<?= $oi ?>][Naziv]" class="form-control" maxlength="60" value="<?= e($o['Naziv']) ?>" aria-label="Oprema">
                        <span class="input-group-text">+</span>
                        <input name="o[<?= $oi ?>][Bodovi]" type="number" step="0.5" min="0" class="form-control" style="max-width:6rem" value="<?= e((string) (float) $o['Bodovi']) ?>" aria-label="Bodovi">
                        <span class="input-group-text">b.</span>
                        <input type="hidden" name="o[<?= $oi ?>][Red]" value="<?= (int) $o['Redoslijed'] ?>">
                        <label class="input-group-text text-danger" title="Obriši"><input type="checkbox" class="form-check-input mt-0 me-1" name="o[<?= $oi ?>][Obrisi]" value="1">✕</label>
                    </div>
                <?php endforeach; ?>
                <?php for ($n = 0; $n < ($opr ? 1 : 3); $n++): ?>
                    <div class="input-group input-group-sm mb-1">
                        <input name="on[<?= $i ?>][<?= $n ?>][Naziv]" class="form-control" maxlength="60" placeholder="<?= e(['npr. Traktor', 'npr. Trimer', 'npr. Motorna pila'][$n] ?? 'nova oprema') ?>" aria-label="Nova oprema">
                        <span class="input-group-text">+</span>
                        <input name="on[<?= $i ?>][<?= $n ?>][Bodovi]" type="number" step="0.5" min="0" class="form-control" style="max-width:6rem" placeholder="0" aria-label="Bodovi">
                        <span class="input-group-text">b.</span>
                    </div>
                <?php endfor; ?>
                </div>
            </details>
        </td></tr>
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
