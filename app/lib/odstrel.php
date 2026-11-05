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
/** Krupna divljač – zadano s evidencijskom markicom. */
const ODSTREL_KRUPNA = ['Srna obična', 'Jelen obični', 'Jelen lopatar', 'Divlja svinja', 'Muflon', 'Divokoza', 'Smeđi medvjed'];

/**
 * Vrste divljači udruge (Sustav → Odstrel – evidencija): [['naziv', 'kategorije' => [], 'markica' => bool, 'aktivna' => bool], …].
 * Zadano popis ODSTREL_VRSTE; svaka udruga dodaje svoje (druga područja, druge vrste) ili skriva one kojih nema.
 */
function odstrel_vrste_popis(): array
{
    $j = json_decode((string) postavka('Odstrel.Vrste', ''), true);
    if (is_array($j) && $j) {
        $out = [];
        foreach ($j as $v) {
            $n = trim((string) ($v['naziv'] ?? ''));
            if ($n !== '') {
                $out[] = ['naziv' => $n, 'kategorije' => array_values(array_filter(array_map('trim', (array) ($v['kategorije'] ?? [])), 'strlen')),
                    'markica' => !empty($v['markica']), 'aktivna' => !isset($v['aktivna']) || !empty($v['aktivna'])];
            }
        }
        return $out;
    }
    $out = [];
    foreach (ODSTREL_VRSTE as $n => $k) {
        $out[] = ['naziv' => $n, 'kategorije' => $k, 'markica' => in_array($n, ODSTREL_KRUPNA, true), 'aktivna' => true];
    }
    return $out;
}
/** Aktivne vrste: [naziv => kategorije] (za upis, plan, redoslijed). */
function odstrel_vrste(): array
{
    $o = [];
    foreach (odstrel_vrste_popis() as $v) {
        if ($v['aktivna']) {
            $o[$v['naziv']] = $v['kategorije'];
        }
    }
    return $o;
}
/** Vrste s evidencijskom markicom (aktivne). */
function odstrel_vrste_markica(): array
{
    return array_values(array_map(fn($v) => $v['naziv'], array_filter(odstrel_vrste_popis(), fn($v) => $v['aktivna'] && $v['markica'])));
}

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
        $slob = 0;
        if (odstrel_markice_ukljucene()) {
            foreach (array_keys($grupe) as $kl) {
                $slob += (int) vrijednost('SELECT COUNT(*) FROM OdstrelMarkice WHERE ' . odstrel_grupa_sql((int) $kl) . ' AND LovnaGodina=? AND Status=?', [$pr, MARKICA_SLOBODNA]);
            }
        }
        $out[] = [$slob ? 'warning' : 'info', '🦌 Do <b>31. svibnja</b>: neiskorištene markice za lovnu godinu ' . e(odstrel_lg_naziv($pr))
            . ($slob ? ' (<b>' . $slob . '</b> još nije označeno kao vraćeno)' : '') . ' vratiti Ministarstvu (obrazac PPD-3)'
            . (odstrel_sle() ? ' i upisati izvršenje plana odstrela u SLE' : '') . '.' . $kome, odstrel_markice_ukljucene() ? url('odstrel/markice', ['g' => $pr]) : url('odstrel/plan', ['g' => $pr]), odstrel_markice_ukljucene() ? 'Markice ' . odstrel_lg_naziv($pr) : 'Plan ' . odstrel_lg_naziv($pr)];
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
    $red = array_flip(array_keys(odstrel_vrste()));
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


// ---------- Markice (zaliha evidencijskih markica po evidenciji i lovnoj godini) ----------
const MARKICA_SLOBODNA = 0, MARKICA_ISKORISTENA = 1, MARKICA_VRACENA = 2;
const MARKICA_STATUSI = [0 => 'slobodna', 1 => 'iskorištena', 2 => 'vraćena'];

/** Uključena zaliha markica (Sustav → Odstrel – evidencija). */
function odstrel_markice_ukljucene(): bool
{
    return postavka('Odstrel.Markice') === '1';
}
/** Evidencije u kojima korisnik smije upisivati zalihu, zaduživati i vraćati markice. */
function odstrel_markice_uredive(): array
{
    return odstrel_grupe_za([P_ODSTREL_MARKICE]);
}
/** SQL za stupac Grupa (ključ evidencije; 0 = NULL). */
function odstrel_grupa_sql(int $kljuc): string
{
    return $kljuc ? 'Grupa=' . $kljuc : 'Grupa IS NULL';
}
/**
 * Brojevi markica iz raspona „od“ – „do“ (isti prefiks, zadnji niz znamenki se broji, vodeće nule ostaju):
 * HR-SR-000101 … HR-SR-000150 → 50 brojeva. Ili jedan broj ako je „do“ prazno. @return string[]|string greška
 */
