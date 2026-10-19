<?php
/** Upute za korištenje – kratka pomoć po dijelovima programa; svatko vidi samo ono što smije koristiti. */
$k = korisnik();
$revir = modul('revir') && revir_sekcije();
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
Bodovi vrijede tek kad akciju odobri uprava (do tada uz bodove stoji „?“). Dok nije odobrena, svoju prijavu možete urediti ili obrisati. Gore vidite bodove u lovnoj godini (1.4.–31.3.).</li>
<li>Akcije koje je za vas upisala uprava (npr. zajednička košnja) vidite na istom popisu – njih ne možete mijenjati ni brisati.</li>
<li><b>Dodatna oprema:</b> kod nekih vrsta (npr. Košnja) kvačicom označite što ste donijeli – traktor, trimer, motorna pila… Svaka oprema donosi dodatne bodove – koliko, određuje uprava u postavkama.</li>
<li><b>Rad psa</b> (potraga – krvni trag, pogon, sitna divljač, ptičarenje, izgon iz šikare, jazbina, donošenje) prijavljuje se u Moje radne akcije uz odabir psa – nude se samo radovi koje vaš pas radi (Moj profil → Što pas radi). Bodove određuje uprava.</li>
<li><b>Moj profil:</b> fotografija, kontakt, <b>što drugi vide u Imeniku</b> (mobitel, e-mail, adresa – vi odlučujete), <b>moja vozila</b> (reg. oznake za dezinfekciju) i <b>moji lovački psi</b> s ispitima.</li>'
    . (uplatnica_za_clanove() ? '
<li><b>Moja uplatnica:</b> uplatnica za članarinu s 2D kodom. Kod skenirajte u mobilnom bankarstvu ili ga pokažite na šalteru; može se i ispisati (PDF). Vidite samo svoju.</li>' : '') . '
<li><b>Moja prava:</b> popis onoga što smijete u programu.</li>
</ul>');
}

