<?php
/** Pozivnice, računi članova i uloge. */
declare(strict_types=1);

function pronadji_pozivnicu(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    return red('SELECT p.*, c.Ime, c.Prezime, c.Email, c.Status AS ClanStatus FROM Pozivnice p JOIN Clanovi c ON c.Id=p.ClanId
                WHERE p.TokenHash=? AND p.Opozvana=0 AND p.Iskoristena IS NULL AND p.VrijediDo>? AND c.Status=0', [hash_tokena($token), sada()]);
}

/** Nova pozivnica za člana – stare se opozivaju. Vraća poveznicu. */
function kreiraj_pozivnicu(int $clanId): string
{
    q('UPDATE Pozivnice SET Opozvana=1 WHERE ClanId=? AND Iskoristena IS NULL AND Opozvana=0', [$clanId]);
    $t = novi_token();
    umetni('Pozivnice', [
        'ClanId' => $clanId, 'TokenHash' => hash_tokena($t), 'Kreirano' => sada(),
        'VrijediDo' => date('Y-m-d H:i:s', time() + 7 * 86400), 'KreiraoIme' => korisnik()['Naziv'] ?? null,
        'Iskoristena' => null, 'Opozvana' => 0,
    ]);
    return apsolutni_url('pozivnica', ['token' => $t]);
}

function racun_clana(int $clanId): ?array
{
    return red('SELECT * FROM Korisnici WHERE ClanId=?', [$clanId]);
}

/** Uloge korisnika s nazivima: [Id, UlogaId, SekcijaId, Naziv, Sekcija, Prava] */
function uloge_korisnika(int $korisnikId): array
{
    return redovi('SELECT ku.Id, ku.UlogaId, ku.SekcijaId, u.Naziv, u.Prava, s.Naziv AS Sekcija FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId
                   LEFT JOIN Sekcije s ON s.Id=ku.SekcijaId WHERE ku.KorisnikId=? ORDER BY u.Id', [$korisnikId]);
}
function planirane_uloge(int $clanId): array
{
    return redovi('SELECT pu.Id, pu.UlogaId, pu.SekcijaId, u.Naziv, u.Prava, s.Naziv AS Sekcija FROM PlaniraneUloge pu JOIN Uloge u ON u.Id=pu.UlogaId
                   LEFT JOIN Sekcije s ON s.Id=pu.SekcijaId WHERE pu.ClanId=? ORDER BY u.Id', [$clanId]);
}

/** Broj glavnih administratora (uloga sa svim pravima bez ograničenja sekcije) koji su aktivni. */
function broj_glavnih_admina(?int $osimKorisnika = null): int
{
    return (int) vrijednost('SELECT COUNT(DISTINCT k.Id) FROM Korisnici k JOIN KorisnikUloge ku ON ku.KorisnikId=k.Id JOIN Uloge u ON u.Id=ku.UlogaId
        WHERE k.Aktivan=1 AND k.Odobren=1 AND ku.SekcijaId IS NULL AND (u.Prava & 255)=255' . ($osimKorisnika ? ' AND k.Id<>' . (int) $osimKorisnika : ''));
}

function sekcije(bool $samoAktivne = false): array
{
    return redovi('SELECT * FROM Sekcije' . ($samoAktivne ? ' WHERE Aktivna=1' : '') . ' ORDER BY Redoslijed, Naziv');
}
/** Sekcije koje trenutni korisnik smije vidjeti. */
function moje_sekcije(): array
{
    return array_values(array_filter(sekcije(), fn($s) => moze_sekciju((int) $s['Id'])));
}
function naziv_sekcije(?int $id): string
{
    if ($id === null) {
        return '';
    }
    static $c = [];
    return $c[$id] ??= (string) vrijednost('SELECT Naziv FROM Sekcije WHERE Id=?', [$id]);
}

function broj_akcija_na_cekanju(): int
{
    return (int) vrijednost('SELECT COUNT(*) FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId WHERE a.Status=0 AND ' . opseg_sql('c'));
}

/** Računi bez člana (npr. admin iz prvog pokretanja) povezuju se s članom istog imena. Vraća broj povezanih. */
function povezi_racune(): int
{
    $nepovezani = redovi('SELECT Id, KorisnickoIme, PrikaznoIme FROM Korisnici WHERE ClanId IS NULL AND PrikaznoIme IS NOT NULL');
    if (!$nepovezani) {
        return 0;
    }
    $saRacunom = array_flip(array_map('intval', array_column(redovi('SELECT ClanId FROM Korisnici WHERE ClanId IS NOT NULL'), 'ClanId')));
    $clanovi = redovi('SELECT Id, Ime, Prezime FROM Clanovi WHERE Status=0');
    $n = 0;
    foreach ($nepovezani as $k) {
        $kl = kljuc($k['PrikaznoIme']);
        $pog = array_values(array_filter($clanovi, fn($c) => !isset($saRacunom[(int) $c['Id']]) && (kljuc(puno_ime($c)) === $kl || kljuc(prezime_ime($c)) === $kl)));
        if (count($pog) === 1) {
            q('UPDATE Korisnici SET ClanId=? WHERE Id=?', [$pog[0]['Id'], $k['Id']]);
            $saRacunom[(int) $pog[0]['Id']] = true;
            $n++;
        }
    }
    return $n;
}
