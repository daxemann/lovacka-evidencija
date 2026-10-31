<?php
/** ⚙️ Sustav → Početna: koje blokove vidi koja uloga (i članovi bez uloge) i kojim redom. */
trazi(P_SUSTAV);
$p = pocetna_postavke();
$uloge = redovi('SELECT Id, Naziv, Sustavna FROM Uloge ORDER BY Sustavna DESC, Naziv');
$stupci = ['clan' => 'Član (bez uloge)'];
foreach ($uloge as $u) {
    $stupci[(string) $u['Id']] = $u['Naziv'];
}
// skupine na stranici (i redoslijed na početnoj): veliki gumbi → blokovi s podacima → gumbi iz izbornika
$skupina = fn(string $b) => str_starts_with($b, 'm_') ? 'izbornik' : (POCETNA_BLOKOVI[$b][2] ? 'gumbi' : 'blokovi');
$skupine = [
    'gumbi' => ['🟩 Veliki gumbi (lov)', 'na vrhu početne, slažu se u mrežu'],
    'blokovi' => ['📋 Blokovi s podacima', 'vrijeme, vjetar, termini, članarina, upozorenja …'],
    'izbornik' => ['🔘 Gumbi iz izbornika', 'na dnu početne – tko nema pravo ili modul nije uključen, gumb ne vidi'],
];
// blok → modul (isključen modul = redak skriven, kvačice ostaju sačuvane)
$modulBloka = [
    'sken' => 'dezinfekcija', 'qr' => 'dezinfekcija', 'mobilna' => 'dezinfekcija', 'm_dez_knjiga' => 'dezinfekcija', 'm_dez_naknadno' => 'dezinfekcija', 'm_dez_liste' => 'dezinfekcija', 'm_dez_postavke' => 'dezinfekcija',
    'karta' => 'revir', 'danas' => 'revir', 'kvarovi' => 'revir', 'danas_popis' => 'revir', 'm_dnevnik' => 'revir', 'm_revir_postavke' => 'revir',
    'vrijeme' => 'vrijeme', 'vjetar' => 'vjetar', 'skupni' => 'grupni', 'obavijest_nova' => 'obavijesti', 'obavijesti' => 'obavijesti',
    'termini' => 'kalendar', 'm_kalendar' => 'kalendar', 'moje_akcije' => 'akcije', 'm_moje_akcije' => 'akcije', 'm_akcija_nova' => 'akcije', 'm_akcije' => 'akcije', 'm_izvjestaj' => 'akcije',
    'clanarina' => 'clanarina', 'm_uplatnica' => 'clanarina', 'm_clanarina' => 'clanarina', 'm_domovi' => 'domovi', 'm_dokumenti_moji' => 'mojidok', 'm_iskaznica' => 'iskaznice',
    'm_popusti' => 'popusti', 'm_odstrel' => 'odstrel', 'm_psi' => 'psi', 'm_oglasnik' => 'oglasnik', 'm_dokumenti' => 'dokumenti', 'm_inventura' => 'inventura',
];
$aktivan = fn(string $b) => !isset($modulBloka[$b]) || modul($modulBloka[$b]);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'spremi') {
        // redoslijed iz obrasca (strelice rade bez ponovnog učitavanja), nepoznati/nedostajući blokovi na kraj
        $red = array_values(array_unique(array_filter(array_map('strval', (array) ($_POST['red'] ?? [])), fn($b) => isset(POCETNA_BLOKOVI[$b]))));
        foreach ($p['red'] as $b) {
            if (!in_array($b, $red, true)) {
                $red[] = $b;
            }
        }
        $p['red'] = $red;
        $odabir = (array) ($_POST['b'] ?? []);
        foreach (array_keys($stupci) as $s) {
            $p['uloge'][$s] = array_values(array_filter($p['red'], fn($b) => !empty($odabir[$s][$b])));
        }
        pocetna_spremi($p);
        dnevnik('Postavke početne stranice spremljene');
        poruka('Spremljeno – početna se odmah prikazuje po novim postavkama.');
    } elseif ($radnja === 'zadano') {
        spremi_postavku('Pocetna.Raspored', null);
        dnevnik('Postavke početne stranice vraćene na zadano');
        poruka('Vraćeno na zadane postavke.');
    }
    preusmjeri('sustav/pocetna');
}
// redovi po skupinama (unutar skupine zadržan spremljeni redoslijed)
$poSkupini = array_fill_keys(array_keys($skupine), []);
foreach ($p['red'] as $b) {
    $poSkupini[$skupina($b)][] = $b;
}
$brojIskljucenih = count(array_filter($p['red'], fn($b) => !$aktivan($b)));
ob_start(); ?>
<h1 class="h3 mb-1">🏠 Početna stranica</h1>
<p class="text-muted small mb-3">Za svaku ulogu odaberite što vidi na početnoj. Tko ima više uloga, vidi sve blokove svojih uloga.
    Blok se pokazuje samo ako osoba ima pravo na te podatke. Strelice ▲▼ mijenjaju redoslijed unutar skupine (za sve uloge) – vrijedi nakon „Spremi“.</p>

