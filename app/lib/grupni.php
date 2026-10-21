<?php
/**
 * Grupni (skupni) lov: organizator s pravom P_GRUPNI_LOV pokreće lov za sekcije u svom opsegu,
 * svi lovci tih sekcija dobivaju poziv (push + prozor u aplikaciji) „Dolazim / Ne dolazim“.
 * Tko dolazi, šalje položaj uživo dok je karta otvorena; organizator vidi sve na karti,
 * upozorenje o gubitku signala (zadnji poznati položaj) i SOS.
 * Lov završava organizator („Lov završen“) ili sam u 23:00. Tada se brišu svi položaji – ostaje samo tko je sudjelovao.
 * Kad nema aktivnog lova, lovci ne vide ništa (ni stavku u izborniku).
 */
declare(strict_types=1);

const GL_SAT_KRAJA = 23;      // automatski kraj (sat)
const GL_SIGNAL_MIN = 3;      // bez javljanja toliko minuta = izgubljen signal
const GL_INTERVAL_S = 20;     // koliko često mobitel šalje položaj (sekunde)

// ---------- Opseg ----------
/** Sekcije za koje trenutni korisnik smije pokrenuti skupni lov: [id => naziv]; 0 = udruga bez sekcija. */
function gl_sekcije_organizator(): array
{
    $k = korisnik();
    if (!$k || !ima(P_GRUPNI_LOV)) {
        return [];
    }
    $sve = [];
    foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
        $sve[(int) $s['Id']] = $s['Naziv'];
    }
    if ($k['SveSekcije']) {
        return $sve ?: [0 => udruga_kratko()];
    }
    return array_intersect_key($sve, array_flip($k['Sekcije']));
}

/** Sekcije lova kao niz brojeva (0 = članovi bez sekcije). */
function gl_sekcije_lova(array $lov): array
{
    return array_values(array_unique(array_map('intval', array_filter(explode(',', (string) $lov['Sekcije']), fn($x) => $x !== ''))));
}

/** Organizator ovog lova: tko ga je pokrenuo ili ima pravo za sve njegove sekcije. */
function gl_moze_upravljati(array $lov): bool
{
    $k = korisnik();
    if (!$k) {
        return false;
    }
    if ((int) $lov['KreiraoKorisnikId'] === (int) $k['Id']) {
        return true;
    }
    if (!ima(P_GRUPNI_LOV)) {
        return false;
    }
    if ($k['SveSekcije']) {
        return true;
    }
    foreach (gl_sekcije_lova($lov) as $s) {
        if ($s === 0 || !in_array($s, $k['Sekcije'], true)) {
            return false;
        }
    }
    return true;
}

function gl_lov(int $id): ?array
{
    return red('SELECT * FROM GrupniLov WHERE Id=?', [$id]);
}
function gl_aktivan(array $lov): bool
{
    return $lov['Zavrseno'] === null && $lov['Kraj'] > sada();
}
function gl_sudionik(int $lovId, int $korisnikId): ?array
{
    return red('SELECT * FROM GrupniLovSudionici WHERE LovId=? AND KorisnikId=?', [$lovId, $korisnikId]);
}

