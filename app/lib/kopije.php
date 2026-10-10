<?php
/** Sigurnosne kopije: dnevna kopija baze, kompletna kopija (ZIP) i vraćanje. */
declare(strict_types=1);

const BROJ_DNEVNIH_KOPIJA = 30;

/** Kopija baze u podaci/kopije (VACUUM INTO). Vraća ime datoteke. */
function napravi_kopiju(string $oznaka = 'rucno'): string
{
    revir_granice_podaci(); // stara datoteka granica → baza, da bude u kopiji
    $ime = 'evidencija-' . date('Y-m-d_His') . '-' . preg_replace('/[^a-z0-9\-]/', '', $oznaka) . '.db';
    $put = podaci('kopije/' . $ime);
    try {
        q('VACUUM INTO ' . db()->quote($put));
    } catch (Throwable) {
        copy(podaci('evidencija.db'), $put); // stariji SQLite
    }
    return $ime;
}

/** Jednom dnevno automatska kopija (poziva se pri radu aplikacije), čuva se zadnjih 30. */
function dnevna_kopija(): void
{
    $oznaka = podaci('kopije/.zadnja');
    if (is_file($oznaka) && file_get_contents($oznaka) === date('Y-m-d')) {
        return;
    }
    @file_put_contents($oznaka, date('Y-m-d'));
    if (!vrijednost('SELECT 1 FROM Clanovi LIMIT 1')) {
        return;
    }
    try {
        napravi_kopiju('dnevna');
    } catch (Throwable $e) {
        error_log('Dnevna kopija nije uspjela: ' . $e->getMessage());
        return;
    }
    $sve = glob(podaci('kopije/evidencija-*-dnevna.db')) ?: [];
    rsort($sve);
    foreach (array_slice($sve, BROJ_DNEVNIH_KOPIJA) as $stara) {
        @unlink($stara);
    }
}

function popis_kopija(): array
{
    $r = [];
    foreach (glob(podaci('kopije/*.db')) ?: [] as $f) {
        $r[] = ['ime' => basename($f), 'velicina' => filesize($f), 'vrijeme' => filemtime($f)];
    }
    usort($r, fn($a, $b) => $b['vrijeme'] <=> $a['vrijeme']);
    return $r;
}

/**
 * Kompletna kopija (ZIP): evidencija.db + foto/ + kljucevi/ – isti oblik kao u Windows / Home Assistant verziji,
 * pa se može vratiti u bilo koju verziju. Vraća put do privremene ZIP datoteke.
 */
function napravi_kompletnu_kopiju(): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na poslužitelju nedostaje PHP proširenje „zip“.');
    }
    revir_granice_podaci(); // stara datoteka granica → baza, da bude u kopiji
    $tmpDb = tempnam(sys_get_temp_dir(), 'evdb');
    @unlink($tmpDb);
    try {
        q('VACUUM INTO ' . db()->quote($tmpDb));
    } catch (Throwable) {
        copy(podaci('evidencija.db'), $tmpDb);
    }
    $zipPut = tempnam(sys_get_temp_dir(), 'evzip');
    $z = new ZipArchive();
    if ($z->open($zipPut, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ZIP se ne može napraviti.');
    }
    $z->addFile($tmpDb, 'evidencija.db');
    foreach (glob(podaci('foto/*')) ?: [] as $f) {
        if (is_file($f)) {
            $z->addFile($f, 'foto/' . basename($f));
        }
    }
    foreach (glob(podaci('kljucevi/*.xml')) ?: [] as $f) {
        $z->addFile($f, 'kljucevi/' . basename($f));
    }
    $z->addFromString('kljucevi/php-kljuc.txt', base64_encode(tajni_kljuc()));
    // gotovi PDF-ovi za inspekciju – za slučaj da program ne radi (otvoriti iz ZIP-a i pokazati / ispisati)
    foreach (kopija_pdf_inspekcija() as $ime => $sadrzaj) {
        $z->addFromString('PDF-za-inspekciju/' . $ime, $sadrzaj);
    }
    $z->addFromString('PROCITAJ.txt', "Kompletna kopija – Lovačka evidencija\n" . udruga_naziv() . "\nNapravljeno: " . date('d.m.Y. H:i') . "\n\n"
        . "POVJERLJIVO: sadrži osobne podatke članova.\nVraćanje: prvo pokretanje → „Vrati iz kompletne kopije“ ili Sustav → Sigurnosne kopije.\n\n"
        . "Mapa PDF-za-inspekciju: cijela knjiga dezinfekcije po mjesecima (stalne i mobilne stanice) i izjava udruge kao gotovi PDF-ovi\n"
        . "na dan izrade kopije – ako program ne radi, otvorite ih i pokažite ili ispišite za inspekciju.\n");
    $z->close();
    @unlink($tmpDb);
    return $zipPut;
}