<div class="d-flex flex-wrap align-items-center gap-3 mb-2">
    <label class="small text-muted">Prikaži
        <select class="form-select form-select-sm d-inline-block w-auto ms-1" data-matrica-filtar="#mat-pocetna">
            <option value="">sve uloge</option>
            <?php foreach ($stupci as $s => $naz): ?><option value="<?= e($s) ?>"><?= e($naz) ?></option><?php endforeach; ?>
        </select></label>
    <div class="form-check form-switch small mb-0"><input class="form-check-input" type="checkbox" id="samoOznaceni">
        <label class="form-check-label" for="samoOznaceni">samo uključeni blokovi</label></div>
    <?php if ($brojIskljucenih): ?>
        <div class="form-check form-switch small mb-0"><input class="form-check-input" type="checkbox" id="iskljuceniModuli">
            <label class="form-check-label" for="iskljuceniModuli">prikaži i isključene module (<?= $brojIskljucenih ?>)</label></div>
    <?php endif; ?>
</div>

<form method="post" id="pocetna-obrazac"><?= csrf() ?><input type="hidden" name="radnja" value="spremi">
<div class="matrica" id="mat-pocetna"><table class="table table-sm align-middle">
    <thead><tr><th class="prvi">Blok</th>
        <?php foreach ($stupci as $s => $naz): ?><th class="stup" data-u="<?= e($s) ?>"><?= e($naz) ?><div class="fw-normal"><a href="<?= e(url('pocetna', ['pregled' => $s])) ?>" title="Pregled početne kao ova uloga">👁 pregled</a></div></th><?php endforeach; ?>
    </tr></thead>
    <?php foreach ($skupine as $g => [$gNaz, $gOpis]): if (!$poSkupini[$g]) continue; ?>
    <tbody data-skupina="<?= e($g) ?>">
        <tr class="grupa"><td class="prvi"><?= e($gNaz) ?><div class="fw-normal text-muted small"><?= e($gOpis) ?></div></td><?php foreach ($stupci as $s => $_): ?><td data-u="<?= e($s) ?>"></td><?php endforeach; ?></tr>
        <?php foreach ($poSkupini[$g] as $b): [$naz, $opis] = POCETNA_BLOKOVI[$b]; ?>
        <tr class="blok<?= $aktivan($b) ? '' : ' iskljucen' ?>"<?= $aktivan($b) ? '' : ' hidden' ?>>
            <td class="prvi"><div class="d-flex align-items-center gap-2">
                <span class="d-flex flex-column"><button type="button" class="btn btn-link btn-sm p-0 lh-1 pomak" data-smjer="-1" title="Gore">▲</button><button type="button" class="btn btn-link btn-sm p-0 lh-1 pomak" data-smjer="1" title="Dolje">▼</button></span>
                <input type="hidden" name="red[]" value="<?= e($b) ?>">
                <div><div class="fw-semibold small"><?= e($naz) ?><?= $aktivan($b) ? '' : ' <span class="badge text-bg-secondary">modul isključen</span>' ?></div>
                    <div class="text-muted d-none d-md-block" style="font-size:.75rem"><?= e($opis) ?></div></div>
            </div></td>
            <?php foreach ($stupci as $s => $naz): ?><td class="stup" data-u="<?= e($s) ?>"><input type="checkbox" class="form-check-input" name="b[<?= e($s) ?>][<?= e($b) ?>]" value="1" title="<?= e($naz . ' – ' . POCETNA_BLOKOVI[$b][0]) ?>"<?= in_array($b, $p['uloge'][$s] ?? [], true) ? ' checked' : '' ?>></td><?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
</table></div>
<div class="matrica-spremi d-flex flex-wrap align-items-center gap-2">
    <button class="btn btn-primary">Spremi</button><span class="small text-muted">Ne zaboravite spremiti nakon promjene.</span>
    <button class="btn btn-outline-secondary btn-sm ms-auto" form="zadano" data-potvrda="Vratiti početnu za sve uloge na zadane postavke?">Vrati zadano</button>
</div>
</form>
<form method="post" id="zadano"><?= csrf() ?><input type="hidden" name="radnja" value="zadano"></form>
<script>
(function () {
    var mat = document.getElementById('mat-pocetna'), samo = document.getElementById('samoOznaceni'), isk = document.getElementById('iskljuceniModuli');
    function vidljivo(tr) { return !tr.hidden; }
    function osvjezi() {
        mat.querySelectorAll('tr.blok').forEach(function (tr) {
            var sakrij = (tr.classList.contains('iskljucen') && !(isk && isk.checked)) || (samo.checked && !tr.querySelector('input[type=checkbox]:checked'));
            tr.hidden = sakrij;
        });
        mat.querySelectorAll('tbody').forEach(function (tb) {
            var redovi = Array.prototype.filter.call(tb.querySelectorAll('tr.blok'), vidljivo);
            tb.querySelector('tr.grupa').hidden = !redovi.length;
            redovi.forEach(function (tr, i) {
                var g = tr.querySelectorAll('.pomak');
                g[0].disabled = i === 0; g[1].disabled = i === redovi.length - 1;
            });
        });
    }
    mat.addEventListener('click', function (e) {
        var b = e.target.closest('.pomak'); if (!b) return;
        var tr = b.closest('tr'), smjer = +b.dataset.smjer, susjed = tr;
        do { susjed = smjer < 0 ? susjed.previousElementSibling : susjed.nextElementSibling; } while (susjed && (susjed.hidden || !susjed.classList.contains('blok')));
        if (!susjed) return;
        if (smjer < 0) susjed.before(tr); else susjed.after(tr);
        tr.querySelectorAll('td').forEach(function (td) { td.classList.add('hl'); });
        setTimeout(function () { tr.querySelectorAll('td').forEach(function (td) { td.classList.remove('hl'); }); }, 600);
        osvjezi();
    });
    samo.addEventListener('change', osvjezi);
    if (isk) isk.addEventListener('change', osvjezi);
    osvjezi();
})();
</script>
<?php stranica('Početna – postavke', ob_get_clean());
