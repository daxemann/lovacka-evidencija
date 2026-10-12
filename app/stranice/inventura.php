<?php
/** Inventura: mape i stavke (treba / stvarno), manjak i višak, PDF lista za popis. */
if (!ima(P_INVENTURA) && !ima(P_INVENTURA_UREDI)) {
    zabranjeno();
}
$uredi = ima(P_INVENTURA_UREDI);
$k = korisnik();
$id = (int) ($_GET['mapa'] ?? 0);
$mapa = $id ? inv_mapa($id) : null;
$sekcijeOpseg = moje_sekcije();

if (je_post()) {
    if (!$uredi) {
        zabranjeno();
    }
    $radnja = (string) ($_POST['radnja'] ?? '');

    if ($radnja === 'nova_mapa') {
        $naziv = mb_substr(ul_str('naziv'), 0, 100);
        $sek = ul_int('sekcija');
        if ($sek !== null && !moze_sekciju($sek)) {
            zabranjeno();
        }
        if ($sek === null && !$k['SveSekcije']) {
            // admin sekcije: mapa uvijek pripada njegovoj sekciji
            $sek = $k['Sekcije'][0] ?? null;
        }
        if ($naziv === '') {
            poruka('Upišite naziv mape (npr. „Lovački dom“, „Hranilišta“).', 'danger');
            preusmjeri('inventura');
        }
        $nid = umetni('InvMape', ['Naziv' => $naziv, 'SekcijaId' => $sek, 'Kreirano' => sada(),
            'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),-1)+1 FROM InvMape')]);
        dnevnik('Inventura – nova mapa', 'InvMapa', $nid, $naziv);
        preusmjeri('inventura', ['mapa' => $nid]);
    }

    if ($radnja === 'poredak') {
        $sve = array_map('intval', array_column(inv_mape(), 'Id'));
        $i = array_search((int) ($_POST['id'] ?? 0), $sve, true);
        $j = $i === false ? false : ($_POST['smjer'] === 'gore' ? $i - 1 : $i + 1);
        if ($i !== false && isset($sve[$j])) {
            [$sve[$i], $sve[$j]] = [$sve[$j], $sve[$i]];
        }
        foreach ($sve as $r => $mid) {
            q('UPDATE InvMape SET Redoslijed=? WHERE Id=?', [$r, $mid]);
        }
        preusmjeri('inventura');
    }

    if (!$mapa) {
        preusmjeri('inventura');
    }

    if ($radnja === 'obrisi_mapu') {
        q('DELETE FROM InvMape WHERE Id=?', [$id]);
        dnevnik('Inventura – mapa obrisana', 'InvMapa', $id, $mapa['Naziv']);
        poruka('Mapa „' . e($mapa['Naziv']) . '“ je obrisana.');
        preusmjeri('inventura');
    }

    if ($radnja === 'nova_inventura') {
        q('UPDATE InvStavke SET Stvarno=NULL, Napomena=NULL WHERE MapaId=?', [$id]);
        azuriraj('InvMape', $id, ['Azurirano' => sada(), 'AzuriraoIme' => $k['Naziv']]);
        dnevnik('Inventura – nova inventura (stvarno obrisano)', 'InvMapa', $id, $mapa['Naziv']);
        poruka('Stupac „Stvarno“ je ispražnjen – možete ispisati listu i krenuti s popisom.');
        preusmjeri('inventura', ['mapa' => $id]);
    }

    if ($radnja === 'preuzmi') {
        q('UPDATE InvStavke SET Treba=Stvarno WHERE MapaId=? AND Stvarno IS NOT NULL', [$id]);
        azuriraj('InvMape', $id, ['Azurirano' => sada(), 'AzuriraoIme' => $k['Naziv']]);
        dnevnik('Inventura – stvarno preuzeto kao treba', 'InvMapa', $id, $mapa['Naziv']);
        poruka('Popisano stanje je preuzeto kao novo stanje „Treba“.');
        preusmjeri('inventura', ['mapa' => $id]);
    }

    // spremanje cijele tablice
    transakcija(function () use ($id, $mapa, $k) {
        $naziv = mb_substr(ul_str('naziv_mape'), 0, 100);
        if ($naziv !== '' && $naziv !== $mapa['Naziv']) {
            azuriraj('InvMape', $id, ['Naziv' => $naziv]);
        }
        foreach ((array) ($_POST['obrisi'] ?? []) as $sid) {
            q('DELETE FROM InvStavke WHERE Id=? AND MapaId=?', [(int) $sid, $id]);
        }
        $r = 0;
        foreach ((array) ($_POST['s'] ?? []) as $kljucRetka => $s) {
            $n = mb_substr(trim((string) ($s['naziv'] ?? '')), 0, 150);
            $polja = [
                'Naziv' => $n,
                'Jedinica' => mb_substr(trim((string) ($s['jed'] ?? '')), 0, 20) ?: null,
                'Treba' => u_broj($s['treba'] ?? ''),
                'Stvarno' => u_broj($s['stvarno'] ?? ''),
                'Napomena' => mb_substr(trim((string) ($s['nap'] ?? '')), 0, 200) ?: null,
                'Redoslijed' => $r++,
            ];
            if (str_starts_with((string) $kljucRetka, 'n')) {
                if ($n !== '') {
                    umetni('InvStavke', $polja + ['MapaId' => $id]);
                }
            } elseif ($n !== '') {
                q('UPDATE InvStavke SET Naziv=?, Jedinica=?, Treba=?, Stvarno=?, Napomena=?, Redoslijed=? WHERE Id=? AND MapaId=?',
                    [...array_values($polja), (int) $kljucRetka, $id]);
            }
        }
        azuriraj('InvMape', $id, ['Azurirano' => sada(), 'AzuriraoIme' => $k['Naziv']]);
    });
    dnevnik('Inventura – spremljeno', 'InvMapa', $id, $mapa['Naziv']);
    poruka('Spremljeno.');
    preusmjeri('inventura', ['mapa' => $id]);
}

