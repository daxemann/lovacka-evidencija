<?php
/** Predlošci poruka članovima (bodovi, članarina, pozivnica, odobrenje) i kanali slanja. */
declare(strict_types=1);

const PREDLOSCI_ZADANO = [
    'Predlozak.Bodovi.Naslov' => 'Tvoje radne akcije – {udruga}',
    'Predlozak.Bodovi' => "Pozdrav {ime},\n\nšaljemo ti pregled tvojih radnih akcija za razdoblje {razdoblje}:\n\n{popis}\n\nUkupno: {bodovi} bodova.\n\nHvala ti na trudu i pomoći udruzi!\n\n{potpis}",
    'Predlozak.Clanarina.Naslov' => 'Članarina {godina}. – kratka informacija',
    'Predlozak.Clanarina' => "Pozdrav {ime},\n\nsamo kratka informacija: prema našoj evidenciji članarina za {godina}. godinu iznosi {iznos} €, a do sada je evidentirano {uplaceno} € (preostalo {preostalo} €).\n\nAko si već platio, slobodno zanemari ovu poruku – možda uplata još nije upisana.\n\nHvala i lijep pozdrav!\n\n{potpis}",
    'Predlozak.Rata.Naslov' => 'Članarina {godina}. – podsjetnik',
    'Predlozak.Rata' => "Pozdrav {ime},\n\nmali podsjetnik: {rata} članarine za {godina}. ({iznos_rate} €) dospjela je {dospijece}. Prema evidenciji je otvoreno još {preostalo} €.\n\nAko si već platio, zanemari poruku – uplata možda još nije upisana.\n\nHvala!\n\n{potpis}",
    'Predlozak.Pozivnica' => "Pozdrav {ime},\n\nudruga ima novu evidenciju članova. Preko ove poveznice možeš sam postaviti svoju lozinku (poveznica vrijedi 7 dana i samo je za tebe):\n\n{link}\n\nNakon toga administrator još odobri pristup, a ti onda možeš vidjeti svoje podatke i upisivati radne akcije.\n\n{potpis}",
    'Predlozak.Odobreno' => "Pozdrav {ime},\n\ntvoj pristup evidenciji je odobren. Prijava: {link}\nKorisničko ime: {korisnik}\n\n{potpis}",
    'Predlozak.Potpis' => '{udruga}',
];
const OZNAKE_PREDLOZAKA = [
    '{ime}' => 'ime člana', '{prezime}' => 'prezime', '{razdoblje}' => 'razdoblje (bodovi)', '{popis}' => 'popis akcija (bodovi)',
    '{bodovi}' => 'zbroj bodova', '{godina}' => 'godina članarine', '{iznos}' => 'iznos članarine', '{uplaceno}' => 'uplaćeno',
    '{preostalo}' => 'preostalo', '{link}' => 'poveznica (pozivnica / prijava)', '{korisnik}' => 'korisničko ime',
    '{potpis}' => 'potpis udruge', '{udruga}' => 'naziv udruge', '{rata}' => 'dospjela rata (npr. „2. rata“)', '{iznos_rate}' => 'iznos rate',
    '{dospijece}' => 'datum dospijeća rate',
];

function predlosci_poruka(): array
{
    $r = [];
    foreach (PREDLOSCI_ZADANO as $k => $v) {
        $r[$k] = postavka($k) ?? $v;
    }
    return $r;
}

function zamijeni_oznake(string $t, array $z): string
{
    $t = strtr($t, $z);
    return strtr($t, ['{udruga}' => udruga_naziv(), '{kratko}' => udruga_kratko()]);
}

function osnovne_oznake(array $c, array $p): array
{
    return ['{ime}' => $c['Ime'], '{prezime}' => $c['Prezime'], '{potpis}' => $p['Predlozak.Potpis']];
}

/** Poruka s pregledom bodova: [naslov, tekst, grupe]. */
function poruka_bodovi(array $c, array $f): array
{
    $p = predlosci_poruka();
    $f['ClanId'] = (int) $c['Id'];
    $f['Grupiranje'] = 'Bez';
    $f['Sortiranje'] = 'Datum';
    $grupe = izvjestaj_akcija($f, true);
    foreach ($grupe as &$g) {
        $g['retci'] = array_values(array_filter($g['retci'], fn($r) => $r['Status'] !== AKCIJA_ODBIJENO));
    }
    unset($g);
    $retci = array_merge(...array_column($grupe, 'retci'));
    $popis = '';
    foreach ($retci as $r) {
        $popis .= '• ' . date('d.m.Y', strtotime($r['Datum'])) . ' – ' . $r['Vrsta'];
        if ($r['Sati'] !== null && $r['Sati'] !== '') {
            $popis .= ', ' . broj($r['Sati']) . ' h';
        }
        $popis .= $r['Status'] === AKCIJA_ODOBRENO ? ': ' . broj($r['Bodovi'], 2) . ' b.' : ' (' . mb_strtolower(STATUSI_AKCIJE[$r['Status']]) . ')';
        $popis .= "\n";
    }
    if (!$retci) {
        $popis = '(u tom razdoblju nema evidentiranih radnih akcija)';
    }
    $z = osnovne_oznake($c, $p) + ['{razdoblje}' => opis_raspona($f), '{popis}' => rtrim($popis), '{bodovi}' => broj(ukupno_bodova($retci), 2)];
    return [zamijeni_oznake($p['Predlozak.Bodovi.Naslov'], $z), zamijeni_oznake($p['Predlozak.Bodovi'], $z), $grupe];
}

