<?php
/** Nova / uređivanje trgovine s popustom (pravo „Popusti u trgovinama – uređivanje“). */
if (!pop_smije_uredjivati()) {
    zabranjeno();
}
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$t = $id ? red('SELECT * FROM Popusti WHERE Id=?', [$id]) : null;
if ($id && !$t) {
    nije_pronadjeno('Trgovina');
}

if (je_post()) {
    if (($_POST['radnja'] ?? '') === 'obrisi' && $t) {
        obrisi_sliku($t['Logo']);
        q('DELETE FROM Popusti WHERE Id=?', [$id]);
        dnevnik('Popusti – trgovina obrisana', 'Popust', $id, $t['Naziv']);
        poruka('Trgovina „' . e($t['Naziv']) . '“ je obrisana.');
        preusmjeri('popusti');
    }
    $naziv = mb_substr(ul_str('Naziv'), 0, 100);
    $popust = mb_substr(ul_str('Popust'), 0, 40);
    $email = mb_substr(ul_str('Email'), 0, 120);
    $greska = match (true) {
        $naziv === '' => 'Upišite naziv trgovine.',
        $popust === '' => 'Upišite popust (npr. 10 %).',
        $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL) => 'E-mail adresa nije ispravna.',
        default => null,
    };
    $logo = $t['Logo'] ?? null;
    if (!$greska && !empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $novo = spremi_sliku($_FILES['logo'], 'trgovina-', 400, true);
            obrisi_sliku($logo);
            $logo = $novo;
        } catch (RuntimeException $e) {
            $greska = $e->getMessage();
        }
    }
    if (!$greska && !empty($_POST['bez_loga'])) {
        obrisi_sliku($logo);
        $logo = null;
    }
    if ($greska) {
        poruka(e($greska), 'danger');
        $_SESSION['pop_uredi'] = $_POST;
        preusmjeri('popusti/uredi', array_filter(['id' => $id ?: null]));
    }
    $polja = [
        'Naziv' => $naziv,
        'Popust' => $popust,
        'Uvjeti' => mb_substr(trim((string) ($_POST['Uvjeti'] ?? '')), 0, 1000) ?: null,
        'Nacin' => isset(pop_nacini()[$_POST['Nacin'] ?? '']) ? $_POST['Nacin'] : 'blagajna',
        'KontaktOsoba' => mb_substr(ul_str('KontaktOsoba'), 0, 80) ?: null,
        'Telefon' => mb_substr(ul_str('Telefon'), 0, 40) ?: null,
        'Email' => $email ?: null,
        'Web' => mb_substr(ul_str('Web'), 0, 200) ?: null,
        'Adresa' => mb_substr(ul_str('Adresa'), 0, 150) ?: null,
        'Logo' => $logo,
        'VrijediDo' => u_datum(ul_str('VrijediDo')),
        'Aktivno' => isset($_POST['Aktivno']) ? 1 : 0,
        'Azurirano' => sada(),
    ];
    if ($t) {
        azuriraj('Popusti', $id, $polja);
        dnevnik('Popusti – trgovina uređena', 'Popust', $id, $naziv);
    } else {
        $id = umetni('Popusti', $polja + ['Kreirano' => sada(), 'Redoslijed' => (int) vrijednost('SELECT COALESCE(MAX(Redoslijed),-1)+1 FROM Popusti')]);
        dnevnik('Popusti – nova trgovina', 'Popust', $id, $naziv);
    }
    poruka('Spremljeno.');
    preusmjeri('popusti');
}

$v = $_SESSION['pop_uredi'] ?? $t ?? ['Aktivno' => 1, 'Nacin' => 'blagajna'];
unset($_SESSION['pop_uredi']);
$vd = $v['VrijediDo'] ?? '';
$vd = $vd && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $vd) ? substr((string) $vd, 0, 10) : '';

ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('popusti')) ?>">‹</a>
    <h1 class="h3 mb-0"><?= $t ? 'Uredi trgovinu' : 'Nova trgovina s popustom' ?></h1>