ob_start();

// ---------- jedna mapa – samo pregled (pravo „Inventura – pregled“) ----------
if ($mapa && !$uredi):
    $stavke = inv_stavke($id); ?>
<p class="mb-2"><a href="<?= e(url('inventura')) ?>">← Sve mape</a></p>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">📁 <?= e($mapa['Naziv']) ?></h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf', ['mapa' => $id])) ?>">🖨️ PDF za popis</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf', ['mapa' => $id, 'rezultat' => 1])) ?>">📄 PDF s rezultatom</a>
</div>
<p class="text-muted small mb-3"><?= $mapa['Sekcija'] ? 'Sekcija: ' . e($mapa['Sekcija']) . ' · ' : 'Cijela udruga · ' ?>
    <?= $mapa['Azurirano'] ? 'zadnja izmjena ' . e(datum_vrijeme($mapa['Azurirano'])) . ($mapa['AzuriraoIme'] ? ' (' . e($mapa['AzuriraoIme']) . ')' : '') : 'još nema izmjena' ?>
    · <span class="badge bg-secondary">samo pregled</span></p>
<?php if (!$stavke): ?>
    <div class="alert alert-info">Mapa još nema stavki.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-sm table-striped align-middle inv-tablica">
    <thead><tr><th>Naziv stvari</th><th class="d-none d-md-table-cell">Jed.</th><th class="text-end">Treba</th><th class="text-end">Stvarno</th><th class="text-end">Razlika</th><th class="d-none d-md-table-cell">Napomena</th></tr></thead>
    <tbody>
    <?php foreach ($stavke as $s): ?>
        <tr><td><?= e($s['Naziv']) ?></td><td class="d-none d-md-table-cell"><?= e($s['Jedinica']) ?></td>
            <td class="text-end"><?= e(broj($s['Treba'], 2)) ?></td><td class="text-end"><?= e(broj($s['Stvarno'], 2)) ?></td>
            <td class="text-end text-nowrap"><?= inv_razlika_html($s) ?></td><td class="d-none d-md-table-cell small"><?= e($s['Napomena']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<style>@media (max-width: 575px) { .inv-tablica .inv-rijec { display: none; } }</style>
<?php
    stranica('Inventura – ' . $mapa['Naziv'], ob_get_clean());
endif;

// ---------- jedna mapa – uređivanje ----------
if ($mapa):
    $stavke = inv_stavke($id); ?>
<p class="mb-2"><a href="<?= e(url('inventura')) ?>">← Sve mape</a></p>
<form method="post" action="<?= e(url('inventura', ['mapa' => $id])) ?>" id="inv-obrazac"><?= csrf() ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <input name="naziv_mape" class="form-control form-control-lg fw-semibold flex-grow-1" style="max-width:520px" maxlength="100" value="<?= e($mapa['Naziv']) ?>" aria-label="Naziv mape">
    <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf', ['mapa' => $id])) ?>">🖨️ PDF za popis</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf', ['mapa' => $id, 'rezultat' => 1])) ?>">📄 PDF s rezultatom</a>
