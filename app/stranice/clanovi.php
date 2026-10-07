<?php
trazi(P_CLANOVI_CITAJ);
$k = korisnik();
$sekcija = (string) ($_GET['sekcija'] ?? '');
$moze = ima(P_CLANOVI_UREDI);

if (je_post() && $moze) {
    $ids = array_map('intval', (array) ($_POST['odabrani'] ?? []));
    $radnja = (string) ($_POST['radnja'] ?? '');
    if (!$ids) {
        poruka('Niste odabrali nijednog člana.', 'warning');
    } elseif ($radnja === 'premjesti') {
        $cilj = (int) ($_POST['cilj'] ?? -1);
        $ciljId = $cilj > 0 ? $cilj : null;
        if ($cilj < 0 || ($ciljId === null && !$k['SveSekcije']) || ($ciljId !== null && !moze_sekciju($ciljId))) {
            poruka('Odaberite sekciju.', 'warning');
        } else {
            $n = 0;
            foreach ($ids as $id) {
                $c = clan($id);
                if ($c && u_opsegu($c)) {
                    azuriraj('Clanovi', $id, ['SekcijaId' => $ciljId, 'Azurirano' => sada()]);
                    $n++;
                }
            }
            $naziv = $ciljId ? naziv_sekcije($ciljId) : 'Nije raspoređeno';
            dnevnik('Premještanje članova u sekciju', 'Sekcija', $ciljId, "$n → $naziv");
            poruka(e("$n članova premješteno u „{$naziv}“."));
        }
    } elseif ($radnja === 'obrisi' && ima(P_SUSTAV)) {
        [$n, $presk] = obrisi_pogresne($ids);
        poruka(e("Obrisano: $n.") . ($presk ? ' Nije obrisano jer imaju podatke (njih arhivirajte): ' . e(implode('; ', $presk)) : ''), $presk ? 'warning' : 'success');
    }
    preusmjeri('clanovi', ['sekcija' => $sekcija]);
}

