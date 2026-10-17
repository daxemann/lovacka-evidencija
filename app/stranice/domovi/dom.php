<?php
/** Lovački dom: podaci, navigacija, mjesečni kalendar zauzeća, rezervacija (privatno / događaj udruge), popis rezervacija. */
$k = korisnik();
$d = dom_dom((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if (!$d) {
    nije_pronadjeno('Lovački dom');
}
$id = (int) $d['Id'];
$upr = dom_upravlja($d);
$mozePrivatno = $k['ClanId'] && dom_vidi($d) && $d['Aktivan'];
$sukobi = null;

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $m = (string) ($_POST['m'] ?? '');
    if ($radnja === 'rezerviraj') {
        $vrsta = ($_POST['vrsta'] ?? '') === DOM_VRSTA_UDRUGA ? DOM_VRSTA_UDRUGA : DOM_VRSTA_PRIVATNO;
        $od = u_datum($_POST['od'] ?? '');
        $do = u_datum($_POST['do'] ?? '') ?? $od;
        $rez = dom_rezerviraj($id, $vrsta, $od, $do, (string) ($_POST['naslov'] ?? ''), (string) ($_POST['napomena'] ?? ''), !empty($_POST['potvrdjeno']));
        if (isset($rez['sukobi'])) {
            $sukobi = $rez['sukobi']; // upravitelj najprije potvrđuje otkazivanje privatnih rezervacija (prikaz niže)
        } elseif (isset($rez['greska'])) {
            poruka(e($rez['greska']), 'danger');
            $_SESSION['dom_unos'] = ['vrsta' => $vrsta, 'od' => $od, 'do' => $do, 'naslov' => $_POST['naslov'] ?? '', 'napomena' => $_POST['napomena'] ?? ''];
            preusmjeri('domovi/dom', ['id' => $id, 'm' => $m]);
        } else {
            if ($vrsta === DOM_VRSTA_UDRUGA) {
                poruka('Događaj udruge je upisan' . ($rez['otkazane'] ? ' – otkazano privatnih rezervacija: ' . count($rez['otkazane']) . ' (dobili su obavijest).' : '.'));
            } else {
                poruka('Zahtjev je poslan – upravitelj doma ga mora odobriti. Do tada se u kalendaru vidi kao „na čekanju“.');
            }
            preusmjeri('domovi/dom', ['id' => $id, 'm' => substr((string) $od, 0, 7)]);
        }
    } else {
        $g = dom_obradi((int) ($_POST['rid'] ?? 0), $radnja, (string) ($_POST['razlog'] ?? ''));
        $ok = ['odobri' => 'Rezervacija je odobrena.', 'odbij' => 'Rezervacija je odbijena.', 'otkazi' => 'Rezervacija je otkazana.'];
        poruka($g === null ? ($ok[$radnja] ?? 'Spremljeno.') : e($g), $g === null ? 'success' : 'danger');
        preusmjeri('domovi/dom', ['id' => $id, 'm' => $m]);
    }
}

// ---------- mjesec ----------
$m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? $_POST['m'] ?? '')) ? (string) ($_GET['m'] ?? $_POST['m']) : date('Y-m');
$najkasnije = dom_najkasnije();
if ($m > substr($najkasnije, 0, 7)) {
    $m = substr($najkasnije, 0, 7);
}
$prvi = new DateTimeImmutable($m . '-01');
$start = $prvi->modify('-' . (((int) $prvi->format('N')) - 1) . ' days');
$kraj = $prvi->modify('last day of this month');
$kraj = $kraj->modify('+' . (7 - (int) $kraj->format('N')) . ' days');
$po = [];
foreach (dom_aktivne($id, $start->format('Y-m-d'), $kraj->format('Y-m-d')) as $r) {
    for ($x = new DateTimeImmutable($r['Od']); $x->format('Y-m-d') <= $r['Do'] && $x <= $kraj; $x = $x->modify('+1 day')) {
        $po[$x->format('Y-m-d')] = $r;
    }
}
// prošle odobrene rezervacije u prikazanom mjesecu (dom_aktivne ih vraća i za prošlost) – samo prikaz
$mjeseci = ['', 'Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac'];
$prethodni = $prvi->modify('-1 month')->format('Y-m');
$sljedeci = $prvi->modify('+1 month')->format('Y-m');

