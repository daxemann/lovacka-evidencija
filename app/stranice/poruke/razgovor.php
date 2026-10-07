<?php
/** Razgovor kupca i prodavatelja; nove poruke se dohvaćaju svakih nekoliko sekundi. */
$k = korisnik();
$id = (int) ul('id', 0);
$r = razgovor_za($id, $k['Id']);
if (!$r) {
    stranica('Razgovor', '<a href="' . e(url('poruke')) . '" class="small">← Sve poruke</a><p class="text-muted mt-2">Razgovor ne postoji.</p>');
}
if (je_post()) {
    posalji_poruku($id, $k['Id'], (string) ($_POST['tekst'] ?? ''));
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        json(['ok' => true]);
    }
    preusmjeri('poruke/razgovor', ['id' => $id]);
}
oznaci_procitano($id, $k['Id']);
$jaProdajem = (int) $r['ProdavateljId'] === $k['Id'];
$drugi = ime_korisnika($jaProdajem ? (int) $r['KupacId'] : (int) $r['ProdavateljId']);
$slika = slike_oglasa((int) $r['OglasId'])[0] ?? null;
$poruke = redovi('SELECT * FROM PorukeRazgovora WHERE RazgovorId=? ORDER BY Id', [$id]);
$oblak = fn($p) => '<div class="chat-red ' . ((int) $p['PosiljateljId'] === $k['Id'] ? 'moja' : '') . '" data-id="' . (int) $p['Id'] . '"><div class="chat-oblak"><div style="white-space:pre-wrap">'
    . e($p['Tekst']) . '</div><div class="chat-vrijeme">' . e(date('d.m. H:i', strtotime($p['Vrijeme']))) . ((int) $p['PosiljateljId'] === $k['Id'] && $p['Procitano'] ? ' ✓✓' : '') . '</div></div></div>';
if (isset($_GET['nakon'])) { // AJAX: nove poruke
    session_write_close();
    $nove = redovi('SELECT * FROM PorukeRazgovora WHERE RazgovorId=? AND Id>? ORDER BY Id', [$id, (int) $_GET['nakon']]);
    json(['html' => implode('', array_map($oblak, $nove)), 'zadnji' => $nove ? (int) end($nove)['Id'] : (int) $_GET['nakon'],
          'procitano' => array_map('intval', array_column(redovi('SELECT Id FROM PorukeRazgovora WHERE RazgovorId=? AND PosiljateljId=? AND Procitano=1', [$id, $k['Id']]), 'Id'))]);
}
ob_start(); ?>
<a href="<?= e(url('poruke')) ?>" class="small">← Sve poruke</a>
<div class="d-flex align-items-center gap-3 my-2">
    <div class="oglas-mini-slika"><?php if ($slika): ?><img src="<?= e(foto_url($slika)) ?>" alt=""><?php endif; ?></div>
    <div class="overflow-hidden">
        <a href="<?= e(url('oglasnik/oglas', ['id' => $r['OglasId']])) ?>" class="fw-semibold text-truncate d-block"><?= e($r['Naslov']) ?></a>
        <div class="small text-muted"><?= e(cijena_oglasa($r)) ?> · s: <?= e($drugi) ?></div>
    </div>
</div>
<div class="chat" id="chat">
    <?= implode('', array_map($oblak, $poruke)) ?>
    <?php if (!$poruke): ?><div class="text-muted small text-center py-3">Nema poruka.</div><?php endif; ?>
</div>
<?php if ((int) $r['OglasStatus'] === 2): ?><div class="small text-muted mt-2">Oglas je označen kao prodan – i dalje možete razmijeniti poruke.</div><?php endif; ?>
<form method="post" action="<?= e(url('poruke/razgovor', ['id' => $id])) ?>" class="d-flex gap-2 mt-2" id="slanje"><?= csrf() ?>
    <textarea name="tekst" class="form-control" rows="1" maxlength="2000" required placeholder="Poruka…" style="resize:vertical"></textarea>
    <button class="btn btn-primary">Pošalji</button>
</form>
<script>
(function () {
    var chat = document.getElementById('chat'), f = document.getElementById('slanje'), t = f.querySelector('textarea');
    var zadnji = <?= $poruke ? (int) end($poruke)['Id'] : 0 ?>, adresa = <?= json_encode(url('poruke/razgovor', ['id' => $id])) ?>;
    chat.scrollTop = chat.scrollHeight;
    var dohvati = function () {
        fetch(adresa + '&nakon=' + zadnji, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
            if (!j) return;
            if (j.html) { var dno = chat.scrollTop + chat.clientHeight >= chat.scrollHeight - 30; chat.insertAdjacentHTML('beforeend', j.html); zadnji = j.zadnji; if (dno) chat.scrollTop = chat.scrollHeight; }
            (j.procitano || []).forEach(function (pid) { var v = chat.querySelector('.chat-red.moja[data-id="' + pid + '"] .chat-vrijeme'); if (v && v.textContent.indexOf('✓✓') < 0) v.textContent += ' ✓✓'; });
        }).catch(function () {});
    };
    setInterval(function () { if (!document.hidden) dohvati(); }, 4000);
    f.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!t.value.trim()) return;
        var d = new FormData(f);
        fetch(adresa, { method: 'POST', body: d, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function () { t.value = ''; dohvati(); });
    });
    t.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 640) { e.preventDefault(); f.requestSubmit(); } });
})();
</script>
<?php stranica('Razgovor', ob_get_clean());
