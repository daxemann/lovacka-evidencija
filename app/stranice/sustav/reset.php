<?php
/**
 * 🧹 Reset nakon testiranja: briše upise (dezinfekcija, dnevnik, radne akcije, odstrel …), a članovi, korisnici,
 * uloge, sekcije, postavke i šifrarnici (stanice, naprave, vrste, domovi) ostaju. Prije brisanja uvijek kopija baze.
 */
trazi(P_SUSTAV);
const RESET_SKUPINE = [
    'dezinfekcija' => ['🧴', 'Knjiga dezinfekcije', 'svi dolasci/odlasci, mobilne stanice, slike papirnatih lista', ['DezUpisi', 'DezSkeneri', 'DezAktivacije', 'DezListe'], true,
        'stanice, QR oznake i razlozi ostaju'],
    'loviste' => ['🗺️', 'Lovište – zauzeća i lovački dnevnik', 'zauzimanja čeka, lovački dnevnik, obavijesti lovočuvarima', ['LovackiDnevnik', 'RevirZauzeca', 'RevirObavijesti', 'RevirProcitano'], true,
        'lovne naprave, granice, vrste i idealni vjetar ostaju'],
    'kvarovi' => ['🔧', 'Kvarovi na napravama', 'sve prijave kvarova', ['RevirKvarovi'], true, ''],
    'akcije' => ['🔨', 'Radne akcije i bodovi', 'sve radne akcije, bodovi (i bodovi za pse)', ['PsiBodovi', 'RadneAkcije'], true, 'vrste akcija, bodovi i dodatna oprema u postavkama ostaju'],
    'odstrel' => ['🦌', 'Odstrel i plan odstrela', 'svi upisi odstrela i planovi', ['Odstrel', 'OdstrelPlan'], true, ''],
    'skupni' => ['🎯', 'Skupni lov', 'lovovi, popisi sudionika, dodatni zapisnici (brojevi lova kreću od 0001)', ['GrupniLovZapisnik', 'GrupniLovPopis', 'GrupniLovSudionici', 'GrupniLov'], true, ''],
    'kalendar' => ['📅', 'Kalendar', 'termini, odgovori „Dolazim“ i pozvani gosti', ['DogadjajGosti', 'DogadjajOdgovori', 'DogadjajSekcije', 'Dogadjaji'], true, 'vrste termina ostaju'],
    'domovi' => ['🏡', 'Rezervacije lovačkih domova', 'sve rezervacije i zahtjevi', ['DomRezervacije'], true, 'domovi ostaju'],
    'poruke' => ['✉️', 'Poruke i obavijesti', 'razgovori, poruke, obavijesti udruge i potvrde „pročitao“', ['Poruke2', 'Razgovori2', 'PorukeRazgovora', 'Razgovori', 'SkriveniRazgovori', 'ObavijestPrimatelji', 'Obavijesti'], true, ''],
    'oglasi' => ['📌', 'Interni oglasi', 'oglasi unutar udruge (zajednički Oglasnik lovaca se ne dira)', ['OglasSlike', 'Oglasi'], true, ''],
    'clanarina' => ['💶', 'Članarina i uplate', 'zaduženja, rate i uplate svih članova', ['Uplate', 'ClanarinaRate', 'Clanarine'], false, 'oprez – samo ako ste i članarinu samo isprobavali'],
    'dnevnik' => ['🧾', 'Dnevnik promjena', 'zapis tko je što mijenjao (ostaje samo zapis o ovom resetu)', ['Dnevnik'], false, ''],
];
// pomoćne tablice (oznake pročitanog, veze) – brišu se, ali se ne broje kao upisi
const RESET_POMOCNE = ['RevirProcitano', 'DezSkeneri', 'PsiBodovi', 'GrupniLovSudionici', 'DogadjajSekcije', 'SkriveniRazgovori', 'ObavijestPrimatelji', 'OglasSlike', 'ClanarinaRate'];
if (je_post()) {
    if (je_demo()) {
        poruka('U demo verziji reset nije moguć (sve se ionako vraća svake noći).', 'warning');
        preusmjeri('sustav/reset');
    }
    $odabrano = array_values(array_intersect(array_keys(RESET_SKUPINE), (array) ($_POST['skupine'] ?? [])));
    $k = red('SELECT LozinkaHash FROM Korisnici WHERE Id=?', [(int) korisnik()['Id']]);
    if (!$odabrano) {
        poruka('Ništa nije odabrano.', 'warning');
    } elseif (mb_strtoupper(trim((string) ($_POST['potvrda'] ?? ''))) !== 'RESET') {
        poruka('Za potvrdu upišite riječ RESET.', 'warning');
    } elseif (!$k || !provjeri_lozinku((string) $k['LozinkaHash'], (string) ($_POST['lozinka'] ?? ''))) {
        usleep(500000);
        poruka('Vaša lozinka nije točna.', 'danger');
    } else {
        $kopija = napravi_kopiju('prije-reseta');
        $obrisano = [];
        transakcija(function () use ($odabrano, &$obrisano) {
            foreach ($odabrano as $s) {
                $n = 0;
                foreach (RESET_SKUPINE[$s][3] as $t) {
                    if (!vrijednost("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", [$t])) {
                        continue;
                    }
                    $n += in_array($t, RESET_POMOCNE, true) ? 0 : (int) vrijednost('SELECT COUNT(*) FROM "' . $t . '"');
                    q('DELETE FROM "' . $t . '"');
                    q('DELETE FROM sqlite_sequence WHERE name=?', [$t]); // brojevi kreću ispočetka
                }
                $obrisano[] = RESET_SKUPINE[$s][1] . ' (' . $n . ')';
            }
        });
        dnevnik('Reset nakon testiranja', null, null, implode(', ', $obrisano) . ' · kopija ' . $kopija);
        poruka('<b>Reset gotov.</b> Obrisano: ' . e(implode(', ', $obrisano)) . '.<br>Prije brisanja napravljena je kopija <b>' . e($kopija)
            . '</b> (Sustav → Sigurnosne kopije) – iz nje se sve može vratiti.');
    }
    preusmjeri('sustav/reset');
}
$broj = function (array $tablice): int {
    $n = 0;
    foreach ($tablice as $t) {
        if (!in_array($t, RESET_POMOCNE, true) && vrijednost("SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", [$t])) {
            $n += (int) vrijednost('SELECT COUNT(*) FROM "' . $t . '"');
        }
    }
    return $n;
};
ob_start(); ?>
<h1 class="h3 mb-1">🧹 Reset nakon testiranja</h1>
<p class="text-muted" style="max-width:820px">Kad završite isprobavanje programa, ovdje jednim potezom obrišete probne upise.
    <b>Članovi, korisnici i lozinke, uloge, sekcije, funkcije, psi, vozila, oružni listovi, postavke i šifrarnici</b> (stanice, lovne naprave, vrste akcija, domovi, inventura) <b>ostaju</b>.</p>
