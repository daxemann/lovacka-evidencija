<?php
/** 🏅 Bodovi: dan resetiranja (početak bodovne godine) i pravilo prijenosa bodova u novu godinu. */
trazi(P_SUSTAV);
if (je_post() && ($_POST['radnja'] ?? '') === 'psi' && modul('psi')) {
    $dan = bodovi_dan_unos(ul_str('PsiDan'));
    $vid = (int) ($_POST['PsiVrsta'] ?? 0);
    if (!$dan || !vrijednost('SELECT 1 FROM VrsteAkcija WHERE Id=?', [$vid])) {
        poruka('Upišite dan (dan.mjesec.) i odaberite vrstu.', 'warning');
        preusmjeri('sustav/bodovi');
    }
    $bodNovi = u_broj($_POST['PsiBodovi'] ?? '');
    if ($bodNovi !== null && $bodNovi >= 0) {
        // bodovi vrijede od sada – već upisani bodovi za pse ostaju kakvi su bili
        $stari = (float) vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$vid]);
        if (abs($stari - $bodNovi) > 0.0001) {
            q('UPDATE VrsteAkcija SET StandardniBodovi=? WHERE Id=?', [dec($bodNovi), $vid]);
            dnevnik('Bodovi za držanje psa promijenjeni', null, null, broj($stari, 2) . ' → ' . broj($bodNovi, 2) . ' b.');
        }
    }
    spremi_postavku('Psi.BodoviDan', $dan === '01-01' ? null : $dan);
    spremi_postavku('Psi.Vrsta.godisnje', (string) $vid);
    $n = upisi_bodove_psa((int) date('Y'), null, korisnik());
    dnevnik('Bodovi za pse – postavke', null, null, 'dan ' . bodovi_dan_prikaz($dan) . ', vrsta ' . vrijednost('SELECT Naziv FROM VrsteAkcija WHERE Id=?', [$vid]) . ', ' . broj((float) vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$vid]), 2) . ' b.');
    poruka('Spremljeno.' . ($n ? " Upisano odmah: $n × bodovi za psa (odobreno)." : ''));
    preusmjeri('sustav/bodovi');
}
if (je_post() && ($_POST['radnja'] ?? '') === 'odstrel' && bodovi_odstrel_ukljuceno()) {
    $iz = [];
    foreach ((array) ($_POST['iz'] ?? []) as $r) {
        $kljuc = (string) ($r['k'] ?? '');
        $s = max(0.0, u_broj($r['s'] ?? '') ?? 0.0);
        $n = max(0.0, u_broj($r['n'] ?? '') ?? 0.0);
        if (str_contains($kljuc, '|') && ($s > 0 || $n > 0)) {
            $iz[$kljuc] = ['staro' => $s, 'novo' => $n];
        }
    }
    spremi_postavku('Bodovi.Odstrel', $iz ? json_encode($iz, JSON_UNESCAPED_UNICODE) : null);
    dnevnik('Bodovi za odstrel – postavke', null, null, $iz ? implode(', ', array_map(fn($kl, $v) => str_replace('|', ' ', rtrim($kl, '|')) . ' ' . broj($v['staro'], 2) . '+' . broj($v['novo'], 2), array_keys($iz), $iz)) : 'bez oduzimanja');
    poruka('Spremljeno. Vrijedi za odstrel upisan od sada – već upisani odstrel se ne mijenja.');
    preusmjeri('sustav/bodovi');
}
if (je_post() && ($_POST['radnja'] ?? '') === 'obveze') {
    $g = (int) date('Y');
    $n = 0;
    $greska = false;
    foreach ((array) ($_POST['obveza'] ?? []) as $oid => $r) {
        $o = red('SELECT * FROM GodisnjeObveze WHERE Id=?', [(int) $oid]);
        if (!$o) {
            continue;
        }
        if (!empty($r['obrisi'])) {
            q('DELETE FROM GodisnjeObveze WHERE Id=?', [(int) $oid]); // već upisani bodovi (radne akcije) ostaju
            dnevnik('Godišnja obveza obrisana', null, null, $o['Naziv']);
            continue;
        }
        $naziv = mb_substr(trim((string) ($r['Naziv'] ?? '')), 0, 80);
        $dan = bodovi_dan_unos((string) ($r['Dan'] ?? ''));
        if ($naziv === '' || !$dan) {
            $greska = true;
            continue;
        }
        $pod = ['Naziv' => $naziv, 'Bodovi' => dec(max(0.0, u_broj($r['Bodovi'] ?? '') ?? 0.0)), 'Dan' => $dan, 'Aktivna' => !empty($r['Aktivna']) ? 1 : 0];
        if ($pod['Naziv'] !== $o['Naziv'] || (float) $pod['Bodovi'] !== (float) $o['Bodovi'] || $pod['Dan'] !== $o['Dan'] || $pod['Aktivna'] !== (int) $o['Aktivna']) {
            azuriraj('GodisnjeObveze', (int) $oid, $pod);
            dnevnik('Godišnja obveza promijenjena', null, null, $naziv . ': ' . broj((float) $pod['Bodovi'], 2) . ' b., ' . bodovi_dan_prikaz($dan) . ($pod['Aktivna'] ? '' : ', isključena'));
        }
        $n += upisi_bodove_obveze(array_merge($o, $pod), $g, korisnik());
    }
    $nova = (array) ($_POST['nova'] ?? []);
    $naziv = mb_substr(trim((string) ($nova['Naziv'] ?? '')), 0, 80);
    if ($naziv !== '') {
        $dan = bodovi_dan_unos((string) ($nova['Dan'] ?? '')) ?? '01-01';
        umetni('GodisnjeObveze', ['Naziv' => $naziv, 'Bodovi' => dec(max(0.0, u_broj($nova['Bodovi'] ?? '') ?? 0.0)), 'Dan' => $dan, 'Aktivna' => 1, 'Kreirano' => sada()]);
        dnevnik('Godišnja obveza dodana', null, null, $naziv);
    }
    poruka($greska ? 'Neke obveze nisu spremljene – naziv i dan (dan.mjesec.) su obavezni.' : 'Spremljeno.' . ($n ? " Upisano odmah: $n (odobreno)." : '') . ($naziv !== '' ? ' Članove uključite u Radne akcije → 📅 Godišnje obveze.' : ''), $greska ? 'warning' : 'success');
    preusmjeri('sustav/bodovi');
}
if (je_post()) {
    $dan = bodovi_dan_unos(ul_str('Pocetak'));
    if (!$dan) {
        poruka('Dan resetiranja nije ispravan – upišite dan.mjesec. (npr. 1.4.).', 'warning');
        preusmjeri('sustav/bodovi');
    }
    $nacin = isset(BODOVI_PRIJENOS[$_POST['Prijenos'] ?? '']) ? (string) $_POST['Prijenos'] : 'nista';
    $broj = max(0.0, (float) (u_broj($_POST['PrijenosBroj'] ?? '') ?? 0));
    $auto = isset($_POST['Auto']) && $nacin !== 'nista';
    $bioAuto = bodovi_prijenos_pravilo()['auto'];
    spremi_postavku('Bodovi.Pocetak', $dan === '04-01' ? null : $dan);
    spremi_postavku('Bodovi.Prijenos', $nacin === 'nista' ? null : $nacin);
    spremi_postavku('Bodovi.PrijenosBroj', in_array($nacin, ['max', 'fiksno'], true) ? dec($broj) : null);
    spremi_postavku('Bodovi.PrijenosAuto', $auto ? '1' : null);
    $minus = isset($_POST['Minus']) && $nacin !== 'nista' && bodovi_odstrel_ukljuceno();
    spremi_postavku('Bodovi.PrijenosMinus', $minus ? '1' : null);
    if ($auto && !$bioAuto) {
        // automatski tek od sljedeće bodovne godine – za tekuću je ručni prijenos
        spremi_postavku('Bodovi.Preneseno', (string) bodovna_godina());
    }
    dnevnik('Bodovi – postavke', null, null, 'reset ' . bodovi_dan_prikaz($dan) . ', prijenos: ' . BODOVI_PRIJENOS[$nacin] . (in_array($nacin, ['max', 'fiksno'], true) ? ' ' . broj($broj, 2) : '') . ($auto ? ', automatski' : '') . ($minus ? ', minus se prenosi' : ''));
    poruka('Spremljeno.');
    preusmjeri('sustav/bodovi');
}
$pr = bodovi_prijenos_pravilo();
$bg = bodovna_godina();
[$od, $do] = bodovna_godina_raspon($bg);
$fun = redovi("SELECT Naziv, Bodovi, BodoviDatum FROM Funkcije WHERE BodoviDatum IS NOT NULL AND CAST(COALESCE(Bodovi,'0') AS REAL) > 0 ORDER BY Redoslijed, Naziv");
ob_start(); ?>
<h1 class="h3 mb-1">🏅 Bodovi</h1>
<p class="text-muted" style="max-width:760px">Tekuća bodovna godina: <b><?= e(bodovna_godina_naziv($bg)) ?></b> (<?= e(datum($od)) ?> – <?= e(datum($do)) ?>).
    <a class="ms-2" href="<?= e(url('akcije/bodovi')) ?>">📚 Arhiva bodova</a> · <a href="<?= e(url('akcije/prijenos')) ?>">↪️ Prijenos u novu godinu</a></p>
