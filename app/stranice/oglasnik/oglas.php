<?php
/** Detalj oglasa: slike, podaci, poruka prodavatelju, upiti kupaca (za vlasnika). */
$k = korisnik();
$id = (int) ul('id', 0);
$o = oglas($id);
if (!$o || ((int) $o['Status'] === 3 && (int) $o['KorisnikId'] !== $k['Id'] && !ima(P_SUSTAV))) {
    nije_pronadjeno('Oglas');
}
$moj = (int) $o['KorisnikId'] === $k['Id'];
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'pisi' && !$moj) {
        try {
            $rid = pisi_prodavatelju($id, $k['Id'], (string) ($_POST['tekst'] ?? ''));
            preusmjeri('poruke/razgovor', ['id' => $rid]);
        } catch (Throwable $e) {
            poruka(e($e->getMessage()), 'warning');
        }
    } elseif ($radnja === 'status' && $moj) {
        $s = (int) ($_POST['status'] ?? 0);
        if (in_array($s, [0, 1, 2], true)) {
            azuriraj('Oglasi', $id, ['Status' => $s, 'Azurirano' => sada()]);
        }
    } elseif ($radnja === 'obrisi' && ($moj || ima(P_SUSTAV))) {
        obrisi_oglas($id);
        dnevnik($moj ? 'Obrisan oglas' : 'Administrator uklonio oglas', 'Oglas', $id, $o['Naslov']);
        poruka('Oglas je obrisan.');
        preusmjeri('oglasnik');
    }
    preusmjeri('oglasnik/oglas', ['id' => $id]);
}
if (!$moj) {
    q('UPDATE Oglasi SET Pregleda=Pregleda+1 WHERE Id=?', [$id]);
    $o['Pregleda']++;
}
$slike = slike_oglasa($id);
$prod = red('SELECT k.Id, k.ClanId, c.MobilniTelefon, c.FiksniTelefon, s.Naziv AS Sekcija FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE k.Id=?', [$o['KorisnikId']]);
$telefon = $o['PrikaziTelefon'] && $prod && $prod['MobilniTelefon'] ? $prod['MobilniTelefon'] : '';
$mojRazgovor = !$moj ? vrijednost('SELECT Id FROM Razgovori WHERE OglasId=? AND KupacId=?', [$id, $k['Id']]) : null;
$st = (int) $o['Status'];
ob_start(); ?>
<a href="<?= e(url('oglasnik')) ?>" class="small">← Oglasnik</a>
<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <?php if ($slike): ?>
            <img src="<?= e(foto_url($slike[0])) ?>" class="oglas-velika" id="velika" alt="">
            <?php if (count($slike) > 1): ?><div class="d-flex flex-wrap gap-2 mt-2">
                <?php foreach ($slike as $i => $s): ?><img src="<?= e(foto_url($s)) ?>" class="oglas-palac <?= $i === 0 ? 'odabrana' : '' ?>" alt=""
                    onclick="document.getElementById('velika').src=this.src;document.querySelectorAll('.oglas-palac').forEach(x=>x.classList.remove('odabrana'));this.classList.add('odabrana')"><?php endforeach; ?>
            </div><?php endif; ?>
        <?php else: ?><div class="oglas-velika oglas-bez-slike">bez slike</div><?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="d-flex align-items-start gap-2">
            <h1 class="h4 mb-1 me-auto"><?= e($o['Naslov']) ?></h1>
            <?php if ($st): ?><span class="badge <?= $st === 2 ? 'bg-dark' : 'bg-warning text-dark' ?>"><?= e(STATUSI_OGLASA[$st]) ?></span><?php endif; ?>
        </div>
        <div class="fs-3 fw-bold text-success mb-2"><?= e(cijena_oglasa($o)) ?></div>
        <ul class="list-group list-group-flush small mb-3">
            <li class="list-group-item d-flex px-0"><span class="text-muted">Kategorija</span><span class="ms-auto"><?= e(KATEGORIJE_OGLASA[(int) $o['Kategorija']] ?? '') ?></span></li>
            <li class="list-group-item d-flex px-0"><span class="text-muted">Stanje</span><span class="ms-auto"><?= e(STANJA_ARTIKLA[(int) $o['Stanje']] ?? '') ?></span></li>
            <?php if ($o['Mjesto']): ?><li class="list-group-item d-flex px-0"><span class="text-muted">Mjesto</span><span class="ms-auto"><?= e($o['Mjesto']) ?></span></li><?php endif; ?>
            <li class="list-group-item d-flex px-0"><span class="text-muted">Prodavatelj</span><span class="ms-auto"><?= e(ime_korisnika((int) $o['KorisnikId'])) ?><?= $prod && $prod['Sekcija'] ? ' · ' . e($prod['Sekcija']) : '' ?></span></li>
            <li class="list-group-item d-flex px-0"><span class="text-muted">Objavljeno</span><span class="ms-auto"><?= e(datum($o['Kreirano'])) ?> · <?= (int) $o['Pregleda'] ?> pregleda</span></li>
        </ul>
        <?php if (treba_dozvolu((int) $o['Kategorija'])): ?><div class="alert alert-warning small">Kupnja oružja / streljiva samo uz važeće odobrenje, prijenos preko policijske uprave.</div><?php endif; ?>
        <?php if (!$moj && $st <= 1): ?>
            <div class="card mb-3"><div class="card-body">
                <?php if ($mojRazgovor): ?>
                    <a href="<?= e(url('poruke/razgovor', ['id' => $mojRazgovor])) ?>" class="btn btn-primary w-100 mb-2">Otvori razgovor s prodavateljem</a>
                <?php else: ?>
                    <form method="post" action="<?= e(url('oglasnik/oglas', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="pisi">
                        <label class="form-label small">Poruka prodavatelju</label>
                        <textarea name="tekst" class="form-control mb-2" rows="3" maxlength="2000" required>Pozdrav, zanima me „<?= e($o['Naslov']) ?>“. Je li još dostupno?</textarea>
                        <button class="btn btn-primary w-100">Pošalji poruku</button></form>
                <?php endif; ?>
                <?php if ($telefon): ?>
                    <div class="d-flex gap-2 mt-2">
                        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $telefon)) ?>" class="btn btn-outline-secondary w-50">Nazovi</a>
                        <a href="https://wa.me/<?= e((string) whatsapp_broj(['MobilniTelefon' => $telefon, 'FiksniTelefon' => null])) ?>?text=<?= rawurlencode('Pozdrav, javljam se za oglas „' . $o['Naslov'] . '“.') ?>" target="_blank" rel="noopener" class="btn btn-outline-success w-50">WhatsApp</a>
                    </div>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>
        <?php if ($moj): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="<?= e(url('oglasnik/uredi', ['id' => $id])) ?>" class="btn btn-outline-primary">Uredi</a>
                <?php foreach ([0 => ['Ponovno aktivan', 'btn-outline-success'], 1 => ['Rezervirano', 'btn-outline-warning'], 2 => ['Prodano', 'btn-outline-dark']] as $s => [$t, $kl]): if ($s === $st) continue; ?>
                    <form method="post" action="<?= e(url('oglasnik/oglas', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="status"><input type="hidden" name="status" value="<?= $s ?>">
                        <button class="btn <?= $kl ?>"><?= $t ?></button></form>
                <?php endforeach; ?>
            </div>
            <?php $razg = redovi('SELECT r.Id, r.KupacId, r.ZadnjaPoruka, (SELECT COUNT(*) FROM PorukeRazgovora p WHERE p.RazgovorId=r.Id AND p.PosiljateljId<>? AND p.Procitano=0) AS Novih
                                  FROM Razgovori r WHERE r.OglasId=? ORDER BY r.ZadnjaPoruka DESC', [$k['Id'], $id]); ?>
            <div class="card mb-3"><div class="card-header">Upiti kupaca</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($razg as $r): ?>
                        <a href="<?= e(url('poruke/razgovor', ['id' => $r['Id']])) ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                            <?= e(ime_korisnika((int) $r['KupacId'])) ?><?php if ($r['Novih']): ?><span class="badge bg-danger ms-2"><?= (int) $r['Novih'] ?></span><?php endif; ?>
                            <span class="ms-auto small text-muted"><?= e(date('d.m. H:i', strtotime($r['ZadnjaPoruka']))) ?></span></a>
                    <?php endforeach; ?>
                    <?php if (!$razg): ?><li class="list-group-item text-muted small">Još nema upita.</li><?php endif; ?>
                </ul></div>
        <?php endif; ?>
        <?php if ($moj || ima(P_SUSTAV)): ?>
            <form method="post" action="<?= e(url('oglasnik/oglas', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi">
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Sigurno? Briše oglas, slike i poruke."><?= $moj ? 'Obriši oglas' : 'Ukloni oglas (administrator)' ?></button></form>
        <?php endif; ?>
    </div>
    <?php if (trim((string) $o['Opis']) !== ''): ?>
        <div class="col-12"><h2 class="h6 text-uppercase text-muted">Opis</h2><div style="white-space:pre-wrap"><?= e($o['Opis']) ?></div></div>
    <?php endif; ?>
</div>
<?php stranica($o['Naslov'], ob_get_clean());
