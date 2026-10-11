<?php
/**
 * Upisi dezinfekcije iz preglednika (assets/dez.js, sw.js).
 * GET  → { csrf, uid, ime, sada, stanice: [adrese stranica stanica za spremanje na mobitel],
 *          popis: [{ url, naziv, sekcija, mob }] (stanice u opsegu korisnika – za stranicu "Skeniraj"),
 *          resursi: [stranica "Skeniraj" + datoteke potrebne bez interneta] }
 * POST → JSON { stavke: [{ id, s, t|null, u?, po?, polja }] } (zaglavlje X-CSRF) → { rezultati: [{ id, ok, poruka?, url?, trajno? }] }
 *        t  = vrijeme s mobitela (unix) za upis spremljen bez interneta; bez t vrijedi vrijeme poslužitelja.
 *        u  = korisnik koji je upis spremio na mobitelu – drugi prijavljeni korisnik ga ne može poslati.
 *        po = poznato odstupanje sata mobitela od poslužitelja (s) – samo za zapis u dnevnik.
 */
$k = korisnik();
if (!je_post()) {
    dez_osiguraj_stanice();
    $st = redovi('SELECT st.Token, st.Naziv, st.Vrsta, st.SekcijaId, s.Naziv AS SekcijaNaziv FROM DezStanice st
        LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE st.Aktivna=1 ORDER BY st.Vrsta, COALESCE(s.Redoslijed, 9999), s.Naziv, st.Naziv');
    $popis = [];
    foreach ($st as $s) {
        if ($s['SekcijaId'] === null || moze_sekciju((int) $s['SekcijaId'])) {
            $popis[] = ['url' => url('dez', ['s' => $s['Token']]), 'naziv' => $s['Naziv'], 'sekcija' => $s['SekcijaNaziv'], 'mob' => $s['Vrsta'] === 'M'];
        }
    }
    $resursi = [url('skeniraj'), url('manifest'), url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')])];
    foreach (['assets/bootstrap.min.css', 'assets/app.css', 'assets/dez.js', 'assets/skener.js', 'assets/jsqr.min.js'] as $a) {
        $resursi[] = asset($a);
    }
    if (ima_logo()) {
        $resursi[] = url('logo');
    }
    json([
        'csrf' => csrf_token(), 'uid' => (int) $k['Id'], 'ime' => $k['Naziv'], 'sada' => time(),
        'stanice' => array_map(fn($s) => url('dez', ['s' => $s['Token']]), $st),
        'popis' => $popis, 'resursi' => $resursi,
    ]);
}
$ulaz = json_decode((string) file_get_contents('php://input'), true);
$stavke = array_slice((array) ($ulaz['stavke'] ?? []), 0, 50);
$rez = [];
// jedno slanje u isto vrijeme (stranica i pozadinsko slanje mogu poslati isti red istodobno → bez dvostrukih upisa)
$brava = @fopen(podaci('dez-sync.lock'), 'c');
if ($brava) {
    flock($brava, LOCK_EX);
}
foreach ($stavke as $s) {
    $id = (string) ($s['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => 'Neispravan upis.'];
        continue;
    }
    // upis je spremio drugi korisnik na ovom mobitelu – ostaje na mobitelu dok se on ne prijavi
    if (isset($s['u']) && is_numeric($s['u']) && (int) $s['u'] !== (int) $k['Id']) {
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => false, 'drugi' => true, 'poruka' => 'Upis je spremio drugi korisnik – šalje se kad se on prijavi.'];
        continue;
    }
    $st = dez_stanica_po_tokenu((string) ($s['s'] ?? ''));
    if (!$st) {
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => 'QR oznaka stanice više nije važeća.'];
        continue;
    }
    $t = isset($s['t']) && is_numeric($s['t']) ? (int) $s['t'] : null;
    $po = isset($s['po']) && is_numeric($s['po']) ? (int) $s['po'] : null;
    try {
        $r = dez_spremi_upis($st, $k, (array) ($s['polja'] ?? []), $t, $id, $po);
    } catch (Throwable $e) {
        error_log((string) $e);
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => false, 'poruka' => 'Greška na poslužitelju – pokušat ćemo ponovno.'];
        continue;
    }
    $rez[] = $r['ok']
        ? ['id' => $id, 'ok' => true, 'url' => url('dez/potvrda', ['g' => $r['grupa']])]
        : ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => $r['poruka']];
}
if ($brava) {
    flock($brava, LOCK_UN);
    fclose($brava);
}
json(['rezultati' => $rez]);
