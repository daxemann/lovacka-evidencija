<?php
/** Uvoz iz Google kontakata: učitaj CSV → pregled i odluke → potvrdi. */
trazi(P_SUSTAV);
$tmpDat = fn(string $t) => podaci('uvoz-' . preg_replace('/[^a-f0-9]/', '', $t) . '.json');
$token = (string) ($_SESSION['uvoz'] ?? '');
$retci = $token && is_file($tmpDat($token)) ? json_decode((string) file_get_contents($tmpDat($token)), true) : null;

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'ucitaj') {
        $f = $_FILES['csv'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            poruka('Odaberite CSV datoteku.' . upload_greska($f), 'warning');
        } else {
            try {
                $r = procitaj_google_csv($f['tmp_name']);
                foreach (glob(podaci('uvoz-*.json')) ?: [] as $stari) {
                    if (filemtime($stari) < time() - 86400) {
                        @unlink($stari);
                    }
                }
                $token = bin2hex(random_bytes(8));
                file_put_contents($tmpDat($token), json_encode($r, JSON_UNESCAPED_UNICODE));
                $_SESSION['uvoz'] = $token;
            } catch (Throwable $e) {
                poruka(e($e->getMessage()), 'danger');
            }
        }
    } elseif ($radnja === 'odustani') {
        if ($token) {
            @unlink($tmpDat($token));
        }
        unset($_SESSION['uvoz']);
    } elseif ($radnja === 'uvezi' && $retci) {
        [$oznaka, $n, $s, $p, $foto, $ids] = provedi_uvoz($retci, (array) ($_POST['odluka'] ?? []), (array) ($_POST['preuzmi'] ?? []), isset($_POST['foto']));
        @unlink($tmpDat($token));
        unset($_SESSION['uvoz']);
        poruka(e("Uvezeno: $n novih, $s spojeno s postojećim, $p preskočeno" . ($foto ? ", $foto fotografija" : '') . '.'));
        preusmjeri('sustav/uvoz', ['oznaka' => $oznaka]);
    } elseif ($radnja === 'obrisi-nove') {
        [$n, $presk] = obrisi_pogresne((array) ($_POST['odabrani'] ?? []));
        poruka(e("Obrisano: $n.") . ($presk ? ' Nije obrisano (imaju podatke): ' . e(implode('; ', $presk)) : ''), $presk ? 'warning' : 'success');
        preusmjeri('sustav/uvoz', ['oznaka' => ul_str('oznaka')]);
    }
    preusmjeri('sustav/uvoz');
}

$clanoviPoId = [];
if ($retci) {
    $ids = [];
    foreach ($retci as $r) {
        foreach ($r['Kandidati'] as $k2) {
            $ids[$k2['Id']] = true;
        }
    }
    if ($ids) {
        foreach (redovi('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id IN (' . implode(',', array_map('intval', array_keys($ids))) . ')') as $c) {
            $clanoviPoId[(int) $c['Id']] = $c;
        }
    }
}
$oznaka = (string) ($_GET['oznaka'] ?? '');
ob_start(); ?>
<h1 class="h3 mb-1">Uvoz članova iz Google kontakata</h1>
<p class="text-muted">contacts.google.com → oznaka (npr. „Članovi“) → Izvezi → <b>Google CSV</b>. Svaki kontakt se uspoređuje s postojećim članovima
    (č/ć/š/ž/đ, zamijenjeno ime i prezime, tipfeleri, isti telefon/e-mail). Ništa se ne sprema dok ne potvrdite.</p>
<?php if (!$retci): ?>
    <form method="post" action="<?= e(url('sustav/uvoz')) ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 mb-4" style="max-width:640px"><?= csrf() ?>
        <input type="hidden" name="radnja" value="ucitaj">
        <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required style="max-width:420px">
        <button class="btn btn-primary">Učitaj i usporedi</button>
    </form>
