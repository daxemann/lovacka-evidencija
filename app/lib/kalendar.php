<?php
/** Kalendar termina: vidljivost po sekcijama, odgovori (dolazim / ne dolazim), iCal (.ics). */
declare(strict_types=1);

const VRSTE_DOGADJAJA_ZADANO = [
    ['naziv' => 'Radna akcija', 'boja' => '#3d6b2f', 'akcija' => true],
    ['naziv' => 'Sastanak / Skupština', 'boja' => '#1f5fa8', 'akcija' => false],
    ['naziv' => 'Zajednički lov', 'boja' => '#a8551f', 'akcija' => false],
    ['naziv' => 'Gađanje', 'boja' => '#7a3fa8', 'akcija' => false],
    ['naziv' => 'Druženje', 'boja' => '#c28a00', 'akcija' => false],
    ['naziv' => 'Ostalo', 'boja' => '#6c757d', 'akcija' => false],
];

/** [[naziv, boja, akcija(bool)], …] – uređuje se u Sustav → Kalendar. */
function vrste_dogadjaja(): array
{
    $j = postavka('Kalendar.Vrste');
    $v = $j ? json_decode($j, true) : null;
    return is_array($v) && $v ? $v : VRSTE_DOGADJAJA_ZADANO;
}
function vrsta_dogadjaja(string $naziv): array
{
    foreach (vrste_dogadjaja() as $v) {
        if ($v['naziv'] === $naziv) {
            return $v;
        }
    }
    return ['naziv' => $naziv, 'boja' => '#6c757d', 'akcija' => false];
}

/**
 * Opseg vidljivosti za korisnika: ['sve' => bool, 'sekcije' => int[]].
 * Član vidi termine za cijelu udrugu i za svoju sekciju; uloge dodaju svoje sekcije (ili sve).
 */
function kalendar_opseg(?int $korisnikId = null): array
{
    if ($korisnikId === null) {
        $k = korisnik();
        if (!$k) {
            return ['sve' => false, 'sekcije' => []];
        }
        $sek = $k['Sekcije'];
        $sve = $k['SveSekcije'];
        $clanId = $k['ClanId'];
    } else {
        $r = red('SELECT ClanId FROM Korisnici WHERE Id=? AND Aktivan=1 AND Odobren=1', [$korisnikId]);
        if (!$r) {
            return ['sve' => false, 'sekcije' => []];
        }
        $clanId = $r['ClanId'] !== null ? (int) $r['ClanId'] : null;
        $sve = false;
        $sek = [];
        foreach (redovi('SELECT SekcijaId FROM KorisnikUloge WHERE KorisnikId=?', [$korisnikId]) as $u) {
            if ($u['SekcijaId'] === null) {
                $sve = true;
            } else {
                $sek[] = (int) $u['SekcijaId'];
            }
        }
    }
    if ($clanId) {
        $s = vrijednost('SELECT SekcijaId FROM Clanovi WHERE Id=?', [$clanId]);
        if ($s !== null) {
            $sek[] = (int) $s;
        }
    }
    return ['sve' => $sve, 'sekcije' => array_values(array_unique(array_map('intval', $sek)))];
}
function kalendar_uvjet(array $op, string $a = 'd'): string
{
    if ($op['sve']) {
        return '1=1';
    }
    $u = "$a.ZaSve=1";
    if ($op['sekcije']) {
        $u .= " OR EXISTS (SELECT 1 FROM DogadjajSekcije ds WHERE ds.DogadjajId=$a.Id AND ds.SekcijaId IN (" . implode(',', $op['sekcije']) . '))';
    }
    return "($u)";
}

