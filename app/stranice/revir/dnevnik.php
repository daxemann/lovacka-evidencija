<?php
/**
 * Lovački dnevnik: lovna godina → mjesec → dan. Upisi iz zauzeća naprava, ručni upisi i posjeti iz knjige dezinfekcije.
 * Bez prava dnevnika lovac (ako je uključeno) vidi samo današnji dan svoje sekcije. Gosti se ne upisuju.
 */
$k = korisnik();
if (!revir_dnevnik_vidljiv()) {
    zabranjeno();
}
revir_zatvori_istekle();
$pun = revir_dnevnik_pun();
$uredjivac = ima(P_REVIR_DNEVNIK_UREDI);
$danas = danas();
$dani = ['nedjelja', 'ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota'];
$lgOznaka = function (string $d): string {
    $g = (int) pocetak_lovne_godine(new DateTimeImmutable(substr($d, 0, 10)))->format('Y');
    return 'Lovna godina ' . $g . './' . ($g + 1) . '.';
};
$trajanje = function (int|float $min): string {
    $min = (int) round($min);
    return intdiv($min, 60) . ' h ' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
};
$minuta = fn(array $u): ?float => $u['Do'] ? (strtotime($u['Do']) - strtotime($u['Od'])) / 60 : null;
$sid = fn(array $u): ?int => $u['SekcijaId'] !== null ? (int) $u['SekcijaId'] : null;

// ---------- Opseg ----------
$sekcijeOpseg = [];
if ($pun) {
    $opD = opseg_prava_ili([P_REVIR_DNEVNIK, P_REVIR_DNEVNIK_UREDI]);
    if ($opD['sve']) {
        $uvjet = '1=1';
        foreach (redovi('SELECT Id, Naziv FROM Sekcije WHERE Aktivna=1 ORDER BY Redoslijed, Naziv') as $s) {
            $sekcijeOpseg[(int) $s['Id']] = $s['Naziv'];
        }
    } elseif ($opD['s']) {
        $uvjet = '(d.SekcijaId IN (' . implode(',', array_map('intval', $opD['s'])) . ')' . ($k['ClanId'] ? ' OR d.ClanId=' . (int) $k['ClanId'] : '') . ')';
        foreach ($opD['s'] as $s) {
            $sekcijeOpseg[$s] = naziv_sekcije($s);
        }
    } else {
        $uvjet = 'd.ClanId=' . (int) ($k['ClanId'] ?? 0);
    }
} else {
    // samo današnji dan, sekcije koje lovac vidi na karti
    $sekcijeOpseg = revir_sekcije();
    $uvjet = revir_kvar_sql($sekcijeOpseg, 'd.SekcijaId');
}
$viseSekcija = count($sekcijeOpseg) > 1;