/** Vraća kompletnu kopiju (ZIP iz PHP, Windows ili Home Assistant verzije). Trenutni podaci se zamjenjuju. */
function vrati_kompletnu_kopiju(string $zipPut): void
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na poslužitelju nedostaje PHP proširenje „zip“.');
    }
    $z = new ZipArchive();
    if ($z->open($zipPut) !== true) {
        throw new RuntimeException('Datoteka nije ispravan ZIP.');
    }
    $db = $z->getFromName('evidencija.db');
    if ($db === false) {
        $z->close();
        throw new RuntimeException('U ZIP-u nema datoteke evidencija.db – to nije kompletna kopija.');
    }
    if (substr($db, 0, 15) !== 'SQLite format 3') {
        $z->close();
        throw new RuntimeException('evidencija.db nije ispravna baza.');
    }
    // provjera da je to baza evidencije
    $tmp = tempnam(sys_get_temp_dir(), 'evvr');
    file_put_contents($tmp, $db);
    $probna = new PDO('sqlite:' . $tmp);
    $tablice = $probna->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    $probna = null;
    foreach (['Clanovi', 'Korisnici', 'Uloge', 'RadneAkcije'] as $t) {
        if (!in_array($t, $tablice, true)) {
            @unlink($tmp);
            $z->close();
            throw new RuntimeException('Baza u kopiji nije baza evidencije (nema tablice ' . $t . ').');
        }
    }
    if (vrijednost('SELECT 1 FROM Korisnici LIMIT 1')) {
        try {
            napravi_kopiju('prije-vracanja');
        } catch (Throwable) {
        }
    }
    // fotografije i ključevi
    for ($i = 0; $i < $z->numFiles; $i++) {
        $ime = (string) $z->getNameIndex($i);
        if (preg_match('#^(foto|kljucevi)/([^/]+)$#', $ime, $m) && $m[2] !== '' && !str_starts_with($m[2], '.')) {
            $sadrzaj = $z->getFromIndex($i);
            if ($m[1] === 'kljucevi' && $m[2] === 'php-kljuc.txt') {
                $k = base64_decode(trim((string) $sadrzaj), true);
                if ($k !== false && strlen($k) === 32) {
                    file_put_contents(podaci('kljuc.php'), "<?php return '" . base64_encode($k) . "';\n");
                }
                continue;
            }
            if ($m[1] === 'foto' && !preg_match('/\.(jpe?g|png|webp|gif)$/i', $m[2])) {
                continue;
            }
            if ($m[1] === 'kljucevi' && !preg_match('/\.xml$/i', $m[2])) {
                continue;
            }
            $mapa = podaci($m[1]);
            if (!is_dir($mapa)) {
                @mkdir($mapa, 0775, true);
            }
            file_put_contents($mapa . '/' . basename($m[2]), $sadrzaj);
        }
    }
    $z->close();
    // zamjena baze
    $cilj = podaci('evidencija.db');
    foreach (['-wal', '-shm'] as $s) {
        @unlink($cilj . $s);
    }
    if (!@rename($tmp, $cilj)) {
        copy($tmp, $cilj);
        @unlink($tmp);
    }
    @unlink(podaci('kopije/.zadnja'));
}

/** Knjiga dezinfekcije (sve od početka) i izjava kao PDF – za kompletnu kopiju. Greška ne smije spriječiti kopiju. */
function kopija_pdf_inspekcija(): array
{
    $out = [];
    $dan = date('Y-m-d');
    @ini_set('memory_limit', '512M');
    @set_time_limit(600);
    try {
        foreach (['F' => 'stalne-stanice', 'M' => 'mobilne-stanice'] as $vrsta => $naziv) {
            $stanice = dez_stanice(false, $vrsta);
            if (!$stanice) {
                continue;
            }
            $f = dez_filtar(['Razdoblje' => 'Sve', 'Vrsta' => $vrsta]);
            $ids = array_map(fn($st) => (int) $st['Id'], $stanice);
            $upisi = dez_upisi($f, $ids, 100000);
            if (!$upisi && $vrsta === 'M') {
                continue;
            }
            // po mjesecu – PDF velike knjige (tisuće redaka) troši previše memorije, mjesečni su mali i brzi
            $poMjesecu = [];
            foreach ($upisi as $u) {
                $poMjesecu[substr($u['Vrijeme'], 0, 7)][] = $u;
            }
            if (!$poMjesecu) {
                $poMjesecu[date('Y-m')] = [];
            }
            ksort($poMjesecu);
            foreach ($poMjesecu as $mj => $redovi) {
                $fm = dez_filtar(['Razdoblje' => 'Slobodno', 'Od' => "$mj-01", 'Do' => date('Y-m-t', strtotime("$mj-01")), 'Vrsta' => $vrsta]);
                $liste = $vrsta === 'M' ? [] : dez_liste($fm, $ids);
                $dijelovi = array_chunk($redovi, 800) ?: [[]]; // jako puni mjeseci (skupni lovovi) u više dijelova – memorija
                foreach ($dijelovi as $i => $dio) {
                    $dod = count($dijelovi) > 1 ? '-dio' . ($i + 1) : '';
                    $out["$mj-dezinfekcija-$naziv$dod.pdf"] = dez_pdf($dio, $stanice, mjesec_godina("$mj-01") . ($dod ? ' – ' . ($i + 1) . '. dio' : '') . ' (stanje ' . date('d.m.Y.') . ')',
                        'sve sekcije', $i === count($dijelovi) - 1 ? $liste : []);
                    gc_collect_cycles();
                }
            }
        }
        $out["izjava-evidencija-dezinfekcije-$dan.pdf"] = izjava_pdf(dez_stanice(false, 'F'));
    } catch (Throwable $e) {
        $out['GRESKA-PDF.txt'] = 'PDF-ovi nisu napravljeni: ' . $e->getMessage();
    }
    return $out;
}
