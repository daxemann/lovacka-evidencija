<?php
/** Donja traka prečaca na početnoj: Imenik · Iskaznica · Poruke · Moj profil. */
$k = korisnik();
$cid = $k['ClanId'];
$neproc = broj_neprocitanih((int) $k['Id']);
?>
<nav class="clan-precaci mb-3" aria-label="Prečaci">
    <a href="<?= e(url('imenik')) ?>"><span class="ikona">📇</span>Imenik</a>
    <?php if (modul('iskaznice') && $cid): ?><a href="<?= e(url('moja-iskaznica')) ?>"><span class="ikona">🪪</span>Iskaznica</a><?php endif; ?>
    <a href="<?= e(url('poruke')) ?>"><span class="ikona">💬</span>Poruke<?php if ($neproc): ?><span class="badge rounded-pill bg-danger"><?= $neproc ?></span><?php endif; ?></a>
    <?php if ($cid): ?><a href="<?= e(url('moj-profil')) ?>"><span class="ikona">👤</span>Moj profil</a><?php endif; ?>
</nav>