</div>
<form method="post" enctype="multipart/form-data" class="row g-3" style="max-width:820px"><?= csrf() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="col-md-8"><label class="form-label">Naziv trgovine *</label><input name="Naziv" class="form-control" maxlength="100" required value="<?= e($v['Naziv'] ?? '') ?>" placeholder="npr. Lovački centar Virovitica"></div>
    <div class="col-md-4"><label class="form-label">Popust *</label><input name="Popust" class="form-control" maxlength="40" required value="<?= e($v['Popust'] ?? '') ?>" placeholder="npr. 10 %"></div>
    <div class="col-12"><label class="form-label">Uvjeti</label><textarea name="Uvjeti" class="form-control" rows="2" maxlength="1000" placeholder="npr. na sav asortiman osim oružja i streljiva; ne zbraja se s akcijama"><?= e($v['Uvjeti'] ?? '') ?></textarea></div>

    <div class="col-12"><label class="form-label d-block">Kako član dobiva popust</label>
        <?php foreach (pop_nacini() as $kl => [$ik, $naz, $uputa]): ?>
        <label class="form-check"><input class="form-check-input" type="radio" name="Nacin" value="<?= e($kl) ?>"<?= chk(($v['Nacin'] ?? 'blagajna') === $kl) ?>>
            <?= $ik ?> <b><?= e($naz) ?></b> <span class="small text-muted">– članu piše: „<?= e($uputa) ?>“</span></label>
        <?php endforeach; ?>
    </div>

    <div class="col-md-6"><label class="form-label">Kontakt osoba / prodavač</label><input name="KontaktOsoba" class="form-control" maxlength="80" value="<?= e($v['KontaktOsoba'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Telefon / mobitel</label><input name="Telefon" class="form-control" maxlength="40" inputmode="tel" value="<?= e($v['Telefon'] ?? '') ?>" placeholder="npr. 091 234 5678"></div>
    <div class="col-md-6"><label class="form-label">E-mail</label><input name="Email" type="email" class="form-control" maxlength="120" value="<?= e($v['Email'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Web</label><input name="Web" class="form-control" maxlength="200" value="<?= e($v['Web'] ?? '') ?>" placeholder="npr. www.trgovina.hr"></div>
    <div class="col-12"><label class="form-label">Adresa</label><input name="Adresa" class="form-control" maxlength="150" value="<?= e($v['Adresa'] ?? '') ?>" placeholder="ulica i broj, mjesto (za navigaciju)"></div>

    <div class="col-md-7"><label class="form-label">Logo trgovine</label><input type="file" name="logo" accept="image/*" class="form-control">
        <?php if (!empty($t['Logo'])): ?><div class="d-flex align-items-center gap-2 mt-2"><?= pop_logo_html($t) ?>
            <label class="form-check small"><input type="checkbox" name="bez_loga" value="1" class="form-check-input"> ukloni logo</label></div><?php endif; ?></div>
    <div class="col-md-5"><label class="form-label">Vrijedi do <span class="text-muted small">(prazno = bez roka)</span></label><input name="VrijediDo" type="date" class="form-control" value="<?= e($vd) ?>">
        <div class="form-text">Nakon tog datuma članovi trgovinu više ne vide.</div></div>
    <div class="col-12"><label class="form-check"><input type="checkbox" name="Aktivno" value="1" class="form-check-input"<?= chk($v['Aktivno'] ?? 1) ?>> Prikazati članovima</label></div>

    <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Spremi</button>
        <?php if ($t): ?><button class="btn btn-outline-danger ms-auto" name="radnja" value="obrisi" formnovalidate data-potvrda="Obrisati trgovinu „<?= e($t['Naziv']) ?>“?">🗑 Obriši</button><?php endif; ?>
    </div>
</form>
<?php stranica($t ? 'Uredi trgovinu' : 'Nova trgovina', ob_get_clean());
