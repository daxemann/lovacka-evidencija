<?php
/** Članovi: dohvat, arhiviranje, brisanje, spajanje duplikata. */
declare(strict_types=1);

/** Polja člana koja se uređuju preko obrasca. */
const POLJA_CLANA = ['Ime', 'Prezime', 'Nadimak', 'SekcijaId', 'Ulica', 'KucniBroj', 'PostanskiBroj', 'Mjesto', 'FiksniTelefon', 'MobilniTelefon',
    'Email', 'Oib', 'DatumRodjenja', 'MjestoRodjenja', 'BrojIskaznice', 'IskaznicaVrijediDo', 'IspitDatum', 'IspitMjesto', 'BrojDiplome',
    'IzdavacDiplome', 'ClanOd', 'Odlikovanja', 'Skolovanja', 'Napomena'];
const DATUMSKA_POLJA = ['DatumRodjenja', 'IskaznicaVrijediDo', 'IspitDatum', 'ClanOd', 'DatumIzlaska'];

function clan(int $id): ?array
{
    return red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id=?', [$id]);
}

/** Član u opsegu trenutnog korisnika ili 404/403. */
function clan_ili_kraj(int $id): array
{
    $c = clan($id);
    if (!$c) {
        nije_pronadjeno('Član');
    }
    if (!u_opsegu($c) && (korisnik()['ClanId'] ?? null) !== (int) $c['Id']) {
        zabranjeno();
    }
    return $c;
}