$unos = $_SESSION['dom_unos'] ?? [];
unset($_SESSION['dom_unos']);
if ($sukobi !== null) {
    $unos = ['vrsta' => DOM_VRSTA_UDRUGA, 'od' => u_datum($_POST['od'] ?? ''), 'do' => u_datum($_POST['do'] ?? ''), 'naslov' => $_POST['naslov'] ?? '', 'napomena' => $_POST['napomena'] ?? ''];
}
$odabranOd = u_datum((string) ($_GET['od'] ?? '')) ?? ($unos['od'] ?? '');
$lista = dom_popis($id, danas(), null, !$upr);
$nav = dom_navigacija($d);

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('domovi')) ?>">‹</a>
    <h1 class="h3 mb-0 me-auto">🏡 <?= e($d['Naziv']) ?></h1>
    <?php if ($nav): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($nav) ?>" target="_blank" rel="noopener">🧭 Navigacija</a><?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/domovi-pdf', ['id' => $id])) ?>">📄 PDF</a>
    <?php if ($upr): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('domovi/uredi', ['id' => $id])) ?>">✏️ Uredi</a><?php endif; ?>
</div>
<div class="small text-muted mb-3"><?= e(dom_opseg_naziv($d)) ?><?= $d['Kapacitet'] ? ' · 👥 do ' . (int) $d['Kapacitet'] . ' osoba' : '' ?><?= $d['Kontakt'] ? ' · 🔑 ' . e($d['Kontakt']) : '' ?>
    <?= !$d['Aktivan'] ? ' · <span class="badge bg-secondary">nije u upotrebi</span>' : '' ?></div>
<?php if ($d['Foto'] || $d['Opis']): ?>
<div class="d-flex flex-wrap gap-3 mb-3">
    <?php if ($d['Foto']): ?><a href="<?= e(foto_url($d['Foto'])) ?>" target="_blank" rel="noopener"><img src="<?= e(foto_url($d['Foto'])) ?>" class="dom-foto-mala" alt=""></a><?php endif; ?>
    <?php if ($d['Opis']): ?><div class="small flex-grow-1" style="max-width:640px"><?= nl2br(e($d['Opis'])) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($sukobi): ?>
<div class="alert alert-warning">
    <b>⚠️ U tom razdoblju postoje privatne rezervacije.</b> Događaj udruge ima prednost – ove rezervacije bit će <b>otkazane</b>, a članovi dobivaju obavijest:
    <ul class="mb-2 mt-1"><?php foreach ($sukobi as $s): ?><li><?= e(dom_razdoblje($s)) ?> · <?= e($s['Ime']) ?> (<?= e(DOM_STATUSI[$s['Status']]) ?>)</li><?php endforeach; ?></ul>
    <form method="post" class="d-flex flex-wrap gap-2"><?= csrf() ?>
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="m" value="<?= e($m) ?>"><input type="hidden" name="radnja" value="rezerviraj">
        <input type="hidden" name="vrsta" value="<?= DOM_VRSTA_UDRUGA ?>"><input type="hidden" name="potvrdjeno" value="1">
        <?php foreach (['od', 'do', 'naslov', 'napomena'] as $p): ?><input type="hidden" name="<?= $p ?>" value="<?= e($_POST[$p] ?? '') ?>"><?php endforeach; ?>
        <button class="btn btn-warning btn-sm">Upiši događaj i otkaži navedene</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('domovi/dom', ['id' => $id, 'm' => $m])) ?>">Odustani</a>
    </form>
</div>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('domovi/dom', ['id' => $id, 'm' => $prethodni])) ?>">‹</a>
    <div class="fw-semibold"><?= e($mjeseci[(int) $prvi->format('n')] . ' ' . $prvi->format('Y')) ?></div>
    <?php if ($sljedeci <= substr($najkasnije, 0, 7)): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('domovi/dom', ['id' => $id, 'm' => $sljedeci])) ?>">›</a><?php endif; ?>
    <a class="btn btn-sm btn-link" href="<?= e(url('domovi/dom', ['id' => $id])) ?>">danas</a>
    <div class="dom-legenda small ms-auto">
        <span><i class="slobodno"></i> slobodno</span><span><i class="ceka"></i> na čekanju</span><span><i class="odobreno"></i> zauzeto</span><span><i class="udruga"></i> udruga</span>
    </div>
