<?php
/** Kartica Pristup i uloge. Varijable: $id, $clan, $k */
$racun = racun_clana($id);
$arhiviran = (int) $clan['Status'] === CLAN_ARHIVIRAN;
$url = e(url('clanovi/uredi', ['id' => $id]));
$gumb = fn(string $r, string $tekst, string $klasa, array $skriveno = [], string $potvrda = '') =>
    '<form method="post" class="d-inline" action="' . $url . '">' . csrf() . '<input type="hidden" name="radnja" value="' . $r . '">'
    . implode('', array_map(fn($kk, $v) => '<input type="hidden" name="' . $kk . '" value="' . e($v) . '">', array_keys($skriveno), $skriveno))
    . '<button class="btn ' . $klasa . '"' . ($potvrda ? ' data-potvrda="' . e($potvrda) . '"' : '') . '>' . $tekst . '</button></form>';

// ---------- Uloge ----------
if (!$arhiviran):
    $stavke = $racun ? array_map(fn($u) => $u + ['planirana' => 0], uloge_korisnika((int) $racun['Id'])) : array_map(fn($u) => $u + ['planirana' => 1], planirane_uloge($id));
    $sveUloge = redovi('SELECT * FROM Uloge ORDER BY Id');
    $smijeSve = array_column($sveUloge, 'SveSekcije', 'Id'); ?>
