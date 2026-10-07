<?php
if (je_post()) {
    if (!posta_dostupna()) {
        preusmjeri('zaboravljena-lozinka', ['status' => 'nema-poste']);
    }
    $ime = mb_strtolower(ul_str('korisnik'));
    $k = red('SELECT k.*, c.Email, c.Ime, c.Prezime FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId
              WHERE k.Aktivan=1 AND (k.KorisnickoIme=? OR lower(trim(c.Email))=?) LIMIT 1', [$ime, $ime]);
    if ($k && trim((string) $k['Email']) !== '') {
        $t = novi_token();
        umetni('ResetiLozinki', ['KorisnikId' => $k['Id'], 'TokenHash' => hash_tokena($t), 'VrijediDo' => date('Y-m-d H:i:s', time() + 3600), 'Iskoristen' => 0]);
        $link = apsolutni_url('nova-lozinka', ['token' => $t]);
        $naziv = $k['ClanId'] ? trim($k['Ime'] . ' ' . $k['Prezime']) : ($k['PrikaznoIme'] ?: $k['KorisnickoIme']);
        try {
            posalji_mail($k['Email'], 'Nova lozinka – ' . udruga_kratko() . ' evidencija',
                '<p>Poštovani ' . e($naziv) . ',</p><p>za postavljanje nove lozinke kliknite na poveznicu (vrijedi 1 sat):</p><p><a href="' . e($link) . '">' . e($link) . '</a></p><p>Ako niste tražili novu lozinku, zanemarite ovu poruku.</p>');
        } catch (Throwable $e) {
            error_log('Slanje poveznice za lozinku nije uspjelo: ' . $e->getMessage());
        }
    }
    preusmjeri('zaboravljena-lozinka', ['status' => 'poslano']);
}
$s = (string) ($_GET['status'] ?? '');
ob_start(); ?>
<h1 class="h4 mb-3">Zaboravljena lozinka</h1>
<?php if ($s === 'poslano'): ?>
    <div class="alert alert-success">Ako za taj račun postoji e-mail adresa, poslali smo poveznicu za novu lozinku. Provjerite poštu (i neželjenu poštu).</div>
<?php elseif ($s === 'nema-poste'): ?>
    <div class="alert alert-warning">Slanje e-pošte još nije podešeno. Obratite se administratoru udruge – on vam može postaviti novu lozinku.</div>
<?php else: ?>
    <p class="text-muted">Unesite korisničko ime ili e-mail adresu. Poslat ćemo vam poveznicu za postavljanje nove lozinke.</p>
    <form method="post" action="<?= e(url('zaboravljena-lozinka')) ?>"><?= csrf() ?>
        <div class="mb-3"><label class="form-label">Korisničko ime ili e-mail</label>
            <input name="korisnik" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-100">Pošalji poveznicu</button>
    </form>
<?php endif; ?>
<div class="text-center mt-3"><a href="<?= e(url('prijava')) ?>">Natrag na prijavu</a></div>
<p class="small text-muted mt-3 mb-0">Nemate e-mail adresu? Administrator vam može postaviti novu lozinku.</p>
<?php stranica('Zaboravljena lozinka', ob_get_clean(), 'prazno');
