<?php
// Web manifest – "Dodaj na početni zaslon" na mobitelu.
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name' => udruga_naziv(),
    'short_name' => udruga_kratko(),
    'id' => bazni_put(),
    'start_url' => bazni_put(),
    'scope' => bazni_put(),
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#1f3d1f',
    'description' => udruga_podnaslov(),
    'lang' => 'hr',
    'icons' => [
        ['src' => url('ikona', ['v' => 192, 'x' => postavka('Udruga.Logo')]), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('ikona', ['v' => 512, 'x' => postavka('Udruga.Logo')]), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('ikona', ['v' => 512, 'm' => 1, 'x' => postavka('Udruga.Logo')]), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
