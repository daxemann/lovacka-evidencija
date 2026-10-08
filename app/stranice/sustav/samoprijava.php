<?php
/** Zajednička poveznica za samoprijavu članova + obrada prijava koje čekaju. */
trazi(P_SUSTAV);
$k = korisnik();
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $kid = (int) ($_POST['korisnik'] ?? 0);
    $racun = $kid ? red('SELECT * FROM Korisnici WHERE Id=? AND Odobren=0', [$kid]) : null;
    $pod = $racun ? samoprijava_podaci($kid) : null;
    switch ($radnja) {
        case 'ukljuci':
            samoprijava_ukljuci();
            dnevnik('Samoprijava – nova zajednička poveznica');
            poruka('Nova poveznica je spremna. Prijašnja (ako je postojala) više ne vrijedi.');
            break;
        case 'iskljuci':
            samoprijava_iskljuci();
            dnevnik('Samoprijava – poveznica isključena');
            poruka('Samoprijava je isključena.');
            break;
        case 'povezi':
        case 'novi':
            if (!$racun || !$pod) {
                break;
            }
            if ($radnja === 'povezi') {
                $cid = (int) ($_POST['clan'] ?? 0);
                $clan = $cid ? clan($cid) : null;
                if (!$clan || racun_clana($cid)) {
                    poruka('Taj član ne postoji ili već ima račun.', 'danger');
                    break;
                }
                // dopuni prazna polja člana podacima iz prijave
                $dop = [];
                foreach (['Mobilni' => 'MobilniTelefon', 'Email' => 'Email', 'SekcijaId' => 'SekcijaId'] as $iz => $u) {
                    if (empty($clan[$u]) && !empty($pod[$iz])) {
                        $dop[$u] = $pod[$iz];
                    }
                }
                if ($dop) {
                    azuriraj('Clanovi', $cid, $dop + ['Azurirano' => sada()]);
                }
            } else {
                [$cid, $g] = spremi_clana(null, ['Ime' => $pod['Ime'], 'Prezime' => $pod['Prezime'], 'MobilniTelefon' => $pod['Mobilni'],
                    'Email' => $pod['Email'] ?? '', 'SekcijaId' => (string) ($pod['SekcijaId'] ?? ''), 'Napomena' => $pod['Napomena'] ?? '']);
                if (!$cid) {
                    poruka(e((string) $g), 'danger');
                    break;
                }
            }
            q('UPDATE Korisnici SET ClanId=? WHERE Id=?', [$cid, $kid]);
            dnevnik('Samoprijava povezana s članom', 'Clan', $cid, $pod['Ime'] . ' ' . $pod['Prezime'] . ' (' . $racun['KorisnickoIme'] . ')');
            poruka('Povezano s članom. Sada odobrite pristup.');
            preusmjeri('clanovi/uredi', ['id' => $cid, 'kartica' => 'racun']);
        case 'odbij':
            if ($racun) {
                obrisi_neodobreni_racun($kid);
                dnevnik('Odbijena samoprijava', null, null, ($pod ? $pod['Ime'] . ' ' . $pod['Prezime'] : '') . ' (' . $racun['KorisnickoIme'] . ')');
                poruka('Prijava je odbijena, račun obrisan.');
            }
            break;
    }
    preusmjeri('sustav/samoprijava');
}

$link = samoprijava_link();
$prijave = samoprijave_na_cekanju();
$clanovi = redovi('SELECT c.* FROM Clanovi c WHERE c.Status=0 AND NOT EXISTS (SELECT 1 FROM Korisnici k WHERE k.ClanId=c.Id) ORDER BY c.Prezime, c.Ime');
$poruka = "Pozdrav,\n\nudruga ima novu evidenciju članova. Ovdje se možeš sam prijaviti (ime, mobitel, korisničko ime i lozinka):\n\n{$link}\n\nNakon toga administrator odobri pristup, a ti onda možeš vidjeti svoje podatke i upisivati radne akcije.";
ob_start(); ?>
<h1 class="h3 mb-1">Samoprijava članova</h1>
<p class="text-muted">Jedna zajednička poveznica za sve (npr. u WhatsApp grupu). Svatko se sam prijavi, a vi ga ovdje povežete s članom i odobrite.
    Bez vašeg odobrenja nitko ne vidi ništa.</p>

