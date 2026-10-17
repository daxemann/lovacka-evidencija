<?php
/** Lovački domovi: popis domova (cijela udruga / sekcije), karta za dolazak, moje rezervacije, zahtjevi na čekanju (upravitelj). */
$k = korisnik();
$domovi = dom_svi(true);
$domovi = array_values(array_filter($domovi, fn($d) => $d['Aktivan'] || dom_upravlja($d)));
if (!$domovi && !ima(P_DOMOVI)) {
    stranica('Lovački domovi', '<h1 class="h3">🏡 Lovački domovi</h1><div class="alert alert-secondary">Još nema lovačkih domova za vašu sekciju.</div>');
}

if (je_post()) {
    $g = dom_obradi((int) ($_POST['rid'] ?? 0), (string) ($_POST['radnja'] ?? ''), (string) ($_POST['razlog'] ?? ''));
    $ok = ['odobri' => 'Rezervacija je odobrena.', 'odbij' => 'Rezervacija je odbijena.', 'otkazi' => 'Rezervacija je otkazana.'];
    poruka($g === null ? ($ok[$_POST['radnja'] ?? ''] ?? 'Spremljeno.') : e($g), $g === null ? 'success' : 'danger');
    preusmjeri('domovi');
}

$cekaju = dom_na_cekanju();
$moje = $k['ClanId'] ? redovi("SELECT r.*, d.Naziv AS Dom FROM DomRezervacije r JOIN LovackiDomovi d ON d.Id=r.DomId
    WHERE r.Vrsta='privatno' AND (r.ClanId=? OR r.KorisnikId=?) AND (r.Do>=? OR r.Obradjeno>=?) ORDER BY r.Od LIMIT 30",
    [$k['ClanId'], $k['Id'], danas(), date('Y-m-d', strtotime('-14 days'))]) : [];
$sljedece = [];
if ($domovi) {
    $ids = implode(',', array_map(fn($d) => (int) $d['Id'], $domovi));
    foreach (redovi("SELECT * FROM DomRezervacije WHERE DomId IN ($ids) AND Status IN ('ceka','odobreno') AND Do>=? ORDER BY Od", [danas()]) as $r) {
        $sljedece[(int) $r['DomId']][] = $r;
    }
}
$saPolozajem = array_filter($domovi, fn($d) => $d['Lat'] !== null && $d['Aktivan']);
$cfg = [
    'domovi' => array_values(array_map(fn($d) => ['naziv' => $d['Naziv'], 'lat' => (float) $d['Lat'], 'lon' => (float) $d['Lon'], 'opseg' => dom_opseg_naziv($d),
        'nav' => dom_navigacija($d), 'url' => url('domovi/dom', ['id' => $d['Id']])], $saPolozajem)),
    'granice' => revir_granice(),
    'centar' => revir_centar(),
];
$statusBadge = fn(array $r) => '<span class="badge ' . (['ceka' => 'bg-warning text-dark', 'odobreno' => 'bg-success', 'odbijeno' => 'bg-danger', 'otkazano' => 'bg-secondary'][$r['Status']] ?? 'bg-secondary') . '">'
    . e(DOM_STATUSI[$r['Status']] ?? $r['Status']) . '</span>';

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">🏡 Lovački domovi</h1>
    <?php if (dom_sekcije_za_uredjivanje()): ?><a class="btn btn-sm btn-primary" href="<?= e(url('domovi/uredi')) ?>">➕ Novi lovački dom</a><?php endif; ?>
</div>
<p class="text-muted small">Rezervacija po cijelim danima, najviše <?= DOM_MAX_MJESECI ?> mjeseci unaprijed. Privatnu rezervaciju odobrava upravitelj doma; događaji udruge imaju prednost. Ostali u kalendaru vide samo „zauzeto“ i ime.</p>

<?php if ($cekaju): ?>
<div class="card border-warning mb-3">
    <div class="card-header bg-warning-subtle fw-semibold">⏳ Zahtjevi na čekanju <span class="badge bg-warning text-dark"><?= count($cekaju) ?></span></div>
    <div class="list-group list-group-flush">
    <?php foreach ($cekaju as $r): ?>
        <div class="list-group-item">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a class="fw-semibold text-decoration-none" href="<?= e(url('domovi/dom', ['id' => $r['DomId'], 'm' => substr($r['Od'], 0, 7)])) ?>"><?= e($r['Dom']) ?></a>
                <span><?= e(dom_razdoblje($r)) ?> <span class="text-muted small">(<?= dom_broj_dana($r) ?> d.)</span></span>
                <span class="ms-auto small text-muted"><?= e($r['Ime']) ?> · <?= e(datum_vrijeme($r['Kreirano'])) ?></span>
            </div>
            <?php if ($r['Napomena']): ?><div class="small mt-1">📝 <?= e($r['Napomena']) ?></div><?php endif; ?>
            <form method="post" class="d-flex flex-wrap gap-1 mt-2"><?= csrf() ?><input type="hidden" name="rid" value="<?= (int) $r['Id'] ?>">
                <button class="btn btn-sm btn-success" name="radnja" value="odobri">✅ Odobri</button>
                <input name="razlog" class="form-control form-control-sm" style="max-width:260px" maxlength="300" placeholder="napomena / razlog (neobavezno)" aria-label="Razlog">
                <button class="btn btn-sm btn-outline-danger" name="radnja" value="odbij" data-potvrda="Odbiti zahtjev?">✖ Odbij</button>
            </form>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($moje): ?>
