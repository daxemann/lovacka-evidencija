<?php
/**
 * Obavijesti udruge: primljene obavijesti (svi članovi) i pregled potvrda (pravo „Obavijesti – slanje“).
 * ?id= pojedinačna obavijest. POST radnje: potvrdi, usmeno, ponisti, obrisi.
 */
$k = korisnik();
$id = (int) ($_GET['id'] ?? 0);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $o = obav_obavijest((int) ($_POST['id'] ?? 0));
    if (!$o) {
        nije_pronadjeno('Obavijest');
    }
    if ($radnja === 'potvrdi') {
        if ($k['ClanId'] && obav_potvrdi((int) $o['Id'], $k['ClanId'])) {
            dnevnik('Obavijest potvrđena (pročitao/la)', 'Obavijest', (int) $o['Id'], $o['Naslov']);
        }
        $povratak = (string) ($_POST['povratak'] ?? '');
        // povratak samo na stranicu ove aplikacije (bez preusmjeravanja na drugu domenu)
        if ($povratak !== '' && str_starts_with($povratak, '/') && !str_starts_with($povratak, '//')) {
            header('Location: ' . $povratak);
            exit;
        }
        preusmjeri('obavijesti', ['id' => $o['Id']]);
    }
    if (!obav_smije_upravljati($o)) {
        zabranjeno();
    }
    if ($radnja === 'usmeno' || $radnja === 'ponisti') {
        $clanovi = array_values(array_filter(array_map('intval', (array) ($_POST['clanovi'] ?? []))));
        foreach ($clanovi as $c) {
            if ($radnja === 'usmeno') {
                q('UPDATE ObavijestPrimatelji SET Potvrdjeno=?, Nacin=?, Biljezio=? WHERE ObavijestId=? AND ClanId=? AND Potvrdjeno IS NULL',
                    [sada(), OBAV_NACIN_USMENO, $k['Naziv'], $o['Id'], $c]);
            } else {
                // poništiti se smije samo ručno upisano „usmeno“ – potvrdu člana nitko ne briše
                q('UPDATE ObavijestPrimatelji SET Potvrdjeno=NULL, Nacin=NULL, Biljezio=NULL WHERE ObavijestId=? AND ClanId=? AND Nacin=?',
                    [$o['Id'], $c, OBAV_NACIN_USMENO]);
            }
        }
        if ($clanovi) {
            dnevnik($radnja === 'usmeno' ? 'Obavijest – usmeno obaviješteni' : 'Obavijest – poništeno „usmeno“', 'Obavijest', (int) $o['Id'], $o['Naslov'] . ' · ' . count($clanovi) . ' član(ova)');
            poruka($radnja === 'usmeno' ? 'Označeno kao usmeno obaviješteno: ' . count($clanovi) . '.' : 'Poništeno.');
        }
        preusmjeri('obavijesti', ['id' => $o['Id']]);
    }
    if ($radnja === 'obrisi') {
        q('DELETE FROM Obavijesti WHERE Id=?', [$o['Id']]);
        dnevnik('Obrisana obavijest', 'Obavijest', (int) $o['Id'], $o['Naslov']);
        poruka('Obavijest je obrisana.');
        preusmjeri('obavijesti');
    }
    preusmjeri('obavijesti');
}

// ---------- pojedinačna obavijest ----------
if ($id) {
    $o = obav_obavijest($id);
    $moja = $o && $k['ClanId'] ? red('SELECT * FROM ObavijestPrimatelji WHERE ObavijestId=? AND ClanId=?', [$id, $k['ClanId']]) : null;
    $upravlja = $o && obav_smije_upravljati($o);
    if (!$o || (!$moja && !$upravlja)) {
        nije_pronadjeno('Obavijest');
    }
    // obična obavijest (bez obavezne potvrde) se otvaranjem označi kao pročitana
    if ($moja && $moja['Potvrdjeno'] === null && !$o['Obavezno']) {
        obav_potvrdi($id, $k['ClanId']);
        $moja = red('SELECT * FROM ObavijestPrimatelji WHERE ObavijestId=? AND ClanId=?', [$id, $k['ClanId']]);
        $o = obav_obavijest($id);
    }
    $primatelji = $upravlja ? obav_primatelji($id) : [];
    $nepotvrdjeni = array_values(array_filter($primatelji, fn($p) => $p['Potvrdjeno'] === null));
    $bezPristupa = count(array_filter($nepotvrdjeni, fn($p) => !$p['ImaPristup']));
    $rijec = $o['Obavezno'] ? 'potvrdilo' : 'pročitalo';
    ob_start(); ?>
<p class="mb-2 d-print-none"><a href="<?= e(url('obavijesti')) ?>">← Obavijesti</a></p>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
    <h1 class="h3 mb-0">📢 <?= e($o['Naslov']) ?></h1>
    <?php if ($upravlja): ?><div class="d-flex gap-2 d-print-none">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('izvoz/obavijest-pdf', ['id' => $id])) ?>" target="_blank" rel="noopener">PDF potvrda</a>
        <form method="post" action="<?= e(url('obavijesti')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-outline-danger btn-sm" data-potvrda="Obrisati obavijest zajedno sa svim potvrdama? Ovo se ne može vratiti.">Obriši</button></form>
    </div><?php endif; ?>
