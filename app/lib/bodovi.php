<?php
/**
 * Bodovna godina (dan resetiranja bodova), bodovi za funkciju u udruzi (jednom godišnje, odmah odobreni)
 * i prijenos bodova iz prošle u novu bodovnu godinu. Sve se vodi kao radne akcije, pa zbrojevi rade svugdje isto.
 */
declare(strict_types=1);

const BODOVI_PRIJENOS = [
    'nista' => 'Bez prijenosa – svi kreću od 0',
    'sve' => 'Prenesi sve bodove',
    'max' => 'Prenesi, ali najviše',
    'fiksno' => 'Fiksni iznos (tko ga nije dosegao → 0)',
];

/** Dan resetiranja bodova kao 'MM-DD' (zadano 04-01 = lovna godina). */
function bodovi_dan(): string
{
    $d = (string) postavka('Bodovi.Pocetak', '04-01');
    return preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $d) ? $d : '04-01';
}

/** Početak bodovne godine u kojoj je datum $d. */
function pocetak_bodovne_godine(?DateTimeImmutable $d = null): DateTimeImmutable
{
    $d ??= new DateTimeImmutable('today');
    $md = bodovi_dan();
    $g = $d->format('m-d') >= $md ? (int) $d->format('Y') : (int) $d->format('Y') - 1;
    return new DateTimeImmutable("$g-$md");
}

/** [od, do] ('Y-m-d') bodovne godine koja počinje u godini $g. */
function bodovna_godina_raspon(int $g): array
{
    $od = new DateTimeImmutable($g . '-' . bodovi_dan());
    return [$od->format('Y-m-d'), $od->modify('+1 year -1 day')->format('Y-m-d')];
}

/** Bodovna godina (godina početka) za datum. */
function bodovna_godina(?string $datum = null): int
{
    return (int) pocetak_bodovne_godine(new DateTimeImmutable($datum ?: 'today'))->format('Y');
}

/** Naziv bodovne godine: „2026.“ (od 1.1.) ili „2026./27.“ */
function bodovna_godina_naziv(int $g): string
{
    return bodovi_dan() === '01-01' ? "$g." : $g . './' . substr((string) ($g + 1), -2) . '.';
}

/** „lovnoj godini“ ili „bodovnoj godini“ – za tekstove. */
function bodovna_godina_rijec(): string
{
    return bodovi_dan() === '04-01' ? 'lovnoj godini' : 'bodovnoj godini';
}

/** 'MM-DD' → '01.04.' */
function bodovi_dan_prikaz(?string $md): string
{
    return $md && preg_match('/^(\d\d)-(\d\d)$/', $md, $m) ? "$m[2].$m[1]." : '';
}

/** '1.4.' / '01.04' / '1.4.2026' → 'MM-DD' ili null */
function bodovi_dan_unos(?string $s): ?string
{
    if (!preg_match('/^\s*(\d{1,2})\s*[.\/-]\s*(\d{1,2})/', (string) $s, $m)) {
        return null;
    }
    $d = (int) $m[1];
    $mj = (int) $m[2];
    if ($mj < 1 || $mj > 12 || $d < 1 || $d > [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][$mj - 1]) {
        return null;
    }
    return sprintf('%02d-%02d', $mj, $d);
}

/** Zbroj odobrenih bodova člana u razdoblju. */
function bodovi_clana(int $clanId, string $od, string $do): float
{
    return (float) vrijednost('SELECT COALESCE(SUM(CAST(Bodovi AS REAL)),0) FROM RadneAkcije WHERE ClanId=? AND Status=1 AND substr(Datum,1,10) BETWEEN ? AND ?', [$clanId, $od, $do]);
}

// ---------- Bodovi za funkciju ----------

/** Datum upisa bodova funkcije u godini $g ('Y-m-d') ili null. */
function funkcija_datum_bodova(array $f, int $g): ?string
{
    return !empty($f['BodoviDatum']) && (float) ($f['Bodovi'] ?? 0) > 0 ? $g . '-' . $f['BodoviDatum'] : null;
}

/**
 * Upisuje bodove funkcije za godinu $g svim aktivnim članovima koji su na dan upisa imali tu funkciju
 * (svaki član najviše jednom godišnje po funkciji). Odmah odobreno. Vraća broj novih upisa.
 */
