<?php
/** Papirnate liste dezinfekcije (lovci bez mobitela): fotografija liste se prilaže knjizi, PDF-u i pregledu za inspekciju. */
trazi(P_DEZ_UREDI);
dez_osiguraj_stanice();
$k = korisnik();
$stanice = array_values(array_filter(dez_stanice(), fn($s) => $s['Aktivna']));
$ids = array_map(fn($s) => (int) $s['Id'], $stanice);

if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'obrisi') {
        $l = red('SELECT * FROM DezListe WHERE Id=?', [(int) ($_POST['id'] ?? 0)]);
        if (!$l || !in_array((int) $l['StanicaId'], $ids, true)) {
            zabranjeno();
        }
        q('DELETE FROM DezListe WHERE Id=?', [$l['Id']]);
        obrisi_sliku($l['Datoteka']);
        dnevnik('Dezinfekcija – obrisana fotografija papirnate liste', 'DezLista', (int) $l['Id'], dez_lista_opis($l));
        poruka('Fotografija je obrisana.');
        preusmjeri('dezinfekcija/liste');
    }
    $sid = (int) ($_POST['stanica'] ?? 0);
    $od = u_datum(ul_str('od'));
    $do = u_datum(ul_str('do')) ?? $od;
    if (!in_array($sid, $ids, true) || !$od) {
        poruka('Odaberite stanicu i datum liste.', 'warning');
        preusmjeri('dezinfekcija/liste');
    }
    if ($do < $od) {
        [$od, $do] = [$do, $od];
    }
    $f = $_FILES['slike'] ?? null;
    $n = 0;
    $greske = [];
    if ($f && is_array($f['name'])) {
        foreach (array_keys($f['name']) as $i) {
            $jedna = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            if ($jedna['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $ime = spremi_sliku($jedna, 'dezlista-', 2200);
                $id = umetni('DezListe', ['StanicaId' => $sid, 'Od' => $od, 'Do' => $do, 'Datoteka' => $ime,
                    'Napomena' => mb_substr(ul_str('napomena'), 0, 200) ?: null, 'UcitaoIme' => $k['Naziv'], 'Kreirano' => sada()]);
                dnevnik('Dezinfekcija – učitana fotografija papirnate liste', 'DezLista', $id, datum($od) . ($do !== $od ? ' – ' . datum($do) : ''));
                $n++;
            } catch (Throwable $e) {
                $greske[] = e($jedna['name'] . ': ' . $e->getMessage());
            }
        }
    }
    if ($n) {
        poruka("Učitano fotografija: $n. Vidljive su u knjizi, PDF-u i pregledu za inspekciju.");
    }
    if ($greske || !$n) {
        poruka($greske ? implode('<br>', $greske) : 'Odaberite ili snimite fotografiju liste.', 'warning');
    }
    preusmjeri('dezinfekcija/liste');
}
$sve = $ids ? redovi('SELECT l.*, st.Naziv AS Stanica FROM DezListe l JOIN DezStanice st ON st.Id=l.StanicaId WHERE l.StanicaId IN ('
    . implode(',', $ids) . ') ORDER BY l.Od DESC, l.Id DESC LIMIT 60') : [];
ob_start(); ?>
<h1 class="h3 mb-1">Papirnate liste</h1>
<p class="text-muted">Za lovce bez mobitela: na stanici visi papirnata lista (datum, vrijeme, ime, reg. oznaka, razlog, dolazak/odlazak, potpis).
    Kad je list pun ili na kraju tjedna – fotografirajte ga ovdje. Fotografija se prilaže knjizi dezinfekcije, PDF-u i pregledu za inspekciju. Original sačuvajte.</p>
<form method="post" action="<?= e(url('dezinfekcija/liste')) ?>" enctype="multipart/form-data" class="card card-body mb-4" style="max-width:640px"><?= csrf() ?>
    <div class="row g-3">
        <div class="col-12"><label class="form-label">Stanica</label>
            <select name="stanica" class="form-select" required><?php foreach ($stanice as $s): ?><option value="<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label">Lista od</label><input type="date" name="od" class="form-control" required max="<?= danas() ?>" value="<?= danas() ?>"></div>
        <div class="col-6"><label class="form-label">do</label><input type="date" name="do" class="form-control" max="<?= danas() ?>" value="<?= danas() ?>"></div>
        <div class="col-12"><label class="form-label">Fotografija (može više stranica)</label>
            <input type="file" name="slike[]" class="form-control" accept="image/*" multiple required>
            <div class="form-text">Na mobitelu: „Kamera“ ili iz galerije. Snimite ravno odozgo, cijeli list, dobro osvijetljen.</div></div>
        <div class="col-12"><label class="form-label">Napomena</label><input name="napomena" class="form-control" maxlength="200" placeholder="npr. list 2/3"></div>
    </div>
    <button class="btn btn-primary mt-3">Učitaj</button>
</form>
<?php if ($sve): ?>
    <?= dez_liste_html($sve, null, true) ?>
<?php else: ?>
    <p class="text-muted">Još nema učitanih lista.</p>
<?php endif; ?>
<?php stranica('Papirnate liste', ob_get_clean());