<div class="card mb-3">
    <div class="card-header fw-semibold">🗓️ Moje rezervacije</div>
    <div class="list-group list-group-flush">
    <?php foreach ($moje as $r): ?>
        <div class="list-group-item d-flex flex-wrap gap-2 align-items-center">
            <a class="text-decoration-none fw-semibold" href="<?= e(url('domovi/dom', ['id' => $r['DomId'], 'm' => substr($r['Od'], 0, 7)])) ?>"><?= e($r['Dom']) ?></a>
            <span><?= e(dom_razdoblje($r)) ?></span>
            <?= $statusBadge($r) ?>
            <?php if ($r['Razlog'] && in_array($r['Status'], ['odbijeno', 'otkazano', 'odobreno'], true)): ?><span class="small text-muted"><?= e($r['Razlog']) ?></span><?php endif; ?>
            <?php if (in_array($r['Status'], ['ceka', 'odobreno'], true) && $r['Do'] >= danas()): ?>
                <form method="post" class="ms-auto"><?= csrf() ?><input type="hidden" name="rid" value="<?= (int) $r['Id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" name="radnja" value="otkazi" data-potvrda="Otkazati rezervaciju?">Otkaži</button></form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$domovi): ?>
    <div class="alert alert-light border">Još nema lovačkih domova. Dodajte prvi: <b>➕ Novi lovački dom</b>.</div>
<?php endif; ?>
<div class="row g-3 mb-3">
<?php foreach ($domovi as $d): $sl = array_slice($sljedece[(int) $d['Id']] ?? [], 0, 3); $nav = dom_navigacija($d); ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100<?= $d['Aktivan'] ? '' : ' opacity-75' ?>">
            <?php if ($d['Foto']): ?><img src="<?= e(foto_url($d['Foto'])) ?>" class="card-img-top dom-foto" alt="" loading="lazy"><?php endif; ?>
            <div class="card-body d-flex flex-column">
                <div class="d-flex gap-2 align-items-start">
                    <h2 class="h5 mb-1 me-auto"><?= e($d['Naziv']) ?></h2>
                    <?php if (!$d['Aktivan']): ?><span class="badge bg-secondary">nije u upotrebi</span><?php endif; ?>
                </div>
                <div class="small text-muted mb-2"><?= e(dom_opseg_naziv($d)) ?><?= $d['Kapacitet'] ? ' · 👥 do ' . (int) $d['Kapacitet'] . ' osoba' : '' ?></div>
                <?php if ($d['Opis']): ?><div class="small mb-2"><?= nl2br(e(mb_strimwidth($d['Opis'], 0, 220, '…'))) ?></div><?php endif; ?>
                <?php if ($d['Kontakt']): ?><div class="small mb-2">🔑 <?= e($d['Kontakt']) ?></div><?php endif; ?>
                <div class="small mb-2">
                    <?php if ($sl): ?><b>Rezervirano:</b>
                        <?php foreach ($sl as $r): ?><div class="dom-mini <?= e($r['Vrsta'] === DOM_VRSTA_UDRUGA ? 'udruga' : $r['Status']) ?>"><?= e(dom_razdoblje($r)) ?> · <?= e(dom_oznaka($r)) ?></div><?php endforeach; ?>
                    <?php else: ?><span class="text-success">✓ Nema rezervacija</span><?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <a class="btn btn-sm btn-primary" href="<?= e(url('domovi/dom', ['id' => $d['Id']])) ?>">📅 Kalendar i rezervacija</a>
                    <?php if ($nav): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e($nav) ?>" target="_blank" rel="noopener">🧭 Navigacija</a><?php endif; ?>
                    <?php if (dom_upravlja($d)): ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(url('domovi/uredi', ['id' => $d['Id']])) ?>">✏️ Uredi</a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($saPolozajem): ?>
<link rel="stylesheet" href="<?= e(asset('assets/leaflet/leaflet.css')) ?>">
<h2 class="h5">🗺️ Gdje se nalaze</h2>
<div id="dom-karta" class="dom-karta mb-4"></div>
<script id="dom-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(asset('assets/domovi.js')) ?>"></script>
<?php endif; ?>
<?php stranica('Lovački domovi', ob_get_clean());
