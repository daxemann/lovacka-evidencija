<?php
/**
 * Godišnji bodovi za obveze (npr. držanje lovačkog psa uz ugovor s udrugom, hranilište, briga o čeki …).
 * Admin u Sustav → Bodovi određuje obvezu, bodove i dan; ovlaštena osoba (pravo uređivanja članova) uključuje /
 * isključuje članove u Radne akcije → Godišnje obveze. Jednom godišnje na zadani dan svaki uključeni član dobiva
 * radnu akciju – odmah odobreno. Uključen nakon tog dana → bodovi odmah. Svaki upis najviše jednom godišnje.
 * Lovački psi su posebna, ugrađena obveza (lib/psi.php, upis po psu).
 */
declare(strict_types=1);

/** Oznaka vrste u radnim akcijama (VrstaSlobodno) – ne nudi se za ručni upis. */
function obveza_oznaka(string $naziv): string
{
    return '📅 ' . $naziv;
}

/** Smije li trenutni korisnik uključivati / isključivati članove u obvezama. */
function smije_obveze(): bool
{
    return ima(P_CLANOVI_UREDI);
}

/** Datum upisa obveze u godini $g ('Y-m-d'). */
function obveza_dan(array $o, int $g): string
{
    $d = preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', (string) $o['Dan']) ? $o['Dan'] : '01-01';
    return $g . '-' . $d;
}

/**
 * Upisuje bodove obveze za godinu $g (kad je dan prošao) svim uključenim članovima koji ih još nemaju. Odmah odobreno.
 * $clanObvezaId = samo za jedan upis (npr. odmah nakon uključivanja). Vraća broj novih upisa.
 */
function upisi_bodove_obveze(array $o, int $g, ?array $k = null, ?int $clanObvezaId = null): int
{
    $dan = obveza_dan($o, $g);
    $bod = (float) $o['Bodovi'];
    if (!$o['Aktivna'] || $bod <= 0 || $dan > danas()) {
        return 0;
    }
    $uvjet = $clanObvezaId ? ' AND oc.Id=' . (int) $clanObvezaId : '';
    $lista = redovi("SELECT oc.* FROM ObvezaClanovi oc JOIN Clanovi c ON c.Id=oc.ClanId
        WHERE oc.ObvezaId=? AND oc.Aktivan=1 AND c.Status=0 $uvjet
          AND NOT EXISTS (SELECT 1 FROM ObvezaBodovi b WHERE b.ObvezaClanId=oc.Id AND b.Godina=?) ORDER BY oc.Id", [(int) $o['Id'], $g]);
    $n = 0;
    transakcija(function () use ($lista, $o, $g, $dan, $bod, $k, &$n) {
        foreach ($lista as $oc) {
            $datum = min(max($dan, substr((string) ($oc['AktivnoOd'] ?? ''), 0, 10)), danas());
            $aid = umetni('RadneAkcije', [
                'ClanId' => (int) $oc['ClanId'], 'Datum' => $datum, 'VrstaAkcijeId' => null, 'VrstaSlobodno' => mb_substr(obveza_oznaka($o['Naziv']), 0, 100), 'Sati' => null,
                'Opis' => $o['Naziv'] . ($oc['Opis'] ? ': ' . $oc['Opis'] : '') . ' (' . $g . '.)', 'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec($bod),
                'RazlogOdbijanja' => null, 'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(),
                'OdlucioKorisnikId' => $k['Id'] ?? null, 'OdlucioIme' => $k['Naziv'] ?? 'automatski', 'Odluceno' => sada(),
            ]);
            umetni('ObvezaBodovi', ['ObvezaClanId' => (int) $oc['Id'], 'Godina' => $g, 'AkcijaId' => $aid]);
            $n++;
        }
    });
    if ($n) {
        dnevnik('Godišnji bodovi za obvezu', null, null, $o['Naziv'] . " $g.: $n × " . broj($bod, 2) . ' b. (odmah odobreno)');
    }
    return $n;
}

/** Sve aktivne obveze – za dnevni posao. */
function obveze_dnevno(): void
{
    $g = (int) date('Y');
    foreach (redovi("SELECT * FROM GodisnjeObveze WHERE Aktivna=1 AND CAST(Bodovi AS REAL) > 0") as $o) {
        try {
            upisi_bodove_obveze($o, $g);
        } catch (Throwable $e) {
            error_log('Bodovi za obvezu ' . $o['Naziv'] . ': ' . $e->getMessage());
        }
    }
}
