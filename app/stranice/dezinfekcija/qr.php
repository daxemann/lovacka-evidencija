<?php
/** QR oznaka za ispis (A4): stanica ili pristup za inspekciju. */
trazi(P_DEZ_POSTAVKE);
if (!empty($_GET['inspekcija'])) {
    $adresa = dez_insp_url();
    $naslov = 'INSPEKCIJA';
    $podnaslov = 'Evidencija dezinfekcije vozila, obuće i opreme';
    $upute = ['Skenirajte QR kod kamerom mobitela.', 'Upišite lozinku koju daje lovočuvar.', 'Pregled, ispis ili slanje evidencije na vašu e-mail adresu.'];
} else {
    $st = dez_stanica((int) ($_GET['id'] ?? 0));
    if (!$st || !moze_sekciju($st['SekcijaId'] !== null ? (int) $st['SekcijaId'] : null)) {
        nije_pronadjeno('Stanica');
    }
    $adresa = dez_qr_url($st);
    $naslov = 'DEZINFEKCIJA';
    $podnaslov = $st['Naziv'] . ($st['SekcijaNaziv'] && !str_contains($st['Naziv'], $st['SekcijaNaziv']) ? ' · ' . $st['SekcijaNaziv'] : '');
    $upute = ['Dezinficirajte vozilo, obuću i opremu.', 'Skenirajte QR kod kamerom mobitela (prvi put se prijavite).', 'Odaberite DOLAZAK ili ODLAZAK i dodirnite POTVRDI.',
        'Gosti i suputnici se upisuju zajedno s vama.'];
}
?><!doctype html>
<html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($naslov) ?> – QR</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    body { font-family: system-ui, Arial, sans-serif; color: #1c2b16; margin: 0; }
    .list { max-width: 180mm; margin: 0 auto; text-align: center; padding: 6mm 0; }
    .udruga { font-size: 15pt; font-weight: 600; } .logo { max-height: 26mm; max-width: 60mm; }
    h1 { font-size: 46pt; letter-spacing: .04em; margin: 4mm 0 0; color: #2f5d23; }
    .pod { font-size: 17pt; margin-bottom: 6mm; }
    #qr svg { width: 120mm; height: 120mm; }
    ol { text-align: left; font-size: 15pt; line-height: 1.5; max-width: 150mm; margin: 6mm auto; }
    .adr { font-size: 8pt; color: #666; word-break: break-all; }
    .alati { text-align: center; padding: 1rem; } @media print { .alati { display: none; } }
    button { font-size: 1rem; padding: .5rem 1.2rem; }
</style></head>
<body>
<div class="alati"><button onclick="window.print()">🖨 Ispiši</button> <button onclick="history.length > 1 ? history.back() : window.close()">Zatvori</button>
    <div style="font-size:.85rem;color:#666;margin-top:.5rem">Savjet: isprintajte, plastificirajte (ili u foliju) i pričvrstite na stanicu.</div></div>
<div class="list">
    <?php if (ima_logo()): ?><img class="logo" src="<?= e(url('logo')) ?>" alt=""><?php endif; ?>
    <div class="udruga"><?= e(udruga_naziv()) ?></div>
    <h1><?= e($naslov) ?></h1>
    <div class="pod"><?= e($podnaslov) ?></div>
    <div id="qr"></div>
    <ol><?php foreach ($upute as $u): ?><li><?= e($u) ?></li><?php endforeach; ?></ol>
    <div class="adr"><?= e($adresa) ?></div>
</div>
<script src="<?= e(asset('assets/qrcode.js')) ?>"></script>
<script>
    var q = qrcode(0, 'M'); q.addData(<?= json_encode($adresa) ?>); q.make();
    document.getElementById('qr').innerHTML = q.createSvgTag({ cellSize: 8, margin: 2, scalable: true });
</script>
</body></html>
<?php exit;