function upisi_bodove_funkcije(array $f, int $g, ?array $k = null): int
{
    $datum = funkcija_datum_bodova($f, $g);
    if (!$datum) {
        return 0;
    }
    $clanovi = redovi('SELECT DISTINCT cf.ClanId FROM ClanFunkcije cf JOIN Clanovi c ON c.Id=cf.ClanId
        WHERE cf.FunkcijaId=? AND c.Status=0 AND (cf.Od IS NULL OR substr(cf.Od,1,10)<=?) AND (cf.Do IS NULL OR substr(cf.Do,1,10)>=?)
          AND NOT EXISTS (SELECT 1 FROM FunkcijaBodovi b WHERE b.ClanId=cf.ClanId AND b.FunkcijaId=cf.FunkcijaId AND b.Godina=?)',
        [(int) $f['Id'], $datum, $datum, $g]);
    $n = 0;
    transakcija(function () use ($clanovi, $f, $g, $datum, $k, &$n) {
        foreach ($clanovi as $c) {
            $aid = umetni('RadneAkcije', [
                'ClanId' => (int) $c['ClanId'], 'Datum' => $datum, 'VrstaAkcijeId' => null, 'VrstaSlobodno' => '🏅 Funkcija: ' . $f['Naziv'], 'Sati' => null,
                'Opis' => 'Bodovi za funkciju ' . $f['Naziv'] . ' (' . $g . '.)', 'Status' => AKCIJA_ODOBRENO, 'Bodovi' => dec((float) $f['Bodovi']),
                'RazlogOdbijanja' => null, 'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(),
                'OdlucioKorisnikId' => $k['Id'] ?? null, 'OdlucioIme' => $k['Naziv'] ?? 'automatski', 'Odluceno' => sada(),
            ]);
            umetni('FunkcijaBodovi', ['ClanId' => (int) $c['ClanId'], 'FunkcijaId' => (int) $f['Id'], 'Godina' => $g, 'AkcijaId' => $aid]);
            $n++;
        }
    });
    if ($n) {
        dnevnik('Bodovi za funkciju', null, null, $f['Naziv'] . " $g.: $n članova × " . broj((float) $f['Bodovi'], 2) . ' b.');
    }
    return $n;
}

/** Je li funkcija ove godine dospjela za upis (datum prošao), a nije sve upisano? Vraća datum ili null. */
function funkcija_dospjela(array $f, int $g): ?string
{
    $datum = funkcija_datum_bodova($f, $g);
    return $datum && $datum <= danas() ? $datum : null;
}

// ---------- Prijenos bodova ----------

function bodovi_prijenos_pravilo(): array
{
    $m = (string) postavka('Bodovi.Prijenos', 'nista');
    return ['nacin' => isset(BODOVI_PRIJENOS[$m]) ? $m : 'nista', 'broj' => (float) (postavka('Bodovi.PrijenosBroj', '0') ?: 0), 'auto' => postavka('Bodovi.PrijenosAuto') === '1',
        'minus' => postavka('Bodovi.PrijenosMinus') === '1'];
}

/** Prijedlog prijenosa iz ukupnih bodova prošle godine po pravilu. Minus (dug) se prenosi samo ako je tako postavljeno. */
function bodovi_prijenos_prijedlog(float $ukupno, ?array $pr = null): float
{
    $pr ??= bodovi_prijenos_pravilo();
    if ($ukupno < 0) {
        return !empty($pr['minus']) ? round($ukupno, 2) : 0.0;
    }
    $u = $ukupno;
    return match ($pr['nacin']) {
        'sve' => $u,
        'max' => min($u, max(0.0, $pr['broj'])),
        'fiksno' => $u >= $pr['broj'] && $pr['broj'] > 0 ? $pr['broj'] : 0.0,
        default => 0.0,
    };
}

/** Ukupni odobreni bodovi svih članova u bodovnoj godini $g: [clanId => bodovi] */
function bodovi_godine(int $g): array
{
    [$od, $do] = bodovna_godina_raspon($g);
    return array_map('floatval', array_column(redovi('SELECT ClanId, SUM(CAST(Bodovi AS REAL)) AS B FROM RadneAkcije WHERE Status=1 AND substr(Datum,1,10) BETWEEN ? AND ? GROUP BY ClanId', [$od, $do]), 'B', 'ClanId'));
}

