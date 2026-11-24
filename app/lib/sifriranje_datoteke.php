<?php
/**
 * Šifriranje datoteka (2. faza): fotografije osobnih dokumenata i potpisi u podaci/foto.
 *
 * Sadržaj datoteke je isti zapis kao kod stupaca – sifriraj() (AES-256-GCM, ključ = tajni_kljuc()), početak „php:“.
 * Ime i nastavak datoteke ostaju isti. Nešifrirane datoteke čitaju se normalno → prebacivanje ide u obrocima, prekid je bezopasan.
 * Čitanje i pisanje tih datoteka ide samo preko foto_sadrzaj() / foto_zapisi() / foto_kopiraj().
 * Stanje (Postavke „Sifriranje.Datoteke“): '' isključeno · 'sifriram' · 'ukljuceno' · 'desifriram'.
 * Uključuje se zasebno, tek kad je uključeno šifriranje podataka u bazi.
 */
declare(strict_types=1);

/** Početak imena datoteka koje se šifriraju: Moji dokumenti, osobni potpisi, potpisi na skupnom lovu, stari potpisi dezinfekcije. */
const SIFRIRANE_DATOTEKE = ['cdok-', 'potpis-', 'glpotpis-', 'dezpotpis-'];

// ---------- Stanje ----------
function datoteke_stanje(): string
{
    return (string) postavka('Sifriranje.Datoteke');
}
function datoteke_u_tijeku(): bool
{
    return in_array(datoteke_stanje(), ['sifriram', 'desifriram'], true);
}
/** Spremaju li se nove datoteke šifrirano? */
function datoteke_sifriraj_nove(): bool
{
    return in_array(datoteke_stanje(), ['sifriram', 'ukljuceno'], true);
}
function datoteke_postavi_stanje(string $stanje): void
{
    spremi_postavku('Sifriranje.Datoteke', $stanje === '' ? null : $stanje);
}

