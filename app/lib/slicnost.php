<?php
/** Prepoznavanje sličnih članova (hrvatski znakovi, zamijenjeno ime/prezime, isti telefon/e-mail/OIB). */
declare(strict_types=1);

const PRAG_SLICNOSTI = 0.85;

function slicnost_norm(?string $s): string
{
    $t = str_replace('dj', 'd', mb_strtolower(bez_dijakritika($s)));
    $t = (string) preg_replace('/[^\p{L}]+/u', ' ', $t);
    return trim((string) preg_replace('/\s+/', ' ', $t));
}

/** Zadnjih 8 znamenki broja bez pozivnog broja zemlje (385, 49, 43). */
function slicnost_telefon(?string $s): string
{
    $t = (string) preg_replace('/\D/', '', (string) $s);
    if (str_starts_with($t, '00')) {
        $t = substr($t, 2);
    }
    if (str_starts_with($t, '385')) {
        $t = substr($t, 3);
    } elseif (str_starts_with($t, '49') || str_starts_with($t, '43')) {
        $t = substr($t, 2);
    }
    $t = ltrim($t, '0');
    if (strlen($t) < 7) {
        return '';
    }
    return substr($t, -8);
}

function levenshtein_utf8(string $a, string $b): int
{
    if (!preg_match('/[^\x00-\x7F]/', $a . $b)) {
        return levenshtein($a, $b); // brzo, ugrađeno
    }
    $a =preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
    $b = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);
    $n = count($b);
    $red = range(0, $n);
    foreach ($a as $i => $ca) {
        $pret = $red[0];
        $red[0] = $i + 1;
        for ($j = 1; $j <= $n; $j++) {
            $tmp = $red[$j];
            $red[$j] = min($red[$j] + 1, $red[$j - 1] + 1, $pret + ($ca !== $b[$j - 1] ? 1 : 0));
            $pret = $tmp;
        }
    }
    return $red[$n];
}

function slicnost_imena(string $i1, string $p1, string $i2, string $p2): float
{
    $a = slicnost_norm($i1 . ' ' . $p1);
    $b = slicnost_norm($i2 . ' ' . $p2);
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if ($a === $b) {
        return 1.0;
    }
    $sa = explode(' ', $a);
    sort($sa);
    $sb = explode(' ', $b);
    sort($sb);
    $a2 = implode(' ', $sa);
    $b2 = implode(' ', $sb);
    if ($a2 === $b2) {
        return 0.99;
    }
    $sim = fn($x, $y) => 1.0 - levenshtein_utf8($x, $y) / max(mb_strlen($x), mb_strlen($y));
    return max($sim($a, $b), $sim($a2, $b2));
}

/**
 * Najsličniji članovi (najviše 3).
 * @return array<int, array{clan: array, ocjena: float, razlog: string}>
 */
function kandidati_slicnosti(string $ime, string $prezime, ?string $mob, ?string $fiks, ?string $email, ?string $oib, iterable $clanovi, ?int $osimId = null): array
{
    $tel = array_filter([slicnost_telefon($mob), slicnost_telefon($fiks)]);
    $em = mb_strtolower(trim((string) $email));
    $rez = [];
    foreach ($clanovi as $c) {
        if ($osimId !== null && (int) $c['Id'] === $osimId) {
            continue;
        }
        $s = slicnost_imena($ime, $prezime, (string) $c['Ime'], (string) $c['Prezime']);
        $razlozi = [];
        if ($s >= 1.0) {
            $razlozi[] = 'isto ime';
        } elseif ($s >= 0.99) {
            $razlozi[] = 'ime i prezime zamijenjeni';
        } elseif ($s >= PRAG_SLICNOSTI) {
            $razlozi[] = 'slično ime (' . (int) ($s * 100) . ' %)';
        }
        $ct = array_filter([slicnost_telefon($c['MobilniTelefon'] ?? null), slicnost_telefon($c['FiksniTelefon'] ?? null)]);
        $istiTel = $tel && array_intersect($tel, $ct);
        $istiMail = $em !== '' && $em === mb_strtolower(trim((string) ($c['Email'] ?? '')));
        $istiOib = trim((string) $oib) !== '' && $oib === ($c['Oib'] ?? null);
        if ($istiTel) {
            $razlozi[] = 'isti telefon';
        }
        if ($istiMail) {
            $razlozi[] = 'isti e-mail';
        }
        if ($istiOib) {
            $razlozi[] = 'isti OIB';
        }
        $ocjena = $istiOib ? 1.0 : (($s >= PRAG_SLICNOSTI && ($istiTel || $istiMail)) ? 1.0 : ($s >= PRAG_SLICNOSTI ? $s : (($istiTel || $istiMail) ? 0.7 : 0.0)));
        if ($ocjena > 0) {
            $rez[] = ['clan' => $c, 'ocjena' => $ocjena, 'razlog' => implode(', ', $razlozi)];
        }
    }
    usort($rez, fn($a, $b) => $b['ocjena'] <=> $a['ocjena']);
    return array_slice($rez, 0, 3);
}

/** Parovi mogućih duplikata u opsegu trenutnog korisnika. */
function pronadji_duplikate(): array
{
    $clanovi = redovi('SELECT c.*, s.Naziv AS SekcijaNaziv FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId WHERE ' . opseg_sql('c') . ' ORDER BY c.Id');
    $ignor = array_flip(array_filter(explode(';', (string) postavka('Duplikati.NisuIsti', ''))));
    $parovi = [];
    $n = count($clanovi);
    for ($i = 0; $i < $n; $i++) {
        $a = $clanovi[$i];
        $kasniji = array_slice($clanovi, $i + 1);
        foreach (kandidati_slicnosti($a['Ime'], $a['Prezime'], $a['MobilniTelefon'], $a['FiksniTelefon'], $a['Email'], $a['Oib'], $kasniji, (int) $a['Id']) as $p) {
            if ($p['ocjena'] >= PRAG_SLICNOSTI && !isset($ignor[$a['Id'] . '-' . $p['clan']['Id']])) {
                $parovi[] = ['a' => $a, 'b' => $p['clan'], 'ocjena' => $p['ocjena'], 'razlog' => $p['razlog']];
            }
        }
    }
    usort($parovi, fn($x, $y) => $y['ocjena'] <=> $x['ocjena']);
    return $parovi;
}

function oznaci_nisu_isti(int $a, int $b): void
{
    [$a, $b] = $a < $b ? [$a, $b] : [$b, $a];
    $s = array_filter(explode(';', (string) postavka('Duplikati.NisuIsti', '')));
    $s[] = "$a-$b";
    spremi_postavku('Duplikati.NisuIsti', implode(';', array_unique($s)));
}