</div>
<p class="text-muted small"><?= e($o['KreiraoIme']) ?> · <?= e(datum_vrijeme($o['Kreirano'])) ?>
    <?php if ($o['Obavezno']): ?> · <span class="badge bg-warning text-dark">obavezna potvrda</span><?php endif; ?>
    <?php if ($upravlja): ?> · <?= e($o['Primatelji']) ?><?php endif; ?></p>
<div class="card mb-3"><div class="card-body" style="max-width:900px;line-height:1.6"><?= obav_tekst_html($o['Tekst']) ?></div></div>

<?php if ($moja): ?>
    <?php if ($moja['Potvrdjeno'] === null): ?>
        <form method="post" action="<?= e(url('obavijesti')) ?>" class="mb-4"><?= csrf() ?><input type="hidden" name="radnja" value="potvrdi"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-success btn-lg">✔ Pročitao/la sam</button></form>
    <?php else: ?>
        <p class="text-success mb-4">✔ <?= $moja['Nacin'] === OBAV_NACIN_USMENO ? 'Usmeno obaviješten/a' : ($o['Obavezno'] ? 'Potvrdili ste' : 'Pročitano') ?> <?= e(datum_vrijeme($moja['Potvrdjeno'])) ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($upravlja): $pot = (int) $o['Potvrdjeno']; $uk = (int) $o['Primatelja']; $pct = $uk ? round($pot * 100 / $uk) : 0; ?>
<h2 class="h5 mt-4 mb-2"><?= $o['Obavezno'] ? 'Potvrde čitanja' : 'Pročitano' ?>: <?= $pot ?> / <?= $uk ?></h2>
<div class="progress mb-3" style="height:10px;max-width:520px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div></div>
<?php if ($bezPristupa): ?>
    <div class="alert alert-info small py-2" style="max-width:900px"><?= $bezPristupa ?> član(ova) nema pristup aplikaciji i ne može potvrditi sam. Kad ih obavijestite usmeno ili papirom, označite ih kvačicom i „Usmeno obaviješten“.</div>
<?php endif; ?>
<form method="post" action="<?= e(url('obavijesti')) ?>"><?= csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="table-responsive" style="max-width:900px"><table class="table table-sm align-middle">
    <thead><tr><th style="width:2rem"></th><th>Član</th><th>Sekcija</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($primatelji as $p): $odobren = $p['Potvrdjeno'] !== null; $usmeno = $p['Nacin'] === OBAV_NACIN_USMENO; ?>
        <tr class="<?= $odobren ? '' : 'table-warning' ?>">
            <td><?php if (!$odobren || $usmeno): ?><input type="checkbox" class="form-check-input" name="clanovi[]" value="<?= (int) $p['ClanId'] ?>" aria-label="Odaberi"><?php endif; ?></td>
            <td><?= e($p['Prezime'] . ' ' . $p['Ime']) ?><?php if (!$p['ImaPristup']): ?> <span class="badge bg-light text-muted border" title="Član nema korisnički račun za aplikaciju">bez aplikacije</span><?php endif; ?></td>
            <td class="small text-muted"><?= e($p['Sekcija']) ?></td>
            <td class="small"><?php if (!$odobren): ?><span class="text-danger">nije <?= $o['Obavezno'] ? 'potvrdio' : 'pročitao' ?></span>
                <?php elseif ($usmeno): ?>🗣️ usmeno obaviješten <?= e(datum($p['Potvrdjeno'])) ?><?= $p['Biljezio'] ? ' <span class="text-muted">(' . e($p['Biljezio']) . ')</span>' : '' ?>
                <?php else: ?><span class="text-success">✔ <?= e(datum_vrijeme($p['Potvrdjeno'])) ?></span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="d-flex flex-wrap gap-2 d-print-none">
    <button class="btn btn-outline-primary btn-sm" name="radnja" value="usmeno">🗣️ Usmeno obaviješten (odabrani)</button>
    <button class="btn btn-outline-secondary btn-sm" name="radnja" value="ponisti" data-potvrda="Poništiti „usmeno obaviješten“ za odabrane?">Poništi „usmeno“</button>
