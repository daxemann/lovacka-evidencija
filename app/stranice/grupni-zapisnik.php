<?php
/**
 * Skupni lov – dodatni zapisnik o obavljenom lovu: voditelj upisuje odstrijeljenu divljač (i naknadno),
 * PDF nosi isti broj kao lov. Stupci se podešavaju u Sustav → Skupni lov – PDF.
 */
$lov = gl_lov((int) ul('id', 0));
if (!$lov) {
    nije_pronadjeno('Skupni lov');
}
if (!gl_moze_upravljati($lov)) {
    zabranjeno();
}
if (($_GET['pdf'] ?? '') === '1') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="zapisnik-' . gl_broj_datoteka($lov) . '-' . substr($lov['Pocetak'], 0, 10) . '.pdf"');
    echo gl_zapisnik_pdf($lov);
    exit;
}
$stupci = gl_zapisnik_stupci();
$vrsta = function (string $s): string {
    $k = kljuc($s);
    return match (true) {
        str_contains($k, 'datum') => 'datum',
        str_contains($k, 'vrsta') => 'vrsta',
        str_contains($k, 'spol') => 'spol',
        str_contains($k, 'grla') || str_contains($k, 'kljun') || str_contains($k, 'komad') => 'broj',
        default => 'tekst',
    };
};
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'spremi') {
        $v = [];
        foreach ($stupci as $i => $s) {
            $x = mb_substr(trim((string) ($_POST['s'][$i] ?? '')), 0, 120);
            if ($vrsta($s) === 'datum' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $x, $m)) {
                $x = "$m[3].$m[2].$m[1].";
            }
            $v[$s] = $x;
        }
        if (!array_filter($v, fn($x) => $x !== '')) {
            poruka('Prazan red nije spremljen.', 'warning');
            preusmjeri('grupni-zapisnik', ['id' => $lov['Id']]);
        }
        $json = json_encode($v, JSON_UNESCAPED_UNICODE);
        $rid = (int) ($_POST['red'] ?? 0);
        if ($rid && vrijednost('SELECT 1 FROM GrupniLovZapisnik WHERE Id=? AND LovId=?', [$rid, $lov['Id']])) {
            q('UPDATE GrupniLovZapisnik SET Podaci=?, Azurirano=? WHERE Id=?', [$json, sada(), $rid]);
            poruka('Red je izmijenjen.');
        } else {
            umetni('GrupniLovZapisnik', ['LovId' => $lov['Id'], 'Podaci' => $json, 'Upisao' => korisnik()['Naziv'], 'Kreirano' => sada()]);
            poruka('Upisano.');
        }
        dnevnik('Skupni lov – dodatni zapisnik', 'GrupniLov', (int) $lov['Id'], implode(' · ', array_filter($v)));
        preusmjeri('grupni-zapisnik', ['id' => $lov['Id']]);
    }
    if ($radnja === 'obrisi') {
        q('DELETE FROM GrupniLovZapisnik WHERE Id=? AND LovId=?', [(int) ($_POST['red'] ?? 0), $lov['Id']]);
        poruka('Red je obrisan.');
        preusmjeri('grupni-zapisnik', ['id' => $lov['Id']]);
    }
    zabranjeno();
}
$redovi = gl_zapisnik_redovi((int) $lov['Id']);
$uredi = null;
foreach ($redovi as $r) {
    if ((int) $r['Id'] === (int) ul('uredi', 0)) {
        $uredi = $r;
    }
}
$vrsteDivljaci = ['Srna obična', 'Jelen obični', 'Jelen lopatar', 'Divlja svinja', 'Muflon', 'Zec obični', 'Fazan', 'Trčka skvržulja', 'Prepelica', 'Šljuka bena',
    'Divlja patka', 'Divlja guska', 'Golub grivnjaš', 'Lisica', 'Čagalj', 'Jazavac', 'Kuna', 'Vrana siva', 'Svraka', 'Šojka'];
