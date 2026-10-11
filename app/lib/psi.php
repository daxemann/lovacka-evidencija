<?php
/**
 * Lovački psi članova. Za svakog aktivnog psa (uz uključeno bodovanje) 1. siječnja automatski nastaje
 * radna akcija „Lovački pas“ na čekanju – odobrava se kao i sve ostale (standardni bodovi iz Vrste radnih akcija).
 */
declare(strict_types=1);

/** Vrsta radne akcije za pse (stvara se po potrebi; neaktivna = članovi je ne biraju ručno). */
function vrsta_akcije_psi(): int
{
    $id = (int) postavka('Psi.VrstaAkcije', '0');
    if ($id && vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=?', [$id])) {
        return $id;
    }
    $id = (int) (vrijednost("SELECT Id FROM VrsteAkcija WHERE Naziv='Lovački pas' LIMIT 1") ?: 0);
    if (!$id) {
        $id = umetni('VrsteAkcija', ['Naziv' => 'Lovački pas', 'StandardniBodovi' => '0.0', 'Aktivna' => 0]);
    }
    spremi_postavku('Psi.VrstaAkcije', (string) $id);
    return $id;
}
function bodovi_psa_standard(): float
{
    return (float) (vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=?', [vrsta_akcije_psi()]) ?? 0);
}

function psi_clana(int $clanId): array
{
    $psi = redovi('SELECT * FROM LovniPsi WHERE ClanId=? ORDER BY Aktivan DESC, Ime', [$clanId]);
    foreach ($psi as &$p) {
        $p['Ispiti'] = redovi('SELECT * FROM PsiIspiti WHERE PasId=? ORDER BY Datum DESC, Id DESC', [$p['Id']]);
        $p['Bodovano'] = array_column(redovi('SELECT Godina, AkcijaId FROM PsiBodovi WHERE PasId=? ORDER BY Godina DESC', [$p['Id']]), 'AkcijaId', 'Godina');
    }
    return $psi;
}
function opis_psa(array $p): string
{
    return $p['Ime'] . ($p['Pasmina'] ? ' (' . $p['Pasmina'] . ')' : '');
}

/**
 * Upisuje godišnje bodove za pse (radna akcija na čekanju, datum 1.1.) – svaki pas najviše jednom godišnje.
 * $clanId = samo za jednog člana. Vraća broj novih upisa.
 */
function upisi_bodove_psa(int $godina, ?int $clanId = null, ?array $k = null): int
{
    $vrsta = vrsta_akcije_psi();
    $uvjet = $clanId ? ' AND p.ClanId=' . (int) $clanId : '';
    $psi = redovi("SELECT p.* FROM LovniPsi p JOIN Clanovi c ON c.Id=p.ClanId
        WHERE p.Aktivan=1 AND p.Bodovanje=1 AND c.Status=0 $uvjet
          AND NOT EXISTS (SELECT 1 FROM PsiBodovi b WHERE b.PasId=p.Id AND b.Godina=?)", [$godina]);
    $n = 0;
    transakcija(function () use ($psi, $godina, $vrsta, $k, &$n) {
        foreach ($psi as $p) {
            $aid = umetni('RadneAkcije', [
                'ClanId' => (int) $p['ClanId'], 'Datum' => "$godina-01-01", 'VrstaAkcijeId' => $vrsta, 'VrstaSlobodno' => null, 'Sati' => null,
                'Opis' => 'Lovački pas ' . $godina . '.: ' . opis_psa($p), 'Status' => AKCIJA_CEKA, 'Bodovi' => null, 'RazlogOdbijanja' => null,
                'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(),
            ]);
            umetni('PsiBodovi', ['PasId' => (int) $p['Id'], 'Godina' => $godina, 'AkcijaId' => $aid]);
            $n++;
        }
    });
    return $n;
}

/** Jednom dnevno (pri prvom pozivu u godini): automatski upis za tekuću godinu od 1. siječnja. */
function psi_godisnji_bodovi(): void
{
    $god = (int) date('Y');
    if (postavka('Psi.Bodovano') === (string) $god) {
        return;
    }
    try {
        $n = upisi_bodove_psa($god);
        spremi_postavku('Psi.Bodovano', (string) $god);
        if ($n) {
            dnevnik('Godišnji bodovi za lovačke pse', null, null, "$god: $n upisa na čekanju");
        }
    } catch (Throwable $e) {
        error_log('Bodovi za pse: ' . $e->getMessage());
    }
}
