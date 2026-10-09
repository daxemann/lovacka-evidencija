<?php
/**
 * Knjiga dezinfekcije (ASK – afrička svinjska kuga): dolasci i odlasci na dezinfekcijskim stanicama.
 * Član skenira QR oznaku stanice → provjera položaja (GPS) → upis (vrijeme poslužitelja).
 */
declare(strict_types=1);

const DEZ_SMJER = ['D' => 'Dolazak', 'O' => 'Odlazak'];
const DEZ_LOK_NAKNADNO = 0, DEZ_LOK_OK = 1, DEZ_LOK_NEPOUZDANO = 2, DEZ_LOK_BEZ = 3;
const DEZ_LOKACIJA = [
    DEZ_LOK_NAKNADNO => 'naknadni upis',
    DEZ_LOK_OK => 'u krugu stanice',
    DEZ_LOK_NEPOUZDANO => 'lokacija nepouzdana',
    DEZ_LOK_BEZ => 'bez lokacije',
];
const DEZ_MAX_SUPUTNIKA = 3;
const DEZ_MAX_GOSTIJU = 6;

// ---------- Stanice ----------
function dez_stanica(int $id): ?array
{
    return red('SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE st.Id=?', [$id]);
}
function dez_stanica_po_tokenu(string $t): ?array
{
    if (!preg_match('/^[a-z0-9]{6,40}$/', $t)) {
        return null;
    }
    return red('SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE st.Token=?', [$t]);
}
function dez_novi_token(): string
{
    return substr(bin2hex(random_bytes(8)), 0, 12);
}

/** Svaka aktivna sekcija dobiva svoju fiksnu stanicu (bez koordinata dok se ne upišu). */
function dez_osiguraj_stanice(): void
{
    foreach (sekcije(true) as $s) {
        if (!vrijednost("SELECT 1 FROM DezStanice WHERE Vrsta='F' AND SekcijaId=?", [$s['Id']])) {
            umetni('DezStanice', ['Naziv' => 'Dezinfekcija ' . $s['Naziv'], 'SekcijaId' => (int) $s['Id'], 'Vrsta' => 'F', 'Radijus' => 100,
                'Aktivna' => 1, 'Token' => dez_novi_token(), 'Kreirano' => sada()]);
        }
    }
    if (!sekcije(true) && !vrijednost("SELECT 1 FROM DezStanice WHERE Vrsta='F'")) {
        umetni('DezStanice', ['Naziv' => 'Dezinfekcijska stanica', 'SekcijaId' => null, 'Vrsta' => 'F', 'Radijus' => 100,
            'Aktivna' => 1, 'Token' => dez_novi_token(), 'Kreirano' => sada()]);
    }
}

