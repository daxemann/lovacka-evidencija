<?php
/**
 * Dodatna oprema kod radnih akcija: uz vrstu (npr. Košnja) udruga upisuje opremu s bodovima (traktor, trimer, motorna pila…).
 * Tko je donio opremu, dobiva njezine bodove povrh osnovnih. Uz akciju se sprema snimka (naziv + bodovi u tom trenutku),
 * pa kasnija promjena bodova ne mijenja stare akcije.
 */
declare(strict_types=1);

/** [vrstaId => [['Id','Naziv','Bodovi'], ...]] */
function oprema_po_vrstama(): array
{
    static $o = null;
    if ($o === null) {
        $o = [];
        foreach (redovi('SELECT * FROM VrstaOprema ORDER BY Redoslijed, Id') as $r) {
            $o[(int) $r['VrstaAkcijeId']][] = $r;
        }
    }
    return $o;
}
function oprema_vrste(?int $vrstaId): array
{
    return $vrstaId ? (oprema_po_vrstama()[$vrstaId] ?? []) : [];
}
/** Za JavaScript: {vrstaId: [{id, n, b}]} */
function oprema_js(): array
{
    $r = [];
    foreach (oprema_po_vrstama() as $vid => $lista) {
        $r[$vid] = array_map(fn($x) => ['id' => (int) $x['Id'], 'n' => $x['Naziv'], 'b' => (float) $x['Bodovi']], $lista);
    }
    return $r;
}
/**
 * Snimka odabrane opreme: [['id','n','b'], ...]. $bodovi = ručno upisani bodovi po opremi (id => broj), inače iz postavki vrste.
 */
function oprema_odabir(?int $vrstaId, array $ids, array $bodovi = []): array
{
    $ids = array_flip(array_map('intval', $ids));
    $out = [];
    foreach (oprema_vrste($vrstaId) as $x) {
        $id = (int) $x['Id'];
        if (isset($ids[$id])) {
            $b = isset($bodovi[$id]) && $bodovi[$id] !== '' ? u_broj((string) $bodovi[$id]) : null;
            $out[] = ['id' => $id, 'n' => $x['Naziv'], 'b' => $b ?? (float) $x['Bodovi']];
        }
    }
    return $out;
}
function oprema_json(array $odabir): ?string
{
    return $odabir ? json_encode($odabir, JSON_UNESCAPED_UNICODE) : null;
}
function oprema_lista(?string $json): array
{
    $j = $json ? json_decode($json, true) : null;
    return is_array($j) ? $j : [];
}
function oprema_zbroj(array|string|null $o): float
{
    $l = is_array($o) ? $o : oprema_lista($o);
    return array_sum(array_map(fn($x) => (float) ($x['b'] ?? 0), $l));
}
/** „Traktor +5, Trimer +2“ */
function oprema_tekst(?string $json, bool $bodovi = true): string
{
    return implode(', ', array_map(fn($x) => $x['n'] . ($bodovi && (float) $x['b'] ? ' +' . broj((float) $x['b'], 1) : ''), oprema_lista($json)));
}
function oprema_html(?string $json): string
{
    $l = oprema_lista($json);
    if (!$l) {
        return '';
    }
    return '<span class="oprema-oznake">' . implode('', array_map(fn($x) => '<span class="badge rounded-pill text-bg-light border me-1" title="dodatna oprema">🧰 '
        . e($x['n']) . ((float) $x['b'] ? ' <b>+' . e(broj((float) $x['b'], 1)) . '</b>' : '') . '</span>', $l)) . '</span>';
}