/** Termini u razdoblju [od, do) vidljivi korisniku, s nazivima sekcija i brojem dolazaka. */
function dogadjaji(string $od, ?string $do, ?array $op = null, int $limit = 500): array
{
    $op ??= kalendar_opseg();
    $p = [$od];
    $w = 'COALESCE(d.Kraj, d.Pocetak) >= ?';
    if ($do !== null) {
        $w .= ' AND d.Pocetak < ?';
        $p[] = $do;
    }
    $redovi = redovi('SELECT d.*, (SELECT COUNT(*) FROM DogadjajOdgovori o WHERE o.DogadjajId=d.Id AND o.Dolazi=1) AS Dolazi
        FROM Dogadjaji d WHERE ' . $w . ' AND ' . kalendar_uvjet($op) . ' ORDER BY d.Pocetak LIMIT ' . $limit, $p);
    return dopuni_sekcije($redovi);
}
function dopuni_sekcije(array $redovi): array
{
    if (!$redovi) {
        return [];
    }
    $ids = implode(',', array_map(fn($r) => (int) $r['Id'], $redovi));
    $sek = [];
    foreach (redovi("SELECT ds.DogadjajId, s.Id, s.Naziv FROM DogadjajSekcije ds JOIN Sekcije s ON s.Id=ds.SekcijaId WHERE ds.DogadjajId IN ($ids) ORDER BY s.Redoslijed, s.Naziv") as $s) {
        $sek[(int) $s['DogadjajId']][(int) $s['Id']] = $s['Naziv'];
    }
    foreach ($redovi as &$r) {
        $r['Sekcije'] = $sek[(int) $r['Id']] ?? [];
    }
    return $redovi;
}
function dogadjaj(int $id): ?array
{
    $d = red('SELECT d.* FROM Dogadjaji d WHERE d.Id=? AND ' . kalendar_uvjet(kalendar_opseg()), [$id]);
    return $d ? dopuni_sekcije([$d])[0] : null;
}

/** Smije li trenutni korisnik uređivati termin (pravo Kalendar + sve sekcije termina u opsegu). */
function moze_uredjivati_dogadjaj(?array $d = null): bool
{
    if (!ima(P_KALENDAR)) {
        return false;
    }
    $k = korisnik();
    if ($k['SveSekcije'] || $d === null) {
        return true;
    }
    if ($d['ZaSve']) {
        return false;
    }
    foreach (array_keys($d['Sekcije']) as $s) {
        if (!in_array((int) $s, $k['Sekcije'], true)) {
            return false;
        }
    }
    return (bool) $d['Sekcije'];
}

function oznaka_vremena(array $d): string
{
    $p = strtotime($d['Pocetak']);
    $k = $d['Kraj'] ? strtotime($d['Kraj']) : null;
    $dani = ['ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub'];
    $t = $dani[(int) date('w', $p)] . ' ' . date('d.m.', $p);
    if ($d['CijeliDan']) {
        if ($k && date('Y-m-d', $k) !== date('Y-m-d', $p)) {
            $t .= ' – ' . $dani[(int) date('w', $k)] . ' ' . date('d.m.', $k);
        }
        return $t;
    }
    $t .= ' u ' . date('H:i', $p);
    if ($k) {
        $t .= date('Y-m-d', $k) === date('Y-m-d', $p) ? '–' . date('H:i', $k) : ' – ' . date('d.m. H:i', $k);
    }
    return $t;
}
function karta_url(?string $mjesto): ?string
{
    $m = trim((string) $mjesto);
    if ($m === '') {
        return null;
    }
    return preg_match('#^https?://#i', $m) ? $m : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($m);
}

/** Tekst za dijeljenje termina (WhatsApp / Viber). */
function tekst_dogadjaja(array $d): string
{
    $t = "📅 {$d['Naslov']}\n" . oznaka_vremena($d);
    if ($d['Mjesto']) {
        $t .= "\n📍 " . (preg_match('#^https?://#i', $d['Mjesto']) ? $d['Mjesto'] : $d['Mjesto']);
    }
    if ($d['Opis']) {
        $t .= "\n\n" . $d['Opis'];
    }
    return $t . "\n\n" . apsolutni_url('kalendar/termin', ['id' => $d['Id']]);
}

// ---------- iCal ----------
function kalendar_token(int $korisnikId, bool $novi = false): string
{
    $k = 'Kalendar.Token.K' . $korisnikId;
    $t = postavka($k);
    if (!$t || $novi) {
        $t = bin2hex(random_bytes(16));
        spremi_postavku($k, $t);
    }
    return $t;
}
function korisnik_po_kalendar_tokenu(string $t): ?int
{
    if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
        return null;
    }
    $id = vrijednost("SELECT substr(Kljuc, 17) FROM Postavke WHERE Kljuc LIKE 'Kalendar.Token.K%' AND Vrijednost=?", [$t]);
    return $id ? (int) $id : null;
}
function ics_tekst(string $s): string
{
    return str_replace(["\\", "\r\n", "\n", ',', ';'], ["\\\\", '\\n', '\\n', '\\,', '\\;'], $s);
}
function ics_red(string $r): string
{
    $out = '';
    while (strlen($r) > 74) {
        $cut = 74;
        while ($cut > 0 && (ord($r[$cut]) & 0xC0) === 0x80) {
            $cut--;
        }
        $out .= substr($r, 0, $cut) . "\r\n ";
        $r = substr($r, $cut);
    }
    return $out . $r . "\r\n";
}
function ics(array $dogadjaji, string $naziv): string
{
    $host = parse_url(javna_adresa(), PHP_URL_HOST) ?: 'evidencija';
    $o = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Lovacka evidencija//HR\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
        . ics_red('X-WR-CALNAME:' . ics_tekst($naziv)) . "X-WR-TIMEZONE:Europe/Zagreb\r\nREFRESH-INTERVAL;VALUE=DURATION:PT6H\r\n";
    $utc = new DateTimeZone('UTC');
    foreach ($dogadjaji as $d) {
        $p = new DateTimeImmutable($d['Pocetak']);
        $k = $d['Kraj'] ? new DateTimeImmutable($d['Kraj']) : null;
        $o .= "BEGIN:VEVENT\r\nUID:dogadjaj-{$d['Id']}@{$host}\r\n";
        $o .= 'DTSTAMP:' . (new DateTimeImmutable($d['Azurirano'] ?: $d['Kreirano']))->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n";
        if ($d['CijeliDan']) {
            $o .= 'DTSTART;VALUE=DATE:' . $p->format('Ymd') . "\r\n";
            $o .= 'DTEND;VALUE=DATE:' . ($k ?? $p)->modify('+1 day')->format('Ymd') . "\r\n";
        } else {
            $o .= 'DTSTART:' . $p->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n";
            $o .= 'DTEND:' . ($k ?? $p->modify('+2 hours'))->setTimezone($utc)->format('Ymd\THis\Z') . "\r\n";
        }
        $o .= ics_red('SUMMARY:' . ics_tekst($d['Naslov']));
        if ($d['Mjesto']) {
            $o .= ics_red('LOCATION:' . ics_tekst($d['Mjesto']));
        }
        $opis = trim(($d['Vrsta'] ? $d['Vrsta'] . "\n" : '') . (string) $d['Opis']);
        if ($opis !== '') {
            $o .= ics_red('DESCRIPTION:' . ics_tekst($opis));
        }
        $o .= ics_red('URL:' . apsolutni_url('kalendar/termin', ['id' => $d['Id']]));
        $o .= "END:VEVENT\r\n";
    }
    return $o . "END:VCALENDAR\r\n";
}