<form method="post" class="card mb-4" style="max-width:760px"><?= csrf() ?>
    <div class="card-body">
        <div class="mb-4">
            <label class="form-label fw-semibold">📅 Dan resetiranja bodova</label>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input name="Pocetak" class="form-control" style="width:8rem" value="<?= e(bodovi_dan_prikaz(bodovi_dan())) ?>" placeholder="dan.mjesec." required>
                <?php foreach (['1.4.' => 'lovna godina', '1.1.' => 'kalendarska', '1.8.' => '1. kolovoza'] as $v => $n): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="this.form.Pocetak.value='<?= $v ?>'"><?= $v ?> <span class="text-muted"><?= $n ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="form-text">Od tog dana bodovi kreću ispočetka (stari ostaju u arhivi).</div>
        </div>

        <label class="form-label fw-semibold">↪️ Prijenos bodova iz prošle godine</label>
        <?php foreach (BODOVI_PRIJENOS as $v => $n): ?>
            <div class="form-check d-flex flex-wrap align-items-center gap-2 mb-1">
                <input class="form-check-input" type="radio" name="Prijenos" id="pr-<?= $v ?>" value="<?= $v ?>"<?= chk($pr['nacin'] === $v) ?>>
                <label class="form-check-label" for="pr-<?= $v ?>"><?= e($n) ?></label>
                <?php if ($v === 'max' || $v === 'fiksno'): ?>
                    <input type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" style="width:6rem" data-za="<?= $v ?>"
                           value="<?= $pr['nacin'] === $v ? e((string) $pr['broj']) : '' ?>" placeholder="bodova" onfocus="document.getElementById('pr-<?= $v ?>').checked=true"> <span class="small text-muted">bodova</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <input type="hidden" name="PrijenosBroj" id="PrijenosBroj">
        <div class="form-text mb-2">Primjer „fiksni iznos 10“: tko je imao 10 ili više bodova, prenosi 10 – tko je imao manje, kreće od 0.</div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="Auto" id="auto" value="1"<?= chk($pr['auto']) ?>>
            <label class="form-check-label" for="auto">Prenesi automatski na dan resetiranja</label>
            <div class="form-text">Bez kvačice prenosite ručno (<a href="<?= e(url('akcije/prijenos')) ?>">Prijenos u novu godinu</a>) i možete svakom članu promijeniti iznos.</div>
        </div>
        <?php if (bodovi_odstrel_ukljuceno()): ?>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="Minus" id="minus" value="1"<?= chk($pr['minus']) ?>>
            <label class="form-check-label" for="minus">Minus (dug) prenesi u novu godinu</label>
            <div class="form-text">Bez kvačice lovac koji je zbog odstrela u minusu kreće od 0. S kvačicom minus ide u novu godinu kao <span class="text-danger">staro</span> i prvo se mora odraditi.</div>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<script>document.currentScript.previousElementSibling.addEventListener('submit', function () {
    var c = this.querySelector('[name=Prijenos]:checked'), i = c ? this.querySelector('[data-za="' + c.value + '"]') : null;
    document.getElementById('PrijenosBroj').value = i ? i.value : '';
});</script>

