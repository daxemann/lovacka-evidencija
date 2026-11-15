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
        ['sustav/izgled', '🎨', 'Izgled', 'Svijetla ili tamna tema – zadano za sve.', 0, null],
        ['sustav/pocetna', '🏠', 'Početna stranica', 'Što koja uloga vidi na početnoj i kojim redom.', 0, null],
        ['sustav/moduli', '🧩', 'Moduli', "Što udruga koristi – uključeno $ukljuceno od " . count(moduli()) . '.', 0, null],
        ['sustav/sekcije', '🗂️', 'Sekcije', 'Sekcije / lovne jedinice.', 0, null],
        ['sustav/dokumenti', '📜', 'Statut i pravilnici', 'Dokumenti za članove.', 0, 'dokumenti'],
    ]],
    ['Korisnici i prava', [
        ['sustav/korisnici', '🧑‍💻', 'Korisnici', 'Računi, odobravanje, zaključavanje.', $cekaju, null],
        ['sustav/samoprijava', '📝', 'Samoprijava', 'Registracije bez pozivnice.', $samoprijave, null],
        ['sustav/uloge', '🛡️', 'Uloge i prava', 'Što koja uloga smije i za koje sekcije.', 0, null],
        ['sustav/pregled-prava', '🔎', 'Tko ima koja prava', 'Svi članovi s ulogama i pravima – filtar po imenu, ulozi, pravu, sekciji.', 0, null],
    ]],
    ['Komunikacija', [
        ['sustav/postavke', '📧', 'E-pošta i adresa', 'Javna adresa programa, SMTP za e-poštu.', 0, null],
        ['sustav/poruke', '💬', 'Predlošci poruka', 'Tekstovi za WhatsApp / Viber / e-poštu.', 0, null],
        ['sustav/oglasnik', '🛒', 'Oglasnik lovaca', 'Zajednički oglasnik, moderacija.', 0, 'oglasnik'],
    ]],
    ['Postavke modula', [
        ['akcije/vrste', '🏷️', 'Vrste radnih akcija', 'Bodovi i dodatna oprema.', 0, 'akcije'],
        ['sustav/bodovi', '🏅', 'Bodovi – godina i prijenos', 'Dan resetiranja, prijenos u novu godinu, arhiva, bodovi za funkcije.', 0, 'akcije'],
        ['sustav/uplatnica', '🏦', 'Uplatnica (IBAN)', 'Primatelj, model, poziv na broj.', 0, 'clanarina'],
        ['sustav/kalendar', '🗓️', 'Kalendar – vrste', 'Vrste termina i boje.', 0, 'kalendar'],
        ['sustav/skupni-lov', '🎯', 'Skupni lov – PDF', 'Popis sudionika, dodatni zapisnik, broj lova.', 0, 'grupni'],
        ['sustav/odstrel', '🦌', 'Odstrel – evidencija', 'Po sekcijama / zajednički / spojene sekcije, vrste divljači, markice, polja za obrasce, SLE.', 0, 'odstrel'],
        ['revir/lovista', '🌲', 'Lovišta', 'Podaci, granice (KML ili crtanje), putevi i prosjeke.', 0, 'revir'],
        ['revir/postavke', '🗺️', 'Postavke lovišta', 'Vrste naprava, početni prikaz, istek zauzeća.', 0, 'revir'],
        ['popusti', '🏷️', 'Popusti u trgovinama', 'Trgovine s popustom za članove.', 0, 'popusti'],
        ['dezinfekcija/postavke', '🔳', 'Dezinfekcija – stanice i QR', 'Stanice, QR oznake, inspekcija.', 0, 'dezinfekcija'],
    ]],
    ['Podaci', [
        ['sustav/uvoz', '📥', 'Uvoz (Google kontakti)', 'Članovi iz Google CSV-a.', 0, null],
        ['sustav/duplikati', '🔍', 'Provjera duplikata', 'Dvostruko upisani članovi.', 0, null],
        ['sustav/kopije', '💾', 'Sigurnosne kopije', 'Dnevne kopije, kompletna kopija (ZIP).', 0, null],
        smije_azurirati() ? ['sustav/azuriranje', '🔄', 'Ažuriranje programa', $nova ? 'Dostupna nova verzija ' . $nova['verzija'] . '.' : 'Verzija ' . VERZIJA . '.', $nova ? 1 : 0, null] : null,
        ['sustav/dnevnik', '🧾', 'Dnevnik promjena', 'Tko je što i kada promijenio.', 0, null],
        ['sustav/reset', '🧹', 'Reset nakon testiranja', 'Obriši probne upise – članovi i postavke ostaju.', 0, null],
    ]],
];
ob_start(); ?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 me-auto">⚙️ Sustav</h1>
    <input type="search" id="sustav-trazi" class="form-control" style="max-width:320px" placeholder="🔎 Traži postavku (npr. bodovi, logo)…" autocomplete="off">
</div>
<p class="small text-muted" id="sustav-nema" hidden>Ništa u Sustavu. <a href="#" id="sustav-upute">Traži u cijelom programu →</a></p>
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
<script>
(function () {
    var p = document.getElementById('sustav-trazi'), nema = document.getElementById('sustav-nema'), link = document.getElementById('sustav-upute');
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd'); };
    p.addEventListener('input', function () {
        var q = norm(p.value.trim()), ima = 0;
        document.querySelectorAll('.sustav-kartica').forEach(function (a) {
            var da = !q || norm(a.textContent).indexOf(q) >= 0;
            a.parentNode.hidden = !da; if (da) ima++;
        });
        document.querySelectorAll('.row.g-2').forEach(function (r) {
            var h = r.previousElementSibling, vid = Array.prototype.some.call(r.children, function (c) { return !c.hidden; });
            r.hidden = !vid; if (h && h.tagName === 'H2') h.hidden = !vid;
        });
        nema.hidden = !q || ima > 0;
        link.href = <?= json_encode(url('upute')) ?> + '&q=' + encodeURIComponent(p.value.trim());
    });
})();
</script>
<?php stranica('Sustav', ob_get_clean());
