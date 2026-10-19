<?php
/**
 * Popusti u trgovinama – zajednički za sve udruge, na poslužitelju Oglasnika lovaca (kao Oglasnik).
 * Član ulazi jednokratnim potpisanim tokenom (isti ključ kao Oglasnik); u tokenu je njegova članska iskaznica
 * (ime, sekcija, mala fotografija) – Oglasnik je čuva samo u sesiji i prikazuje na blagajni sa satom koji teče.
 * Trgovine upisuje tko ovdje ima pravo „Popusti u trgovinama – uređivanje“ (P_POPUSTI) – za sve udruge ili samo za svoju.
 * Radi i kad je Oglasnik u udruzi isključen; udruga mora biti registrirana i odobrena u Oglasniku (Sustav → Oglasnik lovaca).
 */
declare(strict_types=1);

function pop_smije_uredjivati(): bool
{
    return ima(P_POPUSTI);
}

/** Je li udruga spojena na Oglasnik (ključ postoji)? Odobrenje provjerava Oglasnik pri ulasku. */
function pop_dostupno(): bool
{
    if (je_demo()) {
        return function_exists('demo_popusti'); // demo: primjeri trgovina iz proširenja, bez Oglasnika
    }
    return ogl_podrzano() && ogl_javni_kljuc() !== null;
}

/** Iskaznica aktivnog člana za token (null ako korisnik nije aktivan član). */
function pop_iskaznica(array $k): ?array
{
    if (!$k['ClanId']) {
        return null;
    }
    $c = red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE c.Id=?', [$k['ClanId']]);
    if (!$c || (int) $c['Status'] !== CLAN_AKTIVAN) {
        return null;
    }
    $foto = isk_foto_data($c['FotoDatoteka'], 120);
    return [
        'first' => (string) $c['Ime'],
        'last' => (string) $c['Prezime'],
        'section' => (string) ($c['SekcijaNaziv'] ?? ''),
        'photo' => $foto && str_starts_with($foto, 'data:image/jpeg;base64,') && strlen($foto) < 45000 ? $foto : '',
    ];
}

/**
 * Otvara adresu s podacima u #fragmentu preko male HTML stranice (ne Location zaglavljem –
 * token s fotografijom je predug za zaglavlje nekih proxyja).
 */
function pop_otvori(string $adresa, array $fragment): never
{
    $cilj = $adresa . '#' . http_build_query($fragment);
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Popusti</title>'
        . '<p style="font-family:system-ui;text-align:center;margin-top:3rem">Otvaram Popuste…</p>'
        . '<script>location.replace(' . json_encode($cilj, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ');</script>';
    exit;
}
