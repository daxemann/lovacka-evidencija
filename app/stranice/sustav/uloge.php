<?php
/** Uloge (skupovi prava) i funkcije u udruzi. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    switch ($radnja) {
        case 'spremi':
            foreach ((array) ($_POST['u'] ?? []) as $id => $u) {
                $ul = red('SELECT * FROM Uloge WHERE Id=?', [(int) $id]);
                if (!$ul) {
                    continue;
                }
                $prava = 0;
                foreach ((array) ($u['p'] ?? []) as $p) {
                    $prava |= (int) $p;
                }
                // prava isključenih modula nisu prikazana – ostaju kakva su bila
                $skriveno = moduli_iskljucena_prava();
                $prava = ($prava & ~$skriveno) | ((int) $ul['Prava'] & $skriveno);
                $polja = ['Naziv' => mb_substr(trim((string) ($u['Naziv'] ?? $ul['Naziv'])), 0, 60) ?: $ul['Naziv'], 'Opis' => trim((string) ($u['Opis'] ?? '')) ?: null];
                if (!$ul['Sustavna']) {
                    $polja += ['Prava' => $prava & P_SVE, 'SveSekcije' => isset($u['SveSekcije']) ? 1 : 0];
                }
                azuriraj('Uloge', (int) $id, $polja);
                if (!$ul['Sustavna'] && ((int) $ul['Prava'] !== ($prava & P_SVE) || (int) $ul['SveSekcije'] !== (isset($u['SveSekcije']) ? 1 : 0))) {
                    // promijenjena prava: korisnici s ulogom se ponovno prijavljuju
                    q('UPDATE Korisnici SET SigurnosniZig=lower(hex(randomblob(16))) WHERE Id IN (SELECT KorisnikId FROM KorisnikUloge WHERE UlogaId=?) AND Id<>?', [(int) $id, korisnik()['Id']]);
                }
            }
            dnevnik('Uređene uloge i prava');
            poruka('Spremljeno.');
            break;
        case 'dodaj':
            $n = ul_str('Naziv');
            if ($n !== '') {
                umetni('Uloge', ['Naziv' => mb_substr($n, 0, 60), 'Opis' => null, 'Prava' => P_CLANOVI_CITAJ, 'Sustavna' => 0, 'SveSekcije' => 0]);
                dnevnik('Nova uloga', null, null, $n);
            }
            break;
        case 'obrisi':
            $ul = red('SELECT * FROM Uloge WHERE Id=?', [(int) $_POST['id']]);
            $koristi = (int) vrijednost('SELECT COUNT(*) FROM KorisnikUloge WHERE UlogaId=?', [(int) $_POST['id']]) + (int) vrijednost('SELECT COUNT(*) FROM PlaniraneUloge WHERE UlogaId=?', [(int) $_POST['id']]);
            if ($ul && !$ul['Sustavna']) {
                if ($koristi) {
                    poruka(e("Uloga „{$ul['Naziv']}“ je dodijeljena ($koristi) – najprije je uklonite kod članova."), 'warning');
                } else {
                    q('DELETE FROM Uloge WHERE Id=?', [$ul['Id']]);
                    dnevnik('Obrisana uloga', null, null, $ul['Naziv']);
                }
            }
            break;
        case 'funkcije':
            foreach ((array) ($_POST['f'] ?? []) as $id => $f) {
                $n = trim((string) ($f['Naziv'] ?? ''));
                if ($n !== '') {
                    $polja = ['Naziv' => mb_substr($n, 0, 60), 'Redoslijed' => (int) ($f['Redoslijed'] ?? 0), 'OslobodjenClanarine' => isset($f['Osl']) ? 1 : 0];
                    if (array_key_exists('Bodovi', $f)) {
                        // bodovi za funkciju: jednom godišnje na zadani datum, odmah odobreni
                        $staro = red('SELECT Bodovi, BodoviDatum FROM Funkcije WHERE Id=?', [(int) $id]);
                        $b = u_broj($f['Bodovi'] ?? '');
                        $dan = bodovi_dan_unos((string) ($f['BodoviDatum'] ?? ''));
                        if ($b !== null && $b > 0 && !$dan) {
                            poruka(e("„{$n}“: upišite datum (dan.mjesec.) kad se bodovi dodaju."), 'warning');
                        }
                        $polja['Bodovi'] = $b !== null && $b > 0 ? dec($b) : null;
                        $polja['BodoviDatum'] = $polja['Bodovi'] ? $dan : null;
                        if ($staro && ((float) $staro['Bodovi'] !== (float) $polja['Bodovi'] || (string) $staro['BodoviDatum'] !== (string) $polja['BodoviDatum'])) {
                            $polja['BodoviOd'] = $polja['Bodovi'] ? danas() : null; // automatski tek od danas, ništa unatrag
                        }
                    }
                    azuriraj('Funkcije', (int) $id, $polja);
                }
            }
            if (ul_str('NovaFunkcija') !== '') {
                umetni('Funkcije', ['Naziv' => mb_substr(ul_str('NovaFunkcija'), 0, 60), 'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0)+1 FROM Funkcije'), 'OslobodjenClanarine' => 0]);
            }
            dnevnik('Uređene funkcije');
            poruka('Funkcije su spremljene.');
            break;
        case 'funkcija-bodovi-sada':
            $f = red('SELECT * FROM Funkcije WHERE Id=?', [(int) $_POST['id']]);
            $g = (int) date('Y');
            if ($f && funkcija_dospjela($f, $g)) {
                $n = upisi_bodove_funkcije($f, $g, korisnik());
                poruka(e("{$f['Naziv']} – {$g}.: bodovi upisani za $n " . ($n === 1 ? 'člana' : 'članova') . '.'));
            }
            break;
        case 'obrisi-funkciju':
            $n = (int) vrijednost('SELECT COUNT(*) FROM ClanFunkcije WHERE FunkcijaId=?', [(int) $_POST['id']]);
            if ($n) {
                poruka("Funkciju ima ili je imalo $n članova – ne može se obrisati.", 'warning');
            } else {
                q('DELETE FROM Funkcije WHERE Id=?', [(int) $_POST['id']]);
            }
            break;
    }
    preusmjeri('sustav/uloge');
}
$uloge = redovi('SELECT u.*, (SELECT COUNT(*) FROM KorisnikUloge ku WHERE ku.UlogaId=u.Id) AS Broj FROM Uloge u ORDER BY u.Id');
$funkcije = redovi('SELECT f.*, (SELECT COUNT(*) FROM ClanFunkcije cf WHERE cf.FunkcijaId=f.Id AND cf.Do IS NULL) AS Broj,
    (SELECT COUNT(*) FROM FunkcijaBodovi fb WHERE fb.FunkcijaId=f.Id AND fb.Godina=' . (int) date('Y') . ') AS Bodovano FROM Funkcije f ORDER BY f.Redoslijed, f.Naziv');
$sBodovima = modul('akcije');
$grupe = prava_grupe();
$brojPrava = fn(array $u) => count(array_filter(array_merge(...array_values($grupe)), fn($p) => ((int) $u['Prava'] & $p) === $p));
ob_start(); ?>
<h1 class="h3 mb-1">Uloge i prava</h1>
<p class="text-muted"><a class="float-end ms-3" href="<?= e(url('sustav/pregled-prava')) ?>">🔎 Tko ima koja prava</a>Uloge se dodjeljuju na kartici člana (Članovi → član → Pristup i uloge). Sekcije su odvojene: uloga vrijedi samo za odabranu sekciju, osim ako je označeno „Smije sve sekcije“ (npr. Glavni admin, Blagajnik). Obični član bez uloge vidi samo svoje podatke.</p>
<form method="post" action="<?= e(url('sustav/uloge')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="spremi">

<h2 class="h5 mt-4">1. Uloge</h2>
<div class="table-responsive" style="max-width:900px"><table class="table table-sm align-middle">
    <thead><tr><th>Naziv</th><th>Opis</th><th class="text-center small">članova</th><th class="text-center small">prava</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($uloge as $u): $i = (int) $u['Id']; ?>
        <tr>
            <td style="min-width:170px"><input name="u[<?= $i ?>][Naziv]" class="form-control form-control-sm fw-semibold" value="<?= e($u['Naziv']) ?>"></td>
            <td style="min-width:200px"><input name="u[<?= $i ?>][Opis]" class="form-control form-control-sm text-muted" value="<?= e($u['Opis']) ?>" placeholder="opis (neobavezno)"></td>
            <td class="text-center"><?= (int) $u['Broj'] ?></td>
            <td class="text-center small text-muted"><?= $u['Sustavna'] ? 'sva' : $brojPrava($u) ?></td>
            <td class="text-nowrap text-end"><?php if ($u['Sustavna']): ?><span class="small text-muted">sistemska</span>
                <?php else: ?><button class="btn btn-sm btn-link text-danger" form="obrisi<?= $i ?>" data-potvrda="Obrisati ulogu?">Obriši</button><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>

<div class="d-flex flex-wrap align-items-end gap-2 mt-4 mb-2">
    <h2 class="h5 mb-0 me-auto">2. Tko smije što</h2>
    <label class="small text-muted">Prikaži
        <select id="ulogaFilter" data-matrica-filtar="#mat-prava" class="form-select form-select-sm d-inline-block w-auto ms-1">
            <option value="">sve uloge</option>
            <?php foreach ($uloge as $u): ?><option value="<?= (int) $u['Id'] ?>"><?= e($u['Naziv']) ?></option><?php endforeach; ?>
        </select></label>
</div>
<p class="small text-muted mb-2">Redovi su prava (grupirana po područjima), stupci su uloge. Naslovi ostaju vidljivi pri pomicanju. Za opis prava zadržite miš / dodirnite naziv.</p>
<div class="matrica" id="mat-prava"><table class="table table-sm align-middle">
    <thead><tr><th class="prvi">Pravo</th>
        <?php foreach ($uloge as $u): ?><th class="stup" data-u="<?= (int) $u['Id'] ?>"><?= e($u['Naziv']) ?><div class="fw-normal text-muted"><?= (int) $u['Broj'] ?>×</div></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($grupe as $g => $pp): ?>
        <tr class="grupa"><td class="prvi"><?= e($g) ?></td><?php foreach ($uloge as $u): ?><td data-u="<?= (int) $u['Id'] ?>"></td><?php endforeach; ?></tr>
        <?php foreach ($pp as $p): ?>
            <tr><td class="prvi"><span title="<?= e(PRAVA_OPIS[$p][1]) ?>"><?= e(PRAVA_OPIS[$p][0]) ?></span>
                    <div class="small text-muted d-none d-md-block text-truncate" style="max-width:300px" title="<?= e(PRAVA_OPIS[$p][1]) ?>"><?= e(PRAVA_OPIS[$p][1]) ?></div></td>
                <?php foreach ($uloge as $u): $i = (int) $u['Id']; ?>
                    <td class="stup" data-u="<?= $i ?>"><input type="checkbox" class="form-check-input" name="u[<?= $i ?>][p][]" value="<?= $p ?>" title="<?= e($u['Naziv'] . ' – ' . PRAVA_OPIS[$p][0]) ?>"<?= chk(((int) $u['Prava'] & $p) === $p) . ($u['Sustavna'] ? ' disabled' : '') ?>></td>
                <?php endforeach; ?></tr>
        <?php endforeach; ?>
        <?php if ($g === array_key_last($grupe)): ?>
            <tr><td class="prvi">Smije sve sekcije<div class="small text-muted d-none d-md-block">uloga vrijedi za sve sekcije, ne samo za sekciju člana</div></td>
                <?php foreach ($uloge as $u): $i = (int) $u['Id']; ?>
                    <td class="stup" data-u="<?= $i ?>"><input type="checkbox" class="form-check-input" name="u[<?= $i ?>][SveSekcije]" value="1" title="<?= e($u['Naziv'] . ' – smije sve sekcije') ?>"<?= chk($u['SveSekcije']) . ($u['Sustavna'] ? ' disabled' : '') ?>></td>
                <?php endforeach; ?></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
</table></div>
<div class="matrica-spremi"><button class="btn btn-primary">Spremi</button> <span class="small text-muted ms-2">Ne zaboravite spremiti nakon promjene.</span></div>
</form>
<?php foreach ($uloge as $u): if ($u['Sustavna']) continue; ?>
    <form method="post" action="<?= e(url('sustav/uloge')) ?>" id="obrisi<?= (int) $u['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= (int) $u['Id'] ?>"></form>
<?php endforeach; ?>
<form method="post" action="<?= e(url('sustav/uloge')) ?>" class="row g-2 align-items-end mt-3" style="max-width:560px"><?= csrf() ?><input type="hidden" name="radnja" value="dodaj">
    <div class="col-8"><label class="form-label">Nova uloga</label><input name="Naziv" class="form-control" maxlength="60" required placeholder="npr. Mali administrator"></div>
    <div class="col-4"><button class="btn btn-outline-primary w-100">Dodaj</button></div>
</form>

<h2 class="h4 mt-5 mb-1" id="funkcije">Funkcije u udruzi</h2>
<p class="text-muted">Funkcije se upisuju na kartici člana (Funkcije). „Oslobođen članarine“ npr. za počasne članove – takvi se ne zadužuju članarinom.<?php if ($sBodovima): ?>
    <br>🏅 <b>Bodovi za funkciju</b> se dodaju <b>jednom godišnje</b> na upisani datum, svima koji tada imaju funkciju – <b>odmah odobreno</b>, bez odobravanja. Dan resetiranja i prijenos bodova: <a href="<?= e(url('sustav/bodovi')) ?>">Sustav → Bodovi</a>.<?php endif; ?></p>
<form method="post" action="<?= e(url('sustav/uloge')) ?>" style="max-width:820px"><?= csrf() ?><input type="hidden" name="radnja" value="funkcije">
<table class="table table-sm align-middle">
    <thead><tr><th style="width:6rem">Redoslijed</th><th>Naziv</th><th class="text-center">Oslobođen članarine</th><?php if ($sBodovima): ?><th class="text-center">Bodovi</th><?php endif; ?><th class="small text-muted">trenutno</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($funkcije as $f): $i = (int) $f['Id']; $imaB = (float) $f['Bodovi'] > 0 && $f['BodoviDatum']; ?>
        <tr><td><input name="f[<?= $i ?>][Redoslijed]" type="number" class="form-control form-control-sm" value="<?= (int) $f['Redoslijed'] ?>"></td>
            <td><input name="f[<?= $i ?>][Naziv]" class="form-control form-control-sm" value="<?= e($f['Naziv']) ?>"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="f[<?= $i ?>][Osl]" value="1"<?= chk($f['OslobodjenClanarine']) ?>></td>
            <?php if ($sBodovima): ?><td class="text-center text-nowrap"><button type="button" class="btn btn-sm <?= $imaB ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                onclick="var r=document.getElementById('fb<?= $i ?>');r.classList.toggle('d-none');if(!r.classList.contains('d-none'))r.querySelector('input').focus()">🏅 <?= $imaB ? e(broj((float) $f['Bodovi'], 2)) . ' · ' . e(bodovi_dan_prikaz($f['BodoviDatum'])) : 'bodovi' ?></button></td><?php endif; ?>
            <td class="small text-muted"><?= (int) $f['Broj'] ?></td>
            <td><button class="btn btn-sm btn-link text-danger" form="obrisiF<?= $i ?>" data-potvrda="Obrisati funkciju?">Obriši</button></td></tr>
        <?php if ($sBodovima): $dosp = $imaB ? funkcija_dospjela($f, (int) date('Y')) : null; ?>
        <tr id="fb<?= $i ?>" class="d-none"><td></td><td colspan="5" class="bg-body-tertiary">
            <div class="d-flex flex-wrap align-items-end gap-2 py-1">
                <div><label class="form-label small mb-0">Bodova godišnje</label>
                    <input name="f[<?= $i ?>][Bodovi]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" style="width:7rem" value="<?= $f['Bodovi'] !== null ? e((string) (float) $f['Bodovi']) : '' ?>" placeholder="0"></div>
                <div><label class="form-label small mb-0">Dodaje se svake godine</label>
                    <input name="f[<?= $i ?>][BodoviDatum]" class="form-control form-control-sm" style="width:7rem" value="<?= e(bodovi_dan_prikaz($f['BodoviDatum'])) ?>" placeholder="dan.mjesec. npr. 1.1."></div>
                <div class="small text-muted pb-1">prazno ili 0 = bez bodova · broje se u bodovnu godinu u kojoj je taj datum</div>
                <?php if ($dosp && (int) $f['Bodovano'] < (int) $f['Broj']): ?>
                    <button class="btn btn-sm btn-outline-success ms-auto" form="bodF<?= $i ?>" data-potvrda="Upisati bodove za <?= date('Y') ?>. (datum <?= e(datum($dosp)) ?>) svima koji su tada imali ovu funkciju?">Upiši sada za <?= date('Y') ?>.</button>
                <?php elseif ($imaB && (int) $f['Bodovano']): ?><span class="small text-success ms-auto pb-1">✓ <?= date('Y') ?>.: upisano <?= (int) $f['Bodovano'] ?>×</span><?php endif; ?>
            </div></td></tr>
        <?php endif; ?>
    <?php endforeach; ?>
    <tr><td></td><td><input name="NovaFunkcija" class="form-control form-control-sm" placeholder="+ nova funkcija"></td><td colspan="<?= $sBodovima ? 4 : 3 ?>"></td></tr>
    </tbody>
</table>
<button class="btn btn-primary">Spremi funkcije</button>
</form>
<?php foreach ($funkcije as $f): ?>
    <form method="post" action="<?= e(url('sustav/uloge')) ?>" id="obrisiF<?= (int) $f['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-funkciju"><input type="hidden" name="id" value="<?= (int) $f['Id'] ?>"></form>
    <form method="post" action="<?= e(url('sustav/uloge')) ?>" id="bodF<?= (int) $f['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="funkcija-bodovi-sada"><input type="hidden" name="id" value="<?= (int) $f['Id'] ?>"></form>
<?php endforeach; ?>
<?php stranica('Uloge i prava', ob_get_clean());
