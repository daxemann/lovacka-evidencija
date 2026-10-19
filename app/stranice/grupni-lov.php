<?php
/** Grupni lov: pokretanje (organizator), poziv „Dolazim / Ne dolazim“, karta s položajem sudionika uživo, SOS, kraj lova. */
$k = korisnik();
gl_zatvori_istekle();

// „Dolazim“ iz push obavijesti (osobni ključ) – samo za vlastiti račun
if (isset($_GET['t'])) {
    $x = gl_po_tokenu((string) $_GET['t']);
    if ($x && (int) $x['ja']['KorisnikId'] === (int) $k['Id']) {
        $g = gl_odgovori($x['lov'], $x['ja'], (int) ($_GET['o'] ?? 1) === 1 ? 1 : 0);
        $g ? poruka($g, 'warning') : poruka((int) ($_GET['o'] ?? 1) === 1 ? '✔ Dolazite. Karta neka bude otvorena – tako vas organizator vidi.' : 'Spremljeno: ne dolazite.');
        preusmjeri('grupni-lov', ['id' => $x['lov']['Id']]);
    }
    preusmjeri('grupni-lov');
}

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $povratak = (string) ($_POST['povratak'] ?? '');
    if ($radnja === 'pokreni') {
        [$id, $g] = gl_pokreni(ul_str('naziv'), ul_str('mjesto'), ul_str('napomena'), (array) ($_POST['sekcije'] ?? []));
        if ($g) {
            poruka(e($g), 'danger');
            preusmjeri('grupni-lov');
        }
        $n = (int) vrijednost('SELECT COUNT(*) FROM GrupniLovSudionici WHERE LovId=?', [$id]);
        poruka("🎯 Grupni lov pokrenut – poziv je poslan lovcima ($n). Odgovori i položaji pojavljuju se na karti.");
        preusmjeri('grupni-lov', ['id' => $id]);
    }
    if ($radnja === 'obrisi-sve') {
        $svi = gl_zavrseni_moji();
        if (!$svi) {
            preusmjeri('grupni-lov');
        }
        try {
            napravi_kopiju('prije-brisanja-lovova');
        } catch (Throwable) {
        }
        $n = 0;
        foreach ($svi as $l) {
            $n += gl_obrisi($l) ? 1 : 0;
        }
        poruka("🗑 Obrisano završenih grupnih lovova: $n (s popisima sudionika). Prije brisanja napravljena je kopija baze.");
        preusmjeri('grupni-lov');
    }
    $lov = gl_lov((int) ($_POST['id'] ?? 0));
    if (!$lov) {
        nije_pronadjeno('Grupni lov');
    }
    if ($radnja === 'obrisi') {
        if (gl_aktivan($lov)) {
            poruka('Lov je u tijeku – prvo ga završite.', 'warning');
            preusmjeri('grupni-lov', ['id' => $lov['Id']]);
        }
        if (!gl_moze_upravljati($lov)) {
            zabranjeno();
        }
        try {
            napravi_kopiju('prije-brisanja-lova');
        } catch (Throwable) {
        }
        gl_obrisi($lov);
        poruka('🗑 Lov „' . e($lov['Naziv']) . '“ i njegov popis sudionika su obrisani.');
        preusmjeri('grupni-lov');
    }
    if ($radnja === 'zavrsi') {
        if (!gl_moze_upravljati($lov)) {
            zabranjeno();
        }
        gl_zavrsi($lov, $k['Naziv']);
        poruka('🏁 Lov završen. Položaji su obrisani, sudionici su obaviješteni.');
        preusmjeri('grupni-lov', ['id' => $lov['Id']]);
    }
    if ($radnja === 'dolazim' || $radnja === 'ne-dolazim') {
        $ja = gl_sudionik((int) $lov['Id'], (int) $k['Id']);
        if (!$ja) {
            zabranjeno();
        }
        $g = gl_odgovori($lov, $ja, $radnja === 'dolazim' ? 1 : 0);
        $g ? poruka(e($g), 'warning') : ($radnja === 'dolazim' ? poruka('✔ Dolazite. Karta neka bude otvorena – tako vas organizator vidi.') : poruka('Spremljeno: ne dolazite.'));
        if ($radnja === 'ne-dolazim' && $povratak !== '' && str_starts_with($povratak, '/') && !str_starts_with($povratak, '//')) {
            header('Location: ' . $povratak);
            exit;
        }
        preusmjeri('grupni-lov', ['id' => $lov['Id']]);
    }
    zabranjeno();
}