function odstrel_markice_raspon(string $od, string $do): array|string
{
    $od = trim($od);
    $do = trim($do);
    if ($od === '') {
        return 'Upišite broj markice (od).';
    }
    if ($do === '' || $do === $od) {
        return [$od];
    }
    if (!preg_match('/^(.*?)(\d+)$/', $od, $a) || !preg_match('/^(.*?)(\d+)$/', $do, $b)) {
        return 'Raspon: broj mora završavati znamenkama (npr. 000101 – 000150).';
    }
    // „do“ smije biti i samo kraj broja (npr. od HR-SR-000101 do 150)
    if ($b[1] === '' && $a[1] !== '') {
        $b[1] = $a[1];
    }
    if ($a[1] !== $b[1]) {
        return 'Raspon: početak i kraj moraju imati isti prefiks (' . $a[1] . ' ≠ ' . $b[1] . ').';
    }
    $x = (int) $a[2];
    $y = (int) $b[2];
    if ($y < $x) {
        return 'Raspon: „do“ je manji od „od“.';
    }
    if ($y - $x >= 2000) {
        return 'Raspon je prevelik (najviše 2000 markica odjednom).';
    }
    $duz = max(strlen($a[2]), strlen($b[2]));
    $out = [];
    for ($i = $x; $i <= $y; $i++) {
        $out[] = $a[1] . str_pad((string) $i, $duz, '0', STR_PAD_LEFT);
    }
    return $out;
}
/** Markica iz zalihe u evidenciji i lovnoj godini (po broju). */
function odstrel_markica_red(int $grupa, int $godina, string $broj): ?array
{
    return red('SELECT * FROM OdstrelMarkice WHERE ' . odstrel_grupa_sql($grupa) . ' AND LovnaGodina=? AND Broj=?', [$godina, $broj]);
}
/**
 * Nakon spremanja / brisanja odstrela: markica iz zalihe → iskorištena (i povezana s upisom), prethodna → opet slobodna.
 * @return string|null upozorenje (markica nije u zalihi, već iskorištena, druga vrsta …)
 */
function odstrel_markica_povezi(?int $odstrelId, ?string $stara, ?array $novi): ?string
{
    if (!odstrel_markice_ukljucene()) {
        return null;
    }
    if ($odstrelId) {
        q('UPDATE OdstrelMarkice SET Status=?, OdstrelId=NULL WHERE OdstrelId=? AND Status=?', [MARKICA_SLOBODNA, $odstrelId, MARKICA_ISKORISTENA]);
    }
    if (!$novi || !$novi['Markica']) {
        return null;
    }
    $grupa = odstrel_grupa($novi['SekcijaId'] !== null ? (int) $novi['SekcijaId'] : null);
    $m = odstrel_markica_red($grupa, (int) $novi['LovnaGodina'], (string) $novi['Markica']);
    if (!$m) {
        $ima = (bool) vrijednost('SELECT 1 FROM OdstrelMarkice WHERE ' . odstrel_grupa_sql($grupa) . ' AND LovnaGodina=? AND Vrsta=? LIMIT 1', [(int) $novi['LovnaGodina'], $novi['Vrsta']]);
        return $ima ? 'Markica <b>' . e($novi['Markica']) . '</b> nije u zalihi markica ove evidencije za ' . e(odstrel_lg_naziv((int) $novi['LovnaGodina'])) . ' – provjerite broj.' : null;
    }
    if ((int) $m['Status'] === MARKICA_ISKORISTENA && (int) $m['OdstrelId'] !== $odstrelId) {
        return 'Markica <b>' . e($novi['Markica']) . '</b> je već iskorištena za drugi odstrel – provjerite.';
    }
    if ((int) $m['Status'] === MARKICA_VRACENA) {
        return 'Markica <b>' . e($novi['Markica']) . '</b> je označena kao vraćena Ministarstvu – provjerite.';
    }
    q('UPDATE OdstrelMarkice SET Status=?, OdstrelId=? WHERE Id=?', [MARKICA_ISKORISTENA, $odstrelId, $m['Id']]);
    return $m['Vrsta'] !== $novi['Vrsta'] ? 'Markica <b>' . e($novi['Markica']) . '</b> je u zalihi za vrstu <b>' . e($m['Vrsta']) . '</b>, a upisano je ' . e($novi['Vrsta']) . '.' : null;
}
/** Slobodne markice za upis: [ključ evidencije => [vrsta => [brojevi]]] za lovnu godinu. */
function odstrel_slobodne_markice(array $grupe, int $godina): array
{
    $out = [];
    if (!odstrel_markice_ukljucene()) {
        return $out;
    }
    foreach (array_keys($grupe) as $kl) {
        foreach (redovi('SELECT Vrsta, Broj, ZaduzenIme FROM OdstrelMarkice WHERE ' . odstrel_grupa_sql((int) $kl) . ' AND LovnaGodina=? AND Status=? ORDER BY Vrsta, Broj', [$godina, MARKICA_SLOBODNA]) as $r) {
            $out[(int) $kl][$r['Vrsta']][] = [$r['Broj'], (string) $r['ZaduzenIme']];
        }
    }
    return $out;
}
