<?php
/** Popis razgovora (oglasnik). */
$k = korisnik();
$lista = redovi('SELECT r.*, o.Naslov, o.KorisnikId AS ProdavateljId,
        (SELECT Datoteka FROM OglasSlike s WHERE s.OglasId=o.Id ORDER BY s.Redoslijed, s.Id LIMIT 1) AS Slika,
        (SELECT Tekst FROM PorukeRazgovora p WHERE p.RazgovorId=r.Id ORDER BY p.Id DESC LIMIT 1) AS Zadnja,
        (SELECT COUNT(*) FROM PorukeRazgovora p WHERE p.RazgovorId=r.Id AND p.PosiljateljId<>? AND p.Procitano=0) AS Novih
    FROM Razgovori r JOIN Oglasi o ON o.Id=r.OglasId WHERE r.KupacId=? OR o.KorisnikId=? ORDER BY r.ZadnjaPoruka DESC', [$k['Id'], $k['Id'], $k['Id']]);
ob_start(); ?>
<h1 class="h4 mb-3">Poruke (oglasnik)</h1>
<div class="list-group">
<?php foreach ($lista as $r): $jaProdajem = (int) $r['ProdavateljId'] === $k['Id']; ?>
    <a href="<?= e(url('poruke/razgovor', ['id' => $r['Id']])) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 <?= $r['Novih'] ? 'fw-semibold' : '' ?>">
        <div class="oglas-mini-slika"><?php if ($r['Slika']): ?><img src="<?= e(foto_url($r['Slika'])) ?>" alt=""><?php endif; ?></div>
        <div class="flex-grow-1 overflow-hidden">
            <div class="text-truncate"><?= e($r['Naslov']) ?></div>
            <div class="small text-muted text-truncate"><?= $jaProdajem ? 'Kupac' : 'Prodavatelj' ?>: <?= e(ime_korisnika($jaProdajem ? (int) $r['KupacId'] : (int) $r['ProdavateljId'])) ?> · <?= e(mb_strimwidth((string) $r['Zadnja'], 0, 80, '…')) ?></div>
        </div>
        <div class="text-end"><div class="small text-muted"><?= e(date('d.m. H:i', strtotime($r['ZadnjaPoruka']))) ?></div>
            <?php if ($r['Novih']): ?><span class="badge bg-danger"><?= (int) $r['Novih'] ?></span><?php endif; ?></div>
    </a>
<?php endforeach; ?>
</div>
<?php if (!$lista): ?><div class="text-muted py-4 text-center">Još nemate poruka. Pišite prodavatelju iz <a href="<?= e(url('oglasnik')) ?>">oglasnika</a>.</div><?php endif; ?>
<?php stranica('Poruke', ob_get_clean());
