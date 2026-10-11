<?php
/** Uplatnice HUB-3A s 2D kodom (PDF417) za članarinu – jednokratno ili po ratama. */
declare(strict_types=1);

// PDF417 koder (bigfish/pdf417, MIT) – samo koder, crtanje je ovdje (SVG).
spl_autoload_register(function (string $kl): void {
    if (str_starts_with($kl, 'BigFish\\PDF417\\')) {
        $d = KORIJEN . '/vendor/bigfish/pdf417/src/' . str_replace('\\', '/', substr($kl, 15)) . '.php';
        if (is_file($d)) {
            require $d;
        }
    }
});

/** Podaci primatelja (udruga) za uplatnicu. */
function uplatnica_postavke(): array
{
    return [
        'Iban' => postavka('Uplatnica.Iban', ''),
        'Naziv' => postavka('Uplatnica.Naziv', udruga_naziv()),
        'Adresa' => postavka('Uplatnica.Adresa', ''),
        'Mjesto' => postavka('Uplatnica.Mjesto', ''),
        'Model' => postavka('Uplatnica.Model', 'HR00'),
        'Poziv' => postavka('Uplatnica.Poziv', '{GODINA}-{CLAN}-{RATA}'),
        'Sifra' => postavka('Uplatnica.Sifra', ''),
        'Opis' => postavka('Uplatnica.Opis', 'Članarina {GODINA}. {RATA_OPIS}'),
    ];
}

function ispravan_iban(string $iban): bool
{
    $i = strtoupper(preg_replace('/\s+/', '', $iban));
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $i)) {
        return false;
    }
    $n = '';
    foreach (str_split(substr($i, 4) . substr($i, 0, 4)) as $z) {
        $n .= ctype_alpha($z) ? (string) (ord($z) - 55) : $z;
    }
    $o = 0;
    foreach (str_split($n, 7) as $dio) {
        $o = (int) (($o . $dio) % 97);
    }
    return $o === 1;
}
function iban_razmaci(string $iban): string
{
    return trim(chunk_split(strtoupper(preg_replace('/\s+/', '', $iban)), 4, ' '));
}

/** HR00: do 22 znaka, najviše 3 dijela (samo znamenke) odvojena crticom. */
function ispravan_poziv(string $model, string $poziv): bool
{
    if ($poziv === '') {
        return true;
    }
    if (strlen($poziv) > 22 || !preg_match('/^\d+(-\d+){0,2}$/', $poziv)) {
        return false;
    }
    foreach (explode('-', $poziv) as $d) {
        if (strlen($d) > 12) {
            return false;
        }
    }
    return true;
}

/** Je li uplatnica podešena (ispravan IBAN udruge)? */
function uplatnica_spremna(): bool
{
    return ispravan_iban(uplatnica_postavke()['Iban']);
}
/** Smije li član sam vidjeti svoju uplatnicu? */
function uplatnica_za_clanove(): bool
{
    return postavka('Uplatnica.Clanovi', '1') === '1' && uplatnica_spremna();
}

/** Polja jedne uplatnice za člana. $rb = redni broj rate (0 = bez plana), $n = broj rata. */
function uplatnica_polja(array $clan, int $godina, int $rb, int $n, float $iznos, ?string $dospijece, bool $djelomicno = false): array
{
    $p = uplatnica_postavke();
    $zam = [
        '{GODINA}' => (string) $godina,
        '{CLAN}' => (string) $clan['Id'],
        '{OIB}' => (string) ($clan['Oib'] ?? ''),
        '{RATA}' => (string) max(1, $rb),
        '{RATA_OPIS}' => $n > 1 ? "$rb. rata" : '',
    ];
    $poziv = (string) preg_replace('/-{2,}/', '-', trim(strtr($p['Poziv'], $zam), '-'));
    return [
        'ClanId' => (int) $clan['Id'],
        'Godina' => $godina,
        'RedniBroj' => $rb,
        'BrojRata' => $n,
        'Platitelj' => puno_ime($clan),
        'PlatiteljAdresa' => trim(($clan['Ulica'] ?? '') . ' ' . ($clan['KucniBroj'] ?? '')),
        'PlatiteljMjesto' => trim(($clan['PostanskiBroj'] ?? '') . ' ' . ($clan['Mjesto'] ?? '')),
        'Primatelj' => $p['Naziv'],
        'PrimateljAdresa' => $p['Adresa'],
        'PrimateljMjesto' => $p['Mjesto'],
        'Iban' => strtoupper((string) preg_replace('/\s+/', '', $p['Iban'])),
        'Iznos' => round($iznos, 2),
        'Model' => $p['Model'],
        'Poziv' => $poziv,
        'Sifra' => strtoupper($p['Sifra']),
        'Opis' => trim((string) preg_replace('/\s+/', ' ', strtr($p['Opis'], $zam))),
        'Dospijece' => $dospijece,
        'Djelomicno' => $djelomicno,
    ];
}