// ---------- Uređivanje (POST) ----------
if (je_post()) {
    $natrag = [];
    parse_str((string) ($_POST['natrag'] ?? ''), $natrag);
    $natrag = array_intersect_key($natrag, array_flip(['R', 'Dan', 'Mj', 'LG', 'Od', 'Do', 'Aktivnost', 'SekcijaId', 'Izvor', 'Trazi', 'S', 'V']));
    $radnja = (string) ($_POST['radnja'] ?? '');
    $id = ul_int('Id');
    $stari = $id ? red('SELECT * FROM LovackiDnevnik WHERE Id=?', [$id]) : null;
    if ($id && !$stari) {
        poruka('Upis više ne postoji.', 'warning');
        preusmjeri('revir/dnevnik', $natrag);
    }
    if ($stari && !revir_dnevnik_moze_urediti($sid($stari))) {
        zabranjeno();
    }
    if (!$uredjivac) {
        zabranjeno();
    }
    if ($radnja === 'obrisi' && $stari) {
        q('DELETE FROM LovackiDnevnik WHERE Id=?', [$id]);
        dnevnik('Lovački dnevnik – obrisan upis', 'LovackiDnevnik', $id, $stari['Ime'] . ' · ' . $stari['Od'] . ' · ' . $stari['Naprava']);
        poruka('Upis je obrisan.');
        preusmjeri('revir/dnevnik', $natrag);
    }
    if ($radnja === 'spremi') {
        $greska = null;
        $clan = red('SELECT Id, Ime, Prezime, SekcijaId FROM Clanovi WHERE Id=?', [(int) ul_int('ClanId')]);
        $datum = u_datum(ul_str('Datum'));
        $odV = preg_match('/^\d{1,2}:\d{2}$/', ul_str('OdV')) ? ul_str('OdV') : null;
        $doV = preg_match('/^\d{1,2}:\d{2}$/', ul_str('DoV')) ? ul_str('DoV') : null;
        $nId = ul_int('NapravaId');
        $n = $nId ? revir_naprava($nId) : null;
        $mjesto = mb_substr(ul_str('Mjesto'), 0, 120);
        $akt = mb_substr(ul_str('Aktivnost'), 0, 60) ?: 'Lov';
        if (!$clan) {
            $greska = 'Odaberite lovca.';
        } elseif (!$datum || !$odV || !$doV) {
            $greska = 'Upišite datum, vrijeme od i do.';
        } elseif ($datum > $danas) {
            $greska = 'Upis ne može biti u budućnosti.';
        } elseif (!$n && $mjesto === '') {
            $greska = 'Odaberite lovnu napravu ili upišite mjesto.';
        }
        $sekcija = $n ? ($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null) : ($clan && $clan['SekcijaId'] !== null ? (int) $clan['SekcijaId'] : null);
        if (!$greska && !revir_dnevnik_moze_urediti($sekcija)) {
            $greska = 'Za tu sekciju nemate pravo uređivanja dnevnika.';
        }
        if ($greska) {
            poruka($greska, 'danger');
            preusmjeri('revir/dnevnik', $natrag + ['uredi' => $id ?: 'novi']);
        }
        $od = $datum . ' ' . str_pad($odV, 5, '0', STR_PAD_LEFT) . ':00';
        $do = $datum . ' ' . str_pad($doV, 5, '0', STR_PAD_LEFT) . ':00';
        if ($do <= $od) {
            $do = date('Y-m-d H:i:s', strtotime($do . ' +1 day')); // preko ponoći
        }
        $polja = [
            'ClanId' => (int) $clan['Id'], 'Ime' => trim($clan['Ime'] . ' ' . $clan['Prezime']), 'SekcijaId' => $sekcija,
            'NapravaId' => $n ? (int) $n['Id'] : null, 'Naprava' => $n ? revir_oznaka($n) : $mjesto, 'Od' => $od, 'Do' => $do,
            'Aktivnost' => revir_je_lov($akt) ? null : $akt, 'Napomena' => mb_substr(ul_str('Napomena'), 0, 200) ?: null,
            'Uredio' => $k['Naziv'], 'Uredjeno' => sada(),
        ];
        if ($stari) {
            azuriraj('LovackiDnevnik', $id, $polja);
            dnevnik('Lovački dnevnik – ispravljen upis', 'LovackiDnevnik', $id,
                $stari['Ime'] . ' ' . $stari['Od'] . ' ' . $stari['Naprava'] . ' → ' . $polja['Ime'] . ' ' . $od . ' ' . $polja['Naprava']);
            poruka('Upis je ispravljen.');
        } else {
            $novi = umetni('LovackiDnevnik', $polja + ['ZauzeceId' => null, 'Automatski' => 0, 'Rucno' => 1]);
            dnevnik('Lovački dnevnik – ručni upis', 'LovackiDnevnik', (int) $novi, $polja['Ime'] . ' · ' . $od . ' · ' . $polja['Naprava']);
            poruka('Upis je dodan.');
        }
        preusmjeri('revir/dnevnik', $stari ? $natrag : ['R' => 'Dan', 'Dan' => $datum] + array_diff_key($natrag, ['R' => 1, 'Dan' => 1, 'Mj' => 1, 'LG' => 1, 'Od' => 1, 'Do' => 1]));
    }
    preusmjeri('revir/dnevnik', $natrag);
}

// ---------- Filtar ----------
$RAZ = ['Dan' => 'Dan', 'Mjesec' => 'Mjesec', 'LG' => 'Lovna godina', 'Slobodno' => 'Od – do', 'Sve' => 'Sve'];
$SORT = ['DatumSilazno' => 'Datum ↓ (najnovije)', 'Datum' => 'Datum ↑', 'Lovac' => 'Lovac', 'Aktivnost' => 'Aktivnost', 'Mjesto' => 'Mjesto', 'Trajanje' => 'Trajanje ↓'];
$IZVORI = ['' => 'Svi izvori', 'lov' => 'Lovne naprave', 'dez' => 'Knjiga dezinfekcije', 'rucno' => 'Ručni upisi'];
$tekLG = (int) pocetak_lovne_godine()->format('Y');
$f = [
    'R' => $pun && isset($RAZ[$_GET['R'] ?? '']) ? $_GET['R'] : ($pun ? 'LG' : 'Dan'),
    'Dan' => u_datum((string) ($_GET['Dan'] ?? '')) ?? $danas,
    'Mj' => preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['Mj'] ?? '')) ? $_GET['Mj'] : substr($danas, 0, 7),
    'LG' => isset($_GET['LG']) && is_numeric($_GET['LG']) ? max(2000, min(2100, (int) $_GET['LG'])) : $tekLG,
    'Od' => u_datum((string) ($_GET['Od'] ?? '')) ?? date('Y-m-01'),
    'Do' => u_datum((string) ($_GET['Do'] ?? '')) ?? $danas,
    'Aktivnost' => (string) ($_GET['Aktivnost'] ?? ''),
    'SekcijaId' => isset($_GET['SekcijaId']) && is_numeric($_GET['SekcijaId']) ? (int) $_GET['SekcijaId'] : null,
    'Izvor' => isset($IZVORI[$_GET['Izvor'] ?? '']) ? (string) ($_GET['Izvor'] ?? '') : '',
    'Trazi' => mb_substr(trim((string) ($_GET['Trazi'] ?? '')), 0, 60),
    'S' => isset($SORT[$_GET['S'] ?? '']) ? $_GET['S'] : 'DatumSilazno',
    'V' => ($_GET['V'] ?? '') === 'tablica' ? 'tablica' : 'dnevnik',
];
if (!$pun) {
    $f['R'] = 'Dan';
    $f['Dan'] = $danas;
    $f['Trazi'] = '';
    $f['SekcijaId'] = null;
}
[$od, $do] = match ($f['R']) {
    'Dan' => [$f['Dan'], $f['Dan']],
    'Mjesec' => [$f['Mj'] . '-01', date('Y-m-t', strtotime($f['Mj'] . '-01'))],
    'LG' => [$f['LG'] . '-04-01', ($f['LG'] + 1) . '-03-31'],
    'Slobodno' => [min($f['Od'], $f['Do']), max($f['Od'], $f['Do'])],
    default => ['2000-01-01', '2100-12-31'],
};
$opisRaz = match ($f['R']) {
    'Dan' => $dani[(int) date('w', strtotime($od))] . ', ' . datum($od),
    'Mjesec' => mjesec_godina($od),
    'LG' => $lgOznaka($od),
    'Slobodno' => datum($od) . ' – ' . datum($do),
    default => 'sve',
};
// ◀ ▶ za dan / mjesec / lovnu godinu
$pomak = function (int $smjer) use ($f) {
    return match ($f['R']) {
        'Dan' => ['Dan' => date('Y-m-d', strtotime($f['Dan'] . ($smjer > 0 ? ' +1 day' : ' -1 day')))],
        'Mjesec' => ['Mj' => date('Y-m', strtotime($f['Mj'] . '-01 ' . ($smjer > 0 ? '+1 month' : '-1 month')))],
        'LG' => ['LG' => $f['LG'] + $smjer],
        default => null,
    };
};