/** Upisuje / mijenja prijenos člana u bodovnu godinu $g (0 = bez bodova, ali označeno kao preneseno; minus samo ako je dopušten). */
function bodovi_prenesi(int $clanId, int $g, float $bodovi, ?array $k = null): void
{
    $bodovi = round(bodovi_prijenos_pravilo()['minus'] ? $bodovi : max(0.0, $bodovi), 2);
    $postoji = red('SELECT * FROM BodoviPrijenos WHERE ClanId=? AND Godina=?', [$clanId, $g]);
    $aid = $postoji['AkcijaId'] ?? null;
    if ($aid && !vrijednost('SELECT 1 FROM RadneAkcije WHERE Id=?', [(int) $aid])) {
        $aid = null;
    }
    if ($bodovi != 0) {
        $polja = ['Bodovi' => dec($bodovi), 'Opis' => ($bodovi < 0 ? 'Preneseni minus iz ' : 'Preneseni bodovi iz ') . bodovna_godina_naziv($g - 1), 'Odluceno' => sada(), 'OdlucioIme' => $k['Naziv'] ?? 'automatski'];
        if ($aid) {
            azuriraj('RadneAkcije', (int) $aid, $polja);
        } else {
            $aid = umetni('RadneAkcije', $polja + [
                'ClanId' => $clanId, 'Datum' => bodovna_godina_raspon($g)[0], 'VrstaAkcijeId' => null, 'VrstaSlobodno' => '↪️ Preneseno iz ' . bodovna_godina_naziv($g - 1),
                'Sati' => null, 'Status' => AKCIJA_ODOBRENO, 'RazlogOdbijanja' => null, 'FotoDatoteka' => null, 'UnioKorisnikId' => $k['Id'] ?? null,
                'Kreirano' => sada(), 'OdlucioKorisnikId' => $k['Id'] ?? null,
            ]);
        }
    } elseif ($aid) {
        q('DELETE FROM RadneAkcije WHERE Id=?', [(int) $aid]);
        $aid = null;
    }
    q('INSERT OR REPLACE INTO BodoviPrijenos (ClanId, Godina, Bodovi, AkcijaId, Kreirano, UnioIme) VALUES (?,?,?,?,?,?)',
        [$clanId, $g, dec($bodovi), $aid, sada(), $k['Naziv'] ?? 'automatski']);
}

/** Automatski prijenos u tekuću bodovnu godinu svim aktivnim članovima koji ga još nemaju. Vraća broj članova. */
function bodovi_auto_prijenos(int $g): int
{
    $pr = bodovi_prijenos_pravilo();
    $staro = bodovi_godine($g - 1);
    $n = 0;
    transakcija(function () use ($g, $pr, $staro, &$n) {
        foreach (redovi('SELECT Id FROM Clanovi c WHERE Status=0 AND NOT EXISTS (SELECT 1 FROM BodoviPrijenos p WHERE p.ClanId=c.Id AND p.Godina=?)', [$g]) as $c) {
            bodovi_prenesi((int) $c['Id'], $g, bodovi_prijenos_prijedlog($staro[(int) $c['Id']] ?? 0.0, $pr));
            $n++;
        }
    });
    return $n;
}

/** Jednom dnevno: dospjeli bodovi za funkcije + (ako je uključeno) automatski prijenos u novu bodovnu godinu. */
function bodovi_dnevno(): void
{
    $oznaka = podaci('.bodovi-dan');
    if (is_file($oznaka) && file_get_contents($oznaka) === danas()) {
        return;
    }
    @file_put_contents($oznaka, danas());
    try {
        $g = (int) date('Y');
        foreach (redovi("SELECT * FROM Funkcije WHERE BodoviDatum IS NOT NULL AND CAST(COALESCE(Bodovi,'0') AS REAL) > 0") as $f) {
            $datum = funkcija_dospjela($f, $g);
            // tek od datuma kad su bodovi postavljeni – ništa unatrag (za to je gumb „Upiši sada“)
            if ($datum && $datum >= substr((string) ($f['BodoviOd'] ?? ''), 0, 10)) {
                upisi_bodove_funkcije($f, $g);
            }
        }
    } catch (Throwable $e) {
        error_log('Bodovi za funkcije: ' . $e->getMessage());
    }
    try {
        obveze_dnevno(); // godišnji bodovi za obveze (hranilište, čeka …)
    } catch (Throwable $e) {
        error_log('Bodovi za obveze: ' . $e->getMessage());
    }
    try {
        $pr = bodovi_prijenos_pravilo();
        $bg = bodovna_godina();
        if ($pr['auto'] && $pr['nacin'] !== 'nista' && (int) postavka('Bodovi.Preneseno', '0') < $bg) {
            $n = bodovi_auto_prijenos($bg);
            spremi_postavku('Bodovi.Preneseno', (string) $bg);
            dnevnik('Automatski prijenos bodova', null, null, bodovna_godina_naziv($bg) . ": $n članova");
        }
    } catch (Throwable $e) {
        error_log('Prijenos bodova: ' . $e->getMessage());
    }
}

