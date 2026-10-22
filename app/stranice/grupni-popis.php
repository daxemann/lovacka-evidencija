<?php
/**
 * Skupni lov – popis sudionika: lovnik skenira članske iskaznice (QR) i popis se slaže sam.
 * Skeniranje radi i bez signala dok je stranica otvorena (upisi čekaju na mobitelu i šalju se kad bude signala).
 * Popis je samo popis – ne utječe na poziv, bodove, dezinfekciju ni ništa drugo.
 */
$lov = gl_lov((int) ul('id', 0));
if (!$lov) {
    nije_pronadjeno('Skupni lov');
}
if (!gl_moze_upravljati($lov)) {
    zabranjeno();
}
if (($_GET['pdf'] ?? '') === 'zapisnik') {
    preusmjeri('grupni-zapisnik', ['id' => $lov['Id'], 'pdf' => 1]);
}
if (in_array($_GET['pdf'] ?? '', ['1'], true)) {
    $zap = false;
    $ime = ($zap ? 'zapisnik-' : 'popis-') . gl_broj_datoteka($lov) . '-' . substr($lov['Pocetak'], 0, 10);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $ime . '.pdf"');
    echo $zap ? gl_zapisnik_pdf($lov) : gl_popis_pdf($lov);
    exit;
}

if (je_post() && ($_POST['radnja'] ?? '') === 'posalji') {
    $moguci = gl_primatelji_popisa($lov);
    $prim = array_values(array_intersect(array_keys($moguci), (array) ($_POST['prima'] ?? [])));
    $dod = trim((string) ($_POST['dodatno'] ?? ''));
    if ($dod !== '') {
        if (!filter_var($dod, FILTER_VALIDATE_EMAIL)) {
            poruka('Dodatna e-adresa nije ispravna.', 'warning');
            preusmjeri('grupni-popis', ['id' => $lov['Id']]);
        }
        $prim[] = $dod;
    }
    if (!$prim) {
        poruka('Odaberite barem jednog primatelja.', 'warning');
    } elseif (empty($_POST['d_popis']) && empty($_POST['d_zapisnik'])) {
        poruka('Odaberite što šaljete (popis i/ili zapisnik).', 'warning');
    } elseif ($g = gl_posalji_popis($lov, $prim, korisnik()['Naziv'], !empty($_POST['d_popis']), !empty($_POST['d_zapisnik']))) {
        poruka('Slanje nije uspjelo: ' . e($g), 'danger');
    } else {
        poruka('📧 Poslano (PDF): ' . e(implode(', ', $prim)));
    }
    preusmjeri('grupni-popis', ['id' => $lov['Id']]);
}
$aktivan = gl_aktivan($lov);
$cfg = [
    'api' => url('api/grupni-popis', ['id' => $lov['Id']]),
    'csrf' => csrf_token(),
    'lov' => (int) $lov['Id'],
    'popis' => gl_popis_json($lov),
    'imenik' => gl_popis_imenik($lov),
];
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-lov', ['id' => $lov['Id']])) ?>">← <?= $aktivan ? 'Karta lova' : 'Skupni lov' ?></a>
    <h1 class="h4 mb-0 me-auto">📋 Popis sudionika <span class="text-muted small">br. <?= e(gl_broj($lov)) ?></span></h1>
    <span id="gp-mreza" class="badge rounded-pill text-bg-secondary">…</span>
</div>
<p class="small text-muted mb-3"><?= e($lov['Naziv']) ?> · <?= e(datum($lov['Pocetak'])) ?><?= $lov['Mjesto'] ? ' · 📍 ' . e($lov['Mjesto']) : '' ?>
    <?= $aktivan ? '<span class="badge bg-danger">u tijeku</span>' : '<span class="badge bg-secondary">završen</span>' ?></p>

