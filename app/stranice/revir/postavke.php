<?php
/** Lovište – postavke: granice lovišta (KML), početni prikaz, sat isteka zauzeća, uvoz fotografija lovnih naprava. */
$k = korisnik();
if (!ima(P_SUSTAV) && !ima(P_REVIR_UREDI)) {
    zabranjeno();
}
$sustav = ima(P_SUSTAV);
/** Jedna datoteka iz polja oblika ime[id] (ili null). */
function revir_upload(string $polje, int $id): ?array
{
    $f = $_FILES[$polje] ?? null;
    if (!$f || !isset($f['error'][$id]) || $f['error'][$id] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return ['name' => $f['name'][$id], 'tmp_name' => $f['tmp_name'][$id], 'error' => $f['error'][$id], 'size' => $f['size'][$id]];
}
if (je_post()) {
    if (!$sustav) {
        zabranjeno();
    }
    switch ((string) ($_POST['radnja'] ?? '')) {
        case 'kml':
            $f = $_FILES['kml'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                poruka('Datoteka nije učitana.' . upload_greska($f), 'warning');
                break;
            }
            try {
                $r = revir_citaj_kml((string) file_get_contents($f['tmp_name']));
            } catch (RuntimeException $e) {
                poruka(e($e->getMessage()), 'danger');
                break;
            }
            if (!$r['oblici']) {
                poruka('U datoteci nema granica (poligona ili linija).' . ($r['tocaka'] ? ' Ima ' . $r['tocaka'] . ' točaka – one se ne uvoze.' : ''), 'warning');
                break;
            }
            file_put_contents(revir_granice_datoteka(), json_encode(['oblici' => $r['oblici'], 'datoteka' => basename((string) $f['name']), 'uvezeno' => sada(), 'uvezao' => $k['Naziv']], JSON_UNESCAPED_UNICODE));
            dnevnik('Lovište – uvezene granice', null, null, basename((string) $f['name']) . ' · ' . count($r['oblici']) . ' oblika');
            poruka('Granice su uvezene: ' . count($r['oblici']) . ' oblika.' . ($r['tocaka'] ? ' (' . $r['tocaka'] . ' točaka je preskočeno.)' : ''));
            break;
        case 'vrste-spremi':
            foreach ((array) ($_POST['v'] ?? []) as $id => $v) {
                $st = revir_vrste()[(int) $id] ?? null;
                if (!$st) {
                    continue;
                }
                $polja = ['Naziv' => mb_substr(trim((string) ($v['Naziv'] ?? '')), 0, 40) ?: $st['Naziv'], 'Zauzimanje' => isset($v['Zauzimanje']) ? 1 : 0,
                    'Redoslijed' => (int) ($v['Redoslijed'] ?? 0)];
                $f = revir_upload('ikona', (int) $id);
                if ($f) {
                    try {
                        $polja['Ikona'] = spremi_sliku($f, 'vrsta-', 128, true);
                        obrisi_sliku($st['Ikona']);
                    } catch (RuntimeException $e) {
                        poruka(e($st['Naziv'] . ': ' . $e->getMessage()), 'warning');
                    }
                }
                azuriraj('RevirVrste', (int) $id, $polja);
            }
            dnevnik('Lovište – vrste naprava');
            poruka('Vrste su spremljene.');
            break;
        case 'vrste-dodaj':
            $n = 0;
            $red = (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0) FROM RevirVrste');
            $ikone = $_FILES['ikone'] ?? null;
            if ($ikone && is_array($ikone['name'])) {
                foreach (array_keys($ikone['name']) as $i) {
                    $f = ['name' => $ikone['name'][$i], 'tmp_name' => $ikone['tmp_name'][$i], 'error' => $ikone['error'][$i], 'size' => $ikone['size'][$i]];
                    if ($f['error'] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    try {
                        $ime = spremi_sliku($f, 'vrsta-', 128, true);
                    } catch (RuntimeException $e) {
                        poruka(e($f['name'] . ': ' . $e->getMessage()), 'warning');
                        continue;
                    }
                    $naziv = trim((string) preg_replace(['/\.[^.]*$/', '/[_\-]+/'], ['', ' '], preg_replace('#^.*[/\\\\]#', '', (string) $f['name'])));
                    $postoji = null;
                    foreach (revir_vrste(true) as $v) {
                        if (kljuc($v['Naziv']) === kljuc($naziv)) {
                            $postoji = $v;
                        }
                    }
                    if ($postoji) { // ista vrsta već postoji – samo nova sličica
                        azuriraj('RevirVrste', (int) $postoji['Id'], ['Ikona' => $ime]);
                        obrisi_sliku($postoji['Ikona']);
                        $n++;
                        continue;
                    }
                    umetni('RevirVrste', ['Naziv' => mb_substr($naziv !== '' ? $naziv : 'Nova vrsta', 0, 40), 'Ikona' => $ime, 'Zauzimanje' => 1, 'Redoslijed' => ++$red]);
                    $n++;
                }
            }
            if (ul_str('naziv') !== '') {
                umetni('RevirVrste', ['Naziv' => mb_substr(ul_str('naziv'), 0, 40), 'Ikona' => null, 'Zauzimanje' => 1, 'Redoslijed' => ++$red]);
                $n++;
            }
            dnevnik('Lovište – nove vrste naprava', null, null, (string) $n);
            poruka($n ? "Dodano vrsta: $n. Provjerite nazive i „zauzima se“ pa Spremi." : 'Ništa nije dodano.', $n ? 'success' : 'warning');
            break;
        case 'vrsta-obrisi':
            $st = revir_vrste()[(int) ($_POST['id'] ?? 0)] ?? null;
            if ($st) {
                q('DELETE FROM RevirVrste WHERE Id=?', [$st['Id']]);
                obrisi_sliku($st['Ikona']);
                dnevnik('Lovište – obrisana vrsta', null, null, $st['Naziv']);
                poruka(e('Vrsta „' . $st['Naziv'] . '“ je obrisana.'));
            }
            break;
        case 'kml-obrisi':
            @unlink(revir_granice_datoteka());
            dnevnik('Lovište – obrisane granice');
            poruka('Granice su obrisane.');
            break;
        case 'postavke':
            $lat = u_broj($_POST['lat'] ?? '');
            $lon = u_broj($_POST['lon'] ?? '');
            $zoom = (int) ($_POST['zoom'] ?? 0);
            $c = revir_koord($lat, $lon);
            spremi_postavku('Revir.Centar', $c && $zoom >= 5 && $zoom <= 19 ? $c[0] . ',' . $c[1] . ',' . $zoom : null);
            $sat = (int) ($_POST['sat'] ?? 3);
            spremi_postavku('Revir.SatIsteka', (string) max(0, min(23, $sat)));
            dnevnik('Lovište – postavke');
            poruka('Spremljeno.');
            break;
    }
    preusmjeri('revir/postavke');
}
$gr = is_file(revir_granice_datoteka()) ? json_decode((string) file_get_contents(revir_granice_datoteka()), true) : null;
$centar = revir_centar();
$uredive = [];
foreach (revir_sekcije() as $id => $naziv) {
    if (revir_moze_urediti($id === 0 ? null : $id)) {
        $uredive[$id] = $naziv;
    }
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">Lovište – postavke</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir')) ?>">Karta lovišta</a>
</div>

<?php if ($uredive): ?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Uvoz lovnih naprava iz fotografija</h2>
    <p class="small text-muted mb-2">Odaberite više fotografija odjednom. Ako je fotografija snimljena mobitelom s uključenom lokacijom, položaj se čita iz nje.
        Za svaku upišite broj i naziv, pa „Spremi sve“. Fotografije poslane preko WhatsAppa/Vibera nemaju položaj – takve naprave postavite kasnije na karti (✏️ Uredi naprave).</p>
    <div class="row g-2 align-items-end mb-2">
        <div class="col-md-6"><input type="file" id="uvoz-foto" accept="image/jpeg,image/*" multiple class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Sekcija za sve</label>
            <select id="uvoz-sekcija" class="form-select"><?php foreach ($uredive as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Vrsta za sve</label>
            <select id="uvoz-vrsta" class="form-select"><?php foreach (revir_vrste() as $v): ?><option value="<?= (int) $v['Id'] ?>"><?= e($v['Naziv']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div id="uvoz-popis"></div>
    <button type="button" class="btn btn-primary mt-2" id="uvoz-spremi" hidden>Spremi sve</button>
    <div id="uvoz-info" class="small mt-2"></div>
</div></div>
<?php endif; ?>

<?php if ($sustav): ?>
<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Vrste lovnih naprava i sličice</h2>
    <p class="small text-muted">Sličica se prikazuje na karti (okvir mijenja boju: zeleno slobodno, crveno zauzeto) i bira se dodirom kod nove naprave.
        Najbolje PNG s prozirnom pozadinom, kvadratni. „Zauzima se“ isključite za kamere, hranilišta, solišta i sl. – one su na karti samo za informaciju.</p>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('revir/postavke')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vrste-spremi">
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th style="width:56px"></th><th>Naziv</th><th class="text-center">Zauzima se</th><th style="width:5rem">Redoslijed</th><th>Nova sličica</th><th></th></tr></thead>
        <tbody><?php foreach (revir_vrste() as $v): $i = (int) $v['Id']; ?>
            <tr><td><?php if ($v['Ikona']): ?><img src="<?= e(foto_url($v['Ikona'])) ?>" alt="" style="width:44px;height:44px;object-fit:contain"><?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                <td><input name="v[<?= $i ?>][Naziv]" class="form-control form-control-sm" maxlength="40" value="<?= e($v['Naziv']) ?>"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input" name="v[<?= $i ?>][Zauzimanje]" value="1"<?= chk($v['Zauzimanje']) ?>></td>
                <td><input name="v[<?= $i ?>][Redoslijed]" type="number" class="form-control form-control-sm" value="<?= (int) $v['Redoslijed'] ?>"></td>
                <td><input type="file" name="ikona[<?= $i ?>]" accept="image/png,image/*" class="form-control form-control-sm" style="max-width:220px"></td>
                <td><button class="btn btn-sm btn-link text-danger" form="obrisiV<?= $i ?>" data-potvrda="Obrisati vrstu? Naprave ove vrste ostaju, bez sličice.">Obriši</button></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <button class="btn btn-primary btn-sm">Spremi vrste</button>
    </form>
    <?php foreach (revir_vrste() as $v): ?><form method="post" action="<?= e(url('revir/postavke')) ?>" id="obrisiV<?= (int) $v['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vrsta-obrisi"><input type="hidden" name="id" value="<?= (int) $v['Id'] ?>"></form><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('revir/postavke')) ?>" class="row g-2 align-items-end mt-3"><?= csrf() ?><input type="hidden" name="radnja" value="vrste-dodaj">
        <div class="col-md-6"><label class="form-label small mb-0">Dodaj više sličica odjednom (naziv = ime datoteke)</label><input type="file" name="ikone[]" multiple accept="image/png,image/*" class="form-control"></div>
        <div class="col-md-4"><label class="form-label small mb-0">ili samo naziv</label><input name="naziv" class="form-control" maxlength="40" placeholder="npr. Lovačka kamera"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Dodaj</button></div>
    </form>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Granice lovišta (KML)</h2>
    <p class="small text-muted">Iz Google My Maps: ⋮ → „Izvezi u KML/KMZ“ (označite „Izvezi u .KML“). Iz Google Eartha: desni klik na mapu → „Spremi mjesto kao…“ → KML.
        Uvoze se poligoni i linije; točke se preskaču. Granice ostaju samo na ovom poslužitelju.</p>
    <?php if ($gr): ?>
        <div class="alert alert-success py-2 small">Uvezeno: <b><?= e($gr['datoteka'] ?? '') ?></b> · <?= count($gr['oblici'] ?? []) ?> oblika · <?= e(datum_vrijeme($gr['uvezeno'] ?? '')) ?> · <?= e($gr['uvezao'] ?? '') ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('revir/postavke')) ?>" class="d-flex flex-wrap gap-2"><?= csrf() ?><input type="hidden" name="radnja" value="kml">
        <input type="file" name="kml" accept=".kml,.kmz,application/vnd.google-earth.kml+xml,application/vnd.google-earth.kmz" class="form-control w-auto" required>
        <button class="btn btn-primary"><?= $gr ? 'Zamijeni granice' : 'Uvezi' ?></button>
    </form>
    <?php if ($gr): ?>
        <form method="post" action="<?= e(url('revir/postavke')) ?>" class="mt-2"><?= csrf() ?><input type="hidden" name="radnja" value="kml-obrisi">
            <button class="btn btn-sm btn-link text-danger p-0" data-potvrda="Obrisati granice lovišta?">Obriši granice</button></form>
    <?php endif; ?>
</div></div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Karta i zauzeća</h2>
    <form method="post" action="<?= e(url('revir/postavke')) ?>" class="row g-2" style="max-width:640px"><?= csrf() ?><input type="hidden" name="radnja" value="postavke">
        <div class="col-12 small text-muted">Početni prikaz karte – prazno = prema granicama lovišta.</div>
        <div class="col-4"><label class="form-label small mb-0">Šir. (lat)</label><input name="lat" class="form-control" value="<?= e($centar[0] ?? '') ?>" placeholder="45.8"></div>
        <div class="col-4"><label class="form-label small mb-0">Duž. (lon)</label><input name="lon" class="form-control" value="<?= e($centar[1] ?? '') ?>" placeholder="17.4"></div>
        <div class="col-4"><label class="form-label small mb-0">Zoom (5–19)</label><input name="zoom" type="number" min="5" max="19" class="form-control" value="<?= e($centar[2] ?? '') ?>" placeholder="13"></div>
        <div class="col-12 mt-3"><label class="form-label small mb-0">Zauzeće automatski istječe u</label>
            <div class="input-group" style="max-width:200px"><input name="sat" type="number" min="0" max="23" class="form-control" value="<?= revir_sat_isteka() ?>"><span class="input-group-text">:00 h</span></div>
            <div class="form-text">ako lovac zaboravi „Odlazim“ (sljedeće jutro).</div></div>
        <div class="col-12"><button class="btn btn-primary">Spremi</button></div>
    </form>
</div></div>
<?php endif; ?>

<?php if ($uredive): ?>
<script src="<?= e(asset('assets/revir-foto.js')) ?>"></script>
<script>
(function () {
    var api = <?= json_encode(url('api/revir')) ?>, csrf = <?= json_encode(csrf_token()) ?>;
    var ulaz = document.getElementById('uvoz-foto'), popis = document.getElementById('uvoz-popis'), gumb = document.getElementById('uvoz-spremi'), info = document.getElementById('uvoz-info');
    var sekSve = document.getElementById('uvoz-sekcija'), vrSve = document.getElementById('uvoz-vrsta');
    var stavke = [];
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    ulaz.addEventListener('change', function () {
        var datoteke = Array.prototype.slice.call(ulaz.files);
        popis.innerHTML = ''; stavke = []; info.textContent = 'Čitam položaje…';
        Promise.all(datoteke.map(function (f) { return RevirFoto.gps(f).then(function (g) { return { f: f, g: g }; }); })).then(function (r) {
            var bezGps = 0;
            r.forEach(function (s, i) {
                if (!s.g) bezGps++;
                var div = document.createElement('div');
                div.className = 'd-flex gap-2 align-items-start border-top py-2';
                div.innerHTML = '<img src="' + URL.createObjectURL(s.f) + '" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:6px">' +
                    '<div class="flex-grow-1 row g-1">' +
                    '<div class="col-3"><input class="form-control form-control-sm" placeholder="br." data-broj inputmode="numeric" maxlength="12"></div>' +
                    '<div class="col-9"><input class="form-control form-control-sm" placeholder="naziv *" data-naziv maxlength="80"></div>' +
                    '<div class="col-12 small ' + (s.g ? 'text-success' : 'text-warning-emphasis') + '">' + (s.g ? '📍 ' + s.g.lat.toFixed(5) + ', ' + s.g.lon.toFixed(5) : 'bez položaja – postavite kasnije na karti') +
                    ' · <span class="text-muted">' + esc(s.f.name) + '</span></div>' +
                    '<div class="col-12 small" data-stanje></div></div>';
                popis.appendChild(div);
                stavke.push({ f: s.f, g: s.g, el: div, gotovo: false });
            });
            info.textContent = r.length + ' fotografija' + (bezGps ? ', ' + bezGps + ' bez položaja' : ', sve s položajem') + '.';
            gumb.hidden = !r.length;
            var prvi = popis.querySelector('[data-broj]'); if (prvi) prvi.focus();
        });
    });
    gumb.addEventListener('click', function () {
        gumb.disabled = true;
        var redom = Promise.resolve(), ok = 0, preskoceno = 0;
        stavke.forEach(function (s) {
            if (s.gotovo) return;
            var naziv = s.el.querySelector('[data-naziv]').value.trim(), broj = s.el.querySelector('[data-broj]').value.trim(), st = s.el.querySelector('[data-stanje]');
            if (!naziv) { preskoceno++; st.innerHTML = '<span class="text-danger">upišite naziv</span>'; return; }
            redom = redom.then(function () {
                st.textContent = 'šaljem…';
                return RevirFoto.smanji(s.f).then(function (mala) {
                    var fd = new FormData();
                    fd.append('_csrf', csrf); fd.append('radnja', 'naprava-spremi');
                    fd.append('naziv', naziv); fd.append('broj', broj); fd.append('vrsta', vrSve.value); fd.append('sekcija', sekSve.value);
                    if (s.g) { fd.append('lat', s.g.lat.toFixed(7)); fd.append('lon', s.g.lon.toFixed(7)); }
                    fd.append('foto', mala, mala.name || 'foto.jpg');
                    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': csrf } }).then(function (r) { return r.json(); });
                }).then(function (j) {
                    if (j.ok) { s.gotovo = true; ok++; st.innerHTML = '<span class="text-success">✓ spremljeno</span>'; s.el.querySelectorAll('input').forEach(function (x) { x.disabled = true; }); }
                    else st.innerHTML = '<span class="text-danger">' + esc(j.greska || 'greška') + '</span>';
                }).catch(function () { st.innerHTML = '<span class="text-danger">nema veze s poslužiteljem</span>'; });
            });
        });
        redom.then(function () {
            gumb.disabled = false;
            info.innerHTML = ok + ' spremljeno' + (preskoceno ? ', ' + preskoceno + ' bez naziva' : '') + '. <a href="<?= e(url('revir')) ?>">Otvori kartu</a>';
        });
    });
})();
</script>
<?php endif; ?>
<?php stranica('Lovište – postavke', ob_get_clean());