<?php if (bodovi_odstrel_ukljuceno()): $iz = bodovi_odstrel_iznosi(); $i = 0; ?>
<form method="post" class="card mb-4" id="odstrel" style="max-width:760px"><?= csrf() ?><input type="hidden" name="radnja" value="odstrel">
    <div class="card-header">🦌 Bodovi za odstrel <span class="text-muted small">(oduzimaju se automatski pri upisu odstrela)</span></div>
    <div class="card-body">
        <p class="small text-muted">Koliko se bodova lovcu oduzme za odstrel – odvojeno iz <span class="text-danger fw-semibold">starih</span> (prenesenih iz prošle godine) i <b>novih</b> bodova.
            Npr. jelen: staro 300 + novo 300 = oduzima se 600. Bez podjele na staro/novo upišite sve u „novo“. Prazno = nema stalnog iznosa: pri upisu odstrela upisivač sam upiše koliko oduzeti (ili ništa).
            Redak „cijela vrsta“ vrijedi za kategorije bez vlastitog iznosa. Ako lovac nema dovoljno, bodovi idu u minus (upisivač vidi upozorenje). Gost i već upisani odstrel – bez oduzimanja.</p>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr class="small"><th>Vrsta / kategorija</th><th style="width:7rem" class="text-danger">Staro</th><th style="width:7rem">Novo</th></tr></thead>
            <tbody>
            <?php foreach (odstrel_vrste() as $vrsta => $kategorije): ?>
                <?php foreach (array_merge([''], $kategorije) as $kat): $kl = $vrsta . '|' . $kat; $v = $iz[$kl] ?? null; $i++; ?>
                <tr<?= $kat === '' ? ' class="table-light"' : '' ?>>
                    <td><?= $kat === '' ? '<b>' . e($vrsta) . '</b> <span class="small text-muted">cijela vrsta</span>' : '<span class="ms-3">' . e($kat) . '</span>' ?>
                        <input type="hidden" name="iz[<?= $i ?>][k]" value="<?= e($kl) ?>"></td>
                    <td><input name="iz[<?= $i ?>][s]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm text-danger" value="<?= $v && $v['staro'] ? e((string) $v['staro']) : '' ?>"></td>
                    <td><input name="iz[<?= $i ?>][n]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" value="<?= $v && $v['novo'] ? e((string) $v['novo']) : '' ?>"></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="small text-muted mt-2">Vrste i kategorije su iz Sustav → Odstrel. Promjena vrijedi za odstrel upisan od sada; izmjena ili brisanje odstrela odmah ispravlja oduzete bodove.</div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<?php elseif (modul('odstrel')): ?>
