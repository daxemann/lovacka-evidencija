<?php
/** Uvoz članova iz Google kontakata (Google CSV) s prepoznavanjem sličnih. */
declare(strict_types=1);

function je_mobilni(string $broj): bool
{
    $t = (string) preg_replace('/[^\d+]/', '', $broj);
    if (str_starts_with($t, '00')) {
        $t = '+' . substr($t, 2);
    }
    if (str_starts_with($t, '+3859') || str_starts_with($t, '09')) {
        return true;
    }
    if (str_starts_with($t, '+491') || preg_match('/^01[567]/', $t)) {
        return true;
    }
    if (str_starts_with($t, '+43') && strlen($t) > 4 && $t[3] === '6') {
        return true;
    }
    return !(str_starts_with($t, '+') || str_starts_with($t, '0'));
}

/** Čita Google CSV i vraća retke za pregled (s kandidatima i prijedlogom odluke). */
function procitaj_google_csv(string $put): array
{
    $f = fopen($put, 'r');
    if (!$f) {
        throw new RuntimeException('Datoteka se ne može otvoriti.');
    }
    $prvi = (string) fgets($f);
    $prvi = preg_replace('/^\xEF\xBB\xBF/', '', $prvi);
    $sep = substr_count($prvi, ';') > substr_count($prvi, ',') ? ';' : ',';
    $zag = array_map('trim', str_getcsv($prvi, $sep));
    if (!in_array('First Name', $zag, true) && !in_array('Given Name', $zag, true)) {
        throw new RuntimeException('To nije Google CSV (nema stupca „First Name“). U Google kontaktima odaberite Izvezi → Google CSV.');
    }
    $idx = array_flip($zag);
    $sekcije = sekcije();
    $postojeci = redovi('SELECT * FROM Clanovi');
    $retci = [];
    while (($red = fgetcsv($f, 0, $sep, '"', '')) !== false) {
        $p = function (string $ime) use ($idx, $red): ?string {
            if (!isset($idx[$ime])) {
                return null;
            }
            $v = trim((string) ($red[$idx[$ime]] ?? ''));
            return $v === '' ? null : $v;
        };
        $r = [
            'Ime' => trim($p('First Name') ?? $p('Given Name') ?? ''),
            'Prezime' => trim($p('Last Name') ?? $p('Family Name') ?? ''),
            'Nadimak' => $p('Nickname'), 'Napomena' => $p('Notes'), 'FotoUrl' => $p('Photo'),
            'Ulica' => $p('Address 1 - Street'), 'PostanskiBroj' => $p('Address 1 - Postal Code'), 'Mjesto' => $p('Address 1 - City'),
            'Email' => null, 'Mobilni' => null, 'Fiksni' => null, 'DatumRodjenja' => null, 'SekcijaId' => null, 'SekcijaNaziv' => null, 'SekcijaIzvor' => null,
            'Napomene' => [], 'Kandidati' => [], 'Odluka' => 'novi', 'TrebaProvjeru' => false,
        ];
        if ($r['Ime'] === '' && $r['Prezime'] === '') {
            continue;
        }
        for ($i = 1; $i <= 5; $i++) {
            $em = $p("E-mail $i - Value");
            if ($em !== null && $r['Email'] === null) {
                $r['Email'] = trim(explode(':::', $em)[0]);
            }
            $tel = $p("Phone $i - Value");
            if ($tel === null) {
                continue;
            }
            $oz = mb_strtolower($p("Phone $i - Label") ?? '');
            foreach (array_filter(array_map('trim', explode(':::', $tel))) as $broj) {
                $mob = str_contains($oz, 'mobile') || (!str_contains($oz, 'home') && !str_contains($oz, 'work') && je_mobilni($broj));
                if ($mob && $r['Mobilni'] === null) {
                    $r['Mobilni'] = $broj;
                } elseif (!$mob && $r['Fiksni'] === null) {
                    $r['Fiksni'] = $broj;
                } elseif ($r['Mobilni'] === null) {
                    $r['Mobilni'] = $broj;
                }
            }
        }
        $rod = $p('Birthday');
        if ($rod !== null) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rod)) {
                $r['DatumRodjenja'] = $rod;
            } else {
                $r['Napomene'][] = "Rođendan bez godine ($rod) – nije uvezen";
            }
        }
        $oznake = array_filter(array_map('trim', explode(':::', $p('Labels') ?? $p('Group Membership') ?? '')), fn($x) => $x !== '' && !str_starts_with($x, '*'));
        $sOz = null;
        foreach ($sekcije as $s) {
            foreach ($oznake as $o) {
                if (slicnost_norm($o) === slicnost_norm($s['Naziv'])) {
                    $sOz ??= $s;
                }
            }
        }
        $org = $p('Organization Name');
        $sOrg = null;
        foreach ($sekcije as $s) {
            if ($org !== null && slicnost_norm($org) === slicnost_norm($s['Naziv'])) {
                $sOrg = $s;
            }
        }
        $s = $sOz ?? $sOrg;
        if ($s) {
            $r['SekcijaId'] = (int) $s['Id'];
            $r['SekcijaNaziv'] = $s['Naziv'];
            $r['SekcijaIzvor'] = $sOz ? 'oznaka' : 'organizacija';
            if ($sOz && $sOrg && $sOz['Id'] !== $sOrg['Id']) {
                $r['Napomene'][] = "Oznaka „{$sOz['Naziv']}“, organizacija „{$sOrg['Naziv']}“ – uzeta oznaka";
            }
        }
        $kand = kandidati_slicnosti($r['Ime'], $r['Prezime'], $r['Mobilni'], $r['Fiksni'], $r['Email'], null, $postojeci);
        $r['Kandidati'] = array_map(fn($k) => ['Id' => (int) $k['clan']['Id'], 'ocjena' => $k['ocjena'], 'razlog' => $k['razlog']], $kand);
        $najbolji = $kand[0] ?? null;
        if ($najbolji && $najbolji['ocjena'] >= PRAG_SLICNOSTI) {
            $r['Odluka'] = 'spoji:' . $najbolji['clan']['Id'];
            $r['TrebaProvjeru'] = $najbolji['ocjena'] < 0.999 || count(array_filter($kand, fn($k) => $k['ocjena'] >= PRAG_SLICNOSTI)) > 1;
            if ((int) $najbolji['clan']['Status'] === CLAN_ARHIVIRAN) {
                $r['Napomene'][] = 'Postojeći član je arhiviran';
                $r['TrebaProvjeru'] = true;
            }
        } elseif ($najbolji) {
            $r['TrebaProvjeru'] = true;
        }
        foreach ($retci as $x) {
            if (slicnost_imena($x['Ime'], $x['Prezime'], $r['Ime'], $r['Prezime']) >= PRAG_SLICNOSTI) {
                $r['Napomene'][] = 'U datoteci već postoji „' . trim($x['Ime'] . ' ' . $x['Prezime']) . '“ – preskočeno';
                $r['Odluka'] = 'preskoci';
                $r['TrebaProvjeru'] = true;
                break;
            }
        }
        $retci[] = $r;
    }
    fclose($f);
    $coll = class_exists('Collator') ? new Collator('hr_HR') : null;
    usort($retci, fn($a, $b) => [$b['TrebaProvjeru'], 0] <=> [$a['TrebaProvjeru'], 0]
        ?: ($coll ? $coll->compare($a['Prezime'] . ' ' . $a['Ime'], $b['Prezime'] . ' ' . $b['Ime']) : strcmp(kljuc($a['Prezime'] . ' ' . $a['Ime']), kljuc($b['Prezime'] . ' ' . $b['Ime']))));
    return $retci;
}