$datumLova = substr($lov['Pocetak'], 0, 10);
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-lov', ['id' => $lov['Id']])) ?>">← Skupni lov</a>
    <h1 class="h4 mb-0 me-auto">📝 Dodatni zapisnik <span class="text-muted small">br. <?= e(gl_broj($lov)) ?></span></h1>
    <a class="btn btn-sm btn-success<?= $redovi ? '' : ' disabled' ?>" href="<?= e(url('grupni-zapisnik', ['id' => $lov['Id'], 'pdf' => 1])) ?>" target="_blank">📄 PDF</a>
    <a class="btn btn-sm btn-outline-primary<?= $redovi ? '' : ' disabled' ?>" href="<?= e(url('grupni-popis', ['id' => $lov['Id'], 'posalji' => 1])) ?>#gp-posalji">📧 Pošalji</a>
</div>
<p class="small text-muted mb-3"><?= e($lov['Naziv']) ?> · <?= e(datum($lov['Pocetak'])) ?> · voditelj <?= e($lov['KreiraoIme']) ?> –
    upišite odstrijeljenu divljač (može i naknadno), zatim PDF.</p>

<div class="card mb-3 border-success" id="obrazac"><div class="card-body">
    <h2 class="h6"><?= $uredi ? '✏️ Izmjena reda' : '➕ Novi red' ?></h2>
    <form method="post"><?= csrf() ?><input type="hidden" name="radnja" value="spremi"><input type="hidden" name="red" value="<?= (int) ($uredi['Id'] ?? 0) ?>">
        <div class="row g-2">
        <?php foreach ($stupci as $i => $s): $val = (string) ($uredi['v'][$s] ?? ''); $t = $vrsta($s); ?>
            <div class="<?= $t === 'tekst' && mb_strlen($s) > 12 ? 'col-12 col-md-6' : 'col-6 col-md-3' ?>">
                <label class="form-label small mb-0" for="s<?= $i ?>"><?= e($s) ?></label>
                <?php if ($t === 'datum'):
                    $iso = preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\.?$/', $val, $m) ? "$m[3]-$m[2]-$m[1]" : ($val === '' ? $datumLova : ''); ?>
                    <input class="form-control" type="date" id="s<?= $i ?>" name="s[<?= $i ?>]" value="<?= e($iso) ?>">
                <?php elseif ($t === 'spol'): ?>
                    <select class="form-select" id="s<?= $i ?>" name="s[<?= $i ?>]">
                        <?php foreach (['', 'muško', 'žensko'] as $o): ?><option<?= $o === $val ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                    </select>
                <?php elseif ($t === 'broj'): ?>
                    <input class="form-control" type="number" min="0" inputmode="numeric" id="s<?= $i ?>" name="s[<?= $i ?>]" value="<?= e($val !== '' ? $val : ($uredi ? '' : '1')) ?>">
                <?php else: ?>
                    <input class="form-control" id="s<?= $i ?>" name="s[<?= $i ?>]" maxlength="120" value="<?= e($val) ?>"<?= $t === 'vrsta' ? ' list="zap-vrste" autocomplete="off"' : '' ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <datalist id="zap-vrste"><?php foreach ($vrsteDivljaci as $o): ?><option value="<?= e($o) ?>"><?php endforeach; ?></datalist>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-success"><?= $uredi ? '💾 Spremi izmjenu' : '➕ Upiši' ?></button>
            <?php if ($uredi): ?><a class="btn btn-outline-secondary" href="<?= e(url('grupni-zapisnik', ['id' => $lov['Id']])) ?>">Odustani</a><?php endif; ?>
        </div>
    </form>
</div></div>

<h2 class="h6">Upisano: <?= count($redovi) ?></h2>
<?php if (!$redovi): ?>
    <div class="alert alert-secondary">Još nije upisana divljač.</div>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>RB</th><?php foreach ($stupci as $s): ?><th><?= e($s) ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
    <?php foreach ($redovi as $n => $r): ?>
        <tr><td><?= $n + 1 ?>.</td><?php foreach ($stupci as $s): ?><td><?= e((string) ($r['v'][$s] ?? '')) ?></td><?php endforeach; ?>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-zapisnik', ['id' => $lov['Id'], 'uredi' => $r['Id']])) ?>#obrazac" aria-label="Uredi">✏️</a>
                <form method="post" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="red" value="<?= (int) $r['Id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati red <?= $n + 1 ?>?" aria-label="Obriši">🗑</button></form></td></tr>
    <?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php stranica('Dodatni zapisnik', ob_get_clean());
