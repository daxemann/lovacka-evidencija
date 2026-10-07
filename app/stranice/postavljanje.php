<?php
$postoji = (bool) vrijednost('SELECT 1 FROM Korisnici LIMIT 1');
if (je_post() && !$postoji) {
    $kor = mb_strtolower(ul_str('korisnik'));
    $loz = (string) ($_POST['lozinka'] ?? '');
    $greska = pravila_lozinke($loz);
    if ($kor === '') {
        $greska = 'Unesite korisničko ime.';
    } elseif ($loz !== (string) ($_POST['lozinka2'] ?? '')) {
        $greska = 'Lozinke se ne podudaraju.';
    }
    if ($greska) {
        poruka(e($greska), 'danger');
        preusmjeri('postavljanje');
    }
    transakcija(function () use ($kor, $loz) {
        $ime = ul_str('ime');
        $id = umetni('Korisnici', [
            'KorisnickoIme' => $kor, 'LozinkaHash' => hash_lozinke($loz), 'ClanId' => null,
            'PrikaznoIme' => $ime !== '' ? $ime : $kor, 'Aktivan' => 1, 'MoraPromijenitiLozinku' => 0,
            'Kreirano' => sada(), 'NeuspjelePrijave' => 0, 'SigurnosniZig' => novi_zig(), 'Odobren' => 1,
        ]);
        umetni('KorisnikUloge', ['KorisnikId' => $id, 'UlogaId' => 1, 'SekcijaId' => null]);
        $udr = ul_str('udruga');
        if ($udr !== '') {
            spremi_postavku('Udruga.Naziv', $udr);
            spremi_postavku('Udruga.Kratko', ul_str('kratko') !== '' ? ul_str('kratko') : mb_substr($udr, 0, 30));
        }
        if (!vrijednost('SELECT 1 FROM Clanovi LIMIT 1')) {
            $nazivi = [];
            foreach (preg_split('/\R/', (string) ($_POST['sekcije'] ?? '')) as $s) {
                $s = mb_substr(trim($s), 0, 100);
                if ($s !== '' && !in_array(mb_strtolower($s), array_map('mb_strtolower', $nazivi), true)) {
                    $nazivi[] = $s;
                }
            }
            if (!$nazivi) {
                $nazivi[] = $udr !== '' ? udruga_kratko() : 'Udruga';
            }
            q('DELETE FROM Sekcije');
            foreach ($nazivi as $i => $n) {
                umetni('Sekcije', ['Naziv' => $n, 'Aktivna' => 1, 'Redoslijed' => $i + 1]);
            }
        }
    });
    preusmjeri('prijava', ['postavljeno' => 1]);
}
ob_start(); ?>
<h1 class="h4 mb-3">Prvo pokretanje</h1>
<?php if ($postoji): ?>
    <div class="alert alert-info">Administrator je već postavljen. <a href="<?= e(url('prijava')) ?>">Na prijavu</a></div>
<?php else: ?>
    <p class="text-muted">Postavite račun glavnog administratora. On ima sva prava i može kasnije dodati ostale korisnike.</p>
    <form method="post" action="<?= e(url('postavljanje')) ?>">
        <?= csrf() ?>
        <h2 class="h6 text-uppercase text-muted mt-2">1. Udruga</h2>
        <div class="mb-3"><label class="form-label">Puni naziv udruge</label>
            <input name="udruga" class="form-control" required maxlength="150" placeholder="npr. Lovačka udruga „Srnjak“ Donji Grad"></div>
        <div class="mb-3"><label class="form-label">Kratki naziv (izbornik)</label>
            <input name="kratko" class="form-control" maxlength="30" placeholder="npr. LU Srnjak"></div>
        <div class="mb-3"><label class="form-label">Sekcije / lovne jedinice</label>
            <textarea name="sekcije" class="form-control" rows="3" placeholder="jedna po retku, npr.&#10;Sjever&#10;Jug"></textarea>
            <div class="form-text">Sekcije su međusobno odvojene (članovi jedne ne vide drugu). Ako udruga nema sekcije, ostavite prazno. Sve se kasnije može mijenjati u izborniku Sustav (logo, funkcije, vrste radnih akcija…).</div></div>
        <h2 class="h6 text-uppercase text-muted mt-4">2. Glavni administrator</h2>
        <div class="mb-3"><label class="form-label">Ime i prezime</label>
            <input name="ime" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Korisničko ime</label>
            <input name="korisnik" class="form-control" value="admin" required autocomplete="username"></div>
        <div class="mb-3"><label class="form-label">Lozinka (min. 8 znakova, slova i brojke)</label>
            <input name="lozinka" type="password" class="form-control" required autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label">Ponovi lozinku</label>
            <input name="lozinka2" type="password" class="form-control" required autocomplete="new-password"></div>
        <button type="submit" class="btn btn-primary w-100">Spremi</button>
    </form>
    <hr class="my-4">
    <h2 class="h6 text-uppercase text-muted">… ili preseljenje postojeće evidencije</h2>
    <p class="small text-muted">Na starom poslužitelju: Sustav → Sigurnosne kopije → <b>Preuzmi kompletnu kopiju (ZIP)</b>. Ovdje je učitajte – vraćaju se svi članovi, fotografije, korisnici i postavke (radi i s kopijom iz Windows / Home Assistant verzije). Prijava zatim s dosadašnjim korisničkim imenom i lozinkom.</p>
    <form method="post" action="<?= e(url('vrati')) ?>" enctype="multipart/form-data">
        <?= csrf() ?>
        <input type="file" name="kopija" accept=".zip" class="form-control mb-2" required>
        <button type="submit" class="btn btn-outline-primary w-100">Vrati iz kompletne kopije</button>
    </form>
<?php endif; ?>
<?php stranica('Prvo pokretanje', ob_get_clean(), 'prazno');
