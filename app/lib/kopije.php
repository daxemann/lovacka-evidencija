<?php
/** Sigurnosne kopije: dnevna kopija baze, kompletna kopija (ZIP) i vraćanje. */
declare(strict_types=1);

const BROJ_DNEVNIH_KOPIJA = 30;

/** Kopija baze u podaci/kopije (VACUUM INTO). Vraća ime datoteke. */
function napravi_kopiju(string $oznaka = 'rucno'): string
{
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
    $z->addFromString('PROCITAJ.txt', "Kompletna kopija – Lovačka evidencija\n" . udruga_naziv() . "\nNapravljeno: " . date('d.m.Y. H:i') . "\n\n"
        . "POVJERLJIVO: sadrži osobne podatke članova.\nVraćanje: prvo pokretanje → „Vrati iz kompletne kopije“ ili Sustav → Sigurnosne kopije.\n");
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