<div class="card mb-4"><div class="card-body">
    <?php if ($link): ?>
        <label class="form-label fw-semibold">Zajednička poveznica</label>
        <div class="input-group mb-2"><input id="sp-link" class="form-control" value="<?= e($link) ?>" readonly>
            <button type="button" class="btn btn-outline-secondary" data-kopiraj="#sp-link">Kopiraj</button></div>
        <textarea id="sp-poruka" class="form-control mb-2" rows="6"><?= e($poruka) ?></textarea>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= e(rawurlencode($poruka)) ?>">Podijeli na WhatsApp</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-kopiraj="#sp-poruka">Kopiraj poruku</button>
            <form method="post" class="d-inline"><?= csrf() ?><button name="radnja" value="ukljuci" class="btn btn-outline-primary btn-sm" data-potvrda="Napraviti novu poveznicu? Stara odmah prestaje vrijediti.">Nova poveznica</button></form>
            <form method="post" class="d-inline"><?= csrf() ?><button name="radnja" value="iskljuci" class="btn btn-outline-danger btn-sm" data-potvrda="Isključiti samoprijavu? Poveznica prestaje vrijediti.">Isključi</button></form>
        </div>
        <?php if (preg_match('#//(localhost|127\.|192\.168\.|10\.)#', javna_adresa())): ?>
            <p class="small text-warning-emphasis mt-2 mb-0">Aplikacija trenutno radi samo lokalno – poveznica će raditi kad aplikacija bude na internetu (Sustav → E-pošta i adresa → Javna adresa).</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="mb-2">Samoprijava je <b>isključena</b>.</p>
        <form method="post"><?= csrf() ?><button name="radnja" value="ukljuci" class="btn btn-primary">Uključi i napravi poveznicu</button></form>
    <?php endif; ?>
</div></div>

<h2 class="h5">Čekaju obradu <span class="badge bg-warning text-dark"><?= count($prijave) ?></span></h2>
<?php if (!$prijave): ?><p class="text-muted">Nema prijava na čekanju.</p><?php endif; ?>
<?php foreach ($prijave as $r): $p = $r['Podaci'];
    $kand = $r['ClanId'] ? [] : kandidati_slicnosti($p['Ime'], $p['Prezime'], $p['Mobilni'] ?? null, null, $p['Email'] ?? null, null, $clanovi); ?>
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold"><?= e($p['Ime'] . ' ' . $p['Prezime']) ?> <span class="text-muted small">· <?= e($r['KorisnickoIme']) ?></span></div>
                <div class="small">📱 <?= e($p['Mobilni'] ?? '') ?><?php if (!empty($p['Email'])): ?> · ✉ <?= e($p['Email']) ?><?php endif; ?>
                    <?php if (!empty($p['SekcijaId'])): ?> · <?= e(naziv_sekcije((int) $p['SekcijaId'])) ?><?php endif; ?>
                    · <?= e(datum_vrijeme($p['Kreirano'] ?? $r['Kreirano'])) ?></div>
                <?php if (!empty($p['Napomena'])): ?><div class="small text-muted">„<?= e($p['Napomena']) ?>“</div><?php endif; ?>
            </div>
            <form method="post"><?= csrf() ?><input type="hidden" name="korisnik" value="<?= (int) $r['Id'] ?>">
                <button name="radnja" value="odbij" class="btn btn-sm btn-outline-danger" data-potvrda="Odbiti prijavu? Račun se briše.">Odbij</button></form>
        </div>
        <hr class="my-2">
        <?php if ($r['ClanId']): ?>
            <p class="mb-0 small">Već povezan s članom <b><?= e($r['CIme'] . ' ' . $r['CPrezime']) ?></b> –
                <a href="<?= e(url('clanovi/uredi', ['id' => $r['ClanId'], 'kartica' => 'racun'])) ?>">provjeri i odobri</a>.</p>
        <?php else: ?>
            <form method="post" class="row g-2 align-items-end"><?= csrf() ?><input type="hidden" name="korisnik" value="<?= (int) $r['Id'] ?>">
                <div class="col-md-7"><label class="form-label small mb-1">Postojeći član</label>
                    <select name="clan" class="form-select form-select-sm">
                        <?php if ($kand): ?><optgroup label="Prijedlozi">
                            <?php foreach ($kand as $i => $c): ?><option value="<?= (int) $c['clan']['Id'] ?>" <?= $i === 0 ? 'selected' : '' ?>>
                                <?= e(prezime_ime($c['clan'])) ?> – <?= e($c['razlog']) ?> (<?= round($c['ocjena'] * 100) ?> %)</option><?php endforeach; ?>
                        </optgroup><?php endif; ?>
                        <optgroup label="Svi članovi bez računa">
                            <?php if (!$kand): ?><option value="">– odaberite –</option><?php endif; ?>
                            <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"><?= e(prezime_ime($c)) ?><?= $c['MobilniTelefon'] ? ' · ' . e($c['MobilniTelefon']) : '' ?></option><?php endforeach; ?>
                        </optgroup>
                    </select></div>
                <div class="col-md-5 d-flex flex-wrap gap-2">
                    <button name="radnja" value="povezi" class="btn btn-sm btn-success">Poveži s članom</button>
                    <button name="radnja" value="novi" class="btn btn-sm btn-outline-primary" data-potvrda="Upisati kao novog člana?">Novi član</button>
                </div>
            </form>
        <?php endif; ?>
    </div></div>
<?php endforeach;
stranica('Samoprijava članova', ob_get_clean());
