<?php
/** Član: podaci, funkcije, članarina, radne akcije, pristup i uloge. */
trazi(P_CLANOVI_CITAJ);
$k = korisnik();
$id = ul_int('id');
$uredi = ima(P_CLANOVI_UREDI);
$clan = $id ? clan_ili_kraj($id) : null;
if (!$id && !$uredi) {
    zabranjeno();
}
$kartice = ['podaci' => 'Podaci', 'funkcije' => 'Funkcije'];
if (ima(P_CLANARINA_CITAJ)) {
    $kartice['clanarina'] = 'Članarina';
}
if (ima(P_AKCIJE_CITAJ)) {
    $kartice['akcije'] = 'Radne akcije';
}
if (ima(P_SUSTAV)) {
    $kartice['racun'] = 'Pristup i uloge';
}
$kartica = (string) ($_GET['kartica'] ?? 'podaci');
if (!isset($kartice[$kartica]) || !$id) {
    $kartica = 'podaci';
}
$natrag = fn(string $kar = '', array $dod = []) => preusmjeri('clanovi/uredi', ['id' => $id, 'kartica' => $kar ?: $kartica] + $dod);

// ======================= POST radnje =======================
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    $treba = fn(int $p) => ima($p) || zabranjeno();
    switch ($radnja) {
        case 'spremi':
            $treba(P_CLANOVI_UREDI);
            if (!$k['SveSekcije'] && ($_POST['SekcijaId'] ?? '') === '') {
                $_POST['SekcijaId'] = $clan['SekcijaId'] ?? ($k['Sekcije'][0] ?? '');
            }
            [$novi, $g] = spremi_clana($id, $_POST);
            if ($g) {
                $_SESSION['obrazac_clan'] = $_POST;
                poruka(e($g), 'danger');
                $id ? $natrag('podaci') : preusmjeri('clanovi/uredi');
            }
            poruka('Spremljeno.');
            preusmjeri('clanovi/uredi', ['id' => $novi]);
        case 'foto':
            $treba(P_CLANOVI_UREDI);
            try {
                $ime = spremi_sliku($_FILES['foto'] ?? [], 'clan-' . $id . '-', 800);
                obrisi_sliku($clan['FotoDatoteka']);
                azuriraj('Clanovi', $id, ['FotoDatoteka' => $ime, 'Azurirano' => sada()]);
                dnevnik('Nova fotografija člana', 'Clan', $id, puno_ime($clan));
            } catch (Throwable $e) {
                poruka(e($e->getMessage()), 'danger');
            }
            $natrag('podaci');
        case 'obrisi-foto':
            $treba(P_CLANOVI_UREDI);
            obrisi_sliku($clan['FotoDatoteka']);
            azuriraj('Clanovi', $id, ['FotoDatoteka' => null, 'Azurirano' => sada()]);
            $natrag('podaci');
        case 'arhiviraj':
            $treba(P_CLANOVI_UREDI);
            arhiviraj_clana($id, u_datum(ul_str('DatumIzlaska')) ?? danas(), ul_null('RazlogIzlaska'));
            poruka('Član je arhiviran, pristup je zaključan.');
            $natrag('podaci');
        case 'vrati':
            $treba(P_CLANOVI_UREDI);
            vrati_iz_arhive($id);
            poruka('Član je vraćen u aktivne. Ako treba pristup, pošaljite novu pozivnicu (Pristup i uloge).');
            $natrag('podaci');
        case 'obrisi-trajno':
            $treba(P_SUSTAV);
            obrisi_clana_trajno($id);
            poruka(e('Član „' . puno_ime($clan) . '“ je trajno obrisan.'));
            preusmjeri('clanovi', ['sekcija' => 'arhiva']);
        case 'obrisi-pogresan':
            $treba(P_SUSTAV);
            [$n, $presk] = obrisi_pogresne([$id]);
            if ($n) {
                poruka(e('Obrisano: „' . puno_ime($clan) . '“.'));
                preusmjeri('clanovi');
            }
            poruka('Nije obrisano jer član ima podatke – arhivirajte ga: ' . e(implode('; ', $presk)), 'warning');
            $natrag('podaci');

        // ---------- funkcije ----------
        case 'funkcija-dodaj':
            $treba(P_SUSTAV);
            $fid = (int) ($_POST['FunkcijaId'] ?? 0);
            if ($fid) {
                $sid = (int) ($_POST['SekcijaId'] ?? 0) ?: null;
                umetni('ClanFunkcije', ['ClanId' => $id, 'FunkcijaId' => $fid, 'SekcijaId' => $sid, 'Od' => u_datum(ul_str('Od')) ?? danas(), 'Do' => null]);
                dnevnik('Dodana funkcija', 'Clan', $id, puno_ime($clan) . ': ' . vrijednost('SELECT Naziv FROM Funkcije WHERE Id=?', [$fid]));
            }
            $natrag('funkcije');
        case 'funkcija-zavrsi':
            $treba(P_SUSTAV);
            q('UPDATE ClanFunkcije SET Do=? WHERE Id=? AND ClanId=?', [danas(), (int) $_POST['fid'], $id]);
            $natrag('funkcije');
        case 'funkcija-obrisi':
            $treba(P_SUSTAV);
            q('DELETE FROM ClanFunkcije WHERE Id=? AND ClanId=?', [(int) $_POST['fid'], $id]);
            $natrag('funkcije');

        // ---------- članarina ----------
        case 'clanarina-dodaj':
            $treba(P_CLANARINA_UREDI);
            $god = (int) ($_POST['Godina'] ?? date('Y'));
            $izn = u_broj($_POST['Iznos'] ?? '');
            if ($izn === null || $izn < 0) {
                poruka('Unesite iznos.', 'warning');
            } elseif (vrijednost('SELECT 1 FROM Clanarine WHERE ClanId=? AND Godina=?', [$id, $god])) {
                poruka("Članarina za $god. već postoji.", 'warning');
            } else {
                umetni('Clanarine', ['ClanId' => $id, 'Godina' => $god, 'Iznos' => dec($izn), 'Valuta' => 'EUR', 'Napomena' => null]);
                dnevnik('Zadužena članarina', 'Clan', $id, puno_ime($clan) . ": $god. – " . novac($izn) . ' €');
            }
            $natrag('clanarina');
        case 'clanarina-obrisi':
            $treba(P_CLANARINA_UREDI);
            q('DELETE FROM Clanarine WHERE Id=? AND ClanId=?', [(int) $_POST['cid'], $id]);
            dnevnik('Uklonjeno zaduženje članarine', 'Clan', $id, puno_ime($clan));
            $natrag('clanarina');
        case 'uplata-dodaj':
            $treba(P_CLANARINA_UREDI);
            $cl = red('SELECT * FROM Clanarine WHERE Id=? AND ClanId=?', [(int) $_POST['cid'], $id]);
            $izn = u_broj($_POST['Iznos'] ?? '');
            if ($cl && $izn !== null && $izn != 0) {
                umetni('Uplate', ['ClanarinaId' => $cl['Id'], 'Datum' => u_datum(ul_str('Datum')) ?? danas(), 'Iznos' => dec($izn), 'Napomena' => ul_null('Napomena')]);
                dnevnik('Uplata članarine', 'Clan', $id, puno_ime($clan) . ": {$cl['Godina']}. – " . novac($izn) . ' €');
            }
            $natrag('clanarina');
        case 'uplata-obrisi':
            $treba(P_CLANARINA_UREDI);
            q('DELETE FROM Uplate WHERE Id=? AND ClanarinaId IN (SELECT Id FROM Clanarine WHERE ClanId=?)', [(int) $_POST['uid'], $id]);
            dnevnik('Obrisana uplata članarine', 'Clan', $id, puno_ime($clan));
            $natrag('clanarina');

        // ---------- pristup i uloge ----------
        case 'uloga-dodaj':
            $treba(P_SUSTAV);
            $uid = (int) ($_POST['UlogaId'] ?? 0);
            $ul = $uid ? red('SELECT * FROM Uloge WHERE Id=?', [$uid]) : null;
            $sid = (int) ($_POST['SekcijaId'] ?? 0) ?: null;
            if (!$ul) {
                $natrag('racun');
            }
            if ($sid === null && !$ul['SveSekcije']) {
                poruka(e("Uloga „{$ul['Naziv']}“ vrijedi samo za jednu sekciju – odaberite sekciju."), 'warning');
                $natrag('racun');
            }
            $racun = racun_clana($id);
            if ($racun) {
                if (!vrijednost('SELECT 1 FROM KorisnikUloge WHERE KorisnikId=? AND UlogaId=? AND SekcijaId IS ?', [$racun['Id'], $uid, $sid])) {
                    umetni('KorisnikUloge', ['KorisnikId' => $racun['Id'], 'UlogaId' => $uid, 'SekcijaId' => $sid]);
                    azuriraj('Korisnici', (int) $racun['Id'], ['SigurnosniZig' => $racun['Id'] == $k['Id'] ? $racun['SigurnosniZig'] : novi_zig()]);
                }
            } elseif (!vrijednost('SELECT 1 FROM PlaniraneUloge WHERE ClanId=? AND UlogaId=? AND SekcijaId IS ?', [$id, $uid, $sid])) {
                umetni('PlaniraneUloge', ['ClanId' => $id, 'UlogaId' => $uid, 'SekcijaId' => $sid]);
            }
            dnevnik('Dodijeljena uloga', 'Clan', $id, puno_ime($clan) . ': ' . $ul['Naziv'] . ($sid ? ' · ' . naziv_sekcije($sid) : ''));
            $natrag('racun');
        case 'uloga-ukloni':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if (($_POST['planirana'] ?? '') === '1') {
                q('DELETE FROM PlaniraneUloge WHERE Id=? AND ClanId=?', [(int) $_POST['ulid'], $id]);
            } elseif ($racun) {
                $ku = red('SELECT ku.*, u.Prava FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId WHERE ku.Id=? AND ku.KorisnikId=?', [(int) $_POST['ulid'], $racun['Id']]);
                if ($ku && ((int) $ku['Prava'] & P_SVE) === P_SVE && $ku['SekcijaId'] === null && broj_glavnih_admina($racun['Id']) === 0) {
                    poruka('Ovo je zadnji glavni administrator – uloga se ne može ukloniti.', 'danger');
                    $natrag('racun');
                }
                q('DELETE FROM KorisnikUloge WHERE Id=? AND KorisnikId=?', [(int) $_POST['ulid'], $racun['Id']]);
                if ($racun['Id'] != $k['Id']) {
                    azuriraj('Korisnici', (int) $racun['Id'], ['SigurnosniZig' => novi_zig()]);
                }
            }
            dnevnik('Uklonjena uloga', 'Clan', $id, puno_ime($clan));
            $natrag('racun');
        case 'pozivnica':
            $treba(P_SUSTAV);
            if (racun_clana($id)) {
                $natrag('racun');
            }
            $link = kreiraj_pozivnicu($id);
            $_SESSION['pozivnica_tekst'][$id] = poruka_pozivnica($clan, $link);
            dnevnik('Kreirana pozivnica', 'Clan', $id, puno_ime($clan));
            $natrag('racun');
        case 'odobri':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun && !$racun['Odobren']) {
                azuriraj('Korisnici', (int) $racun['Id'], ['Odobren' => 1, 'OdobrenDatum' => sada(), 'OdobrioIme' => $k['Naziv']]);
                $_SESSION['odobreno_tekst'][$id] = poruka_odobreno($clan, $racun['KorisnickoIme']);
                dnevnik('Odobren pristup', 'Clan', $id, puno_ime($clan) . ' (' . $racun['KorisnickoIme'] . ')');
                poruka('Pristup je odobren.');
            }
            $natrag('racun');
        case 'odbij':
        case 'obrisi-racun':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun && $racun['Id'] != $k['Id']) {
                if (broj_glavnih_admina($racun['Id']) === 0 && broj_glavnih_admina() > 0) {
                    poruka('Ovo je zadnji glavni administrator – račun se ne može obrisati.', 'danger');
                    $natrag('racun');
                }
                obrisi_oglase_korisnika((int) $racun['Id']);
                q('DELETE FROM ResetiLozinki WHERE KorisnikId=?', [$racun['Id']]);
                q('DELETE FROM PorukeRazgovora WHERE PosiljateljId=?', [$racun['Id']]);
                q('DELETE FROM Korisnici WHERE Id=?', [$racun['Id']]);
                dnevnik($radnja === 'odbij' ? 'Odbijena registracija' : 'Obrisan korisnički račun', 'Clan', $id, puno_ime($clan) . ' (' . $racun['KorisnickoIme'] . ')');
                poruka($radnja === 'odbij' ? 'Registracija je odbijena, račun obrisan.' : 'Račun je obrisan. Podaci člana su sačuvani.');
            }
            $natrag('racun');
        case 'nova-pocetna':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun) {
                $loz = pocetna_lozinka();
                azuriraj('Korisnici', (int) $racun['Id'], ['LozinkaHash' => hash_lozinke($loz), 'MoraPromijenitiLozinku' => 1, 'SigurnosniZig' => novi_zig(), 'ZakljucanDo' => null, 'NeuspjelePrijave' => 0]);
                $_SESSION['pocetna_lozinka'][$id] = $loz;
                dnevnik('Nova početna lozinka', 'Clan', $id, puno_ime($clan));
            }
            $natrag('racun');
        case 'poveznica-lozinka':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun && $clan['Email']) {
                $t = novi_token();
                umetni('ResetiLozinki', ['KorisnikId' => $racun['Id'], 'TokenHash' => hash_tokena($t), 'VrijediDo' => date('Y-m-d H:i:s', time() + 86400), 'Iskoristen' => 0]);
                $link = apsolutni_url('nova-lozinka', ['token' => $t]);
                try {
                    posalji_mail($clan['Email'], 'Nova lozinka – ' . udruga_kratko() . ' evidencija',
                        '<p>Poštovani ' . e(puno_ime($clan)) . ',</p><p>za postavljanje nove lozinke kliknite na poveznicu (vrijedi 24 sata):</p><p><a href="' . e($link) . '">' . e($link) . '</a></p>');
                    poruka(e('Poveznica je poslana na ' . $clan['Email'] . '.'));
                } catch (Throwable $e) {
                    poruka('Slanje nije uspjelo: ' . e($e->getMessage()), 'danger');
                }
            }
            $natrag('racun');
        case 'aktivnost':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun && $racun['Id'] != $k['Id']) {
                $novo = $racun['Aktivan'] ? 0 : 1;
                if (!$novo && broj_glavnih_admina($racun['Id']) === 0 && broj_glavnih_admina() > 0) {
                    poruka('Ovo je zadnji glavni administrator – račun se ne može zaključati.', 'danger');
                    $natrag('racun');
                }
                azuriraj('Korisnici', (int) $racun['Id'], ['Aktivan' => $novo, 'SigurnosniZig' => novi_zig(), 'ZakljucanDo' => null, 'NeuspjelePrijave' => 0]);
                dnevnik($novo ? 'Otključan račun' : 'Zaključan račun', 'Clan', $id, puno_ime($clan));
            }
            $natrag('racun');
    }
    $natrag();
}