<p class="small text-muted" style="max-width:760px">🦌 Oduzimanje bodova za odstrel: uključuje se u <a href="<?= e(url('sustav/moduli')) ?>">Sustav → Moduli → Bodovi za odstrel</a>.</p>
<?php endif; ?>

<div class="card" style="max-width:760px">
    <div class="card-header">🏅 Bodovi za funkciju (jednom godišnje, odmah odobreno)</div>
    <div class="card-body">
        <?php if ($fun): ?><ul class="mb-2"><?php foreach ($fun as $f): ?><li><?= e($f['Naziv']) ?>: <b><?= e(broj((float) $f['Bodovi'], 2)) ?></b> b. svake godine <?= e(bodovi_dan_prikaz($f['BodoviDatum'])) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="text-muted mb-2">Nijedna funkcija nema bodove.</p><?php endif; ?>
        <a href="<?= e(url('sustav/uloge')) ?>#funkcije">Uredi u Uloge i prava → Funkcije u udruzi →</a>
    </div>
</div>
<?php $obveze = redovi('SELECT o.*, (SELECT COUNT(*) FROM ObvezaClanovi oc JOIN Clanovi c ON c.Id=oc.ClanId WHERE oc.ObvezaId=o.Id AND oc.Aktivan=1 AND c.Status=0) AS Ukljuceno FROM GodisnjeObveze o ORDER BY o.Naziv'); ?>
<h2 class="h5 mt-4 mb-1" id="obveze">📅 Godišnji bodovi za obveze <span class="fw-normal text-muted small">(1× godišnje, odmah odobreno)</span></h2>
<p class="small text-muted mb-2" style="max-width:760px">Dodjela bodova za razne obveze jednom godišnje – npr. <b>držanje lovačkog psa</b> (uz ugovor s udrugom), briga o hranilištu, održavanje čeke … Bodove i dan određuje admin ovdje;
    članove uključuje / isključuje ovlaštena osoba (pravo „Godišnje obveze – dodjela“, Sustav → Uloge i prava) na kartici člana → Funkcije ili u <a href="<?= e(url('akcije/obveze')) ?>">Radne akcije → 📅 Godišnje obveze</a>. Uključeni član dobiva bodove na zadani dan – uključen kasnije: odmah. Svaki najviše jednom godišnje; te se obveze ne upisuju ručno.</p>
<?php if (modul('psi')):
    $psiV = vrsta_akcije_psi();
    $psiBod = (float) vrijednost('SELECT StandardniBodovi FROM VrsteAkcija WHERE Id=?', [$psiV]);
    $sve = redovi('SELECT Id, Naziv, StandardniBodovi, Aktivna FROM VrsteAkcija ORDER BY Naziv');
    $brPsa = (int) vrijednost('SELECT COUNT(*) FROM LovniPsi p JOIN Clanovi c ON c.Id=p.ClanId WHERE p.Aktivan=1 AND p.Bodovanje=1 AND c.Status=0'); ?>
