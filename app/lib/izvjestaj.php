<?php
/** Izvještaj radnih akcija: filtar, razdoblja (i lovna godina 1.4.–31.3.), grupiranje, CSV, HTML. */
declare(strict_types=1);

const RAZDOBLJA = [
    'OvajMjesec' => 'Ovaj mjesec', 'ProsliMjesec' => 'Prošli mjesec', 'OvaGodina' => 'Ova godina', 'ProslaGodina' => 'Prošla godina',
    'LovnaGodina' => 'Tekuća lovna godina', 'ProslaLovnaGodina' => 'Prošla lovna godina', 'Mjeseci' => 'Mjeseci (od – do)',
    'Slobodno' => 'Datumi (od – do)', 'Sve' => 'Sve',
];
const SORTIRANJA = ['Datum' => 'Datum ↑', 'DatumSilazno' => 'Datum ↓', 'Clan' => 'Član', 'Vrsta' => 'Vrsta', 'Bodovi' => 'Bodovi ↑', 'BodoviSilazno' => 'Bodovi ↓'];
const GRUPIRANJA = ['Bez' => 'Bez grupiranja', 'PoClanu' => 'Po članu', 'PoMjesecu' => 'Po mjesecu', 'PoVrsti' => 'Po vrsti', 'PoSekciji' => 'Po sekciji'];
const STATUSI_AKCIJE = [AKCIJA_CEKA => 'Na čekanju', AKCIJA_ODOBRENO => 'Odobreno', AKCIJA_ODBIJENO => 'Odbijeno'];
const STATUS_IMENA = ['NaCekanju' => AKCIJA_CEKA, 'Odobreno' => AKCIJA_ODOBRENO, 'Odbijeno' => AKCIJA_ODBIJENO];
const MJESECI_HR = [1 => 'siječanj', 'veljača', 'ožujak', 'travanj', 'svibanj', 'lipanj', 'srpanj', 'kolovoz', 'rujan', 'listopad', 'studeni', 'prosinac'];

/** Filtar iz upita (ista imena parametara kao .NET verzija). */
function filtar_iz_upita(array $q): array
{
    $int = fn($k) => isset($q[$k]) && $q[$k] !== '' && is_numeric($q[$k]) ? (int) $q[$k] : null;
    $dat = fn($k) => isset($q[$k]) && $q[$k] !== '' ? u_datum((string) $q[$k]) : null;
    $st = $q['Status'] ?? '';
    return [
        'Razdoblje' => isset(RAZDOBLJA[$q['Razdoblje'] ?? '']) ? $q['Razdoblje'] : 'OvaGodina',
        'Od' => $dat('Od'),
        'Do' => $dat('Do'),
        'SekcijaId' => $int('SekcijaId'),
        'ClanId' => $int('ClanId'),
        'VrstaId' => $int('VrstaId'),
        'Status' => is_numeric($st) ? (int) $st : (STATUS_IMENA[$st] ?? null),
        'Sortiranje' => isset(SORTIRANJA[$q['Sortiranje'] ?? '']) ? $q['Sortiranje'] : 'Datum',
        'Grupiranje' => isset(GRUPIRANJA[$q['Grupiranje'] ?? '']) ? $q['Grupiranje'] : 'Bez',
    ];
}
function filtar_upit(array $f): array
{
    $st = array_flip(STATUS_IMENA);
    return array_filter([
        'Razdoblje' => $f['Razdoblje'], 'Sortiranje' => $f['Sortiranje'], 'Grupiranje' => $f['Grupiranje'],
        'Od' => $f['Od'], 'Do' => $f['Do'], 'SekcijaId' => $f['SekcijaId'], 'ClanId' => $f['ClanId'], 'VrstaId' => $f['VrstaId'],
        'Status' => $f['Status'] !== null ? $st[$f['Status']] : null,
    ], fn($v) => $v !== null && $v !== '');
}

function pocetak_lovne_godine(?DateTimeImmutable $d = null): DateTimeImmutable
{
    $d ??= new DateTimeImmutable('today');
    $g = (int) $d->format('n') >= 4 ? (int) $d->format('Y') : (int) $d->format('Y') - 1;
    return new DateTimeImmutable("$g-04-01");
}

