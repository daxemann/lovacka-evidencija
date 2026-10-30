<?php
/**
 * Odstrel: upis odstrijeljene divljači (broj markice se upisuje), plan odstrela po sekciji i lovnoj godini (1.4.–31.3.).
 * Sve je strogo po sekcijama: vidi se i upisuje samo za sekcije u opsegu prava (Odstrel – pregled / upis).
 * Član bez tih prava vidi samo svoj odstrel. Udruga bez sekcija: ključ 0 (SekcijaId NULL).
 */
declare(strict_types=1);

/** Vrsta => kategorije (spol / dob) za upis i plan. Prazno = bez kategorija. Ostale vrste mogu se upisati slobodno. */
const ODSTREL_VRSTE = [
    'Srna obična' => ['Srnjak', 'Srna', 'Lane'],
    'Jelen obični' => ['Jelen', 'Košuta', 'Tele'],
    'Jelen lopatar' => ['Jelen', 'Košuta', 'Tele'],
    'Divlja svinja' => ['Vepar', 'Krmača', 'Nazime', 'Prase'],
    'Muflon' => ['Ovan', 'Ovca', 'Janje'],
    'Divokoza' => ['Jarac', 'Koza', 'Kozlić'],
    'Smeđi medvjed' => ['Mužjak', 'Ženka'],
    'Zec obični' => [],
    'Fazan' => ['Pijetao', 'Kokoš'],
    'Trčka skvržulja' => [],
    'Prepelica' => [],
    'Šljuka bena' => [],
    'Divlja patka' => [],
    'Divlja guska' => [],
    'Golub grivnjaš' => [],
    'Lisica' => [],
    'Čagalj' => [],
    'Jazavac' => [],
    'Kuna' => [],
    'Vrana siva' => [],
    'Svraka' => [],
    'Šojka' => [],
];
const ODSTREL_DOBI = ['Mlado (0 god.)', 'Mlado (1 god.)', 'Srednjodobno', 'Zrelo', 'Staro'];

/** Lovna godina (godina početka) za datum 'Y-m-d': 2026-10-30 → 2026, 2027-02-01 → 2026. */
function odstrel_lovna_godina(?string $datum = null): int
{
    return (int) pocetak_lovne_godine(new DateTimeImmutable($datum ?: 'today'))->format('Y');
}
function odstrel_lg_naziv(int $g): string
{
    return $g . './' . substr((string) ($g + 1), -2) . '.';
}

