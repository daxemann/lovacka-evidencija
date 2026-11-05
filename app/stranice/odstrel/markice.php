<?php
/**
 * 🏷️ Zaliha evidencijskih markica po evidenciji (sekcija / spojene sekcije / udruga) i lovnoj godini:
 * upis originalnih brojeva (izdatnica, raspon od–do), zaduženje lovcu, povrat Ministarstvu, PDF (PPD-3).
 * Pregled: Odstrel – pregled / upis; izmjene: Odstrel – markice. Iskorištena = povezana s upisanim odstrelom (automatski).
 */
$k = korisnik();
$sekcije = odstrel_sekcije();
$uredive = odstrel_markice_uredive();
$vidi = $sekcije + $uredive;
if (!odstrel_markice_ukljucene() || !$vidi) {
    zabranjeno();
}
$godine = odstrel_godine();
foreach (array_map('intval', array_column(redovi('SELECT DISTINCT LovnaGodina FROM OdstrelMarkice'), 'LovnaGodina')) as $x) {
    $godine[] = $x;
}
$godine = array_values(array_unique($godine));
rsort($godine);
$g = (int) ul('g', odstrel_lovna_godina());
if ($g < 2000 || $g > 2100) {
    $g = odstrel_lovna_godina();
}
if (!in_array($g, $godine, true)) {
    $godine[] = $g;
    rsort($godine);
}
$sid = (int) ul('s', '');
if (!array_key_exists($sid, $vidi)) {
    $sid = (int) array_key_first($vidi);
}
$moze = array_key_exists($sid, $uredive);
$gSql = odstrel_grupa_sql($sid);
$povratak = fn() => preusmjeri('odstrel/markice', array_filter(['g' => $g, 's' => $sid, 'vrsta' => (string) ($_POST['f_vrsta'] ?? ''), 'st' => (string) ($_POST['f_st'] ?? '')], fn($x) => $x !== ''));