/** [od, do] kao 'Y-m-d'. */
function raspon(array $f): array
{
    $danas = new DateTimeImmutable('today');
    $mj = $danas->modify('first day of this month');
    $lg = pocetak_lovne_godine($danas);
    $g = (int) $danas->format('Y');
    $r = match ($f['Razdoblje']) {
        'OvajMjesec' => [$mj, $mj->modify('last day of this month')],
        'ProsliMjesec' => [$mj->modify('-1 month'), $mj->modify('-1 day')],
        'OvaGodina' => [new DateTimeImmutable("$g-01-01"), new DateTimeImmutable("$g-12-31")],
        'ProslaGodina' => [new DateTimeImmutable(($g - 1) . '-01-01'), new DateTimeImmutable(($g - 1) . '-12-31')],
        'LovnaGodina' => [$lg, $lg->modify('+1 year -1 day')],
        'ProslaLovnaGodina' => [$lg->modify('-1 year'), $lg->modify('-1 day')],
        'Mjeseci' => [
            $f['Od'] ? (new DateTimeImmutable($f['Od']))->modify('first day of this month') : $mj,
            ($f['Do'] ? new DateTimeImmutable($f['Do']) : $danas)->modify('last day of this month'),
        ],
        'Sve' => [new DateTimeImmutable('2000-01-01'), new DateTimeImmutable('2100-12-31')],
        default => [new DateTimeImmutable($f['Od'] ?? '2000-01-01'), new DateTimeImmutable($f['Do'] ?? '2100-12-31')],
    };
    return [$r[0]->format('Y-m-d'), $r[1]->format('Y-m-d')];
}

function mjesec_godina(string $d): string
{
    $t = strtotime($d);
    return MJESECI_HR[(int) date('n', $t)] . ' ' . date('Y', $t) . '.';
}

function opis_raspona(array $f): string
{
    [$od, $do] = raspon($f);
    if ($f['Razdoblje'] === 'Sve') {
        return 'sve';
    }
    if ($f['Razdoblje'] === 'Mjeseci') {
        return substr($od, 0, 7) !== substr($do, 0, 7) ? mjesec_godina($od) . ' – ' . mjesec_godina($do) : mjesec_godina($od);
    }
    return date('d.m.Y.', strtotime($od)) . ' – ' . date('d.m.Y.', strtotime($do));
}

/**
 * Radne akcije prema filtru, u opsegu trenutnog korisnika.
 * @return array<int, array{naziv:string, retci:array}>
 */
