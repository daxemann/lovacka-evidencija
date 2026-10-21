<?php
/** ⚙️ Sustav → Početna: koje blokove vidi koja uloga (i članovi bez uloge) i kojim redom. */
trazi(P_SUSTAV);
$p = pocetna_postavke();
$uloge = redovi('SELECT Id, Naziv, Sustavna FROM Uloge ORDER BY Sustavna DESC, Naziv');
$stupci = ['clan' => 'Član (bez uloge)'];
foreach ($uloge as $u) {
    $stupci[(string) $u['Id']] = $u['Naziv'];
}
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'spremi') {
        $odabir = (array) ($_POST['b'] ?? []);
        foreach (array_keys($stupci) as $s) {
            $p['uloge'][$s] = array_values(array_filter($p['red'], fn($b) => !empty($odabir[$s][$b])));
        }
        pocetna_spremi($p);
        dnevnik('Postavke početne stranice spremljene');
        poruka('Spremljeno – početna se odmah prikazuje po novim postavkama.');
    } elseif (in_array($radnja, ['gore', 'dolje'], true) && ($i = array_search((string) ($_POST['blok'] ?? ''), $p['red'], true)) !== false) {
        $j = $radnja === 'gore' ? $i - 1 : $i + 1;
        if (isset($p['red'][$j])) {
            [$p['red'][$i], $p['red'][$j]] = [$p['red'][$j], $p['red'][$i]];
            pocetna_spremi($p);
        }
    } elseif ($radnja === 'zadano') {
        spremi_postavku('Pocetna.Raspored', null);
        dnevnik('Postavke početne stranice vraćene na zadano');
        poruka('Vraćeno na zadane postavke.');
    }
    preusmjeri('sustav/pocetna');
}
ob_start(); ?>
<h1 class="h3 mb-1">🏠 Početna stranica</h1>
<p class="text-muted small mb-3">Za svaku ulogu odaberite što vidi na početnoj i kojim redom (strelice mijenjaju redoslijed za sve). Tko ima više uloga, vidi sve blokove svojih uloga.
    Blok se i dalje pokazuje samo ako osoba ima pravo na te podatke i modul je uključen. Veliki gumbi (🟩) koji stoje jedan iza drugoga slažu se u mrežu.</p>
<form method="post" id="pocetna-obrazac"><?= csrf() ?><input type="hidden" name="radnja" value="spremi">
<div class="table-responsive pocetna-matrica mb-3"><table class="table table-sm align-middle mb-0">
    <thead><tr><th class="sticky-start">Blok</th>
        <?php foreach ($stupci as $s => $naz): ?><th class="text-center small"><?= e($naz) ?><br><a class="fw-normal" href="<?= e(url('pocetna', ['pregled' => $s])) ?>" title="Pregled početne kao ova uloga">👁 pregled</a></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($p['red'] as $i => $b): [$naz, $opis, $plocica] = POCETNA_BLOKOVI[$b]; ?>
        <tr><td class="sticky-start">
            <div class="d-flex align-items-center gap-1">
                <span class="d-flex flex-column">
                    <button class="btn btn-link btn-sm p-0 lh-1" form="pomak" name="blok" value="<?= e($b) ?>" formaction="<?= e(url('sustav/pocetna')) ?>" onclick="document.getElementById('pomak-radnja').value='gore'"<?= $i === 0 ? ' disabled' : '' ?> title="Gore">▲</button>
                    <button class="btn btn-link btn-sm p-0 lh-1" form="pomak" name="blok" value="<?= e($b) ?>" formaction="<?= e(url('sustav/pocetna')) ?>" onclick="document.getElementById('pomak-radnja').value='dolje'"<?= $i === count($p['red']) - 1 ? ' disabled' : '' ?> title="Dolje">▼</button>
                </span>
                <div><div class="fw-semibold small"><?= $plocica ? '🟩 ' : '' ?><?= e($naz) ?></div><div class="text-muted" style="font-size:.75rem"><?= e($opis) ?></div></div>
            </div></td>
            <?php foreach ($stupci as $s => $_): ?><td class="text-center"><input type="checkbox" class="form-check-input" name="b[<?= e($s) ?>][<?= e($b) ?>]" value="1"<?= in_array($b, $p['uloge'][$s] ?? [], true) ? ' checked' : '' ?>></td><?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="d-flex flex-wrap gap-2">
    <button class="btn btn-primary">Spremi</button>
    <button class="btn btn-outline-secondary ms-auto" form="zadano" data-potvrda="Vratiti početnu za sve uloge na zadane postavke?">Vrati zadano</button>
</div>
</form>
<form method="post" id="pomak"><?= csrf() ?><input type="hidden" name="radnja" id="pomak-radnja" value="gore"></form>
<form method="post" id="zadano"><?= csrf() ?><input type="hidden" name="radnja" value="zadano"></form>
<p class="small text-muted mt-3">Napomena: strelice odmah mijenjaju redoslijed – nespremljene kvačice se pritom gube, zato najprije spremite.</p>
<?php stranica('Početna – postavke', ob_get_clean());
