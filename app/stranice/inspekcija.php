<?php
/**
 * Pregled evidencije dezinfekcije za inspekciju (javna stranica): QR oznaka + lozinka koju daje lovočuvar.
 * Svaki pristup, PDF i slanje bilježi se u dnevnik.
 */
$tko = ['Id' => null, 'KorisnickoIme' => 'inspekcija'];
$tok = (string) ($_GET['k'] ?? '');
$ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$ip = trim(explode(',', $ip)[0]);
if ($tok === '' || !hash_equals(dez_insp_token(), $tok)) {
    stranica('Inspekcija', '<div class="alert alert-warning">Ova QR oznaka nije (više) važeća. Obratite se lovočuvaru.</div>', 'javno');
}
if (!dez_insp_lozinka()) {
    stranica('Inspekcija', '<div class="alert alert-secondary">Pristup za inspekciju trenutno nije uključen. Obratite se lovočuvaru ili predsjedniku udruge.</div>', 'javno');
}
$ovdje = ['k' => $tok];

if (je_post() && ($_POST['radnja'] ?? '') === 'odjava') {
    unset($_SESSION['insp']);
    preusmjeri('inspekcija', $ovdje);
}

if (!dez_insp_prijavljen()) {
    // zaštita od pogađanja: 5 pokušaja / 10 min po sesiji, 20 / 15 min ukupno
    $sad = time();
    $_SESSION['insp_pok'] = array_values(array_filter((array) ($_SESSION['insp_pok'] ?? []), fn($t) => $t > $sad - 600));
    $glob = array_values(array_filter((array) json_decode((string) postavka('Dez.InspNeuspjesi', '[]'), true), fn($t) => $t > $sad - 900));
    $zakljucano = count($_SESSION['insp_pok']) >= 5 || count($glob) >= 20;
    if (je_post() && ($_POST['radnja'] ?? '') === 'prijava' && !$zakljucano) {
        usleep(400000);
        if (hash_equals((string) dez_insp_lozinka(), (string) ($_POST['lozinka'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['insp'] = ['o' => dez_insp_otisak(), 'do' => $sad + 7200];
            $_SESSION['insp_pok'] = [];
            dnevnik('Inspekcija – pristup', null, null, 'IP ' . $ip, $tko);
            preusmjeri('inspekcija', $ovdje);
        }
        $_SESSION['insp_pok'][] = $sad;
        $glob[] = $sad;
        spremi_postavku('Dez.InspNeuspjesi', json_encode($glob));
        if (count($glob) === 20) {
            dnevnik('Inspekcija – previše pogrešnih lozinki (zaključano 15 min)', null, null, 'IP ' . $ip, $tko);
        }
        poruka('Pogrešna lozinka.', 'danger');
        preusmjeri('inspekcija', $ovdje);
    }
    ob_start(); ?>
    <div class="card shadow-sm mx-auto" style="max-width:420px"><div class="card-body p-4">
        <h1 class="h5">Evidencija dezinfekcije</h1>
        <p class="text-muted small">Pregled za inspekciju. Lozinku daje lovočuvar udruge.</p>
        <?php if ($zakljucano): ?><div class="alert alert-danger small">Previše pogrešnih pokušaja. Pokušajte ponovno za nekoliko minuta.</div><?php endif; ?>
        <form method="post" action="<?= e(url('inspekcija', $ovdje)) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="prijava">
            <label class="form-label">Lozinka</label>
            <input name="lozinka" type="password" class="form-control form-control-lg mb-3" maxlength="8" autocomplete="off" autofocus required<?= $zakljucano ? ' disabled' : '' ?>>
            <button class="btn btn-primary w-100"<?= $zakljucano ? ' disabled' : '' ?>>Otvori evidenciju</button>
        </form>
    </div></div>
    <?php stranica('Inspekcija', ob_get_clean(), 'javno');
}

// ---------- prijavljen ----------
$stanice = dez_stanice(false);
$ids = array_map(fn($s) => (int) $s['Id'], $stanice);
$f = dez_filtar($_GET);
$f['Ponisteni'] = false;
$f['Trazi'] = '';
$upit = dez_filtar_upit($f);
$sekcijeSt = [];
foreach ($stanice as $s) {
    if ($s['SekcijaId'] !== null) {
        $sekcijeSt[(int) $s['SekcijaId']] = $s['SekcijaNaziv'];
    }
}
if ($f['SekcijaId'] !== null && !isset($sekcijeSt[$f['SekcijaId']])) {
    $f['SekcijaId'] = null;
}
$upisi = dez_upisi($f, $ids);
$stF = $f['SekcijaId'] ? array_values(array_filter($stanice, fn($s) => (int) $s['SekcijaId'] === $f['SekcijaId'])) : $stanice;
$sekOpis = $f['SekcijaId'] ? 'sekcija ' . $sekcijeSt[$f['SekcijaId']] : 'sve sekcije';
$opis = opis_raspona($f);

if (($_GET['pdf'] ?? '') === '1') {
    dnevnik('Inspekcija – PDF', null, null, $opis . ', ' . $sekOpis . ' · IP ' . $ip, $tko);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="dezinfekcija-' . date('Y-m-d') . '.pdf"');
    echo dez_pdf($upisi, $stF, $opis, $sekOpis);
    exit;
}
if (je_post() && ($_POST['radnja'] ?? '') === 'posalji') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $poslano = (int) ($_SESSION['insp_mail'] ?? 0);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        poruka('Upišite ispravnu e-mail adresu.', 'warning');
    } elseif ($poslano >= 10) {
        poruka('Dosegnut je broj slanja za ovu prijavu.', 'warning');
    } else {
        try {
            posalji_mail($email, 'Evidencija dezinfekcije – ' . udruga_naziv(), dez_mail_html($upisi, $opis, $sekOpis), true, null,
                ['dezinfekcija-' . date('Y-m-d') . '.pdf' => dez_pdf($upisi, $stF, $opis, $sekOpis)]);
            $_SESSION['insp_mail'] = $poslano + 1;
            dnevnik('Inspekcija – poslano e-poštom', null, null, $email . ' · ' . $opis . ', ' . $sekOpis . ' · IP ' . $ip, $tko);
            poruka('Evidencija (PDF) je poslana na ' . e($email) . '.');
        } catch (Throwable $e) {
            poruka('Slanje nije uspjelo. Preuzmite PDF ili koristite ispis.', 'danger');
        }
    }
    preusmjeri('inspekcija', $ovdje + $upit);
}
ob_start(); ?>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="inspekcija"><input type="hidden" name="k" value="<?= e($tok) ?>">
    <div class="row g-2 align-items-end">
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
        <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">🖨 Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('inspekcija', $ovdje + $upit + ['pdf' => 1])) ?>">PDF</a>
    </div>
</form>
<?php if (posta_dostupna()): ?>
<form method="post" action="<?= e(url('inspekcija', $ovdje + $upit)) ?>" class="d-flex flex-wrap gap-2 mb-3 no-print"><?= csrf() ?><input type="hidden" name="radnja" value="posalji">
    <input name="email" type="email" class="form-control form-control-sm" style="max-width:320px" placeholder="vaša e-mail adresa" required>
    <button class="btn btn-sm btn-outline-primary">Pošalji PDF na e-mail</button>
</form>
<?php endif; ?>
<div class="mb-2">
    <div class="h5 mb-0">Evidencija dezinfekcije vozila, obuće i opreme</div>
    <div class="text-muted small"><?= e(udruga_naziv()) ?><?= postavka('Dez.Loviste') ? ' · ' . e(postavka('Dez.Loviste')) : '' ?> · <?= e($opis) ?> · <?= e($sekOpis) ?> · <?= count($upisi) ?> upisa</div>
    <div class="text-muted small"><?php foreach ($stF as $s): ?><div><?= e($s['Naziv']) ?><?= $s['Sredstvo'] ? ' · sredstvo: ' . e($s['Sredstvo']) : '' ?></div><?php endforeach; ?></div>
</div>
<?php if ($upisi): ?>
<div class="table-responsive bg-white"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Datum</th><th>Vrijeme</th><th>Ime i prezime</th><th>Član/gost</th><th>Reg. oznaka</th><th>Razlog</th><th>Smjer</th><th>Dezinficirano</th>
        <?= count($stF) > 1 ? '<th>Stanica</th>' : '' ?><th>Napomena</th><th>Lokacija</th></tr></thead>
    <tbody>
    <?php foreach ($upisi as $u): $t = strtotime($u['Vrijeme']); ?>
        <tr><td class="text-nowrap"><?= date('d.m.Y.', $t) ?></td><td><?= date('H:i', $t) ?></td><td><?= e(dez_ime($u)) ?></td><td><?= $u['Gost'] ? 'gost' : 'član' ?></td>
            <td class="text-nowrap"><?= e($u['Oznaka'] ?? '—') ?></td><td class="small"><?= e($u['Razlog']) ?></td><td><?= DEZ_SMJER[$u['Smjer']] ?></td><td class="small"><?= e(dez_dezinficirano($u)) ?></td>
            <?= count($stF) > 1 ? '<td class="small">' . e($u['Stanica']) . '</td>' : '' ?><td class="small text-muted"><?= e(dez_upisao($u)) ?></td><td class="small"><?= e(dez_oznaka_lokacije($u, true)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <p class="text-muted">Nema upisa za odabrano razdoblje.</p>
<?php endif; ?>
<form method="post" action="<?= e(url('inspekcija', $ovdje)) ?>" class="mt-4 no-print"><?= csrf() ?><input type="hidden" name="radnja" value="odjava">
    <button class="btn btn-sm btn-link text-muted">Odjava</button></form>
<?php stranica('Inspekcija – evidencija dezinfekcije', ob_get_clean(), 'javno');