<form method="post" class="card mb-3" id="psi" style="max-width:760px"><?= csrf() ?><input type="hidden" name="radnja" value="psi">
    <div class="card-header">🐕 Držanje lovačkog psa <span class="text-muted small">(ugrađeno – po psu, uključuje se na kartici psa kod člana)</span></div>
    <div class="card-body">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-sm-6"><label class="form-label small">Vrsta radne akcije</label>
                <select name="PsiVrsta" class="form-select" onchange="var o=this.options[this.selectedIndex];this.form.PsiBodovi.value=o.dataset.b">
                    <?php foreach ($sve as $v): ?><option value="<?= (int) $v['Id'] ?>" data-b="<?= e((string) (float) $v['StandardniBodovi']) ?>"<?= sel($v['Id'], $psiV) ?>><?= e($v['Naziv']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-6 col-sm-3"><label class="form-label small">Bodovi po psu</label>
                <input name="PsiBodovi" type="number" step="0.5" min="0" inputmode="decimal" class="form-control" value="<?= e((string) $psiBod) ?>" required></div>
            <div class="col-6 col-sm-3"><label class="form-label small">Dan upisa svake godine</label>
                <input name="PsiDan" class="form-control" value="<?= e(bodovi_dan_prikaz(psi_bodovi_dan())) ?>" placeholder="dan.mjesec." required></div>
        </div>
        <?php if ($psiBod <= 0): ?><div class="alert alert-warning small py-2 mb-2">Bodovi su 0 – ništa se ne upisuje. Upišite bodove po psu i spremite.</div><?php endif; ?>
        <div class="small text-muted">Upisuje se samo za psa koji je <b>aktivan</b> i ima <b>uključene bodove</b> (uključuje / isključuje osoba s pravom „Godišnje obveze – dodjela“ kod člana → Lovački psi; član sam ne može). Svaki pas najviše jednom godišnje.
            Pas uključen nakon tog dana dobiva bodove odmah, s današnjim datumom. Ta se vrsta ne nudi za ručni upis, a već ručno upisana akcija te vrste u istoj godini se samo poveže – nema dvostrukih bodova.
            Promjena bodova vrijedi za nove upise – već upisani bodovi ostaju.
            Trenutno uključenih pasa: <b><?= $brPsa ?></b>.</div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<?php endif; ?>
<form method="post" class="card" style="max-width:760px"><?= csrf() ?><input type="hidden" name="radnja" value="obveze">
    <div class="card-header">📅 Ostale obveze</div>
    <div class="card-body">
        <div class="table-responsive"><table class="table table-sm align-middle mb-2">
            <thead><tr class="small"><th>Obveza</th><th style="width:7rem">Bodovi</th><th style="width:7rem">Dan</th><th class="text-center">Aktivna</th><th class="text-center">Članova</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($obveze as $o): $oid = (int) $o['Id']; ?>
                <tr>
                    <td><input name="obveza[<?= $oid ?>][Naziv]" class="form-control form-control-sm" maxlength="80" value="<?= e($o['Naziv']) ?>" required></td>
                    <td><input name="obveza[<?= $oid ?>][Bodovi]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" value="<?= e((string) (float) $o['Bodovi']) ?>"></td>
                    <td><input name="obveza[<?= $oid ?>][Dan]" class="form-control form-control-sm" value="<?= e(bodovi_dan_prikaz($o['Dan'])) ?>" placeholder="dan.mjesec."></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" name="obveza[<?= $oid ?>][Aktivna]" value="1"<?= chk($o['Aktivna']) ?>></td>
                    <td class="text-center"><a href="<?= e(url('akcije/obveze')) ?>#o<?= $oid ?>"><?= (int) $o['Ukljuceno'] ?></a></td>
                    <td class="text-end"><label class="small text-danger text-nowrap"><input type="checkbox" class="form-check-input" name="obveza[<?= $oid ?>][obrisi]" value="1"> obriši</label></td>
                </tr>
            <?php endforeach; ?>
            <tr class="table-light">
                <td><input name="nova[Naziv]" class="form-control form-control-sm" maxlength="80" placeholder="+ nova obveza, npr. Briga o hranilištu"></td>
                <td><input name="nova[Bodovi]" type="number" step="0.5" min="0" inputmode="decimal" class="form-control form-control-sm" placeholder="bodovi"></td>
                <td><input name="nova[Dan]" class="form-control form-control-sm" placeholder="1.1."></td>
                <td colspan="3"></td>
            </tr>
            </tbody>
        </table></div>
        <div class="small text-muted">Promjena bodova vrijedi za nove upise – već upisani bodovi ostaju. Brisanje obveze ne briše već upisane bodove.</div>
    </div>
    <div class="card-footer"><button class="btn btn-primary">Spremi</button></div>
</form>
<?php stranica('Bodovi', ob_get_clean());
