<?php
/**
 * Moduli: svaka udruga uključuje samo dijelove programa koje stvarno koristi (Sustav → Moduli).
 * Isključen modul: nestaje iz izbornika, početne stranice i uputa, stranice su nedostupne,
 * a prava tog modula ne vrijede (ima() vraća false). Podaci ostaju u bazi – ponovnim uključivanjem sve je opet tu.
 * Zadano je sve uključeno (postojeće instalacije rade kao i prije).
 */
declare(strict_types=1);

/** Ključ => [ikona, naziv, opis, prava modula, stranice (točno ime ili prefiks sa „/“ na kraju)] */
function moduli(): array
{
    return [
        'akcije' => ['🔨', 'Radne akcije i bodovi', 'Prijava radnih akcija, odobravanje, bodovi (i oprema), izvještaji.',
            P_AKCIJE_CITAJ | P_AKCIJE_ODOBRI | P_IZVJESTAJI, ['akcije', 'akcije/', 'moje-akcije', 'izvjestaji/', 'izvoz/akcije-csv', 'izvoz/akcije-pdf']],
        'clanarina' => ['💶', 'Članarina i uplatnice', 'Članarina po godinama, rate, uplate, HUB-3 uplatnica za članove.',
            P_CLANARINA_CITAJ | P_CLANARINA_UREDI, ['clanarina', 'clanarina/', 'uplatnica', 'izvoz/uplatnica', 'sustav/uplatnica']],
        'kalendar' => ['📅', 'Kalendar', 'Termini udruge, prijave na termine, pretplata na kalendar.',
            P_KALENDAR, ['kalendar', 'kalendar/', 'kalendar-pretplata', 'sustav/kalendar']],
        'dezinfekcija' => ['🧴', 'Knjiga dezinfekcije', 'Stanice s QR oznakom, mobilna stanica, papirnate liste, pristup za inspekciju.',
            P_DEZ_PREGLED | P_DEZ_UREDI | P_DEZ_POSTAVKE | P_DEZ_MOBILNA | P_DEZ_ZA_DRUGE,
            ['dezinfekcija', 'dezinfekcija/', 'dez', 'dez/', 'dez-slika', 'api/dez-sync', 'inspekcija', 'izvoz/dez-pdf']],
        'revir' => ['🗺️', 'Lovište', 'Karta lovišta, čeke i zauzimanje, kvarovi na napravama, lovački dnevnik.',
            P_REVIR_UREDI | P_REVIR_NADZOR | P_REVIR_DNEVNIK, ['revir', 'revir/', 'api/revir', 'izvoz/kvarovi-pdf']],
        'vjetar' => ['💨', 'Vjetar', 'Trenutni vjetar za lovište (Open-Meteo, besplatno), idealni smjerovi vjetra za čeke na karti, kompas na početnoj. Treba modul Lovište.',
            0, []],
        'vrijeme' => ['🌤️', 'Vrijeme i Mjesec', 'Danas u lovištu: zora, izlazak i zalazak Sunca, Mjesec, temperatura, kiša i „Kad u lov?“ za danas i sutra. Treba modul Lovište.',
            0, []],
        'grupni' => ['🎯', 'Skupni lov', 'Poziv lovcima, karta uživo, SOS, popis sudionika (skeniranje iskaznica).',
            P_GRUPNI_LOV, ['grupni-lov', 'grupni-odgovor', 'grupni-popis', 'grupni-zapisnik', 'api/grupni-lov', 'api/grupni-popis', 'sustav/skupni-lov']],
        'iskaznice' => ['🪪', 'Članske iskaznice (QR)', 'Iskaznica s fotografijom i QR kodom (PDF i u aplikaciji).',
            0, ['moja-iskaznica', 'izvoz/iskaznice']],
        'domovi' => ['🏡', 'Lovački domovi', 'Domovi udruge / sekcija, rezervacije i kalendar zauzeća.',
            P_DOMOVI, ['domovi', 'domovi/', 'izvoz/domovi-pdf']],
        'obavijesti' => ['📢', 'Obavijesti', 'Obavijesti udruge s potvrdom „pročitao“.',
            P_OBAVIJESTI, ['obavijesti', 'obavijesti/', 'api/obavijesti', 'izvoz/obavijest-pdf']],
        'oglasnik' => ['🛒', 'Oglasnik', 'Interni oglasi i zajednički Oglasnik lovaca.',
            P_OGLASNIK_MOD, ['oglasnik', 'oglasnik/', 'oglasnik-sso', 'oglasnik-registracija', 'sustav/oglasnik']],
        'inventura' => ['📦', 'Inventura', 'Popis imovine udruge (stanje / manjak), ispis za popis.',
            P_INVENTURA | P_INVENTURA_UREDI, ['inventura', 'izvoz/inventura-pdf']],
        'psi' => ['🐕', 'Lovački psi', 'Psi članova, vodiči pasa, bodovi za pse.',
            0, ['vodici-pasa']],
        'popusti' => ['🏷️', 'Popusti u trgovinama', 'Zajednički popusti svih udruga (preko Oglasnika lovaca) – član pokaže iskaznicu na blagajni.',
            P_POPUSTI, ['popusti', 'popusti/']],
        'dokumenti' => ['📜', 'Statut i pravilnici', 'Dokumenti udruge za članove.',
            0, ['dokumenti', 'sustav/dokumenti']],
    ];
}

function modul(string $kljuc): bool
{
    return postavka('Modul.' . $kljuc) !== '0';
}

/** Prava isključenih modula (ima() ih ne priznaje). */
function moduli_iskljucena_prava(): int
{
    static $m = null;
    if ($m === null) {
        $m = 0;
        foreach (moduli() as $kl => $d) {
            if (!modul($kl)) {
                $m |= $d[3];
            }
        }
    }
    return $m;
}

/** Kojem modulu pripada stranica (ili null – jezgra programa). */
function modul_stranice(string $p): ?string
{
    foreach (moduli() as $kl => $d) {
        foreach ($d[4] as $s) {
            if ($p === $s || (str_ends_with($s, '/') && str_starts_with($p, $s))) {
                return $kl;
            }
        }
    }
    return null;
}

/** Zaustavlja stranicu isključenog modula. */
function provjeri_modul(string $p): void
{
    $m = modul_stranice($p);
    if ($m === null || modul($m)) {
        return;
    }
    http_response_code(404);
    if (str_starts_with($p, 'api/')) {
        json(['ok' => false, 'greska' => 'Ovaj dio programa je isključen.']);
    }
    $naziv = moduli()[$m][1];
    $poruka = '<h1 class="h3">' . e(moduli()[$m][0] . ' ' . $naziv) . '</h1><div class="alert alert-secondary">Ovaj dio programa u vašoj udruzi nije uključen.'
        . (korisnik() && ima(P_SUSTAV) ? ' Uključuje se u <a href="' . e(url('sustav/moduli')) . '">Sustav → Moduli</a>.' : '') . '</div>';
    korisnik() ? stranica($naziv, $poruka) : stranica($naziv, $poruka, 'prazno');
}