/** Aktivne funkcije po članu: [clanId => ["Lovočuvar (Sjever)", ...]] */
function funkcije_clanova(): array
{
    $r = [];
    foreach (redovi('SELECT cf.ClanId, f.Naziv, s.Naziv AS Sekcija FROM ClanFunkcije cf JOIN Funkcije f ON f.Id=cf.FunkcijaId
                     LEFT JOIN Sekcije s ON s.Id=cf.SekcijaId WHERE cf.Do IS NULL ORDER BY f.Redoslijed') as $x) {
        $r[(int) $x['ClanId']][] = $x['Naziv'] . ($x['Sekcija'] ? ' (' . $x['Sekcija'] . ')' : '');
    }
    return $r;
}
/** Uloge (admin) po članu: [clanId => ["Domar · Sjever"]] */
function uloge_clanova(): array
{
    $r = [];
    foreach (redovi('SELECT k.ClanId, u.Naziv, s.Naziv AS Sekcija FROM KorisnikUloge ku JOIN Korisnici k ON k.Id=ku.KorisnikId JOIN Uloge u ON u.Id=ku.UlogaId
                     LEFT JOIN Sekcije s ON s.Id=ku.SekcijaId WHERE k.ClanId IS NOT NULL AND k.Aktivan=1') as $x) {
        $r[(int) $x['ClanId']][] = $x['Naziv'] . ($x['Sekcija'] ? ' · ' . $x['Sekcija'] : '');
    }
    return $r;
}

/** Spremanje člana iz $_POST. Vraća [id, greška]. */
function spremi_clana(?int $id, array $ul): array
{
    $p = [];
    foreach (POLJA_CLANA as $f) {
        $v = trim((string) ($ul[$f] ?? ''));
        if ($f === 'SekcijaId') {
            $p[$f] = $v === '' || $v === '0' ? null : (int) $v;
        } elseif (in_array($f, DATUMSKA_POLJA, true)) {
            $p[$f] = u_datum($v);
        } else {
            $p[$f] = $v === '' ? null : $v;
        }
    }
    if (($p['Ime'] ?? '') === null || ($p['Prezime'] ?? '') === null) {
        return [null, 'Ime i prezime su obavezni.'];
    }
    $p['Ime'] = (string) $p['Ime'];
    $p['Prezime'] = (string) $p['Prezime'];
    if ($p['Oib'] !== null && !ispravan_oib($p['Oib'])) {
        return [null, 'OIB nije ispravan (11 znamenki, kontrolni broj).'];
    }
    if ($p['Email'] !== null && !filter_var($p['Email'], FILTER_VALIDATE_EMAIL)) {
        return [null, 'E-mail adresa nije ispravna.'];
    }
    if (!moze_sekciju($p['SekcijaId']) && !(korisnik()['SveSekcije'] ?? false)) {
        return [null, 'Ne možete člana staviti u tu sekciju.'];
    }
    $p['Azurirano'] = sada();
    if ($id) {
        azuriraj('Clanovi', $id, $p);
        dnevnik('Uređivanje člana', 'Clan', $id, $p['Ime'] . ' ' . $p['Prezime']);
        return [$id, null];
    }
    $p += ['Status' => CLAN_AKTIVAN, 'Kreirano' => sada(), 'LovnaJedinica' => null, 'DatumIzlaska' => null, 'RazlogIzlaska' => null, 'FotoDatoteka' => null, 'UvozOznaka' => null];
    $id = umetni('Clanovi', $p);
    dnevnik('Novi član', 'Clan', $id, $p['Ime'] . ' ' . $p['Prezime']);
    povezi_racune();
    return [$id, null];
}

/** Arhiviranje: član izlazi iz udruge, pristup se zaključava. */
function arhiviraj_clana(int $id, string $datum, ?string $razlog): void
{
    $c = clan($id);
    transakcija(function () use ($id, $datum, $razlog) {
        azuriraj('Clanovi', $id, ['Status' => CLAN_ARHIVIRAN, 'DatumIzlaska' => $datum, 'RazlogIzlaska' => $razlog, 'Azurirano' => sada()]);
        foreach (redovi('SELECT Id FROM Korisnici WHERE ClanId=?', [$id]) as $k) {
            azuriraj('Korisnici', (int) $k['Id'], ['Aktivan' => 0, 'SigurnosniZig' => novi_zig()]);
            q('UPDATE ResetiLozinki SET Iskoristen=1 WHERE KorisnikId=?', [$k['Id']]);
        }
        q('UPDATE Pozivnice SET Opozvana=1 WHERE ClanId=? AND Iskoristena IS NULL', [$id]);
    });
    dnevnik('Arhiviranje člana (pristup zaključan)', 'Clan', $id, puno_ime($c) . ': ' . $razlog);
}

function vrati_iz_arhive(int $id): void
{
    $c = clan($id);
    azuriraj('Clanovi', $id, ['Status' => CLAN_AKTIVAN, 'DatumIzlaska' => null, 'RazlogIzlaska' => null, 'Azurirano' => sada()]);
    dnevnik('Vraćanje člana iz arhive', 'Clan', $id, puno_ime($c));
}

/** Što sve član ima (radne akcije, uplate, račun) – za odluku smije li se obrisati kao pogrešan unos. */
function povijest_clana(int $id): array
{
    $r = [];
    $n = (int) vrijednost('SELECT COUNT(*) FROM RadneAkcije WHERE ClanId=?', [$id]);
    if ($n) {
        $r[] = "$n radnih akcija";
    }
    $n = (int) vrijednost('SELECT COUNT(*) FROM Uplate u JOIN Clanarine c ON c.Id=u.ClanarinaId WHERE c.ClanId=?', [$id]);
    if ($n) {
        $r[] = "$n uplata članarine";
    }
    if (vrijednost('SELECT 1 FROM Korisnici WHERE ClanId=?', [$id])) {
        $r[] = 'korisnički račun';
    }
    return $r;
}

/** Trajno brisanje člana sa svim podacima i računima. */
function obrisi_clana_trajno(int $id): void
{
    $c = clan($id);
    if (!$c) {
        return;
    }
    try {
        napravi_kopiju('prije-brisanja');
    } catch (Throwable) {
    }
    $racuni = array_map('intval', array_column(redovi('SELECT Id FROM Korisnici WHERE ClanId=?', [$id]), 'Id'));
    foreach ($racuni as $kid) {
        obrisi_oglase_korisnika($kid);
    }
    obrisi_sliku($c['FotoDatoteka']);
    foreach (redovi('SELECT FotoDatoteka FROM RadneAkcije WHERE ClanId=? AND FotoDatoteka IS NOT NULL', [$id]) as $a) {
        obrisi_sliku($a['FotoDatoteka']);
    }
    transakcija(function () use ($id, $racuni) {
        foreach ($racuni as $kid) {
            q('DELETE FROM ResetiLozinki WHERE KorisnikId=?', [$kid]);
            q('DELETE FROM PorukeRazgovora WHERE PosiljateljId=?', [$kid]);
            q('DELETE FROM Korisnici WHERE Id=?', [$kid]);
        }
        q('DELETE FROM ClanIskaznice WHERE ClanId=?', [$id]);
        q('DELETE FROM Clanovi WHERE Id=?', [$id]);
    });
    dnevnik('Trajno brisanje člana', 'Clan', null, puno_ime($c) . " (ID $id), računa: " . count($racuni));
}

/** Briše pogrešne unose (bez povijesti). Vraća [broj obrisanih, preskočeni[]]. */
function obrisi_pogresne(array $ids): array
{
    $brisati = [];
    $preskoceno = [];
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $c = clan($id);
        if (!$c || !u_opsegu($c)) {
            continue;
        }
        $pov = povijest_clana($id);
        if ($pov) {
            $preskoceno[] = puno_ime($c) . ' (' . implode(', ', $pov) . ')';
        } else {
            $brisati[] = $c;
        }
    }
    if (!$brisati) {
        return [0, $preskoceno];
    }
    try {
        napravi_kopiju('prije-brisanja');
    } catch (Throwable) {
    }
    transakcija(function () use ($brisati) {
        foreach ($brisati as $c) {
            obrisi_sliku($c['FotoDatoteka']);
            q('DELETE FROM Clanovi WHERE Id=?', [$c['Id']]);
        }
    });
    dnevnik('Obrisani pogrešni unosi (nisu članovi)', 'Clan', null, implode(', ', array_map('puno_ime', $brisati)));
    return [count($brisati), $preskoceno];
}

/** Polja za spajanje: naziv => [prikaz(fn), stupci[]] */
function polja_spajanja(): array
{
    $d = fn($v) => $v ? date('d.m.Y', strtotime(substr($v, 0, 10))) : null;
    return [
        'Ime' => [fn($c) => $c['Ime'], ['Ime']],
        'Prezime' => [fn($c) => $c['Prezime'], ['Prezime']],
        'Nadimak' => [fn($c) => $c['Nadimak'], ['Nadimak']],
        'Sekcija' => [fn($c) => $c['SekcijaId'] ? naziv_sekcije((int) $c['SekcijaId']) : null, ['SekcijaId']],
        'Fotografija' => [fn($c) => $c['FotoDatoteka'] ? 'ima fotografiju' : null, ['FotoDatoteka']],
        'Mobitel' => [fn($c) => $c['MobilniTelefon'], ['MobilniTelefon']],
        'Fiksni' => [fn($c) => $c['FiksniTelefon'], ['FiksniTelefon']],
        'E-mail' => [fn($c) => $c['Email'], ['Email']],
        'Adresa' => [fn($c) => adresa($c) ?: null, ['Ulica', 'KucniBroj', 'PostanskiBroj', 'Mjesto']],
        'OIB' => [fn($c) => $c['Oib'], ['Oib']],
        'Datum rođenja' => [fn($c) => $d($c['DatumRodjenja']), ['DatumRodjenja']],
        'Mjesto rođenja' => [fn($c) => $c['MjestoRodjenja'], ['MjestoRodjenja']],
        'Lovačka iskaznica' => [fn($c) => $c['BrojIskaznice'] ? $c['BrojIskaznice'] . ' (do ' . $d($c['IskaznicaVrijediDo']) . ')' : null, ['BrojIskaznice', 'IskaznicaVrijediDo']],
        'Lovački ispit' => [fn($c) => ($c['IspitDatum'] || $c['IspitMjesto']) ? trim($d($c['IspitDatum']) . ' ' . $c['IspitMjesto']) : null, ['IspitDatum', 'IspitMjesto']],
        'Diploma' => [fn($c) => $c['BrojDiplome'] ? $c['BrojDiplome'] . ' ' . $c['IzdavacDiplome'] : null, ['BrojDiplome', 'IzdavacDiplome']],
        'Član od' => [fn($c) => $d($c['ClanOd']), ['ClanOd']],
        'Odlikovanja' => [fn($c) => $c['Odlikovanja'], ['Odlikovanja']],
        'Školovanja' => [fn($c) => $c['Skolovanja'], ['Skolovanja']],
        'Napomena' => [fn($c) => $c['Napomena'], ['Napomena']],
    ];
}

/** Treba li polje po zadanom uzeti od drugog (zadržani nema vrijednost, drugi ima). */
function zadano_desno(string $polje, array $z, array $d): bool
{
    $f = polja_spajanja()[$polje][0];
    return trim((string) $f($z)) === '' && trim((string) $f($d)) !== '';
}

/** Spaja člana $ukloniId u $zadrzatiId; $odDrugog = nazivi polja koja se preuzimaju od drugog. */
function spoji_clanove(int $zadrzatiId, int $ukloniId, array $odDrugog): string
{
    $z = clan($zadrzatiId);
    $u = clan($ukloniId);
    if (!$z || !$u || $zadrzatiId === $ukloniId) {
        throw new RuntimeException('Članovi nisu pronađeni.');
    }
    try {
        napravi_kopiju('prije-spajanja');
    } catch (Throwable) {
    }
    $info = [];
    $brisatiFoto = null;
    transakcija(function () use ($z, $u, $odDrugog, &$info, &$brisatiFoto) {
        $zid = (int) $z['Id'];
        $uid = (int) $u['Id'];
        $polja = polja_spajanja();
        $novo = [];
        foreach ($odDrugog as $naziv) {
            if (!isset($polja[$naziv])) {
                continue;
            }
            foreach ($polja[$naziv][1] as $st) {
                $novo[$st] = $u[$st];
            }
            $info[] = $naziv;
        }
        if (isset($novo['FotoDatoteka']) && $z['FotoDatoteka'] !== $u['FotoDatoteka']) {
            $brisatiFoto = $z['FotoDatoteka'];
        } elseif ($u['FotoDatoteka'] && $u['FotoDatoteka'] !== $z['FotoDatoteka']) {
            $brisatiFoto = $u['FotoDatoteka'];
        }
        if ($novo) {
            $novo['Azurirano'] = sada();
            azuriraj('Clanovi', $zid, $novo);
        }
        // radne akcije
        $n = q('UPDATE RadneAkcije SET ClanId=? WHERE ClanId=?', [$zid, $uid])->rowCount();
        if ($n) {
            $info[] = "$n radnih akcija premješteno";
        }
        // funkcije (bez dupliciranja aktivnih)
        foreach (redovi('SELECT * FROM ClanFunkcije WHERE ClanId=?', [$uid]) as $f) {
            if ($f['Do'] === null && vrijednost('SELECT 1 FROM ClanFunkcije WHERE ClanId=? AND FunkcijaId=? AND Do IS NULL', [$zid, $f['FunkcijaId']])) {
                q('DELETE FROM ClanFunkcije WHERE Id=?', [$f['Id']]);
            } else {
                q('UPDATE ClanFunkcije SET ClanId=? WHERE Id=?', [$zid, $f['Id']]);
            }
        }
        // članarine i uplate
        foreach (redovi('SELECT * FROM Clanarine WHERE ClanId=?', [$uid]) as $cl) {
            $post = red('SELECT * FROM Clanarine WHERE ClanId=? AND Godina=?', [$zid, $cl['Godina']]);
            if ($post) {
                q('UPDATE Uplate SET ClanarinaId=? WHERE ClanarinaId=?', [$post['Id'], $cl['Id']]);
                q('DELETE FROM Clanarine WHERE Id=?', [$cl['Id']]);
            } else {
                q('UPDATE Clanarine SET ClanId=? WHERE Id=?', [$zid, $cl['Id']]);
            }
        }
        // planirane uloge
        foreach (redovi('SELECT * FROM PlaniraneUloge WHERE ClanId=?', [$uid]) as $pu) {
            if (!vrijednost('SELECT 1 FROM PlaniraneUloge WHERE ClanId=? AND UlogaId=? AND SekcijaId IS ?', [$zid, $pu['UlogaId'], $pu['SekcijaId']])) {
                q('UPDATE PlaniraneUloge SET ClanId=? WHERE Id=?', [$zid, $pu['Id']]);
            }
        }
        q('DELETE FROM PlaniraneUloge WHERE ClanId=?', [$uid]);
        q('UPDATE LovniPsi SET ClanId=? WHERE ClanId=?', [$zid, $uid]);
        q('DELETE FROM Pozivnice WHERE ClanId=?', [$uid]);
        // popisi sudionika skupnog lova (bez dupliciranja u istom lovu); iskaznica uklonjenog unosa prestaje vrijediti
        q('UPDATE GrupniLovPopis SET ClanId=? WHERE ClanId=? AND LovId NOT IN (SELECT LovId FROM GrupniLovPopis WHERE ClanId=?)', [$zid, $uid, $zid]);
        q('DELETE FROM ClanIskaznice WHERE ClanId=?', [$uid]);
        // računi
        $rz = red('SELECT * FROM Korisnici WHERE ClanId=?', [$zid]);
        $ru = red('SELECT * FROM Korisnici WHERE ClanId=?', [$uid]);
        if ($ru && !$rz) {
            q('UPDATE Korisnici SET ClanId=? WHERE Id=?', [$zid, $ru['Id']]);
            $info[] = "račun „{$ru['KorisnickoIme']}“ premješten";
        } elseif ($ru && $rz) {
            // zadržava se račun koji je odobren i češće korišten
            [$ostaje, $brise] = ($ru['Odobren'] && !$rz['Odobren']) || (($ru['ZadnjaPrijava'] ?? '') > ($rz['ZadnjaPrijava'] ?? '') && $ru['Odobren'] >= $rz['Odobren']) ? [$ru, $rz] : [$rz, $ru];
            foreach (redovi('SELECT * FROM KorisnikUloge WHERE KorisnikId=?', [$brise['Id']]) as $ul) {
                if (!vrijednost('SELECT 1 FROM KorisnikUloge WHERE KorisnikId=? AND UlogaId=? AND SekcijaId IS ?', [$ostaje['Id'], $ul['UlogaId'], $ul['SekcijaId']])) {
                    q('UPDATE KorisnikUloge SET KorisnikId=? WHERE Id=?', [$ostaje['Id'], $ul['Id']]);
                }
            }
            q('UPDATE Oglasi SET KorisnikId=? WHERE KorisnikId=?', [$ostaje['Id'], $brise['Id']]);
            q('UPDATE PorukeRazgovora SET PosiljateljId=? WHERE PosiljateljId=?', [$ostaje['Id'], $brise['Id']]);
            q('DELETE FROM Razgovori WHERE KupacId=? AND OglasId IN (SELECT OglasId FROM Razgovori WHERE KupacId=?)', [$brise['Id'], $ostaje['Id']]);
            q('UPDATE Razgovori SET KupacId=? WHERE KupacId=?', [$ostaje['Id'], $brise['Id']]);
            q('DELETE FROM ResetiLozinki WHERE KorisnikId=?', [$brise['Id']]);
            q('DELETE FROM Korisnici WHERE Id=?', [$brise['Id']]);
            q('UPDATE Korisnici SET ClanId=? WHERE Id=?', [$zid, $ostaje['Id']]);
            $info[] = "zadržan račun „{$ostaje['KorisnickoIme']}“, obrisan „{$brise['KorisnickoIme']}“";
        }
        q('DELETE FROM Clanovi WHERE Id=?', [$uid]);
    });
    obrisi_sliku($brisatiFoto);
    $opis = '„' . puno_ime($u) . "“ (ID {$u['Id']}) spojen u „" . puno_ime($z) . "“ (ID {$z['Id']})" . ($info ? ': ' . implode(', ', $info) : '');
    dnevnik('Spajanje duplikata', 'Clan', (int) $z['Id'], $opis);
    return $opis;
}

// ---------- Oružni listovi ----------
/** Oružni listovi člana (broj + opis oružja), redom upisa. */
function oruzni_listovi(int $clanId): array
{
    return redovi('SELECT * FROM ClanOruzniListovi WHERE ClanId=? ORDER BY Id', [$clanId]);
}
/** Brojevi oružnih listova po članu: [ClanId => '123, 456'] (za popise i PDF). */
function oruzni_listovi_brojevi(array $clanIds): array
{
    $clanIds = array_values(array_unique(array_filter(array_map('intval', $clanIds))));
    if (!$clanIds) {
        return [];
    }
    $o = [];
    foreach (redovi('SELECT ClanId, Broj FROM ClanOruzniListovi WHERE ClanId IN (' . implode(',', $clanIds) . ') ORDER BY Id') as $r) {
        $o[(int) $r['ClanId']][] = $r['Broj'];
    }
    return array_map(fn($b) => implode(', ', $b), $o);
}

// ---------- Osobni potpis (Moj profil → ✍ Moj potpis) ----------
// Svaki funkcionar sam sprema svoj potpis; program ga stavlja na PDF-ove gdje je ta osoba potpisnik
// (popis skupnog lova – voditelj lova, knjiga dezinfekcije – odgovorna osoba sekcije i predsjednik). Nitko drugi ga ne može postaviti.
function potpis_korisnika(int $korisnikId): ?string
{
    $f = postavka('Potpis.K' . $korisnikId);
    return $f && is_file(podaci('foto/' . basename($f))) ? basename($f) : null;
}
function potpis_datauri(int $korisnikId): ?string
{
    $f = potpis_korisnika($korisnikId);
    return $f ? 'data:image/png;base64,' . base64_encode((string) file_get_contents(podaci('foto/' . $f))) : null;
}
/** Sprema potpis (crtež data:image/png ili učitana slika) za korisnika. */
function potpis_spremi(int $korisnikId, string $crtez, array $datoteka): void
{
    if (str_starts_with($crtez, 'data:image/png;base64,')) {
        $tmp = tempnam(sys_get_temp_dir(), 'pot');
        file_put_contents($tmp, (string) base64_decode(substr($crtez, 22), true));
        try {
            $ime = spremi_sliku(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'potpis.png'], 'potpis-', 900, true);
        } finally {
            @unlink($tmp);
        }
    } else {
        $ime = spremi_sliku($datoteka, 'potpis-', 900, true);
    }
    obrisi_sliku(postavka('Potpis.K' . $korisnikId));
    spremi_postavku('Potpis.K' . $korisnikId, $ime);
    spremi_postavku('Potpis.Datum.K' . $korisnikId, sada());
}
function potpis_obrisi(int $korisnikId): void
{
    obrisi_sliku(postavka('Potpis.K' . $korisnikId));
    spremi_postavku('Potpis.K' . $korisnikId, null);
    spremi_postavku('Potpis.Datum.K' . $korisnikId, null);
}
/** Korisnik (s potpisom ili bez) čiji povezani član ima točno ovo ime i prezime – samo ako je jednoznačan. */
function korisnik_po_imenu(?string $ime): ?int
{
    if (!$ime) {
        return null;
    }
    $kl = kljuc($ime);
    $nadjeni = [];
    foreach (redovi('SELECT k.Id, c.Ime, c.Prezime FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Aktivan=1') as $r) {
        if (kljuc(puno_ime($r)) === $kl || kljuc(prezime_ime($r)) === $kl) {
            $nadjeni[(int) $r['Id']] = true;
        }
    }
    return count($nadjeni) === 1 ? (int) array_key_first($nadjeni) : null;
}
/** Osobni potpis osobe po imenu (za mjesta gdje je potpisnik upisan imenom, npr. odgovorna osoba sekcije). */
function potpis_po_imenu(?string $ime): ?string
{
    $kid = korisnik_po_imenu($ime);
    return $kid ? potpis_datauri($kid) : null;
}

