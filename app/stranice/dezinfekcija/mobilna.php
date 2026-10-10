<?php
/** Mobilna dezinfekcijska stanica (npr. skupni lov): aktivacija na licu mjesta, praćenje dolazaka i odlazaka. */
trazi(P_DEZ_MOBILNA);
dez_osiguraj_stanice();
$k = korisnik();
$stanice = array_values(array_filter(dez_stanice(true, 'M'), fn($s) => $s['Aktivna']));
$ids = array_map(fn($s) => (int) $s['Id'], $stanice);
$razlozi = dez_razlozi();
$koord = function (): ?array {
    $lat = is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null;
    $lon = is_numeric($_POST['lon'] ?? null) ? (float) $_POST['lon'] : null;
    if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
        return null;
    }
    return [round($lat, 7), round($lon, 7), is_numeric($_POST['acc'] ?? null) ? round((float) $_POST['acc']) : null];
};

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $a = null;
    if ($radnja !== 'aktiviraj') {
        $a = dez_aktivacija_po_id((int) ($_POST['id'] ?? 0));
        if (!$a || !in_array((int) $a['StanicaId'], $ids, true)) {
            zabranjeno();
        }
    }
    switch ($radnja) {
        case 'aktiviraj':
            $sid = (int) ($_POST['stanica'] ?? 0);
            $kk = $koord();
            $do = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', ul_str('do')) ? str_replace('T', ' ', ul_str('do')) . ':00' : date('Y-m-d') . ' 23:59:59';
            if (!in_array($sid, $ids, true)) {
                zabranjeno();
            }
            if (!$kk) {
                poruka('Lokacija nije pronađena – uključite GPS i dopustite lokaciju pa pokušajte ponovno.', 'warning');
                break;
            }
            if ($do <= sada()) {
                poruka('Vrijeme završetka mora biti u budućnosti.', 'warning');
                break;
            }
            if ($stara = dez_aktivacija($sid)) {
                azuriraj('DezAktivacije', (int) $stara['Id'], ['Zatvoreno' => sada(), 'ZatvorioIme' => $k['Naziv']]);
            }
            $raz = red('SELECT Naziv FROM DezRazlozi WHERE Id=?', [(int) ($_POST['razlog'] ?? 0)]);
            $id = umetni('DezAktivacije', [
                'StanicaId' => $sid, 'Naziv' => mb_substr(ul_str('naziv'), 0, 80) ?: 'Skupni lov ' . date('d.m.Y.'), 'Razlog' => $raz['Naziv'] ?? null,
                'Lat' => $kk[0], 'Lon' => $kk[1], 'Tocnost' => $kk[2], 'Od' => sada(), 'Do' => $do, 'AktiviraoId' => $k['Id'], 'AktiviraoIme' => $k['Naziv'],
            ]);
            dnevnik('Dezinfekcija – mobilna stanica aktivirana', 'DezAktivacija', $id, ul_str('naziv') . ' · ' . $kk[0] . ', ' . $kk[1] . ' ±' . $kk[2] . ' m');
            poruka('Mobilna stanica je aktivna. Lovci sada mogu skenirati QR oznaku.' . ($kk[2] !== null && $kk[2] > 50 ? ' <b>Točnost lokacije je ±' . (int) $kk[2] . ' m</b> – po potrebi „Premjesti ovdje“ na otvorenom.' : ''));
            break;
        case 'premjesti':
            $kk = $koord();
            if (!$kk) {
                poruka('Lokacija nije pronađena.', 'warning');
                break;
            }
            azuriraj('DezAktivacije', (int) $a['Id'], ['Lat' => $kk[0], 'Lon' => $kk[1], 'Tocnost' => $kk[2]]);
            dnevnik('Dezinfekcija – mobilna stanica premještena', 'DezAktivacija', (int) $a['Id'], $kk[0] . ', ' . $kk[1] . ' ±' . $kk[2] . ' m');
            poruka('Položaj stanice je ažuriran.');
            break;
        case 'produzi':
            $do = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', ul_str('do')) ? str_replace('T', ' ', ul_str('do')) . ':00' : null;
            if ($do && $do > sada()) {
                azuriraj('DezAktivacije', (int) $a['Id'], ['Do' => $do]);
                poruka('Stanica je aktivna do ' . e(datum_vrijeme($do)) . '.');
            }
            break;
        case 'zavrsi':
            $org = 0;
            if (!empty($_POST['organizirano'])) {
                $proveli = mb_substr(ul_str('proveli'), 0, 200) ?: $k['Naziv'];
                $org = dez_organizirani_odlazak($a, $proveli, $k);
            }
            azuriraj('DezAktivacije', (int) $a['Id'], ['Zatvoreno' => sada(), 'ZatvorioIme' => $k['Naziv']]);
            dnevnik('Dezinfekcija – mobilna stanica zatvorena', 'DezAktivacija', (int) $a['Id'], $a['Naziv']);
            poruka('Mobilna stanica je zatvorena.' . ($org ? ' Upisan odlazak za ' . $org . ' osoba (organizirana dezinfekcija pri odlasku).' : ''));
            break;
    }
    preusmjeri('dezinfekcija/mobilna');
}

