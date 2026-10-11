<?php
/** Primatelj plaćanja za uplatnice HUB-3A (IBAN udruge, naziv, adresa, poziv na broj, opis). */
trazi(P_SUSTAV);
if (je_post()) {
    $iban = strtoupper((string) preg_replace('/\s+/', '', ul_str('iban')));
    $model = strtoupper(ul_str('model')) ?: 'HR00';
    $sifra = strtoupper(ul_str('sifra'));
    $greske = [];
    if ($iban !== '' && !ispravan_iban($iban)) {
        $greske[] = 'IBAN nije ispravan (provjerite znamenke).';
    }
    if (!preg_match('/^HR\d{2}$/', $model)) {
        $greske[] = 'Model mora biti oblika HR00.';
    }
    if ($sifra !== '' && !preg_match('/^[A-Z]{4}$/', $sifra)) {
        $greske[] = 'Šifra namjene ima točno 4 slova (npr. OTHR) ili ostaje prazna.';
    }
    if ($greske) {
        $_SESSION['uplatnica_obrazac'] = $_POST;
        poruka(e(implode(' ', $greske)), 'danger');
        preusmjeri('sustav/uplatnica');
    }
    spremi_postavku('Uplatnica.Iban', $iban ?: null);
    spremi_postavku('Uplatnica.Naziv', mb_substr(ul_str('naziv'), 0, 25) ?: null);
    spremi_postavku('Uplatnica.Adresa', mb_substr(ul_str('adresa'), 0, 25) ?: null);
    spremi_postavku('Uplatnica.Mjesto', mb_substr(ul_str('mjesto'), 0, 27) ?: null);
    spremi_postavku('Uplatnica.Model', $model);
    spremi_postavku('Uplatnica.Poziv', mb_substr(ul_str('poziv'), 0, 60) ?: null);
    spremi_postavku('Uplatnica.Sifra', $sifra ?: null);
    spremi_postavku('Uplatnica.Opis', mb_substr(ul_str('opis'), 0, 80) ?: null);
    spremi_postavku('Uplatnica.Clanovi', !empty($_POST['clanovi']) ? '1' : '0');
    dnevnik('Primatelj za uplatnice', null, null, $iban ? iban_razmaci($iban) : 'bez IBAN-a');
    poruka('Spremljeno.');
    preusmjeri('sustav/uplatnica');
}
$up = uplatnica_postavke();
if (isset($_SESSION['uplatnica_obrazac'])) {
    $o = $_SESSION['uplatnica_obrazac'];
    unset($_SESSION['uplatnica_obrazac']);
    foreach (['iban' => 'Iban', 'naziv' => 'Naziv', 'adresa' => 'Adresa', 'mjesto' => 'Mjesto', 'model' => 'Model', 'poziv' => 'Poziv', 'sifra' => 'Sifra', 'opis' => 'Opis'] as $a => $b) {
        $up[$b] = (string) ($o[$a] ?? $up[$b]);
    }
}
$ibanOk = ispravan_iban($up['Iban']);
// primjer s izmišljenim platiteljem
$primjer = null;
if ($ibanOk) {
    $primjer = uplatnica_polja(['Id' => 57, 'Ime' => 'Ivan', 'Prezime' => 'Horvat', 'Ulica' => 'Ulica primjera', 'KucniBroj' => '1', 'PostanskiBroj' => '33000', 'Mjesto' => 'Mjesto', 'Oib' => '12345678903'],
        (int) date('Y'), 2, 3, 100.0, date('Y') . '-06-30');
}
ob_start(); ?>
<h1 class="h3 mb-1">Uplatnica (primatelj plaćanja)</h1>
<p class="text-muted">Podaci udruge koji se upisuju na svaku uplatnicu HUB-3A s 2D kodom. Platitelj (ime i adresa člana) popunjava se sam iz podataka člana.
    Član uplatnicu ispiše ili 2D kod skenira / pokaže mobitelom u banci, pošti ili FINA-i.</p>