/** Poruka o članarini: [naslov, tekst] ili null ako nema zaduženja. */
function poruka_clanarina(array $c, int $godina): ?array
{
    $cl = clanarina_clana((int) $c['Id'], $godina);
    if (!$cl) {
        return null;
    }
    $p = predlosci_poruka();
    $z = osnovne_oznake($c, $p) + ['{godina}' => (string) $godina, '{iznos}' => novac($cl['Iznos']), '{uplaceno}' => novac($cl['Uplaceno']), '{preostalo}' => novac(max(0, $cl['Preostalo']))];
    return [zamijeni_oznake($p['Predlozak.Clanarina.Naslov'], $z), zamijeni_oznake($p['Predlozak.Clanarina'], $z)];
}

/** Podsjetnik za dospjelu ratu (prva dospjela neplaćena). */
function poruka_rata(array $c, int $godina): ?array
{
    $cl = clanarina_clana((int) $c['Id'], $godina);
    if (!$cl) {
        return null;
    }
    $rate = rate_clanarine($cl);
    $d = array_values(array_filter($rate, fn($r) => $r['Status'] === 'dospjelo'));
    $r = $d[0] ?? null;
    if (!$r) {
        return poruka_clanarina($c, $godina);
    }
    $p = predlosci_poruka();
    $z = osnovne_oznake($c, $p) + ['{godina}' => (string) $godina, '{iznos}' => novac($cl['Iznos']), '{uplaceno}' => novac($cl['Uplaceno']),
        '{preostalo}' => novac(array_sum(array_column($d, 'Preostalo'))), '{rata}' => count($rate) > 1 ? $r['RedniBroj'] . '. rata' : 'uplata',
        '{iznos_rate}' => novac($r['Iznos']), '{dospijece}' => datum($r['Dospijece'])];
    return [zamijeni_oznake($p['Predlozak.Rata.Naslov'], $z), zamijeni_oznake($p['Predlozak.Rata'], $z)];
}

function poruka_pozivnica(array $c, string $link): string
{
    $p = predlosci_poruka();
    return zamijeni_oznake($p['Predlozak.Pozivnica'], osnovne_oznake($c, $p) + ['{link}' => $link]);
}

function poruka_odobreno(array $c, string $korisnik): string
{
    $p = predlosci_poruka();
    return zamijeni_oznake($p['Predlozak.Odobreno'], osnovne_oznake($c, $p) + ['{link}' => apsolutni_url('prijava'), '{korisnik}' => $korisnik]);
}

/** Broj za WhatsApp/SMS u međunarodnom obliku bez + (HR 09x → 3859x, DE 015x/016x/017x → 4915x…). */
function whatsapp_broj(array $c): ?string
{
    $t = trim((string) ($c['MobilniTelefon'] ?: $c['FiksniTelefon']));
    if ($t === '') {
        return null;
    }
    $z = (string) preg_replace('/\D/', '', $t);
    if (str_starts_with($t, '+')) {
        return $z;
    }
    if (str_starts_with($z, '00')) {
        return substr($z, 2);
    }
    if (str_starts_with($z, '09')) {
        return '385' . substr($z, 1);
    }
    if (preg_match('/^01[567]/', $z) && strlen($z) >= 10) {
        return '49' . substr($z, 1);
    }
    return $z;
}

/**
 * Uređivi tekst poruke + gumbi: e-mail (poslužitelj), e-mail program, WhatsApp, SMS, kopiraj.
 * $povratak = stranica na koju se vraća nakon slanja e-pošte.
 */