// ======================= prikaz =======================
$c = $clan ?? array_fill_keys(array_merge(POLJA_CLANA, ['Id', 'FotoDatoteka', 'Status']), null);
if (!$clan && $k['Sekcije'] && !$k['SveSekcije']) {
    $c['SekcijaId'] = $k['Sekcije'][0];
}
if (isset($_SESSION['obrazac_clan'])) {
    $c = array_merge($c, array_intersect_key($_SESSION['obrazac_clan'], array_flip(POLJA_CLANA)));
    unset($_SESSION['obrazac_clan']);
}
$arhiviran = $clan && (int) $clan['Status'] === CLAN_ARHIVIRAN;
$naslov = $clan ? puno_ime($clan) : 'Novi član';
$posalji = (string) ($_GET['posalji'] ?? '');
$pv = fn($f) => e($c[$f] ?? '');
$pd = fn($f) => e($c[$f] ? substr((string) $c[$f], 0, 10) : '');
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <a href="<?= e(url('clanovi')) ?>" class="btn btn-sm btn-outline-secondary no-print">← Popis</a>
    <?= avatar($clan) ?>
    <h1 class="h3 mb-0"><?= e($naslov) ?></h1>
    <?php if ($arhiviran): ?><span class="badge bg-secondary">Arhiviran <?= e(datum($clan['DatumIzlaska'])) ?></span><?php endif; ?>
    <span class="ms-auto"></span>
    <?php if ($id): ?>
        <?php if (ima(P_AKCIJE_CITAJ)): ?><a class="btn btn-sm btn-outline-success no-print" href="<?= e(url('clanovi/uredi', ['id' => $id, 'kartica' => $kartica, 'posalji' => 'bodovi'])) ?>">Pošalji popis bodova</a><?php endif; ?>
        <?php if (ima(P_CLANARINA_CITAJ)): ?><a class="btn btn-sm btn-outline-success no-print" href="<?= e(url('clanovi/uredi', ['id' => $id, 'kartica' => $kartica, 'posalji' => 'clanarina'])) ?>">Info o članarini</a><?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()">Ispis</button>
    <?php endif; ?>
