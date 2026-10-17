<?php
/** Izvještaj radnih akcija: filtar, ispis, PDF, CSV, slanje e-poštom. */
trazi(P_IZVJESTAJI);
$k = korisnik();
$f = filtar_iz_upita($_GET);
$grupe = izvjestaj_akcija($f);
$opis = opis_raspona($f);
$sekcije = moje_sekcije();
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c WHERE ' . opseg_sql('c') . ' ORDER BY c.Prezime, c.Ime');
$vrste = redovi('SELECT * FROM VrsteAkcija ORDER BY Naziv');
$upit = filtar_upit($f);

/*
 * Primatelji e-pošte – samo iz baze, bez ručnog upisa adrese (zaštita od slanja tuđih podataka na krivu adresu):
 *  - član: dobiva SAMO svoje radne akcije i bodove (za razdoblje iz filtra), bez obzira na to što je na zaslonu,
 *  - uprava: cijeli izvještaj samo korisnik koji i sam ima pravo na izvještaje i vidi sve sekcije iz izvještaja.
 */
$clanIds = [];
foreach ($grupe as $g) {
    foreach ($g['retci'] as $r) {
        $clanIds[(int) $r['ClanId']] = true;
    }
}
$sekcijeIzvj = [];
if ($clanIds) {
    foreach (redovi('SELECT DISTINCT SekcijaId FROM Clanovi WHERE Id IN (' . implode(',', array_keys($clanIds)) . ')') as $r) {
        $sekcijeIzvj[] = $r['SekcijaId'] === null ? null : (int) $r['SekcijaId'];
    }
}
$uprava = [];
foreach (redovi("SELECT k.Id, k.ClanId, c.Ime, c.Prezime, c.Email, ku.SekcijaId, u.Prava FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId
    JOIN KorisnikUloge ku ON ku.KorisnikId=k.Id JOIN Uloge u ON u.Id=ku.UlogaId
    WHERE k.Aktivan=1 AND k.Odobren=1 AND TRIM(COALESCE(c.Email,''))<>''") as $r) {
    $x = &$uprava[(int) $r['Id']];
    $x ??= ['Id' => (int) $r['Id'], 'Ime' => $r['Ime'], 'Prezime' => $r['Prezime'], 'Email' => trim((string) $r['Email']), 'Prava' => 0, 'Sve' => false, 'Sek' => []];
    $x['Prava'] |= (int) $r['Prava'];
    if ($r['SekcijaId'] === null) {
        $x['Sve'] = true;
    } else {
        $x['Sek'][] = (int) $r['SekcijaId'];
    }
    unset($x);
}
$uprava = array_values(array_filter($uprava, function ($u) use ($sekcijeIzvj) {
    if (($u['Prava'] & P_IZVJESTAJI) !== P_IZVJESTAJI) {
        return false;
    }
    if ($u['Sve']) {
        return true;
    }
    foreach ($sekcijeIzvj as $s) {
        if ($s === null || !in_array($s, $u['Sek'], true)) {
            return false;
        }
    }
    return true;
}));
usort($uprava, fn($a, $b) => strcoll($a['Prezime'] . ' ' . $a['Ime'], $b['Prezime'] . ' ' . $b['Ime']));
$saMailom = redovi("SELECT c.Id, c.Ime, c.Prezime, c.Email FROM Clanovi c WHERE " . opseg_sql('c') . " AND TRIM(COALESCE(c.Email,''))<>'' ORDER BY c.Prezime, c.Ime");

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'posalji') {
        $prim = (string) ($_POST['primatelj'] ?? '');
        try {
            if (preg_match('/^c:(\d+)$/', $prim, $m)) {
                // član → samo njegovi podaci
                $c = clan((int) $m[1]);
                if (!$c || !u_opsegu($c) || !trim((string) $c['Email'])) {
                    throw new RuntimeException('Član nije pronađen ili nema e-mail adresu.');
                }
                [$nasl, $tekst, $gr] = poruka_bodovi($c, $f);
                posalji_mail(trim($c['Email']), $nasl, tekst_u_html($tekst), true, null, [
                    'radne-akcije-' . bez_dijakritika($c['Prezime']) . '.pdf' => izvjestaj_pdf($gr, 'Radne akcije – ' . puno_ime($c), $opis),
                ]);
                dnevnik('Izvadak radnih akcija poslan članu', 'Clan', (int) $c['Id'], puno_ime($c) . ' · ' . $opis);
                poruka('Poslano: ' . e(puno_ime($c)) . ' – samo svoje radne akcije (' . e($opis) . ').');
            } elseif (preg_match('/^u:(\d+)$/', $prim, $m)) {
                // uprava → cijeli izvještaj, samo ako primatelj smije vidjeti sve podatke iz izvještaja
                $u = array_values(array_filter($uprava, fn($x) => $x['Id'] === (int) $m[1]))[0] ?? null;
                if (!$u) {
                    throw new RuntimeException('Primatelj nema pravo na cijeli izvještaj (pravo „Izvještaji“ i sve sekcije iz izvještaja).');
                }
                posalji_mail($u['Email'], 'Radne akcije – ' . udruga_kratko(), izvjestaj_html($grupe, 'Radne akcije: ' . $opis, false, $f['ClanId'] === null), true, null, [
                    'radne-akcije-' . date('Y-m-d') . '.pdf' => izvjestaj_pdf($grupe, 'Radne akcije', $opis, $f['ClanId'] === null),
                    'radne-akcije-' . date('Y-m-d') . '.csv' => izvjestaj_csv($grupe),
                ]);
                dnevnik('Izvještaj radnih akcija poslan e-poštom', null, null, trim($u['Ime'] . ' ' . $u['Prezime']) . ' · ' . $opis);
                poruka('Cijeli izvještaj poslan: ' . e(trim($u['Ime'] . ' ' . $u['Prezime'])) . '.');
            } else {
                poruka('Odaberite primatelja.', 'warning');
            }
        } catch (Throwable $e) {
            poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
        }
    } elseif ($radnja === 'izvadci') {
        @set_time_limit(300);
        $ids = [];
        foreach ($grupe as $g) {
            foreach ($g['retci'] as $r) {
                $ids[(int) $r['ClanId']] = true;
            }
        }
        $poslano = 0;
        $bez = 0;
        foreach (array_keys($ids) as $cid) {
            $c = clan($cid);
            if (!$c || !trim((string) $c['Email'])) {
                $bez++;
                continue;
            }
            [$nasl, $tekst, $gr] = poruka_bodovi($c, $f);
            try {
                posalji_mail($c['Email'], $nasl, tekst_u_html($tekst), true, null, [
                    'radne-akcije-' . bez_dijakritika($c['Prezime']) . '.pdf' => izvjestaj_pdf($gr, 'Radne akcije – ' . puno_ime($c), $opis),
                ]);
                $poslano++;
            } catch (Throwable $e) {
                poruka('Greška kod ' . e(puno_ime($c)) . ': ' . e($e->getMessage()), 'danger');
                break;
            }
        }
        dnevnik('Izvadci radnih akcija poslani članovima', null, null, "$poslano poslano, $bez bez e-maila");
        poruka("Poslano $poslano izvadaka. Bez e-mail adrese: $bez.");
    }
    preusmjeri('izvjestaji/akcije', $upit);
}
$sel = fn($kl, $v) => sel($kl, $v);
$ukupnoAkcija = array_sum(array_map(fn($g) => count($g['retci']), $grupe));
$ukupnoBodova = array_sum(array_map(fn($g) => ukupno_bodova($g['retci']), $grupe));
ob_start(); ?>
<h1 class="h3 mb-3 no-print">Izvještaj radnih akcija</h1>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="izvjestaji/akcije">
    <div class="row g-2">
        <div class="col-6 col-md-3"><label class="form-label small">Razdoblje</label>
            <select name="Razdoblje" class="form-select form-select-sm" data-auto>
                <?php foreach (RAZDOBLJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Razdoblje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <?php if ($f['Razdoblje'] === 'Mjeseci'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od mjeseca</label><input type="month" name="Od" class="form-control form-control-sm" value="<?= e(substr($f['Od'] ?? date('Y-m'), 0, 7)) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do mjeseca</label><input type="month" name="Do" class="form-control form-control-sm" value="<?= e(substr($f['Do'] ?? date('Y-m'), 0, 7)) ?>"></div>
        <?php elseif ($f['Razdoblje'] === 'Slobodno'): ?>
            <div class="col-6 col-md-2"><label class="form-label small">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>"></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small">Sekcija</label>
            <select name="SekcijaId" class="form-select form-select-sm">
                <option value="">Sve</option>
                <?php foreach ($sekcije as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= $sel($s['Id'], $f['SekcijaId']) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
                <?php if ($k['SveSekcije']): ?><option value="-1"<?= $sel(-1, $f['SekcijaId']) ?>>Nije raspoređeno</option><?php endif; ?>
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label small">Član</label>
            <select name="ClanId" class="form-select form-select-sm"><option value="">Svi članovi</option>
                <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"<?= $sel($c['Id'], $f['ClanId']) ?>><?= e(prezime_ime($c)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Vrsta</label>
            <select name="VrstaId" class="form-select form-select-sm"><option value="">Sve</option>
                <?php foreach ($vrste as $v): ?><option value="<?= (int) $v['Id'] ?>"<?= $sel($v['Id'], $f['VrstaId']) ?>><?= e($v['Naziv']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Status</label>
            <select name="Status" class="form-select form-select-sm"><option value="">Svi</option>
                <option value="Odobreno"<?= $f['Status'] === AKCIJA_ODOBRENO ? ' selected' : '' ?>>Odobreno</option>
                <option value="NaCekanju"<?= $f['Status'] === AKCIJA_CEKA ? ' selected' : '' ?>>Na čekanju</option>
                <option value="Odbijeno"<?= $f['Status'] === AKCIJA_ODBIJENO ? ' selected' : '' ?>>Odbijeno</option>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Sortiranje</label>
            <select name="Sortiranje" class="form-select form-select-sm">
                <?php foreach (SORTIRANJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Sortiranje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Grupiranje</label>
            <select name="Grupiranje" class="form-select form-select-sm">
                <?php foreach (GRUPIRANJA as $kl => $t): ?><option value="<?= $kl ?>"<?= $sel($kl, $f['Grupiranje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('izvoz/akcije-pdf', $upit + ['prikaz' => 1])) ?>">Pogledaj PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/akcije-pdf', $upit)) ?>" download>Preuzmi PDF</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('izvoz/akcije-csv', $upit)) ?>" download>Excel (CSV)</a>
    </div>
</form>
<?php if (posta_dostupna()): ?>
<form method="post" action="<?= e(url('izvjestaji/akcije', $upit)) ?>" class="d-flex flex-wrap gap-2 mb-3 no-print"><?= csrf() ?>
    <select name="primatelj" id="primatelj" class="form-select form-select-sm" style="max-width:420px">
        <option value="">— primatelj e-pošte —</option>
        <?php if ($uprava): ?><optgroup label="Uprava – cijeli izvještaj (kao na zaslonu)">
            <?php foreach ($uprava as $u): ?><option value="u:<?= (int) $u['Id'] ?>" data-opis="cijeli izvještaj (<?= (int) $ukupnoAkcija ?> akcija, svi članovi iz izvještaja)" data-ime="<?= e(prezime_ime($u)) ?>"><?= e(prezime_ime($u)) ?> · <?= e($u['Email']) ?></option><?php endforeach; ?>
        </optgroup><?php endif; ?>
        <optgroup label="Član – samo svoje radne akcije i bodovi">
            <?php foreach ($saMailom as $c): ?><option value="c:<?= (int) $c['Id'] ?>" data-opis="samo svoje radne akcije i bodovi" data-ime="<?= e(prezime_ime($c)) ?>"<?= sel($c['Id'], $f['ClanId']) ?>><?= e(prezime_ime($c)) ?> · <?= e($c['Email']) ?></option><?php endforeach; ?>
        </optgroup>
    </select>
    <button class="btn btn-sm btn-outline-primary" name="radnja" value="posalji" id="posalji-mail" data-potvrda="Odaberite primatelja.">Pošalji e-poštom</button>
    <button class="btn btn-sm btn-outline-success" name="radnja" value="izvadci" data-potvrda="Poslati svakom članu iz izvještaja njegov izvadak e-poštom?">Svakom članu njegov izvadak</button>
</form>
<div class="small text-muted mb-3 no-print" id="primatelj-opis">Adresa se uzima iz podataka člana. Član uvijek dobiva <b>samo svoje</b> radne akcije i bodove za odabrano razdoblje; cijeli izvještaj samo uprava s pravom na izvještaje.</div>
<script>(function () {
    var s = document.getElementById('primatelj'), b = document.getElementById('posalji-mail'), o = document.getElementById('primatelj-opis');
    var opis = <?= json_encode($opis, JSON_UNESCAPED_UNICODE) ?>;
    function osvjezi() {
        var x = s.options[s.selectedIndex];
        if (!s.value) { b.setAttribute('data-potvrda', 'Odaberite primatelja.'); b.disabled = true; return; }
        b.disabled = false;
        b.setAttribute('data-potvrda', 'Poslati e-poštom: ' + x.getAttribute('data-ime') + '\n\nSadržaj: ' + x.getAttribute('data-opis') + '\nRazdoblje: ' + opis + '\n\nPoslati?');
        o.innerHTML = 'Primatelj <b></b> dobiva: <b></b> · ' + opis.replace(/[<&]/g, '');
        o.querySelectorAll('b')[0].textContent = x.getAttribute('data-ime'); o.querySelectorAll('b')[1].textContent = x.getAttribute('data-opis');
    }
    s.addEventListener('change', osvjezi); osvjezi();
})();</script>
<?php else: ?>
    <p class="small text-muted no-print">Slanje e-poštom: podesiti u Sustav → E-pošta i adresa.</p>
<?php endif; ?>
<div class="mb-3">
    <div class="h5 mb-0"><?= e(udruga_naziv()) ?> – radne akcije</div>
    <div class="text-muted small"><?= e($opis) ?></div>
</div>
<?php foreach ($grupe as $g): if (!$g['retci'] && count($grupe) > 1) continue; ?>
    <?php if ($g['naziv'] !== ''): ?><h3 class="h6 mt-3"><?= e($g['naziv']) ?></h3><?php endif; ?>
    <?php if ($g['retci']): ?>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Datum</th><th>Član</th><th>Sekcija</th><th>Vrsta</th><th class="text-end">Sati</th><th>Opis</th><th>Status</th><th class="text-end">Bodovi</th></tr></thead>
        <tbody>
        <?php foreach ($g['retci'] as $r): ?>
            <tr><td class="text-nowrap"><?= e(date('d.m.Y', strtotime($r['Datum']))) ?></td><td><?= e($r['Clan']) ?></td><td><?= e($r['Sekcija']) ?></td><td><?= e($r['Vrsta']) ?></td>
                <td class="text-end"><?= e(broj($r['Sati'])) ?></td><td class="small"><?= !empty($r['Oprema']) ? '<div>' . oprema_html($r['Oprema']) . '</div>' : '' ?><?= e($r['Opis']) ?></td><td><?= STATUSI_AKCIJE[$r['Status']] ?></td><td class="text-end"><?= e(broj($r['Bodovi'], 2)) ?></td></tr>
        <?php endforeach; ?>
        <tr class="fw-bold"><td colspan="4">Ukupno (<?= count($g['retci']) ?>)</td><td class="text-end"><?= e(broj(ukupno_sati($g['retci']))) ?></td><td colspan="2"></td><td class="text-end"><?= e(broj(ukupno_bodova($g['retci']), 2)) ?></td></tr>
        </tbody>
    </table></div>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (count($grupe) > 1): ?><p class="fw-bold">Sveukupno: <?= $ukupnoAkcija ?> akcija, <?= e(broj($ukupnoBodova, 2)) ?> bodova</p><?php endif; ?>
<?php if ($ukupnoAkcija === 0): ?><p class="text-muted">Nema radnih akcija za odabrane uvjete.</p><?php endif; ?>
<?php stranica('Izvještaj radnih akcija', ob_get_clean());
