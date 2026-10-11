<?php
/** E-pošta (SMTP preko PHPMailera) – postavke iz tablice Postavke (Server.*). */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

function posta_postavke(): array
{
    return [
        'Posluzitelj' => postavka('Server.Posluzitelj', ''),
        'Port' => (int) postavka('Server.Port', '587'),
        'Ssl' => postavka('Server.Ssl', '1') !== '0',
        'Korisnik' => postavka('Server.Korisnik', ''),
        'Lozinka' => desifriraj(postavka('Server.Lozinka')) ?? '',
        'Posiljatelj' => postavka('Server.Posiljatelj', ''),
        'NazivPosiljatelja' => postavka('Server.NazivPosiljatelja', ''),
    ];
}

function posta_dostupna(): bool
{
    if (je_demo()) {
        return true;
    }
    $p = posta_postavke();
    return $p['Posluzitelj'] !== '' && ($p['Posiljatelj'] !== '' || $p['Korisnik'] !== '');
}

/**
 * Šalje e-poštu. $html = true → tijelo je HTML.
 * Baca iznimku ako slanje ne uspije.
 */
function posalji_mail(array|string $prima, string $naslov, string $tijelo, bool $html = true, ?array $postavke = null, array $privitci = []): void
{
    if (je_demo()) {
        demo_spremi_mail((array) $prima, $naslov, $tijelo, $html, $privitci);
        return;
    }
    $p = $postavke ?? posta_postavke();
    if ($p['Posluzitelj'] === '') {
        throw new RuntimeException('Slanje e-pošte nije podešeno (Sustav → E-pošta i adresa).');
    }
    $m = new PHPMailer(true);
    $m->isSMTP();
    // zadnji odgovori poslužitelja – za razumljive poruke o grešci (lozinka se ne bilježi)
    $GLOBALS['smtp_dijalog'] = [];
    $m->SMTPDebug = 2;
    $m->Debugoutput = function ($s) {
        if (preg_match('/^SERVER -> CLIENT: (.*)$/s', trim((string) $s), $x)) {
            $GLOBALS['smtp_dijalog'][] = trim($x[1]);
        }
    };
    $m->CharSet = 'UTF-8';
    $m->Host = $p['Posluzitelj'];
    $m->Port = $p['Port'] ?: 587;
    $m->Timeout = 20;
    if ($p['Ssl']) {
        $m->SMTPSecure = $m->Port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $m->SMTPSecure = '';
        $m->SMTPAutoTLS = false;
    }
    if ($p['Korisnik'] !== '') {
        $m->SMTPAuth = true;
        $m->Username = $p['Korisnik'];
        $m->Password = $p['Lozinka'];
    }
    $od = $p['Posiljatelj'] ?: $p['Korisnik'];
    $m->setFrom($od, $p['NazivPosiljatelja'] ?: udruga_kratko());
    foreach ((array) $prima as $a) {
        $m->addAddress(trim($a));
    }
    $m->Subject = $naslov;
    if ($html) {
        $m->isHTML(true);
        $m->Body = $tijelo;
        $m->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $tijelo))));
    } else {
        $m->Body = $tijelo;
    }
    foreach ($privitci as $ime => $sadrzaj) {
        $m->addStringAttachment($sadrzaj, $ime);
    }
    $m->send();
}

/** Običan tekst → jednostavan HTML za e-poštu. */
function tekst_u_html(string $t): string
{
    $t = e($t);
    $t = (string) preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $t);
    return '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">' . nl2br($t) . '</div>';
}

/** Razumljivo objašnjenje greške pri slanju (posebno Gmail). */
function objasni_smtp_gresku(Throwable $e, string $posluzitelj = ''): string
{
    $sve = implode("\n", $GLOBALS['smtp_dijalog'] ?? []) . "\n" . $e->getMessage();
    $gmail = str_contains($posluzitelj, 'gmail') || str_contains($posluzitelj, 'google');
    if (preg_match('/5\.7\.9|Application-specific password required/i', $sve)) {
        return 'Google traži LOZINKU APLIKACIJE (16 znakova), ne običnu lozinku Google računa. Napravite je u koraku 3 čarobnjaka.';
    }
    if (preg_match('/5\.7\.8|Username and Password not accepted|BadCredentials|authenticate/i', $sve)) {
        return $gmail
            ? 'Google nije prihvatio prijavu. Najčešće: upisana je obična lozinka umjesto lozinke aplikacije, lozinka aplikacije je prepisana s greškom, ili korisničko ime nije puna Gmail adresa (npr. ime@gmail.com).'
            : 'Poslužitelj nije prihvatio korisničko ime ili lozinku.';
    }
    if (preg_match('/5\.7\.0.*(disabled|admin)|not allowed/i', $sve)) {
        return 'Slanje je zabranjeno na računu (kod Google Workspacea to određuje administrator domene).';
    }
    if (preg_match('/connect|Connection|timed out|getaddrinfo/i', $sve)) {
        return 'Nema veze s poslužiteljem e-pošte (adresa ili port su krivi, ili poslužitelj blokira odlazni port).';
    }
    $zadnji = end($GLOBALS['smtp_dijalog']) ?: $e->getMessage();
    return 'Slanje nije uspjelo: ' . $zadnji;
}
