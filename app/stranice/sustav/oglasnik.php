<?php
/** Zajednički Oglasnik lovaca: registracija udruge, pravila za članove, uključivanje. */
trazi(P_SUSTAV);

if (je_post() && !je_demo() && ogl_podrzano()) {
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    if ($radnja === 'novi_kljuc') {
        ogl_generiraj_kljuc();
        dnevnik('Oglasnik – novi ključ', null, null, ogl_kod());
        poruka('Napravljen je novi ključ i poslan Oglasniku (potpisan starim ključem – udruga ostaje ista, bez novog odobrenja).');
        preusmjeri('oglasnik-registracija');
    }
    $adr = rtrim(ul_str('adresa'), '/');
    if ($adr !== '' && !preg_match('#^https://[a-z0-9.\-]+(:\d+)?(/[^\s]*)?$#i', $adr)) {
        poruka('Adresa Oglasnika mora počinjati s https://', 'danger');
        preusmjeri('sustav/oglasnik');
    }
    spremi_postavku('Oglasnik.Adresa', $adr !== '' && $adr !== OGL_ZADANA_ADRESA ? $adr : null);
    spremi_postavku('Oglasnik.Objava', ul_str('objava') === 'public' ? 'public' : 'internal');
    spremi_postavku('Oglasnik.VidiJavne', isset($_POST['vidi_javne']) ? '1' : '0');
    spremi_postavku('Oglasnik.Telefon', isset($_POST['telefon']) ? '1' : '0');
    spremi_postavku('Oglasnik.Odobravanje', ul_str('odobravanje') === 'approval' ? 'approval' : 'instant');
    spremi_postavku('Oglasnik.Poruke', isset($_POST['poruke']) ? '1' : '0');
    spremi_postavku('Oglasnik.KontaktIme', ul_null('kontakt_ime'));
    spremi_postavku('Oglasnik.KontaktTelefon', ul_null('kontakt_telefon'));
    spremi_postavku('Oglasnik.KontaktEmail', ul_null('kontakt_email'));
    if ($radnja === 'registracija') {
        if (!ogl_javni_kljuc()) {
            ogl_generiraj_kljuc();
            dnevnik('Oglasnik – ključ napravljen', null, null, ogl_kod());
        }
        preusmjeri('oglasnik-registracija');
    }
    $ukljuci = isset($_POST['aktivan']) && ogl_javni_kljuc() !== null;
    spremi_postavku('Oglasnik.Aktivan', $ukljuci ? '1' : '0');
    dnevnik('Oglasnik – postavke', null, null, ($ukljuci ? 'uključen' : 'isključen') . ', objava: ' . postavka('Oglasnik.Objava'));
    poruka('Postavke Oglasnika su spremljene.');
    preusmjeri('sustav/oglasnik');
}

$k = korisnik();
$ja = $k['ClanId'] ? red('SELECT Ime, Prezime, Email, MobilniTelefon FROM Clanovi WHERE Id=?', [$k['ClanId']]) : null;
$kontakt = ogl_kontakt();
if ($kontakt['name'] === '' && $ja) {
    $kontakt = ['name' => trim($ja['Ime'] . ' ' . $ja['Prezime']), 'phone' => (string) $ja['MobilniTelefon'], 'email' => (string) $ja['Email']];
}
$imaKljuc = ogl_javni_kljuc() !== null;
$zahtjev = postavka('Oglasnik.Zahtjev');
$pol = ogl_politika();
$stariOglasi = (int) vrijednost('SELECT COUNT(*) FROM Oglasi WHERE Status IN (0,1)');
ob_start(); ?>
<h1 class="h3 mb-1">Oglasnik lovaca</h1>
<p class="text-muted">Zajednički oglasnik za lovce svih udruga koje koriste ovu aplikaciju. Članovi ulaze iz izbornika <b>Oglasnik</b> – bez posebne prijave.
    Što vide i smiju određujete vi, ovdje.</p>