function izvjestaj_akcija(array $f, bool $bezOpsega = false): array
{
    [$od, $do] = raspon($f);
    $w = ['substr(a.Datum,1,10) BETWEEN ? AND ?'];
    $p = [$od, $do];
    if (!$bezOpsega) {
        $w[] = opseg_sql('c');
    }
    if ($f['SekcijaId'] === -1) {
        $w[] = 'c.SekcijaId IS NULL';
    } elseif ($f['SekcijaId'] !== null) {
        $w[] = 'c.SekcijaId=?';
        $p[] = $f['SekcijaId'];
    }
    foreach (['ClanId' => 'a.ClanId', 'VrstaId' => 'a.VrstaAkcijeId', 'Status' => 'a.Status'] as $k => $kol) {
        if ($f[$k] !== null) {
            $w[] = "$kol=?";
            $p[] = $f[$k];
        }
    }
    $retci = redovi('SELECT a.*, c.Ime, c.Prezime, s.Naziv AS Sekcija, v.Naziv AS VrstaNaziv FROM RadneAkcije a JOIN Clanovi c ON c.Id=a.ClanId
        LEFT JOIN Sekcije s ON s.Id=c.SekcijaId LEFT JOIN VrsteAkcija v ON v.Id=a.VrstaAkcijeId WHERE ' . implode(' AND ', $w), $p);
    foreach ($retci as &$r) {
        $r['Clan'] = prezime_ime($r);
        $r['Sekcija'] ??= 'Nije raspoređeno';
        $r['Vrsta'] = $r['VrstaNaziv'] ?? $r['VrstaSlobodno'] ?? '—';
        $r['Datum'] = substr($r['Datum'], 0, 10);
        $r['Status'] = (int) $r['Status'];
    }
    unset($r);
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    $cmp = fn($a, $b) => $coll ? $coll->compare($a, $b) : strcmp(kljuc($a), kljuc($b));
    usort($retci, match ($f['Sortiranje']) {
        'DatumSilazno' => fn($a, $b) => [$b['Datum'], kljuc($a['Clan'])] <=> [$a['Datum'], kljuc($b['Clan'])],
        'Clan' => fn($a, $b) => $cmp($a['Clan'], $b['Clan']) ?: strcmp($a['Datum'], $b['Datum']),
        'Vrsta' => fn($a, $b) => $cmp($a['Vrsta'], $b['Vrsta']) ?: strcmp($a['Datum'], $b['Datum']),
        'Bodovi' => fn($a, $b) => [(float) $a['Bodovi'], $a['Datum']] <=> [(float) $b['Bodovi'], $b['Datum']],
        'BodoviSilazno' => fn($a, $b) => (float) $b['Bodovi'] <=> (float) $a['Bodovi'] ?: strcmp($a['Datum'], $b['Datum']),
        default => fn($a, $b) => strcmp($a['Datum'], $b['Datum']) ?: $cmp($a['Clan'], $b['Clan']),
    });
    if ($f['Grupiranje'] === 'Bez') {
        return [['naziv' => '', 'retci' => $retci]];
    }
    $grupe = [];
    foreach ($retci as $r) {
        $kl = match ($f['Grupiranje']) {
            'PoClanu' => $r['Clan'],
            'PoMjesecu' => substr($r['Datum'], 0, 7),
            'PoVrsti' => $r['Vrsta'],
            default => $r['Sekcija'],
        };
        $grupe[$kl][] = $r;
    }
    if ($f['Grupiranje'] === 'PoMjesecu') {
        ksort($grupe);
    } else {
        uksort($grupe, $cmp);
    }
    $rez = [];
    foreach ($grupe as $kl => $rr) {
        $rez[] = ['naziv' => $f['Grupiranje'] === 'PoMjesecu' ? mjesec_godina($kl . '-01') : (string) $kl, 'retci' => $rr];
    }
    return $rez;
}

function ukupno_bodova(array $retci): float
{
    return array_sum(array_map(fn($r) => $r['Status'] === AKCIJA_ODOBRENO ? (float) $r['Bodovi'] : 0.0, $retci));
}
function ukupno_sati(array $retci): float
{
    return array_sum(array_map(fn($r) => (float) $r['Sati'], $retci));
}

function izvjestaj_csv(array $grupe): string
{
    $esc = function ($s) {
        $s = str_replace(["\r", "\n"], ' ', (string) $s);
        return (str_contains($s, ';') || str_contains($s, '"')) ? '"' . str_replace('"', '""', $s) . '"' : $s;
    };
    $broj = fn($v) => $v === null || $v === '' ? '' : str_replace('.', ',', (string) (float) $v);
    $o = "\xEF\xBB\xBFGrupa;Datum;Član;Sekcija;Vrsta;Sati;Opis;Status;Bodovi\r\n";
    foreach ($grupe as $g) {
        foreach ($g['retci'] as $r) {
            $o .= implode(';', [$esc($g['naziv']), date('d.m.Y', strtotime($r['Datum'])), $esc($r['Clan']), $esc($r['Sekcija']), $esc($r['Vrsta']),
                $broj($r['Sati']), $esc($r['Opis']), STATUSI_AKCIJE[$r['Status']], $broj($r['Bodovi'])]) . "\r\n";
        }
    }
    return $o;
}

/** HTML izvještaja (za e-poštu i PDF). */
function izvjestaj_html(array $grupe, string $naslov, bool $zaPdf = false, bool $sekcijaStupac = false): string
{
    $st = $zaPdf ? '' : " style='font-family:Arial'";
    $t = $zaPdf ? "class='t'" : "border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-family:Arial;font-size:13px'";
    $o = "<h2$st>" . e($naslov) . '</h2>';
    foreach ($grupe as $g) {
        if ($g['naziv'] !== '') {
            $o .= "<h3$st>" . e($g['naziv']) . '</h3>';
        }
        $o .= "<table $t><thead><tr><th>Datum</th><th>Član</th>" . ($sekcijaStupac ? '<th>Sekcija</th>' : '') . "<th>Vrsta</th><th>Sati</th><th>Opis</th><th>Status</th><th>Bodovi</th></tr></thead><tbody>";
        foreach ($g['retci'] as $r) {
            $o .= '<tr><td>' . date('d.m.Y', strtotime($r['Datum'])) . '</td><td>' . e($r['Clan']) . '</td>' . ($sekcijaStupac ? '<td>' . e($r['Sekcija']) . '</td>' : '') . '<td>' . e($r['Vrsta']) . '</td><td class="d">'
                . broj($r['Sati']) . '</td><td>' . e($r['Opis']) . '</td><td>' . STATUSI_AKCIJE[$r['Status']] . '</td><td class="d">' . broj($r['Bodovi'], 2) . '</td></tr>';
        }
        $o .= "<tr class='uk'><td colspan='" . ($sekcijaStupac ? 4 : 3) . "'><b>Ukupno</b></td><td class='d'><b>" . broj(ukupno_sati($g['retci'])) . "</b></td><td colspan='2'></td><td class='d'><b>"
            . broj(ukupno_bodova($g['retci']), 2) . '</b></td></tr></tbody></table>';
    }
    return $o;
}

/** PDF izvještaja (dompdf). */
function izvjestaj_pdf(array $grupe, string $naslov, string $podnaslov = '', bool $sekcijaStupac = false): string
{
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
        $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 18mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
        h2 { font-size: 12pt; margin: 4px 0 8px; } h3 { font-size: 10.5pt; margin: 12px 0 4px; color: #2f5d23; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #bbb; padding: 3px 4px; vertical-align: top; } .t td.d { text-align: right; white-space: nowrap; }
        .uk td { background: #f4f6f2; } .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div><div class="p">' . e($podnaslov) . '</div></div>'
        . izvjestaj_html($grupe, $naslov, true, $sekcijaStupac) . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $c = $pdf->getCanvas();
    $c->page_text(520, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}

function status_akcije_oznaka(int $s): string
{
    return match ($s) {
        AKCIJA_CEKA => '<span class="badge bg-warning text-dark">Na čekanju</span>',
        AKCIJA_ODOBRENO => '<span class="badge bg-success">Odobreno</span>',
        default => '<span class="badge bg-danger">Odbijeno</span>',
    };
}
