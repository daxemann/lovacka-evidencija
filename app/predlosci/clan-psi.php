<?php
/** Kartica Lovački psi. Varijable: $id; iz Moj profil još $psiProfil = true (član uređuje svoje pse). */
$psiProfil = !empty($psiProfil);
$moze = $psiProfil || ima(P_CLANOVI_UREDI);
$psi = psi_clana($id);
$god = (int) date('Y');
$std = bodovi_psa_standard();
$url = e($psiProfil ? url('moj-profil') . '#psi' : url('clanovi/uredi', ['id' => $id]));
$gumb = fn(string $r, array $skr, string $tekst, string $klasa, string $potvrda = '') =>
    '<form method="post" class="d-inline" action="' . $url . '">' . csrf() . '<input type="hidden" name="radnja" value="' . $r . '">'
    . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . $k . '" value="' . e((string) $v) . '">', array_keys($skr), $skr))
    . '<button class="btn btn-sm ' . $klasa . '"' . ($potvrda ? ' data-potvrda="' . e($potvrda) . '"' : '') . '>' . $tekst . '</button></form>';
$polja = function (?array $p) { ob_start(); ?>
    <div class="col-sm-4"><label class="form-label small">Ime psa</label><input name="Ime" class="form-control form-control-sm" maxlength="60" required value="<?= e($p['Ime'] ?? '') ?>"></div>
    <div class="col-sm-4"><label class="form-label small">Pasmina</label><input name="Pasmina" class="form-control form-control-sm" maxlength="80" list="pasmine" value="<?= e($p['Pasmina'] ?? '') ?>"></div>
    <div class="col-sm-2"><label class="form-label small">Spol</label><select name="Spol" class="form-select form-select-sm"><option value=""></option><option<?= sel('pas', $p['Spol'] ?? '') ?> value="pas">pas (m)</option><option<?= sel('kuja', $p['Spol'] ?? '') ?> value="kuja">kuja (ž)</option></select></div>
    <div class="col-sm-2"><label class="form-label small">Datum oštenja</label><input type="date" name="DatumRodjenja" class="form-control form-control-sm" value="<?= e(substr((string) ($p['DatumRodjenja'] ?? ''), 0, 10)) ?>"></div>
    <div class="col-sm-4"><label class="form-label small">Broj rodne knjige</label><input name="RodnaKnjiga" class="form-control form-control-sm" maxlength="40" placeholder="npr. HRK NjKP 1234" value="<?= e($p['RodnaKnjiga'] ?? '') ?>"></div>
    <div class="col-sm-4"><label class="form-label small">Broj čipa</label><input name="Cip" class="form-control form-control-sm" maxlength="40" inputmode="numeric" value="<?= e($p['Cip'] ?? '') ?>"></div>
    <div class="col-6 col-sm-2"><label class="form-label small">Boja</label><input name="Boja" class="form-control form-control-sm" maxlength="60" list="boje" value="<?= e($p['Boja'] ?? '') ?>"></div>
    <div class="col-6 col-sm-2"><label class="form-label small">Dlaka</label><input name="Dlaka" class="form-control form-control-sm" maxlength="40" list="dlake" value="<?= e($p['Dlaka'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label small">Napomena</label><input name="Napomena" class="form-control form-control-sm" maxlength="200" value="<?= e($p['Napomena'] ?? '') ?>"></div>
    <div class="col-12 d-flex flex-wrap gap-3">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="Bodovanje" value="1" id="bod<?= (int) ($p['Id'] ?? 0) ?>"<?= chk($p ? $p['Bodovanje'] : 1) ?>><label class="form-check-label small" for="bod<?= (int) ($p['Id'] ?? 0) ?>">godišnji bodovi za psa (1.1.)</label></div>
        <div class="form-check"><input class="form-check-input potraga-kv" type="checkbox" name="Potraga" value="1" id="pot<?= (int) ($p['Id'] ?? 0) ?>"<?= chk($p ? $p['Potraga'] : 0) ?><?= $p ? '' : ' data-auto-potraga="1"' ?>><label class="form-check-label small" for="pot<?= (int) ($p['Id'] ?? 0) ?>">🩸 radi potragu (krvni trag)</label></div>
        <?php if ($p): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="Aktivan" value="1" id="akt<?= (int) $p['Id'] ?>"<?= chk($p['Aktivan']) ?>><label class="form-check-label small" for="akt<?= (int) $p['Id'] ?>">pas je aktivan (isključite kad ugine / proda se)</label></div><?php endif; ?>
    </div>
<?php return (string) ob_get_clean(); };
?>
<datalist id="boje"><?php foreach (['smeđa', 'smeđe-bijela', 'smeđi čilaš', 'crna', 'crno-bijela', 'crveno-smeđa', 'žuta', 'žuto-bijela', 'bijelo-narančasta', 'crveni', 'jelenje crvena', 'tigrasta', 'trobojna'] as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist>
<datalist id="dlake"><?php foreach (['kratka', 'oštra', 'duga', 'glatka', 'kovrčava'] as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist>
<datalist id="pasmine"><?php foreach (['Njemački kratkodlaki ptičar', 'Njemački oštrodlaki ptičar (Drahthaar)', 'Vižla', 'Jazavčar', 'Jagdterijer', 'Bavarski krvoslijednik', 'Hannoverski krvoslijednik', 'Istarski kratkodlaki gonič', 'Istarski oštrodlaki gonič', 'Posavski gonič', 'Brak jazavčar', 'Labrador retriver', 'Engleski seter', 'Irski seter', 'Weimaraner', 'Fox terijer', 'Prepeličar (njemački)', 'Brak'] as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist>
<?php $bl = bodovna_lista_psi(); ?>
<div class="card mb-3"><div class="card-body py-2">
    <div class="fw-semibold small mb-1">Bodovna lista – psi <?php if (ima(P_SUSTAV)): ?><a class="fw-normal ms-1" href="<?= e(url('akcije/vrste')) ?>">promijeni bodove</a><?php endif; ?></div>
    <div class="d-flex flex-wrap gap-2 small">
        <span class="badge text-bg-light border fw-normal">Posjedovanje psa (svake godine 1.1.): <b><?= broj($bl['godisnje']['bodovi']) ?></b></span>
        <span class="badge text-bg-light border fw-normal">Pas na zajedničkom lovu (po lovu): <b><?= broj($bl['lov']['bodovi']) ?></b></span>
        <span class="badge text-bg-light border fw-normal">Potraga s psom – krvni trag (prijavi član): <b><?= broj($bl['potraga']['bodovi']) ?></b></span>
    </div>
    <div class="small text-muted mt-1">Sve se upisuje kao radna akcija <b>na čekanju</b> i odobrava kao i ostali bodovi. Lov: član pri „Dolazim“ u kalendaru označi psa (vrsta termina „lov sa psima“).</div>
</div></div>
<?php if (!$psiProfil && ima(P_SUSTAV) && array_filter($bl, fn($x) => $x['bodovi'] <= 0)): ?><div class="alert alert-warning small py-2">Neki bodovi za pse su 0 – upišite ih u <a href="<?= e(url('akcije/vrste')) ?>">Vrste radnih akcija</a>.</div><?php endif; ?>

<?php foreach ($psi as $p): $pid = (int) $p['Id']; ?>
    <div class="card mb-3<?= $p['Aktivan'] ? '' : ' opacity-75' ?>"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h2 class="h5 mb-0">🐕 <?= e($p['Ime']) ?></h2>
            <span class="text-muted"><?= e(implode(' · ', array_filter([$p['Pasmina'], $p['Spol'], $p['Boja'] ?? null, $p['Dlaka'] ? 'dlaka ' . $p['Dlaka'] : null, $p['DatumRodjenja'] ? 'oštenje ' . datum($p['DatumRodjenja']) : null,
                $p['RodnaKnjiga'] ? 'RK ' . $p['RodnaKnjiga'] : null, $p['Cip'] ? 'čip ' . $p['Cip'] : null]))) ?></span>
            <?php if ($p['Potraga']): ?><span class="badge bg-danger-subtle text-danger-emphasis border">🩸 potraga</span><?php endif; ?>
            <?php if (!$p['Aktivan']): ?><span class="badge bg-secondary">neaktivan</span><?php endif; ?>
            <span class="ms-auto small">
                <?php if (isset($p['Bodovano'][$god])): ?><span class="badge bg-success-subtle text-success-emphasis border">bodovi <?= $god ?>. upisani</span>
                <?php elseif ($p['Aktivan'] && $p['Bodovanje']): ?><span class="badge text-bg-light border">bodovi <?= $god ?>. još nisu upisani</span>
                <?php elseif (!$p['Bodovanje']): ?><span class="badge text-bg-light border">bez bodovanja</span><?php endif; ?>
            </span>
        </div>
        <?php if ($p['Napomena']): ?><div class="small text-muted mb-1"><?= e($p['Napomena']) ?></div><?php endif; ?>
        <div class="fw-semibold small mt-2">Ispiti / ocjene</div>
        <?php if ($p['Ispiti']): ?>
            <table class="table table-sm mb-1"><tbody>
            <?php foreach ($p['Ispiti'] as $is): ?><tr><td><?= e($is['Naziv']) ?></td><td class="text-nowrap"><?= e(datum($is['Datum'])) ?></td><td><?= e($is['Ocjena']) ?></td>
                <td class="text-end"><?php if ($moze) echo $gumb('pas-ispit-obrisi', ['iid' => $is['Id']], '✕', 'btn-link text-danger p-0', 'Obrisati ispit?'); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php else: ?><div class="small text-muted mb-1">Nema upisanih ispita.</div><?php endif; ?>
        <?php if ($p['Akcije']): ?>
            <details class="small mb-2"><summary>Bodovi psa (<?= count($p['Akcije']) ?>)</summary>
                <table class="table table-sm mb-0 mt-1"><tbody>
                <?php foreach ($p['Akcije'] as $a): ?><tr><td class="text-nowrap"><?= e(datum($a['Datum'])) ?></td><td><?= e($a['VrstaNaziv'] ?? $a['VrstaSlobodno']) ?></td>
                    <td class="text-muted"><?= e($a['Opis']) ?></td><td><?= status_akcije_oznaka((int) $a['Status']) ?></td><td class="text-end"><?= $a['Bodovi'] !== null ? e(broj($a['Bodovi'], 2)) . ' b.' : '' ?></td></tr><?php endforeach; ?>
                </tbody></table></details>
        <?php endif; ?>
        <?php if ($moze): ?>
            <form method="post" action="<?= $url ?>" class="row g-1 align-items-end mb-2 no-print"><?= csrf() ?><input type="hidden" name="radnja" value="pas-ispit"><input type="hidden" name="pid" value="<?= $pid ?>">
                <div class="col-sm-5"><input name="Naziv" class="form-control form-control-sm" maxlength="100" placeholder="ispit, npr. Uzgojni ispit (UI), ispit na krvnom tragu…" list="ispiti" required></div>
                <div class="col-sm-3"><input type="date" name="Datum" class="form-control form-control-sm"></div>
                <div class="col-sm-2"><input name="Ocjena" class="form-control form-control-sm" maxlength="40" placeholder="ocjena / nagrada"></div>
                <div class="col-sm-2"><button class="btn btn-sm btn-outline-primary w-100">+ Ispit</button></div>
            </form>
            <details class="no-print"><summary class="small">Uredi psa</summary>
                <form method="post" action="<?= $url ?>" class="row g-2 mt-1"><?= csrf() ?><input type="hidden" name="radnja" value="pas-spremi"><input type="hidden" name="pid" value="<?= $pid ?>">
                    <?= $polja($p) ?>
                    <div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-primary">Spremi</button>
                        <span class="ms-auto"></span></div>
                </form>
                <?= $gumb('pas-obrisi', ['pid' => $pid], 'Obriši psa', 'btn-link text-danger small p-0 mt-1', 'Obrisati psa i njegove ispite? (Već upisane radne akcije ostaju.)') ?>
            </details>
        <?php endif; ?>
    </div></div>
<?php endforeach; ?>
<datalist id="ispiti"><?php foreach (['Uzgojni ispit (UI)', 'Ispit prirođenih osobina', 'Jesenski uzgojni ispit', 'Ispit radnih osobina', 'Ispit na krvnom tragu', 'Ispit u jazbini', 'Ispit goniča', 'Izložba (CAC)', 'Svestrani ispit (VGP)'] as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist>
<?php if (!$psi): ?><p class="text-muted">Član nema upisanih lovačkih pasa.</p><?php endif; ?>

<?php if ($moze): ?>
    <div class="card border-dashed mb-3 no-print"><div class="card-body">
        <h2 class="h6">+ Novi lovački pas</h2>
        <form method="post" action="<?= $url ?>" class="row g-2"><?= csrf() ?><input type="hidden" name="radnja" value="pas-spremi">
            <?= $polja(null) ?>
            <div class="col-12"><button class="btn btn-primary btn-sm">Dodaj psa</button></div>
        </form>
    </div></div>
    <?php $nema = array_filter($psi, fn($p) => $p['Aktivan'] && $p['Bodovanje'] && !isset($p['Bodovano'][$god]));
    if ($nema && !$psiProfil && ima(P_AKCIJE_ODOBRI)): ?>
        <div class="no-print"><?= $gumb('pas-bodovi', [], 'Upiši bodove za ' . $god . '. sada (' . count($nema) . ' ' . (count($nema) === 1 ? 'pas' : 'psa') . ', na čekanju)', 'btn-outline-success',
            'Upisati radnu akciju „Lovački pas“ za ' . $god . '. (na čekanju odobrenja)?') ?>
            <div class="form-text">Npr. za psa dodanog tijekom godine – automatski upis je samo 1. siječnja.</div></div>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    // nova kvačica „potraga“ predloži se sama prema pasmini (dok je korisnik ne dira)
    var kljucevi = <?= json_encode(array_map('kljuc', PASMINE_POTRAGA)) ?>;
    function kl(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd'); }
    document.querySelectorAll('input[data-auto-potraga]').forEach(function (kv) {
        var f = kv.form, pas = f && f.querySelector('[name=Pasmina]');
        if (!pas) return;
        kv.addEventListener('change', function () { kv.removeAttribute('data-auto-potraga'); });
        pas.addEventListener('input', function () {
            if (!kv.hasAttribute('data-auto-potraga')) return;
            var k = kl(pas.value); kv.checked = kljucevi.some(function (x) { return x && k.indexOf(kl(x)) >= 0; });
        });
    });
})();
</script>
