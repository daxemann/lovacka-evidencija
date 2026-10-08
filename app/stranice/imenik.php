<?php
/** Imenik cijele udruge: svaki član sam odlučuje što dijeli; uprava (pravo) vidi sve. Poruka u aplikaciji uvijek. */
$k = korisnik();
$tr = trim((string) ($_GET['q'] ?? ''));
$sek = ul_int('sekcija');
$clanovi = redovi('SELECT c.*, s.Naziv AS Sekcija, v.Mobitel AS VMob, v.Fiksni AS VFix, v.Email AS VMail, v.Mjesto AS VMj, v.Adresa AS VAdr,
        (SELECT k.Id FROM Korisnici k WHERE k.ClanId=c.Id AND k.Aktivan=1 AND k.Odobren=1 LIMIT 1) AS KorisnikId
    FROM Clanovi c LEFT JOIN Sekcije s ON s.Id=c.SekcijaId LEFT JOIN ClanVidljivost v ON v.ClanId=c.Id WHERE c.Status=0');
if ($tr !== '') {
    $kl = kljuc($tr);
    $clanovi = array_filter($clanovi, fn($c) => str_contains(kljuc(puno_ime($c) . ' ' . $c['Nadimak'] . ' ' . prezime_ime($c)), $kl));
}
if ($sek) {
    $clanovi = array_filter($clanovi, fn($c) => (int) $c['SekcijaId'] === $sek);
}
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
usort($clanovi, fn($a, $b) => $coll ? $coll->compare(prezime_ime($a), prezime_ime($b)) : strcmp(kljuc(prezime_ime($a)), kljuc(prezime_ime($b))));
$nije = '<span class="text-muted fst-italic">nije podijelio/la</span>';
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <h1 class="h3 mb-0 me-auto">Imenik</h1>
    <?php if ($k['ClanId']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('moj-profil')) ?>#dijeljenje">Što ja dijelim?</a><?php endif; ?>
</div>
<form method="get" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="p" value="imenik">
    <input name="q" class="form-control" style="max-width:280px" placeholder="Traži ime ili nadimak" value="<?= e($tr) ?>" autofocus>
    <?php $sv = sekcije(true); if ($sv): ?><select name="sekcija" class="form-select" style="width:auto" data-auto><option value="">Sve sekcije</option>
        <?php foreach ($sv as $s): ?><option value="<?= (int) $s['Id'] ?>"<?= sel($s['Id'], $sek) ?>><?= e($s['Naziv']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <button class="btn btn-outline-primary">Traži</button>
</form>
<div class="list-group">
<?php foreach ($clanovi as $c):
    $ja = (int) $c['Id'] === (int) $k['ClanId'];
    $sve = $ja || vidi_sve_kontakte($c);
    $mob = $c['MobilniTelefon'] && ($sve || $c['VMob']) ? $c['MobilniTelefon'] : null;
    $fix = $c['FiksniTelefon'] && ($sve || $c['VFix']) ? $c['FiksniTelefon'] : null;
    $mail = $c['Email'] && ($sve || $c['VMail']) ? $c['Email'] : null;
    $mj = $c['Mjesto'] && ($sve || $c['VMj'] || $c['VAdr']) ? trim($c['PostanskiBroj'] . ' ' . $c['Mjesto']) : null;
    $adr = trim($c['Ulica'] . ' ' . $c['KucniBroj']) !== '' && ($sve || $c['VAdr']) ? trim($c['Ulica'] . ' ' . $c['KucniBroj']) : null;
    ?>
    <div class="list-group-item">
        <div class="d-flex gap-3 align-items-start">
            <?= avatar($c) ?>
            <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold"><?= e(prezime_ime($c)) ?><?= $c['Nadimak'] ? ' <span class="text-muted fw-normal">„' . e($c['Nadimak']) . '“</span>' : '' ?><?= $ja ? ' <span class="badge text-bg-light border">ja</span>' : '' ?></div>
                <div class="small text-muted"><?= e($c['Sekcija'] ?? '') ?></div>
                <div class="small mt-1">
                    📱 <?= $mob ? '<a href="tel:+' . e(whatsapp_broj(['MobilniTelefon' => $mob, 'FiksniTelefon' => null])) . '">' . e($mob) . '</a>' : ($c['MobilniTelefon'] ? $nije : '<span class="text-muted">—</span>') ?>
                    <?php if ($fix || ($c['FiksniTelefon'] && !$sve)): ?> · ☎ <?= $fix ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $fix)) . '">' . e($fix) . '</a>' : $nije ?><?php endif; ?>
                    <?php if ($c['Email']): ?><br>✉ <?= $mail ? '<a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : $nije ?><?php endif; ?>
                    <?php if ($c['Mjesto'] || $adr): ?><br>📍 <?= $mj || $adr ? e(implode(', ', array_filter([$adr, $mj]))) : $nije ?><?php endif; ?>
                </div>
                <?php if ($sve && (!$c['VMob'] || !$c['VMail']) && !$ja): ?><div class="small text-muted fst-italic">vidite sve podatke zbog svoje uloge u udruzi</div><?php endif; ?>
            </div>
        </div>
        <?php if (!$ja): ?>
        <div class="d-flex flex-wrap gap-1 mt-2">
            <?php if ($c['KorisnikId']): ?><a class="btn btn-sm btn-primary" href="<?= e(url('poruke/osoba', ['k' => $c['KorisnikId']])) ?>">💬 Poruka</a>
            <?php else: ?><span class="small text-muted align-self-center me-2">nema račun u aplikaciji</span><?php endif; ?>
            <?= gumbi_kontakta($mob) ?>
        </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$clanovi): ?><div class="list-group-item text-muted">Nema rezultata.</div><?php endif; ?>
</div>
<?php stranica('Imenik', ob_get_clean());
