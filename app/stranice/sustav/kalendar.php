<?php
/** Vrste termina u kalendaru (naziv, boja, je li radna akcija). */
trazi(P_SUSTAV);
if (je_post()) {
    if (($_POST['radnja'] ?? '') === 'zadano') {
        spremi_postavku('Kalendar.Vrste', null);
    } else {
        $nove = [];
        foreach ((array) ($_POST['v'] ?? []) as $r) {
            $n = mb_substr(trim((string) ($r['naziv'] ?? '')), 0, 40);
            if ($n === '' || isset($nove[$n])) {
                continue;
            }
            $b = preg_match('/^#[0-9a-f]{6}$/i', (string) ($r['boja'] ?? '')) ? $r['boja'] : '#6c757d';
            $nove[$n] = ['naziv' => $n, 'boja' => $b, 'akcija' => isset($r['akcija'])];
        }
        if ($nove) {
            spremi_postavku('Kalendar.Vrste', json_encode(array_values($nove), JSON_UNESCAPED_UNICODE));
        }
        dnevnik('Uređene vrste termina');
    }
    poruka('Spremljeno.');
    preusmjeri('sustav/kalendar');
}
$vrste = vrste_dogadjaja();
$vrste[] = ['naziv' => '', 'boja' => '#6c757d', 'akcija' => false];
ob_start(); ?>
<h1 class="h3 mb-1">Kalendar – vrste termina</h1>
<p class="text-muted">Tko smije upisivati termine određuje se u <a href="<?= e(url('sustav/uloge')) ?>">Uloge i prava</a> (stupac „Kalendar uređ.“).
    Oznaka „radna akcija“: nakon termina Domar/Lovnik jednim klikom upisuje akciju svima koji su potvrdili dolazak.</p>
<form method="post" style="max-width:640px"><?= csrf() ?>
<table class="table table-sm align-middle"><thead><tr><th>Naziv</th><th>Boja</th><th class="text-center">Radna akcija</th></tr></thead><tbody>
<?php foreach ($vrste as $i => $v): ?>
    <tr><td><input name="v[<?= $i ?>][naziv]" class="form-control form-control-sm" value="<?= e($v['naziv']) ?>" maxlength="40" placeholder="nova vrsta…"></td>
        <td><input type="color" name="v[<?= $i ?>][boja]" class="form-control form-control-color form-control-sm" value="<?= e($v['boja']) ?>"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input" name="v[<?= $i ?>][akcija]" value="1"<?= chk($v['akcija']) ?>></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="small text-muted">Brisanje: obrišite naziv. Postojeći termini zadržavaju svoju vrstu.</p>
<div class="d-flex gap-2"><button class="btn btn-primary">Spremi</button>
    <button class="btn btn-outline-secondary" name="radnja" value="zadano" data-potvrda="Vratiti zadane vrste?">Zadane vrste</button></div>
</form>
<?php stranica('Kalendar – vrste', ob_get_clean());