$op = opseg_sql('c');
$svi = redovi("SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE $op ORDER BY c.Prezime COLLATE NOCASE, c.Ime COLLATE NOCASE");
// abecedno s hrvatskim slovima
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
usort($svi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
$aktivni = array_values(array_filter($svi, fn($c) => (int) $c['Status'] === CLAN_AKTIVAN));
$arhiva = array_values(array_filter($svi, fn($c) => (int) $c['Status'] === CLAN_ARHIVIRAN));
$sek = moje_sekcije();
$prikaz = match (true) {
    $sekcija === 'arhiva' => $arhiva,
    $sekcija === 'nije' => array_values(array_filter($aktivni, fn($c) => $c['SekcijaId'] === null)),
    ctype_digit($sekcija) => array_values(array_filter($aktivni, fn($c) => (string) $c['SekcijaId'] === $sekcija)),
    default => $aktivni,
};
$funkcije = funkcije_clanova();
$uloge = uloge_clanova();
$tab = function (string $vr, string $tekst, int $n, string $boja = 'bg-secondary') use ($sekcija) {
    return '<li class="nav-item"><a class="nav-link ' . ($sekcija === $vr ? 'active' : '') . '" href="' . e(url('clanovi', ['sekcija' => $vr])) . '">'
        . e($tekst) . ' <span class="badge ' . $boja . '">' . $n . '</span></a></li>';
};
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">Članovi</h1>
    <input id="trazi" class="form-control" style="max-width:260px" placeholder="Traži (ime, mjesto, telefon…)" autocomplete="off">
    <?php if ($moze): ?><a href="<?= e(url('clanovi/uredi')) ?>" class="btn btn-primary">+ Novi član</a><?php endif; ?>
</div>
<ul class="nav nav-tabs mb-3">
    <?= $tab('', 'Svi', count($aktivni)) ?>
    <?php foreach ($sek as $s): ?>
        <?= $tab((string) $s['Id'], $s['Naziv'], count(array_filter($aktivni, fn($c) => (int) $c['SekcijaId'] === (int) $s['Id']))) ?>
    <?php endforeach; ?>
    <?php if ($k['SveSekcije']): $n = count(array_filter($aktivni, fn($c) => $c['SekcijaId'] === null)); ?>
        <?= $tab('nije', 'Nije raspoređeno', $n, $n > 0 ? 'bg-warning text-dark' : 'bg-secondary') ?>
    <?php endif; ?>
    <?= $tab('arhiva', 'Arhiva', count($arhiva)) ?>
</ul>
<form method="post" action="<?= e(url('clanovi', ['sekcija' => $sekcija])) ?>" id="obrazac">
<?= csrf() ?>
<?php if ($moze): ?>
<div class="alert alert-secondary d-flex flex-wrap align-items-center gap-2" id="traka" style="display:none!important">
    <span>Odabrano: <b id="brojOdabranih">0</b></span>
    <span class="ms-3">Premjesti u sekciju:</span>
    <select name="cilj" class="form-select form-select-sm" style="max-width:200px">
        <option value="-1">— odaberi —</option>
        <?php foreach ($sek as $s): ?><option value="<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></option><?php endforeach; ?>
        <?php if ($k['SveSekcije']): ?><option value="0">Nije raspoređeno</option><?php endif; ?>
    </select>
    <button class="btn btn-sm btn-primary" name="radnja" value="premjesti">Premjesti</button>
    <button type="button" class="btn btn-sm btn-link" onclick="document.querySelectorAll('input[name=\'odabrani[]\']').forEach(c=>c.checked=false);document.dispatchEvent(new Event('ev-oznaceno'))">Poništi odabir</button>
    <?php if (ima(P_SUSTAV)): ?>
        <button class="btn btn-sm btn-outline-danger ms-auto" name="radnja" value="obrisi" data-potvrda="Trajno obrisati odabrane? Brišu se samo unosi bez radnih akcija, uplata i računa (ostale arhivirajte).">Obriši – nisu članovi (pogrešan unos)</button>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="table-responsive">
<table class="table table-hover align-middle" id="tablica">
    <thead><tr>
        <?php if ($moze): ?><th style="width:2rem"><input type="checkbox" class="form-check-input" data-sve="odabrani[]"></th><?php endif; ?>
        <th style="width:3rem"></th><th>Prezime i ime</th><th>Sekcija</th><th>Mobitel</th>
        <th class="d-none d-md-table-cell">E-mail</th><th class="d-none d-lg-table-cell">Funkcije / uloge</th><th class="d-none d-lg-table-cell">Iskaznica do</th>
    </tr></thead>
    <tbody>
    <?php foreach ($prikaz as $c):
        $href = e(url('clanovi/uredi', ['id' => $c['Id']]));
        $kljucTr = kljuc("{$c['Ime']} {$c['Prezime']} {$c['Nadimak']} {$c['Mjesto']} {$c['MobilniTelefon']} {$c['FiksniTelefon']} {$c['Email']} {$c['Oib']}");
        $istekla = $c['IskaznicaVrijediDo'] && substr($c['IskaznicaVrijediDo'], 0, 10) < date('Y-m-d'); ?>
        <tr class="klikabilno" data-trazi="<?= e($kljucTr) ?>" data-href="<?= $href ?>">
            <?php if ($moze): ?><td><input type="checkbox" class="form-check-input" name="odabrani[]" value="<?= (int) $c['Id'] ?>"></td><?php endif; ?>
            <td><?= avatar($c) ?></td>
            <td><b><?= e($c['Prezime']) ?></b> <?= e($c['Ime']) ?><?php if ($c['Nadimak']): ?> <span class="text-muted">(<?= e($c['Nadimak']) ?>)</span><?php endif; ?></td>
            <td><?= e($c['SekcijaNaziv'] ?? '—') ?></td>
            <td><?= e($c['MobilniTelefon']) ?></td>
            <td class="d-none d-md-table-cell"><?= e($c['Email']) ?></td>
            <td class="d-none d-lg-table-cell small"><?= e(implode(', ', $funkcije[$c['Id']] ?? [])) ?>
                <?php foreach ($uloge[$c['Id']] ?? [] as $u): ?><span class="badge bg-success ms-1"><?= e($u) ?></span><?php endforeach; ?></td>
            <td class="d-none d-lg-table-cell <?= $istekla ? 'text-danger' : '' ?>"><?= $c['IskaznicaVrijediDo'] ? e(date('d.m.Y', strtotime(substr($c['IskaznicaVrijediDo'], 0, 10)))) : '' ?></td>
        </tr>
    <?php endforeach; ?>
    <tr id="nema" style="<?= $prikaz ? 'display:none' : '' ?>"><td colspan="8" class="text-muted text-center py-4">Nema članova.</td></tr>
    </tbody>
</table>
</div>
</form>
<script>
(function () {
    var norm = function (s) { return s.toLowerCase().replace(/[čć]/g, 'c').replace(/š/g, 's').replace(/ž/g, 'z').replace(/đ/g, 'd').normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var redovi = Array.prototype.slice.call(document.querySelectorAll('#tablica tbody tr[data-trazi]'));
    var t = document.getElementById('trazi');
    var filtriraj = function () {
        var r = norm(t.value).split(/\s+/).filter(Boolean), n = 0;
        redovi.forEach(function (tr) { var ok = r.every(function (w) { return tr.dataset.trazi.indexOf(w) >= 0; }); tr.style.display = ok ? '' : 'none'; if (ok) n++; });
        document.getElementById('nema').style.display = n ? 'none' : '';
    };
    t.addEventListener('input', filtriraj);
    try { if (sessionStorage.clanoviTrazi) { t.value = sessionStorage.clanoviTrazi; filtriraj(); } } catch (e) {}
    t.addEventListener('input', function () { try { sessionStorage.clanoviTrazi = t.value; } catch (e) {} });
    redovi.forEach(function (tr) {
        tr.addEventListener('click', function (e) { if (e.target.closest('input,a,button')) return; location.href = tr.dataset.href; });
    });
    var traka = document.getElementById('traka');
    var osvjezi = function () {
        if (!traka) return;
        var n = document.querySelectorAll('input[name="odabrani[]"]:checked').length;
        document.getElementById('brojOdabranih').textContent = n;
        traka.style.setProperty('display', n ? 'flex' : 'none', 'important');
    };
    document.addEventListener('change', osvjezi);
    document.addEventListener('ev-oznaceno', osvjezi);
})();
</script>
<?php stranica('Članovi', ob_get_clean());