/**
 * Uplatnice člana za godinu: jedna po otvorenoj rati (ili samo zadana rata).
 * @return array<int,array> polja za uplatnica_html()
 */
function uplatnice_clana(int $clanId, int $godina, ?int $rataBr = null): array
{
    $clan = red('SELECT * FROM Clanovi WHERE Id=?', [$clanId]);
    $cl = $clan ? clanarina_clana($clanId, $godina) : null;
    if (!$cl || isset(oslobodjeni_clanarine($godina)[$clanId])) {
        return [];
    }
    $rate = rate_clanarine($cl);
    if (!$rate) { // zaduženje bez plana – cijeli preostali iznos
        $rate = [['RedniBroj' => 0, 'Iznos' => $cl['Iznos'], 'Preostalo' => $cl['Preostalo'], 'Dospijece' => null]];
    }
    $n = count($rate);
    $r = [];
    foreach ($rate as $x) {
        if (($rataBr !== null && (int) $x['RedniBroj'] !== $rataBr) || $x['Preostalo'] <= 0.004) {
            continue;
        }
        $r[] = uplatnica_polja($clan, $godina, (int) $x['RedniBroj'], $n, (float) $x['Preostalo'], $x['Dospijece'] ? substr($x['Dospijece'], 0, 10) : null,
            $x['Preostalo'] < (float) $x['Iznos'] - 0.004);
    }
    return $r;
}

/** Naslov uplatnice za prikaz: „2. rata 2026.“ / „Članarina 2026.“ */
function uplatnica_naslov(array $u): string
{
    return ($u['BrojRata'] > 1 ? $u['RedniBroj'] . '. rata · ' : 'Članarina ') . $u['Godina'] . '.';
}

/** Kartica za zaslon (mobitel): veliki 2D kod za pokazati u banci + podaci. */
function uplatnica_kartica(array $u): string
{
    $svg = pdf417_svg(hub3_podaci($u));
    $svg = preg_replace('/ width="\d+" height="\d+"/', ' style="width:100%;height:auto;max-width:520px;display:block;margin:0 auto"', $svg, 1);
    $dosp = $u['Dospijece'] ? ' · rok <b>' . e(datum($u['Dospijece'])) . '</b>' . ($u['Dospijece'] < danas() ? ' <span class="badge bg-danger">dospjelo</span>' : '') : '';
    return '<div class="uplatnica-kartica">'
        . '<div class="d-flex flex-wrap align-items-baseline gap-2 mb-2"><span class="fw-semibold">' . e(uplatnica_naslov($u)) . '</span>'
        . '<span class="ms-auto fs-4 fw-bold">' . novac($u['Iznos']) . ' €</span></div>'
        . '<div class="small text-muted mb-2">' . e($u['Platitelj']) . $dosp . ($u['Djelomicno'] ? ' · preostali iznos' : '') . '</div>'
        . '<div class="uplatnica-kod bg-white p-2 rounded border">' . $svg . '</div>'
        . '<table class="table table-sm small mt-2 mb-0"><tbody>'
        . '<tr><th class="fw-normal text-muted">Primatelj</th><td>' . e($u['Primatelj']) . '</td></tr>'
        . '<tr><th class="fw-normal text-muted">IBAN</th><td style="font-family:monospace">' . e(iban_razmaci($u['Iban'])) . '</td></tr>'
        . '<tr><th class="fw-normal text-muted">Model / poziv</th><td>' . e($u['Model'] . ' ' . $u['Poziv']) . '</td></tr>'
        . '<tr><th class="fw-normal text-muted">Opis</th><td>' . e($u['Opis']) . '</td></tr>'
        . '</tbody></table></div>';
}