/** Razlike između postojećeg člana i podataka iz Googlea: [[polje, staro, novo], …] */
function razlike_uvoza(array $r, array $c): array
{
    $rez = [];
    $p = function (string $polje, ?string $staro, ?string $novo, callable $norm) use (&$rez) {
        if (trim((string) $staro) !== '' && trim((string) $novo) !== '' && $norm($staro) !== $norm($novo)) {
            $rez[] = [$polje, $staro, $novo];
        }
    };
    $tel = fn($x) => slicnost_telefon($x) ?: trim($x);
    $p('Mobitel', $c['MobilniTelefon'], $r['Mobilni'], $tel);
    $p('Fiksni', $c['FiksniTelefon'], $r['Fiksni'], $tel);
    $p('E-mail', $c['Email'], $r['Email'], fn($x) => mb_strtolower(trim($x)));
    $p('Ulica', $c['Ulica'], $r['Ulica'], 'slicnost_norm');
    $p('Mjesto', $c['Mjesto'], $r['Mjesto'], 'slicnost_norm');
    if ($c['DatumRodjenja'] && $r['DatumRodjenja'] && substr($c['DatumRodjenja'], 0, 10) !== $r['DatumRodjenja']) {
        $rez[] = ['Datum rođenja', datum($c['DatumRodjenja']), datum($r['DatumRodjenja'])];
    }
    return $rez;
}
/** Što će se dopuniti (prazna polja kod postojećeg člana). */
function dopune_uvoza(array $r, array $c): array
{
    $d = [];
    foreach (['MobilniTelefon' => ['Mobilni', 'mobitel'], 'FiksniTelefon' => ['Fiksni', 'fiksni'], 'Email' => ['Email', 'e-mail'], 'Ulica' => ['Ulica', 'adresa'],
        'Mjesto' => ['Mjesto', 'mjesto'], 'DatumRodjenja' => ['DatumRodjenja', 'datum rođenja'], 'Nadimak' => ['Nadimak', 'nadimak'], 'SekcijaId' => ['SekcijaId', 'sekcija']] as $kc => [$kr, $naziv]) {
        if (trim((string) $c[$kc]) === '' && trim((string) $r[$kr]) !== '') {
            $d[] = $naziv;
        }
    }
    return $d;
}

