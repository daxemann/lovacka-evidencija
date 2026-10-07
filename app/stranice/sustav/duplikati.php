<?php
/** Provjera i spajanje duplikata. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $a = (int) ($_POST['a'] ?? 0);
    $b = (int) ($_POST['b'] ?? 0);
    if ($radnja === 'nisu') {
        oznaci_nisu_isti($a, $b);
        poruka('Označeno: nisu ista osoba.');
    } elseif ($radnja === 'spoji') {
        $z = (int) ($_POST['zadrzati'] ?? $a);
        $u = $z === $a ? $b : $a;
        try {
            $opis = spoji_clanove($z, $u, (array) ($_POST['polja'] ?? []));
            poruka(e('Spojeno: ' . $opis));
        } catch (Throwable $e) {
            poruka(e($e->getMessage()), 'danger');
        }
    }
    preusmjeri('sustav/duplikati');
}
$parovi = pronadji_duplikate();
$otvoren = (string) ($_GET['par'] ?? '');
$zamjena = ($_GET['zamjena'] ?? '') === '1';
ob_start(); ?>
<h1 class="h3 mb-1">Provjera duplikata</h1>
<p class="text-muted">Parovi članova koji su vjerojatno ista osoba (č/ć/š/ž/đ, zamijenjeno ime i prezime, tipfeleri, isti telefon/e-mail/OIB).
    Odaberite koji se podaci zadržavaju i spojite – radne akcije, funkcije, članarina i korisnički račun prelaze na zadržanog člana.</p>
<?php if (!$parovi): ?><div class="alert alert-success">Nema mogućih duplikata. 👍</div><?php else: ?><p><b><?= count($parovi) ?></b> mogućih duplikata.</p><?php endif; ?>
<?php foreach ($parovi as $p):
    $kl = $p['a']['Id'] . '-' . $p['b']['Id'];
    [$z, $d] = $zamjena && $otvoren === $kl ? [$p['b'], $p['a']] : [$p['a'], $p['b']]; ?>
    <div class="card mb-2"><div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <b><?= e(prezime_ime($p['a'])) ?></b> ↔ <b><?= e(prezime_ime($p['b'])) ?></b>
            <span class="badge bg-<?= $p['ocjena'] >= 0.999 ? 'danger' : 'warning text-dark' ?>"><?= (int) round($p['ocjena'] * 100) ?> %</span>
            <span class="small text-muted"><?= e($p['razlog']) ?></span>
            <span class="ms-auto"></span>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('sustav/duplikati', ['par' => $kl])) ?>#p<?= e($kl) ?>">Usporedi i spoji</a>
            <form method="post" action="<?= e(url('sustav/duplikati')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="nisu"><input type="hidden" name="a" value="<?= (int) $p['a']['Id'] ?>"><input type="hidden" name="b" value="<?= (int) $p['b']['Id'] ?>">
                <button class="btn btn-sm btn-outline-secondary">Nisu ista osoba</button></form>
        </div>
        <?php if ($otvoren === $kl): ?>
        <form method="post" action="<?= e(url('sustav/duplikati')) ?>" class="mt-3" id="p<?= e($kl) ?>"><?= csrf() ?>
            <input type="hidden" name="radnja" value="spoji"><input type="hidden" name="a" value="<?= (int) $z['Id'] ?>"><input type="hidden" name="b" value="<?= (int) $d['Id'] ?>"><input type="hidden" name="zadrzati" value="<?= (int) $z['Id'] ?>">
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th style="width:150px"></th>
                    <th>Zadržati: <?= e(puno_ime($z)) ?> <span class="text-muted small">(ID <?= (int) $z['Id'] ?>, <?= e(implode(', ', povijest_clana((int) $z['Id'])) ?: 'bez povijesti') ?>)</span></th>
                    <th>Drugi: <?= e(puno_ime($d)) ?> <span class="text-muted small">(ID <?= (int) $d['Id'] ?>, <?= e(implode(', ', povijest_clana((int) $d['Id'])) ?: 'bez povijesti') ?>)</span>
                        <a class="btn btn-sm btn-link" href="<?= e(url('sustav/duplikati', ['par' => $kl, 'zamjena' => $zamjena ? null : 1])) ?>#p<?= e($kl) ?>">⇄ Zadrži drugog</a></th></tr></thead>
                <tbody>
                <?php foreach (polja_spajanja() as $polje => [$fn, $st]):
                    $vz = $fn($z);
                    $vd = $fn($d);
                    if (trim((string) $vz) === '' && trim((string) $vd) === '') continue;
                    $desno = zadano_desno($polje, $z, $d); ?>
                    <tr><td class="small text-muted"><?= e($polje) ?></td>
                        <td><label><input type="radio" class="form-check-input me-1" name="r[<?= e($polje) ?>]" value="0"<?= chk(!$desno) ?>
                            onchange="document.getElementById('cb<?= md5($kl . $polje) ?>').checked=false"> <?= e((string) $vz) ?: '<span class="text-muted">—</span>' ?></label></td>
                        <td><label><input type="radio" class="form-check-input me-1" name="r[<?= e($polje) ?>]" value="1"<?= chk($desno) ?>
                            onchange="document.getElementById('cb<?= md5($kl . $polje) ?>').checked=true"> <?= e((string) $vd) ?: '<span class="text-muted">—</span>' ?></label>
                            <input type="checkbox" class="d-none" id="cb<?= md5($kl . $polje) ?>" name="polja[]" value="<?= e($polje) ?>"<?= chk($desno) ?>></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-danger" data-potvrda="Spojiti? Drugi zapis se briše.">Spoji</button>
                <span class="small text-muted">Radne akcije, funkcije, članarina i račun oba člana ostaju (spojeno). Drugi zapis se briše. Prije spajanja radi se sigurnosna kopija.</span>
            </div>
        </form>
        <?php endif; ?>
    </div></div>
<?php endforeach; ?>
<?php stranica('Provjera duplikata', ob_get_clean());
