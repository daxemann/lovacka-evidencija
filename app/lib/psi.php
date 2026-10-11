<?php
/**
 * Lovački psi članova. Za svakog aktivnog psa (uz uključeno bodovanje) 1. siječnja automatski nastaje
 * radna akcija „Lovački pas“ na čekanju – odobrava se kao i sve ostale (standardni bodovi iz Vrste radnih akcija).
 */
declare(strict_types=1);

/** Vrste radnih akcija za pse (bodovna lista; bodovi se mijenjaju u Sustav → Vrste radnih akcija). */
const VRSTE_PSI = [
    'godisnje' => ['Lovački pas', 0],               // posjedovanje psa – automatski 1.1.
    'lov' => ['Pas na lovu', 0],                    // prijavljen pas na zajedničkom lovu – automatski nakon lova
    'potraga' => ['Potraga s psom (krvni trag)', 1], // član sam prijavljuje (Moje radne akcije)
];
function vrsta_akcije_psi(string $kljuc = 'godisnje'): int
{
    [$naziv, $aktivna] = VRSTE_PSI[$kljuc];
    $pk = 'Psi.Vrsta.' . $kljuc;
    $id = (int) postavka($pk, $kljuc === 'godisnje' ? postavka('Psi.VrstaAkcije', '0') : '0');
    if ($id && vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=?', [$id])) {
        return $id;
    }
    $id = (int) (vrijednost('SELECT Id FROM VrsteAkcija WHERE Naziv=? LIMIT 1', [$naziv]) ?: 0);
    if (!$id) {
        $id = umetni('VrsteAkcija', ['Naziv' => $naziv, 'StandardniBodovi' => '0.0', 'Aktivna' => $aktivna]);
    }
    spremi_postavku($pk, (string) $id);
    return $id;
}
/** [kljuc => ['naziv','bodovi','id']] */
function bodovna_lista_psi(): array
{
    $r = [];
    foreach (VRSTE_PSI as $kl => $x) {
        $id = vrsta_akcije_psi($kl);
        $v = red('SELECT Naziv, StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$id]);
        $r[$kl] = ['id' => $id, 'naziv' => $v['Naziv'] ?? $x[0], 'bodovi' => (float) ($v['StandardniBodovi'] ?? 0)];
    }
    return $r;
}
function bodovi_psa_standard(): float
{
    return bodovna_lista_psi()['godisnje']['bodovi'];
}
/** Aktivni psi člana [Id => opis] */
function aktivni_psi(int $clanId): array
{
    $r = [];
    foreach (redovi('SELECT * FROM LovniPsi WHERE ClanId=? AND Aktivan=1 ORDER BY Ime', [$clanId]) as $p) {
        $r[(int) $p['Id']] = $p;
    }
    return $r;
}
/** "1,3" → [1,3] */
function psi_ids(?string $s): array
{
    return array_values(array_filter(array_map('intval', explode(',', (string) $s))));
}

function psi_clana(int $clanId): array
{
    $psi = redovi('SELECT * FROM LovniPsi WHERE ClanId=? ORDER BY Aktivan DESC, Ime', [$clanId]);
    foreach ($psi as &$p) {
        $p['Ispiti'] = redovi('SELECT * FROM PsiIspiti WHERE PasId=? ORDER BY Datum DESC, Id DESC', [$p['Id']]);
        $p['Bodovano'] = array_column(redovi('SELECT Godina, AkcijaId FROM PsiBodovi WHERE PasId=? ORDER BY Godina DESC', [$p['Id']]), 'AkcijaId', 'Godina');
        $p['Akcije'] = redovi('SELECT a.*, v.Naziv AS VrstaNaziv FROM RadneAkcije a LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE a.PasId=? ORDER BY a.Datum DESC, a.Id DESC LIMIT 30', [$p['Id']]);
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
                'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(), 'PasId' => (int) $p['Id'],
            ]);
            umetni('PsiBodovi', ['PasId' => (int) $p['Id'], 'Godina' => $godina, 'AkcijaId' => $aid]);
            $n++;
        }
    });
    return $n;
}

/**
 * Zajednički lov sa psima: za prošle termine (vrsta „lov sa psima“) svakom prijavljenom psu radna akcija „Pas na lovu“ na čekanju.
 * Vraća broj novih upisa.
 */
function psi_bodovi_lova(): int
{
    $vrste = array_column(array_filter(vrste_dogadjaja(), fn($v) => !empty($v['psi'])), 'naziv');
    if (!$vrste) {
        return 0;
    }
    $dog = redovi('SELECT * FROM Dogadjaji WHERE PsiBodovano IS NULL AND substr(COALESCE(Kraj, Pocetak),1,10) < ? AND Vrsta IN (' . implode(',', array_fill(0, count($vrste), '?')) . ')',
        array_merge([danas()], $vrste));
    $vrstaId = vrsta_akcije_psi('lov');
    $n = 0;
    foreach ($dog as $d) {
        transakcija(function () use ($d, $vrstaId, &$n) {
            $datum = substr($d['Pocetak'], 0, 10);
            foreach (redovi('SELECT o.ClanId, o.PsiIds FROM DogadjajOdgovori o JOIN Clanovi c ON c.Id=o.ClanId WHERE o.DogadjajId=? AND o.Dolazi=1 AND o.PsiIds IS NOT NULL', [$d['Id']]) as $o) {
                foreach (psi_ids($o['PsiIds']) as $pid) {
                    $p = red('SELECT * FROM LovniPsi WHERE Id=? AND ClanId=?', [$pid, $o['ClanId']]);
                    if (!$p || vrijednost('SELECT 1 FROM RadneAkcije WHERE PasId=? AND VrstaAkcijeId=? AND substr(Datum,1,10)=?', [$pid, $vrstaId, $datum])) {
                        continue;
                    }
                    umetni('RadneAkcije', [
                        'ClanId' => (int) $o['ClanId'], 'Datum' => $datum, 'VrstaAkcijeId' => $vrstaId, 'VrstaSlobodno' => null, 'Sati' => null,
                        'Opis' => '🐕 ' . opis_psa($p) . ' – ' . $d['Naslov'], 'Status' => AKCIJA_CEKA, 'Bodovi' => null, 'RazlogOdbijanja' => null,
                        'FotoDatoteka' => null, 'UnioKorisnikId' => null, 'Kreirano' => sada(), 'PasId' => $pid,
                    ]);
                    $n++;
                }
            }
            q('UPDATE Dogadjaji SET PsiBodovano=? WHERE Id=?', [sada(), $d['Id']]);
        });
    }
    return $n;
}

/** Jednom dnevno: psi na prošlim zajedničkim lovovima + (pri prvom pozivu u godini) godišnji bodovi od 1. siječnja. */
function psi_godisnji_bodovi(): void
{
    $oznaka = podaci('.psi-dan');
    if (is_file($oznaka) && file_get_contents($oznaka) === danas()) {
        return;
    }
    @file_put_contents($oznaka, danas());
    try {
        bodovna_lista_psi(); // vrste radnih akcija za pse postoje u bodovnoj listi
        $n = psi_bodovi_lova();
        if ($n) {
            dnevnik('Bodovi za pse na zajedničkom lovu', null, null, "$n upisa na čekanju");
        }
    } catch (Throwable $e) {
        error_log('Psi na lovu: ' . $e->getMessage());
    }
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
