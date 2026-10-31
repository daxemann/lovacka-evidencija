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
                    azuriraj('Funkcije', (int) $id, ['Naziv' => mb_substr($n, 0, 60), 'Redoslijed' => (int) ($f['Redoslijed'] ?? 0), 'OslobodjenClanarine' => isset($f['Osl']) ? 1 : 0]);
                }
            }
            if (ul_str('NovaFunkcija') !== '') {
                umetni('Funkcije', ['Naziv' => mb_substr(ul_str('NovaFunkcija'), 0, 60), 'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),0)+1 FROM Funkcije'), 'OslobodjenClanarine' => 0]);
            }
            dnevnik('Uređene funkcije');
            poruka('Funkcije su spremljene.');
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
$funkcije = redovi('SELECT f.*, (SELECT COUNT(*) FROM ClanFunkcije cf WHERE cf.FunkcijaId=f.Id AND cf.Do IS NULL) AS Broj FROM Funkcije f ORDER BY f.Redoslijed, f.Naziv');
// prava grupirana po područjima – redovi tablice (uloge su stupci)
$grupe = [
    '👥 Članovi i udruga' => [P_CLANOVI_CITAJ, P_CLANOVI_UREDI, P_IMENIK_SVI, P_CLANARINA_CITAJ, P_CLANARINA_UREDI, P_AKCIJE_CITAJ, P_AKCIJE_ODOBRI],
    '📢 Kalendar, obavijesti, izvještaji' => [P_KALENDAR, P_OBAVIJESTI, P_IZVJESTAJI],
    '🧴 Dezinfekcija' => [P_DEZ_PREGLED, P_DEZ_UREDI, P_DEZ_POSTAVKE, P_DEZ_MOBILNA, P_DEZ_ZA_DRUGE],
    '🌲 Lovište i lov' => [P_REVIR_UREDI, P_REVIR_NADZOR, P_REVIR_DNEVNIK, P_GRUPNI_LOV, P_ODSTREL_CITAJ, P_ODSTREL],
    '📦 Inventura, domovi, popusti' => [P_INVENTURA, P_INVENTURA_UREDI, P_DOMOVI, P_POPUSTI],
    '⚙️ Administracija' => [P_SUSTAV],
];
$skriveno = moduli_iskljucena_prava(); // isključeni moduli – redovi skriveni
foreach ($grupe as $g => $pp) {
    $grupe[$g] = array_values(array_filter($pp, fn($p) => !($p & $skriveno)));
    if (!$grupe[$g]) {
        unset($grupe[$g]);
    }
}
$brojPrava = fn(array $u) => count(array_filter(array_merge(...array_values($grupe)), fn($p) => ((int) $u['Prava'] & $p) === $p));
ob_start(); ?>
<h1 class="h3 mb-1">Uloge i prava</h1>
<p class="text-muted">Uloge se dodjeljuju na kartici člana (Članovi → član → Pristup i uloge). Sekcije su odvojene: uloga vrijedi samo za odabranu sekciju, osim ako je označeno „Smije sve sekcije“ (npr. Glavni admin, Blagajnik). Obični član bez uloge vidi samo svoje podatke.</p>
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

<h2 class="h4 mt-5 mb-1">Funkcije u udruzi</h2>
<p class="text-muted">Funkcije se upisuju na kartici člana (Funkcije). „Oslobođen članarine“ npr. za počasne članove – takvi se ne zadužuju članarinom.</p>
<form method="post" action="<?= e(url('sustav/uloge')) ?>" style="max-width:760px"><?= csrf() ?><input type="hidden" name="radnja" value="funkcije">
<table class="table table-sm align-middle">
    <thead><tr><th style="width:6rem">Redoslijed</th><th>Naziv</th><th class="text-center">Oslobođen članarine</th><th class="small text-muted">trenutno</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($funkcije as $f): $i = (int) $f['Id']; ?>
        <tr><td><input name="f[<?= $i ?>][Redoslijed]" type="number" class="form-control form-control-sm" value="<?= (int) $f['Redoslijed'] ?>"></td>
            <td><input name="f[<?= $i ?>][Naziv]" class="form-control form-control-sm" value="<?= e($f['Naziv']) ?>"></td>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="f[<?= $i ?>][Osl]" value="1"<?= chk($f['OslobodjenClanarine']) ?>></td>
            <td class="small text-muted"><?= (int) $f['Broj'] ?></td>
            <td><button class="btn btn-sm btn-link text-danger" form="obrisiF<?= $i ?>" data-potvrda="Obrisati funkciju?">Obriši</button></td></tr>
    <?php endforeach; ?>
    <tr><td></td><td><input name="NovaFunkcija" class="form-control form-control-sm" placeholder="+ nova funkcija"></td><td colspan="3"></td></tr>
    </tbody>
</table>
<button class="btn btn-primary">Spremi funkcije</button>
</form>
<?php foreach ($funkcije as $f): ?>
    <form method="post" action="<?= e(url('sustav/uloge')) ?>" id="obrisiF<?= (int) $f['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-funkciju"><input type="hidden" name="id" value="<?= (int) $f['Id'] ?>"></form>
<?php endforeach; ?>
<?php stranica('Uloge i prava', ob_get_clean());
