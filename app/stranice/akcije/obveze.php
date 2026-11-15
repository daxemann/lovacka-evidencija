<?php
/** 📅 Godišnje obveze: ovlaštena osoba uključuje / isključuje članove (bodovi 1× godišnje, odmah odobreno). */
trazi(P_CLANOVI_UREDI);
$k = korisnik();
$g = (int) date('Y');
$opseg = opseg_sql_prava(P_CLANOVI_UREDI, 'c');
/** Upis obveze člana u opsegu ili null. */
$upis = fn(int $id) => red('SELECT oc.*, c.Ime, c.Prezime, c.SekcijaId FROM ObvezaClanovi oc JOIN Clanovi c ON c.Id=oc.ClanId WHERE oc.Id=? AND ' . $opseg, [$id]);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'dodaj') {
        $o = red('SELECT * FROM GodisnjeObveze WHERE Id=?', [(int) ($_POST['obveza'] ?? 0)]);
        $c = red('SELECT * FROM Clanovi c WHERE c.Id=? AND c.Status=0 AND ' . $opseg, [(int) ($_POST['clan'] ?? 0)]);
        if ($o && $c) {
            $id = umetni('ObvezaClanovi', ['ObvezaId' => (int) $o['Id'], 'ClanId' => (int) $c['Id'], 'Opis' => mb_substr(ul_str('opis'), 0, 120) ?: null,
                'Aktivan' => 1, 'AktivnoOd' => danas(), 'Kreirano' => sada()]);
            dnevnik('Obveza – član uključen', 'Clan', (int) $c['Id'], $o['Naziv'] . ': ' . puno_ime($c));
            $n = upisi_bodove_obveze($o, $g, $k, $id);
            poruka('Uključeno: ' . puno_ime($c) . ($n ? " – bodovi za $g. upisani (odobreno)." : ' – bodovi se upisuju sami ' . bodovi_dan_prikaz($o['Dan']) . '.'));
        }
    } elseif (in_array($radnja, ['ukljuci', 'iskljuci', 'ukloni'], true) && ($u = $upis((int) ($_POST['id'] ?? 0)))) {
        $o = red('SELECT * FROM GodisnjeObveze WHERE Id=?', [(int) $u['ObvezaId']]);
        if ($radnja === 'ukloni') {
            q('DELETE FROM ObvezaClanovi WHERE Id=?', [(int) $u['Id']]); // već upisani bodovi ostaju
            dnevnik('Obveza – član uklonjen', 'Clan', (int) $u['ClanId'], $o['Naziv'] . ': ' . prezime_ime($u));
            poruka('Uklonjeno. Već upisani bodovi ostaju.');
        } else {
            $da = $radnja === 'ukljuci';
            azuriraj('ObvezaClanovi', (int) $u['Id'], $da ? ['Aktivan' => 1, 'AktivnoOd' => danas()] : ['Aktivan' => 0]);
            dnevnik($da ? 'Obveza – član uključen' : 'Obveza – član isključen', 'Clan', (int) $u['ClanId'], $o['Naziv'] . ': ' . prezime_ime($u));
            $n = $da ? upisi_bodove_obveze($o, $g, $k, (int) $u['Id']) : 0;
            poruka($da ? 'Uključeno' . ($n ? " – bodovi za $g. upisani (odobreno)." : '.') : 'Isključeno – od iduće godine nema bodova.');
        }
    }
    preusmjeri('akcije/obveze');
}

$obveze = redovi('SELECT * FROM GodisnjeObveze ORDER BY Aktivna DESC, Naziv');
$clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c WHERE c.Status=0 AND ' . $opseg . ' ORDER BY c.Prezime, c.Ime');
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">📅 Godišnje obveze</h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('akcije')) ?>">← Radne akcije</a>
    <?php if (ima(P_SUSTAV)): ?><a class="btn btn-outline-secondary" href="<?= e(url('sustav/bodovi')) ?>#obveze">Obveze i bodovi</a><?php endif; ?>
</div>
<p class="text-muted small" style="max-width:760px">Dodjela bodova za razne obveze jednom godišnje (npr. briga o hranilištu, održavanje čeke). Uključen član dobiva bodove na zadani dan – <b>odmah odobreno</b>;
    uključen nakon tog dana dobiva ih odmah. Svaki najviše jednom godišnje. Lovački psi se uključuju na kartici psa kod člana.</p>
