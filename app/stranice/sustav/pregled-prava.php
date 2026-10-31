<?php
/** 🔎 Sustav → Tko ima koja prava: svi računi s ulogama i stvarnim pravima, filtar po imenu, ulozi, pravu i sekciji. */
trazi(P_SUSTAV);
$grupe = prava_grupe();
$svaPrava = array_merge(...array_values($grupe));
$uloge = redovi('SELECT Id, Naziv, Prava, Sustavna FROM Uloge ORDER BY Sustavna DESC, Naziv');
$sekcije = redovi('SELECT Id, Naziv FROM Sekcije ORDER BY Naziv');
$racuni = redovi('SELECT k.Id, k.KorisnickoIme, k.PrikaznoIme, k.Aktivan, k.Odobren, k.ClanId, c.Ime, c.Prezime, c.Status AS ClanStatus, s.Naziv AS Sekcija
                  FROM Korisnici k LEFT JOIN Clanovi c ON c.Id=k.ClanId LEFT JOIN Sekcije s ON s.Id=c.SekcijaId
                  ORDER BY c.Prezime IS NULL, c.Prezime, c.Ime, k.KorisnickoIme');
// dodjele: korisnik → [[UlogaId, Naziv, Prava, Sustavna, SekcijaId|null, Sekcija|null]]
$dodjele = [];
foreach (redovi('SELECT ku.KorisnikId, ku.SekcijaId, u.Id AS UlogaId, u.Naziv, u.Prava, u.Sustavna, s.Naziv AS Sekcija
                 FROM KorisnikUloge ku JOIN Uloge u ON u.Id=ku.UlogaId LEFT JOIN Sekcije s ON s.Id=ku.SekcijaId ORDER BY u.Sustavna DESC, u.Naziv') as $d) {
    $dodjele[(int) $d['KorisnikId']][] = $d;
}
// po korisniku: pravo → opseg (null = sve sekcije, inače [naziv sekcije …])
$opseg = function (array $dd) use ($svaPrava): array {
    $o = [];
    foreach ($dd as $d) {
        $prava = $d['Sustavna'] ? P_SVE : (int) $d['Prava'];
        foreach ($svaPrava as $p) {
            if (($prava & $p) !== $p) {
                continue;
            }
            if ($d['SekcijaId'] === null) {
                $o[$p] = null;
            } elseif (!array_key_exists($p, $o) || $o[$p] !== null) {
                $o[$p][] = (string) $d['Sekcija'];
            }
        }
    }
    return $o;
};
// „Dezinfekcija – pregled“ → ['Dezinfekcija', 'pregled']
$dijelovi = fn(int $p) => array_pad(explode(' – ', PRAVA_OPIS[$p][0], 2), 2, '');
$opsegTekst = fn(?array $o) => $o === null ? '' : implode(', ', array_unique($o));
$saUlogom = count(array_filter($racuni, fn($r) => !empty($dodjele[(int) $r['Id']])));
ob_start(); ?>
<h1 class="h3 mb-1">🔎 Tko ima koja prava</h1>
<p class="text-muted small mb-3">Svi računi s ulogama i pravima koja iz njih proizlaze – bez otvaranja svakog člana. Uloge se mijenjaju na kartici člana (klik na ime),
    prava uloga u <a href="<?= e(url('sustav/uloge')) ?>">Uloge i prava</a>. Sekcija za koju pravo vrijedi stoji kao „vrijedi za …“ ili u zagradi – bez toga vrijedi za sve sekcije.</p>

<div class="card mb-3"><div class="card-body py-2">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label small mb-0">Ime</label>
            <input type="search" id="fIme" class="form-control form-control-sm" placeholder="ime, prezime ili korisničko ime"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Uloga</label>
            <select id="fUloga" class="form-select form-select-sm"><option value="">sve</option>
                <?php foreach ($uloge as $u): ?><option value="<?= (int) $u['Id'] ?>"><?= e($u['Naziv']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Pravo</label>
            <select id="fPravo" class="form-select form-select-sm"><option value="">sva</option>
                <?php foreach ($grupe as $g => $pp): ?><optgroup label="<?= e($g) ?>">
                    <?php foreach ($pp as $p): ?><option value="<?= $p ?>"><?= e(PRAVA_OPIS[$p][0]) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
        <?php if ($sekcije): ?>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">Sekcija (prava za)</label>
            <select id="fSekcija" class="form-select form-select-sm"><option value="">sve</option>
                <?php foreach ($sekcije as $s): ?><option value="<?= e($s['Naziv']) ?>"><?= e($s['Naziv']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-6 col-md-2"><div class="form-check form-switch small mb-1"><input class="form-check-input" type="checkbox" id="fBez">
            <label class="form-check-label" for="fBez">i bez uloge</label></div></div>
    </div>
</div></div>
<div class="small text-muted mb-2">Prikazano: <b id="brPrikaz">0</b> od <?= count($racuni) ?> računa (s ulogom: <?= $saUlogom ?>)
    <button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline" id="fOcisti">poništi filtar</button></div>

<div class="table-responsive"><table class="table table-sm table-hover align-middle tablica-prava" id="tPrava">
    <thead><tr><th style="min-width:160px">Član</th><th style="min-width:180px">Uloge</th><th>Prava</th></tr></thead>
    <tbody>
    <?php foreach ($racuni as $r):
        $id = (int) $r['Id'];
        $dd = $dodjele[$id] ?? [];
        $o = $opseg($dd);
        $ime = $r['ClanId'] ? trim($r['Prezime'] . ' ' . $r['Ime']) : ((string) $r['PrikaznoIme'] ?: $r['KorisnickoIme']);
        $href = $r['ClanId'] ? url('clanovi/uredi', ['id' => $r['ClanId'], 'kartica' => 'racun']) : '';
        $sveSek = array_unique(array_merge(...array_map(fn($v) => $v ?? ['*'], array_values($o) ?: [[]])));
        $admin = (bool) array_filter($dd, fn($d) => $d['Sustavna']);
        $prava = array_sum(array_keys($o)); ?>
        <tr data-ime="<?= e(mb_strtolower($ime . ' ' . $r['KorisnickoIme'])) ?>" data-uloge=" <?= e(implode(' ', array_column($dd, 'UlogaId'))) ?> "
            data-prava="<?= $prava ?>" data-sek="<?= e('|' . implode('|', $sveSek) . '|') ?>" data-bez="<?= $dd ? 0 : 1 ?>"<?= $dd ? '' : ' hidden' ?>>
            <td><?= $href ? '<a href="' . e($href) . '" class="fw-semibold text-decoration-none">' . e($ime) . '</a>' : '<span class="fw-semibold">' . e($ime) . '</span>' ?>
                <div class="small text-muted"><?= e($r['Sekcija'] ?? '') ?>
                    <?php if ($r['ClanStatus'] !== null && (int) $r['ClanStatus'] === CLAN_ARHIVIRAN): ?><span class="badge bg-secondary">arhiviran</span>
                    <?php elseif (!$r['Odobren']): ?><span class="badge bg-warning text-dark">čeka odobrenje</span>
                    <?php elseif (!$r['Aktivan']): ?><span class="badge bg-secondary">zaključan</span><?php endif; ?></div></td>
            <td><?php foreach ($dd as $d): ?><span class="badge bg-success me-1 mb-1"><?= e($d['Naziv']) ?><?= $d['Sekcija'] ? ' · ' . e($d['Sekcija']) : '' ?></span><?php endforeach; ?>
                <?= $dd ? '' : '<span class="small text-muted">— obični član</span>' ?></td>
            <td class="small">
                <?php if ($admin): ?><span class="badge bg-danger">sva prava</span>
                <?php elseif ($o):
                    $opsezi = array_unique(array_map($opsegTekst, $o));
                    $jedan = count($opsezi) === 1; // sva prava za isti opseg → napisati jednom
                    if ($jedan && reset($opsezi) !== ''): ?><div class="mb-1"><span class="badge text-bg-light border">vrijedi za: <?= e(reset($opsezi)) ?></span></div><?php endif;
                    foreach ($grupe as $g => $pp): $ima = array_values(array_filter($pp, fn($p) => array_key_exists($p, $o))); if (!$ima) continue;
                        $po = []; // predmet → [pravo, …]
                        foreach ($ima as $p) { $po[$dijelovi($p)[0]][] = $p; } ?>
                    <div class="pravo-red"><span class="me-1"><?= e(mb_substr($g, 0, 2)) ?></span><?php $n = 0; foreach ($po as $predmet => $lista): ?><?= $n++ ? ' · ' : '' ?><b><?= e($predmet) ?></b>
                        <?php foreach ($lista as $i => $p): $dio = $dijelovi($p)[1]; ?><span class="pravo-chip" data-p="<?= $p ?>" title="<?= e(PRAVA_OPIS[$p][0] . ': ' . PRAVA_OPIS[$p][1]) ?>"><?= e($dio !== '' ? $dio : '✓') ?><?= !$jedan && $o[$p] !== null ? ' <span class="text-muted">(' . e($opsegTekst($o[$p])) . ')</span>' : '' ?></span><?= $i < count($lista) - 1 ? ', ' : '' ?><?php endforeach; ?>
                    <?php endforeach; ?></div>
                <?php endforeach; else: ?><span class="text-muted">samo svoji podaci</span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<style>@media (max-width: 767.98px) { .tablica-prava thead { display: none; } .tablica-prava tr { display: block; border-bottom: 1px solid var(--bs-border-color); padding: .4rem 0; } .tablica-prava td { display: block; border: 0; padding: .1rem 0; } }
.pravo-red { line-height: 1.5; } .pravo-chip.istaknuto { background: var(--bs-warning-bg-subtle); border-radius: .25rem; padding: 0 .2rem; font-weight: 600; }</style>
<script>
(function () {
    var t = document.getElementById('tPrava'), ime = document.getElementById('fIme'), ul = document.getElementById('fUloga'), pr = document.getElementById('fPravo'),
        sek = document.getElementById('fSekcija'), bez = document.getElementById('fBez'), br = document.getElementById('brPrikaz');
    function filtriraj() {
        var q = ime.value.trim().toLowerCase(), u = ul.value, p = +pr.value || 0, s = sek ? sek.value : '', n = 0;
        t.querySelectorAll('tbody tr').forEach(function (tr) {
            var d = tr.dataset, ok = true;
            if (d.bez === '1' && !bez.checked && !q) ok = false;
            if (q && d.ime.indexOf(q) < 0) ok = false;
            if (u && d.uloge.indexOf(' ' + u + ' ') < 0) ok = false;
            if (p && (+d.prava & p) !== p) ok = false;
            if (s && d.sek.indexOf('|' + s + '|') < 0 && d.sek.indexOf('|*|') < 0) ok = false;
            tr.hidden = !ok; if (ok) n++;
            tr.querySelectorAll('.pravo-chip').forEach(function (c) { c.classList.toggle('istaknuto', !!p && +c.dataset.p === p); });
        });
        br.textContent = n;
    }
    [ime, ul, pr, sek, bez].forEach(function (el) { if (el) el.addEventListener(el.tagName === 'INPUT' && el.type !== 'checkbox' ? 'input' : 'change', filtriraj); });
    document.getElementById('fOcisti').addEventListener('click', function () { ime.value = ''; ul.value = ''; pr.value = ''; if (sek) sek.value = ''; bez.checked = false; filtriraj(); });
    filtriraj();
})();
</script>
<?php stranica('Tko ima koja prava', ob_get_clean());