if (modul('kalendar')) $o('kalendar', '📅 Kalendar', '
<ul>
<li>Termini udruge ili vaše sekcije (lovovi, radne akcije, sastanci…). Dodirom na termin vidite detalje i navigaciju do mjesta.</li>
<li><b>✔ Dolazim / Ne dolazim</b> – nije obavezno, ali pomaže organizatoru. Kod lova sa psima kvačicom označite psa kojeg dovodite.</li>
<li>Termine možete dodati u kalendar mobitela (pojedinačno ili stalnom <b>pretplatom</b> – novi termini se tada pojavljuju sami).</li>'
    . (ima(P_KALENDAR) ? '
<li><i>Uređivanje:</i> novi termin za cijelu udrugu ili odabrane sekcije (ostali ga ne vide). Nakon radne akcije jednim klikom upišete akciju svima koji su potvrdili dolazak.</li>' : '') . '
</ul>');

if (modul('domovi') && (dom_ima_vidljivih() || ima(P_DOMOVI))) {
    $o('domovi', '🏡 Lovački domovi – rezervacija', '
<ul>
<li><b>🏡 Lovački domovi</b> pokazuje domove cijele udruge i vaše sekcije – fotografija, kapacitet, tko ima ključ i <b>🧭 Navigacija</b> do doma.</li>
<li>Kalendar doma: zeleno = slobodno, žuto = na čekanju, crveno = zauzeto, plavo = događaj udruge. Ostali vide samo „zauzeto“ i ime – razlog vidi samo upravitelj doma.</li>
<li><b>Rezervacija:</b> dodirnite slobodan dan (ili upišite od – do). Uvijek cijeli dan(i), najviše ' . DOM_MAX_MJESECI . ' mjeseci unaprijed. Zahtjev odobrava upravitelj doma – stiže vam obavijest.</li>
<li>Isti dani ne mogu se rezervirati dvaput. <b>Događaj udruge ima prednost</b>: ako uprava u vašem terminu zakaže događaj, vaša rezervacija se otkazuje i dobivate obavijest.</li>
<li>Svoju rezervaciju otkazujete u <b>Moje rezervacije</b> ili u popisu ispod kalendara.</li>'
    . (ima(P_DOMOVI) ? '
<li><i>Upravitelj doma:</i> <b>➕ Novi lovački dom</b> (za cijelu udrugu ili sekciju, položaj dodirom na kartu ili <b>📍 Moja pozicija</b>). Zahtjevi na čekanju: <b>Odobri / Odbij</b> s napomenom.
<b>🏛️ Događaj udruge</b> vrijedi odmah; ako se preklapa s privatnim rezervacijama, program ih nabroji i otkazuje tek nakon potvrde. PDF plan zauzeća.</li>' : '') . '
</ul>');
}

if (modul('dezinfekcija')) $o('dezinfekcija-upis', '🧴 Dezinfekcija – upis na stanici', '
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

if (modul('grupni') && (gl_vidljivo() || ($k['ClanId'] && $revir))) {
    $o('grupni-lov', '🎯 Grupni lov – karta uživo i SOS', '
<ul>
<li><b>Stavka 🎯 Grupni lov pojavljuje se u izborniku samo dok traje lov</b> u koji ste pozvani – inače je nema.</li>
<li>Kad organizator pokrene grupni lov, stiže <b>poziv na mobitel</b> i prozor u aplikaciji: <b>✔ Dolazim</b> ili <b>✖ Ne dolazim</b>. Na Androidu se može odgovoriti izravno iz obavijesti.</li>
<li>Tko dolazi, otvara <b>🎯 Grupni lov</b> – karta pokazuje sudionike lova, a vaš položaj vidi organizator.</li>
<li><b>Položaj se šalje samo dok je karta otvorena</b> (zaslon ostaje upaljen). Ugasite li zaslon, organizator vidi „zaslon ugašen“ i vaš zadnji položaj.</li>
<li><b>🆘 SOS</b> dolje na karti – dodirnite <b>dvaput</b> (da ne ode slučajno iz džepa). Obavijest s vašim položajem odmah dobivaju organizator, Lovnik i <b>svi koji su u lovu</b>; tko krene (<b>🚑 Idem pomoći</b>), vama javlja „Pomoć stiže“, a ostalima tko je krenuo. Bez signala SOS čeka i šalje se sam čim se veza vrati – u nuždi nazovite <b>112</b>.</li>
<li>Odlazite ranije? <b>🚶 Odlazim</b> – položaj se više ne šalje.</li>
<li>Lov završava organizator (<b>🏁 Lov završen</b>), inače sam u ' . GL_SAT_KRAJA . ':00. Tada se <b>brišu svi položaji</b>, a stavka nestaje iz izbornika.</li>'
    . (ima(P_GRUPNI_LOV) ? '
<li><i>Organizator:</i> <b>🎯 Grupni lov → Pokreni</b> (naziv, mjesto okupljanja, napomena, sekcije). Vidite tko dolazi, tko ne i tko nije odgovorio,
sve sudionike na karti, <b>📡 gubitak signala</b> (nema javljanja ' . GL_SIGNAL_MIN . ' min – sivo, zadnji poznati položaj) i <b>🆘 SOS</b> s gumbom <b>🚑 Idem pomoći</b> i telefonom.
SOS stiže organizatorima (tko je pokrenuo lov i svi s pravom Grupni lov za tu sekciju, npr. Lovnik) i svim sudionicima; gubitak signala samo organizatorima. Za istu sekciju istodobno može biti samo jedan lov. Završeni lovovi: popis tko je sudjelovao (bez položaja).</li>
<li><i>Organizator / Lovnik:</i> <b>📋 Popis sudionika – skeniraj iskaznice</b>: kamera ostaje otvorena, skenirate člansku iskaznicu (plastičnu ili 🪪 u aplikaciji) jednu za drugom – zvučni signal, fotografija i ime, popis se slaže sam.
Radi i <b>bez signala</b> dok je stranica otvorena (otvorite je prije s internetom) – upisi čekaju na mobitelu i šalju se sami. Bez iskaznice ili gost: <b>✍ Ručni upis</b>. <b>📄 PDF</b> s rubrikom za potpis.
Popis je samo popis – ne mijenja odgovore na poziv, bodove ni dezinfekciju.</li>' : '') . '
</ul>');
}
if ($k['ClanId'] && modul('iskaznice')) {
    $o('iskaznica', '🪪 Članska iskaznica (QR)', '
<ul>
<li><b>Moje → 🪪 Moja iskaznica</b>: iskaznica s fotografijom i QR kodom. Kod grupnog lova lovnik je skenira i upisuje vas u popis sudionika – ništa drugo.</li>
<li>U QR kodu nema vaših podataka (samo slučajni ključ). Stranica radi i bez signala ako ste je jednom otvorili s internetom. <b>🔍 Veliki QR</b> se lakše skenira.</li>
<li>Plastičnu / papirnatu iskaznicu ispisuje uprava (<i>Članovi → član → 🪪 Iskaznica</i>). Vrijedi dok ste aktivan član; izgubljenu uprava zamjenjuje novom – stara tada više ne vrijedi.</li>
</ul>');
}

if ($k['ClanId'] && modul('popusti')) {
    $o('popusti', '🏷️ Popusti u trgovinama', '
<ul>
<li><b>Moje → 🏷️ Popusti u trgovinama</b> (i na početnoj): trgovine koje članovima udruge daju popust.</li>
<li>Dodirnite trgovinu – na zaslonu se otvara <b>vaša članska iskaznica</b> i piše što učiniti: <b>pokazati zaslon na blagajni</b> prije plaćanja ili <b>javiti se prodavaču</b> (gumbi za poziv, WhatsApp, Viber, e-poštu).</li>
<li>Ispod iskaznice je zelena traka <b>✓ Aktivni član</b> s datumom i satom koji teče – prodavač tako vidi da to nije stara snimka zaslona.</li>
<li>Popust vrijedi samo dok ste aktivan član.</li>'
    . (pop_smije_uredjivati() ? '
<li><i>Administrator:</i> trgovine dodajete jednu po jednu – <b>+ Nova trgovina</b> (naziv, popust, uvjeti, način, kontakt, logo, „vrijedi do“). Isključene i istekle članovi ne vide. Modul se uključuje u Sustav → Moduli.</li>' : '') . '
</ul>');
}

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

$pushVrste = ['poruke', 'obavijesti'];
if (modul('akcije') && ($k['ClanId'] || ima(P_AKCIJE_ODOBRI))) $pushVrste[] = 'akcije';
if ($revir && (ima(P_REVIR_NADZOR) || ima(P_REVIR_UREDI))) $pushVrste[] = 'kvarovi';
if (modul('domovi') && (dom_ima_vidljivih() || ima(P_DOMOVI))) $pushVrste[] = 'domovi';
if ($revir && ima(P_REVIR_NADZOR)) $pushVrste[] = 'loviste';
$pushPopis = '';
foreach ($pushVrste as $v) {
    if (isset(PUSH_VRSTE[$v])) $pushPopis .= '<li><b>' . e(PUSH_VRSTE[$v][0]) . '</b> – ' . e(PUSH_VRSTE[$v][1]) . '</li>';
}
$o('push', '🔔 Push obavijesti na mobitel', '
<ul>
<li>Obavijest stiže na mobitel i kad je aplikacija <b>zatvorena</b>. Za vas mogu stizati:<ul class="mb-1">' . $pushPopis . '</ul></li>
<li><b>🎯 Poziv na grupni lov i 🆘 SOS</b> stižu uvijek i ne mogu se isključiti.</li>
<li>Uključite je u izborniku <b>' . ($k['ClanId'] ? 'Moje' : 'Program') . ' → Push obavijesti</b> → <b>Uključi obavijesti</b> i dopustite obavijesti kad mobitel pita. Gumb <b>Pošalji probnu</b> provjerava da sve radi.</li>
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
' . (ogl_aktivan() ? '
<li><b>🛒 Oglasnik lovaca</b> – zajednički oglasnik lovaca iz više udruga. Otvara se u novoj kartici, <b>bez posebne lozinke</b> (ulazite preko ovog programa).
Kod prvog otvaranja prihvatite pravila. Kod oglasa birate vide li ga samo članovi udruge ili svi lovci. Najviše 3 slike, oglas traje 30 dana (može se produžiti).
Kontakt s lovcima iz drugih udruga samo porukom ili brojem koji ste sami podijelili. Sumnjiv oglas prijavite gumbom u oglasu.</li>'
    . (vrijednost('SELECT 1 FROM Oglasi WHERE Status IN (0,1) LIMIT 1') ? '
<li><b>📌 Interni oglasi:</b> stari oglasi iz programa udruge – ostaju za pregled.</li>' : '')
    . (ima(P_OGLASNIK_MOD) ? '
<li><i>Moderacija:</i> odobravate javne oglase članova, skrivate neprimjerene oglase i obrađujete prijave (rok 48 sati, inače prijava ide administratoru Oglasnika).</li>' : '') : '
<li><b>Oglasnik:</b> kupujem / prodajem (oružje, optika, pribor, psi…) sa slikama i porukama prodavatelju.</li>') . '
<li>Oružje i streljivo smiju se oglašavati, ali prodaja samo prema zakonu (kupac s valjanom dozvolom); u oglas ne upisujte serijske brojeve ni brojeve dozvola.</li>
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
        . '<li><b>Dodatna oprema</b> (Sustav → Vrste radnih akcija): uz vrstu upišite opremu s bodovima (npr. Košnja: Traktor +5, Trimer +2). Kod nove akcije uz svakog člana kvačicom označite što je donio – bodovi se zbrajaju sami iz postavki i u akciji se ne mijenjaju. Kod odobravanja bodove ručno mijenja samo admin; ostali odobravaju bodove iz postavki.</li>';
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
<li>Sve postavke su na jednom mjestu: <b>⚙️ Sustav</b> u izborniku otvara pregled u karticama.</li>
<li><b>🧩 Moduli:</b> uključite samo ono što udruga koristi (radne akcije, članarina, kalendar, dezinfekcija, lovište, grupni lov, iskaznice, domovi, obavijesti, oglasnik, inventura, psi, dokumenti). Isključeno nestaje iz izbornika svih korisnika, podaci ostaju sačuvani.</li>
<li><b>Podaci o udruzi:</b> naziv, adresa, OIB, logo – ispisuju se na PDF-ovima.</li>
<li><b>E-pošta i adresa:</b> slanje e-pošte (najlakše <b>Gmail čarobnjak</b>) i javna adresa programa za pozivnice i QR oznake.</li>
<li><b>Korisnici / Samoprijava:</b> odobravanje pristupa (broj na čekanju u izborniku), poveznica za samoprijavu (npr. za WhatsApp grupu).</li>
<li><b>Uloge i prava:</b> što koja uloga smije i za koje sekcije – među ostalim <b>Grupni lov – organizacija</b> (npr. Lovnik), <b>Lovački domovi – upravljanje</b> (npr. Domar) i <b>Oglasnik – moderacija</b>.</li>
<li><b>Push obavijesti</b> ne trebaju podešavanje (ključevi nastaju sami i dio su sigurnosne kopije). Na hostingu koji blokira odlazne veze push ne radi.</li>
<li><b>Sekcije, vrste radnih akcija</b> (osnovni bodovi i bodovi dodatne opreme – samo ovdje), <b>predlošci poruka, vrste u kalendaru, uplatnica (IBAN).</b></li>
<li><b>Oglasnik lovaca:</b> udruga se jednim klikom prijavi u zajednički Oglasnik (zahtjev odobrava administrator Oglasnika), zatim ga uključite za članove i odredite pravila (samo interno ili i javno, vidljivost telefona, odobravanje javnih oglasa). Dok nije uključen, radi interni oglasnik udruge.</li>
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
<dt>Push obavijesti ne stižu.</dt>
<dd>Na stranici Push obavijesti dodirnite <b>Pošalji probnu</b>. Na iPhoneu obavijesti rade samo iz aplikacije na početnom zaslonu (iOS 16.4+). Provjerite i da obavijesti za preglednik / aplikaciju nisu isključene u postavkama mobitela.</dd>
<dt>Ne vidim Grupni lov u izborniku.</dt>
<dd>Stavka se pojavljuje samo dok traje lov u koji ste pozvani.</dd>
<dt>Moja rezervacija doma piše „na čekanju“.</dt>
<dd>Privatnu rezervaciju mora odobriti upravitelj doma – dobit ćete obavijest. Dok čeka, ti dani su za druge blokirani.</dd>
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
