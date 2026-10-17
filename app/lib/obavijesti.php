<?php
/**
 * Obavijesti udruge: tko ima pravo „Obavijesti – slanje“ piše obavijest članovima u svom opsegu sekcija.
 * Obavezna obavijest se članu prikaže preko cijelog zaslona dok jednom ne potvrdi „Pročitao/la sam“.
 * Primatelji se spremaju pri slanju (ObavijestPrimatelji); članovi bez pristupa aplikaciji mogu se označiti „usmeno obaviješten“.
 */
declare(strict_types=1);

const OBAV_NACIN_APP = 'app';
const OBAV_NACIN_USMENO = 'usmeno';

/** Aktivni članovi u opsegu trenutnog korisnika, s oznakom ima li pristup aplikaciji. */
function obav_clanovi_u_opsegu(): array
{
    return redovi('SELECT c.Id, c.Ime, c.Prezime, c.SekcijaId, s.Naziv AS Sekcija,
            EXISTS (SELECT 1 FROM Korisnici k WHERE k.ClanId=c.Id AND k.Aktivan=1 AND k.Odobren=1) AS ImaPristup
        FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
        WHERE c.Status=' . CLAN_AKTIVAN . ' AND ' . opseg_sql('c') . ' ORDER BY c.Prezime, c.Ime');
}

/**
 * Primatelji iz obrasca: [clanIds[], opis]. Uvijek samo aktivni članovi u opsegu pošiljatelja.
 * $nacin: svi | sekcije | funkcija | odabrani
 */
function obav_odabir_primatelja(string $nacin, array $sekcije, ?int $funkcija, array $odabrani): array
{
    $clanovi = obav_clanovi_u_opsegu();
    $k = korisnik();
    switch ($nacin) {
        case 'sekcije':
            $sek = array_values(array_filter(array_map('intval', $sekcije), fn($s) => moze_sekciju($s)));
            $ids = array_column(array_filter($clanovi, fn($c) => $c['SekcijaId'] !== null && in_array((int) $c['SekcijaId'], $sek, true)), 'Id');
            $opis = $sek ? (count($sek) === 1 ? 'Sekcija ' : 'Sekcije ') . implode(', ', array_map('naziv_sekcije', $sek)) : '';
            break;
        case 'funkcija':
            $f = $funkcija ? red('SELECT * FROM Funkcije WHERE Id=?', [$funkcija]) : null;
            $s_f = $f ? array_map('intval', array_column(redovi('SELECT DISTINCT ClanId FROM ClanFunkcije WHERE FunkcijaId=? AND Do IS NULL', [$f['Id']]), 'ClanId')) : [];
            $ids = array_column(array_filter($clanovi, fn($c) => in_array((int) $c['Id'], $s_f, true)), 'Id');
            $opis = $f ? 'Funkcija: ' . $f['Naziv'] : '';
            break;
        case 'odabrani':
            $tr = array_map('intval', $odabrani);
            $ids = array_column(array_filter($clanovi, fn($c) => in_array((int) $c['Id'], $tr, true)), 'Id');
            $opis = count($ids) === 1 ? 'Odabrani član' : 'Odabrani članovi (' . count($ids) . ')';
            break;
        default:
            $ids = array_column($clanovi, 'Id');
            $opis = $k['SveSekcije'] ? 'Svi članovi' : 'Svi članovi – ' . implode(', ', array_map(fn($s) => naziv_sekcije((int) $s), $k['Sekcije']));
    }
    return [array_values(array_unique(array_map('intval', $ids))), $opis];
}

/** Spremi i pošalji obavijest. Vraća Id. Pošiljatelj (ako je među primateljima) je odmah potvrđen. */
function obav_posalji(string $naslov, string $tekst, bool $obavezno, array $clanIds, string $opis): int
{
    $k = korisnik();
    return transakcija(function () use ($k, $naslov, $tekst, $obavezno, $clanIds, $opis) {
        $sek = [];
        foreach (array_chunk($clanIds, 500) as $dio) {
            foreach (redovi('SELECT DISTINCT COALESCE(SekcijaId,0) AS S FROM Clanovi WHERE Id IN (' . implode(',', $dio) . ')') as $r) {
                $sek[(int) $r['S']] = true;
            }
        }
        ksort($sek);
        $id = umetni('Obavijesti', ['Naslov' => $naslov, 'Tekst' => $tekst, 'Obavezno' => $obavezno ? 1 : 0, 'Primatelji' => $opis,
            'Sekcije' => ',' . implode(',', array_keys($sek)) . ',', 'KreiraoId' => $k['Id'], 'KreiraoIme' => $k['Naziv'], 'Kreirano' => sada()]);
        foreach ($clanIds as $c) {
            $ja = $k['ClanId'] === $c;
            q('INSERT OR IGNORE INTO ObavijestPrimatelji (ObavijestId, ClanId, Potvrdjeno, Nacin) VALUES (?,?,?,?)',
                [$id, $c, $ja ? sada() : null, $ja ? OBAV_NACIN_APP : null]);
        }
        dnevnik('Poslana obavijest' . ($obavezno ? ' (obavezna potvrda)' : ''), 'Obavijest', $id, $naslov . ' · ' . $opis . ' · ' . count($clanIds) . ' primatelja');
        push_posalji(push_korisnici_clanova($clanIds), 'obavijesti', '📢 ' . $naslov, mb_substr(preg_replace('/\s+/u', ' ', $tekst), 0, 200)
            . ($obavezno ? ' · potrebna potvrda čitanja' : ''), 'obavijesti', ['id' => $id], 'obavijest-' . $id);
        return $id;
    });
}

/** Smije li trenutni korisnik vidjeti pregled potvrda (pošiljatelj ili pravo + cijeli opseg obavijesti). */
function obav_smije_upravljati(array $o): bool
{
    $k = korisnik();
    if (!$k || !ima(P_OBAVIJESTI)) {
        return false;
    }
    if ($k['SveSekcije'] || (int) $o['KreiraoId'] === $k['Id']) {
        return true;
    }
    $sek = array_map('intval', array_filter(explode(',', (string) $o['Sekcije']), fn($s) => $s !== ''));
    if (!$sek || in_array(0, $sek, true)) {
        return false;
    }
    return !array_diff($sek, $k['Sekcije']);
}

/** Obavijest s brojem primatelja / potvrda. */
function obav_obavijest(int $id): ?array
{
    return red('SELECT o.*, (SELECT COUNT(*) FROM ObavijestPrimatelji p WHERE p.ObavijestId=o.Id) AS Primatelja,
            (SELECT COUNT(*) FROM ObavijestPrimatelji p WHERE p.ObavijestId=o.Id AND p.Potvrdjeno IS NOT NULL) AS Potvrdjeno
        FROM Obavijesti o WHERE o.Id=?', [$id]);
}

/** Obavijesti koje je primio član (najnovije prvo), s njegovim statusom. */
function obav_moje(?int $clanId): array
{
    if (!$clanId) {
        return [];
    }
    return redovi('SELECT o.*, p.Potvrdjeno AS MojaPotvrda, p.Nacin FROM ObavijestPrimatelji p JOIN Obavijesti o ON o.Id=p.ObavijestId
        WHERE p.ClanId=? ORDER BY o.Id DESC', [$clanId]);
}

/** Obavijesti za pregled pošiljatelja (u opsegu), s brojem potvrda. */
function obav_poslane(): array
{
    if (!ima(P_OBAVIJESTI)) {
        return [];
    }
    $svi = redovi('SELECT o.*, (SELECT COUNT(*) FROM ObavijestPrimatelji p WHERE p.ObavijestId=o.Id) AS Primatelja,
            (SELECT COUNT(*) FROM ObavijestPrimatelji p WHERE p.ObavijestId=o.Id AND p.Potvrdjeno IS NOT NULL) AS Potvrdjeno
        FROM Obavijesti o ORDER BY o.Id DESC');
    return array_values(array_filter($svi, 'obav_smije_upravljati'));
}

/** Broj nepročitanih/nepotvrđenih obavijesti trenutnog člana (za izbornik). */
function obav_broj_nepotvrdjenih(): int
{
    $c = korisnik()['ClanId'] ?? null;
    return $c ? (int) vrijednost('SELECT COUNT(*) FROM ObavijestPrimatelji WHERE ClanId=? AND Potvrdjeno IS NULL', [$c]) : 0;
}

/** Najstarija obavezna obavijest koju trenutni član još nije potvrdio (prikazuje se preko cijelog zaslona). */
function obav_obavezna_na_cekanju(): ?array
{
    $c = korisnik()['ClanId'] ?? null;
    if (!$c) {
        return null;
    }
    return red('SELECT o.* FROM ObavijestPrimatelji p JOIN Obavijesti o ON o.Id=p.ObavijestId
        WHERE p.ClanId=? AND p.Potvrdjeno IS NULL AND o.Obavezno=1 ORDER BY o.Id LIMIT 1', [$c]);
}

/** Član potvrđuje (obavezna) ili je pročitao (obična) obavijest – samo jednom. */
function obav_potvrdi(int $obavijestId, int $clanId): bool
{
    $n = q('UPDATE ObavijestPrimatelji SET Potvrdjeno=?, Nacin=? WHERE ObavijestId=? AND ClanId=? AND Potvrdjeno IS NULL',
        [sada(), OBAV_NACIN_APP, $obavijestId, $clanId])->rowCount();
    return $n > 0;
}

/** Tekst obavijesti → HTML (odlomci, prijelomi redaka, poveznice). */
function obav_tekst_html(string $t): string
{
    $h = nl2br(e(trim($t)));
    return (string) preg_replace('#(https?://[^\s<]+)#', '<a href="$1" target="_blank" rel="noopener">$1</a>', $h);
}

/** Primatelji jedne obavijesti s imenom, sekcijom i statusom. */
function obav_primatelji(int $id): array
{
    return redovi('SELECT p.*, c.Ime, c.Prezime, s.Naziv AS Sekcija,
            EXISTS (SELECT 1 FROM Korisnici k WHERE k.ClanId=c.Id AND k.Aktivan=1 AND k.Odobren=1) AS ImaPristup
        FROM ObavijestPrimatelji p JOIN Clanovi c ON c.Id=p.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
        WHERE p.ObavijestId=? ORDER BY (p.Potvrdjeno IS NOT NULL), c.Prezime, c.Ime', [$id]);
}

/** PDF: obavijest i popis potvrda (dokaz da su članovi obaviješteni). */
function obav_pdf(array $o): string
{
    $primatelji = obav_primatelji((int) $o['Id']);
    usort($primatelji, fn($a, $b) => strcmp(kljuc($a['Prezime'] . ' ' . $a['Ime']), kljuc($b['Prezime'] . ' ' . $b['Ime'])));
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $mime = str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg';
        $logo = '<img src="data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $potv = count(array_filter($primatelji, fn($p) => $p['Potvrdjeno'] !== null));
    $retci = '';
    foreach ($primatelji as $n => $p) {
        $st = $p['Potvrdjeno'] === null ? '<span class="ne">nije potvrdio</span>'
            : ($p['Nacin'] === OBAV_NACIN_USMENO ? 'usmeno obaviješten ' . e(datum($p['Potvrdjeno'])) . ($p['Biljezio'] ? ' (upisao ' . e($p['Biljezio']) . ')' : '')
            : '<span class="da">' . ($o['Obavezno'] ? 'potvrdio' : 'pročitao') . '</span> ' . e(datum_vrijeme($p['Potvrdjeno'])));
        $retci .= '<tr><td>' . ($n + 1) . '.</td><td>' . e($p['Prezime'] . ' ' . $p['Ime']) . '</td><td>' . e($p['Sekcija']) . '</td><td>' . $st . '</td></tr>';
    }
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; } .zag .p { color: #666; }
        h1 { font-size: 13pt; margin: 6px 0 2px; } .meta { color: #666; margin-bottom: 8px; }
        .tekst { border: 0.5pt solid #bbb; background: #fafaf7; padding: 8px 10px; margin-bottom: 12px; line-height: 1.45; }
        table.t { width: 100%; border-collapse: collapse; } .t th { background: #e9efe5; text-align: left; }
        .t th, .t td { border: 0.5pt solid #999; padding: 3px 4px; } .da { color: #1e7b34; font-weight: bold; } .ne { color: #b02a37; }
        .podnozje { position: fixed; bottom: -8mm; left: 0; right: 0; font-size: 7.5pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div>' . (udruga_adresa() ? '<div style="color:#555">' . e(udruga_adresa()) . '</div>' : '')
        . '<div class="p">Obavijest članovima – ' . ($o['Obavezno'] ? 'potvrde čitanja' : 'pročitano') . '</div></div>
    <h1>' . e($o['Naslov']) . '</h1>
    <div class="meta">Poslao: ' . e($o['KreiraoIme']) . ' · ' . e(datum_vrijeme($o['Kreirano'])) . ' · Primatelji: ' . e($o['Primatelji']) . '<br>'
        . ($o['Obavezno'] ? 'Potvrdilo' : 'Pročitalo') . ': <b>' . $potv . ' / ' . count($primatelji) . '</b></div>
    <div class="tekst">' . nl2br(e(trim($o['Tekst']))) . '</div>
    <table class="t"><thead><tr><th style="width:8mm">Rb.</th><th>Član</th><th style="width:34mm">Sekcija</th><th style="width:70mm">Status</th></tr></thead><tbody>'
        . ($retci ?: '<tr><td colspan="4">Nema primatelja.</td></tr>') . '</tbody></table></body></html>';
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