// ---------- koji lov prikazati ----------
$moj = gl_moj_aktivni();
$orgAktivni = gl_aktivni_organizator();
$lov = null;
if (ul('id', '') !== '') {
    $lov = gl_lov((int) ul('id', 0));
    if (!$lov || (!gl_moze_upravljati($lov) && !gl_sudionik((int) $lov['Id'], (int) $k['Id']))) {
        nije_pronadjeno('Grupni lov');
    }
    // završen lov: lovci više ništa ne vide, organizator vidi popis sudionika
    if (!gl_aktivan($lov) && !gl_moze_upravljati($lov)) {
        stranica('Grupni lov', '<h1 class="h3">🎯 Grupni lov</h1><div class="alert alert-secondary">Lov „' . e($lov['Naziv']) . '“ je završen. Lovu čast!</div>');
    }
} elseif ($moj) {
    $lov = $moj['lov'];
} elseif ($orgAktivni) {
    $lov = $orgAktivni[0];
}
$organizatorSek = gl_sekcije_organizator();

// ---------- nema aktivnog lova ----------
if (!$lov) {
    if (!ima(P_GRUPNI_LOV)) {
        stranica('Grupni lov', '<h1 class="h3">🎯 Grupni lov</h1><div class="alert alert-secondary">Trenutno nema grupnog lova.</div>');
    }
    $prosli = array_values(array_filter(redovi('SELECT l.*, (SELECT COUNT(*) FROM GrupniLovSudionici s WHERE s.LovId=l.Id AND s.Odgovor=1) AS Dosli,
        (SELECT COUNT(*) FROM GrupniLovSudionici s WHERE s.LovId=l.Id) AS Pozvani, (SELECT COUNT(*) FROM GrupniLovPopis p WHERE p.LovId=l.Id) AS NaPopisu FROM GrupniLov l WHERE l.Zavrseno IS NOT NULL ORDER BY l.Id DESC LIMIT 60'), 'gl_moze_upravljati'));
    ob_start(); ?>
    <h1 class="h3">🎯 Grupni lov</h1>
    <?php if (!$organizatorSek): ?>
        <div class="alert alert-warning">Vaša uloga nema sekciju – grupni lov ne možete pokrenuti.</div>
    <?php elseif (!gl_kraj_danas()): ?>
        <div class="alert alert-secondary">Nakon <?= GL_SAT_KRAJA ?>:00 grupni lov se više ne može pokrenuti – lov završava sam u <?= GL_SAT_KRAJA ?>:00.</div>
    <?php else: ?>
    <div class="card mb-4"><div class="card-body">
        <h2 class="h5">Pokreni grupni lov</h2>
        <p class="small text-muted">Svi lovci odabranih sekcija odmah dobivaju poziv na mobitel (<b>Dolazim / Ne dolazim</b>) i prozor u aplikaciji.
            Tko dolazi, vidi se uživo na karti dok mu je karta otvorena. Lov završavate gumbom <b>🏁 Lov završen</b>, inače završava sam u <?= GL_SAT_KRAJA ?>:00 – tada se brišu svi položaji.</p>
        <form method="post"><?= csrf() ?><input type="hidden" name="radnja" value="pokreni">
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Naziv</label><input class="form-control" name="naziv" maxlength="80" value="Grupni lov" required></div>
                <div class="col-md-6"><label class="form-label">Mjesto okupljanja</label><input class="form-control" name="mjesto" maxlength="120" placeholder="npr. Lovački dom"></div>
                <div class="col-12"><label class="form-label">Napomena</label><input class="form-control" name="napomena" maxlength="500" placeholder="npr. okupljanje 7:30, prsluk obavezan"></div>
                <div class="col-12"><label class="form-label d-block">Sekcije</label>
                    <?php foreach ($organizatorSek as $id => $n): ?>
                        <label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="sekcije[]" value="<?= (int) $id ?>"<?= count($organizatorSek) === 1 ? ' checked' : '' ?>> <?= e($n) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-success btn-lg mt-3" onclick="return confirm('Pokrenuti grupni lov i poslati poziv svim lovcima odabranih sekcija?')">🎯 Pokreni i pošalji poziv</button>
        </form>
    </div></div>
    <?php endif; ?>
    <?php if ($prosli): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h2 class="h5 mb-0 me-auto">Završeni grupni lovovi <span class="badge bg-secondary"><?= count($prosli) ?></span></h2>
        <form method="post"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-sve">
            <button class="btn btn-sm btn-outline-danger" data-potvrda="Trajno obrisati SVE završene grupne lovove (<?= count($prosli) ?>) zajedno s popisima sudionika? Ako trebate popis, prije preuzmite PDF. Kopija baze se radi automatski.">🗑 Obriši sve završene</button>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>Datum</th><th>Naziv</th><th>Organizator</th><th class="text-end">Došli / pozvani</th><th class="text-end">Na popisu</th><th></th></tr></thead><tbody>
        <?php foreach ($prosli as $p): ?>
            <tr><td><?= e(datum($p['Pocetak'])) ?></td><td><?= e($p['Naziv']) ?></td><td><?= e($p['KreiraoIme']) ?></td>
                <td class="text-end"><?= (int) $p['Dosli'] ?> / <?= (int) $p['Pozvani'] ?></td>
                <td class="text-end"><?= (int) $p['NaPopisu'] ?: '' ?></td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-lov', ['id' => $p['Id']])) ?>">Sudionici</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('grupni-popis', ['id' => $p['Id']])) ?>">📋 Popis</a>
                    <form method="post" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= (int) $p['Id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Obriši lov i popis" aria-label="Obriši <?= e($p['Naziv']) ?>"
                            data-potvrda="Trajno obrisati lov „<?= e($p['Naziv']) ?>“ (<?= e(datum($p['Pocetak'])) ?>) i njegov popis sudionika?">🗑</button></form></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif;
    stranica('Grupni lov', ob_get_clean());
}

$org = gl_moze_upravljati($lov);
$ja = gl_sudionik((int) $lov['Id'], (int) $k['Id']);

// ---------- završen lov (organizator): tko je sudjelovao ----------
if (!gl_aktivan($lov)) {
    $sud = redovi('SELECT * FROM GrupniLovSudionici WHERE LovId=? ORDER BY Odgovor DESC, Ime', [$lov['Id']]);
    ob_start(); ?>
    <h1 class="h3">🎯 <?= e($lov['Naziv']) ?> <span class="badge bg-secondary align-middle">završen</span></h1>
    <p class="text-muted"><?= e(datum_vrijeme($lov['Pocetak'])) ?> – <?= e(date('H:i', strtotime((string) $lov['Zavrseno']))) ?> · organizator <?= e($lov['KreiraoIme']) ?> · završio: <?= e($lov['ZavrsioIme']) ?>
        <?= $lov['Mjesto'] ? '· 📍 ' . e($lov['Mjesto']) : '' ?></p>
    <p class="small text-muted">Položaji su obrisani pri završetku lova – ostaje samo tko je sudjelovao.</p>
    <?php $naPopisu = (int) vrijednost('SELECT COUNT(*) FROM GrupniLovPopis WHERE LovId=?', [$lov['Id']]); ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-outline-primary" href="<?= e(url('grupni-popis', ['id' => $lov['Id']])) ?>">📋 Popis sudionika (skenirane iskaznice: <?= $naPopisu ?>)</a>
        <?php if ($naPopisu): ?><a class="btn btn-outline-secondary" href="<?= e(url('grupni-popis', ['id' => $lov['Id'], 'pdf' => 1])) ?>" target="_blank">📄 PDF popisa</a><?php endif; ?>
    </div>
    <h2 class="h6">Odgovori na poziv</h2>
    <table class="table table-sm"><thead><tr><th>Lovac</th><th>Odgovor</th><th>Napomena</th></tr></thead><tbody>
    <?php foreach ($sud as $s): ?>
        <tr><td><?= e($s['Ime']) ?></td>
            <td><?= $s['Odgovor'] === null ? '<span class="text-muted">bez odgovora</span>' : ((int) $s['Odgovor'] ? '✔ dolazi' : '✖ ne dolazi') ?></td>
            <td class="small"><?= $s['SosOd'] ? '🆘 SOS u ' . e(date('H:i', strtotime($s['SosOd']))) . ($s['SosPreuzeoIme'] ? ' (preuzeo ' . e($s['SosPreuzeoIme']) . ')' : '') : '' ?>
                <?= $s['Otisao'] ? ' otišao u ' . e(date('H:i', strtotime($s['Otisao']))) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('grupni-lov')) ?>">← Grupni lov</a>
        <form method="post" class="ms-auto"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= (int) $lov['Id'] ?>">
            <button class="btn btn-outline-danger" data-potvrda="Trajno obrisati ovaj lov i njegov popis sudionika? Ako trebate popis, prije preuzmite PDF.">🗑 Obriši lov</button></form>
    </div>
    <?php stranica('Grupni lov', ob_get_clean());
}

// ---------- aktivan lov ----------
$naslov = '<div class="d-flex flex-wrap align-items-center gap-2 mb-2"><h1 class="h4 mb-0 me-auto">🎯 ' . e($lov['Naziv']) . ' <span class="badge bg-danger align-middle">u tijeku</span></h1>'
    . (count($orgAktivni) > 1 ? implode('', array_map(fn($l) => (int) $l['Id'] !== (int) $lov['Id'] ? '<a class="btn btn-sm btn-outline-secondary" href="' . e(url('grupni-lov', ['id' => $l['Id']])) . '">' . e($l['Naziv']) . '</a>' : '', $orgAktivni)) : '')
    . '</div><p class="small text-muted mb-2">Organizator ' . e($lov['KreiraoIme']) . ($lov['Mjesto'] ? ' · 📍 ' . e($lov['Mjesto']) : '') . ($lov['Napomena'] ? ' · ' . e($lov['Napomena']) : '')
    . ' · završava sam u ' . e(date('H:i', strtotime($lov['Kraj']))) . '</p>';

// pozvan, još nije odgovorio / ne dolazi / otišao – bez karte
if (!$org && (!$ja || $ja['Odgovor'] === null || (int) $ja['Odgovor'] === 0 || $ja['Otisao'] !== null)) {
    ob_start(); ?>
    <?= $naslov ?>
    <div class="card"><div class="card-body text-center py-4">
        <?php if ($ja && $ja['Otisao'] !== null): ?><p class="mb-3">Otišli ste iz lova u <?= e(date('H:i', strtotime($ja['Otisao']))) ?> – položaj se više ne šalje.</p>
        <?php elseif ($ja && $ja['Odgovor'] !== null): ?><p class="mb-3">Odgovorili ste: <b>✖ ne dolazim</b>.</p><?php else: ?><p class="h5 mb-3">Dolazite na grupni lov?</p><?php endif; ?>
        <form method="post" class="d-flex flex-wrap justify-content-center gap-2"><?= csrf() ?><input type="hidden" name="id" value="<?= (int) $lov['Id'] ?>">
            <button class="btn btn-success btn-lg px-4" name="radnja" value="dolazim"><?= $ja && $ja['Otisao'] !== null ? '↩ Vraćam se u lov' : '✔ Dolazim' ?></button>
            <?php if (!$ja || $ja['Odgovor'] === null): ?><button class="btn btn-outline-secondary btn-lg px-4" name="radnja" value="ne-dolazim">✖ Ne dolazim</button><?php endif; ?>
        </form>
        <p class="small text-muted mt-3 mb-0">Tko dolazi, šalje svoj položaj na kartu lova dok mu je karta otvorena – organizator ga vidi, a u nuždi ima <b>🆘 SOS</b>.
            Položaji se brišu kad lov završi.</p>
    </div></div>
    <?php stranica('Grupni lov', ob_get_clean());
}

$saljem = $ja && (int) $ja['Odgovor'] === 1 && $ja['Otisao'] === null;
$cfg = [
    'api' => url('api/grupni-lov', ['id' => $lov['Id']]),
    'csrf' => csrf_token(),
    'granice' => revir_granice(),
    'centar' => revir_centar(),
    'saljem' => $saljem,
    'interval' => GL_INTERVAL_S,
    'signalMin' => GL_SIGNAL_MIN,
    'fokus' => ul('k', '') !== '' ? (int) ul('k', 0) : null,
    'sos' => !empty($_GET['sos']),
    'stanje' => gl_stanje($lov),
];
ob_start(); ?>
<link rel="stylesheet" href="<?= e(asset('assets/leaflet/leaflet.css')) ?>">
<?= $naslov ?>
<div id="gl-traka" class="gl-traka mb-2" hidden></div>
<div id="gl-sos-traka" class="gl-sos-traka mb-2" hidden></div>
<div class="gl-raspored">
    <div class="revir-okvir gl-karta-okvir">
        <div id="gl-karta" class="revir-karta gl-karta"></div>
        <button type="button" class="revir-lociraj" id="gl-lociraj" title="Gdje sam?" aria-label="Gdje sam?">◎</button>
        <?php if ($saljem): ?><button type="button" class="gl-sos-gumb" id="gl-sos" aria-label="SOS – trebam pomoć">🆘 SOS</button><?php endif; ?>
    </div>
    <div class="gl-popis">
        <div class="d-flex flex-wrap gap-2 small mb-2" id="gl-brojevi"></div>
        <div id="gl-sudionici" class="list-group list-group-flush small"></div>
        <div class="gl-legenda small text-muted mt-2">
            <span><i class="gl-tocka uzivo"></i> uživo</span> <span><i class="gl-tocka pozadina"></i> zaslon ugašen</span>
            <span><i class="gl-tocka signal"></i> nema signala (zadnji položaj)</span> <span><i class="gl-tocka sos"></i> SOS</span>
        </div>
        <?php if ($saljem): ?>
        <div class="mt-3 d-grid gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="gl-odlazim">🚶 Odlazim – prestani slati položaj</button>
        </div>
        <p class="small text-muted mt-2 mb-0">📱 Položaj se šalje <b>samo dok je ova stranica otvorena</b> (zaslon ostaje upaljen). Ugasite li zaslon, organizator vidi vaš zadnji položaj.</p>
        <?php endif; ?>
        <?php if ($org): ?>
        <a class="btn btn-success w-100 mt-3" href="<?= e(url('grupni-popis', ['id' => $lov['Id']])) ?>">📋 Popis sudionika – skeniraj iskaznice
            <span class="badge text-bg-light ms-1"><?= (int) vrijednost('SELECT COUNT(*) FROM GrupniLovPopis WHERE LovId=?', [$lov['Id']]) ?></span></a>
        <form method="post" class="mt-2"><?= csrf() ?><input type="hidden" name="id" value="<?= (int) $lov['Id'] ?>">
            <button class="btn btn-danger w-100" name="radnja" value="zavrsi" onclick="return confirm('Lov završen? Svi sudionici dobivaju obavijest, položaji se brišu.')">🏁 Lov završen</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script id="gl-cfg" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('assets/leaflet/leaflet.js')) ?>"></script>
<script src="<?= e(asset('assets/grupni.js')) ?>"></script>
<?php stranica('Grupni lov', ob_get_clean());
