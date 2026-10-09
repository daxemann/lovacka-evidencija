<?php
/** Knjiga dezinfekcije: pregled po sekcijama, ispis, PDF, e-pošta, poništavanje. */
trazi(P_DEZ_PREGLED);
dez_osiguraj_stanice();
$f = dez_filtar($_GET);
$upit = dez_filtar_upit($f);
$mob = $f['Vrsta'] === 'M';
$stanice = dez_stanice(true, $f['Vrsta']);
$ids = array_map(fn($s) => (int) $s['Id'], $stanice);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'ponisti' && ima(P_DEZ_UREDI)) {
        $u = red('SELECT * FROM DezUpisi WHERE Id=?', [(int) ($_POST['id'] ?? 0)]);
        $razlog = mb_substr(ul_str('razlog'), 0, 200);
        if (!$u || !in_array((int) $u['StanicaId'], $ids, true)) {
            zabranjeno();
        }
        if ($razlog === '') {
            poruka('Upišite razlog poništenja.', 'warning');
        } else {
            azuriraj('DezUpisi', (int) $u['Id'], ['Ponisteno' => 1, 'PonistenoRazlog' => $razlog . ' (' . korisnik()['Naziv'] . ', ' . date('d.m.Y. H:i') . ')']);
            dnevnik('Dezinfekcija – poništen upis', 'DezUpis', (int) $u['Id'], dez_ime($u) . ' ' . datum_vrijeme($u['Vrijeme']) . ': ' . $razlog);
            poruka('Upis je poništen (ostaje vidljiv uz „prikaži poništene“).');
        }
    } elseif ($radnja === 'obrisi-ponistene' && ima(P_SUSTAV) && ima(P_DEZ_UREDI)) {
        // trajno brisanje samo već poništenih upisa (npr. probni upisi) – u odabranom prikazu, zapisano u dnevnik
        $fp = $f;
        $fp['Ponisteni'] = true;
        $za = array_values(array_filter(dez_upisi($fp, $ids), fn($u) => (int) $u['Ponisteno'] === 1));
        if ($za) {
            q('DELETE FROM DezUpisi WHERE Ponisteno=1 AND Id IN (' . implode(',', array_map(fn($u) => (int) $u['Id'], $za)) . ')');
            dnevnik('Dezinfekcija – trajno obrisani poništeni upisi', null, null, count($za) . ': ' . implode('; ', array_map(fn($u) => dez_ime($u) . ' ' . datum_vrijeme($u['Vrijeme']) . ' (' . $u['PonistenoRazlog'] . ')', $za)));
            poruka('Trajno obrisano ' . count($za) . ' poništenih upisa.');
        } else {
            poruka('Nema poništenih upisa u ovom prikazu.', 'warning');
        }
    } elseif ($radnja === 'posalji') {
        $prim = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) ($_POST['primatelji'] ?? '')))));
        $prim = array_filter($prim, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL));
        if (!$prim) {
            poruka('Unesite ispravnu e-mail adresu.', 'warning');
        } else {
            $upisi = dez_upisi($f, $ids);
            $st = $f['SekcijaId'] ? array_values(array_filter($stanice, fn($s) => (int) $s['SekcijaId'] === $f['SekcijaId'])) : $stanice;
            $sekOpis = $f['SekcijaId'] ? 'sekcija ' . naziv_sekcije($f['SekcijaId']) : 'sve sekcije';
            try {
                posalji_mail($prim, 'Evidencija dezinfekcije – ' . udruga_kratko(), dez_mail_html($upisi, opis_raspona($f), $sekOpis), true, null,
                    ['dezinfekcija-' . date('Y-m-d') . '.pdf' => dez_pdf($upisi, $st, opis_raspona($f), $sekOpis)]);
                dnevnik('Dezinfekcija – knjiga poslana e-poštom', null, null, implode(', ', $prim));
                poruka('Poslano.');
            } catch (Throwable $e) {
                poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    }
    preusmjeri('dezinfekcija', $upit);
}

$upisi = dez_upisi($f, $ids);
$unutra = $mob ? [] : dez_trenutno_unutra($ids);
$akcija = $f['AktivacijaId'] ? dez_aktivacija_po_id($f['AktivacijaId']) : null;
$sekcijeSt = [];
foreach ($stanice as $s) {
    if ($s['SekcijaId'] !== null) {
        $sekcijeSt[(int) $s['SekcijaId']] = $s['SekcijaNaziv'];
    }
}
$bezKoord = $mob ? [] : array_filter($stanice, fn($s) => $s['Lat'] === null && $s['Aktivna']);
$upozorenja = count(array_filter($upisi, fn($u) => in_array((int) $u['Lokacija'], [DEZ_LOK_NEPOUZDANO, DEZ_LOK_BEZ], true) && !$u['Ponisteno']));
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
    <h1 class="h3 mb-0 me-auto">Knjiga dezinfekcije</h1>
    <?php if (ima(P_DEZ_UREDI)): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('dezinfekcija/naknadno')) ?>">+ Naknadni upis</a><?php endif; ?>
    <?php if (ima(P_DEZ_POSTAVKE)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('dezinfekcija/postavke')) ?>">Stanice i QR</a><?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item"><a class="nav-link<?= $mob ? '' : ' active' ?>" href="<?= e(url('dezinfekcija', array_diff_key($upit, ['Vrsta' => 1, 'AktivacijaId' => 1]))) ?>">Stalne stanice</a></li>
    <li class="nav-item"><a class="nav-link<?= $mob ? ' active' : '' ?>" href="<?= e(url('dezinfekcija', ['Vrsta' => 'M'] + array_diff_key($upit, ['AktivacijaId' => 1]))) ?>">Mobilne stanice (skupni lov)</a></li>