/** Aktivni lov u koji je trenutni korisnik pozvan (najnoviji) s njegovim redom: ['lov' => …, 'ja' => …] ili null. */
function gl_moj_aktivni(): ?array
{
    static $r = false;
    if ($r !== false) {
        return $r;
    }
    $k = korisnik();
    if (!$k) {
        return $r = null;
    }
    $x = red('SELECT s.*, l.Id AS L_Id FROM GrupniLovSudionici s JOIN GrupniLov l ON l.Id=s.LovId
        WHERE s.KorisnikId=? AND l.Zavrseno IS NULL AND l.Kraj>? ORDER BY l.Id DESC LIMIT 1', [$k['Id'], sada()]);
    if (!$x) {
        return $r = null;
    }
    unset($x['L_Id']);
    return $r = ['lov' => gl_lov((int) $x['LovId']), 'ja' => $x];
}

/** Aktivni lovovi kojima trenutni korisnik upravlja. */
function gl_aktivni_organizator(): array
{
    $k = korisnik();
    if (!$k) {
        return [];
    }
    return array_values(array_filter(redovi('SELECT * FROM GrupniLov WHERE Zavrseno IS NULL AND Kraj>? ORDER BY Id DESC', [sada()]), 'gl_moze_upravljati'));
}

/** Smije li korisnik uopće vidjeti stavku „Skupni lov“ (organizator ili pozvan u aktivni lov). */
function gl_vidljivo(): bool
{
    return ima(P_GRUPNI_LOV) || gl_moj_aktivni() !== null;
}

// ---------- Pokretanje ----------
/** Vrijeme automatskog kraja (danas 23:00) ili null ako je već prošlo. */
function gl_kraj_danas(): ?string
{
    $t = mktime(GL_SAT_KRAJA, 0, 0);
    return $t > time() ? date('Y-m-d H:i:s', $t) : null;
}

/** Pokreće lov i šalje poziv. Vraća [id, null] ili [null, greška]. */
function gl_pokreni(string $naziv, string $mjesto, string $napomena, array $sekcije): array
{
    $k = korisnik();
    $dozvoljene = gl_sekcije_organizator();
    $sekcije = array_values(array_intersect(array_map('intval', $sekcije), array_keys($dozvoljene)));
    if (!$sekcije) {
        return [null, 'Odaberite barem jednu sekciju.'];
    }
    $kraj = gl_kraj_danas();
    if (!$kraj) {
        return [null, 'Skupni lov ne može se pokrenuti nakon ' . GL_SAT_KRAJA . ':00.'];
    }
    foreach (redovi('SELECT * FROM GrupniLov WHERE Zavrseno IS NULL AND Kraj>?', [sada()]) as $l) {
        if (array_intersect($sekcije, gl_sekcije_lova($l))) {
            return [null, 'Za odabranu sekciju već je u tijeku skupni lov „' . $l['Naziv'] . '“ – prvo ga završite.'];
        }
    }
    $naziv = mb_substr(trim($naziv), 0, 80) ?: 'Skupni lov';
    $uvjet = [];
    $bez = in_array(0, $sekcije, true);
    $ids = array_filter($sekcije);
    if ($ids) {
        $uvjet[] = 'c.SekcijaId IN (' . implode(',', $ids) . ')';
    }
    if ($bez) {
        $uvjet[] = 'c.SekcijaId IS NULL';
    }
    $lovci = redovi('SELECT k.Id AS KorisnikId, c.Id AS ClanId, c.Ime, c.Prezime, c.SekcijaId FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId
        WHERE k.Aktivan=1 AND k.Odobren=1 AND c.Status=' . CLAN_AKTIVAN . ' AND (' . implode(' OR ', $uvjet) . ')');
    if (!$lovci) {
        return [null, 'U odabranim sekcijama nema lovaca s korisničkim računom – nema koga pozvati.'];
    }
    $id = transakcija(function () use ($k, $naziv, $mjesto, $napomena, $sekcije, $kraj, $lovci) {
        $id = umetni('GrupniLov', [
            'Naziv' => $naziv, 'Mjesto' => mb_substr(trim($mjesto), 0, 120) ?: null, 'Napomena' => mb_substr(trim($napomena), 0, 500) ?: null,
            'Sekcije' => implode(',', $sekcije), 'Pocetak' => sada(), 'Kraj' => $kraj,
            'KreiraoKorisnikId' => $k['Id'], 'KreiraoClanId' => $k['ClanId'], 'KreiraoIme' => $k['Naziv'], 'Kreirano' => sada(),
        ]);
        foreach ($lovci as $l) {
            $ja = (int) $l['KorisnikId'] === (int) $k['Id'];
            umetni('GrupniLovSudionici', [
                'LovId' => $id, 'KorisnikId' => $l['KorisnikId'], 'ClanId' => $l['ClanId'], 'Ime' => trim($l['Ime'] . ' ' . $l['Prezime']),
                'SekcijaId' => $l['SekcijaId'], 'Token' => bin2hex(random_bytes(16)),
                // organizator koji je i sam lovac te sekcije – dolazi (on je pokrenuo lov)
                'Odgovor' => $ja ? 1 : null, 'Odgovoreno' => $ja ? sada() : null,
            ]);
        }
        return $id;
    });
    dnevnik('Skupni lov – pokrenut', 'GrupniLov', $id, $naziv . ' · pozvano ' . count($lovci));
    gl_posalji_poziv($id);
    return [$id, null];
}

/** Push poziv svima koji još nisu odgovorili – s osobnim gumbima „Dolazim / Ne dolazim“. */
function gl_posalji_poziv(int $lovId): void
{
    $lov = gl_lov($lovId);
    $po = [];
    $ids = [];
    foreach (redovi('SELECT KorisnikId, Token FROM GrupniLovSudionici WHERE LovId=? AND Odgovor IS NULL', [$lovId]) as $s) {
        $ids[] = (int) $s['KorisnikId'];
        $po[(int) $s['KorisnikId']] = ['a' => [
            ['a' => 'da', 't' => '✔ Dolazim', 'otvori' => apsolutni_url('grupni-lov', ['t' => $s['Token'], 'o' => 1])],
            ['a' => 'ne', 't' => '✖ Ne dolazim', 'url' => apsolutni_url('grupni-odgovor', ['t' => $s['Token'], 'o' => 0])],
        ], 'u' => apsolutni_url('grupni-lov', ['id' => $lovId])];
    }
    $tekst = trim(($lov['Mjesto'] ? '📍 ' . $lov['Mjesto'] . ' · ' : '') . 'Organizator: ' . $lov['KreiraoIme'] . ($lov['Napomena'] ? ' · ' . $lov['Napomena'] : ''));
    push_posalji($ids, 'grupni', '🎯 ' . $lov['Naziv'] . ' – dolazite?', $tekst, 'grupni-lov', ['id' => $lovId], 'gl-' . $lovId,
        ['ostaje' => true, 'vibracija' => [300, 150, 300], 'poKorisniku' => $po]);
}

// ---------- Odgovor ----------
function gl_odgovori(array $lov, array $s, int $odgovor): ?string
{
    if (!gl_aktivan($lov)) {
        return 'Ovaj skupni lov je završen.';
    }
    $odgovor = $odgovor ? 1 : 0;
    q('UPDATE GrupniLovSudionici SET Odgovor=?, Odgovoreno=?, Otisao=NULL' . ($odgovor ? '' : ', Lat=NULL, Lon=NULL, Tocnost=NULL, Polozaj=NULL, Skriveno=NULL, SignalJavljen=NULL') . ' WHERE LovId=? AND KorisnikId=?',
        [$odgovor, sada(), $lov['Id'], $s['KorisnikId']]);
    return null;
}

/** Odgovor preko osobnog ključa (gumb u obavijesti, poveznica). */
function gl_po_tokenu(string $t): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
        return null;
    }
    $s = red('SELECT * FROM GrupniLovSudionici WHERE Token=?', [$t]);
    return $s ? ['ja' => $s, 'lov' => gl_lov((int) $s['LovId'])] : null;
}

