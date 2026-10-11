<?php
/**
 * Vodiči pasa: svi lovci vide tko ima lovačkog psa (npr. za potragu na krvnom tragu).
 * Kontakt samo ako ga je član sam podijelio (Moj profil → dijeljenje) – inače poruka u aplikaciji.
 * Psi s isključenim „prikaži u popisu“ i neaktivni psi se ne prikazuju.
 */
$k = korisnik();
$tr = trim((string) ($_GET['q'] ?? ''));
$samoPotraga = !empty($_GET['potraga']);
$sek = ul_int('sekcija');
$psi = redovi('SELECT p.*, c.Id AS ClanId, c.Ime AS CIme, c.Prezime, c.Nadimak, c.FotoDatoteka, c.SekcijaId, c.MobilniTelefon, c.Email,
        s.Naziv AS Sekcija, v.Mobitel AS VMob, v.Email AS VMail,
        (SELECT ko.Id FROM Korisnici ko WHERE ko.ClanId=c.Id AND ko.Aktivan=1 AND ko.Odobren=1 LIMIT 1) AS KorisnikId
    FROM LovniPsi p JOIN Clanovi c ON c.Id=p.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId LEFT JOIN ClanVidljivost v ON v.ClanId=c.Id
    WHERE p.Aktivan=1 AND p.UPopisu=1 AND c.Status=0' . ($samoPotraga ? ' AND p.Potraga=1' : '') . ($sek ? ' AND c.SekcijaId=' . (int) $sek : ''));
if ($tr !== '') {
    $kl = kljuc($tr);
    $psi = array_filter($psi, fn($p) => str_contains(kljuc($p['CIme'] . ' ' . $p['Prezime'] . ' ' . $p['Nadimak'] . ' ' . $p['Ime'] . ' ' . $p['Pasmina']), $kl));
}
// grupirano po vodiču
$vodici = [];
foreach ($psi as $p) {
    $vodici[(int) $p['ClanId']]['clan'] ??= ['Id' => $p['ClanId'], 'Ime' => $p['CIme'], 'Prezime' => $p['Prezime'], 'Nadimak' => $p['Nadimak'], 'FotoDatoteka' => $p['FotoDatoteka'],
        'SekcijaId' => $p['SekcijaId'], 'Sekcija' => $p['Sekcija'], 'MobilniTelefon' => $p['MobilniTelefon'], 'Email' => $p['Email'], 'VMob' => $p['VMob'], 'VMail' => $p['VMail'], 'KorisnikId' => $p['KorisnikId']];
    $vodici[(int) $p['ClanId']]['psi'][] = $p;
}
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
uasort($vodici, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a['clan']), prezime_ime($b['clan'])) : strcmp(kljuc(prezime_ime($a['clan'])), kljuc(prezime_ime($b['clan']))));
$brojPasa = count($psi);
$ispiti = [];
if ($psi) {
    foreach (redovi('SELECT PasId, Naziv, Ocjena FROM PsiIspiti WHERE PasId IN (' . implode(',', array_map(fn($p) => (int) $p['Id'], $psi)) . ') ORDER BY Datum DESC') as $i) {
        $ispiti[(int) $i['PasId']][] = $i['Naziv'] . ($i['Ocjena'] ? ' – ' . $i['Ocjena'] : '');
    }
}
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
    <h1 class="h3 mb-0 me-auto">🐕 Vodiči pasa</h1>
    <?php if ($k['ClanId']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('moj-profil', ['psi' => 1])) ?>">Moji psi</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('moj-profil')) ?>#dijeljenje">Što ja dijelim?</a><?php endif; ?>
</div>
<p class="small text-muted">Članovi s lovačkim psom – npr. kad trebate psa za potragu za ranjenom divljači. Telefon se vidi samo ako ga je član podijelio, inače mu pišite porukom u aplikaciji.</p>
<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3"><input type="hidden" name="p" value="vodici-pasa">
    <input name="q" class="form-control" style="max-width:260px" placeholder="Ime, pas ili pasmina" value="<?= e($tr) ?>">
    <?php $sv = sekcije(true); if ($sv): ?><select name="sekcija" class="form-select" style="width:auto" data-auto><option value="">Sve sekcije</option>
        <?php foreach ($sv as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $sek) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="potraga" value="1" id="fPotraga"<?= chk($samoPotraga) ?> data-auto>
        <label class="form-check-label" for="fPotraga">🩸 samo psi za potragu (krvni trag)</label></div>
    <button class="btn btn-outline-primary">Traži</button>
</form>
<div class="small text-muted mb-2"><?= count($vodici) ?> vodiča · <?= $brojPasa ?> <?= $brojPasa === 1 ? 'pas' : 'pasa' ?></div>
<div class="list-group">
<?php foreach ($vodici as $v): $c = $v['clan'];
    $ja = (int) $c['Id'] === (int) $k['ClanId'];
    $sve = $ja || vidi_sve_kontakte($c);
    $mob = $c['MobilniTelefon'] && ($sve || $c['VMob']) ? $c['MobilniTelefon'] : null; ?>
    <div class="list-group-item">
        <div class="d-flex gap-3 align-items-start">
            <?= avatar($c) ?>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold"><?= e(prezime_ime($c)) ?><?= $c['Nadimak'] ? ' <span class="text-muted fw-normal">„' . e($c['Nadimak']) . '“</span>' : '' ?><?= $ja ? ' <span class="badge text-bg-light border">ja</span>' : '' ?></div>
                <div class="small text-muted"><?= e($c['Sekcija'] ?? '') ?></div>
                <?php foreach ($v['psi'] as $p): ?>
                    <div class="mt-1">
                        <b>🐕 <?= e($p['Ime']) ?></b>
                        <span class="small text-muted"><?= e(implode(' · ', array_filter([$p['Pasmina'], $p['Spol'], $p['DatumRodjenja'] ? substr($p['DatumRodjenja'], 0, 4) . '.' : null]))) ?></span>
                        <?php if ($p['Potraga']): ?><span class="badge bg-danger-subtle text-danger-emphasis border">🩸 potraga</span><?php endif; ?>
                        <?php if (!empty($ispiti[(int) $p['Id']])): ?><div class="small text-muted">ispiti: <?= e(implode('; ', array_slice($ispiti[(int) $p['Id']], 0, 3))) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$ja): ?><div class="small mt-1">📱 <?= $mob ? e($mob) : ($c['MobilniTelefon'] ? '<span class="text-muted fst-italic">broj nije podijelio/la – pišite porukom</span>' : '<span class="text-muted">—</span>') ?></div><?php endif; ?>
            </div>
        </div>
        <?php if (!$ja): ?>
        <div class="d-flex flex-wrap gap-1 mt-2">
            <?php if ($c['KorisnikId']): ?><a class="btn btn-sm btn-primary" href="<?= e(url('poruke/osoba', ['k' => $c['KorisnikId']])) ?>">💬 Poruka u aplikaciji</a>
            <?php else: ?><span class="small text-muted align-self-center me-2">nema račun u aplikaciji</span><?php endif; ?>
            <?= gumbi_kontakta($mob) ?>
        </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$vodici): ?><div class="list-group-item text-muted">Nema pasa<?= $samoPotraga ? ' za potragu' : '' ?><?= $tr !== '' ? ' za „' . e($tr) . '“' : '' ?>. Psa upisuje svaki član u <a href="<?= e(url('moj-profil', ['psi' => 1])) ?>">Moj profil</a>.</div><?php endif; ?>
</div>
<?php stranica('Vodiči pasa', ob_get_clean());