<?php if (!$obveze): ?>
    <div class="alert alert-info">Još nema obveza.<?php if (ima(P_SUSTAV)): ?> Dodajte ih u <a href="<?= e(url('sustav/bodovi')) ?>#obveze">Sustav → Bodovi</a>.<?php endif; ?></div>
<?php endif; ?>
<?php foreach ($obveze as $o):
    $oid = (int) $o['Id'];
    $lista = redovi('SELECT oc.*, c.Ime, c.Prezime, (SELECT 1 FROM ObvezaBodovi b WHERE b.ObvezaClanId=oc.Id AND b.Godina=?) AS Upisano
        FROM ObvezaClanovi oc JOIN Clanovi c ON c.Id=oc.ClanId WHERE oc.ObvezaId=? AND c.Status=0 AND ' . $opseg . ' ORDER BY oc.Aktivan DESC, c.Prezime, c.Ime', [$g, $oid]); ?>
    <div class="card mb-3<?= $o['Aktivna'] ? '' : ' opacity-75' ?>" id="o<?= $oid ?>"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0"><?= e($o['Naziv']) ?></h2>
            <span class="badge text-bg-light border"><?= e(broj((float) $o['Bodovi'], 2)) ?> b. · svake godine <?= e(bodovi_dan_prikaz($o['Dan'])) ?></span>
            <?php if (!$o['Aktivna']): ?><span class="badge bg-secondary">isključena</span><?php endif; ?>
            <?php if ((float) $o['Bodovi'] <= 0): ?><span class="badge bg-warning text-dark">0 bodova – ništa se ne upisuje</span><?php endif; ?>
        </div>
        <?php if ($lista): ?>
        <table class="table table-sm align-middle mb-2"><tbody>
        <?php foreach ($lista as $u): $btn = fn(string $r, string $t, string $kl, string $p = '') => '<form method="post" class="d-inline">' . csrf() . '<input type="hidden" name="radnja" value="' . $r . '"><input type="hidden" name="id" value="' . (int) $u['Id'] . '"><button class="btn btn-sm ' . $kl . '"' . ($p ? ' data-potvrda="' . e($p) . '"' : '') . '>' . $t . '</button></form>'; ?>
            <tr class="<?= $u['Aktivan'] ? '' : 'text-muted' ?>">
                <td><a href="<?= e(url('clanovi/uredi', ['id' => $u['ClanId'], 'kartica' => 'akcije'])) ?>"><?= e(prezime_ime($u)) ?></a><?php if ($u['Opis']): ?> <span class="small text-muted">· <?= e($u['Opis']) ?></span><?php endif; ?></td>
                <td class="small"><?= $u['Upisano'] ? '<span class="badge bg-success-subtle text-success-emphasis border">bodovi ' . $g . '. upisani</span>' : ($u['Aktivan'] ? '<span class="badge text-bg-light border">upisuju se ' . e(bodovi_dan_prikaz($o['Dan'])) . '</span>' : '<span class="badge text-bg-light border">isključen</span>') ?></td>
                <td class="text-end text-nowrap">
                    <?= $u['Aktivan'] ? $btn('iskljuci', '⏸️ isključi', 'btn-link p-0', 'Isključiti? Već upisani bodovi ostaju.') : $btn('ukljuci', '✅ uključi', 'btn-outline-success py-0', 'Uključiti? Ako je dan već prošao, bodovi za ' . $g . '. upisuju se odmah (odobreno).') ?>
                    <?= $btn('ukloni', '✕', 'btn-link text-danger p-0 ms-2', 'Ukloniti člana s obveze? Već upisani bodovi ostaju.') ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php else: ?><p class="small text-muted mb-2">Nijedan član nije uključen.</p><?php endif; ?>
        <form method="post" class="row g-1 align-items-end"><?= csrf() ?><input type="hidden" name="radnja" value="dodaj"><input type="hidden" name="obveza" value="<?= $oid ?>">
            <div class="col-sm-5"><select name="clan" class="form-select form-select-sm" required><option value="">+ uključi člana …</option>
                <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"><?= e(prezime_ime($c)) ?></option><?php endforeach; ?></select></div>
            <div class="col-sm-5"><input name="opis" class="form-control form-control-sm" maxlength="120" placeholder="napomena, npr. hranilište Bukovica 3"></div>
            <div class="col-sm-2"><button class="btn btn-sm btn-success w-100" data-potvrda="Uključiti člana? Ako je dan već prošao, bodovi za <?= $g ?>. upisuju se odmah (odobreno).">Uključi</button></div>
        </form>
    </div></div>
<?php endforeach; ?>
<?php stranica('Godišnje obveze', ob_get_clean());
