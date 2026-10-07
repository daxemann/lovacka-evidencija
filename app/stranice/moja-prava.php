<?php
$k = korisnik();
ob_start(); ?>
<h1 class="h4 mb-3">Prijavljeni ste kao <?= e($k['Naziv']) ?></h1>
<dl class="row">
    <dt class="col-sm-3">Korisničko ime</dt><dd class="col-sm-9"><?= e($k['KorisnickoIme']) ?></dd>
    <dt class="col-sm-3">Uloge</dt>
    <dd class="col-sm-9"><?php if (!$k['Uloge']): ?><span>Član (bez dodatnih uloga)</span><?php endif; ?>
        <?php foreach ($k['Uloge'] as $u): ?><span class="badge bg-success me-1"><?= e($u) ?></span><?php endforeach; ?></dd>
    <?php if ($k['Prava']): ?>
        <dt class="col-sm-3">Sekcije</dt>
        <dd class="col-sm-9"><?= $k['SveSekcije'] ? 'sve sekcije' : e(implode(', ', array_map('naziv_sekcije', $k['Sekcije']))) ?></dd>
    <?php endif; ?>
</dl>
<h2 class="h6">Što smijete</h2>
<ul class="list-group mb-3" style="max-width:720px">
    <?php if ($k['ClanId']): ?><li class="list-group-item">✔ vlastiti profil i kontakt podaci, vlastite radne akcije i bodovi</li><?php endif; ?>
    <li class="list-group-item">✔ oglasnik (kupujem / prodajem) i poruke</li>
    <?php foreach (PRAVA_OPIS as $p => [$naziv, $opis]): if (!ima($p)) continue; ?>
        <li class="list-group-item">✔ <b><?= e($naziv) ?></b> – <?= e($opis) ?> <?php if (!$k['SveSekcije'] && $p !== P_SUSTAV): ?><span class="small text-muted">(samo vaše sekcije)</span><?php endif; ?></li>
    <?php endforeach; ?>
</ul>
<p class="small text-muted">Uloge dodjeljuje glavni administrator (Članovi → član → Pristup i uloge).</p>
<?php stranica('Moja prava', ob_get_clean());
