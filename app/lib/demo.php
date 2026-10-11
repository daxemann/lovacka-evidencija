<?php
/**
 * DEMO način (javna probna instalacija): svi se prijavljuju istim demo računima, mogu sve isprobati, ali ništa ne mogu pokvariti.
 *  - uključuje se postavkom Demo.Aktivno = 1 (u bazi demo podataka) ili 'demo' => true u config.php,
 *  - računi i lozinke su zaključani (promjena lozinke, korisnici, uloge, samoprijava…), kao i sustavske radnje (kopije, ažuriranje, e-pošta),
 *  - e-pošta se ne šalje – sprema se kao pregled (stranica „Demo – e-pošta“),
 *  - svake noći (nakon 03:00) podaci se vraćaju na početno demo stanje (podaci/demo-izvor/).
 * Vlasnik instalacije upravlja demom preko stranice demo-vlasnik?k=<Demo.Kljuc>.
 */
declare(strict_types=1);

/** Stranice na kojima su u demo načinu zabranjene sve izmjene (POST). */
const DEMO_ZAKLJUCANO = [
    'promjena-lozinke', 'nova-lozinka', 'zaboravljena-lozinka', 'samoprijava', 'pozivnica', 'postavljanje', 'vrati',
    'sustav/korisnici', 'sustav/uloge', 'sustav/samoprijava', 'sustav/kopije', 'sustav/azuriranje', 'sustav/postavke',
    'sustav/udruga', 'sustav/sekcije', 'revir/postavke', 'dezinfekcija/postavke',
];
const DEMO_SATI_RESETA = 3;
const DEMO_MAX_POSTE = 40;

function je_demo(): bool
{
    static $d = null;
    if ($d === null) {
        $d = (bool) cfg('demo', false);
        if (!$d) {
            try {
                $d = postavka('Demo.Aktivno', '0') === '1';
            } catch (Throwable) {
                $d = false;
            }
        }
    }
    return $d;
}
/** Je li trenutna stranica u demo načinu samo za pregled (za sivi prikaz obrazaca). */
function demo_samo_pregled(string $p): bool
{
    return je_demo() && (in_array($p, DEMO_ZAKLJUCANO, true) || ($p === 'clanovi/uredi' && ($_GET['kartica'] ?? '') === 'racun'));
}
/**
 * Tajni ključ vlasnika (stranica demo-vlasnik). Čuva se u datoteci podaci/demo-kljuc.txt – izvan baze, pa ga noćni povratak
 * ni kompletna kopija ne mijenjaju. Prvi put se preuzima iz postavke Demo.Kljuc (iz demo podataka) – vlasnik ga odmah zamijeni novim.
 */
function demo_kljuc(): string
{
    $dat = podaci('demo-kljuc.txt');
    $k = trim((string) @file_get_contents($dat));
    if ($k === '' && ($p = (string) postavka('Demo.Kljuc', '')) !== '') {
        @file_put_contents($dat, $p);
        $k = $p;
    }
    return $k;
}
function demo_lozinka(): string
{
    return (string) postavka('Demo.Lozinka', 'demo');
}
/** Demo računi za stranicu prijave: [['k' => korisničko ime, 'opis' => …], …] */
function demo_racuni(): array
{
    $r = json_decode((string) postavka('Demo.Racuni', '[]'), true);
    return is_array($r) ? $r : [];
}
function demo_poruka_zakljucano(): string
{
    return '🔒 <b>Demo verzija</b> – ovo se ovdje ne može mijenjati (lozinke, korisnici, uloge i postavke sustava su zaključani). U vašoj instalaciji radi normalno.';
}
/** Poziva se iz index.php prije stranice: zabrana izmjena na zaključanim stranicama. */
function demo_cuvar(string $p): void
{
    if (!je_demo() || !je_post() || !in_array($p, DEMO_ZAKLJUCANO, true)) {
        return;
    }
    poruka(demo_poruka_zakljucano(), 'warning');
    $natrag = in_array($p, ['postavljanje', 'vrati', 'samoprijava', 'pozivnica', 'nova-lozinka', 'zaboravljena-lozinka'], true) ? 'prijava' : $p;
    preusmjeri(korisnik() ? $natrag : 'prijava');
}

