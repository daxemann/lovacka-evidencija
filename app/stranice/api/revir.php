<?php
/** Lovište – JSON za kartu: stanje naprava, zauzimanje, oslobađanje, uređivanje naprava. */
$k = korisnik();
$sekcije = revir_sekcije();
if (!$sekcije) {
    json(['greska' => 'Nemate pristup karti lovišta.']);
}
/** Sekcije koje traži karta (?s=… ili sve vidljive). */
$odabrane = function () use ($sekcije): array {
    $s = (string) ul('s', '');
    if ($s !== '' && $s !== 'sve' && array_key_exists((int) $s, $sekcije)) {
        return [(int) $s => $sekcije[(int) $s]];
    }
    return $sekcije;
};

if (!je_post()) {
    session_write_close();
    json(['naprave' => revir_stanje($odabrane()), 'obavijesti' => revir_broj_obavijesti(), 'vrijeme' => date('H:i')]);
}

$greska = null;
$radnja = (string) ($_POST['radnja'] ?? '');
switch ($radnja) {
    case 'zauzmi':
        $greska = revir_zauzmi((int) ($_POST['id'] ?? 0), isset($_POST['gost']) ? (string) ($_POST['gostIme'] ?? '') : null);
        break;
    case 'oslobodi':
        $greska = revir_oslobodi((int) ($_POST['zid'] ?? 0));
        break;
    case 'odlazim':
        foreach (redovi('SELECT Id FROM RevirZauzeca WHERE ClanId=? AND Kraj IS NULL', [(int) $k['ClanId']]) as $z) {
            $greska ??= revir_oslobodi((int) $z['Id']);
        }
        break;
    case 'obrisi':
        $greska = revir_obrisi((int) ($_POST['zid'] ?? 0));
        break;

    case 'naprava-spremi':
        $id = (int) ($_POST['id'] ?? 0);
        $stara = $id ? revir_naprava($id) : null;
        if ($id && !$stara) {
            $greska = 'Naprava ne postoji.';
            break;
        }
        $sid = ($_POST['sekcija'] ?? '') !== '' && (int) $_POST['sekcija'] > 0 ? (int) $_POST['sekcija'] : null;
        if (!revir_moze_urediti($sid) || !revir_moze_vidjeti($sid) || ($stara && !revir_moze_urediti($stara['SekcijaId'] !== null ? (int) $stara['SekcijaId'] : null))) {
            $greska = 'Nemate pravo uređivati lovne naprave ove sekcije.';
            break;
        }
        $naziv = mb_substr(ul_str('naziv'), 0, 80);
        if ($naziv === '') {
            $greska = 'Upišite naziv.';
            break;
        }
        $koord = revir_koord($_POST['lat'] ?? null, $_POST['lon'] ?? null);
        $polja = [
            'Broj' => mb_substr(ul_str('broj'), 0, 12) ?: null, 'Naziv' => $naziv,
            'Vrsta' => in_array(ul_str('vrsta'), REVIR_VRSTE, true) ? ul_str('vrsta') : null,
            'SekcijaId' => $sid, 'Napomena' => mb_substr(ul_str('napomena'), 0, 500) ?: null,
            'Lat' => $koord[0] ?? null, 'Lon' => $koord[1] ?? null, 'Azurirano' => sada(),
        ];
        if (!$koord && $stara && !isset($_POST['bezPolozaja'])) {
            unset($polja['Lat'], $polja['Lon']); // položaj se ne mijenja
        }
        $f = $_FILES['foto'] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $polja['Foto'] = spremi_sliku($f, 'naprava-', 1600);
                if ($stara) {
                    obrisi_sliku($stara['Foto']);
                }
            } catch (RuntimeException $e) {
                $greska = $e->getMessage();
                break;
            }
        } elseif ($stara && isset($_POST['obrisiFoto'])) {
            obrisi_sliku($stara['Foto']);
            $polja['Foto'] = null;
        }
        if ($stara) {
            azuriraj('RevirNaprave', $id, $polja);
            dnevnik('Lovište – uređena naprava', 'RevirNaprava', $id, revir_oznaka($polja + $stara));
        } else {
            $id = umetni('RevirNaprave', $polja + ['Aktivna' => 1, 'KreiraoIme' => $k['Naziv'], 'Kreirano' => sada()]);
            dnevnik('Lovište – nova naprava', 'RevirNaprava', $id, revir_oznaka($polja));
        }
        json(['ok' => true, 'id' => $id, 'naprave' => revir_stanje($odabrane())]);

    case 'naprava-pomakni':
        $n = revir_naprava((int) ($_POST['id'] ?? 0));
        $koord = revir_koord($_POST['lat'] ?? null, $_POST['lon'] ?? null);
        if (!$n || !$koord || !revir_moze_urediti($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null)) {
            $greska = 'Položaj nije spremljen.';
            break;
        }
        azuriraj('RevirNaprave', (int) $n['Id'], ['Lat' => $koord[0], 'Lon' => $koord[1], 'Azurirano' => sada()]);
        dnevnik('Lovište – premještena naprava', 'RevirNaprava', (int) $n['Id'], revir_oznaka($n) . ' · ' . $koord[0] . ', ' . $koord[1]);
        break;

    case 'naprava-obrisi':
        $n = revir_naprava((int) ($_POST['id'] ?? 0));
        if (!$n || !revir_moze_urediti($n['SekcijaId'] !== null ? (int) $n['SekcijaId'] : null)) {
            $greska = 'Nemate pravo.';
            break;
        }
        if (revir_aktivno((int) $n['Id'])) {
            $greska = 'Naprava je trenutno zauzeta – ne može se obrisati.';
            break;
        }
        q('DELETE FROM RevirNaprave WHERE Id=?', [$n['Id']]);
        obrisi_sliku($n['Foto']);
        dnevnik('Lovište – obrisana naprava', 'RevirNaprava', (int) $n['Id'], revir_oznaka($n));
        break;

    default:
        $greska = 'Nepoznata radnja.';
}
json(['ok' => $greska === null, 'greska' => $greska, 'naprave' => revir_stanje($odabrane())]);