/** Fiksne stanice (mobilne stanice dolaze kasnije, zasebno). $samoOpseg: samo sekcije trenutnog korisnika. */
function dez_stanice(bool $samoOpseg = true): array
{
    $r = redovi("SELECT st.*, s.Naziv AS SekcijaNaziv FROM DezStanice st LEFT JOIN Sekcije s ON s.Id=st.SekcijaId
        WHERE st.Vrsta='F' ORDER BY COALESCE(s.Redoslijed, 9999), s.Naziv, st.Naziv");
    return $samoOpseg ? array_values(array_filter($r, fn($st) => moze_sekciju($st['SekcijaId'] !== null ? (int) $st['SekcijaId'] : null))) : $r;
}

function dez_razlozi(bool $samoAktivni = true): array
{
    return redovi('SELECT * FROM DezRazlozi' . ($samoAktivni ? ' WHERE Aktivan=1' : '') . ' ORDER BY Redoslijed, Naziv');
}

// ---------- Položaj ----------
/** Udaljenost dvije točke u metrima (haversine). */
function udaljenost_m(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $r = 6371000.0;
    $f1 = deg2rad($lat1);
    $f2 = deg2rad($lat2);
    $df = deg2rad($lat2 - $lat1);
    $dl = deg2rad($lon2 - $lon1);
    $a = sin($df / 2) ** 2 + cos($f1) * cos($f2) * sin($dl / 2) ** 2;
    return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Procjena položaja: [status, udaljenost|null].
 * Unutar radijusa → OK. Izvan, ali unutar nepreciznosti mobitela → nepouzdano (upis ide, označen).
 * Sigurno izvan (i uz nepreciznost) → null = odbijeno.
 */
function dez_procijeni_lokaciju(array $st, ?float $lat, ?float $lon, ?float $tocnost): array
{
    if ($lat === null || $lon === null || $st['Lat'] === null || $st['Lon'] === null) {
        return [DEZ_LOK_BEZ, null];
    }
    $d = udaljenost_m((float) $st['Lat'], (float) $st['Lon'], $lat, $lon);
    $rad = max(10, (int) $st['Radijus']);
    if ($d <= $rad) {
        return [DEZ_LOK_OK, $d];
    }
    $t = $tocnost !== null ? min(max($tocnost, 0), 2000) : 0;
    if ($d - $t <= $rad) {
        return [DEZ_LOK_NEPOUZDANO, $d];
    }
    return [null, $d];
}

function dez_oznaka_lokacije(array $u, bool $tekst = false): string
{
    $s = (int) $u['Lokacija'];
    $opis = DEZ_LOKACIJA[$s] ?? '';
    if ($s === DEZ_LOK_OK) {
        return $tekst ? '✓' : '<span class="text-success" title="' . e($opis . ($u['Udaljenost'] !== null ? ' (' . round((float) $u['Udaljenost']) . ' m)' : '')) . '">✓</span>';
    }
    if ($s === DEZ_LOK_NAKNADNO) {
        return $tekst ? 'naknadno' : '<span class="badge bg-secondary" title="' . e((string) $u['NaknadnoRazlog']) . '">naknadno</span>';
    }
    $det = $opis . ($u['Udaljenost'] !== null ? ', ' . round((float) $u['Udaljenost']) . ' m' : '') . ($u['Tocnost'] !== null ? ', ±' . round((float) $u['Tocnost']) . ' m' : '');
    return $tekst ? '⚠ ' . $det : '<span class="text-warning-emphasis" title="' . e($det) . '">⚠ ' . e($opis) . '</span>';
}

// ---------- Vozila člana ----------
function vozila_clana(int $clanId): array
{
    return redovi('SELECT * FROM ClanVozila WHERE ClanId=? ORDER BY Zadano DESC, Id', [$clanId]);
}
function normaliziraj_oznaku(?string $s): string
{
    $s = mb_strtoupper(trim((string) $s));
    $s = (string) preg_replace('/\s+/', ' ', $s);
    return mb_substr($s, 0, 20);
}
function dodaj_vozilo(int $clanId, string $oznaka, bool $zadano = false): void
{
    $oznaka = normaliziraj_oznaku($oznaka);
    if ($oznaka === '') {
        return;
    }
    $postoji = red('SELECT Id FROM ClanVozila WHERE ClanId=? AND replace(upper(Oznaka),\' \',\'\')=?', [$clanId, str_replace(' ', '', $oznaka)]);
    $prvo = !vrijednost('SELECT 1 FROM ClanVozila WHERE ClanId=?', [$clanId]);
    if ($zadano || $prvo) {
        q('UPDATE ClanVozila SET Zadano=0 WHERE ClanId=?', [$clanId]);
    }
    if ($postoji) {
        if ($zadano || $prvo) {
            q('UPDATE ClanVozila SET Zadano=1 WHERE Id=?', [$postoji['Id']]);
        }
        return;
    }
    if ((int) vrijednost('SELECT COUNT(*) FROM ClanVozila WHERE ClanId=?', [$clanId]) >= 5) {
        return;
    }
    umetni('ClanVozila', ['ClanId' => $clanId, 'Oznaka' => $oznaka, 'Zadano' => ($zadano || $prvo) ? 1 : 0]);
}

// ---------- Upisi ----------
/** Zadnji upis člana na stanici u zadnjih 24 sata (za prijedlog dolazak/odlazak). */
function dez_zadnji_upis_clana(int $clanId, int $stanicaId): ?array
{
    return red('SELECT * FROM DezUpisi WHERE ClanId=? AND StanicaId=? AND Ponisteno=0 AND Vrijeme>=? ORDER BY Vrijeme DESC, Id DESC LIMIT 1',
        [$clanId, $stanicaId, date('Y-m-d H:i:s', time() - 86400)]);
}

/**
 * Filtar knjige: Razdoblje/Od/Do (kao izvještaji), SekcijaId (null = sve), StanicaId, Smjer, Trazi, Ponisteni.
 */
function dez_filtar(array $q): array
{
    $int = fn($k) => isset($q[$k]) && $q[$k] !== '' && is_numeric($q[$k]) ? (int) $q[$k] : null;
    $dat = fn($k) => isset($q[$k]) && $q[$k] !== '' ? u_datum((string) $q[$k]) : null;
    return [
        'Razdoblje' => isset(RAZDOBLJA[$q['Razdoblje'] ?? '']) ? $q['Razdoblje'] : 'OvajMjesec',
        'Od' => $dat('Od'),
        'Do' => $dat('Do'),
        'SekcijaId' => $int('SekcijaId'),
        'StanicaId' => $int('StanicaId'),
        'Smjer' => in_array($q['Smjer'] ?? '', ['D', 'O'], true) ? $q['Smjer'] : null,
        'Trazi' => mb_substr(trim((string) ($q['Trazi'] ?? '')), 0, 60),
        'Ponisteni' => !empty($q['Ponisteni']),
    ];
}
function dez_filtar_upit(array $f): array
{
    return array_filter([
        'Razdoblje' => $f['Razdoblje'], 'Od' => $f['Od'], 'Do' => $f['Do'], 'SekcijaId' => $f['SekcijaId'], 'StanicaId' => $f['StanicaId'],
        'Smjer' => $f['Smjer'], 'Trazi' => $f['Trazi'], 'Ponisteni' => $f['Ponisteni'] ? 1 : null,
    ], fn($v) => $v !== null && $v !== '');
}
function dez_opis_razdoblja(array $f): string
{
    return opis_raspona($f + ['Razdoblje' => 'OvajMjesec']);
}

/**
 * Upisi prema filtru. $stanice = dopuštene stanice (Id-ovi) – opseg korisnika ili inspekcije.
 */
function dez_upisi(array $f, array $staniceIds, int $limit = 5000): array
{
    if (!$staniceIds) {
        return [];
    }
    [$od, $do] = raspon($f);
    $w = ['u.StanicaId IN (' . implode(',', array_map('intval', $staniceIds)) . ')', 'substr(u.Vrijeme,1,10) BETWEEN ? AND ?'];
    $p = [$od, $do];
    if ($f['SekcijaId'] !== null) {
        $w[] = 'st.SekcijaId=?';
        $p[] = $f['SekcijaId'];
    }
    if ($f['StanicaId'] !== null) {
        $w[] = 'u.StanicaId=?';
        $p[] = $f['StanicaId'];
    }
    if ($f['Smjer'] !== null) {
        $w[] = 'u.Smjer=?';
        $p[] = $f['Smjer'];
    }
    if (!$f['Ponisteni']) {
        $w[] = 'u.Ponisteno=0';
    }
    if ($f['Trazi'] !== '') {
        $w[] = '(kljuc(u.Ime || \' \' || u.Prezime) LIKE ? OR kljuc(u.Prezime || \' \' || u.Ime) LIKE ? OR replace(upper(COALESCE(u.Oznaka,\'\')),\' \',\'\') LIKE ?)';
        $t = '%' . kljuc($f['Trazi']) . '%';
        array_push($p, $t, $t, '%' . str_replace(' ', '', mb_strtoupper($f['Trazi'])) . '%');
    }
    return redovi('SELECT u.*, st.Naziv AS Stanica, st.SekcijaId AS StSekcijaId, s.Naziv AS SekcijaNaziv FROM DezUpisi u JOIN DezStanice st ON st.Id=u.StanicaId
        LEFT JOIN Sekcije s ON s.Id=st.SekcijaId WHERE ' . implode(' AND ', $w) . ' ORDER BY u.Vrijeme, u.Id LIMIT ' . $limit, $p);
}

/**
 * Tko je trenutno u lovištu: zadnji upis osobe u zadnja 24 h je Dolazak.
 * @return array<int, array> upisi dolaska
 */
function dez_trenutno_unutra(array $staniceIds): array
{
    if (!$staniceIds) {
        return [];
    }
    $r = redovi('SELECT u.*, st.Naziv AS Stanica FROM DezUpisi u JOIN DezStanice st ON st.Id=u.StanicaId
        WHERE u.Ponisteno=0 AND u.Vrijeme>=? AND u.StanicaId IN (' . implode(',', array_map('intval', $staniceIds)) . ') ORDER BY u.Vrijeme, u.Id',
        [date('Y-m-d H:i:s', time() - 86400)]);
    $zadnji = [];
    foreach ($r as $u) {
        $kl = $u['ClanId'] ? 'c' . $u['ClanId'] : 'g' . kljuc($u['Ime'] . ' ' . $u['Prezime']);
        $zadnji[$kl] = $u;
    }
    return array_values(array_filter($zadnji, fn($u) => $u['Smjer'] === 'D'));
}

function dez_ime(array $u): string
{
    return trim($u['Ime'] . ' ' . $u['Prezime']);
}

/** Tko je upisao / pozvao gosta – stupac u knjizi. */
function dez_upisao(array $u): string
{
    if ($u['Naknadno']) {
        return 'naknadno: ' . ($u['UpisaoIme'] ?? '');
    }
    if ($u['Gost']) {
        return 'gost – pozvao: ' . ($u['PozvaoIme'] ?? $u['UpisaoIme'] ?? '');
    }
    if ($u['PozvaoIme']) {
        return 'upisao: ' . $u['PozvaoIme'];
    }
    return 'osobno';
}

function dez_dezinficirano(array $u): string
{
    $d = [];
    if ($u['Vozilo']) {
        $d[] = 'vozilo';
    }
    if ($u['Obuca']) {
        $d[] = 'obuća';
    }
    if ($u['Oprema']) {
        $d[] = 'oprema';
    }
    return $d ? implode(', ', $d) : '—';
}

// ---------- Ispis / PDF ----------
function dez_html(array $upisi, string $naslov, string $podnaslov, bool $zaPdf = false, bool $stanicaStupac = true): string
{
    $o = '<h2>' . e($naslov) . '</h2><div class="pod">' . e($podnaslov) . '</div>';
    $o .= "<table class='t'><thead><tr><th>Datum</th><th>Vrijeme</th><th>Ime i prezime</th><th>Član / gost</th><th>Reg. oznaka</th><th>Razlog</th><th>Smjer</th>"
        . "<th>Dezinficirano</th>" . ($stanicaStupac ? '<th>Stanica</th>' : '') . "<th>Napomena</th><th>Lokacija</th></tr></thead><tbody>";
    foreach ($upisi as $u) {
        $t = strtotime($u['Vrijeme']);
        $pon = $u['Ponisteno'] ? " class='pon'" : '';
        $o .= "<tr$pon><td>" . date('d.m.Y.', $t) . '</td><td>' . date('H:i', $t) . '</td><td>' . e(dez_ime($u)) . '</td><td>' . ($u['Gost'] ? 'gost' : 'član') . '</td><td>'
            . e($u['Oznaka'] ?? '') . '</td><td>' . e($u['Razlog'] ?? '') . '</td><td>' . DEZ_SMJER[$u['Smjer']] . '</td><td>' . e(dez_dezinficirano($u)) . '</td>'
            . ($stanicaStupac ? '<td>' . e($u['Stanica']) . '</td>' : '') . '<td>' . e(dez_upisao($u)) . ($u['Ponisteno'] ? ' · PONIŠTENO: ' . e($u['PonistenoRazlog'] ?? '') : '')
            . ($u['Naknadno'] && $u['NaknadnoRazlog'] ? ' · ' . e($u['NaknadnoRazlog']) : '') . '</td><td>' . e(dez_oznaka_lokacije($u, true)) . '</td></tr>';
    }
    if (!$upisi) {
        $o .= "<tr><td colspan='" . ($stanicaStupac ? 11 : 10) . "'>Nema upisa za odabrano razdoblje.</td></tr>";
    }
    $o .= '</tbody></table>';
    return $o;
}

/** Zaglavlje protokola: stanice s koordinatama i sredstvom. */
function dez_opis_stanica(array $stanice): string
{
    $d = [];
    foreach ($stanice as $st) {
        $d[] = $st['Naziv'] . ($st['SekcijaNaziv'] ? ' (' . $st['SekcijaNaziv'] . ')' : '')
            . ($st['Lat'] !== null ? ' · ' . number_format((float) $st['Lat'], 5, '.', '') . ', ' . number_format((float) $st['Lon'], 5, '.', '') : '')
            . ($st['Sredstvo'] ? ' · sredstvo: ' . $st['Sredstvo'] : '');
    }
    return implode("\n", $d);
}

function dez_pdf(array $upisi, array $stanice, string $razdoblje, string $sekcijaOpis): string
{
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
        $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $loviste = postavka('Dez.Loviste', '');
    $odg = postavka('Dez.OdgovornaOsoba', '');
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 8px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #555; white-space: pre-line; }
        h2 { font-size: 11.5pt; margin: 2px 0 2px; } .pod { color: #555; margin-bottom: 6px; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #bbb; padding: 2px 3px; vertical-align: top; }
        tr.pon td { color: #999; text-decoration: line-through; }
        .potpis { margin-top: 18px; } .podnozje { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · knjiga dezinfekcije · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">'
        . e(($loviste ? 'Lovište: ' . $loviste . "\n" : '') . dez_opis_stanica($stanice)) . '</div></div>'
        . dez_html($upisi, 'Evidencija dezinfekcije vozila, obuće i opreme (ASK)', 'Razdoblje: ' . $razdoblje . ' · ' . $sekcijaOpis . ' · upisa: ' . count($upisi), true, count($stanice) > 1)
        . '<div class="potpis">Odgovorna osoba: ' . ($odg !== '' ? e($odg) : '______________________') . ' &nbsp;&nbsp;&nbsp; Potpis: ______________________</div>'
        . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();
    $pdf->getCanvas()->page_text(770, 570, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}

function dez_mail_html(array $upisi, string $razdoblje, string $sekcijaOpis): string
{
    return "<p style='font-family:Arial'>U privitku je evidencija dezinfekcije (PDF) – " . e(udruga_naziv()) . ', razdoblje ' . e($razdoblje) . ', ' . e($sekcijaOpis)
        . ', ' . count($upisi) . ' upisa.</p>';
}

// ---------- Inspekcija ----------
function dez_insp_token(bool $novi = false): string
{
    $t = postavka('Dez.InspekcijaToken');
    if (!$t || $novi) {
        $t = substr(bin2hex(random_bytes(8)), 0, 12);
        spremi_postavku('Dez.InspekcijaToken', $t);
    }
    return $t;
}
function dez_insp_lozinka(): ?string
{
    return desifriraj(postavka('Dez.InspekcijaLozinka'));
}
/** Otisak lozinke u sesiji – promjena lozinke odmah odjavljuje inspekciju. */
function dez_insp_otisak(): string
{
    return substr(hash('sha256', (string) postavka('Dez.InspekcijaLozinka') . '|' . dez_insp_token()), 0, 24);
}
function dez_insp_prijavljen(): bool
{
    return !empty($_SESSION['insp']) && $_SESSION['insp']['o'] === dez_insp_otisak() && $_SESSION['insp']['do'] > time();
}

function dez_qr_url(array $st): string
{
    return apsolutni_url('dez', ['s' => $st['Token']]);
}
function dez_insp_url(): string
{
    return apsolutni_url('inspekcija', ['k' => dez_insp_token()]);
}
function karta_tocke_url(float $lat, float $lon): string
{
    return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=17/' . $lat . '/' . $lon;
}