</div>
<div class="kal-mjesec dom-kal mb-1">
    <?php foreach (['Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub', 'Ned'] as $dn): ?><div class="kal-zag"><?= $dn ?></div><?php endforeach; ?>
    <?php for ($x = $start; $x <= $kraj; $x = $x->modify('+1 day')):
        $dan = $x->format('Y-m-d');
        $r = $po[$dan] ?? null;
        $kl = $x->format('Y-m') !== $m ? ' kal-van' : '';
        $kl .= $dan === danas() ? ' kal-danas' : '';
        $kl .= $dan < danas() ? ' dom-prosli' : '';
        $kl .= $r ? ' dom-' . ($r['Vrsta'] === DOM_VRSTA_UDRUGA ? 'udruga' : $r['Status']) : ($dan >= danas() && $dan <= $najkasnije ? ' dom-slobodno' : '');
        $slobodan = !$r && $dan >= danas() && $dan <= $najkasnije && $d['Aktivan'] && ($mozePrivatno || $upr); ?>
        <div class="kal-dan<?= $kl ?>">
            <?php if ($slobodan): ?><a class="dom-dan-link" href="<?= e(url('domovi/dom', ['id' => $id, 'm' => $m, 'od' => $dan]) . '#rezerviraj') ?>" title="Rezerviraj od <?= e(datum($dan)) ?>"><?php endif; ?>
            <div class="kal-br"><?= $x->format('j') ?></div>
            <?php if ($r): ?><div class="dom-oznaka" title="<?= e(dom_razdoblje($r) . ' · ' . dom_oznaka($r)) ?>"><?= e(dom_oznaka($r)) ?></div><?php endif; ?>
            <?php if ($slobodan): ?></a><?php endif; ?>
        </div>
    <?php endfor; ?>
</div>
<p class="small text-muted mb-4">Dodirnite slobodan dan za rezervaciju. Rezervacija najviše do <?= e(datum($najkasnije)) ?></p>

<?php if ($d['Aktivan'] && ($mozePrivatno || $upr)): $vrsta = $unos['vrsta'] ?? ($mozePrivatno ? DOM_VRSTA_PRIVATNO : DOM_VRSTA_UDRUGA); ?>
<div class="card mb-4" id="rezerviraj">
    <div class="card-header fw-semibold">➕ Rezervacija</div>
    <div class="card-body">
        <form method="post" class="row g-2" data-dom-forma><?= csrf() ?>
            <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="m" value="<?= e($m) ?>"><input type="hidden" name="radnja" value="rezerviraj">
            <?php if ($upr): ?>
            <div class="col-12">
                <div class="btn-group btn-group-sm" role="group" aria-label="Vrsta">
                    <?php if ($mozePrivatno): ?><input type="radio" class="btn-check" name="vrsta" id="v-priv" value="<?= DOM_VRSTA_PRIVATNO ?>"<?= chk($vrsta === DOM_VRSTA_PRIVATNO) ?>><label class="btn btn-outline-secondary" for="v-priv">🙋 Privatno</label><?php endif; ?>
                    <input type="radio" class="btn-check" name="vrsta" id="v-udr" value="<?= DOM_VRSTA_UDRUGA ?>"<?= chk($vrsta === DOM_VRSTA_UDRUGA) ?>><label class="btn btn-outline-secondary" for="v-udr">🏛️ Događaj udruge</label>
                </div>
            </div>
            <?php else: ?><input type="hidden" name="vrsta" value="<?= DOM_VRSTA_PRIVATNO ?>"><?php endif; ?>
            <div class="col-6 col-md-3"><label class="form-label small mb-0">Od *</label>
                <input type="date" name="od" class="form-control form-control-sm" required min="<?= e(danas()) ?>" max="<?= e($najkasnije) ?>" value="<?= e($odabranOd) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-0">Do (uključivo) *</label>
                <input type="date" name="do" class="form-control form-control-sm" required min="<?= e(danas()) ?>" max="<?= e($najkasnije) ?>" value="<?= e($unos['do'] ?? $odabranOd) ?>"></div>
            <?php if ($upr): ?>
            <div class="col-md-6" data-samo-udruga><label class="form-label small mb-0">Naziv događaja * <span class="text-muted">(vide svi)</span></label>
                <input name="naslov" class="form-control form-control-sm" maxlength="100" value="<?= e($unos['naslov'] ?? '') ?>" placeholder="npr. Godišnja skupština, Lovačka zabava"></div>
            <?php endif; ?>
            <div class="col-md-9"><label class="form-label small mb-0">Napomena za upravitelja <span class="text-muted">(ostali je ne vide)</span></label>
                <input name="napomena" class="form-control form-control-sm" maxlength="500" value="<?= e($unos['napomena'] ?? '') ?>" placeholder="npr. rođendan, oko 30 osoba"></div>
            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100" data-gumb-tekst>Pošalji zahtjev</button></div>
            <div class="col-12 small text-muted" data-samo-privatno>Cijeli dan(i). Zahtjev odobrava upravitelj doma – do tada drugi vide „na čekanju“ i ne mogu rezervirati iste dane.</div>
            <?php if ($upr): ?><div class="col-12 small text-muted" data-samo-udruga>Događaj udruge vrijedi odmah i ima prednost – privatne rezervacije u tom razdoblju se otkazuju (uz potvrdu i obavijest članovima).</div><?php endif; ?>
        </form>
    </div>
