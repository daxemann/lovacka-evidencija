<?php
/** Pregled svih računa (dodjela pristupa samo preko kartice člana). */
trazi(P_SUSTAV);
$racuni = redovi('SELECT k.*, c.Ime, c.Prezime, c.Status AS ClanStatus, s.Naziv AS Sekcija FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
                  ORDER BY k.Odobren, c.Prezime IS NULL, c.Prezime, c.Ime, k.KorisnickoIme');
$uloge = [];
foreach (redovi('SELECT ku.KorisnikId, u.Naziv, s.Naziv AS Sekcija FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId LEFT JOIN Sekcije s ON s.Id=ku.SekcijaId') as $u) {
    $uloge[(int) $u['KorisnikId']][] = $u['Naziv'] . ($u['Sekcija'] ? ' · ' . $u['Sekcija'] : '');
}
$cekaju = array_filter($racuni, fn($r) => !$r['Odobren'] && $r['Aktivan']);
$bezRacuna = (int) vrijednost('SELECT COUNT(*) FROM Clanovi c WHERE c.Status=0 AND NOT EXISTS (SELECT 1 FROM Korisnici k WHERE k.ClanId=c.Id)');
$pozivnice = (int) vrijednost('SELECT COUNT(*) FROM Pozivnice WHERE Iskoristena IS NULL AND Opozvana=0 AND VrijediDo>?', [sada()]);
ob_start(); ?>
<h1 class="h3 mb-1">Korisnici (pregled)</h1>
<p class="text-muted">Pristup se dodjeljuje samo na jednom mjestu: <b>Članovi → član → Pristup i uloge</b> (pozivnica, odobrenje, uloge, lozinka, brisanje). Klik na redak otvara člana.</p>
<?php if ($cekaju): ?>
    <div class="alert alert-warning"><b>Čekaju odobrenje:</b>
        <?php foreach ($cekaju as $r): ?> <a href="<?= e(url('clanovi/uredi', ['id' => $r['ClanId'], 'kartica' => 'racun'])) ?>" class="ms-2"><?= e(trim($r['Ime'] . ' ' . $r['Prezime'])) ?> (<?= e($r['KorisnickoIme']) ?>)</a><?php endforeach; ?></div>
<?php endif; ?>
<div class="d-flex flex-wrap gap-4 mb-2 small"><span>Računa: <b><?= count($racuni) ?></b></span><span>Aktivnih članova bez računa: <b><?= $bezRacuna ?></b></span><span>Važećih pozivnica (čekaju člana): <b><?= $pozivnice ?></b></span></div>
<div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Član</th><th>Sekcija</th><th>Korisničko ime</th><th>Status</th><th>Uloge</th><th>Zadnja prijava</th></tr></thead>
    <tbody>
    <?php foreach ($racuni as $r): $href = $r['ClanId'] ? url('clanovi/uredi', ['id' => $r['ClanId'], 'kartica' => 'racun']) : ''; ?>
        <tr class="<?= $href ? 'klikabilno' : '' ?>" <?= $href ? 'onclick="location.href=\'' . e($href) . '\'"' : '' ?>>
            <td><?= $r['ClanId'] ? e(trim($r['Prezime'] . ' ' . $r['Ime'])) : e($r['PrikaznoIme'] ?: '—') . ' <span class="badge bg-danger ms-1" title="Račun nije povezan s članom">nije povezan</span>' ?></td>
            <td><?= e($r['Sekcija'] ?? '') ?></td>
            <td><?= e($r['KorisnickoIme']) ?></td>
            <td><?php if ($r['ClanStatus'] !== null && (int) $r['ClanStatus'] === CLAN_ARHIVIRAN): ?><span class="badge bg-secondary">arhiviran</span>
                <?php elseif (!$r['Odobren']): ?><span class="badge bg-warning text-dark">čeka odobrenje</span>
                <?php elseif (!$r['Aktivan']): ?><span class="badge bg-secondary">zaključan</span>
                <?php else: ?><span class="badge bg-success">aktivan</span><?php endif; ?></td>
            <td class="small"><?php foreach ($uloge[(int) $r['Id']] ?? [] as $u): ?><span class="badge bg-success me-1"><?= e($u) ?></span><?php endforeach; ?></td>
            <td class="small"><?= e(datum_vrijeme($r['ZadnjaPrijava'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="small text-muted">„Nije povezan“: račun iz prvog pokretanja još nema člana istog imena. Čim se član s tim imenom upiše (ili uveze), račun se automatski poveže.</p>
<?php stranica('Korisnici', ob_get_clean());
