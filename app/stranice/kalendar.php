<?php
/** Kalendar termina – popis (nadolazeći / prošli) ili mjesečni prikaz. */
$k = korisnik();
$prikaz = ($_GET['prikaz'] ?? 'popis') === 'mjesec' ? 'mjesec' : 'popis';
$prosli = isset($_GET['prosli']);
$ured = ima(P_KALENDAR);
$mojiOdg = [];
if ($k['ClanId']) {
    foreach (redovi('SELECT DogadjajId, Dolazi FROM DogadjajOdgovori WHERE ClanId=?', [$k['ClanId']]) as $o) {
        $mojiOdg[(int) $o['DogadjajId']] = (int) $o['Dolazi'];
    }
}
$oznaka = function (array $d) use ($mojiOdg): string {
    $v = vrsta_dogadjaja($d['Vrsta']);
    $sek = $d['ZaSve'] ? '' : '<span class="badge text-bg-light border ms-1">' . e(implode(', ', $d['Sekcije'])) . '</span>';
    $odg = isset($mojiOdg[(int) $d['Id']]) ? ($mojiOdg[(int) $d['Id']] ? '<span class="badge bg-success ms-1">dolazim</span>' : '<span class="badge bg-secondary ms-1">ne dolazim</span>') : '';
    return '<span class="badge me-1" style="background:' . e($v['boja']) . '">' . e($d['Vrsta']) . '</span>' . $sek . $odg;
};
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">Kalendar</h1>
    <div class="btn-group btn-group-sm">
        <a class="btn btn-outline-secondary <?= $prikaz === 'popis' ? 'active' : '' ?>" href="<?= e(url('kalendar')) ?>">Popis</a>
        <a class="btn btn-outline-secondary <?= $prikaz === 'mjesec' ? 'active' : '' ?>" href="<?= e(url('kalendar', ['prikaz' => 'mjesec'])) ?>">Mjesec</a>
    </div>
    <?php if ($ured): ?><a class="btn btn-primary btn-sm" href="<?= e(url('kalendar/uredi')) ?>">+ Novi termin</a><?php endif; ?>
