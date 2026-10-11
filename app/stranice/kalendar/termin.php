<?php
/** Jedan termin: podaci, karta, dolazim / ne dolazim, popis dolazaka, dijeljenje, upis radne akcije. */
$k = korisnik();
$id = (int) ul_int('id');
$d = dogadjaj($id);
if (!$d) {
    nije_pronadjeno('Termin');
}
$ured = moze_uredjivati_dogadjaj($d);
$v = vrsta_dogadjaja($d['Vrsta']);
$saPsima = !empty($v['psi']);
$mojiPsi = $saPsima && $k['ClanId'] ? aktivni_psi((int) $k['ClanId']) : [];
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if (in_array($radnja, ['dolazim', 'ne-dolazim', 'ponisti'], true) && $k['ClanId']) {
        if ($radnja === 'ponisti') {
            q('DELETE FROM DogadjajOdgovori WHERE DogadjajId=? AND ClanId=?', [$id, $k['ClanId']]);
        } else {
            $psi = $radnja === 'dolazim' ? array_values(array_intersect(array_map('intval', (array) ($_POST['psi'] ?? [])), array_keys($mojiPsi))) : [];
            q('INSERT INTO DogadjajOdgovori (DogadjajId, ClanId, Dolazi, Vrijeme, PsiIds) VALUES (?,?,?,?,?) ON CONFLICT(DogadjajId, ClanId) DO UPDATE SET Dolazi=excluded.Dolazi, Vrijeme=excluded.Vrijeme, PsiIds=excluded.PsiIds',
                [$id, $k['ClanId'], $radnja === 'dolazim' ? 1 : 0, sada(), $psi ? implode(',', $psi) : null]);
        }
    } elseif ($radnja === 'obrisi' && $ured) {
        q('DELETE FROM Dogadjaji WHERE Id=?', [$id]);
        dnevnik('Obrisan termin', null, null, $d['Naslov'] . ' ' . datum($d['Pocetak']));
        poruka('Termin je obrisan.');
        preusmjeri('kalendar');
    }
    preusmjeri('kalendar/termin', ['id' => $id]);
}
$mojOdg = $k['ClanId'] ? red('SELECT Dolazi, PsiIds FROM DogadjajOdgovori WHERE DogadjajId=? AND ClanId=?', [$id, $k['ClanId']]) : null;
$moj = $mojOdg['Dolazi'] ?? null;
$mojiOdabrani = psi_ids($mojOdg['PsiIds'] ?? null);
$odg = redovi('SELECT o.Dolazi, o.PsiIds, c.Id, c.Ime, c.Prezime, c.Nadimak, c.FotoDatoteka FROM DogadjajOdgovori o JOIN Clanovi c ON c.Id=o.ClanId WHERE o.DogadjajId=? ORDER BY o.Dolazi DESC, c.Prezime, c.Ime', [$id]);
$dolaze = array_values(array_filter($odg, fn($o) => (int) $o['Dolazi'] === 1));
$imenaPsa = [];
if ($saPsima && ($sviIds = array_merge(...array_map(fn($o) => psi_ids($o['PsiIds']), $dolaze ?: [['PsiIds' => null]])))) {
    foreach (redovi('SELECT Id, Ime, Pasmina FROM LovniPsi WHERE Id IN (' . implode(',', $sviIds) . ')') as $p) {
        $imenaPsa[(int) $p['Id']] = $p;
    }
}
$brojPasa = count($imenaPsa);
$neDolaze = array_values(array_filter($odg, fn($o) => (int) $o['Dolazi'] === 0));
$prosao = substr($d['Kraj'] ?: $d['Pocetak'], 0, 10) < danas();
$karta = karta_url($d['Mjesto']);
$tekst = tekst_dogadjaja($d);
$gumb = fn(string $r, string $t, string $kl) => '<form method="post" class="d-inline">' . csrf() . '<button name="radnja" value="' . $r . '" class="btn ' . $kl . '">' . $t . '</button></form>';
ob_start(); ?>
<div class="mb-2 small"><a href="<?= e(url('kalendar')) ?>">← Kalendar</a></div>
<div class="card mb-3" style="border-left:6px solid <?= e($v['boja']) ?>"><div class="card-body">
    <div class="d-flex flex-wrap gap-2 align-items-start">
        <div class="me-auto">
            <span class="badge mb-1" style="background:<?= e($v['boja']) ?>"><?= e($d['Vrsta']) ?></span>
            <?php if (!$d['ZaSve']): ?><span class="badge text-bg-light border mb-1">samo: <?= e(implode(', ', $d['Sekcije'])) ?></span><?php else: ?><span class="badge text-bg-light border mb-1">cijela udruga</span><?php endif; ?>
            <h1 class="h4 mb-1"><?= e($d['Naslov']) ?></h1>
            <div class="fw-semibold">🕒 <?= e(oznaka_vremena($d)) ?> <?= substr($d['Pocetak'], 0, 4) !== date('Y') ? e(substr($d['Pocetak'], 0, 4)) . '.' : '' ?></div>
            <?php if ($d['Mjesto']): ?><div>📍 <?= $karta ? '<a href="' . e($karta) . '" target="_blank" rel="noopener">' . e(preg_match('#^https?://#i', $d['Mjesto']) ? 'Otvori kartu' : $d['Mjesto']) . '</a>' : e($d['Mjesto']) ?></div><?php endif; ?>
        </div>
        <?php if ($ured): ?>
            <div class="d-flex gap-1">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('kalendar/uredi', ['id' => $id])) ?>">Uredi</a>
                <form method="post"><?= csrf() ?><button name="radnja" value="obrisi" class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati termin? Brišu se i odgovori članova.">Obriši</button></form>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($d['Opis']): ?><div class="mt-3" style="white-space:pre-line"><?= e($d['Opis']) ?></div><?php endif; ?>
    <div class="small text-muted mt-3">Upisao/la: <?= e($d['KreiraoIme'] ?: '—') ?> · <?= e(datum_vrijeme($d['Kreirano'])) ?></div>