</div>
<p class="text-muted small mb-3"><?= $mapa['Sekcija'] ? 'Sekcija: ' . e($mapa['Sekcija']) . ' · ' : 'Cijela udruga · ' ?>
    <?= $mapa['Azurirano'] ? 'zadnja izmjena ' . e(datum_vrijeme($mapa['Azurirano'])) . ($mapa['AzuriraoIme'] ? ' (' . e($mapa['AzuriraoIme']) . ')' : '') : 'još nema izmjena' ?></p>

<div class="table-responsive">
<table class="table table-sm align-middle inv-tablica">
    <thead><tr>
        <th>Naziv stvari</th><th style="width:90px" class="d-none d-md-table-cell">Jed.</th><th style="width:100px" class="text-end">Treba</th><th style="width:100px" class="text-end">Stvarno</th>
        <th style="width:140px" class="text-end">Razlika</th><th class="d-none d-md-table-cell">Napomena</th><th style="width:34px"></th>
    </tr></thead>
    <tbody id="inv-retci">
    <?php foreach ($stavke as $s): ?>
        <tr>
            <td><input name="s[<?= (int) $s['Id'] ?>][naziv]" class="form-control form-control-sm" maxlength="150" value="<?= e($s['Naziv']) ?>"></td>
            <td class="d-none d-md-table-cell"><input name="s[<?= (int) $s['Id'] ?>][jed]" class="form-control form-control-sm" maxlength="20" value="<?= e($s['Jedinica']) ?>" list="inv-jedinice"></td>
            <td><input name="s[<?= (int) $s['Id'] ?>][treba]" class="form-control form-control-sm text-end" inputmode="decimal" value="<?= e(broj($s['Treba'], 2)) ?>" data-treba></td>
            <td><input name="s[<?= (int) $s['Id'] ?>][stvarno]" class="form-control form-control-sm text-end" inputmode="decimal" value="<?= e(broj($s['Stvarno'], 2)) ?>" data-stvarno></td>
            <td class="text-end text-nowrap" data-razlika><?= inv_razlika_html($s) ?></td>
            <td class="d-none d-md-table-cell"><input name="s[<?= (int) $s['Id'] ?>][nap]" class="form-control form-control-sm" maxlength="200" value="<?= e($s['Napomena']) ?>"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" data-ukloni="<?= (int) $s['Id'] ?>" title="Ukloni stavku" aria-label="Ukloni stavku">−</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<datalist id="inv-jedinice"><?php foreach (['kom', 'kg', 'l', 'm', 'vreća', 'kutija', 'paket', 'par', 'set'] as $j): ?><option value="<?= e($j) ?>"><?php endforeach; ?></datalist>
<div id="inv-obrisani"></div>
<div class="d-flex flex-wrap gap-2 inv-gumbi">
    <button type="button" class="btn btn-outline-success" id="inv-dodaj">+ Dodaj stavku</button>
    <button type="submit" class="btn btn-primary ms-auto" name="radnja" value="spremi">💾 Spremi</button>
</div>
<p class="small text-muted mt-2 mb-4"><b>Treba</b> = koliko bi trebalo biti · <b>Stvarno</b> = koliko je popisano · Razlika: <span class="text-danger fw-semibold">crveno manjak</span>, <span class="text-success fw-semibold">zeleno višak / u redu</span>, — još nije popisano.</p>

<details class="card mb-3"><summary class="card-header">Nova inventura i ostalo</summary><div class="card-body d-flex flex-wrap gap-2">
    <button class="btn btn-outline-secondary btn-sm" name="radnja" value="nova_inventura" formnovalidate data-potvrda="Isprazniti stupac „Stvarno“ i napomene u ovoj mapi za novi popis?">🔄 Nova inventura (isprazni „Stvarno“)</button>
    <button class="btn btn-outline-secondary btn-sm" name="radnja" value="preuzmi" formnovalidate data-potvrda="Popisano stanje postaje novo stanje „Treba“. Nastaviti?">⤴ Preuzmi „Stvarno“ kao „Treba“</button>
    <button class="btn btn-outline-danger btn-sm ms-auto" name="radnja" value="obrisi_mapu" formnovalidate data-potvrda="Obrisati mapu „<?= e($mapa['Naziv']) ?>“ i sve njezine stavke (<?= count($stavke) ?>)?">Obriši mapu</button>