</div>
<?php if ($prikaz === 'popis'):
    $lista = $prosli
        ? dopuni_sekcije(redovi('SELECT d.*, (SELECT COUNT(*) FROM DogadjajOdgovori o WHERE o.DogadjajId=d.Id AND o.Dolazi=1) AS Dolazi FROM Dogadjaji d
            WHERE COALESCE(d.Kraj, d.Pocetak) < ? AND ' . kalendar_uvjet(kalendar_opseg()) . ' ORDER BY d.Pocetak DESC LIMIT 100', [danas()]))
        : dogadjaji(danas(), null);
    $mjeseci = ['', 'siječanj', 'veljača', 'ožujak', 'travanj', 'svibanj', 'lipanj', 'srpanj', 'kolovoz', 'rujan', 'listopad', 'studeni', 'prosinac'];
    $zadnji = ''; ?>
    <div class="mb-2 small"><?php if ($prosli): ?><a href="<?= e(url('kalendar')) ?>">← Nadolazeći termini</a><?php else: ?><a href="<?= e(url('kalendar', ['prosli' => 1])) ?>">Prošli termini</a><?php endif; ?></div>
    <?php if (!$lista): ?><div class="alert alert-light border">Nema <?= $prosli ? 'prošlih' : 'nadolazećih' ?> termina.</div><?php endif; ?>
    <div class="list-group mb-4">
    <?php foreach ($lista as $d):
        $m = substr($d['Pocetak'], 0, 7);
        if ($m !== $zadnji): $zadnji = $m; ?>
            <div class="list-group-item bg-light small text-uppercase fw-semibold text-muted"><?= e($mjeseci[(int) substr($m, 5, 2)] . ' ' . substr($m, 0, 4)) ?></div>
        <?php endif; ?>
        <a class="list-group-item list-group-item-action d-flex gap-3 align-items-start" href="<?= e(url('kalendar/termin', ['id' => $d['Id']])) ?>">
            <div class="text-center" style="min-width:3rem"><div class="fs-4 fw-bold lh-1"><?= date('d', strtotime($d['Pocetak'])) ?></div>
                <div class="small text-muted"><?= ['ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub'][(int) date('w', strtotime($d['Pocetak']))] ?></div></div>
            <div class="flex-grow-1">
                <div class="fw-semibold"><?= e($d['Naslov']) ?></div>
                <div class="small text-muted"><?= e(oznaka_vremena($d)) ?><?= $d['Mjesto'] ? ' · ' . e(mb_strimwidth($d['Mjesto'], 0, 50, '…')) : '' ?></div>
                <div class="mt-1"><?= $oznaka($d) ?><?php if ((int) $d['Dolazi']): ?><span class="small text-muted ms-1">👥 <?= (int) $d['Dolazi'] ?></span><?php endif; ?></div>
            </div>
        </a>
    <?php endforeach; ?>
    </div>
<?php else:
    $m = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
    $prvi = new DateTimeImmutable($m . '-01');
    $start = $prvi->modify('-' . (((int) $prvi->format('N')) - 1) . ' days');
    $kraj = $prvi->modify('last day of this month');
    $kraj = $kraj->modify('+' . (7 - (int) $kraj->format('N')) . ' days');
    $po = [];
    foreach (dogadjaji($start->format('Y-m-d'), $kraj->modify('+1 day')->format('Y-m-d')) as $d) {
        $a = new DateTimeImmutable(substr($d['Pocetak'], 0, 10));
        $b = $d['Kraj'] ? new DateTimeImmutable(substr($d['Kraj'], 0, 10)) : $a;
        for ($x = $a; $x <= $b && $x <= $kraj; $x = $x->modify('+1 day')) {
            $po[$x->format('Y-m-d')][] = $d;
        }
    }
    $mjeseci = ['', 'Siječanj', 'Veljača', 'Ožujak', 'Travanj', 'Svibanj', 'Lipanj', 'Srpanj', 'Kolovoz', 'Rujan', 'Listopad', 'Studeni', 'Prosinac']; ?>
    <div class="d-flex align-items-center gap-2 mb-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kalendar', ['prikaz' => 'mjesec', 'm' => $prvi->modify('-1 month')->format('Y-m')])) ?>">‹</a>
        <div class="fw-semibold"><?= e($mjeseci[(int) $prvi->format('n')] . ' ' . $prvi->format('Y')) ?></div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('kalendar', ['prikaz' => 'mjesec', 'm' => $prvi->modify('+1 month')->format('Y-m')])) ?>">›</a>
        <a class="btn btn-sm btn-link" href="<?= e(url('kalendar', ['prikaz' => 'mjesec'])) ?>">danas</a>
    </div>
    <div class="kal-mjesec mb-4">
        <?php foreach (['Pon', 'Uto', 'Sri', 'Čet', 'Pet', 'Sub', 'Ned'] as $dn): ?><div class="kal-zag"><?= $dn ?></div><?php endforeach; ?>
        <?php for ($x = $start; $x <= $kraj; $x = $x->modify('+1 day')): $kl = $x->format('Y-m') !== $m ? ' kal-van' : ''; $kl .= $x->format('Y-m-d') === danas() ? ' kal-danas' : ''; ?>
            <div class="kal-dan<?= $kl ?>"><div class="kal-br"><?= $x->format('j') ?></div>
                <?php foreach ($po[$x->format('Y-m-d')] ?? [] as $d): ?>
                    <a class="kal-dog" style="background:<?= e(vrsta_dogadjaja($d['Vrsta'])['boja']) ?>" href="<?= e(url('kalendar/termin', ['id' => $d['Id']])) ?>" title="<?= e($d['Naslov']) ?>"><?= $d['CijeliDan'] ? '' : '<span class="kal-sat">' . date('H:i', strtotime($d['Pocetak'])) . ' </span>' ?><?= e($d['Naslov']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
<?php endif; ?>
<div class="card"><div class="card-body small">
    <b>📲 Kalendar na mobitelu</b> – termini se sami pojavljuju u kalendaru vašeg mobitela (Google kalendar, iPhone kalendar):
    <div class="d-flex flex-wrap gap-2 mt-2">
        <?php $tok = kalendar_token((int) $k['Id']); $ics = apsolutni_url('kalendar-pretplata', ['t' => $tok]); $webcal = preg_replace('#^https?://#', 'webcal://', $ics); ?>
        <a class="btn btn-sm btn-primary" href="<?= e($webcal) ?>">Pretplati se (iPhone / Outlook)</a>
        <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="https://calendar.google.com/calendar/r?cid=<?= e(rawurlencode($webcal)) ?>">Google kalendar (Android)</a>
        <input id="ics-link" class="form-control form-control-sm" style="max-width:420px" value="<?= e($ics) ?>" readonly>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-kopiraj="#ics-link">Kopiraj poveznicu</button>
    </div>
    <div class="text-muted mt-1">Poveznica je samo vaša – ne dijelite je. Novi termini se pojavljuju s odmakom (mobitel osvježava kalendar svakih nekoliko sati).</div>
</div></div>
<?php stranica('Kalendar', ob_get_clean());