<div class="row g-4">
<div class="col-lg-7">
<form method="post" class="card" action="<?= e(url('sustav/uplatnica')) ?>"><?= csrf() ?><div class="card-body row g-2">
    <div class="col-12"><label class="form-label">IBAN udruge</label>
        <input name="iban" class="form-control<?= $up['Iban'] !== '' ? ($ibanOk ? ' is-valid' : ' is-invalid') : '' ?>" value="<?= e($up['Iban'] !== '' ? iban_razmaci($up['Iban']) : '') ?>" placeholder="HR12 3456 7890 1234 5678 9" style="font-family:monospace" inputmode="text" autocomplete="off">
        <div class="form-text">Bez IBAN-a se uplatnice ne prikazuju.</div></div>
    <div class="col-12"><label class="form-label">Naziv primatelja <span class="text-muted small">(najviše 25 znakova – ograničenje 2D koda)</span></label>
        <input name="naziv" class="form-control" maxlength="25" value="<?= e(mb_substr($up['Naziv'], 0, 25)) ?>" data-brojac></div>
    <div class="col-md-6"><label class="form-label">Ulica i kućni broj <span class="text-muted small">(max. 25)</span></label>
        <input name="adresa" class="form-control" maxlength="25" value="<?= e($up['Adresa']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Poštanski broj i mjesto</label>
        <input name="mjesto" class="form-control" maxlength="27" value="<?= e($up['Mjesto']) ?>" placeholder="poštanski broj i mjesto"></div>
    <div class="col-4 col-md-3"><label class="form-label">Model</label>
        <input name="model" class="form-control" maxlength="4" value="<?= e($up['Model']) ?>"></div>
    <div class="col-8 col-md-9"><label class="form-label">Poziv na broj</label>
        <input name="poziv" class="form-control" value="<?= e($up['Poziv']) ?>"></div>
    <div class="col-4 col-md-3"><label class="form-label">Šifra namjene</label>
        <input name="sifra" class="form-control" maxlength="4" value="<?= e($up['Sifra']) ?>" placeholder="prazno"></div>
    <div class="col-8 col-md-9"><label class="form-label">Opis plaćanja <span class="text-muted small">(max. 35 znakova nakon zamjene)</span></label>
        <input name="opis" class="form-control" value="<?= e($up['Opis']) ?>"></div>
    <div class="col-12 small text-muted">Zamjene u pozivu na broj i opisu: <code>{GODINA}</code> <code>{CLAN}</code> (broj člana u programu) <code>{OIB}</code> <code>{RATA}</code> (1, 2, 3…)
        <code>{RATA_OPIS}</code> („2. rata“, kod jednokratnog plaćanja prazno).<br>
        Uz model <b>HR00</b> poziv na broj smije imati samo znamenke i najviše dvije crtice, npr. <code>2026-57-2</code>. Pitajte blagajnika kako mu je najlakše prepoznati uplatu.</div>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="clanovi" value="1" id="uplClanovi"<?= chk(postavka('Uplatnica.Clanovi', '1') === '1') ?>>
        <label class="form-check-label" for="uplClanovi">Član na svojoj početnoj stranici vidi gumb <b>„Moja uplatnica“</b> (samo svoju)</label></div></div>
    <div class="col-12"><button class="btn btn-primary">Spremi</button></div>
</div></form>
</div>
<div class="col-lg-5">
    <div class="card"><div class="card-header">Primjer (izmišljeni član)</div><div class="card-body">
    <?php if ($primjer): ?>
        <?= uplatnica_kartica($primjer) ?>
        <?php if (!ispravan_poziv($primjer['Model'], $primjer['Poziv'])): ?><div class="alert alert-warning small mt-2 mb-0">Poziv na broj „<?= e($primjer['Poziv']) ?>“ ne odgovara modelu <?= e($primjer['Model']) ?> – banka ga može odbiti.</div><?php endif; ?>
        <?php if (mb_strlen($primjer['Opis']) > 35): ?><div class="alert alert-warning small mt-2 mb-0">Opis je dulji od 35 znakova – u 2D kodu bit će skraćen.</div><?php endif; ?>
    <?php else: ?>
        <p class="text-muted mb-0">Upišite ispravan IBAN – ovdje se prikazuje primjer uplatnice.</p>
    <?php endif; ?>
    </div></div>
</div>
</div>
<?php stranica('Uplatnica', ob_get_clean());