<?php else:
    $n = count($retci);
    $noviBr = count(array_filter($retci, fn($r) => $r['Odluka'] === 'novi'));
    $spojBr = count(array_filter($retci, fn($r) => str_starts_with($r['Odluka'], 'spoji:')));
    $preskBr = count(array_filter($retci, fn($r) => $r['Odluka'] === 'preskoci'));
    $provBr = count(array_filter($retci, fn($r) => $r['TrebaProvjeru'])); ?>
    <form method="post" action="<?= e(url('sustav/uvoz')) ?>"><?= csrf() ?>
    <div class="alert alert-secondary d-flex flex-wrap align-items-center gap-3 sticky-top" style="top:3.6rem">
        <span>Kontakata: <b><?= $n ?></b></span><span>Novi: <b><?= $noviBr ?></b></span><span>Spajanje s postojećim: <b><?= $spojBr ?></b></span>
        <span>Preskočeno: <b><?= $preskBr ?></b></span><span>Za provjeru: <b class="text-danger"><?= $provBr ?></b></span>
        <label class="form-check-label small"><input type="checkbox" class="form-check-input" id="samoProvjera"> samo za provjeru</label>
        <label class="form-check-label small"><input type="checkbox" class="form-check-input" name="foto" value="1"> preuzmi fotografije</label>
        <span class="ms-auto"></span>
        <button class="btn btn-sm btn-outline-secondary" name="radnja" value="odustani" formnovalidate>Odustani</button>
        <button class="btn btn-sm btn-primary" name="radnja" value="uvezi" data-potvrda="Provesti uvoz prema odabranim odlukama?">Uvezi</button>
    </div>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>#</th><th>Iz Googlea</th><th>Postojeći član (sličnost)</th><th style="min-width:260px">Što napraviti</th></tr></thead>
        <tbody>
        <?php foreach ($retci as $i => $r): ?>
            <tr class="<?= $r['TrebaProvjeru'] ? 'table-warning provjera' : 'bez-provjere' ?>">
                <td class="text-muted small"><?= $i + 1 ?></td>
                <td><b><?= e($r['Prezime']) ?></b> <?= e($r['Ime']) ?>
                    <div class="small text-muted"><?= e(implode(' · ', array_filter([$r['Mobilni'], $r['Fiksni'], $r['Email'], $r['Mjesto'], $r['SekcijaNaziv'] ? 'sekcija: ' . $r['SekcijaNaziv'] : null]))) ?></div>
                    <?php foreach ($r['Napomene'] as $nap): ?><div class="small text-danger"><?= e($nap) ?></div><?php endforeach; ?></td>
                <td class="small">
                    <?php if (!$r['Kandidati']): ?><span class="text-muted">— nema sličnih</span><?php endif; ?>
                    <?php foreach ($r['Kandidati'] as $k2): $c = $clanoviPoId[$k2['Id']] ?? null; if (!$c) continue; ?>
                        <div><a href="<?= e(url('clanovi/uredi', ['id' => $c['Id']])) ?>" target="_blank"><?= e(prezime_ime($c)) ?></a>
                            <span class="badge bg-<?= $k2['ocjena'] >= 0.999 ? 'success' : ($k2['ocjena'] >= PRAG_SLICNOSTI ? 'warning text-dark' : 'secondary') ?>"><?= (int) round($k2['ocjena'] * 100) ?> %</span>
                            <?php if ((int) $c['Status'] === CLAN_ARHIVIRAN): ?><span class="badge bg-secondary">arhiviran</span><?php endif; ?>
                            <span class="text-muted"><?= e($k2['razlog']) ?></span></div>
                    <?php endforeach; ?>
                </td>
                <td class="small">
                    <select name="odluka[<?= $i ?>]" class="form-select form-select-sm mb-1">
                        <option value="novi"<?= sel('novi', $r['Odluka']) ?>>Novi član</option>
                        <?php foreach ($r['Kandidati'] as $k2): $c = $clanoviPoId[$k2['Id']] ?? null; if (!$c) continue; ?>
                            <option value="spoji:<?= (int) $c['Id'] ?>"<?= sel('spoji:' . $c['Id'], $r['Odluka']) ?>>Isti je kao: <?= e(prezime_ime($c)) ?> → dopuni</option>
                        <?php endforeach; ?>
                        <option value="preskoci"<?= sel('preskoci', $r['Odluka']) ?>>Preskoči (nije član / ne uvozi)</option>
                    </select>
                    <?php if (str_starts_with($r['Odluka'], 'spoji:') && ($c = $clanoviPoId[(int) substr($r['Odluka'], 6)] ?? null)):
                        $dop = dopune_uvoza($r, $c);
                        $raz = razlike_uvoza($r, $c); ?>
                        <?php if ($dop): ?><div>Dopunit će se: <?= e(implode(', ', $dop)) ?></div><?php else: ?><div class="text-muted">Nema novih podataka.</div><?php endif; ?>
                        <?php if ($raz): ?><div class="text-danger">Različito:</div>
                            <?php foreach ($raz as [$pol, $st, $no]): ?><div><?= e($pol) ?>: <?= e($st) ?> → <b><?= e($no) ?></b></div><?php endforeach; ?>
                            <label><input type="checkbox" class="form-check-input" name="preuzmi[<?= $i ?>]" value="1"> preuzmi podatke iz Googlea</label>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    </form>
    <script>document.getElementById('samoProvjera').addEventListener('change', function () { var s = this.checked; document.querySelectorAll('tr.bez-provjere').forEach(function (t) { t.style.display = s ? 'none' : ''; }); });</script>
