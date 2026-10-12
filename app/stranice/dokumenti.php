<?php
/** Statut i pravilnici – pregled za sve članove (samo čitanje). */
$admin = ima(P_SUSTAV);
$id = (int) ($_GET['id'] ?? 0);

if ($id) {
    $dok = red('SELECT * FROM Dokumenti WHERE Id=?', [$id]);
    if (!$dok || (!$dok['Objavljeno'] && !$admin)) {
        nije_pronadjeno('Dokument');
    }
    $datoteke = dok_datoteke($id);
    $pdfovi = array_values(array_filter($datoteke, fn($d) => $d['Vrsta'] === 'pdf'));
    $slike = array_values(array_filter($datoteke, fn($d) => $d['Vrsta'] === 'slika'));
    ob_start(); ?>
<p class="mb-2 d-print-none"><a href="<?= e(url('dokumenti')) ?>">← Statut i pravilnici</a></p>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-1">
    <h1 class="h3 mb-0"><?= e($dok['Naslov']) ?></h1>
    <?php if ($admin): ?><a class="btn btn-outline-secondary btn-sm d-print-none" href="<?= e(url('sustav/dokumenti', ['id' => $id])) ?>">Uredi</a><?php endif; ?>
</div>
<p class="text-muted small"><?= $dok['Opis'] ? e($dok['Opis']) . ' · ' : '' ?>izmijenjeno <?= e(datum_vrijeme($dok['Azurirano'])) ?>
    <?php if (!$dok['Objavljeno']): ?><span class="badge bg-secondary">skriveno – članovi ga ne vide</span><?php endif; ?></p>

<?php foreach ($pdfovi as $d): ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-danger">PDF</span><span class="flex-grow-1"><?= e($d['Naziv']) ?></span>
            <a class="btn btn-sm btn-primary" href="<?= e(dok_datoteka_url($d)) ?>" target="_blank" rel="noopener">Otvori</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(dok_datoteka_url($d, true)) ?>">Preuzmi</a>
        </div>
        <object data="<?= e(dok_datoteka_url($d)) ?>" type="application/pdf" class="d-none d-md-block w-100" style="height:80vh">
            <div class="p-3 small text-muted">Pregled PDF-a nije moguć u ovom pregledniku – kliknite „Otvori“.</div>
        </object>
    </div>
<?php endforeach; ?>

<?php if ($dok['Tekst']): ?>
    <div class="card mb-3"><div class="card-body dok-tekst" style="max-width:900px;line-height:1.6"><?= dok_tekst_html($dok['Tekst']) ?></div></div>
<?php endif; ?>

<?php foreach ($slike as $n => $d): ?>
    <figure class="mb-3 text-center">
        <a href="<?= e(dok_datoteka_url($d)) ?>" target="_blank" rel="noopener">
            <img src="<?= e(dok_datoteka_url($d)) ?>" alt="<?= e($d['Naziv']) ?>" class="img-fluid border rounded shadow-sm" loading="lazy" style="max-height:none">
        </a>
        <?php if (count($slike) > 1): ?><figcaption class="small text-muted mt-1">Stranica <?= $n + 1 ?> / <?= count($slike) ?></figcaption><?php endif; ?>
    </figure>
<?php endforeach; ?>

<?php if (!$datoteke && !$dok['Tekst']): ?>
    <div class="alert alert-info">Dokument još nema sadržaja.</div>
<?php endif; ?>
<?php
    stranica($dok['Naslov'], ob_get_clean());
}

$svi = redovi('SELECT d.*, (SELECT COUNT(*) FROM DokumentDatoteke f WHERE f.DokumentId=d.Id AND f.Vrsta=\'pdf\') AS Pdf,
        (SELECT COUNT(*) FROM DokumentDatoteke f WHERE f.DokumentId=d.Id AND f.Vrsta=\'slika\') AS Slike
    FROM Dokumenti d ' . ($admin ? '' : 'WHERE Objavljeno=1 ') . 'ORDER BY Redoslijed, Id');
ob_start(); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">📜 Statut i pravilnici</h1>
    <?php if ($admin): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('sustav/dokumenti')) ?>">Uredi dokumente</a><?php endif; ?>
</div>
<?php if (!$svi): ?>
    <div class="alert alert-info">Udruga još nije objavila dokumente.</div>
<?php else: ?>
<div class="list-group">
<?php foreach ($svi as $d): ?>
    <a class="list-group-item list-group-item-action py-3" href="<?= e(url('dokumenti', ['id' => $d['Id']])) ?>">
        <div class="fw-semibold"><?= e($d['Naslov']) ?>
            <?php if (!$d['Objavljeno']): ?><span class="badge bg-secondary ms-1">skriveno</span><?php endif; ?></div>
        <div class="small text-muted">
            <?= $d['Opis'] ? e($d['Opis']) . ' · ' : '' ?>
            <?= $d['Pdf'] ? '<span class="badge bg-danger">PDF</span> ' : '' ?><?= $d['Slike'] ? '<span class="badge bg-secondary">' . (int) $d['Slike'] . ' sl.</span> ' : '' ?><?= $d['Tekst'] ? '<span class="badge bg-light text-dark border">tekst</span> ' : '' ?>
            · <?= e(datum_vrijeme($d['Azurirano'])) ?>
        </div>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php stranica('Statut i pravilnici', ob_get_clean());
