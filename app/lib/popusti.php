<?php
/**
 * Popusti u trgovinama: trgovine (lovački dućani, oružari, veterinari…) koje članovima udruge daju popust.
 * Član otvori trgovinu u aplikaciji i dobije svoju člansku iskaznicu na zaslonu – pokaže je na blagajni
 * ili se javi prodavaču. Trgovine upisuje tko ima pravo „Popusti u trgovinama – uređivanje“;
 * ostali članovi vide samo važeće ponude (uključene i neistekle).
 */
declare(strict_types=1);

/** Način korištenja popusta: ključ => [ikona, kratko, uputa članu]. */
function pop_nacini(): array
{
    return [
        'blagajna' => ['🛒', 'Pokazati iskaznicu na blagajni', 'Prije plaćanja pokažite ovaj zaslon na blagajni.'],
        'kontakt' => ['📞', 'Javiti se prodavaču', 'Javite se prodavaču i recite da ste član udruge. Ovaj zaslon pokažite pri kupnji / preuzimanju.'],
    ];
}

function pop_nacin(?string $n): array
{
    return pop_nacini()[$n ?? ''] ?? pop_nacini()['blagajna'];
}

function pop_smije_uredjivati(): bool
{
    return ima(P_POPUSTI);
}

/** Je li ponuda trenutno važeća (uključena i nije istekla). */
function pop_vrijedi(array $t): bool
{
    return (int) $t['Aktivno'] === 1 && (!$t['VrijediDo'] || substr((string) $t['VrijediDo'], 0, 10) >= danas());
}

/** Trgovine za popis: članovi vide samo važeće, administrator sve. */
function pop_trgovine(bool $sve = false): array
{
    $r = redovi('SELECT * FROM Popusti ORDER BY Redoslijed, Naziv COLLATE NOCASE');
    return $sve ? $r : array_values(array_filter($r, 'pop_vrijedi'));
}

function pop_broj_vazecih(): int
{
    static $n = null;
    return $n ??= count(pop_trgovine());
}

/** Poveznica na web (dodaje https:// ako nedostaje). */
function pop_web_url(?string $w): ?string
{
    $w = trim((string) $w);
    if ($w === '') {
        return null;
    }
    return preg_match('#^https?://#i', $w) ? $w : 'https://' . $w;
}

/** Logo trgovine ili slovo na obojenoj podlozi. */
function pop_logo_html(array $t, string $klasa = 'pop-logo'): string
{
    if (!empty($t['Logo'])) {
        return '<img src="' . e(foto_url($t['Logo'])) . '" class="' . e($klasa) . '" alt="" loading="lazy">';
    }
    return '<span class="' . e($klasa) . ' pop-logo-slovo" aria-hidden="true">' . e(mb_strtoupper(mb_substr(trim((string) $t['Naziv']), 0, 1))) . '</span>';
}
