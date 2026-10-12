<?php
/** Statut i pravilnici: dokumenti udruge (tekst, PDF, slike). Uređuje administrator, članovi samo čitaju. */
declare(strict_types=1);

const DOK_MAX_PDF_MB = 20;

function dok_datoteke(int $id): array
{
    return redovi('SELECT * FROM DokumentDatoteke WHERE DokumentId=? ORDER BY Redoslijed, Id', [$id]);
}

/** Sprema učitani PDF ili sliku u podaci/foto (prefiks dok-, da ide u kompletnu kopiju). Vraća [datoteka, vrsta]. */
function dok_spremi(array $f, int $dokId): array
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Datoteka nije učitana.' . upload_greska($f));
    }
    $glava = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
    if ($glava === '%PDF-') {
        if (filesize($f['tmp_name']) > DOK_MAX_PDF_MB * 1024 * 1024) {
            throw new RuntimeException('PDF je veći od ' . DOK_MAX_PDF_MB . ' MB.');
        }
        $ime = 'dok-' . $dokId . '-' . bin2hex(random_bytes(6)) . '.pdf';
        $cilj = podaci('foto/' . $ime);
        $ok = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], $cilj) : copy($f['tmp_name'], $cilj);
        if (!$ok) {
            throw new RuntimeException('PDF se ne može spremiti (prava na mapu podaci/foto).');
        }
        return [$ime, 'pdf'];
    }
    // slika (npr. fotografija papira) – smanjena na 2400 px da tekst ostane čitljiv
    return [spremi_sliku($f, 'dok-' . $dokId . '-', 2400), 'slika'];
}

function dok_obrisi_datoteku(array $d): void
{
    @unlink(podaci('foto/' . basename((string) $d['Datoteka'])));
}

function dok_naziv_datoteke(string $ime): string
{
    $n = trim(preg_replace('/\.[a-z0-9]{2,5}$/i', '', basename($ime)));
    $n = trim(preg_replace('/[\x00-\x1f]+/', ' ', $n));
    return mb_substr($n !== '' ? $n : 'Datoteka', 0, 120);
}

/** Smije li korisnik vidjeti datoteku dokumenta (foto.php)? */
function dok_smije_datoteku(string $ime): bool
{
    $d = red('SELECT d.Objavljeno FROM DokumentDatoteke f JOIN Dokumenti d ON d.Id=f.DokumentId WHERE f.Datoteka=?', [$ime]);
    return $d && ((int) $d['Objavljeno'] === 1 || ima(P_SUSTAV));
}

function dok_datoteka_url(array $d, bool $preuzmi = false): string
{
    return url('foto', ['ime' => $d['Datoteka']] + ($preuzmi ? ['preuzmi' => 1] : []));
}

/** Tekst dokumenta: odlomci, prazni red = novi odlomak; redovi koji počinju s "#" su podnaslovi. */
function dok_tekst_html(?string $t): string
{
    $t = trim(str_replace("\r\n", "\n", (string) $t));
    if ($t === '') {
        return '';
    }
    $o = '';
    $odlomak = [];
    $zatvori = function () use (&$o, &$odlomak) {
        if ($odlomak) {
            $o .= '<p>' . implode('<br>', array_map('e', $odlomak)) . '</p>';
            $odlomak = [];
        }
    };
    foreach (explode("\n", $t) as $red) {
        $red = rtrim($red);
        if (trim($red) === '') {
            $zatvori();
        } elseif (preg_match('/^\s*#{1,3}\s*(.+)$/u', $red, $m)) {
            $zatvori();
            $o .= '<h2 class="h5 mt-4 mb-2">' . e($m[1]) . '</h2>';
        } else {
            $odlomak[] = $red;
        }
    }
    $zatvori();
    return $o;
}
