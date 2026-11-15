<?php
/**
 * Kazalo „Gdje je…?“ – pretraga po ključnim riječima: gdje se u programu nalazi neka stvar (Upute → tražilica, tražilica u izborniku).
 * Svaki korisnik vidi samo stavke za koje ima pravo i čiji je modul uključen.
 * [stranica, put (gdje je), ključne riječi, pravo (0 = svi; 'clan' = samo član s profilom), modul (null = uvijek)]
 */
declare(strict_types=1);

function kazalo_stavke(): array
{
    return [
        // ---------- Moje ----------
        ['pocetna', '🏠 Početna', 'pocetna start naslovna', 0, null],
        ['moj-profil', '👤 Moje → Moj profil', 'profil fotografija slika kontakt telefon mobitel email adresa vozila registracija kenteken auto psi pas imenik dijeljenje potpis lozinka', 'clan', null],
        ['moj-profil', '👤 Moje → Moj profil → Lovački psi', 'pas psi lovacki pas ispit rasa vodic pasa sto pas radi', 'clan', 'psi'],
        ['moje-akcije', '🛠️ Moje → Moje radne akcije', 'radne akcije bodovi bod prijava rada moji bodovi kosnja oprema traktor trimer pila rad psa', 'clan', 'akcije'],
        ['moja-iskaznica', '🪪 Moje → Moja iskaznica', 'iskaznica qr kod clanska popust trgovina ispis', 'clan', 'iskaznice'],
        ['uplatnica', '💳 Moje → Moja uplatnica', 'uplatnica clanarina placanje iban hub3 2d kod banka', 'clan', 'clanarina'],
        ['moja-prava', '🔑 Moje → Moja prava', 'prava sto smijem uloga dozvola', 0, null],
        ['moji-dokumenti', '📄 Moje → Moji dokumenti', 'dokumenti oruzni list dozvola slika dokumenta', 'clan', 'mojidok'],
        ['promjena-lozinke', '🔒 Lozinka (gore desno)', 'lozinka promjena zaporka sifra password', 0, null],
        ['dvofaktorska', '🔐 Dvofaktorska prijava (2FA)', '2fa dvofaktorska authenticator google kod sigurnost', P_SUSTAV, null],
        ['push', '🔔 Push obavijesti', 'push obavijesti notifikacije mobitel zvono', 0, null],
        ['skeniraj', '📷 Skeniraj QR stanice', 'qr skeniranje dezinfekcija stanica kamera', 0, 'dezinfekcija'],
        // ---------- Lovište ----------
        ['revir', '🗺️ Lovište → Karta lovišta', 'karta lovista ceka cake naprava zauzmi sjedim ovdje kvar mapa gps', 0, 'revir'],
        ['revir/danas', '🌲 Lovište → Danas u lovištu', 'danas lovište tko je u lovistu zauzeto', 0, 'revir'],
        ['revir/dnevnik', '📓 Lovište → Lovački dnevnik', 'dnevnik lovacki tko kada gdje pirs obilazak lovna godina mjesec dan ispravak rucni upis', P_REVIR_DNEVNIK, 'revir'],
        ['revir/kvarovi', '🔧 Lovište → Kvarovi na napravama', 'kvar kvarovi popravak ceka ostecena naprava', 0, 'revir'],
        ['revir/postavke', '⚙️ Sustav → Postavke lovišta', 'postavke lovista vrste naprava istek zauzeca pocetni prikaz', P_REVIR_UREDI, 'revir'],
        ['revir/lovista', '🌲 Sustav → Lovišta', 'lovista granice kml putevi prosjeke vise lovista', P_SUSTAV, 'revir'],
        ['odstrel', '🦌 Odstrel', 'odstrel markica markice divljac plan odstrela odstrijel sle', P_ODSTREL_CITAJ, 'odstrel'],
        ['odstrel/plan', '🦌 Odstrel → Plan odstrela', 'plan odstrela ostvarenje', P_ODSTREL_CITAJ, 'odstrel'],
        ['odstrel/markice', '🏷️ Odstrel → Markice', 'markice izdatnica zaliha povrat ppd', P_ODSTREL_MARKICE, 'odstrel'],
        ['grupni-lov', '🎯 Skupni lov', 'skupni lov grupni lov zajednicki lov sos pozicija popis sudionika zapisnik', P_GRUPNI_LOV, 'grupni'],
        ['vodici-pasa', '🐕 Vodiči pasa', 'vodici pasa psi pas nasljedivanje potraga krvni trag', 0, 'psi'],
        // ---------- Dezinfekcija ----------
        ['dezinfekcija', '🧴 Dezinfekcija → Knjiga dezinfekcije', 'dezinfekcija knjiga asf dolazak odlazak kontrola inspekcija pdf', P_DEZ_PREGLED, 'dezinfekcija'],
        ['dezinfekcija/naknadno', '➕ Dezinfekcija → Naknadni upis', 'naknadni upis dezinfekcija zaboravio ponisti', P_DEZ_UREDI, 'dezinfekcija'],
        ['dezinfekcija/mobilna', '📱 Dezinfekcija → Mobilna stanica', 'mobilna stanica skupni lov skener dezinfekcija', P_DEZ_MOBILNA, 'dezinfekcija'],
        ['dezinfekcija/liste', '📄 Dezinfekcija → Papirnate liste', 'papirnata lista papir slika fotografija', P_DEZ_PREGLED, 'dezinfekcija'],
        ['dezinfekcija/postavke', '🔳 Sustav → Dezinfekcija – stanice i QR', 'stanice qr oznake radijus sredstvo inspekcija lozinka', P_DEZ_POSTAVKE, 'dezinfekcija'],
        // ---------- Udruga ----------
        ['kalendar', '📅 Kalendar', 'kalendar termin dogadaj sastanak dolazim pretplata ical', 0, 'kalendar'],
        ['imenik', '📒 Imenik', 'imenik telefon kontakt broj mobitel', 0, null],
        ['poruke', '✉️ Poruke', 'poruke chat razgovor posalji poruku', 0, null],
        ['obavijesti', '📢 Obavijesti', 'obavijesti procitao sam potvrda meldung', 0, 'obavijesti'],
        ['dokumenti', '📜 Statut i pravilnici', 'statut pravilnik dokumenti udruge', 0, 'dokumenti'],
        ['domovi', '🏡 Lovački domovi', 'lovacki dom kuca rezervacija zauzeto kljuc', 0, 'domovi'],
        ['oglasnik', '🛒 Oglasnik', 'oglasnik oglas prodaja kupnja oruzje', 0, 'oglasnik'],
        ['popusti', '🏷️ Popusti u trgovinama', 'popust trgovina benko akcija', 0, 'popusti'],
        ['inventura', '📦 Inventura', 'inventura popis stanje manjak visak treba stvarno', P_INVENTURA, 'inventura'],
        // ---------- Članovi i uprava ----------
        ['clanovi', '👥 Članovi', 'clanovi clan popis novi clan arhiva sekcija uredi clana pozivnica', P_CLANOVI_CITAJ, null],
        ['clanovi', '👥 Članovi → član → Funkcije', 'funkcija funkcije lovnik tajnik predsjednik godisnje obveze', P_CLANOVI_CITAJ, null],
        ['clanovi', '👥 Članovi → član → Lovački psi', 'pas psi bodovi za psa ukljuci bodove ispit', P_CLANOVI_CITAJ, 'psi'],
        ['clanovi', '👥 Članovi → član → Pristup i uloge', 'pristup racun uloga prava korisnik lozinka reset', P_SUSTAV, null],
        ['clanarina', '💶 Članarina', 'clanarina uplata rata dug placeno zaduzenje', P_CLANARINA_CITAJ, 'clanarina'],
        ['akcije', '🔨 Radne akcije', 'radne akcije odobri odbij na cekanju bodovi bod uredi ispravi oprema', P_AKCIJE_CITAJ, 'akcije'],
        ['akcije/nova', '➕ Radne akcije → Nova radna akcija', 'nova radna akcija grupni upis bodovi za vise clanova kosnja', P_AKCIJE_UNOS, 'akcije'],
        ['akcije/bodovi', '📚 Radne akcije → Arhiva bodova', 'arhiva bodova bodovi prosla godina ukupno', P_AKCIJE_CITAJ, 'akcije'],
        ['akcije/obveze', '📅 Radne akcije → Godišnje obveze', 'godisnje obveze obaveze bodovi hraniliste ceka ukljuci clana', P_OBVEZE, 'akcije'],
        ['izvjestaji/akcije', '📊 Izvještaj radnih akcija', 'izvjestaj radne akcije bodovi pdf excel ispis', P_IZVJESTAJI, 'akcije'],
        // ---------- Sustav ----------
        ['sustav', '⚙️ Sustav', 'sustav postavke admin', P_SUSTAV, null],
        ['sustav/bodovi', '🏅 Sustav → Bodovi', 'bodovi bod reset bodova pocetak godine prijenos arhiva bodovi za funkcije godisnje obveze obaveze pas psi drzanje psa', P_SUSTAV, 'akcije'],
        ['akcije/vrste', '🏷️ Sustav → Vrste radnih akcija', 'vrste radnih akcija bodovi osnovni bodovi dodatna oprema traktor trimer pila', P_SUSTAV, 'akcije'],
        ['sustav/uloge', '🛡️ Sustav → Uloge i prava', 'uloge prava dozvola funkcije u udruzi bodovi za funkcije lovnik domar', P_SUSTAV, null],
        ['sustav/pregled-prava', '🔎 Sustav → Tko ima koja prava', 'tko ima prava pregled prava', P_SUSTAV, null],
        ['sustav/korisnici', '🧑‍💻 Sustav → Korisnici', 'korisnici racuni odobravanje zakljucavanje', P_SUSTAV, null],
        ['sustav/samoprijava', '📝 Sustav → Samoprijava', 'samoprijava registracija link pozivnica', P_SUSTAV, null],
        ['sustav/udruga', '🏛️ Sustav → Podaci o udruzi', 'udruga naziv logo adresa oib', P_SUSTAV, null],
        ['sustav/izgled', '🎨 Sustav → Izgled', 'izgled tema tamna svijetla boja', P_SUSTAV, null],
        ['sustav/pocetna', '🏠 Sustav → Početna stranica', 'pocetna stranica raspored uloga', P_SUSTAV, null],
        ['sustav/moduli', '🧩 Sustav → Moduli', 'moduli ukljuci iskljuci funkcije programa', P_SUSTAV, null],
        ['sustav/sekcije', '🗂️ Sustav → Sekcije', 'sekcije lovne jedinice', P_SUSTAV, null],
        ['sustav/postavke', '📧 Sustav → E-pošta i adresa', 'email e-posta smtp adresa programa gmail', P_SUSTAV, null],
        ['sustav/poruke', '💬 Sustav → Predlošci poruka', 'predlosci poruka whatsapp viber tekst', P_SUSTAV, null],
        ['sustav/uplatnica', '🏦 Sustav → Uplatnica (IBAN)', 'uplatnica iban primatelj model poziv na broj', P_SUSTAV, 'clanarina'],
        ['sustav/kalendar', '🗓️ Sustav → Kalendar – vrste', 'kalendar vrste termina boje', P_SUSTAV, 'kalendar'],
        ['sustav/skupni-lov', '🎯 Sustav → Skupni lov – PDF', 'skupni lov pdf popis zapisnik broj lova', P_SUSTAV, 'grupni'],
        ['sustav/odstrel', '🦌 Sustav → Odstrel – evidencija', 'odstrel postavke vrste divljaci markice sle', P_SUSTAV, 'odstrel'],
        ['sustav/oglasnik', '🛒 Sustav → Oglasnik lovaca', 'oglasnik moderacija', P_SUSTAV, 'oglasnik'],
        ['sustav/uvoz', '📥 Sustav → Uvoz (Google kontakti)', 'uvoz google kontakti csv', P_SUSTAV, null],
        ['sustav/duplikati', '🔍 Sustav → Provjera duplikata', 'duplikati dvostruko', P_SUSTAV, null],
        ['sustav/kopije', '💾 Sustav → Sigurnosne kopije', 'kopija backup sigurnosna kopija zip lozinka kopije', P_SUSTAV, null],
        ['sustav/azuriranje', '🔄 Sustav → Ažuriranje programa', 'azuriranje update nova verzija', P_SUSTAV, null],
        ['sustav/dnevnik', '🧾 Sustav → Dnevnik promjena', 'dnevnik promjena log tko je sto promijenio', P_SUSTAV, null],
        ['sustav/reset', '🧹 Sustav → Reset nakon testiranja', 'reset brisanje probnih upisa test', P_SUSTAV, null],
        // ---------- Pomoć ----------
        ['upute', '📖 Upute za korištenje', 'upute pomoc kako', 0, null],
        ['pomoc', '❓ Pomoć i kontakt', 'pomoc kontakt problem greska pitanje autor', 0, null],
    ];
}

/** Stavke kazala koje trenutni korisnik smije vidjeti: [url, put, ključne riječi]. */
function kazalo_za_korisnika(): array
{
    $k = korisnik();
    $out = [];
    foreach (kazalo_stavke() as [$str, $put, $kljuc, $pravo, $mod]) {
        if ($mod !== null && !modul($mod)) {
            continue;
        }
        if ($pravo === 'clan' ? empty($k['ClanId']) : ($pravo && !ima($pravo))) {
            continue;
        }
        $out[] = [url($str), $put, $kljuc];
    }
    return $out;
}
