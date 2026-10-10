<?php
/** Karta lovišta: lovne naprave, zauzimanje („sjedim ovdje“), uređivanje naprava. */
$k = korisnik();
$sekcije = revir_sekcije();
if (!$sekcije) {
    stranica('Karta lovišta', '<h1 class="h3">Karta lovišta</h1><div class="alert alert-secondary">Vaš korisnički račun nije povezan s članom i nema ulogu sa sekcijom – karta nije dostupna.</div>');
}
revir_zatvori_istekle();
$moja = revir_moja_sekcija();
$uredive = [];
foreach ($sekcije as $id => $naziv) {
    if (revir_moze_urediti($id === 0 ? null : $id)) {
        $uredive[$id] = $naziv;
    }
}
$zadana = ($moja !== false && array_key_exists(revir_sid($moja), $sekcije)) ? revir_sid($moja) : array_key_first($sekcije);
$cfg = [
    'api' => url('api/revir'),
    'csrf' => csrf_token(),
    'sekcije' => array_map(fn($id, $n) => ['id' => $id, 'naziv' => $n], array_keys($sekcije), $sekcije),
    'zadana' => count($sekcije) > 1 ? $zadana : null,
    'uredive' => array_map(fn($id, $n) => ['id' => $id, 'naziv' => $n], array_keys($uredive), $uredive),
    'granice' => revir_granice(),
    'centar' => revir_centar(),
    'vrste' => REVIR_VRSTE,
    'clan' => $k['ClanId'] !== null,
    'istek' => sprintf('%02d:00', revir_sat_isteka()),
    'dnevnik' => url('revir/dnevnik'),
    'danas' => url('revir/danas'),
    'odabir' => (string) ul('naprava', ''),
];
ob_start(); ?>
<link rel="stylesheet" href="<?= e(asset('assets/leaflet/leaflet.css')) ?>">
<div class="revir-glava d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h4 mb-0 me-auto">Karta lovišta</h1>
    <?php if (count($sekcije) > 1): ?>
        <select id="revir-sekcija" class="form-select form-select-sm w-auto" aria-label="Sekcija">
            <option value="sve">Sve sekcije</option>
            <?php foreach ($sekcije as $id => $n): ?><option value="<?= (int) $id ?>"<?= sel($id, $zadana) ?>><?= e($n) ?></option><?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if ($uredive): ?><button type="button" class="btn btn-sm btn-outline-secondary" id="revir-uredi" aria-pressed="false">✏️ Uredi naprave</button><?php endif; ?>
</div>
<div id="revir-moje" class="revir-moje alert alert-danger py-2 px-3 mb-2 d-flex align-items-center gap-2" hidden></div>
<div id="revir-uredi-traka" class="alert alert-warning py-2 px-3 mb-2 small" hidden>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <b class="me-auto">Uređivanje: dodirnite kartu za novu napravu ili povucite postojeću.</b>
        <button type="button" class="btn btn-sm btn-success" id="revir-ovdje">📍 Nova na mojoj poziciji</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir/postavke')) ?>">Uvoz fotografija / granice</a>
    </div>
    <div id="revir-bez-polozaja" class="mt-2" hidden></div>
</div>
<div class="revir-okvir">
    <div id="revir-karta" class="revir-karta"></div>
    <div class="revir-legenda small">
        <span><i class="revir-tocka slobodno"></i> slobodno</span>
        <span><i class="revir-tocka zauzeto"></i> zauzeto</span>
        <span><i class="revir-tocka moje"></i> moje</span>
    </div>
    <button type="button" class="revir-lociraj" id="revir-lociraj" title="Gdje sam?" aria-label="Gdje sam?">◎</button>
    <div id="revir-panel" class="revir-panel" hidden></div>
</div>
<p class="small text-muted mt-2 mb-0">Zauzeće vrijedi dok ne dodirnete „Odlazim“, najkasnije do <?= e($cfg['istek']) ?> sljedećeg jutra. Gost sjedi sam na drugoj napravi koju zauzmete za njega.
    <a href="<?= e(url('revir/dnevnik')) ?>">Moj lovački dnevnik</a></p>
<script id="revir-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(asset('assets/revir-foto.js')) ?>"></script>
<script src="<?= e(asset('assets/revir.js')) ?>"></script>
<?php stranica('Karta lovišta', ob_get_clean());