// ---------- Čitanje i pisanje ----------
/** Je li to vrsta datoteke koja se šifrira (po imenu)? */
function foto_osjetljiva(string $ime): bool
{
    foreach (SIFRIRANE_DATOTEKE as $p) {
        if (str_starts_with($ime, $p)) {
            return true;
        }
    }
    return false;
}
/** Je li datoteka na disku šifrirana (samo prva 4 bajta)? */
function foto_sifrirana(string $put): bool
{
    return @file_get_contents($put, false, null, 0, strlen(SIFRA_PREFIKS)) === SIFRA_PREFIKS;
}
/** Sadržaj datoteke iz podaci/foto, po potrebi dešifriran. null = ne postoji ili se ne može dešifrirati. */
function foto_sadrzaj(?string $ime): ?string
{
    $ime = basename((string) $ime);
    $put = podaci('foto/' . $ime);
    if ($ime === '' || !is_file($put)) {
        return null;
    }
    $s = file_get_contents($put);
    if ($s === false || !foto_osjetljiva($ime) || !str_starts_with($s, SIFRA_PREFIKS)) {
        return $s === false ? null : $s;
    }
    $cisto = desifriraj($s);
    if ($cisto === null) {
        error_log('Datoteka se ne može dešifrirati (drugi ključ?): ' . $ime);
    }
    return $cisto;
}
/** Slika kao data-URI (za PDF i prikaz potpisa) ili null. */
function foto_datauri(?string $ime, string $tip = 'image/png'): ?string
{
    $s = foto_sadrzaj($ime);
    return $s === null ? null : 'data:' . $tip . ';base64,' . base64_encode($s);
}
/** Zapisuje datoteku u podaci/foto – osjetljivu šifrira ako je šifriranje uključeno. */
function foto_zapisi(string $ime, string $sadrzaj): bool
{
    $ime = basename($ime);
    $sifriraj = foto_osjetljiva($ime) && datoteke_sifriraj_nove();
    return foto_zamijeni(podaci('foto/' . $ime), $sifriraj ? foto_sifriraj($sadrzaj) : $sadrzaj);
}
/** Kopija pod novim imenom (npr. potpis sa skupnog lova → trajni potpis člana). */
function foto_kopiraj(string $izvor, string $novo): bool
{
    $s = foto_sadrzaj($izvor);
    return $s !== null && foto_zapisi($novo, $s);
}
/** Šifrira i odmah provjerava – bolje greška nego datoteka koja se ne može otvoriti. */
function foto_sifriraj(string $sadrzaj): string
{
    $s = sifriraj($sadrzaj);
    if (desifriraj($s) !== $sadrzaj) {
        throw new RuntimeException('Datoteka se ne može šifrirati.');
    }
    return $s;
}
/** Zapis preko privremene datoteke i preimenovanja – prekid ne ostavlja polovičnu datoteku. */
function foto_zamijeni(string $put, string $sadrzaj): bool
{
    $tmp = dirname($put) . '/.tmp-' . bin2hex(random_bytes(6)); // skrivena – ne ulazi u kopiju ni u popise
    if (@file_put_contents($tmp, $sadrzaj) !== strlen($sadrzaj) || !@rename($tmp, $put)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

// ---------- Prebacivanje u obrocima ----------
/** Datoteke koje još treba obraditi u smjeru $smjer (imena). */
function datoteke_preostale(string $smjer): array
{
    $imena = [];
    foreach (SIFRIRANE_DATOTEKE as $p) {
        foreach (glob(podaci('foto/' . $p . '*')) ?: [] as $f) {
            if (is_file($f) && foto_sifrirana($f) === ($smjer === 'desifriram')) {
                $imena[] = basename($f);
            }
        }
    }
    return $imena;
}
/** (De)šifrira jednu postojeću datoteku. false = nije uspjelo (datoteka ostaje kakva jest). */
function datoteke_prebaci(string $ime, string $smjer): bool
{
    $put = podaci('foto/' . $ime);
    try {
        $cisto = foto_sadrzaj($ime);
        if ($cisto === null) {
            return false;
        }
        $novo = $smjer === 'sifriram' ? foto_sifriraj($cisto) : $cisto;
        return is_file($put) && foto_zamijeni($put, $novo); // u međuvremenu obrisana se ne vraća
    } catch (Throwable $e) {
        error_log('Šifriranje datoteke ' . $ime . ' nije uspjelo: ' . $e->getMessage());
        return false;
    }
}
/**
 * Obrađuje datoteke najviše $sekundi. Jedan zahtjev odjednom (brava).
 * Vraća ['preostalo' => koliko još treba, 'neuspjelo' => koliko ne ide, 'zauzeto' => drugi zahtjev upravo radi].
 */
function datoteke_obrok(string $smjer, float $sekundi = SIFRIRANJE_OBROK_SEKUNDI): array
{
    $brava = @fopen(podaci('foto/.sif-brava'), 'c');
    if ($brava && !flock($brava, LOCK_EX | LOCK_NB)) {
        fclose($brava);
        return ['preostalo' => count(datoteke_preostale($smjer)), 'neuspjelo' => 0, 'zauzeto' => true];
    }
    $do = microtime(true) + $sekundi;
    $neuspjelo = 0;
    $imena = datoteke_preostale($smjer);
    $preostalo = count($imena);
    foreach ($imena as $ime) {
        datoteke_prebaci($ime, $smjer) || $neuspjelo++;
        $preostalo--;
        if (microtime(true) >= $do) {
            break;
        }
    }
    if ($brava) {
        flock($brava, LOCK_UN);
        fclose($brava);
    }
    return ['preostalo' => $preostalo, 'neuspjelo' => $neuspjelo, 'zauzeto' => false];
}
/** Nastavi prebacivanje datoteka (poziva traka napretka). Vraća ['stanje' => …, 'preostalo' => …]. */
function datoteke_nastavi(): array
{
    $smjer = datoteke_stanje();
    if (!in_array($smjer, ['sifriram', 'desifriram'], true)) {
        return ['stanje' => $smjer, 'preostalo' => 0];
    }
    @set_time_limit(60);
    $r = datoteke_obrok($smjer);
    if ($r['zauzeto']) {
        usleep(500000);
        return ['stanje' => $smjer, 'preostalo' => $r['preostalo']];
    }
    if ($r['preostalo'] === 0) {
        datoteke_zavrsi($smjer, $r['neuspjelo']);
    }
    return ['stanje' => datoteke_stanje(), 'preostalo' => $r['preostalo']];
}
function datoteke_zavrsi(string $smjer, int $neuspjelo): void
{
    datoteke_postavi_stanje($smjer === 'sifriram' ? 'ukljuceno' : '');
    dnevnik('Šifriranje datoteka: ' . ($smjer === 'sifriram' ? 'uključeno' : 'isključeno'), null, null,
        $neuspjelo ? 'nije uspjelo za ' . $neuspjelo . ' datoteka' : 'sve datoteke');
}

// ---------- Uključivanje / isključivanje ----------
/** Zašto se šifriranje datoteka (još) ne može uključiti – '' = može. */
function datoteke_prepreka(): string
{
    if (je_demo()) {
        return 'U demo verziji šifriranje se ne može mijenjati.';
    }
    if (datoteke_stanje() !== '') {
        return 'Šifriranje datoteka je već uključeno ili je prebacivanje u tijeku.';
    }
    if (!sifriranje_ukljuceno()) {
        return 'Prvo uključite šifriranje podataka članova.';
    }
    return sifriranje_prepreka_kopija();
}
function datoteke_ukljuci(): void
{
    if (($p = datoteke_prepreka()) !== '') {
        throw new RuntimeException($p);
    }
    datoteke_postavi_stanje('sifriram');
    dnevnik('Šifriranje datoteka: pokrenuto');
}

// ---------- Dnevno ----------
/** Dok je uključeno: datoteke koje su ostale nešifrirane (vraćena starija kopija, drugi poslužitelj) šifriraju se naknadno. */
function datoteke_sifriraj_ostatke(): void
{
    if (datoteke_stanje() !== 'ukljuceno') {
        return;
    }
    $oznaka = podaci('foto/.sif-provjereno');
    if (is_file($oznaka) && file_get_contents($oznaka) === date('Y-m-d')) {
        return;
    }
    $prije = count(datoteke_preostale('sifriram'));
    $r = $prije ? datoteke_obrok('sifriram', 2) : ['preostalo' => 0, 'neuspjelo' => 0, 'zauzeto' => false];
    if ($r['zauzeto']) {
        return;
    }
    if ($r['preostalo'] === 0) { // nije sve stiglo → nastavlja sljedeći zahtjev
        @file_put_contents($oznaka, date('Y-m-d'));
    }
    $obradjeno = $prije - $r['preostalo'] - $r['neuspjelo'];
    if ($obradjeno > 0) {
        dnevnik('Šifriranje: naknadno šifrirane datoteke', null, null, (string) $obradjeno, ['Id' => null, 'KorisnickoIme' => 'sustav']);
    }
}