$sekcijaF = $f['SekcijaId'] !== null && isset($sekcijeOpseg[$f['SekcijaId']]) ? $f['SekcijaId'] : null;
$upisi = revir_dnevnik_upisi($uvjet, $od, $do, $sekcijaF);

$aktivnosti = ['Lov' => 'Lov'];
foreach (dez_razlozi() as $dr) {
    if (!revir_je_lov($dr['Naziv'])) {
        $aktivnosti[$dr['Naziv']] = $dr['Naziv'];
    }
}
foreach ($upisi as $u) {
    $aktivnosti[$u['Aktivnost']] ??= $u['Aktivnost'];
}
if ($f['Aktivnost'] !== '' && isset($aktivnosti[$f['Aktivnost']])) {
    $upisi = array_values(array_filter($upisi, fn($u) => $u['Aktivnost'] === $f['Aktivnost']));
} else {
    $f['Aktivnost'] = '';
}
if ($f['Izvor'] !== '') {
    $upisi = array_values(array_filter($upisi, fn($u) => $u['Izvor'] === $f['Izvor']));
}
if ($f['Trazi'] !== '') {
    $t = kljuc($f['Trazi']);
    $upisi = array_values(array_filter($upisi, fn($u) => str_contains(kljuc($u['Ime']), $t) || str_contains(kljuc((string) $u['Mjesto']), $t) || str_contains(kljuc((string) $u['Napomena']), $t)));
}
$poKljucu = fn(array $u): array => match ($f['S']) {
    'Lovac' => [kljuc($u['Ime']), $u['Od']],
    'Aktivnost' => [kljuc($u['Aktivnost']), $u['Od']],
    'Mjesto' => [kljuc((string) $u['Mjesto']), $u['Od']],
    'Trajanje' => [-($minuta($u) ?? 0), $u['Od']],
    default => [$u['Od']],
};
usort($upisi, function ($a, $b) use ($f, $poKljucu) {
    if ($f['S'] === 'DatumSilazno') {
        return strcmp($b['Od'], $a['Od']);
    }
    return $poKljucu($a) <=> $poKljucu($b);
});
// prikaz dnevnika: dani prema smjeru datuma, unutar dana kronološki (ili po odabranom sortiranju)
$grupe = [];
if ($f['V'] === 'dnevnik') {
    $poDanu = [];
    foreach ($upisi as $u) {
        $poDanu[substr($u['Od'], 0, 10)][] = $u;
    }
    $f['S'] === 'Datum' ? ksort($poDanu) : krsort($poDanu);
    foreach ($poDanu as $d => $lista) {
        if (in_array($f['S'], ['Datum', 'DatumSilazno'], true)) {
            usort($lista, fn($a, $b) => strcmp($a['Od'], $b['Od']));
        }
        $grupe[$lgOznaka($d)][mjesec_godina($d)][$d] = $lista;
    }
}
$ukupnoMin = array_sum(array_map(fn($u) => $minuta($u) ?? 0, $upisi));
$poAktivnosti = [];
foreach ($upisi as $u) {
    $poAktivnosti[$u['Aktivnost']] = ($poAktivnosti[$u['Aktivnost']] ?? 0) + 1;
}
$upit = array_filter(['R' => $f['R'], 'Dan' => $f['R'] === 'Dan' ? $f['Dan'] : null, 'Mj' => $f['R'] === 'Mjesec' ? $f['Mj'] : null,
    'LG' => $f['R'] === 'LG' ? $f['LG'] : null, 'Od' => $f['R'] === 'Slobodno' ? $f['Od'] : null, 'Do' => $f['R'] === 'Slobodno' ? $f['Do'] : null,
    'Aktivnost' => $f['Aktivnost'], 'SekcijaId' => $sekcijaF, 'Izvor' => $f['Izvor'], 'Trazi' => $f['Trazi'],
    'S' => $f['S'] !== 'DatumSilazno' ? $f['S'] : null, 'V' => $f['V'] !== 'dnevnik' ? $f['V'] : null], fn($v) => $v !== null && $v !== '');
