<?php
/** Imenik članova (član sam odlučuje što dijeli) i izravne poruke među članovima. */
declare(strict_types=1);

const POLJA_VIDLJIVOSTI = ['Mobitel' => 'Mobitel', 'Fiksni' => 'Fiksni telefon', 'Email' => 'E-mail', 'Mjesto' => 'Mjesto', 'Adresa' => 'Ulica i kućni broj'];

function vidljivost_clana(int $clanId): array
{
    $r = red('SELECT * FROM ClanVidljivost WHERE ClanId=?', [$clanId]) ?? [];
    $v = [];
    foreach (array_keys(POLJA_VIDLJIVOSTI) as $f) {
        $v[$f] = (bool) ($r[$f] ?? false);
    }
    return $v;
}
function spremi_vidljivost(int $clanId, array $v): void
{
    $p = ['ClanId' => $clanId, 'Azurirano' => sada()];
    foreach (array_keys(POLJA_VIDLJIVOSTI) as $f) {
        $p[$f] = !empty($v[$f]) ? 1 : 0;
    }
    q('INSERT INTO ClanVidljivost (ClanId, Mobitel, Fiksni, Email, Mjesto, Adresa, Azurirano) VALUES (?,?,?,?,?,?,?)
       ON CONFLICT(ClanId) DO UPDATE SET Mobitel=excluded.Mobitel, Fiksni=excluded.Fiksni, Email=excluded.Email, Mjesto=excluded.Mjesto, Adresa=excluded.Adresa, Azurirano=excluded.Azurirano',
        [$p['ClanId'], $p['Mobitel'], $p['Fiksni'], $p['Email'], $p['Mjesto'], $p['Adresa'], $p['Azurirano']]);
}
/** Vidi li trenutni korisnik sve kontakte (pravo Imenik ili pregled članova u opsegu). */
function vidi_sve_kontakte(array $clan): bool
{
    return ima(P_IMENIK_SVI) || (ima(P_CLANOVI_CITAJ) && u_opsegu($clan));
}

// ---------- Izravni razgovori (Razgovori2 / Poruke2) ----------
function izravni_razgovor(int $a, int $b, bool $stvori = true): ?int
{
    [$x, $y] = $a < $b ? [$a, $b] : [$b, $a];
    $id = vrijednost('SELECT Id FROM Razgovori2 WHERE KorisnikA=? AND KorisnikB=?', [$x, $y]);
    if ($id || !$stvori) {
        return $id ? (int) $id : null;
    }
    return umetni('Razgovori2', ['KorisnikA' => $x, 'KorisnikB' => $y, 'Kreirano' => sada(), 'ZadnjaPoruka' => sada()]);
}
function izravni_razgovor_za(int $razgovorId, int $korisnikId): ?array
{
    $r = red('SELECT * FROM Razgovori2 WHERE Id=? AND (KorisnikA=? OR KorisnikB=?)', [$razgovorId, $korisnikId, $korisnikId]);
    if ($r) {
        $r['Drugi'] = (int) $r['KorisnikA'] === $korisnikId ? (int) $r['KorisnikB'] : (int) $r['KorisnikA'];
    }
    return $r;
}
function broj_neprocitanih_izravno(int $korisnikId): int
{
    return (int) vrijednost('SELECT COUNT(*) FROM Poruke2 p JOIN Razgovori2 r ON r.Id=p.RazgovorId WHERE p.Procitano=0 AND p.PosiljateljId<>? AND (r.KorisnikA=? OR r.KorisnikB=?)',
        [$korisnikId, $korisnikId, $korisnikId]);
}
function posalji_izravnu_poruku(int $razgovorId, int $posiljateljId, string $tekst): void
{
    $tekst = trim(mb_substr($tekst, 0, 2000));
    $r = izravni_razgovor_za($razgovorId, $posiljateljId);
    if ($tekst === '' || !$r) {
        return;
    }
    $vecCeka = vrijednost('SELECT 1 FROM Poruke2 WHERE RazgovorId=? AND PosiljateljId=? AND Procitano=0', [$razgovorId, $posiljateljId]);
    umetni('Poruke2', ['RazgovorId' => $razgovorId, 'PosiljateljId' => $posiljateljId, 'Tekst' => $tekst, 'Vrijeme' => sada(), 'Procitano' => 0]);
    q('UPDATE Razgovori2 SET ZadnjaPoruka=? WHERE Id=?', [sada(), $razgovorId]);
    if (!$vecCeka && posta_dostupna()) {
        $email = vrijednost('SELECT c.Email FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Id=? AND k.Aktivan=1', [$r['Drugi']]);
        if ($email) {
            try {
                posalji_mail((string) $email, 'Nova poruka – ' . udruga_kratko(),
                    '<p>Pozdrav,</p><p><b>' . e(ime_korisnika($posiljateljId)) . '</b> vam je poslao poruku:</p><blockquote>' . nl2br(e($tekst))
                    . '</blockquote><p><a href="' . e(apsolutni_url('poruke/osoba', ['r' => $razgovorId])) . '">Odgovori u aplikaciji</a></p>');
            } catch (Throwable $e) {
                error_log('Obavijest o poruci nije poslana: ' . $e->getMessage());
            }
        }
    }
}
