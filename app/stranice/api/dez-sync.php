<?php
/**
 * Upisi dezinfekcije iz preglednika (assets/dez.js).
 * GET  → { csrf, stanice: [adrese stranica stanica za spremanje na mobitel] }
 * POST → JSON { stavke: [{ id, s, t|null, polja }] } (zaglavlje X-CSRF) → { rezultati: [{ id, ok, poruka?, url? }] }
 *        t = vrijeme s mobitela (unix) za upis spremljen bez interneta; bez t vrijedi vrijeme poslužitelja.
 */
$k = korisnik();
if (!je_post()) {
    dez_osiguraj_stanice();
    $st = redovi('SELECT Token FROM DezStanice WHERE Aktivna=1 ORDER BY Id');
    json(['csrf' => csrf_token(), 'stanice' => array_map(fn($s) => url('dez', ['s' => $s['Token']]), $st)]);
}
$ulaz = json_decode((string) file_get_contents('php://input'), true);
$stavke = array_slice((array) ($ulaz['stavke'] ?? []), 0, 50);
$rez = [];
foreach ($stavke as $s) {
    $id = (string) ($s['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => 'Neispravan upis.'];
        continue;
    }
    $st = dez_stanica_po_tokenu((string) ($s['s'] ?? ''));
    if (!$st) {
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => 'QR oznaka stanice više nije važeća.'];
        continue;
    }
    $t = isset($s['t']) && is_numeric($s['t']) ? (int) $s['t'] : null;
    try {
        $r = dez_spremi_upis($st, $k, (array) ($s['polja'] ?? []), $t, $id);
    } catch (Throwable $e) {
        error_log((string) $e);
        $rez[] = ['id' => $id, 'ok' => false, 'trajno' => false, 'poruka' => 'Greška na poslužitelju – pokušat ćemo ponovno.'];
        continue;
    }
    $rez[] = $r['ok']
        ? ['id' => $id, 'ok' => true, 'url' => url('dez/potvrda', ['g' => $r['grupa']])]
        : ['id' => $id, 'ok' => false, 'trajno' => true, 'poruka' => $r['poruka']];
}
json(['rezultati' => $rez]);
