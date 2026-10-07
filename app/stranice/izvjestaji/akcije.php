<?php
/** Izvještaj radnih akcija: filtar, ispis, PDF, CSV, slanje e-poštom. */
trazi(P_IZVJESTAJI);
$k = korisnik();
$f = filtar_iz_upita($_GET);
$grupe = izvjestaj_akcija($f);
$opis = opis_raspona($f);
$sekcije = moje_sekcije();
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c WHERE ' . opseg_sql('c') . ' ORDER BY c.Prezime, c.Ime');
$vrste = redovi('SELECT * FROM VrsteAkcija ORDER BY Naziv');
$upit = filtar_upit($f);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'posalji') {
        $prim = array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) ($_POST['primatelji'] ?? ''))));
        if (!$prim) {
            poruka('Unesite e-mail primatelja.', 'warning');
        } else {
            try {
                posalji_mail($prim, 'Radne akcije – ' . udruga_kratko(), izvjestaj_html($grupe, 'Radne akcije: ' . $opis, false, $f['ClanId'] === null), true, null, [
                    'radne-akcije-' . date('Y-m-d') . '.pdf' => izvjestaj_pdf($grupe, 'Radne akcije', $opis, $f['ClanId'] === null),
                    'radne-akcije-' . date('Y-m-d') . '.csv' => izvjestaj_csv($grupe),
                ]);
                dnevnik('Izvještaj poslan e-poštom', null, null, implode(', ', $prim));
                poruka('Izvještaj je poslan.');
            } catch (Throwable $e) {
                poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    } elseif ($radnja === 'izvadci') {
        @set_time_limit(300);
        $ids = [];
        foreach ($grupe as $g) {
            foreach ($g['retci'] as $r) {
                $ids[(int) $r['ClanId']] = true;
            }
        }
        $poslano = 0;
        $bez = 0;
        foreach (array_keys($ids) as $cid) {
            $c = clan($cid);
            if (!$c || !trim((string) $c['Email'])) {
                $bez++;
                continue;
            }
            [$nasl, $tekst, $gr] = poruka_bodovi($c, $f);
            try {
                posalji_mail($c['Email'], $nasl, tekst_u_html($tekst), true, null, [
                    'radne-akcije-' . bez_dijakritika($c['Prezime']) . '.pdf' => izvjestaj_pdf($gr, 'Radne akcije – ' . puno_ime($c), $opis),
                ]);
                $poslano++;
            } catch (Throwable $e) {
                poruka('Greška kod ' . e(puno_ime($c)) . ': ' . e($e->getMessage()), 'danger');
                break;
            }
        }
        dnevnik('Izvadci radnih akcija poslani članovima', null, null, "$poslano poslano, $bez bez e-maila");
        poruka("Poslano $poslano izvadaka. Bez e-mail adrese: $bez.");
    }
    preusmjeri('izvjestaji/akcije', $upit);
}
$sel = fn($kl, $v) => sel($kl, $v);
$ukupnoAkcija = array_sum(array_map(fn($g) => count($g['retci']), $grupe));
$ukupnoBodova = array_sum(array_map(fn($g) => ukupno_bodova($g['retci']), $grupe));
ob_start(); ?>
<h1 class="h3 mb-3 no-print">Izvještaj radnih akcija</h1>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="izvjestaji/akcije">
    <div class="row g-2">
        <div class="col-6 col-md-3"><label class="form-label small">Razdoblje</label>
            <select name="Razdoblje" class="form-select form-select-sm" data-auto>
                <?php foreach (RAZDOBLJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Razdoblje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <?php if ($f['Razdoblje'] === 'Mjeseci'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od mjeseca</label><input type="month" name="Od" class="form-control form-control-sm" value="<?= e(substr($f['Od'] ?? date('Y-m'), 0, 7)) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do mjeseca</label><input type="month" name="Do" class="form-control form-control-sm" value="<?= e(substr($f['Do'] ?? date('Y-m'), 0, 7)) ?>"></div>
        <?php elseif ($f['Razdoblje'] === 'Slobodno'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>"></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small">Sekcija</label>
            <select name="SekcijaId" class="form-select form-select-sm">
                <option value="">Sve</option>
                <?php foreach ($sekcije as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= $sel($s['Id'], $f['SekcijaId']) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
                <?php if ($k['SveSekcije']): ?><option value="-1"<?= $sel(-1, $f['SekcijaId']) ?>>Nije raspoređeno</option><?php endif; ?>
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label small">Član</label>
            <select name="ClanId" class="form-select form-select-sm"><option value="">Svi članovi</option>
                <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"<?= $sel($c['Id'], $f['ClanId']) ?>><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Vrsta</label>
            <select name="VrstaId" class="form-select form-select-sm"><option value="">Sve</option>
                <?php foreach ($vrste as $v): ?><option value="<?= (int) $v['Id'] ?>"<?= $sel($v['Id'], $f['VrstaId']) ?>><?= e($v['Naziv']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Status</label>
            <select name="Status" class="form-select form-select-sm"><option value="">Svi</option>
                <option value="Odobreno"<?= $f['Status'] === AKCIJA_ODOBRENO ? ' selected' : '' ?>>Odobreno</option>
                <option value="NaCekanju"<?= $f['Status'] === AKCIJA_CEKA ? ' selected' : '' ?>>Na čekanju</option>
                <option value="Odbijeno"<?= $f['Status'] === AKCIJA_ODBIJENO ? ' selected' : '' ?>>Odbijeno</option>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Sortiranje</label>
            <select name="Sortiranje" class="form-select form-select-sm">
                <?php foreach (SORTIRANJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Sortiranje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Grupiranje</label>
            <select name="Grupiranje" class="form-select form-select-sm">
                <?php foreach (GRUPIRANJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Grupiranje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/akcije-pdf', $upit + ['prikaz' => 1])) ?>">Pogledaj PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/akcije-pdf', $upit)) ?>" download>Preuzmi PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/akcije-csv', $upit)) ?>" download>Excel (CSV)</a>
    </div>
</form>
<?php if (posta_dostupna()): ?>
<form method="post" action="<?= e(url('izvjestaji/akcije', $upit)) ?>" class="d-flex flex-wrap gap-2 mb-3 no-print"><?= csrf() ?>
    <input name="primatelji" class="form-control form-control-sm" style="max-width:360px" placeholder="e-mail primatelja (više odvojiti zarezom)">
    <button class="btn btn-sm btn-outline-primary" name="radnja" value="posalji">Pošalji e-poštom</button>
    <button class="btn btn-sm btn-outline-success" name="radnja" value="izvadci" data-potvrda="Poslati svakom članu iz izvještaja njegov izvadak e-poštom?">Svakom članu njegov izvadak</button>
</form>
<?php else: ?>
    <p class="small text-muted no-print">Slanje e-poštom: podesiti u Sustav → E-pošta i adresa.</p>
<?php endif; ?>
<div class="mb-3">
    <div class="h5 mb-0"><?= e(udruga_naziv()) ?> – radne akcije</div>
    <div class="text-muted small"><?= e($opis) ?></div>
</div>
<?php foreach ($grupe as $g): if (!$g['retci'] && count($grupe) > 1) continue; ?>
    <?php if ($g['naziv'] !== ''): ?><h3 class="h6 mt-3"><?= e($g['naziv']) ?></h3><?php endif; ?>
    <?php if ($g['retci']): ?>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Datum</th><th>Član</th><th>Sekcija</th><th>Vrsta</th><th class="text-end">Sati</th><th>Opis</th><th>Status</th><th class="text-end">Bodovi</th></tr></thead>
        <tbody>
        <?php foreach ($g['retci'] as $r): ?>
            <tr><td class="text-nowrap"><?= e(date('d.m.Y', strtotime($r['Datum']))) ?></td><td><?= e($r['Clan']) ?></td><td><?= e($r['Sekcija']) ?></td><td><?= e($r['Vrsta']) ?></td>
                <td class="text-end"><?= e(broj($r['Sati'])) ?></td><td class="small"><?= e($r['Opis']) ?></td><td><?= STATUSI_AKCIJE[$r['Status']] ?></td><td class="text-end"><?= e(broj($r['Bodovi'], 2)) ?></td></tr>
        <?php endforeach; ?>
        <tr class="fw-bold"><td colspan="4">Ukupno (<?= count($g['retci']) ?>)</td><td class="text-end"><?= e(broj(ukupno_sati($g['retci']))) ?></td><td colspan="2"></td><td class="text-end"><?= e(broj(ukupno_bodova($g['retci']), 2)) ?></td></tr>
        </tbody>
    </table></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (count($grupe) > 1): ?><p class="fw-bold">Sveukupno: <?= $ukupnoAkcija ?> akcija, <?= e(broj($ukupnoBodova, 2)) ?> bodova</p><?php endif; ?>
<?php if ($ukupnoAkcija === 0): ?><p class="text-muted">Nema radnih akcija za odabrane uvjete.</p><?php endif; ?>
<?php stranica('Izvještaj radnih akcija', ob_get_clean());