<?php endif; ?>

<?php if ($oznaka !== ''):
    $novi = redovi('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.UvozOznaka=? ORDER BY c.Prezime, c.Ime', [$oznaka]); ?>
    <h2 class="h5 mt-4">Novi članovi iz uvoza <?= e($oznaka) ?> <span class="ms-2 small text-muted">– označite one koji nisu članovi udruge i obrišite ih</span></h2>
    <form method="post" action="<?= e(url('sustav/uvoz')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-nove"><input type="hidden" name="oznaka" value="<?= e($oznaka) ?>">
        <table class="table table-sm align-middle"><thead><tr><th><input type="checkbox" class="form-check-input" data-sve="odabrani[]"></th><th>Član</th><th>Sekcija</th><th>Mobitel</th><th>E-mail</th></tr></thead>
        <tbody><?php foreach ($novi as $c): ?>
            <tr><td><input type="checkbox" class="form-check-input" name="odabrani[]" value="<?= (int) $c['Id'] ?>"></td>
                <td><a href="<?= e(url('clanovi/uredi', ['id' => $c['Id']])) ?>"><?= e(prezime_ime($c)) ?></a></td><td><?= e($c['SekcijaNaziv'] ?? '—') ?></td><td><?= e($c['MobilniTelefon']) ?></td><td><?= e($c['Email']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$novi): ?><tr><td colspan="5" class="text-muted">Nema (više) članova iz ovog uvoza.</td></tr><?php endif; ?></tbody></table>
        <?php if ($novi): ?><button class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati odabrane (nisu članovi)?">Obriši odabrane – nisu članovi</button><?php endif; ?>
    </form>
<?php endif; ?>

<?php $prethodni = redovi('SELECT UvozOznaka, COUNT(*) AS n FROM Clanovi WHERE UvozOznaka IS NOT NULL GROUP BY UvozOznaka ORDER BY UvozOznaka DESC LIMIT 20');
if ($prethodni): ?>
    <h2 class="h6 mt-4">Prethodni uvozi</h2>
    <ul class="list-group" style="max-width:560px">
        <?php foreach ($prethodni as $pu): ?>
            <li class="list-group-item d-flex align-items-center"><span><?= e($pu['UvozOznaka']) ?> · <?= (int) $pu['n'] ?> novih (još postoje)</span>
                <a class="btn btn-sm btn-link ms-auto" href="<?= e(url('sustav/uvoz', ['oznaka' => $pu['UvozOznaka']])) ?>">Pregled / brisanje</a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php stranica('Uvoz', ob_get_clean());
