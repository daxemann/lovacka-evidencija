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
    $p = posta_postavke();
    return $p['Posluzitelj'] !== '' && ($p['Posiljatelj'] !== '' || $p['Korisnik'] !== '');
}

/**
 * Šalje e-poštu. $html = true → tijelo je HTML.
 * Baca iznimku ako slanje ne uspije.
 */
function posalji_mail(array|string $prima, string $naslov, string $tijelo, bool $html = true, ?array $postavke = null, array $privitci = []): void
{
    $p = $postavke ?? posta_postavke();
    if ($p['Posluzitelj'] === '') {
        throw new RuntimeException('Slanje e-pošte nije podešeno (Sustav → E-pošta i adresa).');
    }
    $m = new PHPMailer(true);
    $m->isSMTP();
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
