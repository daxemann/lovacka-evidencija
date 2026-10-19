<?php
/**
 * Članska iskaznica s QR kodom (format bankovne kartice 85,6 × 54 mm).
 *
 * Svaki član ima stalni slučajni ključ – u QR kodu nema imena, OIB-a ni drugih podataka.
 * Ključ služi SAMO za popis sudionika grupnog lova (lovnik skenira iskaznice) – nije povezan
 * s dezinfekcijom, bodovima ni ničim drugim.
 * Iskaznica vrijedi dok je član aktivan: arhiviran ili obrisan član → iskaznica ne vrijedi.
 * Izgubljena iskaznica → „Nova iskaznica“ (novi ključ), stara odmah prestaje vrijediti.
 */
declare(strict_types=1);

const ISK_PREFIKS = 'LEI1:';
const ISK_SIRINA_MM = 85.6;
const ISK_VISINA_MM = 54.0;

// ---------- Ključ ----------
function isk_novi_token(): string
{
    return strtoupper(bin2hex(random_bytes(12)));
}

/** Ključ člana (stvara se pri prvoj potrebi). */
function isk_kljuc(int $clanId): string
{
    return isk_kljucevi([$clanId])[$clanId] ?? '';
}

/** Ključevi za više članova odjednom: [clanId => token]; nedostajući se stvaraju. */
function isk_kljucevi(array $clanIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $clanIds))));
    if (!$ids) {
        return [];
    }
    $out = [];
    foreach (array_chunk($ids, 500) as $dio) {
        $in = implode(',', $dio);
        foreach (redovi("SELECT ClanId, Token FROM ClanIskaznice WHERE ClanId IN ($in)") as $r) {
            $out[(int) $r['ClanId']] = $r['Token'];
        }
    }
    $nedostaju = array_diff($ids, array_keys($out));
    if ($nedostaju) {
        transakcija(function () use ($nedostaju, &$out) {
            foreach ($nedostaju as $id) {
                if (!vrijednost('SELECT 1 FROM Clanovi WHERE Id=?', [$id])) {
                    continue;
                }
                $t = isk_novi_token();
                q('INSERT OR IGNORE INTO ClanIskaznice (ClanId, Token, Kreirano) VALUES (?,?,?)', [$id, $t, sada()]);
                $out[$id] = (string) vrijednost('SELECT Token FROM ClanIskaznice WHERE ClanId=?', [$id]);
            }
        });
    }
    return $out;
}

/** Nova iskaznica (izgubljena / ukradena): novi ključ, stara iskaznica više ne vrijedi. */
function isk_nova(int $clanId): string
{
    $t = isk_novi_token();
    q('INSERT INTO ClanIskaznice (ClanId, Token, Kreirano) VALUES (?,?,?) ON CONFLICT(ClanId) DO UPDATE SET Token=excluded.Token, Kreirano=excluded.Kreirano', [$clanId, $t, sada()]);
    return $t;
}

function isk_qr_tekst(string $token): string
{
    return ISK_PREFIKS . $token;
}

/** Ključ iz skeniranog teksta (ili null ako to nije iskaznica). */
function isk_token_iz_teksta(string $tekst): ?string
{
    $tekst = strtoupper(trim($tekst));
    if (str_starts_with($tekst, ISK_PREFIKS)) {
        $tekst = substr($tekst, strlen(ISK_PREFIKS));
    }
    return preg_match('/^[0-9A-F]{24}$/', $tekst) ? $tekst : null;
}

/** Član po ključu iskaznice (bez obzira na status – pozivatelj provjerava je li aktivan). */
function isk_clan(string $token): ?array
{
    return red('SELECT c.*, s.Naziv AS SekcijaNaziv FROM ClanIskaznice i JOIN Clanovi c ON c.Id=i.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE i.Token=?', [$token]);
}

