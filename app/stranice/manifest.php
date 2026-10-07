<?php
// Web manifest – "Dodaj na početni zaslon" na mobitelu.
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name' => udruga_naziv(),
    'short_name' => udruga_kratko(),
    'start_url' => bazni_put(),
    'scope' => bazni_put(),
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#1f3d1f',
    'icons' => [['src' => ima_logo() ? url('logo') : asset('assets/favicon.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
