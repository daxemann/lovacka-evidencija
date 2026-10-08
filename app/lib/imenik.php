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

// ---------- Brisanje poruka ----------
// Vrsta 'o' = oglasnik (Razgovori / PorukeRazgovora), 'c' = članovi (Razgovori2 / Poruke2)
const TABLICE_PORUKA = ['o' => ['Razgovori', 'PorukeRazgovora'], 'c' => ['Razgovori2', 'Poruke2']];

/** Do koje poruke je korisnik "obrisao" razgovor za sebe (0 = ništa). */
function skriveno_do(string $vrsta, int $razgovorId, int $korisnikId): int
{
    return (int) vrijednost('SELECT DoPorukeId FROM SkriveniRazgovori WHERE Vrsta=? AND RazgovorId=? AND KorisnikId=?', [$vrsta, $razgovorId, $korisnikId]);
}
/** Briše razgovor za korisnika (drugi ga i dalje vidi). Kad su ga obrisala oba sudionika, brišu se i poruke. */
function obrisi_razgovor_za_mene(string $vrsta, int $razgovorId, int $korisnikId, array $sudionici): void
{
    [$tR, $tP] = TABLICE_PORUKA[$vrsta];
    $max = (int) vrijednost("SELECT COALESCE(MAX(Id),0) FROM \"$tP\" WHERE RazgovorId=?", [$razgovorId]);
    q("UPDATE \"$tP\" SET Procitano=1 WHERE RazgovorId=? AND PosiljateljId<>?", [$razgovorId, $korisnikId]);
    q('INSERT INTO SkriveniRazgovori (Vrsta, RazgovorId, KorisnikId, DoPorukeId) VALUES (?,?,?,?)
       ON CONFLICT(Vrsta, RazgovorId, KorisnikId) DO UPDATE SET DoPorukeId=excluded.DoPorukeId', [$vrsta, $razgovorId, $korisnikId, $max]);
    $min = $max;
    foreach ($sudionici as $s) {
        $min = min($min, skriveno_do($vrsta, $razgovorId, (int) $s));
    }
    if ($min > 0) {
        q("DELETE FROM \"$tP\" WHERE RazgovorId=? AND Id<=?", [$razgovorId, $min]);
    }
}
/** Briše vlastitu poruku (za oba sudionika). */
function obrisi_svoju_poruku(string $vrsta, int $razgovorId, int $porukaId, int $korisnikId): void
{
    $tP = TABLICE_PORUKA[$vrsta][1];
    q("DELETE FROM \"$tP\" WHERE Id=? AND RazgovorId=? AND PosiljateljId=?", [$porukaId, $razgovorId, $korisnikId]);
}

/** Oblačić poruke; vlastitu poruku pošiljatelj može obrisati (×). */
function oblak_poruke(array $p, int $ja, string $akcija): string
{
    $moja = (int) $p['PosiljateljId'] === $ja;
    $brisi = $moja ? '<form method="post" action="' . e($akcija) . '" class="chat-brisi">' . csrf() . '<input type="hidden" name="radnja" value="obrisi-poruku"><input type="hidden" name="pid" value="' . (int) $p['Id'] . '">'
        . '<button class="btn btn-link btn-sm p-0" title="Obriši poruku" data-potvrda="Obrisati ovu poruku? Nestaje i kod sugovornika.">×</button></form>' : '';
    return '<div class="chat-red ' . ($moja ? 'moja' : '') . '" data-id="' . (int) $p['Id'] . '"><div class="chat-oblak">' . $brisi . '<div style="white-space:pre-wrap">'
        . e($p['Tekst']) . '</div><div class="chat-vrijeme">' . e(date('d.m. H:i', strtotime($p['Vrijeme']))) . ($moja && $p['Procitano'] ? ' ✓✓' : '') . '</div></div></div>';
}