/** Šalje članu e-mail s uplatnicama (PDF u privitku). Vraća broj uplatnica ili baca iznimku. */
function posalji_uplatnice_mailom(int $clanId, int $godina): int
{
    $clan = red('SELECT * FROM Clanovi WHERE Id=?', [$clanId]);
    $u = uplatnice_clana($clanId, $godina);
    if (!$clan || !$u) {
        return 0;
    }
    $email = trim((string) $clan['Email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('član nema ispravnu e-mail adresu');
    }
    $uk = array_sum(array_column($u, 'Iznos'));
    $redovi = implode('', array_map(fn($x) => '<li>' . e(uplatnica_naslov($x)) . ': <b>' . novac($x['Iznos']) . ' €</b>' . ($x['Dospijece'] ? ', rok ' . e(datum($x['Dospijece'])) : '') . '</li>', $u));
    $html = '<p>Poštovani/a ' . e($clan['Ime']) . ',</p><p>u privitku ' . (count($u) > 1 ? 'su uplatnice' : 'je uplatnica') . ' za članarinu ' . $godina . '. (ukupno otvoreno ' . novac($uk) . ' €):</p><ul>' . $redovi . '</ul>'
        . '<p>Uplatnicu možete ispisati ili 2D kod skenirati u mobilnom bankarstvu. Uplatnicu vidite i u aplikaciji: <a href="' . e(apsolutni_url('uplatnica', ['godina' => $godina])) . '">Moja uplatnica</a>.</p>'
        . '<p>Ako ste već platili, zanemarite ovu poruku.</p><p>' . e(udruga_naziv()) . '</p>';
    posalji_mail($email, 'Uplatnica – članarina ' . $godina . '. – ' . udruga_kratko(), $html, true, null,
        ['uplatnica-clanarina-' . $godina . '.pdf' => uplatnice_pdf($u, 'Uplatnica – članarina ' . $godina)]);
    return count($u);
}

/** Tekst za WhatsApp/Viber/SMS s poveznicom na uplatnicu u aplikaciji. */
function tekst_uplatnice(array $clan, int $godina): string
{
    $u = uplatnice_clana((int) $clan['Id'], $godina);
    $uk = array_sum(array_column($u, 'Iznos'));
    $sljedeca = $u[0] ?? null;
    return 'Pozdrav ' . $clan['Ime'] . ', za članarinu ' . $godina . '. otvoreno je ' . novac($uk) . ' €'
        . ($sljedeca && $sljedeca['Dospijece'] ? ' (sljedeći rok ' . rtrim(datum($sljedeca['Dospijece']), '.') . ')' : '') . ".\n"
        . 'Uplatnica s 2D kodom (za ispis ili mobilno bankarstvo): ' . apsolutni_url('uplatnica', ['godina' => $godina]) . "\n"
        . 'Ako ste već platili, zanemarite. – ' . udruga_kratko();
}

/** Tekst za 2D kod prema HUB-3 standardu (ISO-8859-2, svaki element završava s LF). */
function hub3_podaci(array $u): string
{
    $pol = fn(string $s, int $max) => mb_substr(trim((string) preg_replace('/\s+/', ' ', $s)), 0, $max);
    $redovi = [
        'HRVHUB30',
        'EUR',
        str_pad((string) (int) round($u['Iznos'] * 100), 15, '0', STR_PAD_LEFT),
        $pol($u['Platitelj'], 30),
        $pol($u['PlatiteljAdresa'], 27),
        $pol($u['PlatiteljMjesto'], 27),
        $pol($u['Primatelj'], 25),
        $pol($u['PrimateljAdresa'], 25),
        $pol($u['PrimateljMjesto'], 27),
        $pol($u['Iban'], 21),
        $pol($u['Model'], 4),
        $pol($u['Poziv'], 22),
        $pol($u['Sifra'], 4),
        $pol($u['Opis'], 35),
    ];
    return (string) mb_convert_encoding(implode("\n", $redovi) . "\n", 'ISO-8859-2', 'UTF-8');
}

/** PDF417 kao SVG (HUB-3: razina korekcije 4, 9 stupaca, visina retka = 3 modula). */
function pdf417_svg(string $podaci, int $stupci = 9, int $razina = 4): string
{
    $p = new BigFish\PDF417\PDF417();
    $p->setColumns($stupci);
    $p->setSecurityLevel($razina);
    $kod = $p->encode($podaci);
    $tih = 2;
    $vis = 3;
    $sirina = 0;
    $rect = '';
    foreach ($kod->codes as $y => $red) {
        $bitovi = implode('', array_map('decbin', $red));
        $sirina = max($sirina, strlen($bitovi));
        preg_match_all('/1+/', $bitovi, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$niz, $x]) {
            $rect .= 'M' . ($x + $tih) . ' ' . ($y * $vis + $tih) . 'h' . strlen($niz) . 'v' . $vis . 'h-' . strlen($niz) . 'z';
        }
    }
    $w = $sirina + 2 * $tih;
    $h = count($kod->codes) * $vis + 2 * $tih;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" shape-rendering="crispEdges">'
        . '<rect width="' . $w . '" height="' . $h . '" fill="#fff"/><path d="' . $rect . '" fill="#000"/></svg>';
}

