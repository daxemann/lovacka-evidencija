<?php
/** 📄 Moji dokumenti: lovačka iskaznica, dozvole, oružni list, osiguranje … – fotografija i/ili upis s rokom. ?clan=ID za ovlaštene. */
$k = korisnik();
$clanId = (int) ul('clan', $k['ClanId'] ?? 0);
if (!$clanId || !mojidok_vidi($clanId)) {
    zabranjeno();
}
$c = clan($clanId) ?? nije_pronadjeno('Član');
$moj = $k['ClanId'] !== null && (int) $k['ClanId'] === $clanId;
$uredjuje = mojidok_uredjuje($clanId);
$povratak = fn(array $x = []) => preusmjeri('moji-dokumenti', ($moj ? [] : ['clan' => $clanId]) + $x);

if (je_post()) {
    if (!$uredjuje) {
        zabranjeno();
    }
    $radnja = (string) ($_POST['radnja'] ?? 'spremi');
    $d = ($id = (int) ($_POST['id'] ?? 0)) ? mojidok_dokument($id) : null;
    if ($id && (!$d || (int) $d['ClanId'] !== $clanId)) {
        zabranjeno();
    }
    if ($radnja === 'obrisi' && $d) {
        obrisi_sliku($d['Foto1']);
        obrisi_sliku($d['Foto2']);
        q('DELETE FROM ClanDokumenti WHERE Id=?', [$d['Id']]);
        dnevnik('Dokument obrisan', 'Clan', $clanId, $d['Vrsta'] . ' · ' . puno_ime($c));
        poruka('Dokument obrisan.');
        $povratak();
    }
    $vrsta = in_array(ul_str('vrsta'), MOJIDOK_VRSTE, true) ? ul_str('vrsta') : 'Ostalo';
    $datum = fn(string $kl) => preg_match('/^\d{4}-\d{2}-\d{2}$/', ul_str($kl)) ? ul_str($kl) : null;
    $polja = ['Vrsta' => $vrsta, 'Naziv' => mb_substr(ul_str('naziv'), 0, 80) ?: null, 'Broj' => mb_substr(ul_str('broj'), 0, 60) ?: null,
        'Izdavatelj' => mb_substr(ul_str('izdavatelj'), 0, 120) ?: null, 'VrijediOd' => $datum('od'), 'VrijediDo' => $datum('do'),
        'Napomena' => mb_substr(ul_str('napomena'), 0, 500) ?: null, 'Azurirano' => sada()];
    foreach (['Foto1' => 'foto1', 'Foto2' => 'foto2'] as $st => $polje) {
        $f = $_FILES[$polje] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $polja[$st] = spremi_sliku($f, 'cdok-' . $clanId . '-', 1800);
                if ($d) {
                    obrisi_sliku($d[$st]);
                }
            } catch (RuntimeException $e) {
                poruka(e($e->getMessage()), 'danger');
            }
        } elseif ($d && isset($_POST['obrisi_' . $polje])) {
            obrisi_sliku($d[$st]);
            $polja[$st] = null;
        }
    }
    $imaSliku = ($polja['Foto1'] ?? ($d['Foto1'] ?? null)) || ($polja['Foto2'] ?? ($d['Foto2'] ?? null));
    if (!$imaSliku && !$polja['Broj'] && !$polja['VrijediDo'] && !$polja['Naziv']) {
        poruka('Dodajte fotografiju dokumenta ili upišite barem broj / rok.', 'warning');
        $povratak($d ? ['uredi' => $d['Id']] : ['novo' => 1]);
    }
    if ($d) {
        azuriraj('ClanDokumenti', (int) $d['Id'], $polja);
    } else {
        umetni('ClanDokumenti', $polja + ['ClanId' => $clanId, 'Kreirano' => sada()]);
    }
    if (!$moj) {
        dnevnik('Dokument člana spremljen', 'Clan', $clanId, $vrsta . ' · ' . puno_ime($c));
    }
    poruka('Dokument spremljen.');
    $povratak();
}

$lista = mojidok_lista($clanId);
$uredi = null;
if ($uid = (int) ul('uredi', 0)) {
    foreach ($lista as $x) {
        if ((int) $x['Id'] === $uid) {
            $uredi = $x;
        }
    }
}
$novo = $uredjuje && ($uredi || ul('novo', '') || !$lista);
$v = $uredi ?? ['Id' => 0, 'Vrsta' => (string) ul('vrsta', 'Lovačka iskaznica'), 'Naziv' => '', 'Broj' => '', 'Izdavatelj' => '', 'VrijediOd' => '', 'VrijediDo' => '', 'Napomena' => '', 'Foto1' => null, 'Foto2' => null];
$oruzje = oruzni_listovi($clanId);
$par = $moj ? [] : ['clan' => $clanId];
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <?php if (!$moj): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('clanovi/uredi', ['id' => $clanId])) ?>">← <?= e(puno_ime($c)) ?></a><?php endif; ?>
    <h1 class="h3 mb-0 me-auto">📄 <?= $moj ? 'Moji dokumenti' : 'Dokumenti – ' . e(puno_ime($c)) ?></h1>
    <?php if ($uredjuje && !$novo): ?><a class="btn btn-success" href="<?= e(url('moji-dokumenti', $par + ['novo' => 1])) ?>">➕ Dodaj dokument</a><?php endif; ?>
</div>
<p class="small text-muted" style="max-width:760px"><?= $moj ? 'Spremite svoje dokumente – fotografirajte papir ili samo upišite broj i rok. Imate ih uvijek u mobitelu.' : 'Dokumente upisuje član sam.' ?>
    Vide ih samo <?= $moj ? 'vi' : 'član' ?> i ovlašteni u udruzi (pregled članova).</p>