</ul>
<?php if ($akcija): ?><div class="alert alert-light border no-print">Akcija: <b><?= e($akcija['Naziv']) ?></b> · <?= e(datum_vrijeme($akcija['Od'])) ?> · <?= e($akcija['Stanica']) ?>
    <a class="ms-2" href="<?= e(url('dezinfekcija', array_diff_key($upit, ['AktivacijaId' => 1]))) ?>">prikaži sve akcije</a></div><?php endif; ?>
<?php if ($bezKoord && ima(P_DEZ_POSTAVKE)): ?>
    <div class="alert alert-warning no-print">Bez koordinata: <?= e(implode(', ', array_map(fn($s) => $s['Naziv'], $bezKoord))) ?>.
        Upisi se bilježe bez provjere lokacije. <a href="<?= e(url('dezinfekcija/postavke')) ?>">Upisati koordinate →</a></div>
<?php endif; ?>
<?php if ($unutra): ?>
<div class="card mb-3 no-print"><div class="card-header d-flex">Trenutno u lovištu <span class="badge bg-success ms-2"><?= count($unutra) ?></span></div>
    <ul class="list-group list-group-flush small">
    <?php foreach ($unutra as $u): $sati = (time() - strtotime($u['Vrijeme'])) / 3600; ?>
        <li class="list-group-item d-flex flex-wrap gap-2"><b><?= e(dez_ime($u)) ?></b><?= $u['Gost'] ? '<span class="badge bg-secondary">gost</span>' : '' ?>
            <span class="text-muted"><?= e($u['Oznaka'] ?? '') ?> · <?= e($u['Razlog']) ?> · <?= e($u['Stanica']) ?></span>
            <span class="ms-auto <?= $sati > 12 ? 'text-danger fw-semibold' : 'text-muted' ?>">od <?= e(date('d.m. H:i', strtotime($u['Vrijeme']))) ?><?= $sati > 12 ? ' – nema odlaska!' : '' ?></span></li>
    <?php endforeach; ?>
    </ul></div>
