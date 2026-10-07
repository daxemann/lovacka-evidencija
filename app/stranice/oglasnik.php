<?php
/** Oglasnik – popis oglasa. */
$k = korisnik();
$kat = isset($_GET['kat']) && $_GET['kat'] !== '' ? (int) $_GET['kat'] : null;
$moji = ($_GET['moji'] ?? '') === '1';
$prodani = ($_GET['prodani'] ?? '') === '1';
$trazi = trim((string) ($_GET['trazi'] ?? ''));
$svi = redovi('SELECT o.*, (SELECT Datoteka FROM OglasSlike s WHERE s.OglasId=o.Id ORDER BY s.Redoslijed, s.Id LIMIT 1) AS Slika FROM Oglasi o WHERE o.Status<>3 ORDER BY o.Status=2, o.Kreirano DESC');
$aktivni = array_filter($svi, fn($o) => (int) $o['Status'] !== 2);
$prikaz = array_filter($moji ? array_filter($svi, fn($o) => (int) $o['KorisnikId'] === $k['Id']) : ($prodani ? $svi : $aktivni), function ($o) use ($kat, $trazi) {
    if ($kat !== null && (int) $o['Kategorija'] !== $kat) {
        return false;
    }
    if ($trazi !== '') {
        $t = kljuc($o['Naslov'] . ' ' . $o['Opis'] . ' ' . $o['Mjesto']);
        foreach (preg_split('/\s+/', kljuc($trazi)) as $r) {
            if (!str_contains($t, $r)) {
                return false;
            }
        }
    }
    return true;
});
$neprocitano = [];
foreach (redovi('SELECT r.OglasId, COUNT(*) AS n FROM PorukeRazgovora p JOIN Razgovori r ON r.Id=p.RazgovorId JOIN Oglasi o ON o.Id=r.OglasId
                 WHERE o.KorisnikId=? AND p.PosiljateljId<>? AND p.Procitano=0 GROUP BY r.OglasId', [$k['Id'], $k['Id']]) as $x) {
    $neprocitano[(int) $x['OglasId']] = (int) $x['n'];
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><h1 class="h3 mb-0 me-auto">Oglasnik</h1>
    <a href="<?= e(url('oglasnik/uredi')) ?>" class="btn btn-primary">+ Novi oglas</a></div>
<div class="d-flex flex-wrap gap-2 mb-3 oglas-kategorije">
    <a class="btn btn-sm <?= $kat === null && !$moji ? 'btn-success' : 'btn-outline-success' ?>" href="<?= e(url('oglasnik')) ?>">Sve <span class="badge bg-light text-dark"><?= count($aktivni) ?></span></a>
    <?php foreach (KATEGORIJE_OGLASA as $i => $naziv): $n = count(array_filter($aktivni, fn($o) => (int) $o['Kategorija'] === $i)); ?>
        <a class="btn btn-sm <?= $kat === $i ? 'btn-success' : 'btn-outline-success' ?>" href="<?= e(url('oglasnik', ['kat' => $i])) ?>"><?= e($naziv) ?> <span class="badge bg-light text-dark"><?= $n ?></span></a>
    <?php endforeach; ?>
    <a class="btn btn-sm <?= $moji ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="<?= e(url('oglasnik', ['moji' => 1])) ?>">Moji oglasi</a>
</div>
<form method="get" action="<?= e(url()) ?>" class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <input type="hidden" name="p" value="oglasnik"><?php if ($kat !== null): ?><input type="hidden" name="kat" value="<?= $kat ?>"><?php endif; ?>
    <?php if ($moji): ?><input type="hidden" name="moji" value="1"><?php endif; ?>
    <input name="trazi" class="form-control" style="max-width:280px" placeholder="Traži…" value="<?= e($trazi) ?>">
    <?php if (!$moji): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="prodani" value="1" id="prodani"<?= chk($prodani) ?> data-auto>
        <label class="form-check-label small" for="prodani">prikaži i prodane</label></div><?php endif; ?>
</form>
<?php if (!$prikaz): ?>
    <div class="text-muted py-4 text-center">Nema oglasa. <?php if ($moji || !$svi): ?><a href="<?= e(url('oglasnik/uredi')) ?>">Objavite prvi oglas.</a><?php endif; ?></div>
<?php endif; ?>
<div class="row g-3">
<?php foreach ($prikaz as $o): $st = (int) $o['Status']; ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= e(url('oglasnik/oglas', ['id' => $o['Id']])) ?>" class="card h-100 text-decoration-none text-reset oglas-kartica <?= $st ? 'oglas-neaktivan' : '' ?>">
            <div class="oglas-slika">
                <?php if ($o['Slika']): ?><img src="<?= e(foto_url($o['Slika'])) ?>" alt="" loading="lazy">
                <?php else: ?><div class="oglas-bez-slike w-100 h-100"><?= e(KATEGORIJE_OGLASA[(int) $o['Kategorija']] ?? '') ?></div><?php endif; ?>
                <?php if ($st): ?><span class="badge <?= $st === 2 ? 'bg-dark' : 'bg-warning text-dark' ?> oglas-status"><?= e(STATUSI_OGLASA[$st]) ?></span><?php endif; ?>
            </div>
            <div class="card-body p-2">
                <div class="fw-semibold text-truncate"><?= e($o['Naslov']) ?></div>
                <div class="text-success fw-bold"><?= e(cijena_oglasa($o)) ?></div>
                <div class="small text-muted text-truncate"><?= e($o['Mjesto']) ?><?= $o['Mjesto'] ? ' · ' : '' ?><?= e(date('d.m.', strtotime($o['Kreirano']))) ?></div>
                <?php if ((int) $o['KorisnikId'] === $k['Id'] && ($neprocitano[(int) $o['Id']] ?? 0) > 0): ?><span class="badge bg-danger mt-1"><?= $neprocitano[(int) $o['Id']] ?> nova poruka</span><?php endif; ?>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<?php stranica('Oglasnik', ob_get_clean());