</div></div>

<?php if ($k['ClanId'] && !$prosao): ?>
<div class="card mb-3"><div class="card-body">
    <div class="small text-muted mb-2">Dolazite? (nije obavezno – organizatoru pomaže u planiranju)</div>
    <?php if ($mojiPsi): ?>
    <form method="post" class="mb-2"><?= csrf() ?>
        <div class="small fw-semibold mb-1">🐕 Dovodim psa:</div>
        <div class="d-flex flex-wrap gap-3 mb-2">
            <?php foreach ($mojiPsi as $pid => $p): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="psi[]" value="<?= $pid ?>" id="pas<?= $pid ?>"<?= chk(in_array($pid, $mojiOdabrani, true)) ?>>
                <label class="form-check-label" for="pas<?= $pid ?>"><?= e(opis_psa($p)) ?></label></div><?php endforeach; ?>
        </div>
        <button name="radnja" value="dolazim" class="btn <?= $moj !== null && (int) $moj === 1 ? 'btn-success' : 'btn-outline-success' ?>">✔ Dolazim<?= $moj !== null && (int) $moj === 1 ? ' (spremi pse)' : '' ?></button>
        <span class="small text-muted ms-1">bez kvačice = dolazim bez psa</span>
    </form>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?php if (!$mojiPsi) echo $gumb('dolazim', '✔ Dolazim', $moj !== null && (int) $moj === 1 ? 'btn-success' : 'btn-outline-success'); ?>
        <?= $gumb('ne-dolazim', '✖ Ne dolazim', $moj !== null && (int) $moj === 0 ? 'btn-secondary' : 'btn-outline-secondary') ?>
        <?php if ($moj !== null): ?><?= $gumb('ponisti', 'poništi odgovor', 'btn-link btn-sm') ?><?php endif; ?>
    </div>
</div></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-header">Dolaze <span class="badge bg-success"><?= count($dolaze) ?></span><?php if ($saPsima): ?> <span class="badge text-bg-light border">🐕 <?= $brojPasa ?> <?= $brojPasa === 1 ? 'pas' : 'pasa' ?></span><?php endif; ?></div>
        <ul class="list-group list-group-flush">
            <?php if (!$dolaze): ?><li class="list-group-item text-muted small">Još nitko nije potvrdio.</li><?php endif; ?>
            <?php foreach ($dolaze as $o): ?><li class="list-group-item d-flex align-items-center gap-2 py-1"><?= avatar($o) ?> <?= e(puno_ime($o)) ?>
                <?php if ($saPsima): $ps = array_filter(array_map(fn($x) => $imenaPsa[$x] ?? null, psi_ids($o['PsiIds']))); ?><span class="ms-auto small"><?= $ps ? '🐕 ' . e(implode(', ', array_map(fn($p) => $p['Ime'], $ps))) : '<span class="text-muted">bez psa</span>' ?></span><?php endif; ?></li><?php endforeach; ?>
        </ul></div></div>
    <?php if ($neDolaze): ?>
    <div class="col-md-6"><div class="card h-100"><div class="card-header">Ne dolaze <span class="badge bg-secondary"><?= count($neDolaze) ?></span></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($neDolaze as $o): ?><li class="list-group-item d-flex align-items-center gap-2 py-1 text-muted"><?= avatar($o) ?> <?= e(puno_ime($o)) ?></li><?php endforeach; ?>
        </ul></div></div>
    <?php endif; ?>
</div>

<?php if ($saPsima && ima(P_AKCIJE_ODOBRI) && $brojPasa): ?>
<div class="card mb-3 border-warning-subtle"><div class="card-body small">
    🐕 <b>Bodovi za pse na lovu</b>: <?= $d['PsiBodovano'] ? 'upisani ' . e(datum($d['PsiBodovano'])) . ' – čekaju odobrenje u <a href="' . e(url('akcije')) . '">Radne akcije</a> (psa koji nije bio – odbiti).'
        : 'upisuju se automatski dan nakon lova za svakog prijavljenog psa (radna akcija „Pas na lovu“, na čekanju odobrenja).' ?>
</div></div>
<?php endif; ?>
<?php if ($v['akcija'] && ima(P_AKCIJE_ODOBRI) && substr($d['Pocetak'], 0, 10) <= danas()): ?>
<div class="card mb-3 border-success"><div class="card-body">
    <b>Radna akcija je održana?</b>
    <?php if ($d['AkcijeUpisane']): ?><span class="badge bg-success ms-1">upisano <?= e(datum($d['AkcijeUpisane'])) ?></span><?php endif; ?>
    <p class="small text-muted mb-2">Upišite akciju i bodove svima koji su bili – označeni su oni koji su potvrdili dolazak, popis možete promijeniti.</p>
    <a class="btn btn-success btn-sm" href="<?= e(url('akcije/nova', ['dogadjaj' => $id])) ?>">Upiši radnu akciju (<?= count($dolaze) ?>)</a>
</div></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('kalendar/ics', ['id' => $id])) ?>">📅 Dodaj u moj kalendar</a>
    <?= gumbi_dijeljenja($tekst) ?>
</div>
<?php stranica($d['Naslov'], ob_get_clean());