/** Sekcije koje korisnik smije vidjeti / uređivati: [id => naziv] (0 = udruga bez sekcija). */
function odstrel_sekcije(bool $uredjivanje = false): array
{
    $sve = [];
    foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
        $sve[(int) $s['Id']] = $s['Naziv'];
    }
    $op = ['sve' => false, 's' => []];
    foreach ($uredjivanje ? [P_ODSTREL] : [P_ODSTREL, P_ODSTREL_CITAJ] as $pr) {
        $o = opseg_prava($pr);
        $op['sve'] = $op['sve'] || $o['sve'];
        $op['s'] = array_merge($op['s'], $o['s']);
    }
    if (!$sve) {
        return $op['sve'] || $op['s'] ? [0 => udruga_kratko()] : [];
    }
    if ($op['sve']) {
        return $sve;
    }
    return array_intersect_key($sve, array_flip(array_unique($op['s'])));
}
function odstrel_sid(?int $sid): int
{
    return $sid ?? 0;
}
function odstrel_moze_urediti(?int $sid): bool
{
    return array_key_exists(odstrel_sid($sid), odstrel_sekcije(true));
}
/** SQL uvjet za sekcije (ključ 0 = SekcijaId IS NULL). */
function odstrel_sek_sql(array $sekcije, string $stupac = 'SekcijaId'): string
{
    $uv = [];
    $ids = array_filter(array_keys($sekcije));
    if ($ids) {
        $uv[] = $stupac . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
    if (array_key_exists(0, $sekcije)) {
        $uv[] = $stupac . ' IS NULL';
    }
    return $uv ? '(' . implode(' OR ', $uv) . ')' : '1=0';
}
/** Smije li korisnik uopće otvoriti odstrel (pravo ili barem vlastiti upisi kao član). */
function odstrel_dostupno(): bool
{
    $k = korisnik();
    return $k && modul('odstrel') && (odstrel_sekcije() || $k['ClanId']);
}

function odstrel_red(int $id): ?array
{
    return red('SELECT * FROM Odstrel WHERE Id=?', [$id]);
}
/** Smije li korisnik vidjeti ovaj upis. */
function odstrel_vidljiv(array $o): bool
{
    $k = korisnik();
    if (array_key_exists(odstrel_sid($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null), odstrel_sekcije())) {
        return true;
    }
    return $k['ClanId'] && (int) $o['ClanId'] === (int) $k['ClanId'];
}

/** Lovne godine koje postoje u odstrelu / planu + tekuća, novije prve. */
function odstrel_godine(): array
{
    $g = array_map('intval', array_column(redovi('SELECT DISTINCT LovnaGodina FROM Odstrel UNION SELECT DISTINCT LovnaGodina FROM OdstrelPlan'), 'LovnaGodina'));
    $g[] = odstrel_lovna_godina();
    $g = array_unique($g);
    rsort($g);
    return $g;
}

/**
 * Plan i ostvarenje: [['vrsta','kategorija','plan','ostvareno','id'], …] za sekciju i godinu.
 * Ostvareno za kategoriju broji upise iste vrste s tim spolom/kategorijom; prazna kategorija = sva grla vrste.
 * Vrste koje su odstrijeljene, a nisu u planu, dolaze s planom null.
 */
function odstrel_plan(int $sid, int $godina): array
{
    $sekSql = $sid ? 'SekcijaId=' . $sid : 'SekcijaId IS NULL';
    $plan = redovi("SELECT * FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=? ORDER BY Id", [$godina]);
    $ost = [];
    foreach (redovi("SELECT Vrsta, COALESCE(Spol,'') AS Spol, COUNT(*) AS N FROM Odstrel WHERE $sekSql AND LovnaGodina=? GROUP BY Vrsta, COALESCE(Spol,'')", [$godina]) as $r) {
        $ost[$r['Vrsta']][$r['Spol']] = (int) $r['N'];
    }
    $out = [];
    $uPlanu = [];
    foreach ($plan as $p) {
        $kat = (string) $p['Kategorija'];
        $n = $kat === '' ? array_sum($ost[$p['Vrsta']] ?? []) : ($ost[$p['Vrsta']][$kat] ?? 0);
        $out[] = ['id' => (int) $p['Id'], 'vrsta' => $p['Vrsta'], 'kategorija' => $kat, 'plan' => (int) $p['Broj'], 'ostvareno' => $n];
        $uPlanu[$p['Vrsta']] = true;
    }
    foreach ($ost as $vrsta => $po) {
        if (!isset($uPlanu[$vrsta])) {
            $out[] = ['id' => 0, 'vrsta' => $vrsta, 'kategorija' => '', 'plan' => null, 'ostvareno' => array_sum($po)];
        }
    }
    $red = array_flip(array_keys(ODSTREL_VRSTE));
    usort($out, fn($a, $b) => [$red[$a['vrsta']] ?? 99, $a['vrsta'], $a['kategorija'] === '' ? 0 : 1, $a['kategorija']]
        <=> [$red[$b['vrsta']] ?? 99, $b['vrsta'], $b['kategorija'] === '' ? 0 : 1, $b['kategorija']]);
    return $out;
}

/** Članovi sekcije za odabir lovca (aktivni, prezime ime). */
function odstrel_clanovi(?int $sid): array
{
    $uv = $sid ? 'SekcijaId=' . (int) $sid : '1=1';
    return redovi("SELECT Id, Ime, Prezime FROM Clanovi WHERE Status=? AND $uv ORDER BY Prezime, Ime", [CLAN_AKTIVAN]);
}

/** Oznaka „Vepar · Zrelo“. */
function odstrel_opis(array $o): string
{
    return implode(' · ', array_filter([(string) $o['Spol'], (string) $o['Dob']]));
}