function kanali_slanja(array $c, string $naslov, string $tekst, array $povratak, int $redaka = 9, array $pdf = []): string
{
    static $n = 0;
    $n++;
    $id = 'poruka' . $n;
    $wa = whatsapp_broj($c);
    $email = trim((string) ($c['Email'] ?? ''));
    ob_start(); ?>
    <form method="post" action="<?= e(url('posalji-mail')) ?>" class="kanali" data-wa="<?= e((string) $wa) ?>" data-email="<?= e($email) ?>">
        <?= csrf() ?>
        <input type="hidden" name="clan" value="<?= (int) $c['Id'] ?>">
        <input type="hidden" name="povratak" value="<?= e(json_encode($povratak)) ?>">
        <?php foreach ($pdf as $k => $v): ?><input type="hidden" name="pdf[<?= e($k) ?>]" value="<?= e((string) $v) ?>"><?php endforeach; ?>
        <input name="naslov" class="form-control mb-2" value="<?= e($naslov) ?>" aria-label="Naslov">
        <textarea id="<?= $id ?>" name="tekst" class="form-control mb-2" rows="<?= $redaka ?>"><?= e($tekst) ?></textarea>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($email !== '' && posta_dostupna()): ?><button class="btn btn-primary btn-sm">Pošalji e-mail</button><?php endif; ?>
            <?php if ($email !== ''): ?><a class="btn btn-outline-primary btn-sm" data-kanal="mailto" href="#">E-mail program</a><?php endif; ?>
            <?php if ($wa): ?>
                <?php if (kanal_ukljucen('whatsapp')): ?><a class="btn btn-success btn-sm" data-kanal="wa" href="#" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                <?php if (kanal_ukljucen('viber')): ?><a class="btn btn-sm text-white" style="background:#7360f2" data-kanal="viber" href="#" title="Tekst se kopira – u Viberu ga samo zalijepite">Viber</a><?php endif; ?>
                <a class="btn btn-outline-secondary btn-sm" data-kanal="sms" href="#">SMS</a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-kopiraj="#<?= $id ?>">Kopiraj tekst</button>
        </div>
    </form>
    <?php if ($n === 1): ?>
    <script>
    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-kanal]');
        if (!a) return;
        var f = a.closest('form.kanali'), t = f.querySelector('textarea').value, s = f.querySelector('[name=naslov]').value, k = a.getAttribute('data-kanal');
        if (k === 'wa') a.href = 'https://wa.me/' + f.dataset.wa + '?text=' + encodeURIComponent(t);
        if (k === 'viber') { e.preventDefault(); kopirajTekst(t); viberPoruka(f.dataset.wa); return; }
        if (k === 'sms') a.href = 'sms:+' + f.dataset.wa + '?body=' + encodeURIComponent(t);
        if (k === 'mailto') a.href = 'mailto:' + f.dataset.email + '?subject=' + encodeURIComponent(s) + '&body=' + encodeURIComponent(t);
    }, true);
    </script>
    <?php endif;
    return (string) ob_get_clean();
}

/** Je li kanal (whatsapp / viber) uključen u Sustav → Predlošci poruka. */
function kanal_ukljucen(string $kanal): bool
{
    return postavka('Kanal.' . $kanal, '1') === '1';
}

/** Gumbi za dijeljenje teksta bez primatelja (grupa, više ljudi): WhatsApp, Viber, kopiraj. */
function gumbi_dijeljenja(string $tekst, string $vel = 'btn-sm'): string
{
    static $n = 0;
    $id = 'dijeli' . (++$n);
    $o = '<textarea id="' . $id . '" class="d-none">' . e($tekst) . '</textarea>';
    if (kanal_ukljucen('whatsapp')) {
        $o .= '<a class="btn btn-success ' . $vel . '" target="_blank" rel="noopener" href="https://wa.me/?text=' . e(rawurlencode($tekst)) . '">Podijeli na WhatsApp</a>';
    }
    if (kanal_ukljucen('viber')) {
        $o .= '<a class="btn ' . $vel . ' text-white" style="background:#7360f2" href="viber://forward?text=' . e(rawurlencode($tekst)) . '">Podijeli na Viber</a>';
    }
    return $o . '<button type="button" class="btn btn-outline-secondary ' . $vel . '" data-kopiraj="#' . $id . '">Kopiraj tekst</button>';
}

/** Gumbi za kontakt s jednim članom (broj): poziv, WhatsApp, Viber, SMS. */
function gumbi_kontakta(?string $broj, bool $poziv = true): string
{
    if (!$broj) {
        return '';
    }
    $z = whatsapp_broj(['MobilniTelefon' => $broj, 'FiksniTelefon' => null]);
    $o = $poziv ? '<a class="btn btn-sm btn-outline-primary" href="tel:+' . e($z) . '">📞 Nazovi</a>' : '';
    if (kanal_ukljucen('whatsapp')) {
        $o .= '<a class="btn btn-sm btn-success" target="_blank" rel="noopener" href="https://wa.me/' . e($z) . '">WhatsApp</a>';
    }
    if (kanal_ukljucen('viber')) {
        $o .= '<a class="btn btn-sm text-white" style="background:#7360f2" href="viber://chat?number=%2B' . e($z) . '">Viber</a>';
    }
    return $o;
}