</div></details>
</form>

<template id="inv-predlozak"><tr>
    <td><input name="s[nX][naziv]" class="form-control form-control-sm" maxlength="150" placeholder="npr. Kukuruz, Sol za solište, Motorna pila"></td>
    <td class="d-none d-md-table-cell"><input name="s[nX][jed]" class="form-control form-control-sm" maxlength="20" list="inv-jedinice" placeholder="kom"></td>
    <td><input name="s[nX][treba]" class="form-control form-control-sm text-end" inputmode="decimal" data-treba></td>
    <td><input name="s[nX][stvarno]" class="form-control form-control-sm text-end" inputmode="decimal" data-stvarno></td>
    <td class="text-end text-nowrap" data-razlika><span class="text-body-tertiary">—</span></td>
    <td class="d-none d-md-table-cell"><input name="s[nX][nap]" class="form-control form-control-sm" maxlength="200"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" data-ukloni="" title="Ukloni stavku" aria-label="Ukloni stavku">−</button></td>
</tr></template>
<style>.inv-tablica td { padding-top: .25rem; padding-bottom: .25rem; } .inv-tablica input { min-width: 58px; } .inv-tablica td:first-child input { min-width: 120px; }
@media (max-width: 575px) { .inv-tablica .inv-rijec { display: none; } .inv-tablica th, .inv-tablica td { padding-left: .15rem; padding-right: .15rem; } }
.inv-gumbi { position: sticky; bottom: 0; background: var(--bs-body-bg); padding: .5rem 0; z-index: 5; }</style>
<script>
(function () {
    var tbody = document.getElementById('inv-retci'), n = 0, promjena = false;
    function broj(s) { s = String(s || '').replace(/\s/g, ''); if (s === '') return null; if (s.indexOf(',') >= 0 && s.indexOf('.') >= 0) s = s.replace(/\./g, ''); s = s.replace(',', '.'); var f = parseFloat(s); return isNaN(f) ? null : f; }
    function fmt(f) { return (Math.round(f * 100) / 100).toString().replace('.', ','); }
    function razlika(tr) {
        var t = broj(tr.querySelector('[data-treba]').value) || 0, s = broj(tr.querySelector('[data-stvarno]').value), c = tr.querySelector('[data-razlika]');
        if (s === null) { c.innerHTML = '<span class="text-body-tertiary">—</span>'; return; }
        var r = Math.round((s - t) * 100) / 100;
        c.innerHTML = r === 0 ? '<span class="badge bg-success-subtle text-success-emphasis">✓ 0</span>'
            : r < 0 ? '<span class="badge bg-danger">−' + fmt(-r) + '<span class="inv-rijec"> manjak</span></span>' : '<span class="badge bg-success">+' + fmt(r) + '<span class="inv-rijec"> višak</span></span>';
    }
    function dodaj() {
        var html = document.getElementById('inv-predlozak').innerHTML.replace(/nX/g, 'n' + (n++));
        tbody.insertAdjacentHTML('beforeend', html);
        var tr = tbody.lastElementChild; tr.querySelector('input').focus();
    }
    tbody.addEventListener('input', function (e) { promjena = true; var tr = e.target.closest('tr'); if (tr && (e.target.hasAttribute('data-treba') || e.target.hasAttribute('data-stvarno'))) razlika(tr); });
    tbody.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ukloni]'); if (!b) return;
        var tr = b.closest('tr'), naziv = tr.querySelector('input').value;
        if (naziv && !confirm('Ukloniti stavku „' + naziv + '“? (vrijedi tek nakon „Spremi“)')) return;
        if (b.getAttribute('data-ukloni')) document.getElementById('inv-obrisani').insertAdjacentHTML('beforeend', '<input type="hidden" name="obrisi[]" value="' + b.getAttribute('data-ukloni') + '">');
        tr.remove(); promjena = true;
    });
    // Enter u zadnjem retku = novi redak (brzi unos)
    tbody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); var tr = e.target.closest('tr'); if (tr === tbody.lastElementChild) dodaj(); else { var i = Array.prototype.indexOf.call(tr.querySelectorAll('input'), e.target); var sl = tr.nextElementSibling.querySelectorAll('input')[i]; if (sl) sl.focus(); } }
    });
    document.getElementById('inv-dodaj').addEventListener('click', dodaj);
    document.getElementById('inv-obrazac').addEventListener('submit', function () { promjena = false; });
    window.addEventListener('beforeunload', function (e) { if (promjena) { e.preventDefault(); e.returnValue = ''; } });
    if (!tbody.children.length) dodaj();
})();
</script>
<?php
    stranica('Inventura – ' . $mapa['Naziv'], ob_get_clean());
