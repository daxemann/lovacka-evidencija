<?php
/** Veliki gumbi za lov na vrhu početne – isti za lovce i za sve s pravima (osim glavnog admina). */
$dez = modul('dezinfekcija');
$revir = modul('revir') && revir_sekcije();
$skeniranje = $dez ? dez_moje_skeniranje() : [];
?>
<?php foreach ($skeniranje as $sa): ?>
<a class="lov-gumb lov-gumb-qr lov-gumb-sken mb-3" href="<?= e(url('dez/iskaznice', ['a' => $sa['Id']])) ?>"><span class="lov-gumb-ikona">🪪</span><span><b>Skeniraj iskaznice</b><small><?= e($sa['Naziv']) ?> · mobilna stanica do <?= e(date('H:i', strtotime($sa['Do']))) ?></small></span></a>
<?php endforeach; ?>
<?php if ($dez || $revir): ?>
<div class="lov-gumbi mb-3">
    <?php if ($dez): ?>
    <a class="lov-gumb lov-gumb-qr" href="<?= e(url('skeniraj')) ?>"><span class="lov-gumb-ikona">📷</span><span><b>Skeniraj QR</b><small>Dezinfekcija – dolazak / odlazak</small></span></a>
    <?php endif; ?>
    <?php if ($revir): ?>
    <a class="lov-gumb" href="<?= e(url('revir')) ?>"><span class="lov-gumb-ikona">🗺️</span><span><b>Karta lovišta</b><small>sjedim ovdje · kvarovi</small></span></a>
    <a class="lov-gumb" href="<?= e(url('revir/danas')) ?>"><span class="lov-gumb-ikona">🌲</span><span><b>Danas u lovištu</b><small>tko gdje sjedi</small></span></a>
    <?php endif; ?>
</div>
<?php endif; ?>
