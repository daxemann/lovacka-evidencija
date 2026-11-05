<?php
/**
 * Odstrel: upis odstrijeljene divljači (broj markice se upisuje), plan odstrela po sekciji i lovnoj godini (1.4.–31.3.).
 * Evidencija je po sekcijama, zajednička ili sa spojenim sekcijama (Sustav → Odstrel); ključ evidencije = vodeća sekcija (0 = cijela udruga).
 * Vidi se i upisuje samo za evidencije u opsegu prava (Odstrel – pregled / upis); pravo za jednu sekciju evidencije vrijedi za cijelu evidenciju.
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

/** Napomena na stranicama i PDF-ovima odstrela: interna evidencija, ne zamjenjuje SLE ni propisane obrasce. */
const ODSTREL_SLE_NAPOMENA = 'Ova evidencija služi isključivo za interne potrebe udruge i ne zamjenjuje Središnju lovnu evidenciju (SLE) Ministarstva poljoprivrede ni propisane obrasce (dopuštenje za lov, zapisnik o obavljenom lovu, potvrda o podrijetlu divljači, evidencijske markice).';

/** Dodatna polja upisa za propisane obrasce – svaka udruga uključuje ona koja koristi (Sustav → Odstrel). */
const ODSTREL_POLJA = [
    'dopustenje' => 'Broj dopuštenja za lov',
    'zapisnik' => 'Broj zapisnika o obavljenom lovu',
    'potvrda' => 'Broj potvrde o podrijetlu divljači',
    'pregled' => 'Prvi pregled divljači (tko je pregledao, nalaz)',
];
const ODSTREL_VODJENJE = [
    'sekcije' => 'Po sekcijama – svaka sekcija vodi svoju evidenciju (markice, plan, PDF)',
    'zajednicki' => 'Zajednički – jedna evidencija za cijelu udrugu',
    'mjesovito' => 'Mješovito – sekcije po izboru spojene (npr. dvije sekcije s istim lovnikom)',
];

/** Način vođenja evidencije: sekcije | zajednicki | mjesovito. */
function odstrel_vodjenje(): string
{
    $v = (string) postavka('Odstrel.Vodjenje', 'sekcije');
    return isset(ODSTREL_VODJENJE[$v]) ? $v : 'sekcije';
}
/** Uključena dodatna polja (ključevi iz ODSTREL_POLJA). */
function odstrel_polja(): array
{
    return array_values(array_intersect(array_keys(ODSTREL_POLJA), explode(',', (string) postavka('Odstrel.Polja', ''))));
}
/** Udruga koristi SLE: oznaka „upisano u SLE“ i upozorenje za rok od 7 dana. */
function odstrel_sle(): bool
{
    return postavka('Odstrel.Sle') === '1';
}
/** Aktivne sekcije [id => naziv]. */
function odstrel_sve_sekcije(): array
{
    static $s = null;
    if ($s === null) {
        $s = [];
        foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $r) {
            $s[(int) $r['Id']] = $r['Naziv'];
        }
    }
    return $s;
}
/** Mješovito: [sekcija => sekcija koja vodi evidenciju]; vodeća sekcija ne može i sama biti pridružena. */
function odstrel_spajanja(): array
{
    if (odstrel_vodjenje() !== 'mjesovito') {
        return [];
    }
    $sve = odstrel_sve_sekcije();
    $m = [];
    foreach ((array) json_decode((string) postavka('Odstrel.Spajanja', ''), true) as $sid => $vod) {
        $sid = (int) $sid;
        $vod = (int) $vod;
        if ($sid !== $vod && isset($sve[$sid], $sve[$vod])) {
            $m[$sid] = $vod;
        }
    }
    foreach ($m as $sid => $vod) {
        if (isset($m[$vod])) {
            unset($m[$sid]);
        }
    }
    return $m;
}
/** Ključ evidencije (grupe) za sekciju upisa: 0 = cijela udruga / udruga bez sekcija, inače id vodeće sekcije. */
function odstrel_grupa(?int $sid): int
{
    if (!odstrel_sve_sekcije() || odstrel_vodjenje() === 'zajednicki' || !$sid) {
        return 0;
    }
    return odstrel_spajanja()[$sid] ?? $sid;
}
/** Sve evidencije [ključ => naziv], npr. „Bušetina + Špišić Bukovica“. */
function odstrel_grupe(): array
{
    $sve = odstrel_sve_sekcije();
    if (!$sve) {
        return [0 => udruga_kratko()];
    }
    if (odstrel_vodjenje() === 'zajednicki') {
        return [0 => udruga_kratko() . ' (sve sekcije)'];
    }
    $g = [];
    foreach ($sve as $sid => $n) {
        $g[odstrel_grupa($sid)][] = $n;
    }
    $out = [];
    foreach ($sve as $sid => $n) {
        if (isset($g[$sid])) {
            $out[$sid] = implode(' + ', $g[$sid]);
        }
    }
    return $out;
}
/** Sekcije (stvarne) koje pripadaju evidenciji; 0 = upisi bez sekcije. */
function odstrel_clanice(int $kljuc): array
{
    $sve = odstrel_sve_sekcije();
    if (!$sve) {
        return [0];
    }
    if (odstrel_vodjenje() === 'zajednicki') {
        return array_merge(array_keys($sve), [0]);
    }
    return array_values(array_filter(array_keys($sve), fn($sid) => odstrel_grupa($sid) === $kljuc));
}
/**
 * Evidencije koje korisnik smije vidjeti / uređivati: [ključ => naziv].
 * Pravo za jednu sekciju evidencije vrijedi za cijelu evidenciju (spojene sekcije, zajednička evidencija).
 */
