<?php
/** Početna stranica člana (mobitel): termini, radne akcije, oglasnik, članarina u jednoj liniji. */
$k = korisnik();
$cid = $k['ClanId'];
$termini = dogadjaji(danas(), null, null, 3);
$mojiOdg = [];
if ($cid && $termini) {
    foreach (redovi('SELECT DogadjajId, Dolazi FROM DogadjajOdgovori WHERE ClanId=?', [$cid]) as $o) {
        $mojiOdg[(int) $o['DogadjajId']] = (int) $o['Dolazi'];
    }
}
$oglasi = redovi('SELECT o.*, (SELECT Datoteka FROM OglasSlike s WHERE s.OglasId=o.Id ORDER BY s.Redoslijed, s.Id LIMIT 1) AS Slika FROM Oglasi o WHERE o.Status IN (0,1) ORDER BY o.Kreirano DESC LIMIT 3');
if ($cid) {
    $lg = pocetak_lovne_godine();
    $bodovi = (float) vrijednost('SELECT COALESCE(SUM(CAST(Bodovi AS REAL)),0) FROM RadneAkcije WHERE ClanId=? AND Status=1 AND substr(Datum,1,10) BETWEEN ? AND ?', [$cid, $lg->format('Y-m-d'), $lg->modify('+1 year -1 day')->format('Y-m-d')]);
    $ceka = (int) vrijednost('SELECT COUNT(*) FROM RadneAkcije WHERE ClanId=? AND Status=0', [$cid]);
    $zadnje = redovi('SELECT a.*, v.Naziv AS Vrsta FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.ClanId=? ORDER BY a.Datum DESC, a.Id DESC LIMIT 3', [$cid]);
}
ob_start(); ?>
<h1 class="h4 mb-3">Pozdrav, <?= e(explode(' ', (string) $k['Naziv'])[0]) ?></h1>

<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">📅 Nadolazeći termini</b><a class="small" href="<?= e(url('kalendar')) ?>">kalendar →</a></div>
    <div class="list-group list-group-flush">
    <?php foreach ($termini as $d): $v = vrsta_dogadjaja($d['Vrsta']); ?>
        <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="<?= e(url('kalendar/termin', ['id' => $d['Id']])) ?>" style="border-left:4px solid <?= e($v['boja']) ?>">
            <div class="text-center text-nowrap" style="min-width:3.2rem"><div class="fw-bold lh-1"><?= date('d.m.', strtotime($d['Pocetak'])) ?></div><div class="small text-muted"><?= $d['CijeliDan'] ? '' : date('H:i', strtotime($d['Pocetak'])) ?></div></div>
            <div class="flex-grow-1 overflow-hidden"><div class="text-truncate fw-semibold"><?= e($d['Naslov']) ?></div>
                <div class="small text-muted text-truncate"><?= e($d['Vrsta']) ?><?= $d['Mjesto'] ? ' · ' . e($d['Mjesto']) : '' ?></div></div>
            <?php if (isset($mojiOdg[(int) $d['Id']])): ?><span class="badge <?= $mojiOdg[(int) $d['Id']] ? 'bg-success' : 'bg-secondary' ?>"><?= $mojiOdg[(int) $d['Id']] ? '✔' : '✖' ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <?php if (!$termini): ?><div class="list-group-item small text-muted">Nema najavljenih termina.</div><?php endif; ?>
    </div></div>

<?php if ($cid): ?>
<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">🪓 Moje radne akcije</b><a class="small" href="<?= e(url('moje-akcije')) ?>">sve →</a></div>
    <div class="card-body pb-2">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div><div class="kartica-broj lh-1"><?= e(broj($bodovi, 2)) ?></div><div class="small text-muted">bodova u lovnoj godini<?= $ceka ? " · $ceka čeka odobrenje" : '' ?></div></div>
            <a class="btn btn-primary ms-auto" href="<?= e(url('moje-akcije')) ?>#nova">+ Prijavi akciju</a>
        </div>
        <?php foreach ($zadnje as $a): ?>
            <div class="small d-flex border-top py-1"><span class="text-muted me-2"><?= e(date('d.m.', strtotime($a['Datum']))) ?></span>
                <span class="flex-grow-1 text-truncate"><?= e($a['Vrsta'] ?? $a['VrstaSlobodno']) ?></span><span><?= status_akcije_oznaka((int) $a['Status']) ?></span></div>
        <?php endforeach; ?>
    </div></div>
<?php endif; ?>

<div class="card mb-3"><div class="card-header d-flex align-items-center"><b class="me-auto">🛒 Oglasnik</b><a class="small" href="<?= e(url('oglasnik')) ?>">svi oglasi →</a></div>
    <div class="list-group list-group-flush">
    <?php foreach ($oglasi as $o): ?>
        <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="<?= e(url('oglasnik/oglas', ['id' => $o['Id']])) ?>">
            <div class="oglas-mini-slika"><?php if ($o['Slika']): ?><img src="<?= e(foto_url($o['Slika'])) ?>" alt="" loading="lazy"><?php endif; ?></div>
            <div class="flex-grow-1 overflow-hidden"><div class="text-truncate"><?= e($o['Naslov']) ?></div><div class="small text-muted"><?= e(cijena_oglasa($o)) ?></div></div>
        </a>
    <?php endforeach; ?>
    <?php if (!$oglasi): ?><div class="list-group-item small text-muted">Trenutno nema oglasa.</div><?php endif; ?>
    </div></div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('imenik')) ?>">📇 Imenik</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('poruke')) ?>">💬 Poruke</a>
    <?php if ($cid): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('moj-profil')) ?>">👤 Moj profil</a><?php endif; ?>
</div>

<?php if ($cid) echo predlozak('moja-clanarina', ['clanId' => $cid]); ?>
<?php stranica('Početna', ob_get_clean());
