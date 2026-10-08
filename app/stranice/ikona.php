<?php
/** Ikona aplikacije za početni zaslon mobitela (iz loga udruge ili zadana), kvadratna PNG. */
$v = (int) ($_GET['v'] ?? 192);
$v = in_array($v, [180, 192, 512], true) ? $v : 192;
$mask = isset($_GET['m']);
header('Cache-Control: public, max-age=86400');
$logo = ima_logo() ? podaci('foto/' . basename((string) postavka('Udruga.Logo'))) : null;
if (!$logo || !function_exists('imagecreatetruecolor')) {
    header('Content-Type: image/png');
    readfile(KORIJEN . '/assets/' . ($mask ? 'ikona-maskable-512.png' : ($v === 512 ? 'ikona-512.png' : ($v === 180 ? 'ikona-180.png' : 'ikona-192.png'))));
    exit;
}
$pred = podaci('foto/ikona-' . $v . ($mask ? 'm' : '') . '-' . md5(basename($logo) . filemtime($logo)) . '.png');
if (!is_file($pred)) {
    foreach (glob(podaci('foto/ikona-' . $v . ($mask ? 'm' : '') . '-*.png')) ?: [] as $stara) {
        @unlink($stara);
    }
    $izvor = @imagecreatefromstring((string) file_get_contents($logo));
    $dst = imagecreatetruecolor($v, $v);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    if ($izvor) {
        $w = imagesx($izvor);
        $h = imagesy($izvor);
        $m = $v * ($mask ? 0.72 : 0.9);
        $f = min($m / $w, $m / $h);
        $nw = (int) round($w * $f);
        $nh = (int) round($h * $f);
        imagecopyresampled($dst, $izvor, (int) (($v - $nw) / 2), (int) (($v - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
    }
    imagepng($dst, $pred);
}
header('Content-Type: image/png');
readfile($pred);
exit;
