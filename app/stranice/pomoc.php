<?php
/** O programu, pomoć i kontakt s autorom (prijava greške, prijedlog, pitanje). */
$k = korisnik();
$info = "\n\n---\nUdruga: " . udruga_naziv() . "\nVerzija programa: " . VERZIJA . "\nPHP: " . PHP_VERSION . "\nUređaj / preglednik: " . mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160);
$mail = fn(string $vrsta, string $uvod) => 'mailto:' . KONTAKT_EMAIL . '?subject=' . rawurlencode('Lovačka evidencija – ' . $vrsta) . '&body=' . rawurlencode($uvod . $info);
ob_start(); ?>
<h1 class="h3 mb-1">Pomoć i kontakt</h1>
<p class="text-muted">Lovačka evidencija – besplatni program za lovačke udruge · verzija <b><?= e(VERZIJA) ?></b></p>
<div class="row g-3">
    <div class="col-md-6"><div class="card h-100"><div class="card-body">
        <h2 class="h5">✉️ Pišite autoru</h2>
        <p class="small text-muted">Pronašli ste grešku, trebate pomoć ili imate prijedlog? Javite se e-poštom – odgovaramo čim stignemo.
            Verzija programa i naziv udruge automatski se dodaju u poruku (bez podataka članova).</p>
        <div class="d-grid gap-2">
            <a class="btn btn-outline-danger" href="<?= e($mail('greška', "Opis greške (što ste radili, što se dogodilo, na kojoj stranici):\n\n")) ?>">🐞 Prijavi grešku</a>
            <a class="btn btn-outline-primary" href="<?= e($mail('prijedlog', "Što biste željeli da program još radi?\n\n")) ?>">💡 Prijedlog / želja</a>
            <a class="btn btn-outline-secondary" href="<?= e($mail('pomoć', "Pitanje:\n\n")) ?>">❓ Trebam pomoć</a>
        </div>
        <p class="small mt-2 mb-0">E-pošta: <a href="mailto:<?= e(KONTAKT_EMAIL) ?>"><?= e(KONTAKT_EMAIL) ?></a></p>
    </div></div></div>
    <div class="col-md-6"><div class="card h-100"><div class="card-body">
        <h2 class="h5">💻 GitHub</h2>
        <p class="small text-muted">Program je otvorenog koda. Na GitHubu su upute, nove verzije i popis prijavljenih grešaka (potreban besplatan GitHub račun).</p>
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= e(PROJEKT_URL) ?>">Upute i nove verzije</a>
            <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= e(PROJEKT_URL . '/issues') ?>">Greške i prijedlozi (Issues)</a>
        </div>
        <?php if (ima(P_SUSTAV)): ?><p class="small text-muted mt-2 mb-0">Za administratore: prije slanja greške pogledajte i <a href="<?= e(url('sustav/dnevnik')) ?>">dnevnik promjena</a>.</p><?php endif; ?>
    </div></div></div>
</div>
<p class="small text-muted mt-3">Napomena: autor nema pristup podacima vaše udruge – svi podaci ostaju na vašem poslužitelju. Ne šaljite osobne podatke članova e-poštom.</p>
<?php stranica('Pomoć i kontakt', ob_get_clean());
