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
$kratko = [P_CLANOVI_CITAJ => 'Članovi<br>pregled', P_CLANOVI_UREDI => 'Članovi<br>uređ.', P_CLANARINA_CITAJ => 'Članarina<br>pregled', P_CLANARINA_UREDI => 'Članarina<br>uređ.',
    P_AKCIJE_CITAJ => 'Akcije<br>pregled', P_AKCIJE_ODOBRI => 'Akcije<br>unos/odobr.', P_IZVJESTAJI => 'Izvještaji<br>poruke', P_SUSTAV => 'Sustav',
    P_KALENDAR => 'Kalendar<br>uređ.', P_IMENIK_SVI => 'Imenik<br>svi kontakti',
    P_DEZ_PREGLED => 'Dezinf.<br>pregled', P_DEZ_UREDI => 'Dezinf.<br>naknadno', P_DEZ_POSTAVKE => 'Dezinf.<br>stanice/QR', P_DEZ_MOBILNA => 'Dezinf.<br>mobilna', P_DEZ_ZA_DRUGE => 'Dezinf.<br>upis za druge',
    P_REVIR_UREDI => 'Lovište<br>naprave', P_REVIR_NADZOR => 'Lovište<br>nadzor'];
ob_start(); ?>
<h1 class="h3 mb-1">Uloge i prava</h1>
<p class="text-muted">Uloge se dodjeljuju na kartici člana (Članovi → član → Pristup i uloge). Sekcije su odvojene: uloga vrijedi samo za odabranu sekciju, osim ako je označeno „Smije sve sekcije“ (npr. Glavni admin, Blagajnik). Ne zaboravite „Spremi“ nakon promjene. Obični član bez uloge vidi samo svoje podatke.</p>
<form method="post" action="<?= e(url('sustav/uloge')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="spremi">
<div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Uloga</th>
        <?php foreach ($kratko as $p => $t): ?><th class="text-center small" title="<?= e(PRAVA_OPIS[$p][1]) ?>"><?= $t ?></th><?php endforeach; ?>
        <th class="text-center small">Smije sve sekcije</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($uloge as $u): $i = (int) $u['Id']; $dis = $u['Sustavna'] ? ' disabled' : ''; ?>
        <tr>
            <td style="min-width:200px"><input name="u[<?= $i ?>][Naziv]" class="form-control form-control-sm" value="<?= e($u['Naziv']) ?>">
                <input name="u[<?= $i ?>][Opis]" class="form-control form-control-sm mt-1 text-muted" value="<?= e($u['Opis']) ?>" placeholder="opis (neobavezno)"></td>
            <?php foreach ($kratko as $p => $t): ?>
                <td class="text-center"><input type="checkbox" class="form-check-input" name="u[<?= $i ?>][p][]" value="<?= $p ?>"<?= chk(((int) $u['Prava'] & $p) === $p) . $dis ?>></td>
            <?php endforeach; ?>
            <td class="text-center"><input type="checkbox" class="form-check-input" name="u[<?= $i ?>][SveSekcije]" value="1"<?= chk($u['SveSekcije']) . $dis ?>></td>
            <td class="text-nowrap small"><?php if ($u['Sustavna']): ?><span class="small text-muted">sistemska</span>
                <?php else: ?><button class="btn btn-sm btn-link text-danger" form="obrisi<?= $i ?>" data-potvrda="Obrisati ulogu?">Obriši</button><?php endif; ?>
                <div class="text-muted"><?= (int) $u['Broj'] ?>×</div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<button class="btn btn-primary">Spremi</button>
</form>
<?php foreach ($uloge as $u): if ($u['Sustavna']) continue; ?>
    <form method="post" action="<?= e(url('sustav/uloge')) ?>" id="obrisi<?= (int) $u['Id'] ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="id" value="<?= (int) $u['Id'] ?>"></form>
<?php endforeach; ?>
<form method="post" action="<?= e(url('sustav/uloge')) ?>" class="row g-2 align-items-end mt-3" style="max-width:560px"><?= csrf() ?><input type="hidden" name="radnja" value="dodaj">
    <div class="col-8"><label class="form-label">Nova uloga</label><input name="Naziv" class="form-control" maxlength="60" required placeholder="npr. Mali administrator"></div>
    <div class="col-4"><button class="btn btn-outline-primary w-100">Dodaj</button></div>
</form>
<ul class="small text-muted mt-3">
    <?php foreach (PRAVA_OPIS as [$n, $o]): ?><li><b><?= e($n) ?></b> – <?= e($o) ?></li><?php endforeach; ?>
</ul>

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
