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

// ---------- Planovi plaćanja (jednokratno / rate) ----------
/**
 * Planovi za godinu: [['id'=>'p1','naziv'=>'Jednokratno','zadano'=>true,'rate'=>[['iznos'=>320.0,'datum'=>'2026-03-31']]], …]
 * Spremaju se u Postavke (Clanarina.Planovi.<godina>).
 */
function planovi_clanarine(int $godina): array
{
    $j = postavka("Clanarina.Planovi.$godina");
    $p = $j ? json_decode($j, true) : null;
    return is_array($p) ? $p : [];
}
function spremi_planove_clanarine(int $godina, array $planovi): void
{
    spremi_postavku("Clanarina.Planovi.$godina", $planovi ? json_encode(array_values($planovi), JSON_UNESCAPED_UNICODE) : null);
}
function zadani_plan(int $godina): ?array
{
    $p = planovi_clanarine($godina);
    foreach ($p as $x) {
        if (!empty($x['zadano'])) {
            return $x;
        }
    }
    return $p[0] ?? null;
}
function plan_po_id(int $godina, string $id): ?array
{
    foreach (planovi_clanarine($godina) as $x) {
        if ($x['id'] === $id) {
            return $x;
        }
    }
    return null;
}
function iznos_plana(array $plan): float
{
    return round(array_sum(array_map(fn($r) => (float) $r['iznos'], $plan['rate'])), 2);
}
function opis_plana(array $plan): string
{
    $n = count($plan['rate']);
    return $plan['naziv'] . ' (' . ($n === 1 ? novac(iznos_plana($plan)) . ' €, do ' . datum($plan['rate'][0]['datum']) : "$n rate: " . implode(' + ', array_map(fn($r) => novac($r['iznos']), $plan['rate'])) . ' €') . ')';
}

/** Postavlja rate članarine prema planu (ili vlastitom popisu [['iznos','datum'],…]) i usklađuje ukupni iznos. */
function postavi_rate(int $clanarinaId, array $rate): void
{
    q('DELETE FROM ClanarinaRate WHERE ClanarinaId=?', [$clanarinaId]);
    $i = 0;
    $uk = 0.0;
    usort($rate, fn($a, $b) => strcmp((string) $a['datum'], (string) $b['datum']));
    foreach ($rate as $r) {
        $iz = round((float) $r['iznos'], 2);
        if ($iz <= 0 || !$r['datum']) {
            continue;
        }
        umetni('ClanarinaRate', ['ClanarinaId' => $clanarinaId, 'RedniBroj' => ++$i, 'Iznos' => dec($iz), 'Dospijece' => $r['datum']]);
        $uk += $iz;
    }
    if ($i > 0) {
        q('UPDATE Clanarine SET Iznos=? WHERE Id=?', [dec($uk), $clanarinaId]);
    }
}
/** Novo zaduženje člana za godinu prema planu. */
function zaduzi_clanarinu(int $clanId, int $godina, ?array $plan, ?float $iznos = null): int
{
    $iz = $plan ? iznos_plana($plan) : (float) $iznos;
    $id = umetni('Clanarine', ['ClanId' => $clanId, 'Godina' => $godina, 'Iznos' => dec($iz), 'Valuta' => 'EUR', 'Napomena' => null]);
    if ($plan) {
        postavi_rate($id, $plan['rate']);
    }
    return $id;
}

/**
 * Rate s raspodjelom uplata (redom, najstarija prva).
 * Svaka rata: RedniBroj, Iznos, Dospijece, Placeno, Preostalo, Status (placeno | djelomicno | dospjelo | nije_dospjelo)
 */
function rate_clanarine(array $cl): array
{
    $rate = redovi('SELECT * FROM ClanarinaRate WHERE ClanarinaId=? ORDER BY RedniBroj', [$cl['Id']]);
    $ostatak = (float) ($cl['Uplaceno'] ?? 0);
    $danas = danas();
    foreach ($rate as &$r) {
        $iz = (float) $r['Iznos'];
        $pl = min($iz, max(0, $ostatak));
        $ostatak -= $pl;
        $r['Placeno'] = $pl;
        $r['Preostalo'] = round($iz - $pl, 2);
        $r['Status'] = $r['Preostalo'] <= 0.004 ? 'placeno' : (substr($r['Dospijece'], 0, 10) < $danas ? 'dospjelo' : ($pl > 0 ? 'djelomicno' : 'nije_dospjelo'));
    }
    return $rate;
}
/** Dospjele neplaćene rate (nakon odgode u danima). */
function dospjele_rate(array $rate, ?int $odgoda = null): array
{
    $odgoda ??= (int) postavka('Clanarina.Odgoda', '7');
    $gr = date('Y-m-d', strtotime("-$odgoda days"));
    return array_values(array_filter($rate, fn($r) => $r['Preostalo'] > 0.004 && substr($r['Dospijece'], 0, 10) < $gr));
}
function oznaka_statusa_rate(string $s): string
{
    return match ($s) {
        'placeno' => '<span class="badge bg-success">plaćeno</span>',
        'djelomicno' => '<span class="badge bg-warning text-dark">djelomično</span>',
        'dospjelo' => '<span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">dospjelo</span>',
        default => '<span class="badge text-bg-light border">nije još dospjelo</span>',
    };
}