endif;

// ---------- popis mapa ----------
$mape = inv_mape(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">📦 Inventura</h1>
    <?php if ($mape): ?>
        <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf')) ?>">🖨️ PDF za popis (sve mape)</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('izvoz/inventura-pdf', ['rezultat' => 1])) ?>">📄 Rezultat (sve)</a>
    <?php endif; ?>
</div>

<?php if ($uredi): ?>
<form method="post" action="<?= e(url('inventura')) ?>" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end"><?= csrf() ?>
    <div class="flex-grow-1" style="min-width:200px"><label class="form-label small mb-1">Nova mapa</label>
        <input name="naziv" class="form-control" maxlength="100" required placeholder="npr. Lovački dom, Hranilišta, Oprema, Hladnjača"></div>
    <?php if (count($sekcijeOpseg) > 0): ?>
    <div><label class="form-label small mb-1">Pripada</label>
        <select name="sekcija" class="form-select">
            <?php if ($k['SveSekcije']): ?><option value="">Cijela udruga</option><?php endif; ?>
            <?php foreach ($sekcijeOpseg as $s): ?><option value="<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></option><?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <button class="btn btn-success" name="radnja" value="nova_mapa">+ Dodaj mapu</button>
</div></form>
<?php endif; ?>

<?php if (!$mape): ?>
    <div class="alert alert-info"><?= $uredi ? '' : 'Još nema mapa inventure.' ?><?php if ($uredi): ?>Još nema mapa. Dodajte prvu (npr. „Lovački dom“), zatim u njoj stavke s količinom koja treba biti. Lista za popis ispisuje se kao PDF s praznim stupcem „Stvarno“.<?php endif; ?></div>
<?php else: ?>
<div class="list-group mb-3">
    <?php foreach ($mape as $n => $m): ?>
    <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
        <a class="flex-grow-1 text-decoration-none" href="<?= e(url('inventura', ['mapa' => $m['Id']])) ?>">
            <span class="fw-semibold">📁 <?= e($m['Naziv']) ?></span>
            <span class="small text-muted ms-1"><?= $m['Sekcija'] ? e($m['Sekcija']) : 'cijela udruga' ?> · <?= (int) $m['Stavki'] ?> stavki<?= $m['Azurirano'] ? ' · ' . e(datum($m['Azurirano'])) : '' ?></span>
        </a>
        <span class="text-nowrap">
            <?php if ($m['Manjak']): ?><span class="badge bg-danger" title="stavke s manjkom">− <?= (int) $m['Manjak'] ?> manjak</span><?php endif; ?>
            <?php if ($m['Visak']): ?><span class="badge bg-success" title="stavke s viškom">+ <?= (int) $m['Visak'] ?> višak</span><?php endif; ?>
            <?php if ($m['Stavki'] && !$m['Manjak'] && !$m['Visak'] && !$m['Nepopisano']): ?><span class="badge bg-success-subtle text-success-emphasis">✓ sve u redu</span><?php endif; ?>
            <?php if ($m['Nepopisano'] && $m['Nepopisano'] < $m['Stavki']): ?><span class="badge bg-secondary-subtle text-secondary-emphasis" title="još nije popisano"><?= (int) $m['Nepopisano'] ?> nepopisano</span><?php endif; ?>
        </span>
        <?php if ($uredi): ?><form method="post" action="<?= e(url('inventura')) ?>" class="text-nowrap"><?= csrf() ?><input type="hidden" name="id" value="<?= (int) $m['Id'] ?>">
            <?php if ($n > 0): ?><button class="btn btn-sm btn-link p-0" name="radnja" value="poredak" onclick="this.form.smjer.value='gore'" title="Gore">▲</button><?php endif; ?>
            <?php if ($n < count($mape) - 1): ?><button class="btn btn-sm btn-link p-0" name="radnja" value="poredak" onclick="this.form.smjer.value='dolje'" title="Dolje">▼</button><?php endif; ?>
            <input type="hidden" name="smjer" value="">
        </form><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif;
stranica('Inventura', ob_get_clean());