// ---------- QR ----------
/** QR matrica (true = tamni modul). Razina Q – iskaznica izdrži ogrebotine i prljavštinu. */
function qr_matrica(string $tekst): array
{
    require_once KORIJEN . '/vendor/kazuhikoarase/qrcode-generator/qrcode.php';
    $q = QRCode::getMinimumQRCode($tekst, QR_ERROR_CORRECT_LEVEL_Q);
    $n = $q->getModuleCount();
    $m = [];
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            $m[$r][$c] = (bool) $q->isDark($r, $c);
        }
    }
    return $m;
}

/** QR kao SVG (s bijelim rubom od $rub modula). */
function qr_svg(string $tekst, int $rub = 2, string $boja = '#000'): string
{
    $m = qr_matrica($tekst);
    $n = count($m);
    $v = $n + 2 * $rub;
    $d = '';
    foreach ($m as $r => $red) {
        $c = 0;
        while ($c < $n) {
            if (!$red[$c]) {
                $c++;
                continue;
            }
            $poc = $c;
            while ($c < $n && $red[$c]) {
                $c++;
            }
            $d .= 'M' . ($poc + $rub) . ' ' . ($r + $rub) . 'h' . ($c - $poc) . 'v1h-' . ($c - $poc) . 'z';
        }
    }
    // bez width/height – veličinu određuje CSS (dompdf bi inače crtao prema atributima, a ne prema CSS-u)
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $v . ' ' . $v . '" shape-rendering="crispEdges">'
        . '<rect width="' . $v . '" height="' . $v . '" fill="#fff"/><path d="' . $d . '" fill="' . e($boja) . '"/></svg>';
}

function qr_data_uri(string $tekst): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qr_svg($tekst));
}

// ---------- Slike za iskaznicu ----------
/** Fotografija člana izrezana na 4:5 (portret) kao data URI; bez GD-a original. Null ako nema slike. */
function isk_foto_data(?string $ime, int $sirina = 360): ?string
{
    if (!$ime) {
        return null;
    }
    $put = podaci('foto/' . basename($ime));
    if (!is_file($put)) {
        return null;
    }
    $info = @getimagesize($put);
    if (!$info) {
        return null;
    }
    if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring((string) file_get_contents($put)))) {
        $w = imagesx($src);
        $h = imagesy($src);
        // izrez 4:5 (visina 1,25 × širina), malo prema gore – lice je obično u gornjem dijelu
        if ($h / $w > 1.25) {
            $cw = $w;
            $ch = (int) round($w * 1.25);
            $x = 0;
            $y = (int) round(($h - $ch) * 0.3);
        } else {
            $ch = $h;
            $cw = (int) round($h / 1.25);
            $x = (int) round(($w - $cw) / 2);
            $y = 0;
        }
        $vis = (int) round($sirina * 1.25);
        $dst = imagecreatetruecolor($sirina, $vis);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $sirina, $vis, $cw, $ch);
        ob_start();
        imagejpeg($dst, null, 85);
        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }
    if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
        return null; // npr. WEBP bez GD-a – dompdf ga ne može prikazati
    }
    return 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($put));
}

function isk_logo_data(): ?string
{
    $l = postavka('Udruga.Logo');
    $put = $l ? podaci('foto/' . basename($l)) : '';
    if (!$put || !is_file($put)) {
        return null;
    }
    $info = @getimagesize($put);
    return $info ? 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($put)) : null;
}

// ---------- Iskaznica (HTML za dompdf) ----------
/**
 * Jedna iskaznica, apsolutno postavljena na ($lijevo, $gore) u mm.
 * Sve je na jednoj strani (fotografija, ime i QR) – ispis na običnom pisaču bez obostranog poravnanja.
 */