// ---------- Položaj, signal, SOS ----------
function gl_polozaj(array $lov, array $s, ?float $lat, ?float $lon, ?float $tocnost): void
{
    $polja = 'Polozaj=?, Skriveno=NULL, SignalJavljen=NULL';
    $p = [sada()];
    if ($lat !== null && $lon !== null && abs($lat) <= 90 && abs($lon) <= 180) {
        $polja .= ', Lat=?, Lon=?, Tocnost=?';
        array_push($p, round($lat, 6), round($lon, 6), $tocnost !== null ? min(round($tocnost), 99999) : null);
    }
    // ako se javi nakon upozorenja o gubitku signala – organizator dobiva obavijest da je opet tu
    if ($s['SignalJavljen'] !== null) {
        push_posalji(gl_organizatori($lov), 'grupni', '📶 ' . $s['Ime'] . ' – signal se vratio', $lov['Naziv'], 'grupni-lov', ['id' => $lov['Id']], 'gl-signal-' . $s['KorisnikId']);
    }
    $p[] = $lov['Id'];
    $p[] = $s['KorisnikId'];
    q("UPDATE GrupniLovSudionici SET $polja, Otisao=NULL WHERE LovId=? AND KorisnikId=? AND Odgovor=1", $p);
}

/** Zaslon ugašen / aplikacija u pozadini – položaj se ne šalje, ali to nije gubitak signala. */
function gl_skriveno(array $lov, array $s): void
{
    q('UPDATE GrupniLovSudionici SET Skriveno=? WHERE LovId=? AND KorisnikId=? AND Odgovor=1', [sada(), $lov['Id'], $s['KorisnikId']]);
}

