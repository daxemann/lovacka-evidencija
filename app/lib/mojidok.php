<?php
/**
 * Moji dokumenti: član sprema svoje dokumente (fotografija prednje/stražnje strane i/ili upis s brojem i rokom).
 * Vide ih samo član sam i tko smije vidjeti tog člana (Članovi – pregled u opsegu sekcija). Fotografije: prefiks „cdok-“, foto.php provjerava pravo.
 */
declare(strict_types=1);

const MOJIDOK_VRSTE = ['Lovačka iskaznica', 'Dozvola za lov', 'Oružni list', 'Osiguranje', 'Lovački ispit / certifikat', 'Ostalo'];

function mojidok_dokument(int $id): ?array
{
    return red('SELECT * FROM ClanDokumenti WHERE Id=?', [$id]);
}
function mojidok_lista(int $clanId): array
{
    return redovi('SELECT * FROM ClanDokumenti WHERE ClanId=? ORDER BY CASE Vrsta ' . implode(' ', array_map(fn($i, $v) => "WHEN '" . str_replace("'", "''", $v) . "' THEN $i", array_keys(MOJIDOK_VRSTE), MOJIDOK_VRSTE))
        . ' ELSE 99 END, VrijediDo IS NULL, VrijediDo DESC, Id', [$clanId]);
}
/** Smije li trenutni korisnik vidjeti dokumente člana. */
function mojidok_vidi(int $clanId): bool
{
    $k = korisnik();
    if (!$k || !modul('mojidok')) {
        return false;
    }
    if ($k['ClanId'] !== null && (int) $k['ClanId'] === $clanId) {
        return true;
    }
    return ima(P_CLANOVI_CITAJ) && u_opsegu(clan($clanId));
}
/** Uređuje član sam ili tko uređuje članove u opsegu. */
function mojidok_uredjuje(int $clanId): bool
{
    $k = korisnik();
    if (!$k || !modul('mojidok')) {
        return false;
    }
    if ($k['ClanId'] !== null && (int) $k['ClanId'] === $clanId) {
        return true;
    }
    return ima(P_CLANOVI_UREDI) && u_opsegu(clan($clanId));
}
/** Fotografija dokumenta (foto.php): samo uz pravo na tog člana. */
function mojidok_smije_datoteku(string $ime): bool
{
    $d = red('SELECT ClanId FROM ClanDokumenti WHERE Foto1=? OR Foto2=?', [$ime, $ime]);
    return $d && mojidok_vidi((int) $d['ClanId']);
}
/** [tekst, klasa bedža] za rok valjanosti. */
function mojidok_rok(?string $do): array
{
    if (!$do) {
        return ['', ''];
    }
    $dana = (int) floor((strtotime($do) - strtotime(danas())) / 86400);
    if ($dana < 0) {
        return ['istekao ' . datum($do), 'text-bg-danger'];
    }
    if ($dana <= 60) {
        return ['ističe ' . datum($do) . ' (' . $dana . ' d)', 'text-bg-warning'];
    }
    return ['vrijedi do ' . datum($do), 'text-bg-success'];
}
