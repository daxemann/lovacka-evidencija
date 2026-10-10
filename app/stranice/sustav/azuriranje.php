<?php
/** Ažuriranje programa s GitHuba – samo glavni admin (Sustav + sve sekcije). */
if (!smije_azurirati()) {
    zabranjeno();
}
$addon = azuriranje_addon();
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'provjeri') {
        $i = azuriranje_izdanje(true);
        poruka($i ? e('Zadnje izdanje na GitHubu: ' . $i['verzija'] . (version_compare($i['verzija'], VERZIJA, '>') ? ' – dostupno ažuriranje.' : ' – program je ažuran.'))
            : 'GitHub trenutno nije dostupan.', $i ? 'info' : 'warning');
    } elseif ($radnja === 'azuriraj') {
        $i = azuriranje_izdanje();
        if (!$i || !version_compare($i['verzija'], VERZIJA, '>') || ($_POST['verzija'] ?? '') !== $i['verzija']) {
            poruka('Nema novije verzije za ažuriranje.', 'warning');
        } else {
            try {
                poruka(e($addon ? azuriraj_addon($i) : azuriraj_program($i)), 'success');
            } catch (Throwable $e) {
                poruka('Ažuriranje nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    }
    preusmjeri('sustav/azuriranje');
}
$izd = azuriranje_izdanje();
$novo = $izd && version_compare($izd['verzija'], VERZIJA, '>');
$prepreke = $addon ? (addon_supervisor_dostupan() ? [] : ['Add-on nema pristup Home Assistant Supervisoru – ažurirajte ga ručno (Postavke → Dodaci) ili pričekajte automatsko ažuriranje.']) : azuriranje_preduvjeti();
$provjera = is_file(podaci('azuriranje.json')) ? (int) (json_decode((string) file_get_contents(podaci('azuriranje.json')), true)['provjereno'] ?? 0) : 0;
ob_start(); ?>
<h1 class="h3 mb-1">Ažuriranje programa</h1>
<p class="text-muted">Instalirana verzija: <b><?= e(VERZIJA) ?></b> · Izvor: <a href="https://github.com/<?= e(azuriranje_repo()) ?>/releases" target="_blank" rel="noopener">github.com/<?= e(azuriranje_repo()) ?></a>
    <?php if ($provjera): ?>· provjereno <?= e(date('d.m.Y. H:i', $provjera)) ?><?php endif; ?></p>

<?php if ($novo): ?>
<div class="card border-success mb-3" style="max-width:820px">
    <div class="card-header bg-success-subtle"><b>Nova verzija <?= e($izd['verzija']) ?></b> <span class="text-muted small">· <?= e($izd['naziv']) ?><?= $izd['objavljeno'] ? ' · ' . e(date('d.m.Y.', strtotime($izd['objavljeno']))) : '' ?></span></div>
    <div class="card-body">
        <ol class="small mb-3">
            <li>automatska <b>sigurnosna kopija baze</b> (Sustav → Sigurnosne kopije)<?= $addon ? '' : ' i kopija trenutnog programa' ?></li>
            <li><?= $addon ? 'Home Assistant preuzima i instalira novu verziju add-ona (1–3 minute, program se ponovno pokreće)' : 'preuzimanje nove verzije s GitHuba i zamjena programskih datoteka' ?></li>
            <li>podaci (članovi, fotografije, postavke, <code>config.php</code>) ostaju; baza se sama proširuje</li>
        </ol>
        <?php if ($prepreke): ?>
            <div class="alert alert-warning small mb-0"><?= implode('<br>', array_map('e', $prepreke)) ?></div>
        <?php else: ?>
            <form method="post" action="<?= e(url('sustav/azuriranje')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="azuriraj"><input type="hidden" name="verzija" value="<?= e($izd['verzija']) ?>">
                <button class="btn btn-success" data-potvrda="Ažurirati program na verziju <?= e($izd['verzija']) ?>? Prije toga se radi sigurnosna kopija.">⬆ Ažuriraj na <?= e($izd['verzija']) ?></button></form>
        <?php endif; ?>
        <details class="mt-3"><summary class="small">Što je novo</summary><div class="small mt-2"><?= azuriranje_biljeske_html($izd['biljeske']) ?></div></details>
    </div>
</div>
<?php elseif ($izd): ?>
    <div class="alert alert-success" style="max-width:820px">Program je ažuran (zadnje izdanje: <?= e($izd['verzija']) ?>).</div>
<?php else: ?>
    <div class="alert alert-secondary" style="max-width:820px">Podaci o izdanjima još nisu dohvaćeni ili GitHub nije dostupan.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('sustav/azuriranje')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="provjeri"><button class="btn btn-outline-secondary btn-sm">Provjeri sada</button></form>
<p class="small text-muted mt-3" style="max-width:820px">Program provjerava GitHub najviše svakih <?= AZURIRANJE_PROVJERA_SATI ?> sati, samo kad je prijavljen glavni admin. Ako nešto pođe po zlu:
    stari program se automatski vraća, a kopija baze je u Sustav → Sigurnosne kopije. Isključivanje: <code>'azuriranje' => false</code> u <code>config.php</code>.</p>
<?php stranica('Ažuriranje programa', ob_get_clean());