// ---------- Potpis člana (i bez korisničkog računa) ----------
/** Korisnički račun člana (za osobni potpis). */
function korisnik_clana(int $clanId): ?int
{
    $id = vrijednost('SELECT Id FROM Korisnici WHERE ClanId=? AND Aktivan=1 ORDER BY Id LIMIT 1', [$clanId]);
    return $id ? (int) $id : null;
}
/** Spremljeni potpis člana (datoteka): osobni potpis računa, inače potpis spremljen pri skupnom lovu (član bez računa). */
function potpis_clana(int $clanId): ?string
{
    if (($kid = korisnik_clana($clanId)) && ($f = potpis_korisnika($kid))) {
        return $f;
    }
    $f = postavka('Potpis.C' . $clanId);
    return $f && is_file(podaci('foto/' . basename($f))) ? basename($f) : null;
}
/** Sprema kopiju datoteke potpisa kao trajni potpis člana (račun → Moj profil; bez računa → kod člana). */
function potpis_clana_spremi_kopiju(int $clanId, string $datoteka): void
{
    $izvor = podaci('foto/' . basename($datoteka));
    if (!is_file($izvor)) {
        return;
    }
    $novo = 'potpis-' . bin2hex(random_bytes(8)) . '.png';
    copy($izvor, podaci('foto/' . $novo));
    $kl = ($kid = korisnik_clana($clanId)) ? 'K' . $kid : 'C' . $clanId;
    obrisi_sliku(postavka('Potpis.' . $kl));
    spremi_postavku('Potpis.' . $kl, $novo);
    spremi_postavku('Potpis.Datum.' . $kl, sada());
}
function potpis_clana_obrisi(int $clanId): void
{
    foreach (array_filter(['C' . $clanId, ($kid = korisnik_clana($clanId)) ? 'K' . $kid : null]) as $kl) {
        obrisi_sliku(postavka('Potpis.' . $kl));
        spremi_postavku('Potpis.' . $kl, null);
        spremi_postavku('Potpis.Datum.' . $kl, null);
    }
}