/** Jedna uplatnica HUB-3A (HTML za dompdf), visina ≈ 1/3 A4. */
function uplatnica_html(array $u): string
{
    $svg = pdf417_svg(hub3_podaci($u));
    $img = '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '" style="width:58mm">';
    $polje = fn(string $nat, string $vr, string $st = '') => '<div class="nat">' . $nat . '</div><div class="pol"' . ($st ? " style=\"$st\"" : '') . '>' . ($vr === '' ? '&nbsp;' : e($vr)) . '</div>';
    return '<div class="upl"><table class="u"><tr>
        <td class="lijevo">
            <div class="nas">NALOG ZA PLAĆANJE</div>
            ' . $polje('Platitelj (naziv/ime i adresa)', $u['Platitelj'] . "\n" . $u['PlatiteljAdresa'] . "\n" . $u['PlatiteljMjesto'], 'height:15mm;white-space:pre-line') . '
            ' . $polje('Primatelj (naziv/ime i adresa)', $u['Primatelj'] . "\n" . $u['PrimateljAdresa'] . "\n" . $u['PrimateljMjesto'], 'height:15mm;white-space:pre-line') . '
            <div class="kod">' . $img . '</div>
        </td>
        <td class="desno">
            <div class="hub">HUB-3A</div>
            <table class="r"><tr>
                <td style="width:16mm">' . $polje('Valuta', 'EUR') . '</td>
                <td>' . $polje('Iznos', '=' . novac($u['Iznos']), 'text-align:right;font-weight:bold;font-size:11pt') . '</td></tr></table>
            ' . $polje('IBAN ili broj računa primatelja', iban_razmaci($u['Iban']), 'letter-spacing:0.4pt') . '
            <table class="r"><tr>
                <td style="width:16mm">' . $polje('Model', $u['Model']) . '</td>
                <td>' . $polje('Poziv na broj primatelja', $u['Poziv']) . '</td></tr></table>
            <table class="r"><tr>
                <td style="width:16mm">' . $polje('Šifra namj.', $u['Sifra']) . '</td>
                <td>' . $polje('Opis plaćanja', $u['Opis']) . '</td></tr></table>
            ' . $polje('Datum izvršenja', '') . '
            <div class="nap">' . ($u['Dospijece'] ? 'Rok uplate: <b>' . e(datum($u['Dospijece'])) . '</b>' : '') . ($u['Djelomicno'] ? ' · preostali iznos rate' : '')
                . '<br>Skenirajte 2D kod u mobilnom bankarstvu ili predajte u banci / pošti / FINA-i.</div>
        </td></tr></table></div>';
}

/** PDF s uplatnicama (3 na A4, s linijom za rezanje). */
function uplatnice_pdf(array $uplatnice, string $naslov): string
{
    $tijelo = '';
    foreach (array_values($uplatnice) as $i => $u) {
        if ($i > 0 && $i % 3 === 0) {
            $tijelo .= '<div style="page-break-before:always"></div>';
        }
        $tijelo .= uplatnica_html($u);
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . e($naslov) . '</title><style>
        @page { margin: 6mm 8mm; } body { font-family: "DejaVu Sans"; font-size: 8pt; color: #222; }
        .upl { height: 89mm; border-bottom: 0.6pt dashed #999; padding-top: 2mm; margin-bottom: 2mm; overflow: hidden; }
        table.u { width: 100%; border-collapse: collapse; } table.u td { vertical-align: top; }
        td.lijevo { width: 47%; padding-right: 4mm; border-right: 0.6pt solid #c8102e; } td.desno { padding-left: 4mm; }
        .nas { font-weight: bold; color: #c8102e; font-size: 9pt; margin-bottom: 1mm; } .hub { text-align: right; font-weight: bold; color: #c8102e; font-size: 9pt; margin-bottom: 1mm; }
        .nat { font-size: 6pt; color: #c8102e; text-transform: uppercase; margin-top: 1.2mm; }
        .pol { border: 0.6pt solid #c8102e; background: #fff6f6; padding: 1mm 1.5mm; min-height: 4mm; font-size: 8.5pt; }
        table.r { width: 100%; border-collapse: collapse; } table.r td { padding: 0 1mm 0 0; vertical-align: top; } table.r td:last-child { padding-right: 0; }
        .kod { margin-top: 2.5mm; } .nap { font-size: 6.5pt; color: #666; margin-top: 2mm; }
    </style></head><body>' . $tijelo . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    return (string) $pdf->output();
}
