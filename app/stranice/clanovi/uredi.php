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
$kartice = ['podaci' => 'Podaci', 'funkcije' => 'Funkcije'] + (modul('psi') ? ['psi' => 'Lovački psi'] : []);
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
    // demo: pristup i uloge zaključani; član s demo računom se ne može arhivirati ni obrisati
    if (je_demo() && (in_array($radnja, ['uloga-dodaj', 'uloga-ukloni', 'odobri', 'odbij', 'obrisi-racun', 'nova-pocetna', 'poveznica-lozinka'], true)
        || (in_array($radnja, ['arhiviraj', 'obrisi-trajno', 'obrisi-pogresan'], true) && vrijednost('SELECT 1 FROM Korisnici WHERE ClanId=?', [$id])))) {
        poruka(demo_poruka_zakljucano(), 'warning');
        $natrag();
    }
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
        case 'iskaznica-nova':
            $treba(P_CLANOVI_UREDI);
            isk_nova($id);
            dnevnik('Nova članska iskaznica (stara ne vrijedi)', 'Clan', $id, puno_ime($clan));
            poruka('Izdana je nova iskaznica – stara više ne vrijedi. Ispišite novu (🪪 Iskaznica).');
            $natrag();
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
            if (!plan_po_id($god, (string) ($_POST['plan'] ?? '')) && ($izn === null || $izn <= 0)) {
                poruka(isset($_POST['plan']) ? "Za $god. nema tog plana plaćanja – postavite planove ili upišite iznos." : 'Unesite iznos.', 'warning');
            } elseif (vrijednost('SELECT 1 FROM Clanarine WHERE ClanId=? AND Godina=?', [$id, $god])) {
                poruka("Članarina za $god. već postoji.", 'warning');
            } else {
                $plan = plan_po_id($god, (string) ($_POST['plan'] ?? ''));
                zaduzi_clanarinu($id, $god, $plan, $izn);
                dnevnik('Zadužena članarina', 'Clan', $id, puno_ime($clan) . ": $god. – " . ($plan ? opis_plana($plan) : novac($izn) . ' €'));
            }
            $natrag('clanarina');
        case 'clanarina-plan':
        case 'clanarina-rate':
            $treba(P_CLANARINA_UREDI);
            $cl = red('SELECT * FROM Clanarine WHERE Id=? AND ClanId=?', [(int) $_POST['cid'], $id]);
            if ($cl) {
                if ($radnja === 'clanarina-plan') {
                    $plan = plan_po_id((int) $cl['Godina'], (string) ($_POST['plan'] ?? ''));
                    $rate = $plan['rate'] ?? [];
                    $opis = $plan ? opis_plana($plan) : '';
                } else {
                    $rate = [];
                    foreach ((array) ($_POST['rate'] ?? []) as $r) {
                        $iz = u_broj($r['iznos'] ?? '');
                        $dt = u_datum($r['datum'] ?? '');
                        if ($iz !== null && $iz > 0 && $dt) {
                            $rate[] = ['iznos' => $iz, 'datum' => $dt];
                        }
                    }
                    $rate = array_slice($rate, 0, 12);
                    $opis = 'prilagođeno: ' . implode(' + ', array_map(fn($r) => novac($r['iznos']) . ' (' . datum($r['datum']) . ')', $rate));
                }
                if ($rate) {
                    postavi_rate((int) $cl['Id'], $rate);
                    dnevnik('Plan plaćanja članarine', 'Clan', $id, puno_ime($clan) . ": {$cl['Godina']}. – $opis");
                    poruka('Plan plaćanja je spremljen.');
                }
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
                if ($ku && ((int) $ku['Prava'] & P_OSNOVNA) === P_OSNOVNA && $ku['SekcijaId'] === null && broj_glavnih_admina($racun['Id']) === 0) {
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
                samoprijava_spremi((int) $racun['Id'], null);
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
                samoprijava_spremi((int) $racun['Id'], null);
                dnevnik($radnja === 'odbij' ? 'Odbijena registracija' : 'Obrisan korisnički račun', 'Clan', $id, puno_ime($clan) . ' (' . $racun['KorisnickoIme'] . ')');
                poruka($radnja === 'odbij' ? 'Registracija je odbijena, račun obrisan.' : 'Račun je obrisan. Podaci člana su sačuvani.');
            }
            $natrag('racun');
        case 'pas-spremi':
        case 'pas-obrisi':
        case 'pas-ispit':
        case 'pas-ispit-obrisi':
            $treba(P_CLANOVI_UREDI);
            psi_obradi($id, $radnja, puno_ime($clan));
            $natrag('psi');
        case 'pas-bodovi':
            $treba(P_CLANOVI_UREDI);
            $treba(P_AKCIJE_ODOBRI);
            $n = upisi_bodove_psa((int) date('Y'), $id, $k);
            dnevnik('Bodovi za lovačke pse upisani ručno', 'Clan', $id, puno_ime($clan) . ': ' . $n);
            poruka($n ? "Upisano: $n × radna akcija „Lovački pas“ – čekaju odobrenje (Radne akcije)." : 'Nema novih upisa.');
            $natrag('psi');
        case 'potpis-obrisi':
            $treba(P_CLANOVI_UREDI);
            potpis_clana_obrisi($id);
            dnevnik('Obrisan spremljeni potpis člana', 'Clan', $id, puno_ime($clan));
            poruka('Potpis je obrisan.');
            $natrag('podaci');
        case 'oruzje-dodaj':
        case 'oruzje-obrisi':
            $treba(P_CLANOVI_UREDI);
            if ($radnja === 'oruzje-dodaj') {
                $broj = mb_substr(ul_str('broj'), 0, 40);
                if ($broj !== '' && (int) vrijednost('SELECT COUNT(*) FROM ClanOruzniListovi WHERE ClanId=?', [$id]) < 50) {
                    umetni('ClanOruzniListovi', ['ClanId' => $id, 'Broj' => $broj, 'Oruzje' => mb_substr(ul_str('oruzje'), 0, 120) ?: null, 'Kreirano' => sada()]);
                    dnevnik('Oružni list dodan', 'Clan', $id, puno_ime($clan) . ': ' . $broj);
                }
            } else {
                $b = vrijednost('SELECT Broj FROM ClanOruzniListovi WHERE Id=? AND ClanId=?', [(int) ($_POST['oid'] ?? 0), $id]);
                if ($b !== null) {
                    q('DELETE FROM ClanOruzniListovi WHERE Id=? AND ClanId=?', [(int) $_POST['oid'], $id]);
                    dnevnik('Oružni list uklonjen', 'Clan', $id, puno_ime($clan) . ': ' . $b);
                }
            }
            $natrag('podaci');
        case 'vozilo-dodaj':
        case 'vozilo-obrisi':
        case 'vozilo-zadano':
            if (!(ima(P_CLANOVI_UREDI) || ima(P_DEZ_UREDI))) {
                zabranjeno();
            }
            if ($radnja === 'vozilo-dodaj') {
                $oz = normaliziraj_oznaku(ul_str('oznaka'));
                if ($oz !== '' && count(vozila_clana($id)) < 5) {
                    dodaj_vozilo($id, $oz, isset($_POST['zadano']));
                    dnevnik('Vozilo člana dodano', 'Clan', $id, puno_ime($clan) . ': ' . $oz);
                }
            } elseif ($radnja === 'vozilo-obrisi') {
                q('DELETE FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) ($_POST['vid'] ?? 0), $id]);
                if (!vrijednost('SELECT 1 FROM ClanVozila WHERE ClanId=? AND Zadano=1', [$id])) {
                    q('UPDATE ClanVozila SET Zadano=1 WHERE Id=(SELECT MIN(Id) FROM ClanVozila WHERE ClanId=?)', [$id]);
                }
                dnevnik('Vozilo člana uklonjeno', 'Clan', $id, puno_ime($clan));
            } elseif (vrijednost('SELECT 1 FROM ClanVozila WHERE Id=? AND ClanId=?', [(int) ($_POST['vid'] ?? 0), $id])) {
                q('UPDATE ClanVozila SET Zadano=(Id=?) WHERE ClanId=?', [(int) $_POST['vid'], $id]);
            }
            $natrag('podaci');
        case 'ponisti-2fa':
            $treba(P_SUSTAV);
            $racun = racun_clana($id);
            if ($racun && (int) $racun['Id'] !== (int) $k['Id']) {
                azuriraj('Korisnici', (int) $racun['Id'], ['TotpTajna' => null, 'TotpRezervni' => null, 'TotpZadnji' => 0, 'SigurnosniZig' => novi_zig()]);
                dnevnik('Poništena dvofaktorska prijava', 'Clan', $id, puno_ime($clan));
                poruka('Dvofaktorska prijava je poništena. Član je odjavljen; pri sljedećoj prijavi ponovno je postavlja (ako je administrator).');
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
        <?php if ($uredi && !$arhiviran && modul('iskaznice')): ?>
        <a class="btn btn-sm btn-outline-primary no-print" href="<?= e(url('izvoz/iskaznice', ['clan' => $id])) ?>" target="_blank" title="Članska iskaznica s QR kodom (PDF)">🪪 Iskaznica</a>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline-secondary no-print" onclick="window.print()">Ispis</button>
    <?php endif; ?>
</div>
<?php if ($id && in_array($posalji, ['bodovi', 'clanarina', 'rata'], true)) {
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
                    <input type="file" accept="image/*" data-smanji="1200" class="d-none" form="obrazacFoto" name="foto" onchange="this.form.submit()"></label>
                <?php if ($clan['FotoDatoteka']): ?><button type="submit" form="obrazacFotoBrisi" class="btn btn-sm btn-link text-danger">Ukloni</button><?php endif; ?>
            </div>
            <?php if (!$arhiviran && modul('iskaznice')): ?>
            <div class="mt-3 text-start small border rounded p-2 no-print">
                <div class="fw-semibold mb-1">🪪 Članska iskaznica (QR)</div>
                <a href="<?= e(url('izvoz/iskaznice', ['clan' => $id])) ?>" target="_blank">PDF – običan pisač (A4)</a><br>
                <a href="<?= e(url('izvoz/iskaznice', ['clan' => $id, 'format' => 'kartica'])) ?>" target="_blank">PDF – pisač kartica (85,6 × 54 mm)</a>
                <button type="submit" form="obrazacIskaznica" class="btn btn-sm btn-link text-danger p-0 d-block mt-1"
                    data-potvrda="Izdati novu iskaznicu? Stara iskaznica (izgubljena / ukradena) odmah prestaje vrijediti – i u aplikaciji člana.">Nova iskaznica (stara prestaje vrijediti)</button>
                <div class="form-text">QR služi samo za popis sudionika skupnog lova. Vrijedi dok je član aktivan.</div>
            </div>
            <?php endif; ?>
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
<?php if ($id && !$arhiviran && (ima(P_CLANOVI_UREDI) || ima(P_DEZ_UREDI))): $vozila = vozila_clana($id); $vurl = e(url('clanovi/uredi', ['id' => $id])); ?>
    <div class="card mt-3" id="vozila" style="max-width:640px"><div class="card-header">Vozila – registarske oznake <span class="small text-muted">(knjiga dezinfekcije)</span></div><div class="card-body">
        <?php if ($vozila): ?><ul class="list-group mb-2">
            <?php foreach ($vozila as $vz): ?><li class="list-group-item d-flex align-items-center gap-2">
                <b class="me-auto"><?= e($vz['Oznaka']) ?></b>
                <?php if ($vz['Zadano']): ?><span class="badge bg-success">zadano</span><?php else: ?>
                    <form method="post" action="<?= $vurl ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-zadano"><input type="hidden" name="vid" value="<?= (int) $vz['Id'] ?>"><button class="btn btn-sm btn-link p-0">zadano</button></form>
                <?php endif; ?>
                <form method="post" action="<?= $vurl ?>"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-obrisi"><input type="hidden" name="vid" value="<?= (int) $vz['Id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" data-potvrda="Ukloniti vozilo?">✕</button></form>
            </li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-muted mb-2">Još nema upisanih vozila.</p><?php endif; ?>
        <?php if (count($vozila) < 5): ?>
        <form method="post" action="<?= $vurl ?>" class="d-flex gap-2"><?= csrf() ?><input type="hidden" name="radnja" value="vozilo-dodaj">
            <input name="oznaka" class="form-control text-uppercase" maxlength="20" placeholder="npr. VT 123-AB" required>
            <button class="btn btn-outline-primary text-nowrap">Dodaj</button></form>
        <?php endif; ?>
        <div class="form-text">Član ih može i sam upisati (Moj profil). Pri upisu na stanici dezinfekcije zadano vozilo je već odabrano.</div>
    </div></div>
<?php endif; ?>
<?php if ($id): $oruzje = oruzni_listovi($id); $ourl = e(url('clanovi/uredi', ['id' => $id])); ?>
    <div class="card mt-3" id="oruzje" style="max-width:640px"><div class="card-header">Oružni listovi <span class="small text-muted">(<?= count($oruzje) ?>)</span></div><div class="card-body">
        <?php if ($oruzje): ?><ul class="list-group mb-2">
            <?php foreach ($oruzje as $o): ?><li class="list-group-item d-flex align-items-center gap-2">
                <b><?= e($o['Broj']) ?></b><span class="text-muted small me-auto"><?= e((string) $o['Oruzje']) ?></span>
                <?php if ($uredi && !$arhiviran): ?><form method="post" action="<?= $ourl ?>"><?= csrf() ?><input type="hidden" name="radnja" value="oruzje-obrisi"><input type="hidden" name="oid" value="<?= (int) $o['Id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" data-potvrda="<?= e('Ukloniti oružni list ' . $o['Broj'] . '?') ?>">✕</button></form><?php endif; ?>
            </li><?php endforeach; ?></ul>
        <?php else: ?><p class="small text-muted mb-2">Još nema upisanih oružnih listova.</p><?php endif; ?>
        <?php if ($uredi && !$arhiviran): ?>
        <form method="post" action="<?= $ourl ?>" class="row g-2"><?= csrf() ?><input type="hidden" name="radnja" value="oruzje-dodaj">
            <div class="col-sm-4"><input name="broj" class="form-control" maxlength="40" placeholder="Broj oružnog lista" required></div>
            <div class="col-sm-6"><input name="oruzje" class="form-control" maxlength="120" placeholder="Oružje (npr. Blaser R8, .308 Win) – nije obavezno"></div>
            <div class="col-sm-2 d-grid"><button class="btn btn-outline-primary">Dodaj</button></div></form>
        <?php endif; ?>
        <div class="form-text">Brojevi oružnih listova ispisuju se na popisu sudionika skupnog lova.</div>
        <?php if ($pf = potpis_clana($id)): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 border-top pt-2 mt-2"><span class="small text-muted">✍ Spremljeni potpis:</span>
            <img src="data:image/png;base64,<?= base64_encode((string) file_get_contents(podaci('foto/' . $pf))) ?>" alt="potpis" class="dez-potpis-slika">
            <?php if ($uredi): ?><form method="post" action="<?= $ourl ?>" class="ms-auto"><?= csrf() ?><input type="hidden" name="radnja" value="potpis-obrisi">
                <button class="btn btn-sm btn-outline-danger" data-potvrda="Obrisati spremljeni potpis člana?">Obriši</button></form><?php endif; ?></div>
        <?php endif; ?>
    </div></div>
<?php endif; ?>
<?php if ($id && $uredi): ?>
    <form method="post" id="obrazacFoto" enctype="multipart/form-data" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="foto"></form>
    <form method="post" id="obrazacFotoBrisi" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi-foto"></form>
    <form method="post" id="obrazacIskaznica" action="<?= e(url('clanovi/uredi', ['id' => $id])) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="iskaznica-nova"></form>
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

<?php elseif ($kartica === 'psi'):
    require __DIR__ . '/../../predlosci/clan-psi.php';
elseif ($kartica === 'funkcije'):
    require __DIR__ . '/../../predlosci/clan-funkcije.php';
elseif ($kartica === 'clanarina'):
    require __DIR__ . '/../../predlosci/clan-clanarina.php';
elseif ($kartica === 'akcije'):
    require __DIR__ . '/../../predlosci/clan-akcije.php';
elseif ($kartica === 'racun'):
    require __DIR__ . '/../../predlosci/clan-racun.php';
endif;
stranica($naslov, ob_get_clean());
