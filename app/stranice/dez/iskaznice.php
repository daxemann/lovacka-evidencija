<?php
/**
 * Mobilna stanica – upis skeniranjem članskih iskaznica (QR). Smiju samo ovlašteni (najviše 5 po aktivaciji, uključujući lovočuvara),
 * samo dok je stanica aktivna i samo kod stanice (GPS mobitela koji skenira). U knjizi piše tko je skenirao.
 * Bez signala: upis čeka na mobitelu i šalje se kad bude signala (označen „offline“, vrijeme mobitela).
 */
$k = korisnik();
$a = dez_aktivacija_po_id((int) ul('a', 0));
$st = $a ? dez_stanica((int) $a['StanicaId']) : null;
if (!$a || !$st || $st['Vrsta'] !== 'M') {
    nije_pronadjeno('Mobilna stanica');
}
if (!dez_smije_skenirati($a, $k)) {
    stranica('Skeniranje iskaznica', '<h1 class="h4">📷 Skeniranje iskaznica</h1><div class="alert alert-warning mb-0">'
        . ($a['Zatvoreno'] !== null || $a['Do'] < sada() ? 'Mobilna stanica više nije aktivna.' : 'Niste među ovlaštenima za skeniranje na ovoj stanici. Javite se lovočuvaru.')
        . '</div>');
}
$cfg = [
    'api' => url('api/dez-iskaznice', ['a' => $a['Id']]),
    'csrf' => csrf_token(),
    'a' => (int) $a['Id'],
    'imenik' => dez_skener_imenik($st),
    'upisi' => dez_skenirani_json($a),
];
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0 me-auto">📷 Skeniranje iskaznica</h1>
    <span id="di-mreza" class="badge rounded-pill text-bg-secondary">…</span>
</div>
<p class="small text-muted mb-2"><?= e($a['Naziv']) ?> · <?= e($st['Naziv']) ?> · do <?= e(date('H:i', strtotime($a['Do']))) ?><?= $a['Razlog'] ? ' · ' . e($a['Razlog']) : '' ?></p>

<div class="gp-raspored">
    <div>
        <div class="btn-group w-100 mb-2 di-smjer" role="group" aria-label="Smjer">
            <input type="radio" class="btn-check" name="di-smjer" id="di-d" value="D" checked><label class="btn btn-outline-success btn-lg" for="di-d">⬇ DOLAZAK</label>
            <input type="radio" class="btn-check" name="di-smjer" id="di-o" value="O"><label class="btn btn-outline-secondary btn-lg" for="di-o">⬆ ODLAZAK</label>
        </div>
        <label class="form-check small mb-2"><input type="checkbox" class="form-check-input" id="di-potvrda">
            <span class="form-check-label">Potvrđujem: dezinfekcija vozila, obuće i opreme provodi se na ovoj stanici.</span></label>
        <button type="button" class="btn btn-success btn-lg w-100 py-3 mb-2" id="di-start" disabled>📷 Skeniraj iskaznice</button>
        <div id="di-kamera" class="skener-kamera mb-2" hidden>
            <video id="di-video" playsinline muted></video>
            <div class="skener-okvir-ciljnik"></div>
            <button type="button" class="btn btn-light btn-sm skener-zatvori" id="di-stop">✕ Gotovo</button>
        </div>
        <div id="di-vozilo" class="card border-warning mb-2" hidden><div class="card-body p-2">
            <div class="small fw-semibold mb-1" id="di-vozilo-naslov">Kojim vozilom?</div>
            <div class="d-flex flex-wrap gap-2" id="di-vozilo-gumbi"></div>
        </div></div>
        <div id="di-zadnji" class="gp-zadnji mb-2" hidden></div>
        <div id="di-poruka" class="small text-center text-muted mb-2">Odaberite smjer, potvrdite dezinfekciju i pokrenite kameru.</div>
        <div id="di-gps" class="small text-center text-muted mb-2">📍 lokacija…</div>
        <div id="di-red" class="alert alert-warning small py-2" hidden></div>
        <div id="di-greske" class="small mb-2"></div>
    </div>
    <div>
        <h2 class="h6 mb-2">Upisano skeniranjem: <b id="di-broj">0</b> <span class="text-muted small" id="di-broj-detalj"></span></h2>
        <div id="di-popis" class="list-group small"></div>
    </div>
</div>
<p class="small text-muted mt-3 mb-0">Upis vrijedi samo kod stanice (provjerava se lokacija ovog mobitela). Jedno vozilo upisuje se samo, kod više vozila odaberite.
    Gosti bez iskaznice upisuju se preko člana koji ih je pozvao.</p>
<script id="di-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/jsqr.min.js')) ?>"></script>
<script src="<?= e(asset('assets/dez-iskaznice.js')) ?>"></script>
<?php stranica('Skeniranje iskaznica', ob_get_clean());