/** Tko se dulje od GL_SIGNAL_MIN nije javio (a nije ugasio zaslon) – jedna obavijest organizatoru. */
function gl_provjeri_signal(array $lov): void
{
    $granica = date('Y-m-d H:i:s', time() - GL_SIGNAL_MIN * 60);
    $izgubljeni = redovi('SELECT * FROM GrupniLovSudionici WHERE LovId=? AND Odgovor=1 AND Otisao IS NULL AND Polozaj IS NOT NULL AND Polozaj<?
        AND Skriveno IS NULL AND SignalJavljen IS NULL', [$lov['Id'], $granica]);
    foreach ($izgubljeni as $s) {
        if (q('UPDATE GrupniLovSudionici SET SignalJavljen=? WHERE LovId=? AND KorisnikId=? AND SignalJavljen IS NULL', [sada(), $lov['Id'], $s['KorisnikId']])->rowCount()) {
            push_posalji(gl_organizatori($lov), 'grupni', '📡 ' . $s['Ime'] . ' – izgubljen signal',
                'Zadnji poznati položaj u ' . date('H:i', strtotime($s['Polozaj'])) . ' · ' . $lov['Naziv'], 'grupni-lov', ['id' => $lov['Id'], 'k' => $s['KorisnikId']],
                'gl-signal-' . $s['KorisnikId'], ['vibracija' => [400, 200, 400]]);
        }
    }
}

/** Organizatori lova: tko ga je pokrenuo + svi s pravom „Skupni lov – organizacija“ za njegove sekcije (npr. Lovnik na licu mjesta). */
function gl_organizatori(array $lov): array
{
    $ids = [(int) $lov['KreiraoKorisnikId']];
    foreach (gl_sekcije_lova($lov) as $sid) {
        $ids = array_merge($ids, push_korisnici_s_pravom(P_GRUPNI_LOV, $sid === 0 ? null : $sid));
    }
    return array_values(array_unique(array_filter($ids)));
}
/** SOS primatelji: organizatori + svi koji su trenutno u lovu. */
function gl_primatelji_sos(array $lov): array
{
    $u = array_map('intval', array_column(redovi('SELECT KorisnikId FROM GrupniLovSudionici WHERE LovId=? AND Odgovor=1 AND Otisao IS NULL', [$lov['Id']]), 'KorisnikId'));
    return array_values(array_unique(array_merge(gl_organizatori($lov), $u)));
}
/** Smije li trenutni korisnik preuzeti SOS: organizator ili sudionik u lovu. */
function gl_u_lovu(array $lov): bool
{
    $ja = gl_sudionik((int) $lov['Id'], (int) (korisnik()['Id'] ?? 0));
    return gl_moze_upravljati($lov) || ($ja && (int) $ja['Odgovor'] === 1 && $ja['Otisao'] === null);
}

function gl_sos(array $lov, array $s, ?float $lat, ?float $lon): ?string
{
    if (!gl_aktivan($lov)) {
        return 'Skupni lov je završen. U nuždi nazovite 112.';
    }
    if ($lat !== null && $lon !== null) {
        gl_polozaj($lov, $s, $lat, $lon, null);
    }
    q('UPDATE GrupniLovSudionici SET SosOd=COALESCE(SosOd, ?), SosLat=?, SosLon=?, SosPreuzeoIme=NULL, SosPreuzeto=NULL, Odgovor=1 WHERE LovId=? AND KorisnikId=?',
        [sada(), $lat ?? $s['Lat'], $lon ?? $s['Lon'], $lov['Id'], $s['KorisnikId']]);
    $tel = $s['ClanId'] ? (string) vrijednost('SELECT MobilniTelefon FROM Clanovi WHERE Id=?', [$s['ClanId']]) : '';
    push_posalji(gl_primatelji_sos($lov), 'grupni', '🆘 SOS – ' . $s['Ime'] . ' treba pomoć!',
        ($tel !== '' ? '📞 ' . $tel . ' · ' : '') . 'Dodirnite za položaj na karti. ' . $lov['Naziv'], 'grupni-lov', ['id' => $lov['Id'], 'k' => $s['KorisnikId']],
        'gl-sos-' . $s['KorisnikId'], ['ostaje' => true, 'vibracija' => [800, 200, 800, 200, 800, 200, 800]]);
    dnevnik('Skupni lov – SOS', 'GrupniLov', (int) $lov['Id'], $s['Ime']);
    return null;
}

function gl_sos_otkazi(array $lov, array $s): void
{
    q('UPDATE GrupniLovSudionici SET SosOd=NULL, SosLat=NULL, SosLon=NULL, SosPreuzeoIme=NULL, SosPreuzeto=NULL WHERE LovId=? AND KorisnikId=?', [$lov['Id'], $s['KorisnikId']]);
    push_posalji(gl_primatelji_sos($lov), 'grupni', '✅ ' . $s['Ime'] . ' – SOS otkazan', 'Sve u redu · ' . $lov['Naziv'], 'grupni-lov', ['id' => $lov['Id']], 'gl-sos-' . $s['KorisnikId']);
}

function gl_sos_preuzmi(array $lov, int $korisnikId): ?string
{
    $k = korisnik();
    $s = gl_sudionik((int) $lov['Id'], $korisnikId);
    if (!$s || !q('UPDATE GrupniLovSudionici SET SosPreuzeoIme=?, SosPreuzeto=? WHERE LovId=? AND KorisnikId=? AND SosOd IS NOT NULL', [$k['Naziv'], sada(), $lov['Id'], $korisnikId])->rowCount()) {
        return 'SOS više nije aktivan.';
    }
    // ostali vide da je netko krenuo (zamjenjuje SOS obavijest)
    push_posalji(array_diff(gl_primatelji_sos($lov), [$korisnikId]), 'grupni', '🚑 ' . $k['Naziv'] . ' ide pomoći – ' . $s['Ime'],
        'SOS preuzet · ' . $lov['Naziv'], 'grupni-lov', ['id' => $lov['Id'], 'k' => $korisnikId], 'gl-sos-' . $korisnikId);
    push_posalji([$korisnikId], 'grupni', '🚑 Pomoć stiže', $k['Naziv'] . ' je primio vaš SOS. Ostanite na mjestu, karta neka bude otvorena.', 'grupni-lov', ['id' => $lov['Id']], 'gl-sos-odg',
        ['ostaje' => true, 'vibracija' => [300, 100, 300]]);
    return null;
}

function gl_odlazim(array $lov, array $s): void
{
    q('UPDATE GrupniLovSudionici SET Otisao=?, Lat=NULL, Lon=NULL, Tocnost=NULL, Polozaj=NULL, Skriveno=NULL, SignalJavljen=NULL WHERE LovId=? AND KorisnikId=?', [sada(), $lov['Id'], $s['KorisnikId']]);
}

// ---------- Kraj ----------
/** Završava lov: briše sve položaje, obavještava sudionike i one koji nisu odgovorili (poziv nestaje). */
function gl_zavrsi(array $lov, string $tko): void
{
    if (!q('UPDATE GrupniLov SET Zavrseno=?, ZavrsioIme=? WHERE Id=? AND Zavrseno IS NULL', [min(sada(), $lov['Kraj']), $tko, $lov['Id']])->rowCount()) {
        return;
    }
    q('UPDATE GrupniLovSudionici SET Lat=NULL, Lon=NULL, Tocnost=NULL, SosLat=NULL, SosLon=NULL, Skriveno=NULL WHERE LovId=?', [$lov['Id']]);
    $ids = array_map('intval', array_column(redovi('SELECT KorisnikId FROM GrupniLovSudionici WHERE LovId=? AND (Odgovor=1 OR Odgovor IS NULL)', [$lov['Id']]), 'KorisnikId'));
    push_posalji($ids, 'grupni', '🏁 ' . $lov['Naziv'] . ' – lov završen', 'Lov završen. Položaj se više ne šalje. Lovu čast!', 'pocetna', [], 'gl-' . $lov['Id']);
    dnevnik('Skupni lov – završen', 'GrupniLov', (int) $lov['Id'], $lov['Naziv'] . ' · ' . $tko, str_starts_with($tko, 'automatski') ? ['Id' => null, 'KorisnickoIme' => 'sustav'] : null);
}

/** Lovovi kojima je prošlo vrijeme (23:00) – završavaju se pri prvom sljedećem zahtjevu. */
function gl_zatvori_istekle(): void
{
    foreach (redovi('SELECT * FROM GrupniLov WHERE Zavrseno IS NULL AND Kraj<=?', [sada()]) as $l) {
        gl_zavrsi($l, 'automatski ' . GL_SAT_KRAJA . ':00');
    }
}

// ---------- Stanje za kartu ----------
/** Status sudionika: dolazi | uzivo | pozadina | signal | bez-gps | otisao | ne | ceka */
function gl_status(array $s): string
{
    if ($s['Odgovor'] === null) {
        return 'ceka';
    }
    if ((int) $s['Odgovor'] === 0) {
        return 'ne';
    }
    if ($s['Otisao'] !== null) {
        return 'otisao';
    }
    if ($s['Polozaj'] === null) {
        return 'dolazi';
    }
    $star = strtotime($s['Polozaj']) < time() - GL_SIGNAL_MIN * 60;
    if ($s['Skriveno'] !== null && ($star || $s['Skriveno'] >= $s['Polozaj'])) {
        return 'pozadina';
    }
    if ($star) {
        return 'signal';
    }
    return $s['Lat'] === null ? 'bez-gps' : 'uzivo';
}

function gl_stanje(array $lov): array
{
    $k = korisnik();
    $org = gl_moze_upravljati($lov);
    $sekNazivi = array_column(redovi('SELECT Id, Naziv FROM Sekcije'), 'Naziv', 'Id');
    $tel = [];
    // telefoni: organizator vidi sve, sudionici samo onoga tko je poslao SOS
    foreach (redovi('SELECT c.Id, c.MobilniTelefon FROM GrupniLovSudionici s JOIN Clanovi c ON c.Id=s.ClanId WHERE s.LovId=?', [$lov['Id']]) as $c) {
        $tel[(int) $c['Id']] = $c['MobilniTelefon'];
    }
    $sudionici = [];
    $broj = ['dolazi' => 0, 'ne' => 0, 'ceka' => 0, 'otisao' => 0];
    $naPopisu = array_flip(array_map('intval', array_column(redovi('SELECT ClanId FROM GrupniLovPopis WHERE LovId=? AND ClanId IS NOT NULL', [$lov['Id']]), 'ClanId')));
    foreach (redovi('SELECT * FROM GrupniLovSudionici WHERE LovId=? ORDER BY Ime', [$lov['Id']]) as $s) {
        $st = gl_status($s);
        $broj[match ($st) { 'ne' => 'ne', 'ceka' => 'ceka', 'otisao' => 'otisao', default => 'dolazi' }]++;
        if (!$org && !in_array($st, ['uzivo', 'pozadina', 'signal', 'bez-gps', 'dolazi'], true)) {
            continue; // lovci vide samo one koji su u lovu
        }
        $sudionici[] = [
            'id' => (int) $s['KorisnikId'], 'ime' => $s['Ime'], 'st' => $st, 'ja' => (int) $s['KorisnikId'] === (int) $k['Id'],
            'lat' => $s['Lat'] !== null ? (float) $s['Lat'] : null, 'lon' => $s['Lon'] !== null ? (float) $s['Lon'] : null,
            'toc' => $s['Tocnost'] !== null ? (int) $s['Tocnost'] : null,
            'vrijeme' => $s['Polozaj'] ? date('H:i', strtotime($s['Polozaj'])) : null,
            'prije' => $s['Polozaj'] ? max(0, (int) floor((time() - strtotime($s['Polozaj'])) / 60)) : null,
            'sekcija' => $s['SekcijaId'] !== null ? ($sekNazivi[(int) $s['SekcijaId']] ?? '') : '',
            'sos' => $s['SosOd'] ? ['od' => date('H:i', strtotime($s['SosOd'])), 'lat' => $s['SosLat'] !== null ? (float) $s['SosLat'] : null,
                'lon' => $s['SosLon'] !== null ? (float) $s['SosLon'] : null, 'preuzeo' => $s['SosPreuzeoIme']] : null,
            'tel' => ($org || $s['SosOd']) && $s['ClanId'] ? ($tel[(int) $s['ClanId']] ?? null) : null,
            'popis' => $s['ClanId'] !== null && isset($naPopisu[(int) $s['ClanId']]),
        ];
    }
    $ja = gl_sudionik((int) $lov['Id'], (int) $k['Id']);
    $orgTel = $lov['KreiraoClanId'] ? vrijednost('SELECT MobilniTelefon FROM Clanovi WHERE Id=?', [$lov['KreiraoClanId']]) : null;
    return [
        'id' => (int) $lov['Id'], 'naziv' => $lov['Naziv'], 'aktivan' => gl_aktivan($lov), 'org' => $org,
        'kraj' => date('H:i', strtotime($lov['Kraj'])), 'organizator' => $lov['KreiraoIme'], 'orgTel' => $orgTel ?: null,
        'broj' => $broj, 'naPopisu' => $org ? (int) vrijednost('SELECT COUNT(*) FROM GrupniLovPopis WHERE LovId=?', [$lov['Id']]) : null, 'sudionici' => $sudionici, 'vrijeme' => date('H:i:s'),
        'ja' => $ja ? ['odgovor' => $ja['Odgovor'] === null ? null : (int) $ja['Odgovor'], 'otisao' => $ja['Otisao'] !== null, 'sos' => $ja['SosOd'] !== null,
            'preuzeo' => $ja['SosPreuzeoIme']] : null,
    ];
}

/** Broj za izbornik: organizator – aktivni SOS ili izgubljeni signal; lovac – 1 dok nije odgovorio. */
function gl_broj_izbornik(): int
{
    $n = 0;
    foreach (gl_aktivni_organizator() as $l) {
        $n += (int) vrijednost('SELECT COUNT(*) FROM GrupniLovSudionici WHERE LovId=? AND (SosOd IS NOT NULL AND SosPreuzeto IS NULL OR SignalJavljen IS NOT NULL)', [$l['Id']]);
    }
    $m = gl_moj_aktivni();
    if ($m && $m['ja']['Odgovor'] === null) {
        $n++;
    }
    return $n;
}

// ---------- Popis sudionika (lovnik skenira članske iskaznice) ----------
// Popis služi samo kao popis – ne utječe na poziv (dolazim / ne dolazim), bodove, dezinfekciju ni ništa drugo.

/** Uvjet za članove sekcija lova (0 = članovi bez sekcije). */
function gl_uvjet_sekcija(array $lov, string $alias = 'c'): string
{
    $sek = gl_sekcije_lova($lov);
    $uvjet = [];
    $ids = array_filter($sek);
    if ($ids) {
        $uvjet[] = "$alias.SekcijaId IN (" . implode(',', $ids) . ')';
    }
    if (in_array(0, $sek, true)) {
        $uvjet[] = "$alias.SekcijaId IS NULL";
    }
    return $uvjet ? '(' . implode(' OR ', $uvjet) . ')' : '1=0';
}

/**
 * Članovi sekcija lova za skeniranje bez signala i ručni upis: [['t' => ključ, 'id', 'ime', 'sek', 'f' => adresa fotografije]].
 * Iskaznice članova drugih sekcija prepoznaje poslužitelj (kad ima signala).
 */
function gl_popis_imenik(array $lov): array
{
    $clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime, c.FotoDatoteka, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
        WHERE c.Status=' . CLAN_AKTIVAN . ' AND ' . gl_uvjet_sekcija($lov));
    $kljucevi = isk_kljucevi(array_column($clanovi, 'Id'));
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
    return array_map(fn($c) => [
        't' => $kljucevi[(int) $c['Id']] ?? '', 'id' => (int) $c['Id'], 'ime' => prezime_ime($c),
        'sek' => (string) ($c['SekcijaNaziv'] ?? ''), 'f' => $c['FotoDatoteka'] ? foto_url($c['FotoDatoteka']) : null,
        'pot' => potpis_clana((int) $c['Id']) !== null,
    ], $clanovi);
}

function gl_popis(array $lov): array
{
    return redovi('SELECT * FROM GrupniLovPopis WHERE LovId=? ORDER BY Vrijeme, Id', [$lov['Id']]);
}

/** Popis za stranicu (JSON). */
function gl_popis_json(array $lov): array
{
    $foto = [];
    foreach (redovi('SELECT p.ClanId, c.FotoDatoteka FROM GrupniLovPopis p JOIN Clanovi c ON c.Id=p.ClanId WHERE p.LovId=? AND c.FotoDatoteka IS NOT NULL', [$lov['Id']]) as $r) {
        $foto[(int) $r['ClanId']] = foto_url($r['FotoDatoteka']);
    }
    return array_map(fn($p) => [
        'id' => (int) $p['Id'], 'clan' => $p['ClanId'] !== null ? (int) $p['ClanId'] : null, 'ime' => $p['Ime'], 'sek' => (string) $p['Sekcija'],
        'gost' => (bool) $p['Gost'], 'nacin' => $p['Nacin'], 'vrijeme' => date('H:i', strtotime($p['Vrijeme'])), 'upisao' => (string) $p['Upisao'],
        'f' => $p['ClanId'] !== null ? ($foto[(int) $p['ClanId']] ?? null) : null,
        'isk' => (string) ($p['GostIskaznica'] ?? ''), 'ol' => (string) ($p['GostOruzni'] ?? ''),
        'pot' => !empty($p['Potpis']) || ($p['ClanId'] !== null && potpis_clana((int) $p['ClanId']) !== null),
    ], gl_popis($lov));
}

/** Vrijeme skeniranja s mobitela (i bez signala) – ne u budućnosti i ne prije početka lova. */
function gl_popis_vrijeme(array $lov, ?int $ms): string
{
    if (!$ms) {
        return sada();
    }
    $t = intdiv($ms, 1000);
    $t = min($t, time());
    $t = max($t, strtotime($lov['Pocetak']) - 6 * 3600); // npr. skenirano na okupljanju prije pokretanja lova
    return date('Y-m-d H:i:s', $t);
}

/**
 * Skenirana iskaznica → upis u popis.
 * Vraća ['ok' => bool, 'vec' => bool (već je na popisu), 'ime', 'sek', 'f', 'greska', 'trajno' (ne pokušavati ponovno)].
 */
function gl_popis_skeniraj(array $lov, string $tekst, ?int $ms = null, ?string $klijentId = null): array
{
    $k = korisnik();
    $token = isk_token_iz_teksta($tekst);
    if (!$token) {
        return ['ok' => false, 'trajno' => true, 'greska' => 'Ovo nije članska iskaznica.'];
    }
    $c = isk_clan($token);
    if (!$c) {
        return ['ok' => false, 'trajno' => true, 'greska' => 'Iskaznica ne vrijedi (zamijenjena novom ili član više ne postoji).'];
    }
    $ime = prezime_ime($c);
    if ((int) $c['Status'] !== CLAN_AKTIVAN) {
        return ['ok' => false, 'trajno' => true, 'ime' => $ime, 'greska' => 'Iskaznica ne vrijedi – ' . $ime . ' nije aktivan član.'];
    }
    $rez = ['ok' => true, 'vec' => false, 'ime' => $ime, 'sek' => (string) ($c['SekcijaNaziv'] ?? ''), 'f' => $c['FotoDatoteka'] ? foto_url($c['FotoDatoteka']) : null];
    $vec = red('SELECT Id, Potpis FROM GrupniLovPopis WHERE LovId=? AND ClanId=?', [$lov['Id'], $c['Id']]);
    if (!$vec) {
        q('INSERT OR IGNORE INTO GrupniLovPopis (LovId, ClanId, Ime, Sekcija, Gost, Nacin, Vrijeme, Upisao, KlijentId, Kreirano) VALUES (?,?,?,?,0,?,?,?,?,?)',
            [$lov['Id'], $c['Id'], $ime, $rez['sek'] ?: null, 'qr', gl_popis_vrijeme($lov, $ms), $k['Naziv'], $klijentId ? mb_substr($klijentId, 0, 40) : null, sada()]);
        $vec = red('SELECT Id, Potpis FROM GrupniLovPopis WHERE LovId=? AND ClanId=?', [$lov['Id'], $c['Id']]);
    } else {
        $rez['vec'] = true;
    }
    // bez spremljenog potpisa član se potpiše na mobitelu voditelja lova
    $rez['pid'] = (int) $vec['Id'];
    $rez['pot'] = !empty($vec['Potpis']) || potpis_clana((int) $c['Id']) !== null;
    return $rez;
}

/** Ručni upis: član (bez iskaznice pri ruci) ili gost (ime, broj lovačke iskaznice, oružni list, potpis na mobitelu). */
function gl_popis_rucno(array $lov, ?int $clanId, string $gost, array $gostPodaci = []): ?string
{
    $k = korisnik();
    if ($clanId) {
        $c = red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id=? AND c.Status=' . CLAN_AKTIVAN . ' AND ' . gl_uvjet_sekcija($lov), [$clanId]);
        if (!$c) {
            return 'Član nije pronađen među aktivnim članovima sekcija ovog lova – člana druge sekcije upišite skeniranjem iskaznice.';
        }
        if (vrijednost('SELECT 1 FROM GrupniLovPopis WHERE LovId=? AND ClanId=?', [$lov['Id'], $c['Id']])) {
            return prezime_ime($c) . ' je već na popisu.';
        }
        umetni('GrupniLovPopis', ['LovId' => $lov['Id'], 'ClanId' => $c['Id'], 'Ime' => prezime_ime($c), 'Sekcija' => $c['SekcijaNaziv'] ?: null, 'Gost' => 0,
            'Nacin' => 'rucno', 'Vrijeme' => sada(), 'Upisao' => $k['Naziv'], 'Kreirano' => sada()]);
        return null;
    }
    $gost = mb_substr(trim(preg_replace('/\s+/', ' ', $gost)), 0, 80);
    if (mb_strlen($gost) < 3) {
        return 'Upišite ime i prezime gosta.';
    }
    $isk = mb_substr(trim((string) ($gostPodaci['iskaznica'] ?? '')), 0, 40);
    $oruzni = mb_substr(trim((string) ($gostPodaci['oruzni'] ?? '')), 0, 120);
    if ($isk === '' || $oruzni === '') {
        return 'Za gosta upišite broj lovačke iskaznice i oružni list.';
    }
    $potpis = null;
    $crtez = (string) ($gostPodaci['potpis'] ?? '');
    if (str_starts_with($crtez, 'data:image/png;base64,')) {
        $tmp = tempnam(sys_get_temp_dir(), 'pot');
        file_put_contents($tmp, (string) base64_decode(substr($crtez, 22), true));
        try {
            $potpis = spremi_sliku(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'potpis.png'], 'glpotpis-', 600, true);
        } catch (Throwable) {
            $potpis = null;
        } finally {
            @unlink($tmp);
        }
    }
    umetni('GrupniLovPopis', ['LovId' => $lov['Id'], 'ClanId' => null, 'Ime' => $gost, 'Sekcija' => null, 'Gost' => 1,
        'Nacin' => 'rucno', 'Vrijeme' => sada(), 'Upisao' => $k['Naziv'], 'Kreirano' => sada(),
        'GostIskaznica' => $isk, 'GostOruzni' => $oruzni, 'Potpis' => $potpis]);
    return null;
}

