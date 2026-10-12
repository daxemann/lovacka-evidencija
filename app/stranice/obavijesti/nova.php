<?php
/** Nova obavijest članovima (pravo „Obavijesti – slanje“), po želji s obaveznom potvrdom čitanja. */
trazi(P_OBAVIJESTI);
$k = korisnik();
$clanovi = obav_clanovi_u_opsegu();
$sekcije = moje_sekcije();
$funkcije = redovi('SELECT f.Id, f.Naziv FROM Funkcije f WHERE EXISTS (SELECT 1 FROM ClanFunkcije cf WHERE cf.FunkcijaId=f.Id AND cf.Do IS NULL) ORDER BY f.Redoslijed, f.Naziv');

if (je_post()) {
    $naslov = mb_substr(ul_str('naslov'), 0, 150);
    $tekst = trim(str_replace("\r\n", "\n", (string) ($_POST['tekst'] ?? '')));
    $nacin = (string) ($_POST['nacin'] ?? 'svi');
    [$ids, $opis] = obav_odabir_primatelja($nacin, (array) ($_POST['sekcije'] ?? []), ul_int('funkcija'), (array) ($_POST['odabrani'] ?? []));
    $greska = match (true) {
        $naslov === '' => 'Upišite naslov.',
        $tekst === '' => 'Upišite tekst obavijesti.',
        mb_strlen($tekst) > 10000 => 'Tekst je predug (najviše 10 000 znakova).',
        !$ids => 'Nema primatelja – odaberite sekciju, funkciju ili članove.',
        default => null,
    };
    if ($greska) {
        poruka(e($greska), 'danger');
        $_SESSION['obav_nacrt'] = $_POST;
        preusmjeri('obavijesti/nova');
    }
    unset($_SESSION['obav_nacrt']);
    $nid = obav_posalji($naslov, $tekst, isset($_POST['obavezno']), $ids, $opis);
    poruka(e('Obavijest je poslana: ' . count($ids) . ' primatelja.'));
    preusmjeri('obavijesti', ['id' => $nid]);
}

