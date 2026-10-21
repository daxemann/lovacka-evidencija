<?php
/**
 * Lovni gost člana na terminu lova: član pri „Dolazim“ prijavi jednog gosta (podaci + QR pozivnica).
 * QR vrijedi samo za tu pozivnicu i samo na dan termina; na popisu skupnog lova gost se skenira, dopuni podatke i potpiše.
 */
declare(strict_types=1);

const GOST_PREFIKS = 'LEG1:';

/** Može li se na ovu vrstu termina dovesti lovni gost (vrste lova). */
function gost_dopusten(array $vrsta): bool
{
    return !empty($vrsta['psi']);
}
function gost_clana(int $dogadjajId, int $clanId): ?array
{
    return red('SELECT * FROM DogadjajGosti WHERE DogadjajId=? AND ClanId=?', [$dogadjajId, $clanId]);
}
function gosti_termina(int $dogadjajId): array
{
    return redovi('SELECT g.*, c.Ime AS CIme, c.Prezime AS CPrezime FROM DogadjajGosti g JOIN Clanovi c ON c.Id=g.ClanId WHERE g.DogadjajId=? ORDER BY g.Prezime, g.Ime', [$dogadjajId]);
}
function gost_qr_tekst(array $g): string
{
    return GOST_PREFIKS . $g['Token'];
}
/** Sprema (novi ili izmjena) gosta člana. @return string|null greška */
function gost_spremi(array $d, int $clanId, array $u): ?string
{
    $ime = mb_substr(trim((string) ($u['ime'] ?? '')), 0, 60);
    $prez = mb_substr(trim((string) ($u['prezime'] ?? '')), 0, 60);
    if ($ime === '' || $prez === '') {
        return 'Upišite ime i prezime gosta.';
    }
    $mail = trim((string) ($u['email'] ?? ''));
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        return 'E-adresa gosta nije ispravna.';
    }
    $pod = ['Ime' => $ime, 'Prezime' => $prez, 'BrojIskaznice' => mb_substr(trim((string) ($u['iskaznica'] ?? '')), 0, 40) ?: null,
        'OruzniList' => mb_substr(trim((string) ($u['oruzni'] ?? '')), 0, 120) ?: null, 'Email' => $mail ?: null];
    if ($g = gost_clana((int) $d['Id'], $clanId)) {
        azuriraj('DogadjajGosti', (int) $g['Id'], $pod);
    } else {
        umetni('DogadjajGosti', $pod + ['DogadjajId' => (int) $d['Id'], 'ClanId' => $clanId, 'Token' => strtoupper(bin2hex(random_bytes(12))), 'Kreirano' => sada()]);
    }
    dnevnik('Lovni gost prijavljen', 'Dogadjaj', (int) $d['Id'], $ime . ' ' . $prez);
    return null;
}
function gost_ukloni(int $dogadjajId, int $clanId): void
{
    q('DELETE FROM DogadjajGosti WHERE DogadjajId=? AND ClanId=?', [$dogadjajId, $clanId]);
}
/** Gost po skeniranom QR-u – samo ako je termin danas (pozivnica vrijedi samo za taj dan). */
function gost_po_qr(string $tekst, string $dan): array
{
    $t = strtoupper(trim($tekst));
    if (!str_starts_with($t, GOST_PREFIKS)) {
        return [null, null];
    }
    $t = substr($t, strlen(GOST_PREFIKS));
    $g = preg_match('/^[0-9A-F]{24}$/', $t) ? red('SELECT g.*, d.Pocetak, d.Kraj, d.Naslov, c.Ime AS CIme, c.Prezime AS CPrezime FROM DogadjajGosti g
        JOIN Dogadjaji d ON d.Id=g.DogadjajId JOIN Clanovi c ON c.Id=g.ClanId WHERE g.Token=?', [$t]) : null;
    if (!$g) {
        return [null, 'Pozivnica gosta ne vrijedi (otkazana ili zamijenjena).'];
    }
    $od = substr($g['Pocetak'], 0, 10);
    $do = substr($g['Kraj'] ?: $g['Pocetak'], 0, 10);
    if ($dan < $od || $dan > $do) {
        return [null, 'Pozivnica gosta ' . $g['Ime'] . ' ' . $g['Prezime'] . ' vrijedi samo ' . datum($g['Pocetak']) . ' (' . $g['Naslov'] . ').'];
    }
    return [$g, null];
}
/** PDF pozivnice s QR kodom (za e-poštu gostu). */
function gost_pozivnica_pdf(array $g, array $d): string
{
    $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#222;text-align:center}
        .n{font-size:15pt;font-weight:bold;color:#2f5d23;margin-top:10mm}.qr{width:70mm;height:70mm;margin:8mm auto}.m{color:#666;font-size:9pt}</style></head><body>
        <div class="n">' . e(udruga_naziv()) . '</div><div>Pozivnica za lovnog gosta</div>
        <h2>' . e($g['Ime'] . ' ' . $g['Prezime']) . '</h2>
        <div><b>' . e($d['Naslov']) . '</b><br>' . e(oznaka_vremena($d)) . ' ' . e(substr($d['Pocetak'], 0, 4)) . '.' . ($d['Mjesto'] ? '<br>' . e($d['Mjesto']) : '') . '</div>
        <img class="qr" src="' . qr_data_uri(gost_qr_tekst($g)) . '">
        <div>Pozvao: ' . e(trim(($g['CIme'] ?? '') . ' ' . ($g['CPrezime'] ?? ''))) . '</div>
        <p class="m">Pokažite QR kod voditelju lova – upisuje vas na popis sudionika, gdje se samo potpišete.<br>Vrijedi samo za ovaj lov.</p></body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A5', 'portrait');
    $pdf->render();
    return (string) $pdf->output();
}
/** Šalje gostu pozivnicu e-poštom. @return string|null greška */
function gost_posalji(array $g, array $d): ?string
{
    if (!$g['Email']) {
        return 'Gost nema upisanu e-adresu.';
    }
    $g = red('SELECT g.*, c.Ime AS CIme, c.Prezime AS CPrezime FROM DogadjajGosti g JOIN Clanovi c ON c.Id=g.ClanId WHERE g.Id=?', [$g['Id']]) ?? $g;
    $tijelo = '<p>Poštovani/a ' . e($g['Ime']) . ',</p><p>' . e(trim($g['CIme'] . ' ' . $g['CPrezime'])) . ' vas poziva kao lovnog gosta: <b>' . e($d['Naslov']) . '</b>, '
        . e(oznaka_vremena($d)) . ' ' . e(substr($d['Pocetak'], 0, 4)) . '.' . ($d['Mjesto'] ? ', ' . e($d['Mjesto']) : '') . '.</p>'
        . '<p>U privitku je pozivnica s QR kodom – pokažite je voditelju lova (na mobitelu ili ispisano). Vrijedi samo za ovaj lov.</p>'
        . '<p style="color:#888;font-size:12px">' . e(udruga_naziv()) . '</p>';
    try {
        posalji_mail($g['Email'], 'Pozivnica – ' . $d['Naslov'] . ' (' . datum($d['Pocetak']) . ')', $tijelo, true, null,
            ['pozivnica-' . bez_dijakritika(strtolower($g['Prezime'])) . '.pdf' => gost_pozivnica_pdf($g, $d)]);
        q('UPDATE DogadjajGosti SET Poslano=? WHERE Id=?', [sada(), $g['Id']]);
        dnevnik('Pozivnica lovnom gostu poslana', 'Dogadjaj', (int) $d['Id'], $g['Ime'] . ' ' . $g['Prezime'] . ' · ' . $g['Email']);
        return null;
    } catch (Throwable $e) {
        return objasni_smtp_gresku($e);
    }
}