/** Potpis sudionika na mobitelu (za PDF); $spremi = zapamti kao potpis člana za sljedeći put. @return string|null greška */
function gl_popis_potpis(array $lov, int $pid, string $crtez, bool $spremi): ?string
{
    $p = red('SELECT * FROM GrupniLovPopis WHERE Id=? AND LovId=?', [$pid, $lov['Id']]);
    if (!$p) {
        return 'Sudionik nije na popisu.';
    }
    if (!str_starts_with($crtez, 'data:image/png;base64,')) {
        return 'Potpis nije zaprimljen.';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'pot');
    file_put_contents($tmp, (string) base64_decode(substr($crtez, 22), true));
    try {
        $ime = spremi_sliku(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'potpis.png'], 'glpotpis-', 600, true);
    } catch (Throwable $e) {
        return 'Potpis nije spremljen: ' . $e->getMessage();
    } finally {
        @unlink($tmp);
    }
    if ($p['Potpis']) {
        obrisi_sliku($p['Potpis']);
    }
    q('UPDATE GrupniLovPopis SET Potpis=? WHERE Id=?', [$ime, $pid]);
    if ($spremi && $p['ClanId'] !== null) {
        potpis_clana_spremi_kopiju((int) $p['ClanId'], $ime);
        dnevnik('Spremljen potpis člana (skupni lov)', 'Clan', (int) $p['ClanId'], $p['Ime']);
    }
    return null;
}