function isk_html(array $c, string $token, float $lijevo, float $gore, ?string $logo): string
{
    $foto = isk_foto_data($c['FotoDatoteka'] ?? null);
    $sek = trim((string) ($c['SekcijaNaziv'] ?? ''));
    $clanOd = !empty($c['ClanOd']) ? substr((string) $c['ClanOd'], 0, 4) : '';
    $naziv = udruga_naziv();
    $mm = fn(float $x) => rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') . 'mm';
    $tekstLijevo = $logo ? 12.5 : 3.5;
    $h = '<div class="isk" style="left:' . $mm($lijevo) . ';top:' . $mm($gore) . '">';
    $h .= '<div class="isk-traka"></div>';
    if ($logo) {
        $h .= '<img class="isk-logo" src="' . $logo . '">';
    }
    $h .= '<div class="isk-udruga" style="left:' . $mm($tekstLijevo) . ';width:' . $mm(ISK_SIRINA_MM - $tekstLijevo - 3) . '">' . e($naziv) . '</div>';
    $h .= $foto
        ? '<img class="isk-foto" src="' . $foto . '">'
        : '<div class="isk-foto isk-bez-foto"><div>' . e(inicijali($c)) . '</div></div>';
    $h .= '<div class="isk-podaci">'
        . '<div class="isk-vrsta">ČLANSKA ISKAZNICA</div>'
        . '<div class="isk-ime">' . e((string) $c['Ime']) . '</div>'
        . '<div class="isk-prezime">' . e((string) $c['Prezime']) . '</div>'
        . ($sek !== '' ? '<div class="isk-sek">' . e($sek) . '</div>' : '')
        . ($clanOd !== '' ? '<div class="isk-od">član od ' . e($clanOd) . '.</div>' : '')
        . '</div>';
    $h .= '<img class="isk-qr" src="' . qr_data_uri(isk_qr_tekst($token)) . '">';
    $h .= '<div class="isk-qr-opis">popis sudionika lova</div>';
    $h .= '<div class="isk-dno">Ne zamjenjuje lovačku iskaznicu ni oružni list.</div>';
    return $h . '</div>';
}

