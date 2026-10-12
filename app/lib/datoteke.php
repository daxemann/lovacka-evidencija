<?php
/** Fotografije, slanje datoteka, učitavanje. */
declare(strict_types=1);

const SLIKA_MAX_MP = 60;          // veće slike se odbijaju (memorija poslužitelja)
const SLIKA_MAX_ORIGINAL_MB = 4;  // bez smanjivanja (nema GD) sprema se samo do ove veličine

function posalji_datoteku(string $put, int $cache = 0, ?string $ime = null, ?string $tip = null): never
{
    $tip ??= match (strtolower(pathinfo($put, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'pdf' => 'application/pdf', 'zip' => 'application/zip', 'csv' => 'text/csv; charset=utf-8',
        'db' => 'application/octet-stream', default => 'application/octet-stream',
    };
    header('Content-Type: ' . $tip);
    header('Content-Length: ' . filesize($put));
    header('X-Content-Type-Options: nosniff');
    header($cache > 0 ? 'Cache-Control: private, max-age=' . $cache : 'Cache-Control: no-store');
    if ($ime) {
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $ime) . '"');
    }
    readfile($put);
    exit;
}

function upload_greska(?array $f): string
{
    if (!$f) {
        return '';
    }
    return match ($f['error'] ?? 0) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ' Datoteka je prevelika za ovaj poslužitelj (najviše ' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_NO_FILE => '',
        UPLOAD_ERR_OK => '',
        default => ' Greška pri učitavanju (' . (int) $f['error'] . ').',
    };
}

/**
 * Sprema učitanu sliku u podaci/foto: smanjuje na $max px (ako postoji GD), JPEG ili PNG.
 * Vraća ime datoteke. $prefiks npr. "clan-5-", "oglas-3-".
 */
function spremi_sliku(array $f, string $prefiks, int $max = 1600, bool $png = false): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Slika nije učitana.' . upload_greska($f));
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        throw new RuntimeException('Datoteka nije slika (JPG, PNG, WEBP).');
    }
    // zaštita poslužitelja: preveliku sliku ne otvaramo (memorija) i ne spremamo kao original
    $mp = $info[0] * $info[1] / 1e6;
    if ($mp > SLIKA_MAX_MP) {
        throw new RuntimeException('Slika je prevelika (' . round($mp) . ' megapiksela, najviše ' . SLIKA_MAX_MP . '). Smanjite je ili snimite manjom rezolucijom.');
    }
    if (function_exists('imagecreatefromstring')) {
        @ini_set('memory_limit', '512M'); // GD treba ~5 B po pikselu (npr. 48 MP ≈ 250 MB)
    }
    $ext = $png ? 'png' : 'jpg';
    $ime = $prefiks . bin2hex(random_bytes(6)) . '.' . $ext;
    $cilj = podaci('foto/' . $ime);
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if ($src) {
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $o = @exif_read_data($f['tmp_name'])['Orientation'] ?? 1;
                $src = match ((int) $o) { 3 => imagerotate($src, 180, 0), 6 => imagerotate($src, -90, 0), 8 => imagerotate($src, 90, 0), default => $src };
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $s = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $s));
            $nh = max(1, (int) round($h * $s));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($png) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            } else {
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = $png ? imagepng($dst, $cilj, 6) : imagejpeg($dst, $cilj, 85);
            if ($ok) {
                return $ime;
            }
        }
    }
    // bez GD-a (ili GD nije uspio): original samo ako nije prevelik
    if (filesize($f['tmp_name']) > SLIKA_MAX_ORIGINAL_MB * 1024 * 1024) {
        throw new RuntimeException('Slika je prevelika (' . round(filesize($f['tmp_name']) / 1048576, 1) . ' MB), a poslužitelj je ne može smanjiti. Najviše ' . SLIKA_MAX_ORIGINAL_MB . ' MB.');
    }
    $ext = image_type_to_extension($info[2], false) === 'jpeg' ? 'jpg' : image_type_to_extension($info[2], false);
    $ime = $prefiks . bin2hex(random_bytes(6)) . '.' . $ext;
    $ok = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], podaci('foto/' . $ime)) : copy($f['tmp_name'], podaci('foto/' . $ime));
    if (!$ok) {
        throw new RuntimeException('Slika se ne može spremiti (prava na mapu podaci/foto).');
    }
    return $ime;
}

function obrisi_sliku(?string $ime): void
{
    if ($ime) {
        @unlink(podaci('foto/' . basename($ime)));
    }
}

function foto_url(?string $ime): string
{
    return $ime ? url('foto', ['ime' => $ime]) : '';
}

/** Avatar člana: fotografija ili inicijali. */
function avatar(?array $c, bool $velika = false): string
{
    if ($c && !empty($c['FotoDatoteka'])) {
        return '<img src="' . e(foto_url($c['FotoDatoteka'])) . '" class="' . ($velika ? 'foto-velika' : 'foto-mala') . '" alt="" loading="lazy">';
    }
    return '<span class="inicijali' . ($velika ? ' inicijali-velika' : '') . '">' . e(inicijali($c)) . '</span>';
}