<div class="card mb-3">
    <div class="card-header"><b>Uloge (dodatna prava)</b></div>
    <div class="card-body">
        <?php if (!$stavke): ?><p class="small text-muted mb-2">Nema uloga – član vidi samo svoje podatke.</p><?php endif; ?>
        <?php foreach ($stavke as $s): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-success"><?= e($s['Naziv']) ?></span>
                <span class="small"><?= $s['Sekcija'] === null ? 'sve sekcije' : 'samo ' . e($s['Sekcija']) ?></span>
                <?php if ($s['planirana']): ?><span class="badge bg-light text-dark border ms-2" title="Vrijedi čim se član registrira putem pozivnice">čeka račun</span><?php endif; ?>
                <?php if ($s['Sekcija'] === null && empty($smijeSve[$s['UlogaId']])): ?><span class="badge bg-warning text-dark ms-2">odaberite sekciju</span><?php endif; ?>
                <span class="ms-auto"><?= $gumb('uloga-ukloni', 'Ukloni', 'btn-sm btn-link text-danger', ['ulid' => $s['Id'], 'planirana' => $s['planirana']], 'Ukloniti ulogu?') ?></span>
            </div>
        <?php endforeach; ?>
        <form method="post" action="<?= $url ?>" class="row g-2 align-items-end mt-1"><?= csrf() ?>
            <input type="hidden" name="radnja" value="uloga-dodaj">
            <div class="col-sm-5"><label class="form-label small mb-0">Uloga</label>
                <select name="UlogaId" class="form-select form-select-sm" id="ulogaIzbor" required><option value="">— odaberi —</option>
                    <?php foreach ($sveUloge as $u): ?><option value="<?= (int) $u['Id'] ?>" data-sve="<?= (int) $u['SveSekcije'] ?>"><?= e($u['Naziv']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-sm-4"><label class="form-label small mb-0">Za sekciju</label>
                <select name="SekcijaId" class="form-select form-select-sm" id="sekcijaIzbor">
                    <option value="0" data-sve="1">sve sekcije</option>
                    <?php foreach (sekcije() as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $clan['SekcijaId']) ?>>samo <?= e($s['Naziv']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-sm-3"><button class="btn btn-sm btn-primary w-100">Dodaj</button></div>
        </form>
        <p class="small text-muted mt-2 mb-0">Sekcije su odvojene: uloga „samo jedna sekcija“ ne vidi druge sekcije. Sve sekcije smiju samo uloge označene u Sustav → Uloge i prava (zadano: Glavni admin, Blagajnik).
            <?php if (!$racun): ?><span>Član još nema račun – uloge se primjenjuju čim se registrira putem pozivnice.</span><?php endif; ?></p>
    </div>
</div>
<script>
(function () {
    var u = document.getElementById('ulogaIzbor'), s = document.getElementById('sekcijaIzbor'), sve = s.querySelector('option[data-sve]');
    var osvj = function () {
        var o = u.options[u.selectedIndex], dozvoli = o && o.dataset.sve === '1';
        sve.disabled = !dozvoli; sve.hidden = !dozvoli;
        if (!dozvoli && s.value === '0') { var prva = s.querySelector('option:not([data-sve])'); if (prva) s.value = prva.value; }
        if (dozvoli && o.value) s.value = '0';
    };
    u.addEventListener('change', osvj); osvj();
})();
</script>
<?php endif;

// ---------- Račun ----------
if ($arhiviran): ?>
    <div class="alert alert-secondary">Član je arhiviran – pristup je zaključan.<?php if ($racun): ?> Korisnički račun „<?= e($racun['KorisnickoIme']) ?>“ je zaključan.<?php endif; ?></div>
<?php elseif (!$racun):
    $zadnja = red('SELECT * FROM Pozivnice WHERE ClanId=? ORDER BY Id DESC LIMIT 1', [$id]);
    $vazeca = $zadnja && !$zadnja['Iskoristena'] && !$zadnja['Opozvana'] && $zadnja['VrijediDo'] > sada();
    $pozTekst = $_SESSION['pozivnica_tekst'][$id] ?? null;
    unset($_SESSION['pozivnica_tekst'][$id]); ?>
    <div class="card mb-3">
        <div class="card-header"><b>Pristup za člana – pozivnica</b></div>
        <div class="card-body">
            <ol class="small mb-3">
                <li>Kreirajte pozivnicu i pošaljite je članu (WhatsApp, SMS, e-mail ili kopirani tekst).</li>
                <li>Član otvori poveznicu (vrijedi 7 dana, samo jednom) i sam postavi lozinku.</li>
                <li>Ovdje zatim <b>odobrite pristup</b> – bez odobrenja prijava nije moguća.</li>
            </ol>
            <?php if ($zadnja): ?>
                <p class="small text-muted mb-2">Zadnja pozivnica: <?= e(datum_vrijeme($zadnja['Kreirano'])) ?> –
                    <?php if ($zadnja['Iskoristena']): ?><span>iskorištena</span>
                    <?php elseif ($zadnja['Opozvana']): ?><span>opozvana</span>
                    <?php elseif ($zadnja['VrijediDo'] < sada()): ?><span class="text-danger">istekla</span>
                    <?php else: ?><span class="text-success">vrijedi do <?= e(datum($zadnja['VrijediDo'])) ?></span><?php endif; ?></p>
            <?php endif; ?>
            <?php if (!$pozTekst): ?>
                <?= $gumb('pozivnica', $vazeca ? 'Nova pozivnica (stara prestaje vrijediti)' : 'Kreiraj pozivnicu', 'btn-primary') ?>
            <?php else: ?>
                <?= kanali_slanja($clan, 'Pozivnica – ' . udruga_kratko(), $pozTekst, ['clanovi/uredi', ['id' => $id, 'kartica' => 'racun']]) ?>
                <?php if (preg_match('#//(localhost|127\.|192\.168\.|10\.)#', javna_adresa())): ?>
                    <p class="small text-warning-emphasis mt-2 mb-0">Napomena: aplikacija trenutno radi samo na ovom računalu / lokalnoj mreži – poveznica će raditi za članove kad aplikacija bude na internetu (Sustav → E-pošta i adresa → Javna adresa).</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php elseif (!$racun['Odobren']): ?>
    <div class="alert alert-warning">
        <h2 class="h6">Račun čeka vaše odobrenje</h2>
        <p class="small mb-2">Korisničko ime: <b><?= e($racun['KorisnickoIme']) ?></b> · registriran <?= e(datum_vrijeme($racun['Kreirano'])) ?>
            <?php if ($racun['SuglasnostDatum']): ?> · suglasnost dana <?= e(datum($racun['SuglasnostDatum'])) ?><?php endif; ?></p>
        <?php if ($sp = samoprijava_podaci((int) $racun['Id'])): ?>
            <p class="small mb-2">Samoprijava: <b><?= e($sp['Ime'] . ' ' . $sp['Prezime']) ?></b> · 📱 <?= e($sp['Mobilni'] ?? '') ?><?php if (!empty($sp['Email'])): ?> · ✉ <?= e($sp['Email']) ?><?php endif; ?>
                <?php if (!empty($sp['Napomena'])): ?> · „<?= e($sp['Napomena']) ?>“<?php endif; ?><br>
                <span class="text-muted">Usporedite s podacima člana prije odobrenja.</span></p>
        <?php endif; ?>
        <?= $gumb('odobri', 'Odobri pristup', 'btn-success') ?>
        <?= $gumb('odbij', 'Odbij', 'btn-outline-danger ms-2', [], 'Odbiti? Račun se briše.') ?>
    </div>
<?php else:
    $odobrenoTekst = $_SESSION['odobreno_tekst'][$id] ?? null;
    unset($_SESSION['odobreno_tekst'][$id]); ?>
    <dl class="row mb-2">
        <dt class="col-sm-4">Korisničko ime</dt><dd class="col-sm-8"><?= e($racun['KorisnickoIme']) ?></dd>
        <dt class="col-sm-4">Status</dt>
        <dd class="col-sm-8"><?= $racun['Aktivan'] ? '<span class="badge bg-success">Aktivan</span>' : '<span class="badge bg-secondary">Zaključan</span>' ?>
            <?php if ($racun['MoraPromijenitiLozinku']): ?><span class="badge bg-warning text-dark ms-1">Mora promijeniti lozinku</span><?php endif; ?></dd>
        <?php if ($racun['OdobrenDatum']): ?><dt class="col-sm-4">Odobreno</dt><dd class="col-sm-8"><?= e(datum($racun['OdobrenDatum'])) ?> (<?= e($racun['OdobrioIme']) ?>)</dd><?php endif; ?>
        <dt class="col-sm-4">Zadnja prijava</dt><dd class="col-sm-8"><?= $racun['ZadnjaPrijava'] ? e(datum_vrijeme($racun['ZadnjaPrijava'])) : 'nikad' ?></dd>
    </dl>
    <div class="d-flex flex-wrap gap-2">
        <?= $gumb('nova-pocetna', 'Nova početna lozinka', 'btn-sm btn-outline-primary', [], 'Postaviti novu početnu lozinku? Dosadašnja prestaje vrijediti.') ?>
        <?php if (posta_dostupna() && $clan['Email']) echo $gumb('poveznica-lozinka', 'Poveznica za lozinku e-poštom', 'btn-sm btn-outline-primary'); ?>
        <?php if ($racun['Id'] != $k['Id']): ?>
            <?= $gumb('aktivnost', $racun['Aktivan'] ? 'Zaključaj' : 'Otključaj', 'btn-sm btn-outline-' . ($racun['Aktivan'] ? 'warning' : 'success')) ?>
            <?= $gumb('obrisi-racun', 'Obriši račun', 'btn-sm btn-outline-danger', [], 'Obrisati račun? Podaci člana ostaju.') ?>
        <?php endif; ?>
    </div>
    <?php if ($racun['Id'] != $k['Id']): ?><p class="small text-muted mt-1">Obriši račun = briše se samo prijava, podaci člana ostaju. Novi pristup ponovno putem pozivnice.</p><?php endif; ?>
    <?php if ($odobrenoTekst): ?>
        <div class="card mt-3 border-success"><div class="card-body">
            <h2 class="h6">Javite članu da je pristup odobren</h2>
            <?= kanali_slanja($clan, 'Pristup odobren – ' . udruga_kratko(), $odobrenoTekst, ['clanovi/uredi', ['id' => $id, 'kartica' => 'racun']], 6) ?>
        </div></div>
    <?php endif; ?>
<?php endif;
$pocetna = $_SESSION['pocetna_lozinka'][$id] ?? null;
unset($_SESSION['pocetna_lozinka'][$id]);
if ($pocetna && $racun): ?>
    <div class="alert alert-info mt-3">Nova početna lozinka za <b><?= e($racun['KorisnickoIme']) ?></b>: <code class="fs-5"><?= e($pocetna) ?></code><br>
        <span class="small">Prikazuje se samo sada. Kod sljedeće prijave član mora postaviti svoju lozinku.</span></div>
<?php endif;
