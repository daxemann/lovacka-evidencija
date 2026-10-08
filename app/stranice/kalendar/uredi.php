<?php
/** Novi termin / uređivanje termina (pravo Kalendar – uređivanje). */
trazi(P_KALENDAR);
$k = korisnik();
$id = ul_int('id');
$d = $id ? dogadjaj($id) : null;
if ($id && (!$d || !moze_uredjivati_dogadjaj($d))) {
    zabranjeno();
}
$sveSek = $k['SveSekcije'];
$sekcije = $sveSek ? sekcije(true) : array_values(array_filter(sekcije(true), fn($s) => in_array((int) $s['Id'], $k['Sekcije'], true)));
$vrste = vrste_dogadjaja();
$v = $d ? [
    'Naslov' => $d['Naslov'], 'Vrsta' => $d['Vrsta'], 'CijeliDan' => (int) $d['CijeliDan'],
    'DatumOd' => substr($d['Pocetak'], 0, 10), 'VrijemeOd' => substr($d['Pocetak'], 11, 5),
    'DatumDo' => $d['Kraj'] ? substr($d['Kraj'], 0, 10) : '', 'VrijemeDo' => $d['Kraj'] ? substr($d['Kraj'], 11, 5) : '',
    'Mjesto' => $d['Mjesto'], 'Opis' => $d['Opis'], 'ZaSve' => (int) $d['ZaSve'], 'Sekcije' => array_keys($d['Sekcije']),
] : ['Naslov' => '', 'Vrsta' => $vrste[0]['naziv'], 'CijeliDan' => 0, 'DatumOd' => date('Y-m-d', strtotime('+1 day')), 'VrijemeOd' => '08:00',
    'DatumDo' => '', 'VrijemeDo' => '', 'Mjesto' => '', 'Opis' => '', 'ZaSve' => $sveSek ? 1 : 0, 'Sekcije' => []];

