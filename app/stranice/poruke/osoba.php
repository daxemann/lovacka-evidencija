<?php
/** Izravni razgovor s članom (?k=<korisnikId> otvara ili stvara razgovor, ?r=<razgovorId>). */
$k = korisnik();
$r = null;
if ($rid = ul_int('r')) {
    $r = izravni_razgovor_za($rid, $k['Id']);
} elseif ($drugi = ul_int('k')) {
    if ($drugi !== $k['Id'] && vrijednost('SELECT 1 FROM Korisnici WHERE Id=? AND Aktivan=1 AND Odobren=1', [$drugi])) {
        $postoji = izravni_razgovor($k['Id'], $drugi, false);
        if ($postoji) {
            preusmjeri('poruke/osoba', ['r' => $postoji]);
        }
        if (je_post()) {
            $nid = izravni_razgovor($k['Id'], $drugi);
            posalji_izravnu_poruku($nid, $k['Id'], (string) ($_POST['tekst'] ?? ''));
            preusmjeri('poruke/osoba', ['r' => $nid]);
        }
        $r = ['Id' => 0, 'Drugi' => $drugi];
    }
}
if (!$r) {
    stranica('Razgovor', '<a href="' . e(url('poruke')) . '" class="small">← Sve poruke</a><p class="text-muted mt-2">Razgovor ne postoji.</p>');
}
$id = (int) $r['Id'];
if ($id && je_post() && ($_POST['radnja'] ?? '') === 'obrisi-poruku') {
    obrisi_svoju_poruku('c', $id, (int) ($_POST['pid'] ?? 0), $k['Id']);
    preusmjeri('poruke/osoba', ['r' => $id]);
}
if ($id && je_post() && ($_POST['radnja'] ?? '') === 'obrisi-razgovor') {
    obrisi_razgovor_za_mene('c', $id, $k['Id'], [(int) $r['KorisnikA'], (int) $r['KorisnikB']]);
    poruka('Razgovor je obrisan (kod vas).');
    preusmjeri('poruke');
}
if ($id && je_post()) {
    posalji_izravnu_poruku($id, $k['Id'], (string) ($_POST['tekst'] ?? ''));
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        json(['ok' => true]);
    }
    preusmjeri('poruke/osoba', ['r' => $id]);
}
if ($id) {
    q('UPDATE Poruke2 SET Procitano=1 WHERE RazgovorId=? AND PosiljateljId<>? AND Procitano=0', [$id, $k['Id']]);
}
$skr = $id ? skriveno_do('c', $id, $k['Id']) : 0;
$poruke = $id ? redovi('SELECT * FROM Poruke2 WHERE RazgovorId=? AND Id>? ORDER BY Id', [$id, $skr]) : [];
$oblak = fn($p) => oblak_poruke($p, $k['Id'], url('poruke/osoba', ['r' => $id]));
if ($id && isset($_GET['nakon'])) {
    session_write_close();
    $nove = redovi('SELECT * FROM Poruke2 WHERE RazgovorId=? AND Id>? ORDER BY Id', [$id, (int) $_GET['nakon']]);
    json(['html' => implode('', array_map($oblak, $nove)), 'zadnji' => $nove ? (int) end($nove)['Id'] : (int) $_GET['nakon'],
          'procitano' => array_map('intval', array_column(redovi('SELECT Id FROM Poruke2 WHERE RazgovorId=? AND PosiljateljId=? AND Procitano=1', [$id, $k['Id']]), 'Id')),
          'ids' => array_map('intval', array_column(redovi('SELECT Id FROM Poruke2 WHERE RazgovorId=? AND Id>?', [$id, $skr]), 'Id'))]);
}
$dc = red('SELECT c.* FROM Korisnici k JOIN Clanovi c ON c.Id=k.ClanId WHERE k.Id=?', [$r['Drugi']]);
$adresa = $id ? url('poruke/osoba', ['r' => $id]) : url('poruke/osoba', ['k' => $r['Drugi']]);
ob_start(); ?>
<div class="d-flex align-items-center"><a href="<?= e(url('poruke')) ?>" class="small me-auto">← Sve poruke</a>
    <?php if ($id): ?><form method="post" action="<?= e($adresa) ?>"><?= csrf() ?><button name="radnja" value="obrisi-razgovor" class="btn btn-sm btn-link text-danger" data-potvrda="Obrisati cijeli razgovor? Briše se samo kod vas – sugovornik ga i dalje vidi.">🗑 Obriši razgovor</button></form><?php endif; ?></div>
<div class="d-flex align-items-center gap-3 my-2">
    <?= $dc ? avatar($dc) : '' ?>
    <div class="fw-semibold"><?= e(ime_korisnika((int) $r['Drugi'])) ?></div>
</div>
<div class="chat" id="chat">
    <?= implode('', array_map($oblak, $poruke)) ?>
    <?php if (!$poruke): ?><div class="text-muted small text-center py-3">Napišite prvu poruku.</div><?php endif; ?>
</div>
<form method="post" action="<?= e($adresa) ?>" class="d-flex gap-2 mt-2" id="slanje"><?= csrf() ?>
    <textarea name="tekst" class="form-control" rows="1" maxlength="2000" required placeholder="Poruka…" style="resize:vertical"></textarea>
    <button class="btn btn-primary">Pošalji</button>
</form>
<?php if ($id): ?>
<script>
(function () {
    var chat = document.getElementById('chat'), f = document.getElementById('slanje'), t = f.querySelector('textarea');
    var zadnji = <?= $poruke ? (int) end($poruke)['Id'] : 0 ?>, adresa = <?= json_encode($adresa) ?>;
    chat.scrollTop = chat.scrollHeight;
    var dohvati = function () {
        fetch(adresa + '&nakon=' + zadnji, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
            if (!j) return;
            if (j.html) { var dno = chat.scrollTop + chat.clientHeight >= chat.scrollHeight - 30; chat.insertAdjacentHTML('beforeend', j.html); zadnji = j.zadnji; if (dno) chat.scrollTop = chat.scrollHeight; }
            if (j.ids) chat.querySelectorAll('.chat-red[data-id]').forEach(function (el) { if (j.ids.indexOf(+el.dataset.id) < 0) el.remove(); });
            (j.procitano || []).forEach(function (pid) { var v = chat.querySelector('.chat-red.moja[data-id="' + pid + '"] .chat-vrijeme'); if (v && v.textContent.indexOf('✓✓') < 0) v.textContent += ' ✓✓'; });
        }).catch(function () {});
    };
    setInterval(function () { if (!document.hidden) dohvati(); }, 4000);
    f.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!t.value.trim()) return;
        fetch(adresa, { method: 'POST', body: new FormData(f), credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function () { t.value = ''; dohvati(); });
    });
    t.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 640) { e.preventDefault(); f.requestSubmit(); } });
})();
</script>
<?php endif;
stranica('Razgovor', ob_get_clean());