function odstrel_grupe_za(array $prava): array
{
    $o = opseg_prava_ili($prava);
    $grupe = odstrel_grupe();
    if (!odstrel_sve_sekcije()) {
        return $o['sve'] || $o['s'] ? $grupe : [];
    }
    if ($o['sve']) {
        return $grupe;
    }
    $k = [];
    foreach ($o['s'] as $sid) {
        $k[odstrel_grupa((int) $sid)] = true;
    }
    return array_intersect_key($grupe, $k);
}
function odstrel_sekcije(bool $uredjivanje = false): array
{
    return odstrel_grupe_za($uredjivanje ? [P_ODSTREL] : [P_ODSTREL, P_ODSTREL_CITAJ]);
}
/** Stvarne sekcije za upis (sekcija lovca) unutar evidencija koje korisnik smije uređivati: [id => naziv]. */
function odstrel_upis_sekcije(): array
{
    $sve = odstrel_sve_sekcije();
    $grupe = odstrel_sekcije(true);
    if (!$sve) {
        return $grupe;
    }
    $out = [];
    foreach ($sve as $sid => $n) {
        if (array_key_exists(odstrel_grupa($sid), $grupe)) {
            $out[$sid] = $n;
        }
    }
    return $out;
}
/** Naziv stvarne sekcije upisa. */
function odstrel_sekcija_naziv(?int $sid): string
{
    return $sid ? (odstrel_sve_sekcije()[$sid] ?? (string) vrijednost('SELECT Naziv FROM Sekcije WHERE Id=?', [$sid])) : '';
}
function odstrel_sid(?int $sid): int
{
    return $sid ?? 0;
}
/** Smije li korisnik uređivati upis s ovom (stvarnom) sekcijom. */
function odstrel_moze_urediti(?int $sid): bool
{
    return array_key_exists(odstrel_grupa($sid), odstrel_sekcije(true));
}
/** SQL uvjet za evidencije [ključ => …] – proširuje na sve sekcije evidencije (0 = SekcijaId IS NULL). */
function odstrel_sek_sql(array $grupe, string $stupac = 'SekcijaId'): string
{
    $ids = [];
    $null = false;
    foreach (array_keys($grupe) as $kl) {
        foreach (odstrel_clanice((int) $kl) as $sid) {
            $sid ? $ids[$sid] = true : $null = true;
        }
    }
    $uv = [];
    if ($ids) {
        $uv[] = $stupac . ' IN (' . implode(',', array_map('intval', array_keys($ids))) . ')';
    }
    if ($null) {
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
    if (array_key_exists(odstrel_grupa($o['SekcijaId'] !== null ? (int) $o['SekcijaId'] : null), odstrel_sekcije())) {
        return true;
    }
    return $k['ClanId'] && (int) $o['ClanId'] === (int) $k['ClanId'];
}

/**
 * Upozorenja za korisnika s pravom „Odstrel – upozorenja“ (za evidencije u opsegu):
 * odstrel nije označen kao upisan u SLE (rok 7 dana) te od 15.4. do 31.5. povrat markica i izvršenje plana.
 * @return array<array{0:string,1:string,2:string,3:string}> [boja, tekst (HTML), poveznica, gumb]
 */
function odstrel_upozorenja(): array
{
    if (!modul('odstrel') || !korisnik()) {
        return [];
    }
    $grupe = odstrel_grupe_za([P_ODSTREL_UPOZ]);
    if (!$grupe) {
        return [];
    }
    $out = [];
    $kome = count(odstrel_grupe()) > 1 ? ' <span class="small text-muted">(' . e(implode(', ', $grupe)) . ')</span>' : '';
    if (odstrel_sle()) {
        $od = (string) postavka('Odstrel.SleOd', '2000-01-01');
        $uv = odstrel_sek_sql($grupe) . ' AND SleDatum IS NULL AND Datum>=?';
        $n = (int) vrijednost("SELECT COUNT(*) FROM Odstrel WHERE $uv", [$od]);
        if ($n) {
            $kasni = (int) vrijednost("SELECT COUNT(*) FROM Odstrel WHERE $uv AND Datum<?", [$od, date('Y-m-d', strtotime('-7 days'))]);
            $m = $n % 10 === 1 && $n % 100 !== 11 ? 'odstrel nije označen kao upisan' : (in_array($n % 10, [2, 3, 4], true) && !in_array($n % 100, [12, 13, 14], true) ? 'odstrela nisu označena kao upisana' : 'odstrela nije označeno kao upisano');
            $out[] = [$kasni ? 'danger' : 'warning', '🦌 <b>' . $n . '</b> ' . $m . ' u SLE'
                . ($kasni ? ' – od toga <b>' . $kasni . '</b> prekoračilo rok od 7 dana' : ' (rok 7 dana od odstrela)') . '.' . $kome, url('odstrel', ['sle' => 0]), 'Pregledaj'];
        }
    }
    $md = date('m-d');
    if ($md >= '04-15' && $md <= '05-31') {
        $pr = odstrel_lovna_godina() - 1;
        $out[] = ['info', '🦌 Do <b>31. svibnja</b>: neiskorištene markice za lovnu godinu ' . e(odstrel_lg_naziv($pr)) . ' vratiti Ministarstvu (obrazac PPD-3)'
            . (odstrel_sle() ? ' i upisati izvršenje plana odstrela u SLE' : '') . '.' . $kome, url('odstrel/plan', ['g' => $pr]), 'Plan ' . odstrel_lg_naziv($pr)];
    }
    return $out;
}
/** Upozorenja kao HTML (alert redovi). */
function odstrel_upozorenja_html(): string
{
    $o = '';
    foreach (odstrel_upozorenja() as [$boja, $tekst, $link, $gumb]) {
        $o .= '<div class="alert alert-' . $boja . ' d-flex flex-wrap align-items-center gap-2 py-2"><span class="me-auto">' . $tekst . '</span>'
            . '<a href="' . e($link) . '" class="btn btn-sm btn-' . ($boja === 'info' ? 'outline-primary' : $boja) . '">' . e($gumb) . '</a></div>';
    }
    return $o;
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
 * Plan i ostvarenje: [['vrsta','kategorija','plan','ostvareno','id'], …] za evidenciju (ključ: vodeća sekcija / 0) i godinu.
 * Ostvareno za kategoriju broji upise iste vrste s tim spolom/kategorijom; prazna kategorija = sva grla vrste.
 * Vrste koje su odstrijeljene, a nisu u planu, dolaze s planom null.
 */
function odstrel_plan(int $sid, int $godina): array
{
    $sekSql = $sid ? 'SekcijaId=' . $sid : 'SekcijaId IS NULL';
    $plan = redovi("SELECT * FROM OdstrelPlan WHERE $sekSql AND LovnaGodina=? ORDER BY Id", [$godina]);
    $ost = [];
    $uvOdstrel = odstrel_sek_sql([$sid => true]);
    foreach (redovi("SELECT Vrsta, COALESCE(Spol,'') AS Spol, COUNT(*) AS N FROM Odstrel WHERE $uvOdstrel AND LovnaGodina=? GROUP BY Vrsta, COALESCE(Spol,'')", [$godina]) as $r) {
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
