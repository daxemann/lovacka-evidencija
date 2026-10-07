<?php
/** Sigurnosne kopije: ručna/dnevna kopija baze, kompletna kopija (ZIP) i vraćanje. */
trazi(P_SUSTAV);
if (je_post()) {
    $radnja = (string) ($_POST['radnja'] ?? '');
    if ($radnja === 'kopija') {
        $ime = napravi_kopiju('rucno');
        dnevnik('Ručna sigurnosna kopija', null, null, $ime);
        poruka(e("Kopija napravljena: $ime"));
    } elseif ($radnja === 'vrati') {
        $f = $_FILES['kopija'] ?? null;
        if (!isset($_POST['razumijem'])) {
            poruka('Potvrdite da razumijete da se trenutni podaci zamjenjuju.', 'warning');
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            poruka('Odaberite ZIP datoteku kompletne kopije.' . upload_greska($f), 'warning');
        } else {
            try {
                $tko = korisnik()['KorisnickoIme'];
                vrati_kompletnu_kopiju($f['tmp_name']);
                dnevnik('Vraćena kompletna kopija', null, null, 'vratio: ' . $tko, ['Id' => null, 'KorisnickoIme' => $tko]);
                odjavi();
                session_start();
                preusmjeri('prijava', ['vraceno' => 1]);
            } catch (Throwable $e) {
                poruka('Vraćanje nije uspjelo: ' . e($e->getMessage()), 'danger');
            }
        }
    } elseif ($radnja === 'obrisi') {
        $ime = basename((string) ($_POST['ime'] ?? ''));
        if (preg_match('/^evidencija-.*\.db$/', $ime)) {
            @unlink(podaci('kopije/' . $ime));
        }
    }
    preusmjeri('sustav/kopije');
}
$kopije = popis_kopija();
$velicina = fn(int $b) => $b > 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
$fotoVel = array_sum(array_map('filesize', glob(podaci('foto/*')) ?: []));
ob_start(); ?>
<h1 class="h3 mb-1">Sigurnosne kopije</h1>
<p class="text-muted">Baza: <code><?= e(podaci('evidencija.db')) ?></code> (<?= $velicina((int) @filesize(podaci('evidencija.db'))) ?>)<br>
    Automatska kopija baze radi se jednom dnevno (čuva se zadnjih <?= BROJ_DNEVNIH_KOPIJA ?>). Fotografije (<?= $velicina($fotoVel) ?>) su u mapi <code><?= e(podaci('foto')) ?></code>.</p>
<div class="d-flex flex-wrap gap-2 mb-2">
    <form method="post" action="<?= e(url('sustav/kopije')) ?>"><?= csrf() ?><input type="hidden" name="radnja" value="kopija"><button class="btn btn-primary">Napravi kopiju sada</button></form>
    <a class="btn btn-outline-primary" href="<?= e(url('sustav/kompletna-kopija')) ?>" download>Preuzmi kompletnu kopiju (ZIP)</a>
</div>
<p class="small text-muted" style="max-width:700px">Kompletna kopija sadrži bazu, sve fotografije i ključeve (npr. šifriranu lozinku e-pošte). Služi za preseljenje na drugi poslužitelj (i između PHP, Windows i Home Assistant verzije): tamo se kod prvog pokretanja odabere „Vrati iz kompletne kopije“. Čuvajte je kao povjerljivu – sadrži osobne podatke članova.</p>
<div class="card border-danger mb-4" style="max-width:700px">
    <div class="card-header text-danger">Vrati kompletnu kopiju (ZIP)</div>
    <div class="card-body">
        <p class="small mb-2"><b>Svi trenutni podaci ovdje se zamjenjuju podacima iz kopije</b> (članovi, korisnici, fotografije, postavke). Trenutna baza se prije toga sprema u sigurnosne kopije.</p>
        <form method="post" action="<?= e(url('sustav/kopije')) ?>" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="radnja" value="vrati">
            <input type="file" name="kopija" accept=".zip" class="form-control mb-2" required>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="razumijem" id="razumijem" value="1" required>
                <label class="form-check-label small" for="razumijem">Razumijem – zamijeni trenutne podatke</label></div>
            <button class="btn btn-outline-danger" data-potvrda="Zamijeniti sve trenutne podatke podacima iz kopije?">Vrati iz kompletne kopije</button>
        </form>
        <div class="small text-muted mt-2">Najveća datoteka koju poslužitelj prima: <?= e(ini_get('upload_max_filesize')) ?> (post_max_size <?= e(ini_get('post_max_size')) ?>).</div>
    </div>
</div>
<table class="table table-sm" style="max-width:700px">
    <thead><tr><th>Datoteka</th><th>Veličina</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($kopije as $kp): ?>
        <tr><td class="small"><?= e($kp['ime']) ?></td><td class="small"><?= $velicina($kp['velicina']) ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-link" href="<?= e(url('sustav/kopija', ['ime' => $kp['ime']])) ?>" download>Preuzmi</a>
                <form method="post" action="<?= e(url('sustav/kopije')) ?>" class="d-inline"><?= csrf() ?><input type="hidden" name="radnja" value="obrisi"><input type="hidden" name="ime" value="<?= e($kp['ime']) ?>">
                    <button class="btn btn-sm btn-link text-danger" data-potvrda="Obrisati kopiju?">Obriši</button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$kopije): ?><tr><td colspan="3" class="text-muted">Još nema kopija.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php stranica('Sigurnosne kopije', ob_get_clean());
