<?php
/** Rezultat slanja uplatnica: tko je dobio e-mail, a kome treba poslati WhatsAppom / Viberom / SMS-om. */
trazi(P_CLANARINA_UREDI);
$rez = $_SESSION['uplatnice_poslano'] ?? null;
$godina = (int) ($rez['godina'] ?? ($_GET['godina'] ?? date('Y')));
$op = opseg_sql('c');
$ucitaj = fn(array $ids) => $ids ? redovi("SELECT c.* FROM Clanovi c WHERE $op AND c.Id IN (" . implode(',', array_map('intval', $ids)) . ')') : [];
$poMailu = $ucitaj($rez['mail'] ?? []);
$rucno = $ucitaj($rez['rucno'] ?? []);
$coll = class_exists('Collator') ? new Collator('hr_HR') : null;
$sort = fn(array &$a) => usort($a, fn($x, $y) => $coll ? $coll->compare(prezime_ime($x), prezime_ime($y)) : strcmp(kljuc(prezime_ime($x)), kljuc(prezime_ime($y))));
$sort($poMailu);
$sort($rucno);
ob_start(); ?>
<div class="mb-2 small"><a href="<?= e(url('clanarina', ['godina' => $godina])) ?>">← Članarina <?= $godina ?>.</a></div>
<h1 class="h3 mb-3">Slanje uplatnica – <?= $godina ?>.</h1>
<?php if (!$rez): ?>
    <div class="alert alert-secondary">Nema podataka o slanju. Odaberite članove na popisu članarine.</div>
<?php else: ?>
    <?php if ($poMailu): ?>
        <div class="alert alert-success"><b><?= count($poMailu) ?></b> član<?= count($poMailu) === 1 ? '' : 'ova' ?> dobilo je uplatnicu e-poštom (PDF u privitku):
            <?= e(implode(', ', array_map('puno_ime', $poMailu))) ?></div>
    <?php endif; ?>
    <?php if ($rucno): ?>
        <p class="mb-2"><b><?= count($rucno) ?></b> bez e-pošte – pošaljite poveznicu na uplatnicu jednim klikom (član je otvara u aplikaciji, ispisuje ili pokazuje u banci):</p>
        <?php foreach ($rucno as $c): $tekst = tekst_uplatnice($c, $godina); $wa = whatsapp_broj($c); $gr = $rez['greske'][(int) $c['Id']] ?? null; ?>
            <div class="card mb-2"><div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold me-auto"><?= e(puno_ime($c)) ?><?php if ($gr): ?> <span class="small text-danger">(e-mail nije uspio: <?= e($gr) ?>)</span><?php endif; ?></span>
                <textarea class="d-none" id="t<?= (int) $c['Id'] ?>"><?= e($tekst) ?></textarea>
                <?php if ($wa): ?>
                    <?php if (kanal_ukljucen('whatsapp')): ?><a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($tekst)) ?>">WhatsApp</a><?php endif; ?>
                    <?php if (kanal_ukljucen('viber')): ?><a class="btn btn-sm text-white" style="background:#7360f2" href="#" data-kopiraj="#t<?= (int) $c['Id'] ?>" onclick="viberPoruka('<?= e($wa) ?>')">Viber</a><?php endif; ?>
                    <a class="btn btn-sm btn-outline-secondary" href="sms:+<?= e($wa) ?>?body=<?= e(rawurlencode($tekst)) ?>">SMS</a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-kopiraj="#t<?= (int) $c['Id'] ?>">Kopiraj</button>
                <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(url('izvoz/uplatnica', ['clan' => $c['Id'], 'godina' => $godina])) ?>">🖨 PDF</a>
            </div></div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!$poMailu && !$rucno): ?><div class="alert alert-secondary">Odabrani članovi nemaju otvoren iznos.</div><?php endif; ?>
    <?php if (!posta_dostupna()): ?><p class="small text-muted mt-2">Slanje e-pošte nije podešeno (Sustav → E-pošta i adresa) – zato svi idu preko WhatsAppa / Vibera / SMS-a.</p><?php endif; ?>
<?php endif; ?>
<?php stranica('Slanje uplatnica', ob_get_clean());