$samoJa = $pun && str_starts_with($uvjet, 'd.ClanId=');
$stupacLovac = !$samoJa;
$vrijeme = function (array $u): string {
    $s = date('H:i', strtotime($u['Od'])) . ' – ';
    if ($u['Do']) {
        $s .= substr($u['Do'], 0, 10) !== substr($u['Od'], 0, 10) ? date('d.m. H:i', strtotime($u['Do'])) : date('H:i', strtotime($u['Do']));
    }
    return $s;
};
$oznake = function (array $u): string {
    $o = [];
    if ($u['Automatski']) {
        $o[] = 'automatski oslobođeno';
    }
    if ($u['Izvor'] === 'dez') {
        $o[] = 'knjiga dezinfekcije';
    }
    if ($u['Rucno']) {
        $o[] = 'ručni upis';
    }
    if ($u['Uredio'] && !$u['Rucno']) {
        $o[] = 'ispravljeno';
    }
    if ($u['Uredio']) {
        $o[count($o) - 1] .= ' (' . $u['Uredio'] . ', ' . datum((string) $u['Uredjeno']) . ')';
    }
    return implode(' · ', $o);
};

// ---------- PDF ----------
if ($pun && ($_GET['izvoz'] ?? '') === 'pdf') {
    $sekOpis = $sekcijaF !== null ? 'sekcija ' . naziv_sekcije($sekcijaF) : ($samoJa ? $k['Naziv'] : ($viseSekcija ? 'sve sekcije' : implode(', ', $sekcijeOpseg)));
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $logo = '<img src="data:' . (str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:42px;float:left;margin-right:10px">';
    }
    $stupci = ($f['V'] === 'dnevnik' ? '' : '<th>Datum</th>') . '<th>Od – do</th><th>Trajanje</th>' . ($stupacLovac ? '<th>Lovac</th>' : '') . '<th>Aktivnost</th><th>Mjesto</th>'
        . ($viseSekcija ? '<th>Sekcija</th>' : '') . '<th>Napomena</th>';
    $brStupaca = substr_count($stupci, '<th>');
    $redak = function (array $u, bool $sDatumom) use ($minuta, $trajanje, $stupacLovac, $viseSekcija, $vrijeme, $oznake, $sid): string {
        $m = $minuta($u);
        $nap = trim($u['Napomena'] . ($u['Napomena'] !== '' && $oznake($u) !== '' ? ' · ' : '') . $oznake($u));
        return '<tr>' . ($sDatumom ? '<td>' . e(datum($u['Od'])) . '</td>' : '') . '<td>' . e($vrijeme($u)) . '</td><td>' . ($m !== null ? e($trajanje($m)) : '') . '</td>'
            . ($stupacLovac ? '<td>' . e($u['Ime']) . '</td>' : '') . '<td>' . e($u['Aktivnost']) . '</td><td>' . e($u['Mjesto']) . '</td>'
            . ($viseSekcija ? '<td>' . e(naziv_sekcije($sid($u))) . '</td>' : '') . '<td>' . e($nap) . '</td></tr>';
    };
    $tijelo = '';
    if ($f['V'] === 'dnevnik') {
        foreach ($grupe as $lg => $mjeseci) {
            $tijelo .= '<h3 class="lg">' . e($lg) . '</h3>';
            foreach ($mjeseci as $mj => $daniM) {
                $tijelo .= '<h4>' . e(mb_convert_case($mj, MB_CASE_TITLE)) . '</h4><table><thead><tr>' . $stupci . '</tr></thead><tbody>';
                foreach ($daniM as $d => $lista) {
                    $tijelo .= '<tr class="dan"><td colspan="' . $brStupaca . '">' . e($dani[(int) date('w', strtotime($d))] . ', ' . datum($d)) . ' · ' . count($lista) . '</td></tr>';
                    foreach ($lista as $u) {
                        $tijelo .= $redak($u, false);
                    }
                }
                $tijelo .= '</tbody></table>';
            }
        }
    } else {
        $tijelo = '<table><thead><tr>' . $stupci . '</tr></thead><tbody>';
        foreach ($upisi as $u) {
            $tijelo .= $redak($u, true);
        }
        $tijelo .= '</tbody></table>';
    }
    if (!$upisi) {
        $tijelo = '<p>Nema upisa za odabrano razdoblje.</p>';
    }
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #222; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 8px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; }
        h2 { font-size: 12pt; margin: 2px 0; } .pod { color: #555; margin-bottom: 6px; }
        h3.lg { font-size: 10.5pt; margin: 10px 0 2px; color: #2f5d23; } h4 { font-size: 9pt; margin: 8px 0 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; } th { background: #e9efe5; text-align: left; }
        th, td { border: 0.5pt solid #bbb; padding: 2px 3px; vertical-align: top; }
        tr.dan td { background: #f4f6f2; font-weight: bold; }
        .podnozje { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · lovački dnevnik · ispisano ' . date('d.m.Y. H:i') . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div>' . (udruga_adresa() ? '<div style="color:#555">' . e(udruga_adresa()) . '</div>' : '') . '</div>
    <h2>Lovački dnevnik' . ($f['Aktivnost'] !== '' ? ' – ' . e($f['Aktivnost']) : '') . '</h2>
    <div class="pod">' . e($opisRaz) . ' · ' . e($sekOpis) . ($f['Izvor'] !== '' ? ' · ' . e($IZVORI[$f['Izvor']]) : '') . ($f['Trazi'] !== '' ? ' · „' . e($f['Trazi']) . '“' : '')
        . ' · upisa: ' . count($upisi) . ' · ukupno ' . e($trajanje($ukupnoMin))
        . (count($poAktivnosti) > 1 ? ' · ' . e(implode(', ', array_map(fn($a, $n) => "$a $n", array_keys($poAktivnosti), $poAktivnosti))) : '') . '</div>'
        . $tijelo . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->getCanvas()->page_text(530, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (($_GET['prikaz'] ?? '') === '1' ? 'inline' : 'attachment') . '; filename="lovacki-dnevnik-' . $od . '-' . $do . '.pdf"');
    echo $pdf->output();
    exit;
}

// ---------- Obrazac za novi upis / ispravak ----------
$urediId = $_GET['uredi'] ?? null;
if ($uredjivac && $urediId !== null) {
    $u = $urediId === 'novi' ? null : red('SELECT * FROM LovackiDnevnik WHERE Id=?', [(int) $urediId]);
    if ($urediId !== 'novi' && (!$u || !revir_dnevnik_moze_urediti($sid($u)))) {
        zabranjeno();
    }
    $clanovi = redovi('SELECT c.Id, c.Ime, c.Prezime FROM Clanovi c WHERE (c.Status=0 AND ' . opseg_sql_prava(P_REVIR_DNEVNIK_UREDI, 'c') . ')'
        . ($u && $u['ClanId'] ? ' OR c.Id=' . (int) $u['ClanId'] : '') . ' ORDER BY c.Prezime, c.Ime');
    $naprave = array_values(array_filter(revir_naprave(revir_sekcije(), true), fn($n) => revir_dnevnik_moze_urediti($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null)));
    $uAkt = $u ? (trim((string) $u['Aktivnost']) ?: 'Lov') : 'Lov';
    ob_start(); ?>
<h1 class="h3 mb-3"><?= $u ? '✏️ Ispravak upisa' : '+ Novi upis u dnevnik' ?></h1>
<form method="post" action="<?= e(url('revir/dnevnik')) ?>" class="card card-body" style="max-width:640px"><?= csrf() ?>
    <input type="hidden" name="radnja" value="spremi"><input type="hidden" name="Id" value="<?= $u ? (int) $u['Id'] : '' ?>">
    <input type="hidden" name="natrag" value="<?= e(http_build_query($upit)) ?>">
    <?php if ($u && !$u['Rucno']): ?><div class="alert alert-info small py-2">Upis je nastao automatski iz zauzeća naprave. Ispravak se bilježi s vašim imenom.</div><?php endif; ?>
    <div class="row g-2">
        <div class="col-12"><label class="form-label small mb-0">Lovac</label>
            <select name="ClanId" class="form-select" required><option value="">— odaberite —</option>
                <?php foreach ($clanovi as $c): ?><option value="<?= (int) $c['Id'] ?>"<?= sel($c['Id'], $u['ClanId'] ?? '') ?>><?= e($c['Prezime'] . ' ' . $c['Ime']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Datum</label><input type="date" name="Datum" class="form-control" required max="<?= e($danas) ?>" value="<?= e($u ? substr($u['Od'], 0, 10) : $danas) ?>"></div>
        <div class="col-3 col-md-4"><label class="form-label small mb-0">Od</label><input type="time" name="OdV" class="form-control" required value="<?= e($u ? substr($u['Od'], 11, 5) : '') ?>"></div>
        <div class="col-3 col-md-4"><label class="form-label small mb-0">Do</label><input type="time" name="DoV" class="form-control" required value="<?= e($u && $u['Do'] ? substr($u['Do'], 11, 5) : '') ?>"></div>
        <div class="col-12 form-text mt-0">„Do“ manje od „Od“ = preko ponoći (sljedeći dan).</div>
        <div class="col-md-6"><label class="form-label small mb-0">Aktivnost</label>
            <select name="Aktivnost" class="form-select"><?php foreach ($aktivnosti as $a): ?><option<?= sel($a, $uAkt) ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label small mb-0">Lovna naprava</label>
            <select name="NapravaId" class="form-select"><option value="">— bez naprave (upišite mjesto) —</option>
                <?php foreach ($naprave as $n): ?><option value="<?= (int) $n['Id'] ?>"<?= sel($n['Id'], $u['NapravaId'] ?? '') ?>><?= e(revir_oznaka($n) . ($viseSekcija && $n['SekcijaId'] !== null ? ' (' . naziv_sekcije((int) $n['SekcijaId']) . ')' : '')) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-12"><label class="form-label small mb-0">Mjesto (ako nije naprava)</label><input name="Mjesto" class="form-control" maxlength="120" placeholder="npr. pirš kod Velikog jezera, hranilište 3" value="<?= e($u && !$u['NapravaId'] ? $u['Naprava'] : '') ?>"></div>
        <div class="col-12"><label class="form-label small mb-0">Napomena</label><input name="Napomena" class="form-control" maxlength="200" value="<?= e($u['Napomena'] ?? '') ?>"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary">Spremi</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('revir/dnevnik', $upit)) ?>">Odustani</a>
        <?php if ($u): ?><button class="btn btn-outline-danger ms-auto" name="radnja" value="obrisi" onclick="return confirm('Obrisati ovaj upis iz dnevnika?')">🗑 Obriši upis</button><?php endif; ?>
    </div>
    <?php if ($u && $u['Uredio']): ?><div class="small text-muted mt-2">Zadnja promjena: <?= e($u['Uredio']) ?>, <?= e(datum((string) $u['Uredjeno'])) ?> <?= e(substr((string) $u['Uredjeno'], 11, 5)) ?></div><?php endif; ?>
</form>
<?php stranica('Lovački dnevnik', ob_get_clean());
    return;
}

// ---------- Prikaz ----------
$redakHtml = function (array $u, bool $sDatumom) use ($minuta, $trajanje, $stupacLovac, $viseSekcija, $vrijeme, $uredjivac, $upit, $sid): string {
    $m = $minuta($u);
    $mozeUr = $uredjivac && $u['Izvor'] !== 'dez' && $u['Id'] && revir_dnevnik_moze_urediti($sid($u));
    $znak = '';
    if ($u['Automatski']) {
        $znak .= ' <span class="badge bg-secondary-subtle text-secondary-emphasis" title="Nije dodirnuo „Odlazim“ – automatski oslobođeno">auto</span>';
    }
    if ($u['Rucno']) {
        $znak .= ' <span class="badge bg-info-subtle text-info-emphasis" title="' . e('Ručni upis: ' . $u['Uredio'] . ', ' . datum((string) $u['Uredjeno'])) . '">ručno</span>';
    } elseif ($u['Uredio']) {
        $znak .= ' <span class="badge bg-warning-subtle text-warning-emphasis" title="' . e('Ispravio: ' . $u['Uredio'] . ', ' . datum((string) $u['Uredjeno'])) . '">ispravljeno</span>';
    }
    $mjesto = $u['NapravaId'] ? '<a href="' . e(url('revir', ['naprava' => $u['NapravaId']])) . '" class="text-decoration-none">' . e($u['Mjesto']) . '</a>'
        : '<span class="small">' . ($u['Izvor'] === 'dez' ? '<span title="Iz knjige dezinfekcije – ispravak samo u knjizi dezinfekcije">🧴</span> ' : '') . e($u['Mjesto']) . '</span>';
    return '<tr>' . ($sDatumom ? '<td class="text-nowrap">' . e(datum($u['Od'])) . '</td>' : '')
        . '<td class="text-nowrap">' . e($vrijeme($u)) . $znak . '</td>'
        . '<td class="text-nowrap small d-none d-sm-table-cell">' . ($m !== null ? e($trajanje($m)) : '') . '</td>'
        . ($stupacLovac ? '<td>' . e($u['Ime']) . '</td>' : '')
        . '<td><span class="badge ' . (revir_je_lov($u['Aktivnost']) ? 'bg-success' : 'bg-secondary') . '">' . e($u['Aktivnost']) . '</span>'
        . ($u['Napomena'] !== '' ? '<div class="small text-muted">' . e($u['Napomena']) . '</div>' : '') . '</td>'
        . '<td>' . $mjesto . '</td>'
        . ($viseSekcija ? '<td class="small">' . e(naziv_sekcije($sid($u))) . '</td>' : '')
        . ($uredjivac ? '<td class="no-print text-end">' . ($mozeUr ? '<a class="btn btn-sm btn-outline-secondary py-0" title="Ispravi / obriši" href="' . e(url('revir/dnevnik', $upit + ['uredi' => $u['Id']])) . '">✏️</a>' : '') . '</td>' : '')
        . '</tr>';
};
$sortLink = function (string $s, string $naslov) use ($upit, $f): string {
    $novi = $s === 'Datum' && $f['S'] === 'DatumSilazno' ? 'Datum' : ($s === 'Datum' && $f['S'] === 'Datum' ? 'DatumSilazno' : $s);
    $akt = $f['S'] === $s || ($s === 'Datum' && $f['S'] === 'DatumSilazno');
    return '<a class="text-decoration-none text-reset" href="' . e(url('revir/dnevnik', ['S' => $novi] + $upit)) . '">' . e($naslov) . ($akt ? ($f['S'] === 'DatumSilazno' ? ' ↓' : ($s === 'Trajanje' ? ' ↓' : ' ↑')) : '') . '</a>';
};
$zaglavlje = function (bool $sDatumom) use ($stupacLovac, $viseSekcija, $uredjivac, $sortLink): string {
    return '<thead><tr>' . ($sDatumom ? '<th>' . $sortLink('Datum', 'Datum') . '</th>' : '') . '<th>Od – do</th><th class="d-none d-sm-table-cell">' . $sortLink('Trajanje', 'Trajanje') . '</th>'
        . ($stupacLovac ? '<th>' . $sortLink('Lovac', 'Lovac') . '</th>' : '') . '<th>' . $sortLink('Aktivnost', 'Aktivnost') . '</th><th>' . $sortLink('Mjesto', 'Mjesto') . '</th>'
        . ($viseSekcija ? '<th>Sekcija</th>' : '') . ($uredjivac ? '<th class="no-print"></th>' : '') . '</tr></thead>';
};
$godine = [];
if ($pun) {
    $min = (string) vrijednost('SELECT MIN(Od) FROM LovackiDnevnik');
    $prva = $min !== '' ? (int) pocetak_lovne_godine(new DateTimeImmutable(substr($min, 0, 10)))->format('Y') : $tekLG;
    for ($g = $tekLG; $g >= min($prva, $f['LG']); $g--) {
        $godine[] = $g;
    }
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">📓 Lovački dnevnik</h1>
    <?php if ($uredjivac): ?><a class="btn btn-sm btn-success no-print" href="<?= e(url('revir/dnevnik', $upit + ['uredi' => 'novi'])) ?>">+ Novi upis</a><?php endif; ?>
    <a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('revir/danas')) ?>">Danas u lovištu</a>
    <a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('revir')) ?>">Karta lovišta</a>
</div>
<p class="text-muted small no-print">Upisuje se automatski: <b>lov</b> kad oslobodite lovnu napravu (ili u <?= sprintf('%02d:00', revir_sat_isteka()) ?> ujutro),
    <b>ostalo</b> (radna akcija, hranjenje…) i lov bez čeke (pirš / obilazak) iz knjige dezinfekcije – dolazak do odlaska. Gosti se ne upisuju.
    <?= !$pun ? '<br><b>Prikazuje se samo današnji dan.</b>' : ($samoJa ? 'Vidite samo svoje upise.' : '') ?></p>

<?php if ($pun): ?>
<form method="get" action="<?= e(url()) ?>" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="revir/dnevnik">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Razdoblje</label>
            <select name="R" class="form-select form-select-sm" data-auto><?php foreach ($RAZ as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['R']) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <?php if ($f['R'] === 'Dan'): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Dan</label><input type="date" name="Dan" class="form-control form-control-sm" value="<?= e($f['Dan']) ?>" data-auto></div>
        <?php elseif ($f['R'] === 'Mjesec'): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Mjesec</label><input type="month" name="Mj" class="form-control form-control-sm" value="<?= e($f['Mj']) ?>" data-auto></div>
        <?php elseif ($f['R'] === 'LG'): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Lovna godina</label>
                <select name="LG" class="form-select form-select-sm" data-auto><?php foreach ($godine as $g): ?><option value="<?= $g ?>"<?= sel($g, $f['LG']) ?>><?= $g ?>./<?= $g + 1 ?>.</option><?php endforeach; ?></select></div>
        <?php elseif ($f['R'] === 'Slobodno'): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>"></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Aktivnost</label>
            <select name="Aktivnost" class="form-select form-select-sm" data-auto><option value="">Sve</option>
                <?php foreach ($aktivnosti as $a): ?><option<?= sel($a, $f['Aktivnost']) ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Izvor</label>
            <select name="Izvor" class="form-select form-select-sm" data-auto><?php foreach ($IZVORI as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['Izvor']) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <?php if ($viseSekcija): ?>
            <div class="col-6 col-md-2"><label class="form-label small mb-0">Sekcija</label>
                <select name="SekcijaId" class="form-select form-select-sm" data-auto><option value="">Sve sekcije</option>
                    <?php foreach ($sekcijeOpseg as $s => $n): ?><option value="<?= $s ?>"<?= sel($s, $sekcijaF) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <?php if (!$samoJa): ?><div class="col-6 col-md-2"><label class="form-label small mb-0">Lovac, mjesto, napomena</label><input name="Trazi" class="form-control form-control-sm" value="<?= e($f['Trazi']) ?>"></div><?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Sortiranje</label>
            <select name="S" class="form-select form-select-sm" data-auto><?php foreach ($SORT as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['S']) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Prikaz</label>
            <select name="V" class="form-select form-select-sm" data-auto><option value="dnevnik"<?= sel('dnevnik', $f['V']) ?>>Dnevnik (po danima)</option><option value="tablica"<?= sel('tablica', $f['V']) ?>>Tablica</option></select></div>
        <div class="col-6 col-md-1"><button class="btn btn-sm btn-primary w-100">Prikaži</button></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">🖨 Ispis</button>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(url('revir/dnevnik', $upit + ['izvoz' => 'pdf', 'prikaz' => 1])) ?>">PDF</a>
        <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(url('revir/dnevnik', ['R' => 'Dan'])) ?>">Danas</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir/dnevnik', ['R' => 'Mjesec'])) ?>">Ovaj mjesec</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('revir/dnevnik')) ?>">Tekuća lovna godina</a>
    </div>
</form>
<?php endif; ?>

<div class="d-flex align-items-center gap-2 mb-2">
    <?php if ($pun && ($p = $pomak(-1))): ?><a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('revir/dnevnik', $p + $upit)) ?>" title="Prethodno">◀</a><?php endif; ?>
    <h2 class="h5 mb-0"><?= e(mb_strtoupper(mb_substr($opisRaz, 0, 1)) . mb_substr($opisRaz, 1)) ?></h2>
    <?php if ($pun && ($p = $pomak(1)) && (($p['Dan'] ?? null) === null || $p['Dan'] <= $danas)): ?><a class="btn btn-sm btn-outline-secondary no-print" href="<?= e(url('revir/dnevnik', $p + $upit)) ?>" title="Sljedeće">▶</a><?php endif; ?>
</div>
<div class="d-flex flex-wrap gap-4 mb-2">
    <div><div class="kartica-broj"><?= count($upisi) ?></div><div class="small text-muted">izlazaka</div></div>
    <div><div class="kartica-broj"><?= e($trajanje($ukupnoMin)) ?></div><div class="small text-muted">ukupno</div></div>
    <?php if ($stupacLovac): ?><div><div class="kartica-broj"><?= count(array_unique(array_column($upisi, 'Ime'))) ?></div><div class="small text-muted">lovaca</div></div><?php endif; ?>
</div>
<?php if (count($poAktivnosti) > 1): ?><div class="small mb-2"><?php foreach ($poAktivnosti as $a => $n): ?><span class="badge text-bg-light border me-1"><?= e($a) ?>: <?= $n ?></span><?php endforeach; ?></div><?php endif; ?>

<?php if (!$upisi): ?><div class="alert alert-secondary"><?= $pun ? 'Nema upisa za odabrano.' : 'Danas još nema upisa u dnevniku.' ?></div>
<?php elseif ($f['V'] === 'dnevnik'): ?>
    <?php foreach ($grupe as $lg => $mjeseci): ?>
        <?php if (!in_array($f['R'], ['Dan', 'Mjesec', 'LG'], true)): ?><h3 class="h5 mt-3 text-success border-bottom pb-1"><?= e($lg) ?></h3><?php endif; ?>
        <?php foreach ($mjeseci as $mj => $daniM): ?>
            <?php if (!in_array($f['R'], ['Dan', 'Mjesec'], true)): ?><h4 class="h6 mt-3 mb-1"><?= e(mb_convert_case($mj, MB_CASE_TITLE)) ?> <span class="text-muted small fw-normal">· <?= array_sum(array_map('count', $daniM)) ?></span></h4><?php endif; ?>
            <div class="table-responsive"><table class="table table-sm align-middle mb-2"><?= $zaglavlje(false) ?><tbody>
            <?php foreach ($daniM as $d => $lista): ?>
                <?php if ($f['R'] !== 'Dan'): ?><tr class="table-light"><td colspan="9" class="fw-semibold small">
                    <?php if ($pun): ?><a class="text-decoration-none text-reset" href="<?= e(url('revir/dnevnik', ['R' => 'Dan', 'Dan' => $d] + array_diff_key($upit, ['R' => 1, 'Mj' => 1, 'LG' => 1, 'Od' => 1, 'Do' => 1]))) ?>"><?php endif; ?>
                    <?= e($dani[(int) date('w', strtotime($d))] . ', ' . datum($d)) ?><?= $pun ? '</a>' : '' ?> <span class="text-muted fw-normal">· <?= count($lista) ?></span></td></tr><?php endif; ?>
                <?php foreach ($lista as $u) echo $redakHtml($u, false); ?>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm table-striped align-middle"><?= $zaglavlje(true) ?><tbody>
    <?php foreach ($upisi as $u) echo $redakHtml($u, true); ?>
</tbody></table></div>
<?php endif; ?>
<?php stranica('Lovački dnevnik', ob_get_clean());