if (je_post()) {
    if (!$moze) {
        zabranjeno();
    }
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'dodaj') {
        $vrsta = mb_substr(ul_str('vrsta'), 0, 60);
        $brojevi = odstrel_markice_raspon(mb_substr(ul_str('od'), 0, 40), mb_substr(ul_str('do'), 0, 40));
        if ($vrsta === '') {
            poruka('Odaberite vrstu divljači.', 'danger');
        } elseif (is_string($brojevi)) {
            poruka(e($brojevi), 'danger');
        } else {
            $izd = mb_substr(ul_str('izdatnica'), 0, 40) ?: null;
            $novo = 0;
            $postoji = [];
            transakcija(function () use ($brojevi, $sid, $g, $vrsta, $izd, $k, $gSql, &$novo, &$postoji) {
                foreach ($brojevi as $b) {
                    if (vrijednost("SELECT 1 FROM OdstrelMarkice WHERE $gSql AND LovnaGodina=? AND Broj=?", [$g, $b])) {
                        $postoji[] = $b;
                        continue;
                    }
                    umetni('OdstrelMarkice', ['Grupa' => $sid ?: null, 'LovnaGodina' => $g, 'Vrsta' => $vrsta, 'Broj' => $b, 'Izdatnica' => $izd,
                        'Status' => MARKICA_SLOBODNA, 'Upisao' => $k['Naziv'], 'Kreirano' => sada()]);
                    $novo++;
                }
            });
            // već upisan odstrel s tim brojevima → odmah iskorištena
            foreach (redovi('SELECT * FROM Odstrel WHERE LovnaGodina=? AND ' . odstrel_sek_sql([$sid => true]) . ' AND Markica IS NOT NULL', [$g]) as $o) {
                if (in_array($o['Markica'], $brojevi, true)) {
                    odstrel_markica_povezi((int) $o['Id'], $o['Markica'], $o);
                }
            }
            dnevnik('Markice upisane u zalihu', null, null, "$vrsta · $novo kom · " . odstrel_lg_naziv($g) . ' · ' . $vidi[$sid]);
            poruka("Upisano u zalihu: <b>$novo</b> markica (" . e($vrsta) . ').' . ($postoji ? ' Već postoje (preskočeno): ' . e(implode(', ', array_slice($postoji, 0, 10))) . (count($postoji) > 10 ? ' …' : '') : ''), $postoji ? 'warning' : 'success');
        }
        $povratak();
    }
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['odabrani'] ?? []))));
    if (!$ids) {
        poruka('Niste odabrali nijednu markicu.', 'warning');
        $povratak();
    }
    $in = implode(',', $ids);
    $uv = "Id IN ($in) AND $gSql AND LovnaGodina=" . $g;
    $n = 0;
    if ($radnja === 'zaduzi') {
        $cid = (int) ($_POST['clan'] ?? 0);
        $ime = $cid ? puno_ime(clan($cid)) : mb_substr(ul_str('zaduzen_ime'), 0, 80);
        if ($ime === '') {
            poruka('Odaberite člana ili upišite ime.', 'danger');
            $povratak();
        }
        $n = q("UPDATE OdstrelMarkice SET ZaduzenClanId=?, ZaduzenIme=?, ZaduzenDatum=? WHERE $uv AND Status=" . MARKICA_SLOBODNA, [$cid ?: null, $ime, danas()])->rowCount();
        poruka("Zaduženo: <b>$n</b> markica → " . e($ime) . '.');
    } elseif ($radnja === 'razduzi') {
        $n = q("UPDATE OdstrelMarkice SET ZaduzenClanId=NULL, ZaduzenIme=NULL, ZaduzenDatum=NULL WHERE $uv AND Status=" . MARKICA_SLOBODNA)->rowCount();
        poruka("Razduženo (vraćeno u zalihu udruge): <b>$n</b> markica.");
    } elseif ($radnja === 'vrati') {
        $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['datum'] ?? '')) ? $_POST['datum'] : danas();
        $n = q("UPDATE OdstrelMarkice SET Status=?, VracenoDatum=? WHERE $uv AND Status=" . MARKICA_SLOBODNA, [MARKICA_VRACENA, $d])->rowCount();
        poruka("Označeno kao vraćeno Ministarstvu: <b>$n</b> markica (" . e(datum($d)) . ').');
    } elseif ($radnja === 'ponisti') {
        $n = q("UPDATE OdstrelMarkice SET Status=?, VracenoDatum=NULL WHERE $uv AND Status=" . MARKICA_VRACENA, [MARKICA_SLOBODNA])->rowCount();
        poruka("Povrat poništen: <b>$n</b> markica opet slobodno.");
    } elseif ($radnja === 'obrisi') {
        $n = q("DELETE FROM OdstrelMarkice WHERE $uv AND Status=" . MARKICA_SLOBODNA . ' AND OdstrelId IS NULL')->rowCount();
        poruka("Obrisano iz zalihe: <b>$n</b> markica (samo slobodne; iskorištene i vraćene ostaju).");
    }
    dnevnik('Markice: ' . $radnja, null, null, "$n kom · " . odstrel_lg_naziv($g) . ' · ' . $vidi[$sid]);
    $povratak();
}

$vrstaF = mb_substr(trim((string) ul('vrsta', '')), 0, 60);
$stF = (string) ul('st', '');
$trazi = mb_substr(trim((string) ul('q', '')), 0, 40);
$w = [str_replace('Grupa', 'm.Grupa', $gSql), 'm.LovnaGodina=?'];
$par = [$g];
if ($vrstaF !== '') {
    $w[] = 'm.Vrsta=?';
    $par[] = $vrstaF;
}
if ($stF === 'z') {
    $w[] = 'm.Status=0 AND m.ZaduzenIme IS NOT NULL';
} elseif ($stF !== '' && isset(MARKICA_STATUSI[(int) $stF])) {
    $w[] = 'm.Status=' . (int) $stF;
}
if ($trazi !== '') {
    $w[] = '(m.Broj LIKE ? OR m.ZaduzenIme LIKE ? OR m.Izdatnica LIKE ?)';
    array_push($par, "%$trazi%", "%$trazi%", "%$trazi%");
}
$lista = redovi('SELECT m.*, o.Datum AS ODatum, o.Lovac AS OLovac FROM OdstrelMarkice m LEFT JOIN Odstrel o ON o.Id=m.OdstrelId WHERE ' . implode(' AND ', $w)
    . ' ORDER BY m.Vrsta, m.Broj LIMIT 3000', $par);
