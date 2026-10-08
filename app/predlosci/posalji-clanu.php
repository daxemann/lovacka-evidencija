<?php
/** Kartica "Pošalji popis bodova" / "Info o članarini". Varijable: $id, $clan, $posalji, $kartica */
$zatvori = url('clanovi/uredi', ['id' => $id, 'kartica' => $kartica]);
?>
<div class="card border-success mb-3 no-print"><div class="card-body">
    <div class="d-flex align-items-center mb-2">
        <h2 class="h6 mb-0"><?= ['bodovi' => 'Pošalji popis bodova', 'clanarina' => 'Prijateljska info o članarini', 'rata' => 'Podsjetnik – dospjela rata'][$posalji] ?> <span class="text-muted">→ <?= e(puno_ime($clan)) ?></span></h2>
        <a href="<?= e($zatvori) ?>" class="btn-close ms-auto" aria-label="Zatvori"></a>
    </div>
<?php if ($posalji === 'bodovi'):
    if (!ima(P_AKCIJE_CITAJ)) {
        zabranjeno();
    }
    $f = filtar_iz_upita($_GET + ['Razdoblje' => 'LovnaGodina']);
    $iNaCekanju = ($_GET['cekanje'] ?? '1') === '1';
    if (!$iNaCekanju) {
        $f['Status'] = AKCIJA_ODOBRENO;
    }
    [$nasl, $tekst] = poruka_bodovi($clan, $f); ?>
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="p" value="clanovi/uredi"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="posalji" value="bodovi"><input type="hidden" name="kartica" value="<?= e($kartica) ?>">
        <div class="col-sm-4"><label class="form-label small">Razdoblje</label>
            <select name="Razdoblje" class="form-select form-select-sm" data-auto>
                <?php foreach (RAZDOBLJA as $kl => $t): ?><option value="<?= $kl ?>"<?= sel($kl, $f['Razdoblje']) ?>><?= e($t) ?></option><?php endforeach; ?>
            </select></div>
        <?php if ($f['Razdoblje'] === 'Mjeseci'): ?>
            <div class="col-sm-3"><label class="form-label small">Od mjeseca</label><input type="month" name="Od" class="form-control form-control-sm" value="<?= e(substr($f['Od'] ?? date('Y-m'), 0, 7)) ?>" data-auto></div>
            <div class="col-sm-3"><label class="form-label small">Do mjeseca</label><input type="month" name="Do" class="form-control form-control-sm" value="<?= e(substr($f['Do'] ?? date('Y-m'), 0, 7)) ?>" data-auto></div>
        <?php elseif ($f['Razdoblje'] === 'Slobodno'): ?>
            <div class="col-sm-3"><label class="form-label small">Od</label><input type="date" name="Od" class="form-control form-control-sm" value="<?= e($f['Od']) ?>" data-auto></div>
            <div class="col-sm-3"><label class="form-label small">Do</label><input type="date" name="Do" class="form-control form-control-sm" value="<?= e($f['Do']) ?>" data-auto></div>
        <?php endif; ?>
        <div class="col-sm-2"><div class="form-check small"><input type="hidden" name="cekanje" value="0">
            <input type="checkbox" class="form-check-input" id="cek" name="cekanje" value="1"<?= chk($iNaCekanju) ?> data-auto><label class="form-check-label" for="cek">Odobrene i na čekanju</label></div></div>
    </form>
    <?= kanali_slanja($clan, $nasl, $tekst, ['clanovi/uredi', ['id' => $id, 'kartica' => $kartica]], 11, filtar_upit($f)) ?>
    <a class="btn btn-sm btn-outline-secondary mt-2" target="_blank" href="<?= e(url('izvoz/akcije-pdf', filtar_upit(array_merge($f, ['ClanId' => $id])) + ['prikaz' => 1])) ?>">PDF popis</a>
<?php else:
    if (!ima(P_CLANARINA_CITAJ)) {
        zabranjeno();
    }
    $godina = (int) ($_GET['godina'] ?? date('Y'));
    $godine = array_map('intval', array_column(redovi('SELECT DISTINCT Godina FROM Clanarine WHERE ClanId=? ORDER BY Godina DESC', [$id]), 'Godina')) ?: [(int) date('Y')]; ?>
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="p" value="clanovi/uredi"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="posalji" value="<?= e($posalji) ?>"><input type="hidden" name="kartica" value="<?= e($kartica) ?>">
        <div class="col-sm-3"><label class="form-label small">Godina</label>
            <select name="godina" class="form-select form-select-sm" data-auto><?php foreach ($godine as $g): ?><option<?= sel($g, $godina) ?>><?= $g ?></option><?php endforeach; ?></select></div>
    </form>
    <?php if (isset(oslobodjeni_clanarine($godina)[$id])): ?>
        <div class="alert alert-info mb-0">Počasni član – oslobođen članarine za <?= $godina ?>. Info se ne šalje.</div>
    <?php elseif (!($p = $posalji === 'rata' ? poruka_rata($clan, $godina) : poruka_clanarina($clan, $godina))): ?>
        <div class="alert alert-secondary mb-0">Za <?= $godina ?>. nema evidentirane članarine za ovog člana.</div>
    <?php else: ?>
        <?= kanali_slanja($clan, $p[0], $p[1], ['clanovi/uredi', ['id' => $id, 'kartica' => $kartica]], 11) ?>
    <?php endif; ?>
<?php endif; ?>
<?php if (!$clan['Email'] && !whatsapp_broj($clan)): ?>
    <p class="small text-danger mt-2 mb-0">Član nema ni e-mail ni broj telefona – tekst kopirajte i pošaljite drugim putem.</p>
<?php elseif (!$clan['Email']): ?>
    <p class="small text-muted mt-2 mb-0">Član nema e-mail adresu – pošaljite putem WhatsAppa, Vibera ili SMS-a.</p>
<?php elseif (!posta_dostupna()): ?>
    <p class="small text-muted mt-2 mb-0">Izravno slanje e-pošte nije podešeno – „E-mail program“ otvara vaš Outlook/Gmail s gotovim tekstom.</p>
<?php endif; ?>
</div></div>