</div>
<?php if ($id && in_array($posalji, ['bodovi', 'clanarina'], true)) {
    require __DIR__ . '/../../predlosci/posalji-clanu.php';
} ?>
<?php if ($id): ?>
<ul class="nav nav-tabs mb-3 no-print">
    <?php foreach ($kartice as $kl => $t): ?>
        <li class="nav-item"><a class="nav-link <?= $kartica === $kl ? 'active' : '' ?>" href="<?= e(url('clanovi/uredi', ['id' => $id, 'kartica' => $kl])) ?>"><?= e($t) ?></a></li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($kartica === 'podaci'): ?>
<form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" id="obrazacClan"><?= csrf() ?>
<input type="hidden" name="radnja" value="spremi">
<fieldset <?= $uredi ? '' : 'disabled' ?>>
<div class="row g-4">
    <div class="col-lg-3 text-center">
        <?= avatar($clan, true) ?>
        <?php if ($uredi && $id): ?>
            <div class="mt-2 no-print">
                <label class="btn btn-sm btn-outline-primary">Učitaj fotografiju
                    <input type="file" accept="image/*" class="d-none" form="obrazacFoto" name="foto" onchange="this.form.submit()"></label>
                <?php if ($clan['FotoDatoteka']): ?><button type="submit" form="obrazacFotoBrisi" class="btn btn-sm btn-link text-danger">Ukloni</button><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="mt-3 text-start">
            <label class="form-label">Sekcija / lovna jedinica</label>
            <select name="SekcijaId" class="form-select">
                <?php if ($k['SveSekcije']): ?><option value="">Nije raspoređeno</option><?php endif; ?>
                <?php foreach (moje_sekcije() as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($c['SekcijaId'], $s['Id']) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-lg-9">
        <h2 class="h6 text-uppercase text-muted">Osobni podaci</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><label class="form-label">Ime *</label><input name="Ime" class="form-control" value="<?= $pv('Ime') ?>" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label">Prezime *</label><input name="Prezime" class="form-control" value="<?= $pv('Prezime') ?>" required maxlength="100"></div>
            <div class="col-md-4"><label class="form-label">Nadimak</label><input name="Nadimak" class="form-control" value="<?= $pv('Nadimak') ?>"></div>
            <div class="col-md-4"><label class="form-label">OIB</label><input name="Oib" class="form-control" value="<?= $pv('Oib') ?>" inputmode="numeric" maxlength="11" pattern="\d{11}">
                <?php if ($c['Oib'] && !ispravan_oib($c['Oib'])): ?><div class="small text-danger">OIB nije ispravan (provjera kontrolne znamenke).</div><?php endif; ?></div>
            <div class="col-md-4"><label class="form-label">Datum rođenja</label><input type="date" name="DatumRodjenja" class="form-control" value="<?= $pd('DatumRodjenja') ?>"></div>
            <div class="col-md-4"><label class="form-label">Mjesto rođenja</label><input name="MjestoRodjenja" class="form-control" value="<?= $pv('MjestoRodjenja') ?>"></div>
        </div>
        <h2 class="h6 text-uppercase text-muted">Kontakt</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label">Ulica</label><input name="Ulica" class="form-control" value="<?= $pv('Ulica') ?>"></div>
            <div class="col-md-2"><label class="form-label">Kućni broj</label><input name="KucniBroj" class="form-control" value="<?= $pv('KucniBroj') ?>"></div>
            <div class="col-md-2"><label class="form-label">Poštanski broj</label><input name="PostanskiBroj" class="form-control" value="<?= $pv('PostanskiBroj') ?>"></div>
            <div class="col-md-2"><label class="form-label">Mjesto</label><input name="Mjesto" class="form-control" value="<?= $pv('Mjesto') ?>"></div>
            <div class="col-md-4"><label class="form-label">Mobitel</label><input name="MobilniTelefon" type="tel" class="form-control" value="<?= $pv('MobilniTelefon') ?>"></div>
            <div class="col-md-4"><label class="form-label">Fiksni telefon</label><input name="FiksniTelefon" type="tel" class="form-control" value="<?= $pv('FiksniTelefon') ?>"></div>
            <div class="col-md-4"><label class="form-label">E-mail</label><input name="Email" type="email" class="form-control" value="<?= $pv('Email') ?>"></div>
        </div>
        <h2 class="h6 text-uppercase text-muted">Lovački podaci</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><label class="form-label">Broj lovačke iskaznice</label><input name="BrojIskaznice" class="form-control" value="<?= $pv('BrojIskaznice') ?>"></div>
            <div class="col-md-4"><label class="form-label">Iskaznica vrijedi do</label><input type="date" name="IskaznicaVrijediDo" class="form-control" value="<?= $pd('IskaznicaVrijediDo') ?>"></div>
            <div class="col-md-4"><label class="form-label">Član udruge od</label><input type="date" name="ClanOd" class="form-control" value="<?= $pd('ClanOd') ?>"></div>
            <div class="col-md-4"><label class="form-label">Lovački ispit – datum</label><input type="date" name="IspitDatum" class="form-control" value="<?= $pd('IspitDatum') ?>"></div>
            <div class="col-md-4"><label class="form-label">Lovački ispit – mjesto</label><input name="IspitMjesto" class="form-control" value="<?= $pv('IspitMjesto') ?>"></div>
            <div class="col-md-4"><label class="form-label">Broj diplome / uvjerenja</label><input name="BrojDiplome" class="form-control" value="<?= $pv('BrojDiplome') ?>"></div>
            <div class="col-md-6"><label class="form-label">Izdavatelj diplome / uvjerenja</label><input name="IzdavacDiplome" class="form-control" value="<?= $pv('IzdavacDiplome') ?>"></div>
        </div>
        <h2 class="h6 text-uppercase text-muted">Bilješke</h2>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label">Odlikovanja</label><textarea name="Odlikovanja" class="form-control" rows="2"><?= $pv('Odlikovanja') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Školovanja</label><textarea name="Skolovanja" class="form-control" rows="2"><?= $pv('Skolovanja') ?></textarea></div>
            <div class="col-12"><label class="form-label">Napomena</label><textarea name="Napomena" class="form-control" rows="2"><?= $pv('Napomena') ?></textarea></div>
        </div>
        <?php if ($uredi): ?>
        <div class="d-flex flex-wrap gap-2 no-print">
            <button type="submit" class="btn btn-primary">Spremi</button>
            <?php if ($id && !$arhiviran): ?>
                <?php if (ima(P_SUSTAV) && !povijest_clana($id)): ?>
                    <button type="submit" form="obrazacPogresan" class="btn btn-outline-danger" data-potvrda="Trajno obrisati ovaj unos (nije član)?">Obriši – nije član (pogrešan unos)</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-secondary ms-auto" onclick="document.getElementById('arhivForma').classList.toggle('d-none')">Arhiviraj (izlazak iz udruge)</button>
            <?php elseif ($arhiviran): ?>
                <button type="submit" form="obrazacVrati" class="btn btn-outline-success ms-auto">Vrati u aktivne članove</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</fieldset>
</form>
<?php if ($id && $uredi): ?>
    <form method="post" id="obrazacFoto" enctype="multipart/form-data" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="foto"></form>
    <form method="post" id="obrazacFotoBrisi" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-foto"></form>
    <form method="post" id="obrazacPogresan" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-pogresan"></form>
    <form method="post" id="obrazacVrati" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vrati"></form>
    <div class="card border-warning mt-3 no-print d-none" id="arhivForma"><div class="card-body">
        <h2 class="h6">Arhiviranje člana</h2>
        <form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>" class="row g-2 align-items-end"><?= csrf() ?>
            <input type="hidden" name="radnja" value="arhiviraj">
            <div class="col-sm-3"><label class="form-label">Datum izlaska</label><input type="date" name="DatumIzlaska" class="form-control" value="<?= danas() ?>"></div>
            <div class="col-sm-5"><label class="form-label">Razlog</label>
                <input name="RazlogIzlaska" class="form-control" list="razlozi" placeholder="npr. istupio, preminuo, isključen">
                <datalist id="razlozi"><option value="Istupio"></option><option value="Preminuo"></option><option value="Isključen"></option><option value="Preselio"></option></datalist></div>
            <div class="col-sm-4"><button class="btn btn-warning">Arhiviraj</button></div>
        </form>
        <p class="small text-muted mt-2 mb-0">Podaci i povijest ostaju sačuvani, korisnički račun se zaključava.</p>
    </div></div>
<?php endif; ?>
<?php if ($arhiviran): ?>
    <div class="card border-secondary mt-3 no-print"><div class="card-body">
        <h2 class="h6">Arhiviran član</h2>
        <p class="small mb-2">Izlazak: <?= e(datum($clan['DatumIzlaska'])) ?> <?= $clan['RazlogIzlaska'] ? '– ' . e($clan['RazlogIzlaska']) : '' ?><br>
            Pristup je zaključan: prijava nije moguća, otvorene sesije su odjavljene, pozivnice ne vrijede.</p>
        <?php if (ima(P_SUSTAV)): ?>
            <p class="small text-danger mb-2">Trajno brisanje uklanja člana i sve njegove podatke: korisnički račun, funkcije, članarinu, radne akcije i fotografije. Prije brisanja radi se sigurnosna kopija baze.</p>
            <form method="post" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-trajno">
                <button class="btn btn-danger btn-sm" data-potvrda="Sigurno? Član i svi njegovi podaci bit će trajno obrisani.">Trajno obriši člana i sve podatke</button></form>
        <?php endif; ?>
    </div></div>
<?php endif; ?>

<?php elseif ($kartica === 'funkcije'):
    require __DIR__ . '/../../predlosci/clan-funkcije.php';
elseif ($kartica === 'clanarina'):
    require __DIR__ . '/../../predlosci/clan-clanarina.php';
elseif ($kartica === 'akcije'):
    require __DIR__ . '/../../predlosci/clan-akcije.php';
elseif ($kartica === 'racun'):
    require __DIR__ . '/../../predlosci/clan-racun.php';
endif;
stranica($naslov, ob_get_clean());