$sazetak = redovi("SELECT Vrsta, COUNT(*) AS N, SUM(Status=0) AS Slob, SUM(Status=0 AND ZaduzenIme IS NOT NULL) AS Zad, SUM(Status=1) AS Isk, SUM(Status=2) AS Vra
    FROM OdstrelMarkice WHERE $gSql AND LovnaGodina=? GROUP BY Vrsta ORDER BY Vrsta", [$g]);
$vrsteMark = array_values(array_unique(array_merge(odstrel_vrste_markica(), array_column($sazetak, 'Vrsta'))));
$clanovi = [];
foreach (odstrel_clanice($sid) as $cs) {
    foreach (odstrel_clanovi($cs ?: null) as $c) {
        $clanovi[(int) $c['Id']] = trim($c['Prezime'] . ' ' . $c['Ime']);
    }
}
asort($clanovi);
$md = date('m-d');
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('odstrel', ['g' => $g, 's' => $sid])) ?>">← Odstrel</a>
    <h1 class="h4 mb-0 me-auto">🏷️ Markice – zaliha</h1>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/markice-pdf', ['g' => $g, 's' => $sid])) ?>">📄 PDF (PPD-3)</a>
</div>
<form class="row g-2 align-items-end mb-3" method="get"><input type="hidden" name="p" value="odstrel/markice">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Lovna godina</label>
        <select name="g" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach (array_unique(array_merge([odstrel_lovna_godina() + 1], $godine)) as $x): ?><option value="<?= $x ?>"<?= sel($x, $g) ?>><?= e(odstrel_lg_naziv($x)) ?></option><?php endforeach; ?>
        </select></div>
    <?php if (count($vidi) > 1): ?>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Evidencija</label>
        <select name="s" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($vidi as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel($id, $sid) ?>><?= e($n) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Vrsta</label>
        <select name="vrsta" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">sve</option>
            <?php foreach (array_column($sazetak, 'Vrsta') as $x): ?><option<?= sel($x, $vrstaF) ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Stanje</label>
        <select name="st" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">sve</option>
            <?php foreach (MARKICA_STATUSI as $kl => $n): ?><option value="<?= $kl ?>"<?= sel((string) $kl, $stF) ?>><?= e($n) ?></option><?php endforeach; ?>
            <option value="z"<?= sel('z', $stF) ?>>zadužena lovcu</option></select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Broj / lovac</label><input name="q" class="form-control form-control-sm" value="<?= e($trazi) ?>"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Prikaži</button></div>
</form>
<?php if ($md >= '04-15' && $md <= '05-31' && $g === odstrel_lovna_godina() - 1): ?>
<div class="alert alert-info py-2">Neiskorištene markice za <?= e(odstrel_lg_naziv($g)) ?> vraćaju se Ministarstvu <b>do 31. svibnja</b> uz obrazac PPD-3. Odaberite ih (stanje: slobodna) → <i>Vraćeno Ministarstvu</i>.</div>
<?php endif; ?>

<?php if ($sazetak): ?>
<div class="table-responsive"><table class="table table-sm align-middle" style="max-width:720px">
    <thead><tr><th>Vrsta</th><th class="text-end">Zaliha</th><th class="text-end">Slobodne</th><th class="text-end">od toga zadužene</th><th class="text-end">Iskorištene</th><th class="text-end">Vraćene</th></tr></thead><tbody>
    <?php foreach ($sazetak as $r): ?>
        <tr><td><a href="<?= e(url('odstrel/markice', ['g' => $g, 's' => $sid, 'vrsta' => $r['Vrsta']])) ?>"><?= e($r['Vrsta']) ?></a></td><td class="text-end fw-semibold"><?= (int) $r['N'] ?></td>
            <td class="text-end"><?= (int) $r['Slob'] ?></td><td class="text-end text-muted"><?= (int) $r['Zad'] ?: '' ?></td><td class="text-end"><?= (int) $r['Isk'] ?></td><td class="text-end"><?= (int) $r['Vra'] ?: '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php else: ?>
<div class="alert alert-secondary">U zalihi za <?= e($vidi[$sid]) ?> · <?= e(odstrel_lg_naziv($g)) ?> još nema markica.<?= $moze ? ' Upišite ih ispod (originalni brojevi s izdatnice).' : '' ?></div>
<?php endif; ?>

<?php if ($moze): ?>
<details class="card mb-3"<?= $sazetak ? '' : ' open' ?>><summary class="card-header fw-semibold">➕ Upiši markice u zalihu</summary>
<form method="post" class="card-body row g-2 align-items-end"><?= csrf() ?><input type="hidden" name="radnja" value="dodaj">
    <div class="col-12 small text-muted">Originalni brojevi s izdatnice Ministarstva za <b><?= e($vidi[$sid]) ?></b> · <?= e(odstrel_lg_naziv($g)) ?>. Raspon od–do (npr. 000101 – 000150) ili samo „od“ za jednu markicu.</div>
    <div class="col-12 col-md-3"><label class="form-label small mb-0">Vrsta *</label>
        <input name="vrsta" class="form-control" required list="mk-vrste" autocomplete="off">
        <datalist id="mk-vrste"><?php foreach ($vrsteMark as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Broj izdatnice</label><input name="izdatnica" class="form-control" maxlength="40"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Od *</label><input name="od" class="form-control" required maxlength="40" placeholder="npr. HR-SR-000101"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">Do</label><input name="do" class="form-control" maxlength="40" placeholder="000150"></div>
    <div class="col-6 col-md-2"><button class="btn btn-success w-100">➕ Upiši</button></div>
</form></details>
<?php endif; ?>

<?php if ($lista): ?>
<form method="post"><?= csrf() ?><input type="hidden" name="f_vrsta" value="<?= e($vrstaF) ?>"><input type="hidden" name="f_st" value="<?= e($stF) ?>">
<?php if ($moze): ?>
<div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap align-items-end gap-2">
    <span class="small text-muted me-1">Odabrane:</span>
    <div><select name="clan" class="form-select form-select-sm"><option value="0">— član za zaduženje —</option>
        <?php foreach ($clanovi as $id => $n): ?><option value="<?= $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
    <div><input name="zaduzen_ime" class="form-control form-control-sm" placeholder="ili ime (gost)" maxlength="80"></div>
    <button class="btn btn-sm btn-outline-primary" name="radnja" value="zaduzi">👤 Zaduži</button>
    <button class="btn btn-sm btn-outline-secondary" name="radnja" value="razduzi">↩ Razduži</button>
    <span class="vr mx-1"></span>
    <div><input type="date" name="datum" class="form-control form-control-sm" value="<?= e(danas()) ?>"></div>
    <button class="btn btn-sm btn-outline-warning" name="radnja" value="vrati" data-potvrda="Označiti odabrane slobodne markice kao vraćene Ministarstvu?">📤 Vraćeno Ministarstvu</button>
    <button class="btn btn-sm btn-outline-secondary" name="radnja" value="ponisti">Poništi povrat</button>
    <button class="btn btn-sm btn-outline-danger ms-auto" name="radnja" value="obrisi" data-potvrda="Obrisati odabrane slobodne markice iz zalihe (npr. pogrešno upisane)?">🗑 Obriši</button>
</div></div>
<?php endif; ?>
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><?php if ($moze): ?><th style="width:2rem"><input type="checkbox" class="form-check-input" data-sve="odabrani[]"></th><?php endif; ?>
        <th>Broj</th><th>Vrsta</th><th class="d-none d-md-table-cell">Izdatnica</th><th>Stanje</th><th>Zadužena / odstrel</th></tr></thead><tbody>
    <?php foreach ($lista as $m): $st = (int) $m['Status']; ?>
        <tr><?php if ($moze): ?><td><input type="checkbox" class="form-check-input" name="odabrani[]" value="<?= (int) $m['Id'] ?>"></td><?php endif; ?>
            <td class="fw-semibold"><?= e($m['Broj']) ?></td><td><?= e($m['Vrsta']) ?></td><td class="small d-none d-md-table-cell"><?= e((string) $m['Izdatnica']) ?></td>
            <td><span class="badge text-bg-<?= [0 => 'success', 1 => 'secondary', 2 => 'warning'][$st] ?? 'light' ?>"><?= e(MARKICA_STATUSI[$st] ?? '?') ?></span>
                <?= $st === MARKICA_VRACENA && $m['VracenoDatum'] ? '<div class="small text-muted">' . e(datum($m['VracenoDatum'])) . '</div>' : '' ?></td>
            <td class="small"><?php if ($st === MARKICA_ISKORISTENA && $m['OdstrelId']): ?>
                    <a href="<?= e(url('odstrel/uredi', ['id' => $m['OdstrelId']])) ?>">🦌 <?= e(datum((string) $m['ODatum'])) ?> · <?= e((string) $m['OLovac']) ?></a>
                <?php elseif ($m['ZaduzenIme']): ?>👤 <?= e($m['ZaduzenIme']) ?><span class="text-muted"> od <?= e(datum((string) $m['ZaduzenDatum'])) ?></span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</form>
<?php endif; ?>
<p class="small text-muted mt-3 border-top pt-2">Markica postaje <b>iskorištena</b> sama kad se upiše odstrel s tim brojem (brisanjem odstrela opet je slobodna).
    ℹ️ <?= e(ODSTREL_SLE_NAPOMENA) ?></p>
<?php stranica('Markice', ob_get_clean());