if (je_post()) {
    $v = [
        'Naslov' => mb_substr(ul_str('Naslov'), 0, 120), 'Vrsta' => ul_str('Vrsta'), 'CijeliDan' => isset($_POST['CijeliDan']) ? 1 : 0,
        'DatumOd' => (string) u_datum(ul_str('DatumOd')), 'VrijemeOd' => ul_str('VrijemeOd'), 'DatumDo' => (string) u_datum(ul_str('DatumDo')), 'VrijemeDo' => ul_str('VrijemeDo'),
        'Mjesto' => mb_substr(ul_str('Mjesto'), 0, 300), 'Opis' => mb_substr(ul_str('Opis'), 0, 3000),
        'ZaSve' => isset($_POST['ZaSve']) && $sveSek ? 1 : 0, 'Sekcije' => array_map('intval', (array) ($_POST['Sekcije'] ?? [])),
    ];
    $dozv = array_map(fn($s) => (int) $s['Id'], $sekcije);
    $v['Sekcije'] = array_values(array_intersect($v['Sekcije'], $dozv));
    $vr = fn(string $t) => preg_match('/^\d{1,2}:\d{2}$/', $t) ? str_pad($t, 5, '0', STR_PAD_LEFT) : null;
    $pocetak = $v['DatumOd'] ? $v['DatumOd'] . ' ' . ($v['CijeliDan'] ? '00:00' : ($vr($v['VrijemeOd']) ?? '00:00')) . ':00' : null;
    $kraj = null;
    if ($v['DatumDo'] !== '' || (!$v['CijeliDan'] && $vr($v['VrijemeDo']))) {
        $kraj = ($v['DatumDo'] ?: $v['DatumOd']) . ' ' . ($v['CijeliDan'] ? '00:00' : ($vr($v['VrijemeDo']) ?? '23:59')) . ':00';
    }
    $g = null;
    if ($v['Naslov'] === '') {
        $g = 'Upišite naslov.';
    } elseif (!$pocetak) {
        $g = 'Upišite datum.';
    } elseif ($kraj && $kraj < $pocetak) {
        $g = 'Kraj je prije početka.';
    } elseif (!$v['ZaSve'] && !$v['Sekcije']) {
        $g = $sveSek ? 'Označite „Cijela udruga“ ili barem jednu sekciju.' : 'Označite barem jednu sekciju.';
    } elseif (!in_array($v['Vrsta'], array_column($vrste, 'naziv'), true)) {
        $g = 'Odaberite vrstu.';
    }
    if (!$g) {
        $polja = ['Naslov' => $v['Naslov'], 'Vrsta' => $v['Vrsta'], 'Pocetak' => $pocetak, 'Kraj' => $kraj, 'CijeliDan' => $v['CijeliDan'],
            'Mjesto' => $v['Mjesto'] ?: null, 'Opis' => $v['Opis'] ?: null, 'ZaSve' => $v['ZaSve']];
        $novi = !$id;
        $id = transakcija(function () use ($id, $polja, $v, $k) {
            if ($id) {
                azuriraj('Dogadjaji', $id, $polja + ['Azurirano' => sada()]);
            } else {
                $id = umetni('Dogadjaji', $polja + ['KreiraoId' => $k['Id'], 'KreiraoIme' => $k['Naziv'], 'Kreirano' => sada()]);
            }
            q('DELETE FROM DogadjajSekcije WHERE DogadjajId=?', [$id]);
            if (!$v['ZaSve']) {
                foreach ($v['Sekcije'] as $s) {
                    q('INSERT INTO DogadjajSekcije (DogadjajId, SekcijaId) VALUES (?,?)', [$id, $s]);
                }
            }
            return $id;
        });
        dnevnik($novi ? 'Novi termin' : 'Uređen termin', null, null, $v['Naslov'] . ' ' . datum($pocetak));
        poruka($novi ? 'Termin je spremljen. Članovi ga vide u kalendaru – možete ga i podijeliti u grupu (dolje).' : 'Termin je spremljen.');
        preusmjeri('kalendar/termin', ['id' => $id]);
    }
    poruka(e($g), 'danger');
}
ob_start(); ?>
<div class="mb-2 small"><a href="<?= e($id ? url('kalendar/termin', ['id' => $id]) : url('kalendar')) ?>">← Natrag</a></div>
<h1 class="h3 mb-3"><?= $id ? 'Uredi termin' : 'Novi termin' ?></h1>
<form method="post" class="card" style="max-width:720px"><?= csrf() ?><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Naslov</label><input name="Naslov" class="form-control" value="<?= e($v['Naslov']) ?>" required maxlength="120" placeholder="npr. Čišćenje hranilišta"></div>
    <div class="col-md-6"><label class="form-label">Vrsta</label>
        <select name="Vrsta" class="form-select"><?php foreach ($vrste as $vr): ?><option<?= sel($v['Vrsta'], $vr['naziv']) ?>><?= e($vr['naziv']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="CijeliDan" id="cd" value="1"<?= chk($v['CijeliDan']) ?>>
        <label class="form-check-label" for="cd">Cijeli dan (bez sata)</label></div></div>
    <div class="col-7 col-md-4"><label class="form-label">Datum</label><input type="date" name="DatumOd" class="form-control" value="<?= e($v['DatumOd']) ?>" required></div>
    <div class="col-5 col-md-2 sat"><label class="form-label">Od</label><input type="time" name="VrijemeOd" class="form-control" value="<?= e($v['VrijemeOd']) ?>"></div>
    <div class="col-7 col-md-4"><label class="form-label">Do datuma <span class="small text-muted">(ako traje više dana)</span></label><input type="date" name="DatumDo" class="form-control" value="<?= e($v['DatumDo']) ?>"></div>
    <div class="col-5 col-md-2 sat"><label class="form-label">Do</label><input type="time" name="VrijemeDo" class="form-control" value="<?= e($v['VrijemeDo']) ?>"></div>
    <div class="col-12"><label class="form-label">Mjesto / okupljanje</label><input name="Mjesto" class="form-control" value="<?= e($v['Mjesto']) ?>" maxlength="300" placeholder="npr. Lovačka kuća Špišić Bukovica – ili zalijepite poveznicu Google Maps">
        <div class="form-text">Članovi dobiju gumb za navigaciju (Google Maps).</div></div>
    <div class="col-12"><label class="form-label">Opis</label><textarea name="Opis" class="form-control" rows="4" maxlength="3000" placeholder="što se radi, što ponijeti…"><?= e($v['Opis']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Za koga</label>
        <div class="border rounded p-2">
            <?php if ($sveSek): ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="ZaSve" id="zasve" value="1"<?= chk($v['ZaSve']) ?>>
                    <label class="form-check-label fw-semibold" for="zasve">Cijela udruga (svi članovi)</label></div>
            <?php endif; ?>
            <div id="sekcijeIzbor" class="d-flex flex-wrap gap-3 mt-1">
                <?php foreach ($sekcije as $s): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="Sekcije[]" value="<?= (int) $s['Id'] ?>" id="s<?= (int) $s['Id'] ?>"<?= chk(in_array((int) $s['Id'], $v['Sekcije'], true)) ?>>
                        <label class="form-check-label" for="s<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></label></div>
                <?php endforeach; ?>
                <?php if (!$sekcije): ?><span class="text-muted small">Udruga nema sekcija.</span><?php endif; ?>
            </div>
            <div class="form-text">Termin za sekciju vide samo članovi te sekcije (i uprava).</div>
        </div></div>
    <div class="col-12"><button class="btn btn-primary">Spremi</button></div>
</div></form>
<script>
(function () {
    var cd = document.getElementById('cd'), zs = document.getElementById('zasve'), si = document.getElementById('sekcijeIzbor');
    function f() {
        document.querySelectorAll('.sat').forEach(function (e) { e.style.display = cd.checked ? 'none' : ''; });
        if (zs) { si.style.opacity = zs.checked ? .4 : 1; si.querySelectorAll('input').forEach(function (i) { i.disabled = zs.checked; }); }
    }
    cd.addEventListener('change', f); if (zs) zs.addEventListener('change', f); f();
})();
</script>
<?php stranica($id ? 'Uredi termin' : 'Novi termin', ob_get_clean());