/**
 * Arhiva: bodovi po članu za bodovnu godinu $g, razdvojeno na preneseno / funkcije / radne akcije / oduzeto za odstrel.
 * $uvjet = dodatni SQL uvjet za člana (alias c). Vraća retke sa stupcima Preneseno, Funkcije, Akcije, Odstrel, Ukupno
 * te Staro (preneseno umanjeno za oduzeto iz starih) i Novo (ostatak).
 */
function bodovi_arhiva(int $g, string $uvjet = '1=1', array $p = []): array
{
    [$od, $do] = bodovna_godina_raspon($g);
    $r = redovi("SELECT c.Id, c.Ime, c.Prezime, c.Status, s.Naziv AS Sekcija,
            COALESCE(SUM(CASE WHEN bp.AkcijaId IS NOT NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Preneseno,
            COALESCE(SUM(CASE WHEN fb.AkcijaId IS NOT NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Funkcije,
            COALESCE(SUM(CASE WHEN ob.AkcijaId IS NOT NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Odstrel,
            COALESCE(SUM(CASE WHEN ob.AkcijaId IS NOT NULL THEN CAST(ob.Staro AS REAL) END),0) AS OdstrelStaro,
            COALESCE(SUM(CASE WHEN bp.AkcijaId IS NULL AND fb.AkcijaId IS NULL AND ob.AkcijaId IS NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Akcije,
            COALESCE(SUM(CAST(a.Bodovi AS REAL)),0) AS Ukupno
        FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
        LEFT JOIN RadneAkcije a ON a.ClanId=c.Id AND a.Status=1 AND substr(a.Datum,1,10) BETWEEN ? AND ?
        LEFT JOIN BodoviPrijenos bp ON bp.AkcijaId=a.Id
        LEFT JOIN FunkcijaBodovi fb ON fb.AkcijaId=a.Id
        LEFT JOIN OdstrelBodovi ob ON ob.AkcijaId=a.Id
        WHERE $uvjet
        GROUP BY c.Id HAVING c.Status=0 OR COUNT(a.Id)>0
        ORDER BY Ukupno DESC, c.Prezime, c.Ime", array_merge([$od, $do], $p));
    foreach ($r as &$x) {
        $x['Staro'] = (float) $x['Preneseno'] - (float) $x['OdstrelStaro'];
        $x['Novo'] = (float) $x['Ukupno'] - $x['Staro'];
    }
    return $r;
}

/** Stanje bodova člana u bodovnoj godini $g: ['staro' => …, 'novo' => …, 'ukupno' => …]; $bezOdstrela = ne računaj oduzimanje tog odstrela. */
function bodovi_stanje(int $clanId, int $g, ?int $bezOdstrela = null): array
{
    $r = bodovi_arhiva($g, 'c.Id=?', [$clanId])[0] ?? ['Staro' => 0.0, 'Novo' => 0.0, 'Ukupno' => 0.0];
    $s = ['staro' => (float) $r['Staro'], 'novo' => (float) $r['Novo'], 'ukupno' => (float) $r['Ukupno']];
    $ob = $bezOdstrela ? red('SELECT ob.Staro, ob.Novo FROM OdstrelBodovi ob JOIN RadneAkcije a ON a.Id=ob.AkcijaId AND a.Status=1 WHERE ob.OdstrelId=? AND a.ClanId=?', [$bezOdstrela, $clanId]) : null;
    if ($ob && bodovna_godina((string) vrijednost('SELECT Datum FROM Odstrel WHERE Id=?', [$bezOdstrela])) === $g) {
        $s['staro'] += (float) $ob['Staro'];
        $s['novo'] += (float) $ob['Novo'];
        $s['ukupno'] += (float) $ob['Staro'] + (float) $ob['Novo'];
    }
    return $s;
}

/** „Staro: 300 · Novo: 250 · Ukupno: 550“ – staro crveno, novo crno. */
function bodovi_stanje_html(array $s): string
{
    return '<span class="text-danger">Staro: <b>' . e(broj($s['staro'], 2)) . '</b></span> · <span class="text-body">Novo: <b>' . e(broj($s['novo'], 2)) . '</b></span>'
        . ' · Ukupno: <b>' . e(broj($s['ukupno'], 2)) . '</b>';
}

/** Oznaka posebnih upisa u popisu bodova: preneseno (staro) i oduzeto za odstrel. */
function bodovi_oznaka_html(array $a): string
{
    $v = (string) ($a['VrstaSlobodno'] ?? '');
    return match (true) {
        str_starts_with($v, '↪️ Preneseno') => ' <span class="badge text-bg-danger">staro</span>',
        str_starts_with($v, '🦌 Odstrel') => ' <span class="badge text-bg-dark">🦌 odstrel</span>',
        default => '',
    };
}

// ---------- Bodovi za odstrel ----------

/** Oduzimanje bodova za odstrel uključeno (modul + Odstrel + Radne akcije)? */
function bodovi_odstrel_ukljuceno(): bool
{
    return modul('bodovi_odstrel') && modul('odstrel') && modul('akcije');
}

/** Iznosi po vrsti i kategoriji: ['Vrsta|Kategorija' => ['staro' => …, 'novo' => …]] (prazna kategorija = cijela vrsta). */
function bodovi_odstrel_iznosi(): array
{
    $j = json_decode((string) postavka('Bodovi.Odstrel', ''), true);
    $out = [];
    foreach (is_array($j) ? $j : [] as $kljuc => $v) {
        $s = max(0.0, (float) ($v['staro'] ?? 0));
        $n = max(0.0, (float) ($v['novo'] ?? 0));
        if ($s > 0 || $n > 0) {
            $out[(string) $kljuc] = ['staro' => $s, 'novo' => $n];
        }
    }
    return $out;
}

/** Iznos za vrstu i kategoriju (točna kategorija, inače cijela vrsta) ili null. */
function bodovi_odstrel_iznos(string $vrsta, ?string $kategorija): ?array
{
    $iz = bodovi_odstrel_iznosi();
    return $iz[$vrsta . '|' . (string) $kategorija] ?? $iz[$vrsta . '|'] ?? null;
}

/**
 * Nakon upisa / izmjene odstrela: oduzima (ili ispravlja) bodove lovca. Odstrel upisan prije uključivanja se ne dira.
 * Iznos iz postavki (Sustav → Bodovi); ako ga za vrstu/kategoriju nema, vrijedi ručni upis iz obrasca ($rucno, prazno = ništa).
 * Bodovi mogu otići u minus – tada vraća upozorenje za upisivača (odstrel se uvijek sprema).
 */
function bodovi_odstrel_obracunaj(array $o, bool $novi, ?array $k = null, ?array $rucno = null): ?string
{
    $oid = (int) $o['Id'];
    $veza = red('SELECT * FROM OdstrelBodovi WHERE OdstrelId=?', [$oid]);
    if (!bodovi_odstrel_ukljuceno() || (!$veza && !$novi)) {
        return null; // modul isključen ili stari upis: bodovi ostaju kakvi jesu (brisanje odstrela ih i dalje vraća)
    }
    $clanId = !$o['Gost'] && $o['ClanId'] ? (int) $o['ClanId'] : 0;
    $iznos = $clanId ? bodovi_odstrel_iznos((string) $o['Vrsta'], $o['Spol']) : null;
    if ($clanId && !$iznos && $rucno && bodovi_odstrel_ukljuceno()) {
        $r = ['staro' => max(0.0, (float) ($rucno['staro'] ?? 0)), 'novo' => max(0.0, (float) ($rucno['novo'] ?? 0))];
        $iznos = $r['staro'] > 0 || $r['novo'] > 0 ? $r : null; // ručni upis u obrascu (nema iznosa u postavkama)
    }
    $aid = $veza && $veza['AkcijaId'] && vrijednost('SELECT 1 FROM RadneAkcije WHERE Id=?', [(int) $veza['AkcijaId']]) ? (int) $veza['AkcijaId'] : null;
    $upozorenje = null;
    if ($iznos) {
        $ukupno = $iznos['staro'] + $iznos['novo'];
        $stanje = bodovi_stanje($clanId, bodovna_godina((string) $o['Datum']), $oid);
        $manjka = array_filter(['starih' => [$stanje['staro'], $iznos['staro']], 'novih' => [$stanje['novo'], $iznos['novo']]], fn($x) => $x[1] > 0 && $x[0] < $x[1]);
        if ($manjka) {
            $upozorenje = 'Pažnja: ' . e((string) $o['Lovac']) . ' ima premalo bodova (' . implode(', ', array_map(fn($n, $x) => $n . ' ' . broj($x[0], 2) . ' od ' . broj($x[1], 2), array_keys($manjka), $manjka))
                . ') – bodovi idu u minus.';
        }
        $polja = [
            'ClanId' => $clanId, 'Datum' => (string) $o['Datum'], 'Bodovi' => dec(-$ukupno),
            'VrstaSlobodno' => '🦌 Odstrel: ' . $o['Vrsta'] . ($o['Spol'] ? ' – ' . $o['Spol'] : ''),
            'Opis' => trim(($o['Markica'] ? 'markica ' . $o['Markica'] . ' · ' : '') . 'oduzeto staro ' . broj($iznos['staro'], 2) . ' + novo ' . broj($iznos['novo'], 2)),
            'Odluceno' => sada(), 'OdlucioIme' => 'automatski (odstrel)',
        ];
        if ($aid) {
            azuriraj('RadneAkcije', $aid, $polja);
        } else {
            $aid = umetni('RadneAkcije', $polja + ['VrstaAkcijeId' => null, 'Sati' => null, 'Status' => AKCIJA_ODOBRENO, 'RazlogOdbijanja' => null, 'FotoDatoteka' => null,
                'UnioKorisnikId' => $k['Id'] ?? null, 'Kreirano' => sada(), 'OdlucioKorisnikId' => $k['Id'] ?? null]);
        }
        dnevnik('Bodovi za odstrel oduzeti', 'Odstrel', $oid, $o['Lovac'] . ': −' . broj($ukupno, 2) . ' b. (staro ' . broj($iznos['staro'], 2) . ', novo ' . broj($iznos['novo'], 2) . ')');
    } elseif ($aid) {
        q('DELETE FROM RadneAkcije WHERE Id=?', [$aid]);
        $aid = null;
        dnevnik('Bodovi za odstrel vraćeni', 'Odstrel', $oid, (string) $o['Lovac'] . ' (nema iznosa za ' . $o['Vrsta'] . ')');
    }
    q('INSERT OR REPLACE INTO OdstrelBodovi (OdstrelId, AkcijaId, Staro, Novo) VALUES (?,?,?,?)',
        [$oid, $aid, dec($iznos['staro'] ?? 0.0), dec($iznos['novo'] ?? 0.0)]);
    return $upozorenje;
}

/** Prije brisanja odstrela: vraća oduzete bodove (briše radnu akciju i vezu). */
function bodovi_odstrel_ukloni(int $odstrelId): void
{
    $veza = red('SELECT * FROM OdstrelBodovi WHERE OdstrelId=?', [$odstrelId]);
    if (!$veza) {
        return;
    }
    if ($veza['AkcijaId']) {
        q('DELETE FROM RadneAkcije WHERE Id=?', [(int) $veza['AkcijaId']]);
        dnevnik('Bodovi za odstrel vraćeni', 'Odstrel', $odstrelId, 'odstrel obrisan');
    }
    q('DELETE FROM OdstrelBodovi WHERE OdstrelId=?', [$odstrelId]);
}

/** Je li radna akcija oduzimanje za odstrel (ne smije se ručno mijenjati)? */
function je_akcija_odstrela(int $akcijaId): bool
{
    return (bool) vrijednost('SELECT 1 FROM OdstrelBodovi WHERE AkcijaId=?', [$akcijaId]);
}

/** Bodovne godine od prve radne akcije do tekuće (silazno). */
function bodovne_godine_popis(): array
{
    $prva = (string) (vrijednost('SELECT MIN(substr(Datum,1,10)) FROM RadneAkcije') ?: danas());
    $r = [];
    for ($g = bodovna_godina(); $g >= min(bodovna_godina($prva), bodovna_godina()); $g--) {
        $r[] = $g;
    }
    return $r;
}
