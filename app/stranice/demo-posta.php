<?php
/** Demo verzija: e-pošta se ne šalje – ovdje se vidi kako bi izgledala (s privitcima). */
if (!je_demo()) {
    nije_pronadjeno();
}
$mailovi = demo_mailovi();
// privitak
if (isset($_GET['privitak'])) {
    $dat = basename((string) $_GET['privitak']);
    foreach ($mailovi as $m) {
        foreach ((array) $m['privitci'] as $p) {
            if ($p['dat'] === $dat && is_file(demo_posta_mapa() . '/' . $dat)) {
                $ime = (string) $p['ime'];
                $tip = str_ends_with(strtolower($ime), '.pdf') ? 'application/pdf' : (str_ends_with(strtolower($ime), '.csv') ? 'text/csv; charset=utf-8' : 'application/octet-stream');
                header('Content-Type: ' . $tip);
                header('Content-Disposition: ' . ($tip === 'application/pdf' ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $ime) . '"');
                readfile(demo_posta_mapa() . '/' . $dat);
                exit;
            }
        }
    }
    nije_pronadjeno('Privitak');
}
$id = (string) ($_GET['id'] ?? ($mailovi[0]['id'] ?? ''));
$m = null;
foreach ($mailovi as $x) {
    if ($x['id'] === $id) {
        $m = $x;
    }
}
ob_start(); ?>
<h1 class="h3 mb-1">📧 Demo – e-pošta</h1>
<p class="text-muted">U demo verziji e-pošta se <b>ne šalje</b>. Ovdje vidite točno ono što bi primatelj dobio (zadnjih <?= DEMO_MAX_POSTE ?> poruka svih posjetitelja demo verzije).</p>
<?php if (!$mailovi): ?>
    <div class="alert alert-light border">Još nema poruka. Isprobajte npr. <a href="<?= e(url('izvjestaji/akcije')) ?>">Izvještaj radnih akcija → Pošalji e-poštom</a>.</div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-4"><div class="list-group">
        <?php foreach ($mailovi as $x): ?>
            <a class="list-group-item list-group-item-action<?= $m && $x['id'] === $m['id'] ? ' active' : '' ?>" href="<?= e(url('demo-posta', ['id' => $x['id']])) ?>">
                <div class="fw-semibold text-truncate"><?= e($x['naslov']) ?></div>
                <div class="small text-truncate">→ <?= e(implode(', ', $x['prima'])) ?></div>
                <div class="small opacity-75"><?= e(date('d.m. H:i', strtotime($x['vrijeme']))) ?><?= $x['privitci'] ? ' · 📎 ' . count($x['privitci']) : '' ?></div>
            </a>
        <?php endforeach; ?>
    </div></div>
    <div class="col-lg-8">
        <?php if ($m): ?>
        <div class="card"><div class="card-body">
            <div class="small text-muted">Prima: <b class="text-body"><?= e(implode(', ', $m['prima'])) ?></b> · <?= e(date('d.m.Y. H:i', strtotime($m['vrijeme']))) ?><?= $m['tko'] ? ' · poslao ' . e($m['tko']) : '' ?></div>
            <h2 class="h5 my-2"><?= e($m['naslov']) ?></h2>
            <?php if ($m['privitci']): ?><div class="mb-2"><?php foreach ($m['privitci'] as $p): ?>
                <a class="btn btn-sm btn-outline-secondary me-1 mb-1" target="_blank" href="<?= e(url('demo-posta', ['privitak' => $p['dat']])) ?>">📎 <?= e($p['ime']) ?> <span class="text-muted">(<?= max(1, (int) round($p['vel'] / 1024)) ?> KB)</span></a>
            <?php endforeach; ?></div><?php endif; ?>
            <?php if ($m['html']): ?>
                <iframe class="demo-mail-tijelo" sandbox="" srcdoc="<?= e($m['tijelo']) ?>"></iframe>
            <?php else: ?>
                <pre class="demo-mail-tijelo p-3 mb-0" style="white-space:pre-wrap"><?= e($m['tijelo']) ?></pre>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php stranica('Demo – e-pošta', ob_get_clean());
