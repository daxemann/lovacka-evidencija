<?php
/** Inventura: mape (lovački dom, sekcija, hranilišta…) sa stavkama „treba“ i „stvarno“; PDF lista za popis. */
declare(strict_types=1);

/**
 * Mape koje trenutni korisnik smije vidjeti (mapa bez sekcije = cijela udruga), poredane: cijela udruga, pa sekcije redom.
 * $filtar: '' = sve, 'udruga' = samo mape cijele udruge, broj = Id sekcije.
 */
function inv_mape(string $filtar = ''): array
{
    $mape = redovi('SELECT m.*, s.Naziv AS Sekcija,
            (SELECT COUNT(*) FROM InvStavke i WHERE i.MapaId=m.Id) AS Stavki,
            (SELECT COUNT(*) FROM InvStavke i WHERE i.MapaId=m.Id AND i.Stvarno IS NOT NULL AND i.Stvarno < COALESCE(i.Treba,0) - 0.00001) AS Manjak,
            (SELECT COUNT(*) FROM InvStavke i WHERE i.MapaId=m.Id AND i.Stvarno IS NOT NULL AND i.Stvarno > COALESCE(i.Treba,0) + 0.00001) AS Visak,
            (SELECT COUNT(*) FROM InvStavke i WHERE i.MapaId=m.Id AND i.Stvarno IS NULL) AS Nepopisano
        FROM InvMape m LEFT JOIN Sekcije s ON s.Id=m.SekcijaId
        ORDER BY (m.SekcijaId IS NOT NULL), s.Redoslijed, s.Naziv, m.Redoslijed, m.Naziv');
    return array_values(array_filter($mape, fn($m) => inv_smije($m) && inv_u_filtru($m, $filtar)));
}

function inv_u_filtru(array $m, string $filtar): bool
{
    return match (true) {
        $filtar === '' => true,
        $filtar === 'udruga' => $m['SekcijaId'] === null,
        default => (string) $m['SekcijaId'] === $filtar,
    };
}

/** Ključ skupine mape za filtar: 'udruga' ili Id sekcije. */
function inv_skupina(array $m): string
{
    return $m['SekcijaId'] === null ? 'udruga' : (string) $m['SekcijaId'];
}

/** Naziv filtra / skupine za naslove i PDF. */
function inv_filtar_naziv(string $filtar): string
{
    return match (true) {
        $filtar === '' => 'Sve sekcije',
        $filtar === 'udruga' => 'Cijela udruga',
        default => 'Sekcija ' . naziv_sekcije((int) $filtar),
    };
}

/** Filtar iz upita, samo ako ga korisnik smije vidjeti. */
function inv_filtar_iz_upita(): string
{
    $f = (string) ($_GET['sekcija'] ?? '');
    if ($f === 'udruga' || ($f !== '' && ctype_digit($f) && moze_sekciju((int) $f))) {
        return $f;
    }
    return '';
}

function inv_smije(?array $m): bool
{
    return $m && ($m['SekcijaId'] === null || moze_sekciju((int) $m['SekcijaId']));
}

/** Mapa ili 404 / zabranjeno. */
function inv_mapa(int $id): array
{
    $m = red('SELECT m.*, s.Naziv AS Sekcija FROM InvMape m LEFT JOIN Sekcije s ON s.Id=m.SekcijaId WHERE m.Id=?', [$id]);
    if (!$m) {
        nije_pronadjeno('Mapa inventure');
    }
    if (!inv_smije($m)) {
        zabranjeno();
    }
    return $m;
}

function inv_stavke(int $mapaId): array
{
    return redovi('SELECT * FROM InvStavke WHERE MapaId=? ORDER BY Redoslijed, Id', [$mapaId]);
}

/** Razlika stvarno − treba; null dok stavka nije popisana. */
function inv_razlika(array $s): ?float
{
    if ($s['Stvarno'] === null) {
        return null;
    }
    $r = (float) $s['Stvarno'] - (float) ($s['Treba'] ?? 0);
    return abs($r) < 0.00001 ? 0.0 : $r;
}

/** Oznaka razlike za web: crveno manjak, zeleno višak / u redu, sivo nepopisano. */
function inv_razlika_html(array $s): string
{
    $r = inv_razlika($s);
    if ($r === null) {
        return '<span class="text-body-tertiary">—</span>';
    }
    if ($r == 0.0) {
        return '<span class="badge bg-success-subtle text-success-emphasis">✓ 0</span>';
    }
    return $r < 0
        ? '<span class="badge bg-danger">' . e('−' . broj(abs($r), 2)) . '<span class="inv-rijec"> manjak</span></span>'
        : '<span class="badge bg-success">' . e('+' . broj($r, 2)) . '<span class="inv-rijec"> višak</span></span>';
}

/**
 * PDF za inventuru. $sRezultatom = false: stupac „Stvarno“ prazan (lista za popis na licu mjesta);
 * true: upisano stanje i razlika (manjak / višak).
 */
function inv_pdf(array $mape, bool $sRezultatom, string $opseg = ''): string
{
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
        $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $zag = '<div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div>' . (udruga_adresa() ? '<div style="color:#555">' . e(udruga_adresa()) . '</div>' : '')
        . '<div class="p">' . ($sRezultatom ? 'Inventura – rezultat popisa' : 'Inventura – lista za popis') . ($opseg !== '' ? ' · ' . e($opseg) : '') . '</div></div>';
    $tijelo = '';
    foreach ($mape as $i => $m) {
        $stavke = inv_stavke((int) $m['Id']);
        $tijelo .= '<div' . ($i > 0 ? ' style="page-break-before:always"' : '') . '>' . $zag
            . '<h2>' . e($m['Naziv']) . ($m['Sekcija'] ? ' <span class="sek">· ' . e($m['Sekcija']) . '</span>' : '') . '</h2>'
            . '<table class="t"><thead><tr><th style="width:7mm">Rb.</th><th>Naziv</th><th style="width:16mm">Jed.</th><th class="d" style="width:20mm">Treba</th>'
            . '<th class="d" style="width:24mm">Stvarno</th>' . ($sRezultatom ? '<th class="d" style="width:26mm">Razlika</th>' : '') . '<th style="width:' . ($sRezultatom ? '38' : '52') . 'mm">Napomena</th></tr></thead><tbody>';
        if (!$stavke) {
            $tijelo .= '<tr><td colspan="' . ($sRezultatom ? 7 : 6) . '" style="color:#888">Mapa nema stavki.</td></tr>';
        }
        foreach ($stavke as $n => $s) {
            $r = inv_razlika($s);
            $razl = '';
            if ($sRezultatom) {
                $razl = '<td class="d">' . ($r === null ? '–' : ($r == 0.0 ? '<span class="ok">0</span>'
                    : ($r < 0 ? '<span class="mi">−' . broj(abs($r), 2) . ' manjak</span>' : '<span class="pl">+' . broj($r, 2) . ' višak</span>'))) . '</td>';
            }
            $tijelo .= '<tr><td>' . ($n + 1) . '.</td><td>' . e($s['Naziv']) . '</td><td>' . e($s['Jedinica']) . '</td><td class="d">' . broj($s['Treba'], 2) . '</td>'
                . '<td class="d' . ($sRezultatom ? '' : ' upis') . '">' . ($sRezultatom ? broj($s['Stvarno'], 2) : '') . '</td>' . $razl
                . '<td>' . ($sRezultatom ? e($s['Napomena']) : '') . '</td></tr>';
        }
        $tijelo .= '</tbody></table>'
            . '<table class="potpis"><tr><td>Popis obavio: ______________________________</td><td>Datum: ____________</td><td>Potpis: ____________________</td></tr></table></div>';
    }
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
        h2 { font-size: 12pt; margin: 4px 0 8px; } .sek { color: #666; font-weight: normal; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #999; padding: 4px 4px; vertical-align: middle; } .t .d { text-align: right; white-space: nowrap; }
        .t td.upis { background: #fbfbf8; } .t tbody td { height: 6.5mm; }
        .mi { color: #b02a37; font-weight: bold; } .pl { color: #1e7b34; font-weight: bold; } .ok { color: #1e7b34; }
        .potpis { width: 100%; margin-top: 14mm; font-size: 9pt; } .potpis td { padding: 0 4px; }
        .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>' . ($tijelo ?: $zag . '<p>Nema mapa inventure.</p>') . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->getCanvas()->page_text(520, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}
