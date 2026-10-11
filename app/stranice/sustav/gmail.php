<?php
/** Čarobnjak: slanje e-pošte preko Gmaila (potvrda u 2 koraka + lozinka aplikacije), s probom prije spremanja. */
trazi(P_SUSTAV);
$c = $_SESSION['gmail_carobnjak'] ?? ['email' => postavka('Server.Korisnik', '') ?: '', 'naziv' => postavka('Server.NazivPosiljatelja', '') ?: udruga_kratko()];
if (je_post()) {
    $email = mb_strtolower(trim(ul_str('email')));
    $loz = (string) preg_replace('/\s+/', '', (string) ($_POST['lozinka'] ?? ''));
    $naziv = mb_substr(ul_str('naziv'), 0, 60);
    $_SESSION['gmail_carobnjak'] = ['email' => $email, 'naziv' => $naziv];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        poruka('Upišite punu Gmail adresu (npr. lovacka.udruga@gmail.com).', 'danger');
        preusmjeri('sustav/gmail');
    }
    if (!preg_match('/^[a-z]{16}$/i', $loz)) {
        poruka('Lozinka aplikacije ima točno 16 slova (Google je prikazuje u 4 skupine po 4 – razmaci nisu važni). '
            . (strlen($loz) ? 'Upisano je ' . strlen($loz) . ' znakova – vjerojatno je to obična lozinka računa, ona ovdje ne radi.' : ''), 'danger');
        preusmjeri('sustav/gmail', ['korak' => 3]);
    }
    $pp = ['Posluzitelj' => 'smtp.gmail.com', 'Port' => 587, 'Ssl' => true, 'Korisnik' => $email, 'Lozinka' => $loz, 'Posiljatelj' => $email, 'NazivPosiljatelja' => $naziv];
    try {
        posalji_mail($email, 'Evidencija – e-pošta radi ✔', '<p>Slanje e-pošte iz evidencije preko Gmaila je podešeno. ✔</p><p>' . e(udruga_naziv()) . '</p>', true, $pp);
    } catch (Throwable $e) {
        poruka(e(objasni_smtp_gresku($e, 'smtp.gmail.com')), 'danger');
        preusmjeri('sustav/gmail', ['korak' => 3]);
    }
    foreach (['Posluzitelj' => 'smtp.gmail.com', 'Port' => '587', 'Ssl' => '1', 'Korisnik' => $email, 'Posiljatelj' => $email, 'NazivPosiljatelja' => $naziv ?: null] as $kl => $vr) {
        spremi_postavku('Server.' . $kl, $vr);
    }
    spremi_postavku('Server.Lozinka', sifriraj($loz));
    unset($_SESSION['gmail_carobnjak']);
    dnevnik('E-pošta podešena (Gmail čarobnjak)', null, null, $email);
    poruka('Gotovo! Probna poruka je poslana na ' . e($email) . ' i postavke su spremljene.');
    preusmjeri('sustav/postavke');
}
$korak = (int) ($_GET['korak'] ?? 1);
$au = $c['email'] !== '' ? '?authuser=' . rawurlencode($c['email']) : '';
ob_start(); ?>
<div style="max-width:720px">
<div class="mb-2 small"><a href="<?= e(url('sustav/postavke')) ?>">← E-pošta i adresa</a></div>
<h1 class="h3 mb-1">Gmail – čarobnjak za slanje e-pošte</h1>
<p class="text-muted">Google ne dopušta slanje iz programa običnom lozinkom. Potrebna je <b>lozinka aplikacije</b> (16 slova), a nju Google nudi tek kad je na računu
    uključena <b>potvrda u 2 koraka</b>. Na računu udruge to se radi jednom – oko 5 minuta. Preporuka: poseban Gmail račun za udrugu, ne osobni.</p>

