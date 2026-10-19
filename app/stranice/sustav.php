<?php
/** ⚙️ Sustav – pregled svih postavki u karticama (umjesto duge liste u izborniku). */
trazi(P_SUSTAV);
$cekaju = (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND Aktivan=1');
$samoprijave = (int) vrijednost('SELECT COUNT(*) FROM Korisnici WHERE Odobren=0 AND ClanId IS NULL');
$nova = azuriranje_dostupno();
$ukljuceno = count(array_filter(array_keys(moduli()), 'modul'));

// [naslov, [[stranica, ikona, naziv, opis, broj, modul|null]]]
$skupine = [
    ['Udruga', [
        ['sustav/udruga', '🏛️', 'Podaci o udruzi', 'Naziv, logo, adresa, OIB, podnaslov.', 0, null],
        ['sustav/moduli', '🧩', 'Moduli', "Što udruga koristi – uključeno $ukljuceno od " . count(moduli()) . '.', 0, null],
        ['sustav/sekcije', '🗂️', 'Sekcije', 'Sekcije / lovne jedinice.', 0, null],
        ['sustav/dokumenti', '📜', 'Statut i pravilnici', 'Dokumenti za članove.', 0, 'dokumenti'],
    ]],
    ['Korisnici i prava', [
        ['sustav/korisnici', '🧑‍💻', 'Korisnici', 'Računi, odobravanje, zaključavanje.', $cekaju, null],
        ['sustav/samoprijava', '📝', 'Samoprijava', 'Registracije bez pozivnice.', $samoprijave, null],
        ['sustav/uloge', '🛡️', 'Uloge i prava', 'Što koja uloga smije i za koje sekcije.', 0, null],
    ]],
    ['Komunikacija', [
        ['sustav/postavke', '📧', 'E-pošta i adresa', 'Javna adresa programa, SMTP za e-poštu.', 0, null],
        ['sustav/poruke', '💬', 'Predlošci poruka', 'Tekstovi za WhatsApp / Viber / e-poštu.', 0, null],
        ['sustav/oglasnik', '🛒', 'Oglasnik lovaca', 'Zajednički oglasnik, moderacija.', 0, 'oglasnik'],
    ]],
    ['Postavke modula', [
        ['akcije/vrste', '🏷️', 'Vrste radnih akcija', 'Bodovi i dodatna oprema.', 0, 'akcije'],
        ['sustav/uplatnica', '🏦', 'Uplatnica (IBAN)', 'Primatelj, model, poziv na broj.', 0, 'clanarina'],
        ['sustav/kalendar', '🗓️', 'Kalendar – vrste', 'Vrste termina i boje.', 0, 'kalendar'],
        ['revir/postavke', '🗺️', 'Postavke lovišta', 'Granice lovišta, vrste naprava.', 0, 'revir'],
        ['dezinfekcija/postavke', '🔳', 'Dezinfekcija – stanice i QR', 'Stanice, QR oznake, inspekcija.', 0, 'dezinfekcija'],
    ]],
    ['Podaci', [
        ['sustav/uvoz', '📥', 'Uvoz (Google kontakti)', 'Članovi iz Google CSV-a.', 0, null],
        ['sustav/duplikati', '🔍', 'Provjera duplikata', 'Dvostruko upisani članovi.', 0, null],
        ['sustav/kopije', '💾', 'Sigurnosne kopije', 'Dnevne kopije, kompletna kopija (ZIP).', 0, null],
        smije_azurirati() ? ['sustav/azuriranje', '🔄', 'Ažuriranje programa', $nova ? 'Dostupna nova verzija ' . $nova['verzija'] . '.' : 'Verzija ' . VERZIJA . '.', $nova ? 1 : 0, null] : null,
        ['sustav/dnevnik', '🧾', 'Dnevnik promjena', 'Tko je što i kada promijenio.', 0, null],
    ]],
];
ob_start(); ?>
<h1 class="h3 mb-3">⚙️ Sustav</h1>
<?php foreach ($skupine as [$naslov, $kartice]):
    $kartice = array_filter($kartice, fn($x) => $x && ($x[5] === null || modul($x[5])));
    if (!$kartice) {
        continue;
    } ?>
    <h2 class="h6 text-muted text-uppercase mt-4 mb-2"><?= e($naslov) ?></h2>
    <div class="row g-2 g-md-3">
        <?php foreach ($kartice as [$str, $ik, $naz, $opis, $broj]): ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a href="<?= e(url($str)) ?>" class="card sustav-kartica h-100 text-decoration-none">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start gap-2">
                        <span class="sustav-ikona" aria-hidden="true"><?= $ik ?></span>
                        <?php if ($broj > 0): ?><span class="badge rounded-pill bg-warning text-dark ms-auto"><?= (int) $broj ?></span><?php endif; ?>
                    </div>
                    <div class="fw-semibold mt-2 text-body"><?= e($naz) ?></div>
                    <div class="small text-muted d-none d-sm-block"><?= e($opis) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php stranica('Sustav', ob_get_clean());