<form method="post" class="card border-danger" style="max-width:820px"><?= csrf() ?>
    <div class="card-header bg-danger-subtle fw-semibold">Što obrisati?</div>
    <div class="list-group list-group-flush">
        <?php foreach (RESET_SKUPINE as $kl => [$ik, $naziv, $opis, $tablice, $zadano, $ostaje]): $n = $broj($tablice); ?>
        <label class="list-group-item d-flex gap-3 align-items-start">
            <input class="form-check-input mt-1 flex-shrink-0" type="checkbox" name="skupine[]" value="<?= e($kl) ?>"<?= $zadano ? ' checked' : '' ?>>
            <span class="flex-grow-1"><b><?= $ik ?> <?= e($naziv) ?></b> <span class="badge <?= $n ? 'text-bg-secondary' : 'text-bg-light border' ?>"><?= $n ?></span>
                <span class="d-block small text-muted"><?= e($opis) ?><?= $ostaje ? ' · <i>' . e($ostaje) . '</i>' : '' ?></span></span>
        </label>
        <?php endforeach; ?>
    </div>
    <div class="card-body border-top">
        <div class="alert alert-warning small mb-3">Prije brisanja program sam napravi <b>sigurnosnu kopiju baze</b> – ako nešto zatreba, vraća se u <i>Sustav → Sigurnosne kopije</i>.
            Fotografije (kvarovi, odstrel, papirnate liste) ostaju na poslužitelju zbog te kopije.</div>
        <div class="row g-2">
            <div class="col-sm-6"><label class="form-label small mb-0">Upišite <b>RESET</b></label>
                <input name="potvrda" class="form-control" autocomplete="off" required pattern="[Rr][Ee][Ss][Ee][Tt]"></div>
            <div class="col-sm-6"><label class="form-label small mb-0">Vaša lozinka za prijavu</label>
                <input type="password" name="lozinka" class="form-control" autocomplete="current-password" required></div>
        </div>
    </div>
    <div class="card-footer"><button class="btn btn-danger" data-potvrda="Trajno obrisati odabrane upise? (kopija se radi automatski)">🧹 Obriši odabrane upise</button></div>
</form>
<?php stranica('Reset nakon testiranja', ob_get_clean());