// ---------- početno stanje i noćni povratak ----------
function demo_izvor(string $pod = ''): string
{
    return podaci('demo-izvor' . ($pod !== '' ? '/' . $pod : ''));
}
/** Sprema trenutno stanje (baza + fotografije) kao početno demo stanje. */
function demo_spremi_izvor(): void
{
    $m = demo_izvor();
    if (!is_dir($m . '/foto')) {
        @mkdir($m . '/foto', 0770, true);
    }
    $cilj = $m . '/evidencija.db';
    @unlink($cilj);
    try {
        q('VACUUM INTO ' . db()->quote($cilj));
    } catch (Throwable) {
        // starija SQLite bez VACUUM INTO: kopija datoteke nakon prebacivanja WAL-a u bazu
        try {
            q('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
        }
        copy(podaci('evidencija.db'), $cilj);
    }
    foreach (glob($m . '/foto/*') ?: [] as $f) {
        @unlink($f);
    }
    foreach (glob(podaci('foto') . '/*') ?: [] as $f) {
        if (is_file($f)) {
            @copy($f, $m . '/foto/' . basename($f));
        }
    }
    @file_put_contents(podaci('demo-reset.txt'), date('Y-m-d H:i:s'));
    @file_put_contents($m . '/datum.txt', date('Y-m-d'));
}
/**
 * Demo podaci "ne stare": svi datumi u bazi pomiču se za broj dana od spremanja početnog stanja
 * (radne akcije "prošli tjedan", termini u kalendaru i dalje nadolazeći, zauzete čeke i dalje zauzete…).
 */
function demo_pomakni_datume(string $baza, int $dana): void
{
    if ($dana === 0) {
        return;
    }
    $pdo = new PDO('sqlite:' . $baza, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA journal_mode = DELETE; PRAGMA foreign_keys = OFF;');
    $pdo->beginTransaction();
    $pom = ($dana > 0 ? '+' : '') . $dana . ' days';
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name NOT IN ('Postavke','Dnevnik')")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        foreach ($pdo->query('PRAGMA table_info("' . $t . '")')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            if (stripos((string) $c['type'], 'TEXT') === false) {
                continue;
            }
            $k = '"' . $c['name'] . '"';
            $pdo->exec("UPDATE \"$t\" SET $k = CASE WHEN length($k)=10 THEN date($k, '$pom') ELSE strftime('%Y-%m-%d %H:%M:%S', $k, '$pom') END
                WHERE $k GLOB '[12][0-9][0-9][0-9]-[01][0-9]-[0-3][0-9]*' AND length($k) IN (10, 19)");
        }
    }
    $pdo->commit();
    $pdo = null;
}
/** Vraća početno demo stanje. Nakon toga treba preusmjeriti (trenutna veza na bazu pokazuje na staru datoteku). */
function demo_vrati_izvor(): bool
{
    $izvor = demo_izvor('evidencija.db');
    if (!is_file($izvor)) {
        return false;
    }
    $cilj = podaci('evidencija.db');
    $tmp = $cilj . '.demo-tmp';
    if (!@copy($izvor, $tmp)) {
        return false;
    }
    foreach (['-wal', '-shm'] as $s) {
        @unlink($cilj . $s);
    }
    $izvorDan = strtotime((string) @file_get_contents(demo_izvor('datum.txt')));
    if ($izvorDan) {
        try {
            demo_pomakni_datume($tmp, (int) round((strtotime(date('Y-m-d')) - $izvorDan) / 86400));
        } catch (Throwable $e) {
            error_log('demo pomak datuma: ' . $e->getMessage());
        }
    }
    if (!@rename($tmp, $cilj)) {
        @copy($tmp, $cilj);
        @unlink($tmp);
    }
    foreach (glob(podaci('foto') . '/*') ?: [] as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    if (!is_dir(podaci('foto'))) {
        @mkdir(podaci('foto'), 0770, true);
    }
    foreach (glob(demo_izvor('foto') . '/*') ?: [] as $f) {
        @copy($f, podaci('foto') . '/' . basename($f));
    }
    foreach (glob(demo_posta_mapa() . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @file_put_contents(podaci('demo-reset.txt'), date('Y-m-d H:i:s'));
    return true;
}
/** Jednom dnevno (prvi zahtjev nakon 03:00) vraća početno stanje. Prvi put sprema početno stanje. */
function demo_odrzavanje(): void
{
    if (!je_demo()) {
        return;
    }
    if (!is_file(demo_izvor('evidencija.db'))) {
        demo_spremi_izvor();
        return;
    }
    $zadnji = strtotime((string) @file_get_contents(podaci('demo-reset.txt'))) ?: 0;
    $granica = strtotime(date('Y-m-d') . sprintf(' %02d:00:00', DEMO_SATI_RESETA));
    if (time() < $granica) {
        $granica -= 86400;
    }
    if ($zadnji >= $granica) {
        return;
    }
    $brava = @fopen(podaci('demo-reset.lock'), 'c');
    if ($brava && flock($brava, LOCK_EX | LOCK_NB)) {
        if (demo_vrati_izvor()) {
            odjavi();
            header('Location: ' . url('prijava', ['demo-reset' => 1]));
            exit;
        }
    }
}

// ---------- e-pošta: pregled umjesto slanja ----------
function demo_posta_mapa(): string
{
    $m = podaci('demo-posta');
    if (!is_dir($m)) {
        @mkdir($m, 0770, true);
    }
    return $m;
}
/** Umjesto slanja sprema poruku za pregled. */
function demo_spremi_mail(array $prima, string $naslov, string $tijelo, bool $html, array $privitci): void
{
    $id = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $pr = [];
    foreach ($privitci as $ime => $sadrzaj) {
        $dat = $id . '-' . count($pr) . '.bin';
        file_put_contents(demo_posta_mapa() . '/' . $dat, $sadrzaj);
        $pr[] = ['ime' => (string) $ime, 'dat' => $dat, 'vel' => strlen($sadrzaj)];
    }
    $k = korisnik();
    file_put_contents(demo_posta_mapa() . '/' . $id . '.json', json_encode([
        'id' => $id, 'vrijeme' => sada(), 'prima' => array_values(array_map('trim', $prima)), 'naslov' => $naslov,
        'tijelo' => $tijelo, 'html' => $html, 'privitci' => $pr, 'tko' => $k['Naziv'] ?? '',
    ], JSON_UNESCAPED_UNICODE));
    // samo zadnjih DEMO_MAX_POSTE poruka
    $sve = glob(demo_posta_mapa() . '/*.json') ?: [];
    rsort($sve);
    foreach (array_slice($sve, DEMO_MAX_POSTE) as $f) {
        $j = json_decode((string) file_get_contents($f), true);
        foreach ((array) ($j['privitci'] ?? []) as $p) {
            @unlink(demo_posta_mapa() . '/' . basename((string) $p['dat']));
        }
        @unlink($f);
    }
    static $najavljeno = false;
    if (!$najavljeno) {
        $najavljeno = true;
        poruka('📧 <b>Demo:</b> e-pošta nije stvarno poslana. <a href="' . e(url('demo-posta', ['id' => $id])) . '">Pogledajte kako bi izgledala</a>.', 'info');
    }
}
function demo_mailovi(): array
{
    $sve = glob(demo_posta_mapa() . '/*.json') ?: [];
    rsort($sve);
    return array_values(array_filter(array_map(fn($f) => json_decode((string) file_get_contents($f), true), $sve)));
}