<form method="post" action="<?= e(url('sustav/gmail')) ?>" autocomplete="off"><?= csrf() ?>
<div class="card mb-3<?= $korak === 1 ? ' border-primary' : '' ?>"><div class="card-body">
    <h2 class="h6"><span class="badge rounded-pill bg-primary me-1">1</span> Gmail adresa pošiljatelja</h2>
    <div class="row g-2">
        <div class="col-md-7"><input name="email" type="email" class="form-control" placeholder="lovacka.udruga@gmail.com" value="<?= e($c['email']) ?>" required></div>
        <div class="col-md-5"><input name="naziv" class="form-control" placeholder="Naziv pošiljatelja" value="<?= e($c['naziv']) ?>" maxlength="60"></div>
    </div>
    <div class="form-text">U pregledniku se prijavite u Google upravo s tim računom (gore desno u Gmailu). Ako ste prijavljeni s više računa, poveznice ispod otvaraju ovaj račun.</div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <h2 class="h6"><span class="badge rounded-pill bg-primary me-1">2</span> Uključite potvrdu u 2 koraka (Google)</h2>
    <a class="btn btn-outline-primary btn-sm mb-2" target="_blank" rel="noopener" href="https://myaccount.google.com/signinoptions/twosv<?= e($au) ?>">Otvori „Potvrda u 2 koraka“ ↗</a>
    <ol class="small mb-0 ps-3">
        <li>Kliknite <b>„Uključi potvrdu u 2 koraka“</b> (ako piše „Uključeno“ – ovaj korak je gotov).</li>
        <li>Dodajte broj mobitela (SMS) ili potvrdu na mobitelu i dovršite.</li>
        <li>Ovo je Googleova zaštita računa – <b>nije</b> isto što i 2FA prijava u evidenciju.</li>
    </ol>
</div></div>

<div class="card mb-3<?= $korak === 3 ? ' border-primary' : '' ?>"><div class="card-body">
    <h2 class="h6"><span class="badge rounded-pill bg-primary me-1">3</span> Napravite lozinku aplikacije i zalijepite je ovdje</h2>
    <a class="btn btn-outline-primary btn-sm mb-2" target="_blank" rel="noopener" href="https://myaccount.google.com/apppasswords<?= e($au) ?>">Otvori „Lozinke aplikacija“ ↗</a>
    <ol class="small ps-3">
        <li>Upišite naziv aplikacije, npr. <b>Evidencija</b>, i kliknite <b>Izradi</b>.</li>
        <li>Google prikaže 16 slova u žutom okviru (npr. <code>abcd efgh ijkl mnop</code>) – kopirajte ih i zalijepite ispod. Prikazuje se samo jednom.</li>
    </ol>
    <input name="lozinka" class="form-control form-control-lg" style="font-family:monospace;letter-spacing:.1em" placeholder="abcd efgh ijkl mnop" autocomplete="off" required>
    <details class="small mt-2"><summary>Stranica kaže „Postavka koju tražite nije dostupna“?</summary>
        <ul class="mb-0 mt-1 ps-3">
            <li>Potvrda u 2 koraka još nije uključena (korak 2) – nakon uključivanja pričekajte minutu i ponovno otvorite poveznicu.</li>
            <li>Račun koristi samo sigurnosne ključeve ili „Napredna zaštita“ – lozinke aplikacija tada nisu moguće.</li>
            <li>Račun s vlastitom domenom (Google Workspace) – lozinke aplikacija mora dopustiti administrator domene.</li>
            <li>Ako ništa ne pomaže: koristite drugi račun za slanje (npr. novi Gmail samo za udrugu) ili drugog pružatelja (GMX, WEB.DE – tamo u postavkama uključiti POP3/IMAP/SMTP).</li>
        </ul></details>
</div></div>

<div class="card mb-3 border-success"><div class="card-body">
    <h2 class="h6"><span class="badge rounded-pill bg-success me-1">4</span> Proba i spremanje</h2>
    <p class="small text-muted mb-2">Program pošalje probnu poruku na gornju Gmail adresu. Tek ako uspije, sve se sprema (smtp.gmail.com, port 587, šifrirano).</p>
    <button class="btn btn-success">Pošalji probnu poruku i spremi</button>
</div></div>
</form>
</div>
<?php stranica('Gmail – čarobnjak', ob_get_clean());