</div>
<?php elseif (!$k['ClanId'] && !$upr): ?>
<div class="alert alert-light border small">Privatno mogu rezervirati samo članovi (vaš korisnički račun nije povezan s članom).</div>
<?php endif; ?>

<h2 class="h5">Nadolazeće rezervacije</h2>
<?php if (!$lista): ?><div class="alert alert-light border">Nema rezervacija – dom je slobodan.</div><?php endif; ?>
<div class="list-group mb-4">
<?php foreach ($lista as $r):
    $moja = dom_moja($r);
    $vidiDetalje = $upr || $moja;
    $aktivna = in_array($r['Status'], ['ceka', 'odobreno'], true); ?>
    <div class="list-group-item dom-red <?= e($r['Vrsta'] === DOM_VRSTA_UDRUGA ? 'udruga' : $r['Status']) ?>">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <b><?= e(dom_razdoblje($r)) ?></b><span class="small text-muted">(<?= dom_broj_dana($r) ?> d.)</span>
            <span><?= e(dom_oznaka($r)) ?></span>
            <?php if ($vidiDetalje || $r['Status'] !== 'odobreno'): ?><span class="badge <?= ['ceka' => 'bg-warning text-dark', 'odobreno' => 'bg-success', 'odbijeno' => 'bg-danger', 'otkazano' => 'bg-secondary'][$r['Status']] ?? 'bg-secondary' ?> ms-auto"><?= e(DOM_STATUSI[$r['Status']] ?? $r['Status']) ?></span><?php endif; ?>
        </div>
        <?php if ($vidiDetalje): ?>
            <div class="small text-muted">
                <?= $r['Vrsta'] === DOM_VRSTA_UDRUGA ? 'Upisao ' . e($r['Ime']) : 'Zahtjev ' . e(datum_vrijeme($r['Kreirano'])) ?>
                <?php if ($r['ObradioIme'] && $r['Vrsta'] !== DOM_VRSTA_UDRUGA): ?> · <?= e(mb_strtolower(DOM_STATUSI[$r['Status']] ?? '')) ?>: <?= e($r['ObradioIme']) ?>, <?= e(datum_vrijeme($r['Obradjeno'])) ?><?php endif; ?>
            </div>
            <?php if ($r['Napomena']): ?><div class="small">📝 <?= e($r['Napomena']) ?></div><?php endif; ?>
            <?php if ($r['Razlog']): ?><div class="small">💬 <?= e($r['Razlog']) ?></div><?php endif; ?>
        <?php endif; ?>
        <?php if ($aktivna && ($upr || ($moja && $r['Do'] >= danas()))): ?>
        <form method="post" class="d-flex flex-wrap gap-1 mt-2"><?= csrf() ?>
            <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="m" value="<?= e($m) ?>"><input type="hidden" name="rid" value="<?= (int) $r['Id'] ?>">
            <?php if ($upr && $r['Status'] === 'ceka'): ?><button class="btn btn-sm btn-success" name="radnja" value="odobri">✅ Odobri</button><?php endif; ?>
            <?php if ($upr): ?><input name="razlog" class="form-control form-control-sm" style="max-width:260px" maxlength="300" placeholder="napomena / razlog (neobavezno)" aria-label="Razlog"><?php endif; ?>
            <?php if ($upr && $r['Status'] === 'ceka'): ?><button class="btn btn-sm btn-outline-danger" name="radnja" value="odbij" data-potvrda="Odbiti zahtjev?">✖ Odbij</button><?php endif; ?>
            <button class="btn btn-sm btn-outline-danger<?= $upr ? '' : ' ms-auto' ?>" name="radnja" value="otkazi" data-potvrda="Otkazati rezervaciju?">Otkaži</button>
        </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<script>
(function () {
    var f = document.querySelector('[data-dom-forma]');
    if (!f) return;
    function prikaz() {
        var v = f.querySelector('[name=vrsta]:checked') || f.querySelector('[name=vrsta]');
        var udr = v && v.value === '<?= DOM_VRSTA_UDRUGA ?>';
        f.querySelectorAll('[data-samo-udruga]').forEach(function (e) { e.hidden = !udr; });
        f.querySelectorAll('[data-samo-privatno]').forEach(function (e) { e.hidden = udr; });
        var n = f.querySelector('[name=naslov]'); if (n) n.required = udr;
        f.querySelector('[data-gumb-tekst]').textContent = udr ? 'Upiši događaj' : 'Pošalji zahtjev';
    }
    f.addEventListener('change', function (e) {
        if (e.target.name === 'vrsta') prikaz();
        if (e.target.name === 'od' && (!f.do.value || f.do.value < f.od.value)) f.do.value = f.od.value;
    });
    prikaz();
})();
</script>
<?php stranica($d['Naziv'], ob_get_clean());