function preuzmi_url(string $url): ?string
{
    if (!preg_match('#^https://#', $url)) {
        return null;
    }
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15, CURLOPT_MAXREDIRS => 3]);
        $d = curl_exec($c);
        $ok = curl_getinfo($c, CURLINFO_HTTP_CODE) === 200;
        curl_close($c);
        return $ok && is_string($d) ? $d : null;
    }
    $d = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 15]]));
    return $d === false ? null : $d;
}

/** Provodi uvoz. Vraća [oznaka, novi, spojeni, preskoceni, foto, noviIds]. */
function provedi_uvoz(array $retci, array $odluke, array $preuzmi, bool $preuzmiFoto): array
{
    $k = korisnik();
    $oznaka = date('Y-m-d H:i:s') . ' ' . $k['KorisnickoIme'];
    $novi = [];
    $spojeni = 0;
    $presk = 0;
    $foto = 0;
    @set_time_limit(300);
    transakcija(function () use ($retci, $odluke, $preuzmi, $preuzmiFoto, $oznaka, &$novi, &$spojeni, &$presk, &$foto) {
        foreach ($retci as $i => $r) {
            $od = (string) ($odluke[$i] ?? $r['Odluka']);
            if ($od === 'preskoci') {
                $presk++;
                continue;
            }
            $c = null;
            if (str_starts_with($od, 'spoji:')) {
                $c = clan((int) substr($od, 6));
            }
            if ($c) {
                $spojeni++;
                $pol = [];
                if (!empty($preuzmi[$i])) {
                    foreach (['MobilniTelefon' => 'Mobilni', 'FiksniTelefon' => 'Fiksni', 'Email' => 'Email', 'Ulica' => 'Ulica', 'Mjesto' => 'Mjesto', 'DatumRodjenja' => 'DatumRodjenja'] as $kc => $kr) {
                        if ($r[$kr] !== null) {
                            $pol[$kc] = $r[$kr];
                            $c[$kc] = $r[$kr];
                        }
                    }
                }
            } else {
                $id = umetni('Clanovi', [
                    'Ime' => $r['Ime'], 'Prezime' => $r['Prezime'], 'Status' => CLAN_AKTIVAN, 'Kreirano' => sada(), 'Azurirano' => sada(), 'UvozOznaka' => $oznaka,
                ]);
                $novi[] = $id;
                $c = clan($id);
                $pol = [];
            }
            $mapa = ['Nadimak' => 'Nadimak', 'Email' => 'Email', 'MobilniTelefon' => 'Mobilni', 'FiksniTelefon' => 'Fiksni', 'DatumRodjenja' => 'DatumRodjenja',
                'Ulica' => 'Ulica', 'PostanskiBroj' => 'PostanskiBroj', 'Mjesto' => 'Mjesto', 'SekcijaId' => 'SekcijaId', 'Napomena' => 'Napomena'];
            foreach ($mapa as $kc => $kr) {
                if (trim((string) $c[$kc]) === '' && trim((string) $r[$kr]) !== '') {
                    $pol[$kc] = $r[$kr];
                }
            }
            if ($preuzmiFoto && !$c['FotoDatoteka'] && $r['FotoUrl']) {
                $d = preuzmi_url($r['FotoUrl']);
                if ($d) {
                    $tmp = tempnam(sys_get_temp_dir(), 'ev');
                    file_put_contents($tmp, $d);
                    try {
                        $pol['FotoDatoteka'] = spremi_sliku(['tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'name' => 'google.jpg'], 'clan-' . $c['Id'] . '-', 800);
                        $foto++;
                    } catch (Throwable) {
                    }
                    @unlink($tmp);
                }
            }
            $pol['Azurirano'] = sada();
            azuriraj('Clanovi', (int) $c['Id'], $pol);
        }
    });
    povezi_racune();
    dnevnik('Uvoz iz Google kontakata', null, null, count($novi) . " novih, $spojeni spojeno, $presk preskočeno, $foto fotografija");
    return [$oznaka, count($novi), $spojeni, $presk, $foto, $novi];
}