function gl_popis_ukloni(array $lov, int $id): void
{
    $p = red('SELECT * FROM GrupniLovPopis WHERE Id=? AND LovId=?', [$id, $lov['Id']]);
    if ($p) {
        q('DELETE FROM GrupniLovPopis WHERE Id=?', [$id]);
        if ($p['Potpis']) {
            obrisi_sliku($p['Potpis']);
        }
        dnevnik('Skupni lov – uklonjen s popisa', 'GrupniLov', (int) $lov['Id'], $p['Ime']);
    }
}

/** Potpis sudionika (gost se potpisao na mobitelu) za PDF. */
function gl_potpis_img(?string $f): string
{
    $put = $f ? podaci('foto/' . basename($f)) : null;
    return $put && is_file($put) ? '<img src="data:image/png;base64,' . base64_encode((string) file_get_contents($put)) . '" style="height:6mm;max-width:30mm">' : '';
}

/** PDF popisa sudionika (abecedno). */
function gl_popis_pdf(array $lov): string
{
    $popis = gl_popis($lov);
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    // članovi abecedno, zatim gosti
    usort($popis, fn($a, $b) => ((int) $a['Gost'] <=> (int) $b['Gost'])
        ?: ($coll ? $coll->compare($a['Ime'], $b['Ime']) : strcmp(kljuc($a['Ime']), kljuc($b['Ime']))));
    $clanova = count(array_filter($popis, fn($p) => !$p['Gost']));
    $ids = array_filter(array_map(fn($p) => (int) $p['ClanId'], $popis));
    $oruzje = oruzni_listovi_brojevi($ids);
    $isk = $ids ? array_column(redovi('SELECT Id, BrojIskaznice FROM Clanovi WHERE Id IN (' . implode(',', $ids) . ')'), 'BrojIskaznice', 'Id') : [];
    $redovi = '';
    foreach ($popis as $i => $p) {
        $cid = (int) $p['ClanId'];
        $redovi .= '<tr><td class="d">' . ($i + 1) . '.</td><td>' . e($p['Ime']) . ($p['Gost'] ? ' <i>(gost)</i>' : '') . '</td>'
            . '<td>' . e($p['Gost'] ? (string) $p['GostIskaznica'] : (string) ($isk[$cid] ?? '')) . '</td><td class="ol">' . e($p['Gost'] ? (string) $p['GostOruzni'] : ($oruzje[$cid] ?? '')) . '</td>'
            . '<td>' . ($p['Nacin'] === 'qr' ? 'iskaznica' : 'ručno') . '</td><td class="potpis-polje">' . gl_potpis_img(($p['Potpis'] ?? null) ?: ($cid ? potpis_clana($cid) : null)) . '</td></tr>';
    }
    $kraj = $lov['Zavrseno'] ? date('H:i', strtotime($lov['Zavrseno'])) : '';
    // osobni potpis voditelja lova (Moj profil) – tek kad je lov završen
    $pot = !gl_aktivan($lov) && $lov['KreiraoKorisnikId'] ? potpis_datauri((int) $lov['KreiraoKorisnikId']) : null;
    $potpis = $pot ? '<img src="' . $pot . '" style="height:14mm;vertical-align:middle"><br><span style="font-size:7pt;color:#777">elektronički generirano iz evidencije</span>'
        : '____________________';
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
        h2 { font-size: 12pt; margin: 4px 0 2px; } .info { color: #555; margin-bottom: 8px; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #999; padding: 4px 4px; vertical-align: middle; } .t .d { text-align: right; white-space: nowrap; }
        .t td.potpis-polje { width: 30mm; } .t tbody td { height: 6mm; } .t td.ol { font-size: 8pt; }
        .potpis { width: 100%; margin-top: 14mm; font-size: 9pt; } .potpis td { padding: 0 4px; }
        .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag"><div class="n">' . e(udruga_naziv()) . '</div><div class="p">Popis sudionika skupnog lova</div></div>
    <h2>' . e($lov['Naziv']) . '</h2>
    <div class="info">' . e(datum($lov['Pocetak'])) . ' · ' . e(date('H:i', strtotime($lov['Pocetak']))) . ($kraj ? '–' . e($kraj) : '')
        . ($lov['Mjesto'] ? ' · ' . e($lov['Mjesto']) : '') . ' · organizator ' . e($lov['KreiraoIme'])
        . '<br>Ukupno <b>' . count($popis) . '</b> (članova ' . $clanova . ', gostiju ' . (count($popis) - $clanova) . ')</div>
    <table class="t"><thead><tr><th class="d">Rb.</th><th>Prezime i ime</th><th>Broj lovačke iskaznice</th><th>Oružni list</th><th>Upis</th><th>Potpis</th></tr></thead>
    <tbody>' . ($redovi ?: '<tr><td colspan="6">Popis je prazan.</td></tr>') . '</tbody></table>
    <table class="potpis"><tr><td>Lovnik / voditelj lova: <b>' . e((string) $lov['KreiraoIme']) . '</b></td><td>Potpis: ' . $potpis . '</td></tr></table>
    </body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->getCanvas()->page_text(520, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}

// ---------- Brisanje završenih lovova ----------
/** Trajno briše završeni lov (sudionici i popis se brišu s njim). Aktivni lov se ne briše. */
function gl_obrisi(array $lov): bool
{
    if (gl_aktivan($lov) || !gl_moze_upravljati($lov)) {
        return false;
    }
    $n = (int) vrijednost('SELECT COUNT(*) FROM GrupniLovPopis WHERE LovId=?', [$lov['Id']]);
    transakcija(function () use ($lov) {
        foreach (redovi('SELECT Potpis FROM GrupniLovPopis WHERE LovId=? AND Potpis IS NOT NULL', [$lov['Id']]) as $r) {
            obrisi_sliku($r['Potpis']);
        }
        q('DELETE FROM GrupniLovPopis WHERE LovId=?', [$lov['Id']]);
        q('DELETE FROM GrupniLovSudionici WHERE LovId=?', [$lov['Id']]);
        q('DELETE FROM GrupniLov WHERE Id=?', [$lov['Id']]);
    });
    dnevnik('Skupni lov – obrisan', 'GrupniLov', (int) $lov['Id'], $lov['Naziv'] . ' · ' . datum($lov['Pocetak']) . " · na popisu $n");
    return true;
}

/** Svi završeni lovovi kojima trenutni korisnik upravlja. */
function gl_zavrseni_moji(): array
{
    return array_values(array_filter(redovi('SELECT * FROM GrupniLov WHERE Zavrseno IS NOT NULL ORDER BY Id DESC'), 'gl_moze_upravljati'));
}