<div class="gp-raspored">
    <div>
        <button type="button" class="btn btn-success btn-lg w-100 py-3 mb-2" id="gp-start">📷 Skeniraj iskaznice</button>
        <div id="gp-kamera" class="skener-kamera mb-2" hidden>
            <video id="gp-video" playsinline muted></video>
            <div class="skener-okvir-ciljnik"></div>
            <button type="button" class="btn btn-light btn-sm skener-zatvori" id="gp-stop">✕ Gotovo</button>
        </div>
        <div id="gp-zadnji" class="gp-zadnji mb-2" hidden></div>
        <div id="gp-potpis" class="card border-primary mb-2" hidden><div class="card-body p-2">
            <div class="small fw-semibold mb-1" id="gp-potpis-naslov">Potpis</div>
            <div id="gp-potpis-gost" class="d-flex gap-2 mb-2" hidden>
                <input class="form-control form-control-sm" id="gp-potpis-isk" maxlength="40" placeholder="Broj lovačke iskaznice">
                <input class="form-control form-control-sm" id="gp-potpis-ol" maxlength="120" placeholder="Oružni list">
            </div>
            <canvas id="gp-potpis-platno" class="dez-potpis-platno mb-1" width="600" height="160"></canvas>
            <label class="form-check small mb-2" id="gp-potpis-spremi-red"><input type="checkbox" class="form-check-input" id="gp-potpis-spremi" checked>
                <span class="form-check-label">spremi moj potpis za sljedeći put (vidljiv u <i>Moj profil</i>, mogu ga obrisati)</span></label>
            <div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-secondary" id="gp-potpis-ocisti">Očisti</button>
                <button type="button" class="btn btn-sm btn-link text-muted" id="gp-potpis-kasnije">kasnije</button>
                <button type="button" class="btn btn-sm btn-primary ms-auto" id="gp-potpis-spremi-btn">✔ Potpisano</button></div>
        </div></div>
        <div id="gp-poruka" class="small text-center text-muted mb-3">Kamera ostaje otvorena – skenirajte iskaznice jednu za drugom.</div>
        <div id="gp-red" class="alert alert-warning small py-2" hidden></div>
        <div id="gp-greske" class="small mb-2"></div>

        <details class="card mb-3"><summary class="card-header small fw-semibold">✍ Ručni upis (bez iskaznice / gost)</summary>
            <div class="card-body p-3">
                <label class="form-label small mb-1" for="gp-clan">Član</label>
                <div class="d-flex gap-2 mb-3">
                    <input class="form-control form-control-sm" id="gp-clan" list="gp-imenik" placeholder="Prezime i ime…" autocomplete="off">
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="gp-clan-dodaj">Dodaj</button>
                </div>
                <datalist id="gp-imenik"></datalist>
                <label class="form-label small mb-1" for="gp-gost">Gost (nije član udruge)</label>
                <input class="form-control form-control-sm mb-2" id="gp-gost" maxlength="80" placeholder="Ime i prezime gosta" autocomplete="off">
                <div class="d-flex gap-2 mb-2">
                    <input class="form-control form-control-sm" id="gp-gost-isk" maxlength="40" placeholder="Broj lovačke iskaznice" autocomplete="off">
                    <input class="form-control form-control-sm" id="gp-gost-ol" maxlength="120" placeholder="Oružni list" autocomplete="off">
                </div>
                <div class="small text-muted mb-1">Potpis gosta (prstom):</div>
                <canvas id="gp-gost-potpis" class="dez-potpis-platno mb-1" width="600" height="160"></canvas>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gp-gost-ocisti">Očisti potpis</button>
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap ms-auto" id="gp-gost-dodaj">Dodaj gosta</button>
                </div>
                <div class="form-text">Ručni upis treba signal.</div>
            </div>
        </details>
    </div>
    <div>
        <div class="d-flex align-items-center gap-2 mb-2">
            <h2 class="h6 mb-0 me-auto">Na popisu: <b id="gp-broj">0</b> <span class="text-muted small" id="gp-broj-detalj"></span></h2>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-popis', ['id' => $lov['Id'], 'pdf' => 1])) ?>" target="_blank">📄 PDF</a>
            <?php if (!$aktivan): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-zapisnik', ['id' => $lov['Id']])) ?>">📝 Zapisnik</a><?php endif; ?>
        </div>
        <div id="gp-popis" class="list-group small"></div>
    </div>
</div>
<?php $moguci = gl_primatelji_popisa($lov); $vod = gl_email_voditelja($lov); $nZap = (int) vrijednost('SELECT COUNT(*) FROM GrupniLovZapisnik WHERE LovId=?', [$lov['Id']]); ?>
<details class="card mt-3" id="gp-posalji"<?= isset($_GET['posalji']) ? ' open' : '' ?>><summary class="card-header small fw-semibold">📧 Pošalji e-poštom – popis i dodatni zapisnik</summary>
    <div class="card-body p-3">
    <?php if (!posta_dostupna()): ?><div class="small text-muted">Slanje e-pošte nije podešeno (Sustav → E-pošta i adresa).</div>
    <?php else: ?>
        <form method="post" action="<?= e(url('grupni-popis', ['id' => $lov['Id']])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="posalji">
            <div class="d-flex flex-wrap gap-3 mb-2 pb-2 border-bottom">
                <label class="form-check"><input type="checkbox" class="form-check-input" name="d_popis" value="1" checked> <span class="form-check-label">📋 Popis sudionika</span></label>
                <label class="form-check"><input type="checkbox" class="form-check-input" name="d_zapisnik" value="1"<?= $nZap ? ' checked' : ' disabled' ?>>
                    <span class="form-check-label">📝 Dodatni zapisnik <?= $nZap ? '<span class="text-muted small">(' . $nZap . ')</span>' : '<span class="text-muted small">– prazan, <a href="' . e(url('grupni-zapisnik', ['id' => $lov['Id']])) . '">upiši divljač</a></span>' ?></span></label>
            </div>
            <?php foreach ($moguci as $m => $ime): ?>
                <label class="form-check small"><input type="checkbox" class="form-check-input" name="prima[]" value="<?= e($m) ?>"<?= $m === $vod ? ' checked' : '' ?>>
                    <span class="form-check-label"><?= e($ime) ?> <span class="text-muted">· <?= e($m) ?></span><?= $m === $vod ? ' <span class="badge text-bg-light border">voditelj lova</span>' : '' ?></span></label>
            <?php endforeach; ?>
            <?php if (!$moguci): ?><div class="small text-muted mb-2">Nitko od voditelja, lovočuvara i administratora nema upisanu e-poštu.</div><?php endif; ?>
            <input type="email" name="dodatno" class="form-control form-control-sm my-2" placeholder="druga e-adresa (nije obavezno)">
            <button class="btn btn-sm btn-primary">📧 Pošalji</button>
        </form>
        <div class="form-text">Kad lov završi, popis se sam šalje voditelju lova (bez njegove e-pošte – administratoru).</div>
    <?php endif; ?>
    </div></details>
<p class="small text-muted mt-3 mb-0">Popis je samo popis sudionika – ne mijenja odgovore na poziv, bodove ni dezinfekciju.
    Iskaznice izdaje uprava (Članovi → član → 🪪 Iskaznica), a svaki član je ima i u aplikaciji (Moje → Moja iskaznica).</p>
<script id="gp-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/jsqr.min.js')) ?>"></script>
<script src="<?= e(asset('assets/grupni-popis.js')) ?>"></script>
<?php stranica('Popis sudionika', ob_get_clean());