</div>
</form>
<?php if ($nepotvrdjeni): ?>
<div class="mt-4 d-print-none" style="max-width:900px">
    <h3 class="h6">Podsjetnik</h3>
    <p class="small text-muted mb-2">Obavezna obavijest se članovima s aplikacijom prikazuje pri svakom otvaranju dok je ne potvrde. Za ostale možete podijeliti podsjetnik:</p>
    <div class="d-flex flex-wrap gap-2"><?= gumbi_dijeljenja($o['Naslov'] . "\n\n" . trim($o['Tekst']) . "\n\n" . udruga_naziv()) ?></div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php
    stranica($o['Naslov'], ob_get_clean());
}

// ---------- popis ----------
$moje = obav_moje($k['ClanId']);
$poslane = obav_poslane();
ob_start(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">📢 Obavijesti</h1>
    <?php if (ima(P_OBAVIJESTI)): ?><a class="btn btn-primary btn-sm" href="<?= e(url('obavijesti/nova')) ?>">+ Nova obavijest</a><?php endif; ?>
</div>

<?php if ($poslane): ?>
<h2 class="h6 text-muted">Poslane obavijesti</h2>
<div class="list-group mb-4" style="max-width:900px">
<?php foreach ($poslane as $o): $pot = (int) $o['Potvrdjeno']; $uk = (int) $o['Primatelja']; ?>
    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e(url('obavijesti', ['id' => $o['Id']])) ?>">
        <div class="flex-grow-1 overflow-hidden">
            <div class="text-truncate fw-semibold"><?= e($o['Naslov']) ?></div>
            <div class="small text-muted text-truncate"><?= e(datum_vrijeme($o['Kreirano'])) ?> · <?= e($o['KreiraoIme']) ?> · <?= e($o['Primatelji']) ?></div>
        </div>
        <div class="text-end text-nowrap">
            <?php if ($o['Obavezno']): ?><div class="small text-muted">obavezna</div><?php endif; ?>
            <span class="badge <?= $pot >= $uk ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $pot ?> / <?= $uk ?></span>
        </div>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($k['ClanId']): ?>
<?php if ($poslane): ?><h2 class="h6 text-muted">Moje obavijesti</h2><?php endif; ?>
<div class="list-group" style="max-width:900px">
<?php foreach ($moje as $o): $nova = $o['MojaPotvrda'] === null; ?>
    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3 <?= $nova ? 'fw-semibold' : '' ?>" href="<?= e(url('obavijesti', ['id' => $o['Id']])) ?>">
        <div class="flex-grow-1 overflow-hidden">
            <div class="text-truncate"><?= e($o['Naslov']) ?></div>
            <div class="small text-muted text-truncate fw-normal"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', $o['Tekst']), 0, 110, '…')) ?></div>
        </div>
        <div class="text-end text-nowrap"><div class="small text-muted fw-normal"><?= e(datum($o['Kreirano'])) ?></div>
            <?php if ($nova): ?><span class="badge bg-danger"><?= $o['Obavezno'] ? 'potvrdi' : 'novo' ?></span><?php else: ?><span class="text-success small">✔</span><?php endif; ?></div>
    </a>
<?php endforeach; ?>
</div>
<?php if (!$moje): ?><div class="text-muted py-4 text-center">Još nema obavijesti.</div><?php endif; ?>
<?php elseif (!$poslane): ?>
<div class="text-muted py-4 text-center">Još nema obavijesti.</div>
<?php endif; ?>
<?php stranica('Obavijesti', ob_get_clean());