<?php if (je_demo()): ?>
    <div class="alert alert-info">U demo verziji Oglasnik nije spojen na pravi zajednički Oglasnik.</div>
<?php elseif (!ogl_podrzano()): ?>
    <div class="alert alert-danger">Na poslužitelju nedostaje PHP proširenje <b>sodium</b> (potrebno za siguran potpis). Zamolite pružatelja hostinga da ga uključi.</div>
<?php else: ?>
<div class="row g-4">
<div class="col-lg-8">
<form method="post" action="<?= e(url('sustav/oglasnik')) ?>" autocomplete="off"><?= csrf() ?>

    <div class="card mb-3"><div class="card-header">1. Registracija udruge u Oglasniku</div><div class="card-body">
        <?php if (!$imaKljuc): ?>
            <p>Klikom na gumb aplikacija napravi <b>ključ udruge</b> i pošalje <b>zahtjev</b> Oglasniku (naziv udruge i kontakt iz točke 3).
                Administrator Oglasnika zahtjev odobri – nakon toga uključite Oglasnik (točka 4).</p>
            <button class="btn btn-primary" name="radnja" value="registracija">Pošalji zahtjev za registraciju</button>
        <?php else: ?>
            <p class="mb-2">Kod udruge: <code class="fs-6"><?= e(ogl_kod()) ?></code>
                <?php if ($zahtjev): ?><span class="text-muted small"> · zahtjev poslan <?= e(datum_vrijeme($zahtjev)) ?></span><?php endif; ?></p>
            <p class="small text-muted mb-2">Je li udruga odobrena, vidjet ćete kad otvorite Oglasnik (izbornik Članovi → Oglasnik) nakon uključivanja.
                Ako zahtjev nije stigao, pošaljite ga ponovno.</p>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary btn-sm" name="radnja" value="registracija">Pošalji zahtjev ponovno</button>
                <button class="btn btn-outline-danger btn-sm" name="radnja" value="novi_kljuc" data-potvrda="Napraviti novi ključ? (npr. ako sumnjate da je stari ključ otkriven) Novi ključ odmah se šalje Oglasniku.">Novi ključ</button>
            </div>
            <details class="small mt-2"><summary>Javni ključ</summary><code class="d-block text-break mt-1"><?= e(ogl_javni_kljuc()) ?></code>
                <span class="text-muted">Javni ključ nije tajna. Privatni ključ ostaje samo u ovoj aplikaciji (šifrirano).</span></details>
        <?php endif; ?>
    </div></div>

    <div class="card mb-3"><div class="card-header">2. Pravila za članove vaše udruge</div><div class="card-body">
        <label class="form-label">Članovi smiju objaviti oglas</label>
        <div class="form-check"><input class="form-check-input" type="radio" name="objava" id="ob1" value="internal"<?= chk($pol['publish'] === 'internal') ?>>
            <label class="form-check-label" for="ob1"><b>samo za udrugu</b> – oglas vide samo članovi vaše udruge</label></div>
        <div class="form-check mb-3"><input class="form-check-input" type="radio" name="objava" id="ob2" value="public"<?= chk($pol['publish'] === 'public') ?>>
            <label class="form-check-label" for="ob2"><b>za udrugu ili za sve udruge</b> – član pri objavi bira „interno“ ili „javno“</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="vidi_javne" id="vj" value="1"<?= chk($pol['see_public']) ?>>
            <label class="form-check-label" for="vj">Članovi vide javne oglase drugih udruga</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="telefon" id="tel" value="1"<?= chk($pol['phone_allowed']) ?>>
            <label class="form-check-label" for="tel">Članovi smiju svoj broj telefona prikazati i članovima drugih udruga (inače samo vlastitoj udruzi)</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="poruke" id="por" value="1"<?= chk($pol['cross_chat']) ?>>
            <label class="form-check-label" for="por">Poruke s članovima drugih udruga dopuštene</label></div>
        <label class="form-label mt-3">Javni oglasi</label>
        <select name="odobravanje" class="form-select" style="max-width:420px">
            <option value="instant"<?= sel($pol['public_approval'], 'instant') ?>>objavljuju se odmah</option>
            <option value="approval"<?= sel($pol['public_approval'], 'approval') ?>>tek nakon odobrenja (administrator / moderator udruge)</option>
        </select>
        <div class="form-text">Moderatore odredite ulogom s pravom „Oglasnik – moderacija“ (Sustav → Uloge i prava). Administratori ga imaju automatski.</div>
    </div></div>

    <div class="card mb-3"><div class="card-header">3. Kontakt za Oglasnik</div><div class="card-body">
        <p class="small text-muted">Prikazuje se članovima kad im je pristup ograničen („obratite se administratoru svoje udruge“) i administratoru Oglasnika uz zahtjev.</p>
        <div class="row g-2">
            <div class="col-md-4"><input name="kontakt_ime" class="form-control" placeholder="Ime i prezime" value="<?= e($kontakt['name']) ?>"></div>
            <div class="col-md-4"><input name="kontakt_telefon" class="form-control" placeholder="Telefon" value="<?= e($kontakt['phone']) ?>"></div>
            <div class="col-md-4"><input name="kontakt_email" type="email" class="form-control" placeholder="E-pošta" value="<?= e($kontakt['email']) ?>"></div>
        </div>
    </div></div>

    <div class="card mb-3"><div class="card-header">4. Uključivanje</div><div class="card-body">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="aktivan" id="akt" value="1"<?= chk(ogl_aktivan()) ?><?= $imaKljuc ? '' : ' disabled' ?>>
            <label class="form-check-label" for="akt"><b>Oglasnik uključen</b> – izbornik „Oglasnik“ vodi članove u zajednički Oglasnik</label></div>
        <?php if (!$imaKljuc): ?><div class="form-text">Najprije pošaljite zahtjev za registraciju (točka 1).</div><?php endif; ?>
        <div class="alert alert-secondary small mt-3 mb-0">Nakon uključivanja otvorite Oglasnik jednom sami: kao administrator udruge prihvaćate <b>obveze administratora</b>
            (obrada prijava oglasa vaših članova u roku 48 sati). Dok to ne napravite, Oglasnik je za vaše članove zatvoren.</div>
    </div></div>

    <details class="mb-3"><summary class="small text-muted">Napredno</summary>
        <label class="form-label mt-2">Adresa Oglasnika</label>
        <input name="adresa" class="form-control" style="max-width:420px" value="<?= e(ogl_adresa()) ?>">
    </details>

    <button class="btn btn-primary" name="radnja" value="spremi">Spremi postavke</button>
</form>
</div>
<div class="col-lg-4">
    <div class="card"><div class="card-header">Stanje</div>
        <ul class="list-group list-group-flush small">
            <li class="list-group-item"><span class="text-muted">Oglasnik</span><br><?= ogl_aktivan() ? '<span class="badge bg-success">uključen</span>' : '<span class="badge bg-secondary">isključen</span>' ?></li>
            <li class="list-group-item"><span class="text-muted">Ključ udruge</span><br><?= $imaKljuc ? '<span class="badge bg-success">postoji</span>' : '<span class="badge bg-secondary">nema</span>' ?></li>
            <li class="list-group-item"><span class="text-muted">Adresa</span><br><?= e(ogl_adresa()) ?></li>
            <?php if ($stariOglasi): ?><li class="list-group-item"><span class="text-muted">Interni oglasnik</span><br><?= $stariOglasi ?> aktivnih oglasa – i dalje dostupni pod „Interni oglasi“.</li><?php endif; ?>
        </ul>
        <div class="card-body small text-muted">Dok je Oglasnik isključen, članovi koriste dosadašnji interni oglasnik udruge.</div>
    </div>
</div>
</div>
<?php endif; ?>
<?php stranica('Oglasnik lovaca', ob_get_clean());
