<?php
/** Glavni okvir: bočni izbornik + zaglavlje. Varijable: $naslov, $sadrzaj */
$k = korisnik();
$trenutna = (string) ($_GET['p'] ?? 'pocetna');
$stavka = function (string $href, string $tekst, int $broj = 0, bool $tocno = false) use ($trenutna) {
    $akt = $tocno ? $trenutna === $href : ($trenutna === $href || str_starts_with($trenutna, $href . '/'));
    if ($href === 'pocetna' && $trenutna === '') {
        $akt = true;
    }
    return '<div class="nav-item px-3"><a class="nav-link' . ($akt ? ' active' : '') . '" href="' . e(url($href)) . '">'
        . '<span class="flex-grow-1">' . e($tekst) . '</span>'
        . ($broj > 0 ? '<span class="badge rounded-pill bg-warning text-dark">' . $broj . '</span>' : '') . '</a></div>';
};
/** Skupina izbornika koja se može sklopiti; otvorena ako sadrži trenutnu stranicu. */
$grupa = function (string $id, string $naslov, string $stavke): string {
    if ($stavke === '') {
        return '';
    }
    $akt = str_contains($stavke, 'nav-link active');
    return '<details class="nav-grupa" data-grupa="' . e($id) . '"' . ($akt ? ' open data-aktivna' : ' open') . '><summary class="px-3 pt-3 small text-white-50 text-uppercase">'
        . e($naslov) . '</summary>' . $stavke . '</details>';
};
$neprocitano = $k ? broj_neprocitanih((int) $k['Id']) : 0;
$revirObav = $k && modul('revir') ? revir_broj_obavijesti() : 0;
$novaVerzija = $k ? azuriranje_dostupno() : null;
$obavNove = $k && modul('obavijesti') ? obav_broj_nepotvrdjenih() : 0;
// obavezna obavijest preko cijelog zaslona dok je član ne potvrdi (ne na stranicama lozinke / 2FA i ne na samoj obavijesti)
$obavObavezna = $k && modul('obavijesti') && !in_array($trenutna, ['promjena-lozinke', 'dvofaktorska', 'odjava'], true) ? obav_obavezna_na_cekanju() : null;
if ($obavObavezna && $trenutna === 'obavijesti' && (int) ($_GET['id'] ?? 0) === (int) $obavObavezna['Id']) {
    $obavObavezna = null;
}
[$uloga, $boja] = oznaka_uloge();
// grupni lov: lovci vide stavku i prozor samo dok je lov u tijeku
$glMoj = $k && modul('grupni') ? gl_moj_aktivni() : null;
$glVidljivo = $k && modul('grupni') && gl_vidljivo();
$glAktivan = $glMoj !== null || ($k && ima(P_GRUPNI_LOV) && gl_aktivni_organizator());
$glBroj = $glVidljivo ? gl_broj_izbornik() : 0;
$glPoziv = $glMoj && $glMoj['ja']['Odgovor'] === null && $trenutna !== 'grupni-lov' && !in_array($trenutna, ['promjena-lozinke', 'dvofaktorska', 'odjava'], true) ? $glMoj : null;
$glUTijeku = $glMoj && (int) $glMoj['ja']['Odgovor'] === 1 && $glMoj['ja']['Otisao'] === null && $trenutna !== 'grupni-lov' ? $glMoj : null;
?><!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f3d1f">
    <link rel="manifest" href="<?= e(url('manifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('ikona', ['v' => 180, 'x' => postavka('Udruga.Logo')])) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e(udruga_kratko()) ?>">
    <title><?= e($naslov) ?> – <?= e(udruga_kratko()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
    <link rel="icon" type="image/png" href="<?= e(ima_logo() ? url('logo') : asset('assets/favicon.png')) ?>">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body>
<script>try { if (localStorage.getItem('ev-izbornik') === '0') document.documentElement.classList.add('izbornik-skriven'); } catch (x) {}</script>
<div class="page">
    <div class="sidebar">
        <div class="top-row ps-3 navbar navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url()) ?>">
                    <?php if (ima_logo()): ?><img src="<?= e(url('logo')) ?>" class="nav-logo" alt=""><?php endif; ?>
                    <span class="text-truncate"><?= e(udruga_kratko()) ?></span>
                </a>
            </div>
        </div>
        <input type="checkbox" title="Izbornik" class="navbar-toggler">
        <div class="nav-scrollable" onclick="if (event.target.closest('a')) document.querySelector('.navbar-toggler').click()">
            <nav class="nav flex-column">
                <?php if ($k): ?>
                    <?= $stavka('pocetna', '🏠 Početna', 0, true) ?>
                    <?php if (modul('dezinfekcija')) echo $stavka('skeniraj', '📷 Skeniraj QR stanice', 0, true); ?>
                    <?php if (modul('kalendar')) echo $stavka('kalendar', '📅 Kalendar'); ?>
                    <?php if (modul('domovi') && (dom_ima_vidljivih() || ima(P_DOMOVI))) echo $stavka('domovi', '🏡 Lovački domovi', dom_broj_na_cekanju()); ?>
                    <?php if ($glVidljivo) echo $stavka('grupni-lov', $glAktivan ? '🎯 Grupni lov – u tijeku' : '🎯 Grupni lov', $glBroj); ?>
                    <?php if ($k['ClanId']) echo $grupa('moje', 'Moje', (modul('akcije') ? $stavka('moje-akcije', '🛠️ Moje radne akcije') : '') . (modul('clanarina') && uplatnica_za_clanove() ? $stavka('uplatnica', '💳 Moja uplatnica', 0, true) : '') . (modul('iskaznice') ? $stavka('moja-iskaznica', '🪪 Moja iskaznica') : '') . (modul('popusti') && (pop_broj_vazecih() || pop_smije_uredjivati()) ? $stavka('popusti', '🏷️ Popusti u trgovinama', 0, true) : '') . $stavka('moj-profil', '👤 Moj profil') . $stavka('push', '🔔 Push obavijesti') . $stavka('moja-prava', '🔑 Moja prava')); ?>
                    <?php $revirSek = modul('revir') ? revir_sekcije() : []; if (modul('revir') && ($revirSek || ima(P_SUSTAV))) echo $grupa('loviste', 'Lovište',
                        ($revirSek ? $stavka('revir', '🗺️ Karta lovišta', 0, true) . $stavka('revir/danas', '🌲 Danas u lovištu', $revirObav) . $stavka('revir/kvarovi', '🔧 Kvarovi na napravama', revir_broj_kvarova()) . (ima(P_REVIR_DNEVNIK) ? $stavka('revir/dnevnik', '📓 Lovački dnevnik') : '') : '')
                        . (ima(P_SUSTAV) || ima(P_REVIR_UREDI) ? $stavka('revir/postavke', '⚙️ Postavke lovišta') : '')); ?>
                    <?= $grupa('clanovi', 'Članovi', $stavka('imenik', '📒 Imenik') . (modul('psi') ? $stavka('vodici-pasa', '🐕 Vodiči pasa') : '') . (modul('obavijesti') && ($k['ClanId'] || ima(P_OBAVIJESTI)) ? $stavka('obavijesti', '📢 Obavijesti', $obavNove) : '') . $stavka('poruke', '✉️ Poruke', $neprocitano) . (!modul('oglasnik') ? '' : (ogl_aktivan() ? $stavka('oglasnik-sso', '🛒 Oglasnik', 0, true) . (vrijednost('SELECT 1 FROM Oglasi WHERE Status IN (0,1) LIMIT 1') ? $stavka('oglasnik', '📌 Interni oglasi') : '') : $stavka('oglasnik', '🛒 Oglasnik'))) . (modul('dokumenti') ? $stavka('dokumenti', '📜 Statut i pravilnici') : '')) ?>
                    <?php if ($k['Prava']) echo $grupa('udruga', 'Udruga',
                        (ima(P_CLANOVI_CITAJ) ? $stavka('clanovi', '👥 Članovi') : '')
                        . (ima(P_CLANARINA_CITAJ) ? $stavka('clanarina', '💶 Članarina') : '')
                        . (ima(P_AKCIJE_ODOBRI) ? $stavka('akcije/nova', '➕ Nova radna akcija', 0, true) : '')
                        . (ima(P_AKCIJE_CITAJ) ? $stavka('akcije', '🔨 Radne akcije', broj_akcija_na_cekanju(), true) : '')
                        . (ima(P_IZVJESTAJI) ? $stavka('izvjestaji/akcije', '📊 Izvještaj radnih akcija') : '')
                        . (ima(P_INVENTURA) || ima(P_INVENTURA_UREDI) ? $stavka('inventura', '📦 Inventura') : '')
                        . (!$k['ClanId'] && ima(P_POPUSTI) ? $stavka('popusti', '🏷️ Popusti u trgovinama', 0, true) : '')); ?>
                    <?php if (ima(P_DEZ_PREGLED) || ima(P_DEZ_UREDI) || ima(P_DEZ_POSTAVKE) || ima(P_DEZ_MOBILNA)) echo $grupa('dezinfekcija', 'Dezinfekcija',
                        (ima(P_DEZ_PREGLED) ? $stavka('dezinfekcija', '🧴 Knjiga dezinfekcije', 0, true) : '')
                        . (ima(P_DEZ_MOBILNA) ? $stavka('dezinfekcija/mobilna', '📱 Mobilna stanica', (int) vrijednost("SELECT COUNT(*) FROM DezAktivacije WHERE Zatvoreno IS NULL AND Od<=? AND Do>=?", [sada(), sada()])) : '')
                        . (ima(P_DEZ_UREDI) ? $stavka('dezinfekcija/naknadno', '➕ Naknadni upis') : '')
                        . (ima(P_DEZ_UREDI) ? $stavka('dezinfekcija/liste', '📄 Papirnate liste') : '')
                        . (ima(P_DEZ_POSTAVKE) ? $stavka('dezinfekcija/postavke', '🔳 Stanice i QR') : '')); ?>
                    <?php if (ima(P_SUSTAV)) echo $grupa('sustav', 'Administracija', $stavka('sustav', '⚙️ Sustav',
                        (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND Aktivan=1') + ($novaVerzija ? 1 : 0))); ?>
                    <?= $grupa('program', 'Program', (!$k['ClanId'] ? $stavka('push', '🔔 Push obavijesti') : '') . $stavka('upute', '📖 Upute za korištenje') . $stavka('pomoc', '❓ Pomoć i kontakt') . (je_demo() ? $stavka('demo-posta', '📧 Demo – e-pošta') : '')) ?>
                <?php endif; ?>
            </nav>
            <?php if ($k): ?>
            <a class="program-potpis" href="<?= e(url('pomoc')) ?>" title="O programu">
                <img src="<?= e(asset('assets/logo-evidencija.svg')) ?>" alt="" width="22" height="22"><span>Lovačka Evidencija · <?= e(VERZIJA) ?></span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <main>
        <div class="top-row px-4">
            <button type="button" class="izbornik-gumb no-print me-auto" data-izbornik title="Sakrij / prikaži izbornik" aria-label="Sakrij / prikaži izbornik">☰</button>
            <?php if ($k): ?>
                <?php if (ima(P_REVIR_NADZOR)): ?><a href="<?= e(url('revir/danas')) ?>" class="revir-zvono me-3" title="Obavijesti – lovište" aria-label="Obavijesti – lovište">🔔<?php if ($revirObav): ?><span class="badge rounded-pill bg-danger"><?= $revirObav ?></span><?php endif; ?></a><?php endif; ?>
                <a href="<?= e(url('moja-prava')) ?>" class="text-decoration-none d-flex align-items-center gap-1 me-3" title="Moja prava">
                    <span class="small text-body d-none d-sm-inline"><?= e($k['Naziv']) ?></span>
                    <span class="badge <?= e($boja) ?>"><?= e($uloga) ?></span>
                </a>
                <a href="<?= e(url('promjena-lozinke')) ?>">Lozinka</a>
                <?php if ($k['Prava'] && dvofaktor_ukljucen()): ?><a href="<?= e(url('dvofaktorska')) ?>" class="ms-3" title="Dvofaktorska prijava (Google Authenticator)"><?= $k['Ima2fa'] ? '🔐' : '🔓' ?><span class="d-none d-md-inline"> 2FA</span></a><?php endif; ?>
                <form method="post" action="<?= e(url('odjava')) ?>" class="ms-3 d-inline"><?= csrf() ?>
                    <button type="submit" class="btn btn-link p-0">Odjava</button>
                </form>
            <?php endif; ?>
        </div>
        <article class="content px-4 pb-5">
            <?php if (je_demo()): ?>
            <div class="demo-traka no-print">🎯 <b>Demo verzija</b> – izmišljeni podaci, isprobajte slobodno. E-pošta se ne šalje (<a href="<?= e(url('demo-posta')) ?>">pregled</a>), svake noći sve se vraća na početno stanje.</div>
            <?php if (je_demo() && demo_samo_pregled($trenutna)): ?>
            <div class="alert alert-secondary small no-print" id="demo-zakljucano">🔒 <b>Samo pregled u demo verziji</b> – na ovoj stranici izmjene su zaključane (lozinke, korisnici, uloge i postavke sustava). U vašoj instalaciji sve radi normalno.</div>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($k): ?>
            <div id="push-traka" class="instal-traka no-print" hidden>
                <span style="font-size:1.8rem;line-height:1" aria-hidden="true">🔔</span>
                <div class="flex-grow-1 small"><b>Obavijesti na mobitel</b><div class="text-muted">Poruke i obavijesti udruge i kad je aplikacija zatvorena.</div></div>
                <button type="button" class="btn btn-sm btn-success" data-push-ukljuci>Uključi</button>
                <button type="button" class="btn-close ms-1" aria-label="Zatvori" data-push-zatvori></button>
            </div>
            <div id="instal-traka" class="instal-traka no-print" hidden>
                <img src="<?= e(url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')])) ?>" alt="" width="40" height="40">
                <div class="flex-grow-1 small"><b>Aplikacija na mobitelu</b><div class="text-muted">Ikona na početnom zaslonu – otvara se kao prava aplikacija.</div></div>
                <button type="button" class="btn btn-sm btn-success" data-instaliraj>📲 Dodaj</button>
                <button type="button" class="btn-close ms-1" aria-label="Zatvori" data-instal-zatvori></button>
            </div>
            <?php endif; ?>
            <?php if ($glUTijeku): ?>
            <div class="gl-u-tijeku no-print">
                <span class="flex-grow-1 small">🎯 <b><?= e($glUTijeku['lov']['Naziv']) ?></b> je u tijeku – organizator vas vidi samo dok je <b>karta lova</b> otvorena.</span>
                <a class="btn btn-sm btn-success" href="<?= e(url('grupni-lov', ['id' => $glUTijeku['lov']['Id']])) ?>">🗺️ Karta lova</a>
                <a class="btn btn-sm btn-danger" href="<?= e(url('grupni-lov', ['id' => $glUTijeku['lov']['Id'], 'sos' => 1])) ?>">🆘 SOS</a>
            </div>
            <?php endif; ?>
            <?php if ($novaVerzija && $trenutna !== 'sustav/azuriranje'): ?>
                <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 py-2 no-print">
                    <span class="flex-grow-1">⬆ Dostupna je nova verzija programa <b><?= e($novaVerzija['verzija']) ?></b> (instalirana <?= e(VERZIJA) ?>).</span>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('sustav/azuriranje')) ?>">Pogledaj i ažuriraj</a>
                </div>
            <?php endif; ?>
            <?php foreach (poruke() as [$vrsta, $tekst]): ?>
                <div class="alert alert-<?= e($vrsta) ?> alert-dismissible fade show no-print" role="alert">
                    <?= $tekst ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zatvori" onclick="this.parentElement.remove()"></button>
                </div>
            <?php endforeach; ?>
            <?php // stranice iz ⚙️ Sustav (i postavke modula s kartica Sustava): gumb natrag na pregled
            if ($k && ima(P_SUSTAV) && (str_starts_with($trenutna, 'sustav/') || in_array($trenutna, ['akcije/vrste', 'revir/postavke', 'dezinfekcija/postavke'], true))): ?>
                <a class="btn btn-sm btn-outline-secondary mb-3 no-print" href="<?= e(url('sustav')) ?>">← Sustav</a>
            <?php endif; ?>
            <?= $sadrzaj ?>
        </article>
    </main>
</div>
<div id="obavijest" class="lf-toast" style="display:none"></div>
<?php if ($glPoziv): $gl = $glPoziv['lov']; ?>
<div class="modal-pozadina obav-pozadina" role="dialog" aria-modal="true" aria-labelledby="gl-poziv-naslov"><div class="modal-okvir gl-poziv-okvir">
    <div class="gl-poziv-ikona mb-2">🎯</div>
    <h2 class="h5 mb-1" id="gl-poziv-naslov"><?= e($gl['Naziv']) ?></h2>
    <div class="text-muted mb-2">Organizator <?= e($gl['KreiraoIme']) ?><?= $gl['Mjesto'] ? ' · 📍 ' . e($gl['Mjesto']) : '' ?></div>
    <?php if ($gl['Napomena']): ?><p class="mb-2"><?= e($gl['Napomena']) ?></p><?php endif; ?>
    <p class="h6 mb-3">Dolazite na grupni lov?</p>
    <form method="post" action="<?= e(url('grupni-lov')) ?>" class="d-flex gap-2"><?= csrf() ?>
        <input type="hidden" name="id" value="<?= (int) $gl['Id'] ?>"><input type="hidden" name="povratak" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="btn btn-success btn-lg flex-fill" name="radnja" value="dolazim">✔ Dolazim</button>
        <button class="btn btn-outline-secondary btn-lg flex-fill" name="radnja" value="ne-dolazim">✖ Ne dolazim</button>
    </form>
    <p class="small text-muted mt-3 mb-0">Tko dolazi, vidi se na karti lova dok mu je karta otvorena. Položaji se brišu kad lov završi.</p>
</div></div>
<?php endif; ?>
<?php if ($obavObavezna && !$glPoziv): $obavJos = (int) vrijednost('SELECT COUNT(*) FROM ObavijestPrimatelji p JOIN Obavijesti o ON o.Id=p.ObavijestId WHERE p.ClanId=? AND p.Potvrdjeno IS NULL AND o.Obavezno=1', [$k['ClanId']]); ?>
<div class="modal-pozadina obav-pozadina" role="dialog" aria-modal="true" aria-labelledby="obav-naslov"><div class="modal-okvir obav-okvir">
    <div class="small text-muted mb-1">📢 Obavijest udruge<?= $obavJos > 1 ? ' · 1 od ' . $obavJos : '' ?></div>
    <h2 class="h5 mb-1" id="obav-naslov"><?= e($obavObavezna['Naslov']) ?></h2>
    <div class="small text-muted mb-2"><?= e($obavObavezna['KreiraoIme']) ?> · <?= e(datum_vrijeme($obavObavezna['Kreirano'])) ?></div>
    <div class="obav-tekst mb-3"><?= obav_tekst_html($obavObavezna['Tekst']) ?></div>
    <form method="post" action="<?= e(url('obavijesti')) ?>"><?= csrf() ?>
        <input type="hidden" name="radnja" value="potvrdi"><input type="hidden" name="id" value="<?= (int) $obavObavezna['Id'] ?>">
        <input type="hidden" name="povratak" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
        <button class="btn btn-success w-100 btn-lg">✔ Pročitao/la sam</button>
    </form>
</div></div>
<?php endif; ?>
<div class="modal-pozadina" id="instal-upute" hidden><div class="modal-okvir">
    <button type="button" class="btn-close float-end" aria-label="Zatvori" data-upute-zatvori></button>
    <h2 class="h5 mb-3">📲 Dodaj na početni zaslon</h2>
    <div data-sustav="ios"><ol class="mb-2"><li>Dolje u Safariju dodirnite <b>Dijeli</b> <span class="ios-dijeli">⬆︎</span> (kvadrat sa strelicom).</li>
        <li>Pomaknite se i odaberite <b>Dodaj na početni zaslon</b>.</li><li>Dodirnite <b>Dodaj</b> – ikona je na početnom zaslonu.</li></ol>
        <p class="small text-muted mb-0">Na iPhoneu to radi samo u Safariju.</p></div>
    <div data-sustav="android"><ol class="mb-2"><li>Gore desno u pregledniku dodirnite <b>⋮</b> (izbornik).</li>
        <li>Odaberite <b>Dodaj na početni zaslon</b> ili <b>Instaliraj aplikaciju</b>.</li><li>Potvrdite s <b>Dodaj / Instaliraj</b>.</li></ol></div>
    <div data-sustav="app"><p>Stranica je otvorena unutar druge aplikacije (WhatsApp, Viber, Facebook…), odakle se ne može dodati na početni zaslon.</p>
        <p><b>Otvorite je u Chromeu (Android) ili Safariju (iPhone):</b> dodirnite <b>⋮</b> ili <b>…</b> → „Otvori u pregledniku“, ili kopirajte adresu:</p>
        <div class="input-group"><input class="form-control form-control-sm" id="instal-adresa" readonly value="<?= e(javna_adresa()) ?>"><button class="btn btn-sm btn-outline-secondary" type="button" data-kopiraj="#instal-adresa">Kopiraj</button></div></div>
</div></div>
<script>window.EV = { api: <?= json_encode(url('api/obavijesti')) ?>, poruke: <?= json_encode(url('poruke')) ?>, n: <?= (int) $neprocitano ?>, sw: <?= json_encode(bazni_put() . 'sw.js') ?>, push: <?= $k && push_podrzan() ? json_encode(url('api/push')) : 'null' ?>, uid: <?= $k ? (int) $k['Id'] : 'null' ?> };</script>
<script src="<?= e(asset('assets/app.js')) ?>"></script>
<?php if ($k && push_podrzan()): ?><script src="<?= e(asset('assets/push.js')) ?>"></script><?php endif; ?>
<?php if (je_demo() && demo_samo_pregled($trenutna)): ?>
<script>/* demo: zaključane stranice – obrasci za izmjene sivi */
document.querySelectorAll('article form[method=post], article form[method=POST]').forEach(function (f) {
    f.querySelectorAll('input:not([type=hidden]), select, textarea, button').forEach(function (x) { x.disabled = true; x.title = 'Demo verzija – nije moguće mijenjati'; });
    f.classList.add('demo-sivo');
});</script>
<?php endif; ?>
<script src="<?= e(asset('assets/dez.js')) ?>" data-api="<?= e(url('api/dez-sync')) ?>" data-sw="<?= e(bazni_put() . 'sw.js') ?>"></script>
<script>document.querySelectorAll('a[href*="p=oglasnik-sso"]').forEach(function (a) { a.target = '_blank'; a.rel = 'noopener'; });</script>
</body>
</html>
