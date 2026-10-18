<?php
/**
 * Grupni lov – popis sudionika: lovnik skenira članske iskaznice (QR) i popis se slaže sam.
 * Skeniranje radi i bez signala dok je stranica otvorena (upisi čekaju na mobitelu i šalju se kad bude signala).
 * Popis je samo popis – ne utječe na poziv, bodove, dezinfekciju ni ništa drugo.
 */
$lov = gl_lov((int) ul('id', 0));
if (!$lov) {
    nije_pronadjeno('Grupni lov');
}
if (!gl_moze_upravljati($lov)) {
    zabranjeno();
}
if (($_GET['pdf'] ?? '') === '1') {
    $ime = (string) preg_replace('/[^a-z0-9\-]+/', '-', 'popis-' . kljuc($lov['Naziv']) . '-' . substr($lov['Pocetak'], 0, 10));
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . trim($ime, '-') . '.pdf"');
    echo gl_popis_pdf($lov);
    exit;
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
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-lov', ['id' => $lov['Id']])) ?>">← <?= $aktivan ? 'Karta lova' : 'Grupni lov' ?></a>
    <h1 class="h4 mb-0 me-auto">📋 Popis sudionika</h1>
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
                <div class="d-flex gap-2">
                    <input class="form-control form-control-sm" id="gp-gost" maxlength="80" placeholder="Ime i prezime gosta" autocomplete="off">
                    <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="gp-gost-dodaj">Dodaj gosta</button>
                </div>
                <div class="form-text">Ručni upis treba signal.</div>
            </div>
        </details>
    </div>
    <div>
        <div class="d-flex align-items-center gap-2 mb-2">
            <h2 class="h6 mb-0 me-auto">Na popisu: <b id="gp-broj">0</b> <span class="text-muted small" id="gp-broj-detalj"></span></h2>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-popis', ['id' => $lov['Id'], 'pdf' => 1])) ?>" target="_blank">📄 PDF</a>
        </div>
        <div id="gp-popis" class="list-group small"></div>
    </div>
</div>
<p class="small text-muted mt-3 mb-0">Popis je samo popis sudionika – ne mijenja odgovore na poziv, bodove ni dezinfekciju.
    Iskaznice izdaje uprava (Članovi → član → 🪪 Iskaznica), a svaki član je ima i u aplikaciji (Moje → Moja iskaznica).</p>
<script id="gp-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/jsqr.min.js')) ?>"></script>
<script src="<?= e(asset('assets/grupni-popis.js')) ?>"></script>
<?php stranica('Popis sudionika', ob_get_clean());