<?php if ($novo): ?>
<form method="post" enctype="multipart/form-data" class="card mb-3" style="max-width:760px"><?= csrf() ?><input type="hidden" name="id" value="<?= (int) $v['Id'] ?>">
    <div class="card-header fw-semibold"><?= $uredi ? '✏️ Izmjena dokumenta' : '➕ Novi dokument' ?></div>
    <div class="card-body row g-2">
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Vrsta *</label>
            <select name="vrsta" class="form-select"><?php foreach (MOJIDOK_VRSTE as $x): ?><option<?= sel($x, $v['Vrsta']) ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-6"><label class="form-label small mb-0">Naziv / opis</label>
            <input name="naziv" class="form-control" maxlength="80" value="<?= e((string) $v['Naziv']) ?>" placeholder="npr. Dozvola za lov – krupna divljač"></div>
        <div class="col-6 col-md-4"><label class="form-label small mb-0">Broj</label><input name="broj" class="form-control" maxlength="60" value="<?= e((string) $v['Broj']) ?>"></div>
        <div class="col-6 col-md-8"><label class="form-label small mb-0">Izdavatelj</label><input name="izdavatelj" class="form-control" maxlength="120" value="<?= e((string) $v['Izdavatelj']) ?>" placeholder="npr. Hrvatski lovački savez, PU …"></div>
        <div class="col-6"><label class="form-label small mb-0">Vrijedi od</label><input type="date" name="od" class="form-control" value="<?= e((string) $v['VrijediOd']) ?>"></div>
        <div class="col-6"><label class="form-label small mb-0">Vrijedi do</label><input type="date" name="do" class="form-control" value="<?= e((string) $v['VrijediDo']) ?>"></div>
        <?php foreach (['foto1' => ['Foto1', '📷 Prednja strana'], 'foto2' => ['Foto2', '📷 Stražnja strana']] as $polje => [$st, $lab]): ?>
        <div class="col-12 col-md-6"><label class="form-label small mb-0"><?= $lab ?></label>
            <input type="file" name="<?= $polje ?>" accept="image/*" capture="environment" class="form-control" data-smanji="1800">
            <?php if ($v[$st]): ?><div class="d-flex align-items-center gap-2 mt-1"><img src="<?= e(foto_url($v[$st])) ?>" alt="" class="dok-sicicka">
                <label class="small"><input type="checkbox" name="obrisi_<?= $polje ?>" value="1"> obriši</label></div><?php endif; ?></div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-label small mb-0">Napomena</label><input name="napomena" class="form-control" maxlength="500" value="<?= e((string) $v['Napomena']) ?>"></div>
    </div>
    <div class="card-footer d-flex gap-2"><button class="btn btn-success">💾 Spremi</button>
        <?php if ($lista): ?><a class="btn btn-outline-secondary" href="<?= e(url('moji-dokumenti', $par)) ?>">Odustani</a><?php endif; ?>
        <?php if ($uredi): ?><button class="btn btn-outline-danger ms-auto" name="radnja" value="obrisi" data-potvrda="Obrisati ovaj dokument?">🗑 Obriši</button><?php endif; ?></div>
</form>
<?php endif; ?>

<?php if ($lista): ?>
<div class="row g-3 mb-3">
<?php foreach ($lista as $d): [$rok, $rokKl] = mojidok_rok($d['VrijediDo']); ?>
    <div class="col-12 col-md-6 col-xl-4"><div class="card h-100 dok-kartica">
        <?php if ($d['Foto1'] || $d['Foto2']): ?><div class="dok-slike">
            <?php foreach (['Foto1', 'Foto2'] as $st): if ($d[$st]): ?><a href="<?= e(foto_url($d[$st])) ?>" target="_blank"><img src="<?= e(foto_url($d[$st])) ?>" alt="<?= e($d['Vrsta']) ?>" loading="lazy"></a><?php endif; endforeach; ?>
        </div><?php endif; ?>
        <div class="card-body">
            <div class="d-flex align-items-start gap-2"><b class="flex-grow-1"><?= e($d['Vrsta']) ?></b><?php if ($rok): ?><span class="badge <?= $rokKl ?>"><?= e($rok) ?></span><?php endif; ?></div>
            <?php if ($d['Naziv']): ?><div class="small"><?= e($d['Naziv']) ?></div><?php endif; ?>
            <div class="small text-muted"><?= e(implode(' · ', array_filter([$d['Broj'] ? 'br. ' . $d['Broj'] : '', (string) $d['Izdavatelj'], $d['VrijediOd'] ? 'od ' . datum($d['VrijediOd']) : '']))) ?></div>
            <?php if ($d['Napomena']): ?><div class="small mt-1"><?= e($d['Napomena']) ?></div><?php endif; ?>
        </div>
        <?php if ($uredjuje): ?><div class="card-footer bg-transparent"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('moji-dokumenti', $par + ['uredi' => $d['Id']])) ?>">✏️ Uredi</a></div><?php endif; ?>
    </div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($oruzje): ?>
<div class="card" style="max-width:760px"><div class="card-header small">🔫 Oružni listovi <span class="text-muted">(upisuje udruga – ovdje samo pregled; fotografiju možete dodati kao dokument „Oružni list“)</span></div>
    <ul class="list-group list-group-flush small"><?php foreach ($oruzje as $o): ?><li class="list-group-item d-flex gap-2"><b><?= e($o['Broj']) ?></b><span class="text-muted"><?= e((string) $o['Oruzje']) ?></span></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php stranica($moj ? 'Moji dokumenti' : 'Dokumenti člana', ob_get_clean());