$n = (array) ($_SESSION['obav_nacrt'] ?? []);
unset($_SESSION['obav_nacrt']);
$nacin = (string) ($n['nacin'] ?? 'svi');
$odabrani = array_map('intval', (array) ($n['odabrani'] ?? []));
$odSek = array_map('intval', (array) ($n['sekcije'] ?? []));
ob_start(); ?>
<p class="mb-2"><a href="<?= e(url('obavijesti')) ?>">← Obavijesti</a></p>
<h1 class="h3 mb-1">📢 Nova obavijest</h1>
<p class="text-muted" style="max-width:760px">Obavijest se pojavljuje članovima u aplikaciji pod <b>Obavijesti</b> i u <b>Porukama</b>. S obaveznom potvrdom svakome se pri sljedećem otvaranju aplikacije prikaže preko cijelog zaslona – dok jednom ne dodirne „Pročitao/la sam“.</p>
<form method="post" action="<?= e(url('obavijesti/nova')) ?>" style="max-width:760px" id="obav-obrazac"><?= csrf() ?>
    <div class="mb-3"><label class="form-label" for="naslov">Naslov</label>
        <input name="naslov" id="naslov" class="form-control" maxlength="150" required value="<?= e($n['naslov'] ?? '') ?>" placeholder="npr. ASF – nove mjere dezinfekcije od 15.10."></div>
    <div class="mb-3"><label class="form-label" for="tekst">Tekst</label>
        <textarea name="tekst" id="tekst" class="form-control" rows="9" required maxlength="10000"><?= e($n['tekst'] ?? '') ?></textarea></div>
    <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" name="obavezno" id="obavezno" value="1"<?= chk(!$n || isset($n['obavezno'])) ?>>
        <label class="form-check-label" for="obavezno"><b>Obavezna potvrda „Pročitao/la sam“</b> <span class="text-muted small">– bez nje se obavijest samo označi kao pročitana kad je član otvori</span></label>
    </div>

    <h2 class="h6">Primatelji</h2>
    <div class="d-flex flex-wrap gap-3 mb-2">
        <?php foreach (['svi' => $k['SveSekcije'] ? 'Svi članovi' : 'Svi članovi mojih sekcija', 'sekcije' => 'Sekcije', 'funkcija' => 'Funkcija', 'odabrani' => 'Odabrani članovi'] as $v => $t):
            if ($v === 'funkcija' && !$funkcije) continue; ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="nacin" id="n-<?= $v ?>" value="<?= $v ?>"<?= chk($nacin === $v) ?>>
                <label class="form-check-label" for="n-<?= $v ?>"><?= e($t) ?></label></div>
        <?php endforeach; ?>
    </div>
    <div class="card mb-3" data-nacin="sekcije" hidden><div class="card-body py-2 d-flex flex-wrap gap-3">
        <?php foreach ($sekcije as $s): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="sekcije[]" value="<?= (int) $s['Id'] ?>" id="s<?= (int) $s['Id'] ?>"<?= chk(in_array((int) $s['Id'], $odSek, true)) ?>>
                <label class="form-check-label" for="s<?= (int) $s['Id'] ?>"><?= e($s['Naziv']) ?></label></div>
        <?php endforeach; ?>
        <?php if (!$sekcije): ?><span class="text-muted small">Nema sekcija u vašem opsegu.</span><?php endif; ?>
    </div></div>
    <div class="mb-3" data-nacin="funkcija" hidden><select name="funkcija" class="form-select" style="max-width:360px">
        <?php foreach ($funkcije as $f): ?><option value="<?= (int) $f['Id'] ?>"<?= sel((int) ($n['funkcija'] ?? 0), (int) $f['Id']) ?>><?= e($f['Naziv']) ?></option><?php endforeach; ?>
    </select><div class="form-text">Samo članovi s tom funkcijom u vašim sekcijama.</div></div>
    <div class="card mb-3" data-nacin="odabrani" hidden><div class="card-body py-2">
        <input type="search" class="form-control form-control-sm mb-2" placeholder="Traži ime…" data-trazi-clana>
        <div style="max-height:320px;overflow:auto">
        <?php foreach ($clanovi as $c): ?>
            <div class="form-check" data-ime="<?= e(kljuc($c['Prezime'] . ' ' . $c['Ime'])) ?>">
                <input class="form-check-input" type="checkbox" name="odabrani[]" value="<?= (int) $c['Id'] ?>" id="c<?= (int) $c['Id'] ?>"<?= chk(in_array((int) $c['Id'], $odabrani, true)) ?>>
                <label class="form-check-label" for="c<?= (int) $c['Id'] ?>"><?= e($c['Prezime'] . ' ' . $c['Ime']) ?> <span class="small text-muted"><?= e($c['Sekcija']) ?></span>
                    <?php if (!$c['ImaPristup']): ?><span class="badge bg-light text-muted border">bez aplikacije</span><?php endif; ?></label>
            </div>
        <?php endforeach; ?>
        </div>
    </div></div>
    <p class="small text-muted"><?= count($clanovi) ?> aktivnih članova u vašem opsegu, od toga <?= count(array_filter($clanovi, fn($c) => !$c['ImaPristup'])) ?> bez pristupa aplikaciji (njih kasnije možete označiti kao „usmeno obaviještene“).</p>
    <button class="btn btn-primary" data-potvrda="Poslati obavijest odabranim članovima?">Pošalji obavijest</button>
</form>
<script>
(function () {
    var f = document.getElementById('obav-obrazac');
    var prikazi = function () {
        var v = (f.querySelector('[name=nacin]:checked') || {}).value;
        f.querySelectorAll('[data-nacin]').forEach(function (d) { d.hidden = d.getAttribute('data-nacin') !== v; });
    };
    f.addEventListener('change', function (e) { if (e.target.name === 'nacin') prikazi(); });
    prikazi();
    var t = f.querySelector('[data-trazi-clana]');
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); };
    if (t) t.addEventListener('input', function () {
        var q = norm(t.value.trim());
        f.querySelectorAll('[data-ime]').forEach(function (d) { d.hidden = q !== '' && d.getAttribute('data-ime').indexOf(q) < 0; });
    });
})();
</script>
<?php stranica('Nova obavijest', ob_get_clean());
