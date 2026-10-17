<?php
/** Upute za korištenje – kratka pomoć po dijelovima programa; svatko vidi samo ono što smije koristiti. */
$k = korisnik();
$revir = (bool) revir_sekcije();
$odjeljci = [];
$o = function (string $id, string $naslov, string $html) use (&$odjeljci) {
    $odjeljci[] = '<details class="card mb-2 upute-odjeljak" id="' . e($id) . '"><summary class="card-header fw-semibold" style="cursor:pointer">' . e($naslov) . '</summary>'
        . '<div class="card-body small">' . $html . '</div></details>';
};

$o('prvi-koraci', '👋 Prvi koraci', '
<ul>
<li><b>Izbornik</b> je lijevo (na mobitelu gumb gore lijevo). Skupine (Moje, Lovište, Članovi…) se dodirom na naslov sklapaju i otvaraju.</li>
<li><b>Ikona na mobitelu:</b> Moj profil → <b>Dodaj na početni zaslon</b>. Program se tada otvara kao aplikacija, bez upisivanja adrese.</li>
<li><b>Lozinka</b> se mijenja gore desno (poveznica „Lozinka“). Zaboravljenu lozinku obnovite na stranici za prijavu.</li>
<li><b>Ne vidite neku stavku u izborniku?</b> Izbornik pokazuje samo ono što smijete. Što smijete, piše pod <b>Moje → Moja prava</b>; za više prava javite se administratoru udruge.</li>
<li>Program sve sprema sam – nakon promjene uvijek dodirnite gumb <b>Spremi</b> ili <b>Pošalji</b> na dnu obrasca.</li>
</ul>');

if ($k['ClanId']) {
    $o('moje', '🙋 Moje – radne akcije, profil, uplatnica', '
<ul>
<li><b>Moje radne akcije:</b> upišite što ste radili (vrsta, datum, opis, po želji fotografija) i dodirnite <b>Pošalji na odobrenje</b>.
Bodovi vrijede tek kad akciju odobri uprava. Dok nije odobrena, možete je urediti ili obrisati. Gore vidite bodove u lovnoj godini (1.4.–31.3.).</li>
<li><b>Dodatna oprema:</b> kod nekih vrsta (npr. Košnja) kvačicom označite što ste donijeli – traktor, trimer, motorna pila… Svaka oprema donosi dodatne bodove.</li>
<li><b>Potraga s psom (krvni trag)</b> prijavljuje se isto u Moje radne akcije, uz odabir psa.</li>
<li><b>Moj profil:</b> fotografija, kontakt, <b>što drugi vide u Imeniku</b> (mobitel, e-mail, adresa – vi odlučujete), <b>moja vozila</b> (reg. oznake za dezinfekciju) i <b>moji lovački psi</b> s ispitima.</li>'
    . (uplatnica_za_clanove() ? '
<li><b>Moja uplatnica:</b> uplatnica za članarinu s 2D kodom. Kod skenirajte u mobilnom bankarstvu ili ga pokažite na šalteru; može se i ispisati (PDF). Vidite samo svoju.</li>' : '') . '
<li><b>Moja prava:</b> popis onoga što smijete u programu.</li>
</ul>');
}

$o('kalendar', '📅 Kalendar', '
<ul>
<li>Termini udruge ili vaše sekcije (lovovi, radne akcije, sastanci…). Dodirom na termin vidite detalje i navigaciju do mjesta.</li>
<li><b>✔ Dolazim / Ne dolazim</b> – nije obavezno, ali pomaže organizatoru. Kod lova sa psima kvačicom označite psa kojeg dovodite.</li>
<li>Termine možete dodati u kalendar mobitela (pojedinačno ili stalnom <b>pretplatom</b> – novi termini se tada pojavljuju sami).</li>'
    . (ima(P_KALENDAR) ? '
<li><i>Uređivanje:</i> novi termin za cijelu udrugu ili odabrane sekcije (ostali ga ne vide). Nakon radne akcije jednim klikom upišete akciju svima koji su potvrdili dolazak.</li>' : '') . '
</ul>');

$o('dezinfekcija-upis', '🧴 Dezinfekcija – upis na stanici', '
<ol>
<li>Na dezinfekcijskoj stanici skenirajte <b>QR oznaku</b> – najbolje u programu: <b>📷 Skeniraj QR stanice</b> (na iPhoneu obavezno ovako, ne kamerom mobitela).</li>
<li>Dopustite <b>lokaciju</b>. Upis je moguć samo na samoj stanici (zadano unutar 100 m).</li>
<li>Odaberite <b>DOLAZAK</b> ili <b>ODLAZAK</b>, razlog i vozilo. U istom upisu možete dodati do 3 lovca iz udruge i goste.</li>
</ol>
<ul>
<li><b>Bez signala:</b> stanicu jednom otvorite s internetom – nakon toga upis radi i bez signala. Spremi se na mobitelu i pošalje sam čim ima interneta
(na iPhoneu kad ponovno otvorite aplikaciju). Upisi koji čekaju vide se na stranici <b>Skeniraj QR stanice</b> – tamo je i gumb <b>Pošalji sada</b>.</li>
<li>Ako program upozori da <b>sat mobitela</b> nije točan, namjestite automatsko vrijeme na mobitelu.</li>
<li>Upis se <b>ne može mijenjati</b>. Pogrešan upis javite lovočuvaru – on ga može poništiti uz razlog.</li>
<li>Upis odlaska nije obavezan. Ako ga ne upišete, program ga ne dopisuje sam.</li>
</ul>');

if ($revir) {
    $o('loviste', '🗺️ Lovište – karta, čeke, dnevnik', '
<ul>
<li><b>Karta lovišta</b> prikazuje lovne naprave vaše sekcije. Zelena = slobodna, crvena = netko sjedi.</li>
<li>Dodir na napravu → <b>Zauzmi – sjedim ovdje</b>. Kad idete, dodirnite <b>Odlazim</b>. Ako zaboravite, naprava se sama oslobodi u 03:00.</li>
<li><b>Gost</b> sjedi na drugoj napravi: <b>Zauzmi za gosta</b> – za gosta odgovarate vi.</li>
<li>Prelaskom na drugu napravu stara se sama oslobađa.</li>
<li><b>Danas u lovištu:</b> tko gdje sjedi danas.</li>
<li><b>Kvar na napravi</b> (slomljena prečka, krov, ljestve…): dodir na napravu → <b>🔧 Prijavi kvar</b>, kratki opis, po želji fotografija.
Ako je opasno, označite <b>Nesigurno</b> – svi vide crveni ⚠ na karti. Popis svih prijava: <b>Lovište → Kvarovi na napravama</b>.
Svoju prijavu možete označiti riješenom ili je povući dok je nitko nije preuzeo.</li>'
    . (ima(P_REVIR_DNEVNIK) ? '
<li><b>Lovački dnevnik</b> nastaje sam (tko, kada, gdje) iz zauzeća naprava i dolazaka iz knjige dezinfekcije; filtar po aktivnosti (lov, radna akcija, hranjenje…), ispis i PDF.</li>' : '')
    . (ima(P_REVIR_NADZOR) ? '
<li><i>Nadzor:</i> 🔔 gore desno pokazuje zauzimanja i oslobađanja u vašoj sekciji; zauzeće možete obrisati.</li>' : '')
    . (ima(P_REVIR_NADZOR) || ima(P_REVIR_UREDI) ? '
<li><i>Kvarovi:</i> nova prijava stiže u 🔔. Na popisu kvarova: <b>Preuzimam</b> (svi vide tko radi na tome), <b>Riješeno</b> s napomenom, <b>Obriši</b>; PDF popis za obilazak ili radnu akciju.</li>' : '')
    . (ima(P_SUSTAV) || ima(P_REVIR_UREDI) ? '
<li><i>Postavke lovišta:</i> granice iz KML/KMZ datoteke (Google My Maps / Earth), vrste naprava sa sličicama, nove naprave dodirom na kartu,
<b>📍 Nova na mojoj poziciji</b> ili uvozom fotografija s GPS-om. Pin se može povući na točno mjesto.</li>' : '') . '
</ul>');
}

$o('push', '🔔 Push obavijesti na mobitel', '
<ul>
<li>Obavijest stiže na mobitel i kad je aplikacija <b>zatvorena</b> – nova poruka, obavijest udruge, radna akcija odobrena/odbijena' . (revir_sekcije() && (ima(P_REVIR_NADZOR) || ima(P_REVIR_UREDI)) ? ', kvarovi na napravama' : '') . (ima(P_REVIR_NADZOR) ? ', zauzimanje čeka' : '') . '.</li>
<li>Uključite je u izborniku <b>Moje → Push obavijesti</b> → <b>Uključi obavijesti</b> i dopustite obavijesti kad mobitel pita. Gumb <b>Pošalji probnu</b> provjerava da sve radi.</li>
<li><b>Android:</b> radi u Chromeu, i bez dodavanja na početni zaslon. <b>iPhone:</b> samo iz aplikacije na početnom zaslonu (iOS 16.4 ili noviji) – Safari → Dijeli → Dodaj na početni zaslon.</li>
<li>Što ne želite primati, isključite na istoj stranici. Uključuje se posebno na svakom uređaju (mobitel, računalo).</li>
<li>Nakon <b>odjave</b> uređaj više ne prima obavijesti za vaš račun.</li>
</ul>');

$o('clanovi-svi', '👥 Imenik, poruke, oglasi, dokumenti', '
<ul>
<li><b>Imenik:</b> članovi udruge s kontaktima koje su sami podijelili. Svoje postavke mijenjate u Moj profil.</li>
<li><b>Vodiči pasa:</b> tko ima lovačkog psa i radi li potragu (krvni trag) – korisno kad treba pas za potragu.</li>
<li><b>Obavijesti:</b> službene obavijesti uprave. Neke treba potvrditi s <b>Pročitao/la sam</b> – prikazuju se dok ih ne potvrdite.</li>'
    . (ima(P_OBAVIJESTI) ? '
<li><i>Slanje obavijesti:</i> svima, sekcijama, funkciji ili odabranim članovima; po želji s obaveznom potvrdom. Vidite tko je potvrdio, članove bez aplikacije označite „usmeno obaviješten“, PDF s potvrdama.</li>' : '') . '
<li><b>Poruke:</b> poruka bilo kojem članu unutar aplikacije. Svoju poruku možete obrisati.</li>
<li><b>Oglasnik:</b> kupujem / prodajem (oružje, optika, pribor, psi…) sa slikama i porukama prodavatelju. Oružje i streljivo samo prema zakonu.</li>
<li><b>Statut i pravilnici:</b> dokumenti udruge za čitanje.</li>
</ul>');

$udruga = '';
if (ima(P_CLANOVI_CITAJ)) {
    $udruga .= '<li><b>Članovi:</b> podaci, fotografija, funkcije, vozila, lovački psi, pristup i uloge. Bivši članovi idu u arhivu (gube pristup). Pristup se daje <b>pozivnicom</b> ili <b>samoprijavom</b> – uvijek uz vaše odobrenje.</li>';
}
if (ima(P_CLANARINA_CITAJ)) {
    $udruga .= '<li><b>Članarina:</b> plan plaćanja po godini (jednokratno ili 1–12 rata), uplate se raspoređuju po ratama, filtar „Dospjela rata“. Odabranim članovima uplatnice šaljete e-poštom / WhatsAppom / Viberom ili ispisujete (3 po A4).</li>';
}
if (ima(P_AKCIJE_CITAJ) || ima(P_AKCIJE_ODOBRI)) {
    $udruga .= '<li><b>Radne akcije:</b> prijave članova čekaju odobrenje (broj u izborniku). <b>Nova radna akcija</b> – upišete jednom i označite sve koji su bili; ide na odobrenje s predloženim bodovima (vrijede tek kad se odobre). Psi (posjed 1.1., pas na lovu) dolaze sami na odobrenje.</li>'
        . '<li><b>Dodatna oprema</b> (Sustav → Vrste radnih akcija): uz vrstu upišite opremu s bodovima (npr. Košnja: Traktor +5, Trimer +2). Kod nove akcije uz svakog člana kvačicom označite što je donio – bodovi se zbrajaju sami, a bodove opreme i osnovne možete prije spremanja promijeniti. Kod odobravanja prijava predlaže se osnovno + oprema.</li>';
}
if (ima(P_IZVJESTAJI)) {
    $udruga .= '<li><b>Izvještaj radnih akcija:</b> razdoblje (i lovna godina), grupiranje po članu/mjesecu/vrsti/sekciji, ispis, PDF, slanje e-poštom – član dobiva samo svoje.</li>';
}
if (ima(P_INVENTURA) || ima(P_INVENTURA_UREDI)) {
    $udruga .= '<li><b>Inventura:</b> mape (dom, hranilišta, oprema…), stavke s „Treba“ i „Stvarno“; manjak crveno, višak zeleno. PDF lista s praznim stupcem za popis rukom, zatim upis i PDF rezultata.</li>';
}
if ($udruga !== '') {
    $o('udruga', '🏛️ Udruga – za upravu', '<ul>' . $udruga . '</ul>');
}

$dez = '';
if (ima(P_DEZ_PREGLED)) {
    $dez .= '<li><b>Knjiga dezinfekcije:</b> pregled po sekciji i razdoblju, „trenutno u lovištu“, ispis, PDF, e-pošta. Gumb <b>Izjava za inspekciju</b>.</li>';
}
if (ima(P_DEZ_ZA_DRUGE)) {
    $dez .= '<li><b>Upis za drugog lovca</b> (stariji bez mobitela): na stanici, u upisu odaberite lovca – bilježi se tko je upisao.</li>';
}
if (ima(P_DEZ_MOBILNA)) {
    $dez .= '<li><b>Mobilna stanica</b> (skupni lov): na mjestu okupljanja <b>📍 Aktiviraj ovdje</b>, lovci skeniraju QR mobilne stanice. Uživo vidite tko je stigao i tko otišao. Pri zatvaranju potvrdite organiziranu dezinfekciju pri odlasku i tko ju je provodio.</li>';
}
if (ima(P_DEZ_UREDI)) {
    $dez .= '<li><b>Naknadni upis</b> i <b>poništavanje</b> – uvijek s razlogom, vidljivo u knjizi. <b>Papirnate liste:</b> fotografirajte papirnatu listu sa stanice i priložite je uz stanicu i razdoblje.</li>';
}
if (ima(P_DEZ_POSTAVKE)) {
    $dez .= '<li><b>Stanice i QR:</b> položaj (📍 Ovdje sam), radijus, sredstvo, ispis QR oznake, odgovorna osoba i potpis, QR i lozinka za inspekciju.</li>';
}
if ($dez !== '') {
    $o('dezinfekcija-uprava', '🧴 Dezinfekcija – za ovlaštene', '<ul>' . $dez . '<li>Inspekcija ulazi preko svoje QR oznake i lozinke – svaki pristup bilježi se u dnevnik.</li></ul>');
}

if (ima(P_SUSTAV)) {
    $o('sustav', '⚙️ Sustav – za administratore', '
<ul>
<li><b>Podaci o udruzi:</b> naziv, adresa, OIB, logo – ispisuju se na PDF-ovima.</li>
<li><b>E-pošta i adresa:</b> slanje e-pošte (najlakše <b>Gmail čarobnjak</b>) i javna adresa programa za pozivnice i QR oznake.</li>
<li><b>Korisnici / Samoprijava:</b> odobravanje pristupa (broj na čekanju u izborniku), poveznica za samoprijavu (npr. za WhatsApp grupu).</li>
<li><b>Uloge i prava:</b> što koja uloga smije i za koje sekcije.</li>
<li><b>Sekcije, vrste radnih akcija (bodovi), predlošci poruka, vrste u kalendaru, uplatnica (IBAN), oglasnik lovaca.</b></li>
<li><b>Uvoz (Google kontakti)</b> i <b>Provjera duplikata</b> – ništa se ne sprema bez vaše potvrde.</li>
<li><b>Sigurnosne kopije:</b> dnevna kopija ide sama; <b>kompletna kopija (ZIP)</b> povremeno spremite i izvan poslužitelja.</li>'
    . (smije_azurirati() ? '
<li><b>Ažuriranje programa:</b> kad piše „Dostupna je nova verzija“ – jedan klik; prije toga se sama radi kopija baze.</li>' : '') . '
<li><b>Dnevnik promjena:</b> tko je što i kada promijenio.</li>
<li>Administratori se prijavljuju i kodom iz <b>Google Authenticatora</b>. Rezervne kodove čuvajte na sigurnom.</li>
</ul>');
}

$o('pitanja', '❓ Česta pitanja', '
<dl class="mb-0">
<dt>Upis dezinfekcije kaže da sam predaleko.</dt>
<dd>Uključite lokaciju (GPS), pričekajte nekoliko sekundi na otvorenom i pokušajte ponovno. Upis radi samo na stanici.</dd>
<dt>Na iPhoneu QR kod otvara Safari, a ne aplikaciju.</dt>
<dd>Otvorite aplikaciju i skenirajte u njoj: <b>📷 Skeniraj QR stanice</b>.</dd>
<dt>Nema interneta u lovištu.</dt>
<dd>Upis dezinfekcije radi i bez signala ako ste stanicu prije otvorili s internetom. Šalje se sam kasnije.</dd>
<dt>Ne vidim neki dio programa.</dt>
<dd>Za njega nemate pravo – pogledajte Moje → Moja prava i javite se administratoru udruge.</dd>
<dt>Pogriješio sam u upisu.</dt>
<dd>Radnu akciju koja još nije odobrena možete sami urediti ili obrisati. Upis dezinfekcije može poništiti samo lovočuvar, uz razlog.</dd>
</dl>');

ob_start(); ?>
<h1 class="h3 mb-1">Upute za korištenje</h1>
<p class="text-muted mb-3">Kratke upute za dijelove programa koje koristite. Dodirnite naslov za otvaranje.</p>
<input type="search" class="form-control mb-3 no-print" id="upute-trazi" placeholder="Traži u uputama (npr. lozinka, čeka, uplatnica)…" style="max-width:520px" autocomplete="off">
<div id="upute"><?= implode("\n", $odjeljci) ?></div>
<p class="text-muted small mt-2" id="upute-nema" hidden>Ništa nije pronađeno. Pitajte administratora udruge ili pogledajte <a href="<?= e(url('pomoc')) ?>">Pomoć i kontakt</a>.</p>
<p class="small mt-3">Niste pronašli odgovor? <a href="<?= e(url('pomoc')) ?>">❓ Pomoć i kontakt</a> · verzija <?= e(VERZIJA) ?></p>
<script>
(function () {
    var polje = document.getElementById('upute-trazi'), nema = document.getElementById('upute-nema');
    var dijelovi = Array.prototype.slice.call(document.querySelectorAll('.upute-odjeljak'));
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd'); };
    polje.addEventListener('input', function () {
        var q = norm(polje.value.trim()), ima = 0;
        dijelovi.forEach(function (d) {
            var da = !q || norm(d.textContent).indexOf(q) >= 0;
            d.hidden = !da;
            if (q) d.open = da;
            if (da) ima++;
        });
        if (!q) dijelovi.forEach(function (d) { d.open = false; });
        nema.hidden = ima > 0;
    });
    if (location.hash) { var c = document.querySelector('.upute-odjeljak' + location.hash); if (c) c.open = true; }
})();
</script>
<?php stranica('Upute za korištenje', ob_get_clean());