/** Stanje po osobi za jednu aktivaciju: zadnji upis osobe. */
$osobe = function (int $aid): array {
    $z = [];
    foreach (redovi('SELECT * FROM DezUpisi WHERE AktivacijaId=? AND Ponisteno=0 ORDER BY Vrijeme, Id', [$aid]) as $u) {
        $kl = $u['ClanId'] ? 'c' . $u['ClanId'] : 'g' . kljuc($u['Ime'] . ' ' . $u['Prezime']);
        $z[$kl]['zadnji'] = $u;
        if ($u['Smjer'] === 'D' && !isset($z[$kl]['dosao'])) {
            $z[$kl]['dosao'] = $u['Vrijeme'];
        }
    }
    uasort($z, fn($a, $b) => strcmp(kljuc(dez_ime($a['zadnji'])), kljuc(dez_ime($b['zadnji']))));
    return $z;
};
$aktivne = [];
foreach ($stanice as $s) {
    $aktivne[(int) $s['Id']] = dez_aktivacija((int) $s['Id']);
}
$prosle = $ids ? redovi('SELECT a.*, st.Naziv AS Stanica, (SELECT COUNT(*) FROM DezUpisi u WHERE u.AktivacijaId=a.Id AND u.Ponisteno=0) AS Broj
    FROM DezAktivacije a JOIN DezStanice st ON st.Id=a.StanicaId WHERE a.StanicaId IN (' . implode(',', $ids) . ') ORDER BY a.Od DESC LIMIT 15') : [];
$doZadano = date('Y-m-d') . 'T23:59';
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">Mobilna stanica</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('dezinfekcija', ['Vrsta' => 'M'])) ?>">Knjiga – mobilne stanice</a>
</div>
<p class="text-muted">Za skupni lov: postavite dezinfekciju na rubu šume/polja, stanite kraj nje i dodirnite <b>📍 Aktiviraj ovdje</b>.
    Lovci skeniraju QR oznaku mobilne stanice svoje sekcije – vrijedi samo dok je stanica aktivna i samo u krugu oko vas.</p>
<?php if (!$stanice): ?><div class="alert alert-secondary">Nema mobilnih stanica za vaše sekcije.</div><?php endif; ?>
<div class="row g-3">
<?php foreach ($stanice as $s): $sid = (int) $s['Id']; $a = $aktivne[$sid]; ?>
    <div class="col-lg-6"><div class="card h-100 <?= $a ? 'border-success' : '' ?>">
        <div class="card-header d-flex align-items-center gap-2"><b><?= e($s['Naziv']) ?></b>
            <?= $a ? '<span class="badge bg-success">AKTIVNA</span>' : '<span class="badge bg-secondary">neaktivna</span>' ?>
            <?php if (ima(P_DEZ_POSTAVKE) || ima(P_DEZ_MOBILNA)): ?><a class="ms-auto btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('dezinfekcija/qr', ['id' => $sid])) ?>">QR za ispis</a><?php endif; ?></div>
        <div class="card-body">
        <?php if ($a): $os = $osobe((int) $a['Id']); $unutra = array_filter($os, fn($o) => $o['zadnji']['Smjer'] === 'D'); ?>
            <div class="fw-semibold"><?= e($a['Naziv']) ?></div>
            <div class="small text-muted mb-2">od <?= e(date('H:i', strtotime($a['Od']))) ?> do <?= e(datum_vrijeme($a['Do'])) ?> · aktivirao <?= e($a['AktiviraoIme']) ?> ·
                <a target="_blank" rel="noopener" href="<?= e(karta_tocke_url((float) $a['Lat'], (float) $a['Lon'])) ?>">karta</a> (±<?= (int) $a['Tocnost'] ?> m, krug <?= (int) $s['Radijus'] ?> m)</div>
            <div class="d-flex gap-3 mb-2"><div><div class="kartica-broj"><?= count($unutra) ?></div><div class="small text-muted">na lovu</div></div>
                <div><div class="kartica-broj text-secondary"><?= count($os) - count($unutra) ?></div><div class="small text-muted">otišli</div></div>
                <div><div class="kartica-broj text-secondary"><?= count($os) ?></div><div class="small text-muted">ukupno</div></div></div>
            <?php if ($os): ?><ul class="list-group list-group-flush small mb-3 dez-mob-lista">
                <?php foreach ($os as $o): $u = $o['zadnji']; ?>
                    <li class="list-group-item px-0 d-flex gap-2 align-items-center"><span class="badge <?= $u['Smjer'] === 'D' ? 'bg-success' : 'bg-secondary' ?>"><?= $u['Smjer'] === 'D' ? 'tu' : 'otišao' ?></span>
                        <b><?= e(dez_ime($u)) ?></b><?= $u['Gost'] ? ' <span class="text-muted">(gost)</span>' : '' ?>
                        <span class="ms-auto text-muted text-nowrap"><?= isset($o['dosao']) ? e(date('H:i', strtotime($o['dosao']))) : '' ?><?= $u['Smjer'] === 'O' ? ' – ' . e(date('H:i', strtotime($u['Vrijeme']))) : '' ?>
                        <?= in_array((int) $u['Lokacija'], [DEZ_LOK_NEPOUZDANO, DEZ_LOK_BEZ], true) || $u['Izvanmrezno'] ? ' ⚠' : '' ?></span></li>
                <?php endforeach; ?></ul>
            <?php else: ?><p class="small text-muted">Još nitko nije skenirao.</p><?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= e(url('dezinfekcija/mobilna')) ?>" class="dez-gps-obrazac"><?= csrf() ?><input type="hidden" name="radnja" value="premjesti"><input type="hidden" name="id" value="<?= (int) $a['Id'] ?>">
                    <input type="hidden" name="lat"><input type="hidden" name="lon"><input type="hidden" name="acc">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-gps-posalji>📍 Premjesti ovdje</button></form>
                <form method="post" action="<?= e(url('dezinfekcija/mobilna')) ?>" class="d-flex gap-1"><?= csrf() ?><input type="hidden" name="radnja" value="produzi"><input type="hidden" name="id" value="<?= (int) $a['Id'] ?>">
                    <input type="datetime-local" name="do" class="form-control form-control-sm" value="<?= e(str_replace(' ', 'T', substr($a['Do'], 0, 16))) ?>"><button class="btn btn-sm btn-outline-secondary text-nowrap">Produži</button></form>
            </div>
            <form method="post" action="<?= e(url('dezinfekcija/mobilna')) ?>" class="border rounded p-2 mt-2"><?= csrf() ?><input type="hidden" name="radnja" value="zavrsi"><input type="hidden" name="id" value="<?= (int) $a['Id'] ?>">
                <?php if ($unutra): ?>
                    <div class="small mb-1"><b><?= count($unutra) ?></b> osoba još nema upisan odlazak.</div>
                    <label class="form-check small mb-1"><input type="checkbox" class="form-check-input" name="organizirano" value="1" checked>
                        <span class="form-check-label">Svi koji nisu upisali odlazak prošli su <b>organiziranu dezinfekciju pri odlasku</b> (vozila dezinficirana redom na izlazu) – upiši im odlazak.</span></label>
                    <input name="proveli" class="form-control form-control-sm mb-2" maxlength="200" value="<?= e($k['Naziv']) ?>" placeholder="dezinfekciju provode (imena)" aria-label="Dezinfekciju provode">
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Zatvoriti mobilnu stanicu? Nakon toga skeniranje više ne radi.">Zatvori stanicu</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('dezinfekcija/mobilna')) ?>" class="dez-gps-obrazac"><?= csrf() ?><input type="hidden" name="radnja" value="aktiviraj"><input type="hidden" name="stanica" value="<?= $sid ?>">
                <input type="hidden" name="lat"><input type="hidden" name="lon"><input type="hidden" name="acc">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label small">Naziv</label><input name="naziv" class="form-control" maxlength="80" value="Skupni lov <?= e(date('d.m.Y.')) ?>"></div>
                    <div class="col-6"><label class="form-label small">Razlog (zadano)</label>
                        <select name="razlog" class="form-select"><?php foreach ($razlozi as $r): ?><option value="<?= (int) $r['Id'] ?>"><?= e($r['Naziv']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6"><label class="form-label small">Aktivna do</label><input type="datetime-local" name="do" class="form-control" value="<?= e($doZadano) ?>"></div>
                </div>
                <button type="button" class="btn btn-success btn-lg w-100 mt-3" data-gps-posalji>📍 Aktiviraj ovdje</button>
            </form>
        <?php endif; ?>
        <div class="small text-muted mt-2 dez-gps-info"></div>
        </div>
    </div></div>
<?php endforeach; ?>
</div>
<?php if ($prosle): ?>
<h2 class="h5 mt-4">Zadnje akcije</h2>
<div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Naziv</th><th>Stanica</th><th>Od</th><th>Do</th><th>Upisa</th><th></th></tr></thead>
    <tbody><?php foreach ($prosle as $p): ?>
        <tr><td><?= e($p['Naziv']) ?></td><td class="small"><?= e($p['Stanica']) ?></td><td class="text-nowrap"><?= e(datum_vrijeme($p['Od'])) ?></td>
            <td class="text-nowrap"><?= e(date('H:i', strtotime($p['Zatvoreno'] ?? $p['Do']))) ?></td><td><?= (int) $p['Broj'] ?></td>
            <td><?php if (ima(P_DEZ_PREGLED)): ?><a href="<?= e(url('dezinfekcija', ['Vrsta' => 'M', 'AktivacijaId' => $p['Id'], 'Razdoblje' => 'Sve'])) ?>">popis</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-gps-posalji]'); if (!b) return;
    var f = b.closest('form'), info = f.closest('.card-body').querySelector('.dez-gps-info');
    if (!navigator.geolocation || !window.isSecureContext) { info.textContent = 'Lokacija radi samo preko https:// adrese.'; return; }
    b.disabled = true; info.textContent = 'Tražim lokaciju… (do 20 s za točniji položaj)';
    var najb = null, kraj = false;
    function posalji() { if (kraj) return; kraj = true; navigator.geolocation.clearWatch(w);
        if (!najb) { info.textContent = 'Lokacija nije pronađena – uključite GPS.'; b.disabled = false; return; }
        f.lat.value = najb.latitude; f.lon.value = najb.longitude; f.acc.value = Math.round(najb.accuracy); f.submit(); }
    var w = navigator.geolocation.watchPosition(function (p) {
        if (!najb || p.coords.accuracy < najb.accuracy) { najb = p.coords; info.textContent = 'Točnost ±' + Math.round(najb.accuracy) + ' m…'; }
        if (najb.accuracy <= 15) posalji();
    }, function (er) { if (er.code === 1) { kraj = true; info.textContent = 'Lokacija nije dopuštena u pregledniku.'; b.disabled = false; } }, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
    setTimeout(posalji, 20000);
});
<?php if (array_filter($aktivne)): ?>
setInterval(function () { if (document.visibilityState === 'visible' && (!document.activeElement || document.activeElement === document.body)) location.reload(); }, 30000);
<?php endif; ?>
</script>
<?php stranica('Mobilna stanica', ob_get_clean());
