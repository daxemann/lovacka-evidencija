<?php
/** Članarina: pravila i izračuni. */
declare(strict_types=1);

/** Članovi oslobođeni članarine u godini (funkcija s OslobodjenClanarine aktivna te godine). @return array<int,true> */
function oslobodjeni_clanarine(int $godina): array
{
    $poc = "$godina-01-01";
    $kraj = "$godina-12-31";
    $r = [];
    foreach (redovi('SELECT cf.ClanId FROM ClanFunkcije cf JOIN Funkcije f ON f.Id=cf.FunkcijaId
                     WHERE f.OslobodjenClanarine=1 AND (cf.Od IS NULL OR cf.Od<=?) AND (cf.Do IS NULL OR substr(cf.Do,1,10)>=?)', [$kraj, $poc]) as $x) {
        $r[(int) $x['ClanId']] = true;
    }
    return $r;
}

/** Članarina člana za godinu s uplatama: [Id, Iznos, Uplaceno, Preostalo, Uplate[]] ili null. */
function clanarina_clana(int $clanId, int $godina): ?array
{
    $c = red('SELECT * FROM Clanarine WHERE ClanId=? AND Godina=?', [$clanId, $godina]);
    if (!$c) {
        return null;
    }
    $c['Uplate'] = redovi('SELECT * FROM Uplate WHERE ClanarinaId=? ORDER BY Datum', [$c['Id']]);
    $c['Uplaceno'] = array_sum(array_map(fn($u) => (float) $u['Iznos'], $c['Uplate']));
    $c['Preostalo'] = (float) $c['Iznos'] - $c['Uplaceno'];
    return $c;
}