<?php endif; ?>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="dezinfekcija">
    <?php if ($mob): ?><input type="hidden" name="Vrsta" value="M"><?php endif; ?><?php if ($f['AktivacijaId']): ?><input type="hidden" name="AktivacijaId" value="<?= (int) $f['AktivacijaId'] ?>"><?php endif; ?>
    <div class="row g-2">
        <div class="col-6 col-md-3"><label class="form-label small">Razdoblje</label>
            <select name="Razdoblje" class="form-select form-select-sm" data-auto>
                <?php foreach (RAZDOBLJA as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['Razdoblje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <?php if ($f['Razdoblje'] === 'Mjeseci'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od mjeseca</label><input type="month" name="Od" class="form-control form-control-sm" value="<?= e(substr($f['Od'] ?? date('Y-m'), 0, 7)) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do mjeseca</label><input type="month" name="Do" class="form-control form-control-sm" value="<?= e(substr($f['Do'] ?? date('Y-m'), 0, 7)) ?>"></div>
        <?php elseif ($f['Razdoblje'] === 'Slobodno'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>"></div>
        <?php endif; ?>
        <?php if (count($sekcijeSt) > 1): ?>
        <div class="col-6 col-md-2"><label class="form-label small">Sekcija</label>
            <select name="SekcijaId" class="form-select form-select-sm"><option value="">Sve sekcije</option>
                <?php foreach ($sekcijeSt as $sid => $n): ?><option value="<?= $sid ?>"<?= sel($sid, $f['SekcijaId']) ?>><?= e($n) ?></option><?php endforeach; ?>
            </select></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small">Smjer</label>
            <select name="Smjer" class="form-select form-select-sm"><option value="">Oba</option>
                <?php foreach (DEZ_SMJER as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['Smjer']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Ime ili oznaka</label><input name="Trazi" class="form-control form-control-sm" value="<?= e($f['Trazi']) ?>"></div>
        <div class="col-6 col-md-1 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/dez-pdf', $upit + ['prikaz' => 1])) ?>">Pogledaj PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/dez-pdf', $upit)) ?>" download>Preuzmi PDF</a>
        <?php if (ima(P_DEZ_UREDI)): ?><div class="form-check form-check-inline ms-2 small"><input class="form-check-input" type="checkbox" name="Ponisteni" value="1" id="pon"<?= chk($f['Ponisteni']) ?> data-auto>
            <label class="form-check-label" for="pon">prikaži poništene</label></div><?php endif; ?>
    </div>
</form>
<?php $brPon = $f['Ponisteni'] ? count(array_filter($upisi, fn($u) => (int) $u['Ponisteno'] === 1)) : 0; if ($brPon && ima(P_SUSTAV) && ima(P_DEZ_UREDI)): ?>
<form method="post" action="<?= e(url('dezinfekcija', $upit)) ?>" class="mb-3 no-print"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-ponistene">
    <button class="btn btn-sm btn-outline-danger" data-potvrda="Trajno obrisati <?= $brPon ?> poništenih upisa iz ovog prikaza? Ovo se ne može vratiti.">🗑 Trajno obriši poništene (<?= $brPon ?>)</button>
    <span class="small text-muted ms-2">npr. probni upisi – samo glavni admin, zapisuje se u dnevnik</span></form>
<?php endif; ?>
<?php if (posta_dostupna()): ?>
<form method="post" action="<?= e(url('dezinfekcija', $upit)) ?>" class="d-flex flex-wrap gap-2 mb-3 no-print"><?= csrf() ?>
    <input name="primatelji" type="text" inputmode="email" class="form-control form-control-sm" style="max-width:340px" placeholder="e-mail (PDF u privitku)">
    <button class="btn btn-sm btn-outline-primary" name="radnja" value="posalji">Pošalji e-poštom</button>
</form>
<?php endif; ?>
<div class="mb-2">
    <div class="h5 mb-0"><?= e(udruga_naziv()) ?> – evidencija dezinfekcije<?= $mob ? ' (mobilne stanice)' : '' ?></div>
    <div class="text-muted small"><?= e(opis_raspona($f)) ?> · <?= e($f['SekcijaId'] ? 'sekcija ' . naziv_sekcije($f['SekcijaId']) : 'sve sekcije') ?> · <?= count($upisi) ?> upisa
        <?= $upozorenja ? ' · <span class="text-warning-emphasis">⚠ ' . $upozorenja . ' s nepouzdanom ili bez lokacije</span>' : '' ?></div>
</div>
<?php if ($upisi): ?>
<div class="table-responsive"><table class="table table-sm align-middle dez-tablica">
    <thead><tr><th>Datum</th><th>Vrijeme</th><th>Ime i prezime</th><th>Reg. oznaka</th><th>Razlog</th><th>Smjer</th><th><?= $mob ? 'Akcija' : (count($sekcijeSt) > 1 ? 'Sekcija' : 'Stanica') ?></th><th>Napomena</th><th>Lokacija</th>
        <?php if (ima(P_DEZ_UREDI)): ?><th class="no-print"></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($upisi as $u): $t = strtotime($u['Vrijeme']); ?>
        <tr class="<?= $u['Ponisteno'] ? 'dez-ponisteno' : '' ?>">
            <td class="text-nowrap"><?= date('d.m.Y.', $t) ?></td><td><?= date('H:i', $t) ?></td>
            <td><?= e(dez_ime($u)) ?><?= $u['Gost'] ? ' <span class="badge bg-secondary">gost</span>' : '' ?></td>
            <td class="text-nowrap"><?= e($u['Oznaka'] ?? '—') ?></td><td class="small"><?= e($u['Razlog']) ?></td>
            <td><span class="badge <?= $u['Smjer'] === 'D' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= DEZ_SMJER[$u['Smjer']] ?></span></td>
            <td class="small"><?= e($mob ? ($u['Akcija'] ?? $u['Stanica']) : (count($sekcijeSt) > 1 ? ($u['SekcijaNaziv'] ?? $u['Stanica']) : $u['Stanica'])) ?></td>
            <td class="small text-muted"><?= e(dez_upisao($u)) ?><?= $u['Naknadno'] && $u['NaknadnoRazlog'] ? ' · ' . e($u['NaknadnoRazlog']) : '' ?>
                <?= $u['Ponisteno'] ? '<div class="text-danger">poništeno: ' . e($u['PonistenoRazlog']) . '</div>' : '' ?></td>
            <td class="small text-nowrap"><?= dez_oznaka_lokacije($u) ?></td>
            <?php if (ima(P_DEZ_UREDI)): ?><td class="no-print"><?php if (!$u['Ponisteno']): ?>
                <details class="dez-ponisti"><summary class="small text-danger">Poništi</summary>
                    <form method="post" action="<?= e(url('dezinfekcija', $upit)) ?>" class="d-flex gap-1 mt-1"><?= csrf() ?><input type="hidden" name="radnja" value="ponisti"><input type="hidden" name="id" value="<?= (int) $u['Id'] ?>">
                        <input name="razlog" class="form-control form-control-sm" placeholder="razlog" required maxlength="200" style="min-width:9rem"><button class="btn btn-sm btn-danger">OK</button></form></details>
            <?php endif; ?></td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <p class="text-muted">Nema upisa za odabrane uvjete.</p>
<?php endif; ?>
<?php stranica('Knjiga dezinfekcije', ob_get_clean());