function isk_css(): string
{
    return '.isk { position: absolute; width: ' . ISK_SIRINA_MM . 'mm; height: ' . ISK_VISINA_MM . 'mm; border: 0.25pt solid #9a9a9a; border-radius: 3mm; overflow: hidden; background: #fff; }
        .isk-traka { position: absolute; left: 0; top: 0; width: ' . ISK_SIRINA_MM . 'mm; height: 11mm; background: #2f5d23; border-bottom: 0.8mm solid #c9a227; }
        .isk-logo { position: absolute; left: 3mm; top: 1.5mm; width: 8mm; height: 8mm; }
        .isk-udruga { position: absolute; top: 1.4mm; height: 8.4mm; overflow: hidden; color: #fff; font-weight: bold; font-size: 6.6pt; line-height: 1.22; }
        .isk-foto { position: absolute; left: 3.5mm; top: 14.5mm; width: 21mm; height: 26.25mm; border: 0.3pt solid #bbb; }
        .isk-bez-foto { background: #e9efe5; text-align: center; }
        .isk-bez-foto div { padding-top: 8mm; font-size: 16pt; font-weight: bold; color: #2f5d23; }
        .isk-podaci { position: absolute; left: 27.5mm; top: 14mm; width: 27.5mm; }
        .isk-vrsta { font-size: 5.2pt; letter-spacing: 0.4pt; color: #2f5d23; font-weight: bold; margin-bottom: 1.6mm; }
        .isk-ime { font-size: 8.6pt; line-height: 1.15; color: #222; }
        .isk-prezime { font-size: 10pt; line-height: 1.15; font-weight: bold; color: #111; margin-bottom: 1.5mm; }
        .isk-sek { font-size: 6.4pt; color: #444; line-height: 1.2; }
        .isk-od { font-size: 6pt; color: #666; margin-top: 0.6mm; }
        .isk-qr { position: absolute; left: 56.6mm; top: 13.2mm; width: 26mm; height: 26mm; }
        .isk-qr-opis { position: absolute; left: 55.6mm; top: 39.6mm; width: 28mm; text-align: center; font-size: 4.8pt; color: #666; }
        .isk-dno { position: absolute; left: 3.5mm; top: 48.6mm; width: 79mm; font-size: 4.8pt; color: #888; }';
}

/**
 * PDF iskaznica.
 *  $format 'a4'      – 10 iskaznica na A4 (2 × 5), tanki rub za rezanje; ispis na 100 % (bez prilagodbe veličine)
 *  $format 'kartica' – svaka iskaznica na svojoj stranici 85,6 × 54 mm (pisač kartica / PVC kartice)
 * $clanovi: retci Clanovi s poljem SekcijaNaziv.
 */
function isk_pdf(array $clanovi, string $format = 'a4'): string
{
    $kljucevi = isk_kljucevi(array_column($clanovi, 'Id'));
    $logo = isk_logo_data();
    $kartica = $format === 'kartica';
    $stranice = [];
    if ($kartica) {
        foreach ($clanovi as $c) {
            $stranice[] = isk_html($c, $kljucevi[(int) $c['Id']], 0, 0, $logo);
        }
    } else {
        // A4 210 × 297: 2 stupca × 5 redaka, razmak 4 mm između stupaca
        $razmak = 4.0;
        $lijevo0 = (210 - 2 * ISK_SIRINA_MM - $razmak) / 2;
        $gore0 = (297 - 5 * ISK_VISINA_MM) / 2;
        foreach (array_chunk($clanovi, 10) as $dio) {
            $s = '';
            foreach ($dio as $i => $c) {
                $s .= isk_html($c, $kljucevi[(int) $c['Id']], $lijevo0 + ($i % 2) * (ISK_SIRINA_MM + $razmak), $gore0 + intdiv($i, 2) * ISK_VISINA_MM, $logo);
            }
            $stranice[] = $s . '<div class="podnozje">' . e(udruga_naziv()) . ' · članske iskaznice · ispisati na 100 % (bez „prilagodi stranici“), izrezati po rubu</div>';
        }
    }
    $tijelo = '';
    foreach ($stranice as $i => $s) {
        $tijelo .= '<div class="stranica"' . ($i < count($stranice) - 1 ? ' style="page-break-after:always"' : '') . '>' . $s . '</div>';
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>
        @page { margin: 0; } body { margin: 0; font-family: "DejaVu Sans"; }
        .stranica { position: relative; width: ' . ($kartica ? ISK_SIRINA_MM . 'mm' : '210mm') . '; height: ' . ($kartica ? ISK_VISINA_MM . 'mm' : '296mm') . '; }
        .podnozje { position: absolute; left: 0; width: 210mm; top: 289mm; text-align: center; font-size: 6.5pt; color: #999; }
        ' . isk_css() . '
    </style></head><body>' . ($tijelo ?: '<div class="stranica"></div>') . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper($kartica ? [0, 0, ISK_SIRINA_MM / 25.4 * 72, ISK_VISINA_MM / 25.4 * 72] : 'A4', 'portrait');
    $pdf->render();
    return (string) $pdf->output();
}

// ---------- Iskaznica na zaslonu (Moja iskaznica, Popusti u trgovinama) ----------
/** HTML kartice iskaznice za zaslon mobitela. $c = član s poljem SekcijaNaziv. */
function isk_kartica_html(array $c, ?string $qr = null): string
{
    $qr ??= qr_svg(isk_qr_tekst(isk_kljuc((int) $c['Id'])));
    $foto = isk_foto_data($c['FotoDatoteka'], 300); // u stranici (radi i bez signala)
    $logo = isk_logo_data();
    return '<div class="isk-kartica mb-3" id="isk-kartica">'
        . '<div class="isk-kartica-traka">' . ($logo ? '<img src="' . e($logo) . '" alt="">' : '') . '<span>' . e(udruga_naziv()) . '</span></div>'
        . '<div class="isk-kartica-tijelo">'
        . ($foto ? '<img class="isk-k-foto" src="' . e($foto) . '" alt="">' : '<div class="isk-k-foto">' . e(inicijali($c)) . '</div>')
        . '<div><div class="isk-k-vrsta">ČLANSKA ISKAZNICA</div><div class="isk-k-ime">' . e($c['Ime']) . '</div><div class="isk-k-prezime">' . e($c['Prezime']) . '</div>'
        . ($c['SekcijaNaziv'] ? '<div class="isk-k-sek">' . e($c['SekcijaNaziv']) . '</div>' : '') . '</div>'
        . '<div class="isk-k-qr">' . $qr . '</div>'
        . '</div><div class="isk-kartica-dno">Ne zamjenjuje lovačku iskaznicu ni oružni list.</div></div>';
}
