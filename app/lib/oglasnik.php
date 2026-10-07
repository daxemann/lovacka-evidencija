<?php
/** Oglasnik (kupujem/prodajem) i poruke među članovima. */
declare(strict_types=1);

const KATEGORIJE_OGLASA = ['Oružje', 'Streljivo', 'Optika', 'Pribor i oprema', 'Odjeća i obuća', 'Lovački psi', 'Ostalo'];
const STANJA_ARTIKLA = ['Novo', 'Kao novo', 'Rabljeno', 'Za popravak / dijelove'];
const STATUSI_OGLASA = ['Aktivan', 'Rezervirano', 'Prodano', 'Uklonjeno'];

/** Broj nepročitanih poruka za korisnika (u razgovorima gdje je kupac ili prodavatelj). */
function broj_neprocitanih(int $korisnikId): int
{
    return (int) vrijednost('SELECT COUNT(*) FROM PorukeRazgovora p JOIN Razgovori r ON r.Id=p.RazgovorId JOIN Oglasi o ON o.Id=r.OglasId
        WHERE p.Procitano=0 AND p.PosiljateljId<>? AND (r.KupacId=? OR o.KorisnikId=?)', [$korisnikId, $korisnikId, $korisnikId]);
}

/** Briše sve oglase korisnika sa slikama (razgovori i poruke se brišu kaskadno). */
function obrisi_oglase_korisnika(int $korisnikId): void
{
    foreach (redovi('SELECT s.Datoteka FROM OglasSlike s JOIN Oglasi o ON o.Id=s.OglasId WHERE o.KorisnikId=?', [$korisnikId]) as $s) {
        obrisi_sliku($s['Datoteka']);
    }
    q('DELETE FROM Oglasi WHERE KorisnikId=?', [$korisnikId]);
    q('DELETE FROM Razgovori WHERE KupacId=?', [$korisnikId]);
}

const MAX_SLIKA_OGLASA = 8;

function cijena_oglasa(array $o): string
{
    if ($o['Cijena'] === null || $o['Cijena'] === '') {
        return $o['PoDogovoru'] ? 'po dogovoru' : '';
    }
    $c = (float) $o['Cijena'];
    $s = fmod($c, 1.0) == 0.0 ? number_format($c, 0, ',', '.') : number_format($c, 2, ',', '.');
    return $s . ' €' . ($o['PoDogovoru'] ? ' (dogovor)' : '');
}
function treba_dozvolu(int $kategorija): bool
{
    return $kategorija === 0 || $kategorija === 1;
}
function oglas(int $id): ?array
{
    return red('SELECT * FROM Oglasi WHERE Id=?', [$id]);
}
function slike_oglasa(int $id): array
{
    return array_column(redovi('SELECT Datoteka FROM OglasSlike WHERE OglasId=? ORDER BY Redoslijed, Id', [$id]), 'Datoteka');
}
/** Ime korisnika za prikaz (član ili prikazno ime). */
function ime_korisnika(int $kid): string
{
    $r = red('SELECT k.KorisnickoIme, k.PrikaznoIme, c.Ime, c.Prezime FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Id=?', [$kid]);
    if (!$r) {
        return '(obrisan korisnik)';
    }
    return $r['Ime'] !== null ? trim($r['Ime'] . ' ' . $r['Prezime']) : ($r['PrikaznoIme'] ?: $r['KorisnickoIme']);
}

function obrisi_oglas(int $id): void
{
    foreach (slike_oglasa($id) as $s) {
        obrisi_sliku($s);
    }
    q('DELETE FROM Oglasi WHERE Id=?', [$id]);
}

/** Kupac piše prodavatelju: otvara (ili nalazi) razgovor i šalje poruku. Vraća Id razgovora. */
function pisi_prodavatelju(int $oglasId, int $kupacId, string $tekst): int
{
    $o = oglas($oglasId);
    if (!$o) {
        throw new RuntimeException('Oglas ne postoji.');
    }
    if ((int) $o['KorisnikId'] === $kupacId) {
        throw new RuntimeException('Ne možete pisati na vlastiti oglas.');
    }
    if ((int) $o['Status'] >= 2) {
        throw new RuntimeException('Oglas više nije aktivan.');
    }
    $r = red('SELECT * FROM Razgovori WHERE OglasId=? AND KupacId=?', [$oglasId, $kupacId]);
    $rid = $r ? (int) $r['Id'] : umetni('Razgovori', ['OglasId' => $oglasId, 'KupacId' => $kupacId, 'Kreirano' => sada(), 'ZadnjaPoruka' => sada()]);
    posalji_poruku($rid, $kupacId, $tekst);
    return $rid;
}

/** Nova poruka u razgovoru; primatelj dobiva e-mail ako je podešena pošta (samo za prvu nepročitanu). */
function posalji_poruku(int $razgovorId, int $posiljateljId, string $tekst): void
{
    $tekst = trim(mb_substr($tekst, 0, 2000));
    if ($tekst === '') {
        return;
    }
    $r = red('SELECT r.*, o.KorisnikId AS ProdavateljId, o.Naslov FROM Razgovori r JOIN Oglasi o ON o.Id=r.OglasId WHERE r.Id=?', [$razgovorId]);
    if (!$r) {
        return;
    }
    $primatelj = $posiljateljId === (int) $r['KupacId'] ? (int) $r['ProdavateljId'] : (int) $r['KupacId'];
    $vecCeka = vrijednost('SELECT 1 FROM PorukeRazgovora WHERE RazgovorId=? AND PosiljateljId=? AND Procitano=0', [$razgovorId, $posiljateljId]);
    umetni('PorukeRazgovora', ['RazgovorId' => $razgovorId, 'PosiljateljId' => $posiljateljId, 'Tekst' => $tekst, 'Vrijeme' => sada(), 'Procitano' => 0]);
    q('UPDATE Razgovori SET ZadnjaPoruka=? WHERE Id=?', [sada(), $razgovorId]);
    if (!$vecCeka && posta_dostupna()) {
        $email = vrijednost('SELECT c.Email FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Id=? AND k.Aktivan=1', [$primatelj]);
        if ($email) {
            $link = apsolutni_url('poruke/razgovor', ['id' => $razgovorId]);
            try {
                posalji_mail((string) $email, 'Nova poruka – oglasnik ' . udruga_kratko(),
                    '<p>Pozdrav,</p><p><b>' . e(ime_korisnika($posiljateljId)) . '</b> vam je poslao poruku u oglasniku ' . e(udruga_kratko()) . ' za oglas „' . e($r['Naslov']) . '“:</p><blockquote>'
                    . nl2br(e($tekst)) . '</blockquote><p><a href="' . e($link) . '">Odgovori u aplikaciji</a></p>');
            } catch (Throwable $e) {
                error_log('Obavijest o poruci nije poslana: ' . $e->getMessage());
            }
        }
    }
}

function oznaci_procitano(int $razgovorId, int $korisnikId): void
{
    q('UPDATE PorukeRazgovora SET Procitano=1 WHERE RazgovorId=? AND PosiljateljId<>? AND Procitano=0', [$razgovorId, $korisnikId]);
}

/** Razgovor ako mu korisnik pripada (kupac ili prodavatelj). */
function razgovor_za(int $razgovorId, int $korisnikId): ?array
{
    return red('SELECT r.*, o.KorisnikId AS ProdavateljId, o.Naslov, o.Cijena, o.PoDogovoru, o.Status AS OglasStatus FROM Razgovori r JOIN Oglasi o ON o.Id=r.OglasId
                WHERE r.Id=? AND (r.KupacId=? OR o.KorisnikId=?)', [$razgovorId, $korisnikId, $korisnikId]);
}
