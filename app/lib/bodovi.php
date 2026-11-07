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
    return ['nacin' => isset(BODOVI_PRIJENOS[$m]) ? $m : 'nista', 'broj' => (float) (postavka('Bodovi.PrijenosBroj', '0') ?: 0), 'auto' => postavka('Bodovi.PrijenosAuto') === '1'];
}

/** Prijedlog prijenosa iz ukupnih bodova prošle godine po pravilu. */
function bodovi_prijenos_prijedlog(float $ukupno, ?array $pr = null): float
{
    $pr ??= bodovi_prijenos_pravilo();
    $u = max(0.0, $ukupno);
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

/** Upisuje / mijenja prijenos člana u bodovnu godinu $g (0 = bez bodova, ali označeno kao preneseno). */
function bodovi_prenesi(int $clanId, int $g, float $bodovi, ?array $k = null): void
{
    $bodovi = max(0.0, round($bodovi, 2));
    $postoji = red('SELECT * FROM BodoviPrijenos WHERE ClanId=? AND Godina=?', [$clanId, $g]);
    $aid = $postoji['AkcijaId'] ?? null;
    if ($aid && !vrijednost('SELECT 1 FROM RadneAkcije WHERE Id=?', [(int) $aid])) {
        $aid = null;
    }
    if ($bodovi > 0) {
        $polja = ['Bodovi' => dec($bodovi), 'Opis' => 'Preneseni bodovi iz ' . bodovna_godina_naziv($g - 1), 'Odluceno' => sada(), 'OdlucioIme' => $k['Naziv'] ?? 'automatski'];
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
 * Arhiva: bodovi po članu za bodovnu godinu $g, razdvojeno na preneseno / funkcije / radne akcije.
 * $uvjet = dodatni SQL uvjet za člana (alias c). Vraća retke sa stupcima Preneseno, Funkcije, Akcije, Ukupno.
 */
function bodovi_arhiva(int $g, string $uvjet = '1=1', array $p = []): array
{
    [$od, $do] = bodovna_godina_raspon($g);
    return redovi("SELECT c.Id, c.Ime, c.Prezime, c.Status, s.Naziv AS Sekcija,
            COALESCE(SUM(CASE WHEN bp.AkcijaId IS NOT NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Preneseno,
            COALESCE(SUM(CASE WHEN fb.AkcijaId IS NOT NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Funkcije,
            COALESCE(SUM(CASE WHEN bp.AkcijaId IS NULL AND fb.AkcijaId IS NULL THEN CAST(a.Bodovi AS REAL) END),0) AS Akcije,
            COALESCE(SUM(CAST(a.Bodovi AS REAL)),0) AS Ukupno
        FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
        LEFT JOIN RadneAkcije a ON a.ClanId=c.Id AND a.Status=1 AND substr(a.Datum,1,10) BETWEEN ? AND ?
        LEFT JOIN BodoviPrijenos bp ON bp.AkcijaId=a.Id
        LEFT JOIN FunkcijaBodovi fb ON fb.AkcijaId=a.Id
        WHERE $uvjet
        GROUP BY c.Id HAVING c.Status=0 OR COUNT(a.Id)>0
        ORDER BY Ukupno DESC, c.Prezime, c.Ime", array_merge([$od, $do], $p));
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
