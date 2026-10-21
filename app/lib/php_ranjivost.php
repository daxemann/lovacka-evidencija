<?php
/**
 * Upozorenje administratorima: PHP2Shell (CVE-2026-109618, CVE-2026-109619), objavljeno 21.10.2026.
 * Ranjivost je u samom PHP-u, ne u Evidenciji – ispraviti ga mora hosting.
 * Obavijest se pokazuje samo dok poslužitelj radi na ranjivoj verziji PHP-a i nestaje sama nakon ažuriranja.
 */
declare(strict_types=1);

/** Je li trenutna verzija PHP-a ranjiva na PHP2Shell? Ispravljeno u 8.2.35, 8.3.36, 8.4.27, 8.5.12. */
function php2shell_ranjiv(): bool
{
    $ispravljeno = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
    $grana = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    if (isset($ispravljeno[$grana])) {
        return version_compare(PHP_VERSION, $ispravljeno[$grana], '<');
    }
    // starije od 8.2 više ne dobivaju ispravak; novije grane već sadrže ispravak
    return version_compare(PHP_VERSION, '8.2.0', '<');
}

/** HTML obavijesti za početnu – samo administratorima i samo na ranjivom PHP-u. */
function php2shell_obavijest(): string
{
    if (!ima(P_SUSTAV) || je_demo() || !php2shell_ranjiv()) {
        return '';
    }
    $radi = [
        'Prijava i početna stranica',
        'Skeniranje QR koda / dezinfekcija (upis)',
        'Karta lovišta i zauzimanje čeka',
        'Kalendar, termini i „Dolazim“',
        'Pregled radnih akcija i bodova',
        'Poruke i imenik',
        'PDF izvozi',
        'Ažuriranje programa',
    ];
    $neRadi = [
        'Spremanje profila (Moj profil – slika, potpis)',
        'Uređivanje člana (Članovi → uredi)',
        'Postavke udruge (logo)',
        'Učitavanje dokumenata',
        'Prijava kvara sa slikom',
        'Slika papirnate liste (dezinfekcija)',
        'Unos vlastite radne akcije (Moje akcije)',
        'Učitavanje i vraćanje sigurnosne kopije',
        'Oglas sa slikom (Oglasnik)',
        'Nova instalacija programa',
    ];
    $red = '';
    for ($i = 0, $n = max(count($radi), count($neRadi)); $i < $n; $i++) {
        $red .= '<tr><td>' . (isset($radi[$i]) ? '✅ ' . e($radi[$i]) : '') . '</td><td>'
            . (isset($neRadi[$i]) ? '⛔ ' . e($neRadi[$i]) : '') . '</td></tr>';
    }
    return '<div class="alert alert-danger no-print">'
        . '<h5 class="alert-heading mb-2">⚠️ Sigurnosno upozorenje: kritična ranjivost u PHP-u („PHP2Shell“)</h5>'
        . '<p class="mb-2">Dana 21.10.2026. objavljene su dvije kritične ranjivosti u PHP-u (<b>CVE-2026-109618</b> i <b>CVE-2026-109619</b>). '
        . 'Napadač preko njih može na daljinu pokrenuti vlastiti kod na poslužitelju. Ranjivost je u samom PHP-u, <b>ne u Evidenciji</b>.</p>'
        . '<p class="mb-2">Vaš poslužitelj koristi <b>PHP ' . e(PHP_VERSION) . '</b> – ta verzija je ranjiva. '
        . 'Ispravljene verzije: 8.2.35, 8.3.36, 8.4.27 i 8.5.12.</p>'
        . '<p class="mb-2"><b>Ispraviti mora vaš hosting</b> (ažurirati PHP) – Evidencija to ne može sama. '
        . 'Na vlastitom poslužitelju ažurirajte PHP; kod iznajmljenog ili besplatnog hostinga (npr. InfinityFree) pričekajte ili zatražite ažuriranje od hostinga.</p>'
        . '<p class="mb-2">Neki hostinzi (npr. InfinityFree) do ažuriranja <b>blokiraju učitavanje datoteka</b>. '
        . 'Tada se umjesto spremanja pojavi stranica <b>„403 Forbidden – openresty“</b>. To nije greška Evidencije.</p>'
        . '<div class="table-responsive"><table class="table table-sm table-bordered bg-white mb-2"><thead><tr>'
        . '<th>Radi normalno</th><th>Privremeno ne radi (403)</th></tr></thead><tbody>' . $red . '</tbody></table></div>'
        . '<small class="text-muted">Obavijest vide samo administratori i nestaje sama čim hosting ažurira PHP.</small>'
        . '</div>';
}
