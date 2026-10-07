<?php
/**
 * Ruter za ugrađeni PHP poslužitelj (lokalno korištenje / testiranje):
 *   php -S localhost:8080 ruter.php
 * Na pravom poslužitelju (Apache, nginx) ova datoteka nije potrebna.
 */
$put = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('#^/(app|podaci|vendor)(/|$)#', $put) || preg_match('#/\.#', $put) || preg_match('#^/(config\.php|composer\.(json|lock))$#', $put)) {
    http_response_code(403);
    exit('Zabranjeno');
}
if ($put !== '/' && is_file(__DIR__ . $put) && !str_ends_with($put, '.php')) {
    return false; // statičke datoteke (assets)
}
require __DIR__ . '/index.php';
