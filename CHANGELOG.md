# Promjene

## 1.62.3 – 2026-10
- **Uloge i prava – preglednija tablica:** prava su sada **redovi grupirani po područjima** (Članovi i udruga, Kalendar/obavijesti, Dezinfekcija, Lovište i lov, Inventura/domovi/popusti, Administracija), a **uloge su stupci**.
  Naziv prava (lijevo) i nazivi uloga (gore) **ostaju vidljivi pri pomicanju**, redak i stupac se ističu pod mišem. Kratak opis prava stoji uz naziv.
- Izbornik **„Prikaži“**: samo jedna uloga – pregledno i na mobitelu.
- Nazivi i opisi uloga uređuju se u zasebnom popisu iznad tablice (s brojem članova i brojem prava). Gumb **Spremi** uvijek je vidljiv na dnu.

## 1.62.2 – 2026-10
- **Logo programa doteran:** srnjak prirodnijih proporcija (kratak vrat, duge noge, šesterac), mirnije boje sumraka; noge ne nestaju iza donje grede absehena.

## 1.62.1 – 2026-10
- **Novi logo programa:** absehen (križ nišana) s točkom na plećki srnjaka u sumrak. Mijenja se u izborniku (potpis programa), na prijavi, u *Pomoć* i na GitHubu (banner),
  te zadane ikone aplikacije i favicon – **samo ako udruga nema svoj logo** (logo udruge i dalje je ikona aplikacije).

## 1.62.0 – 2026-10
- **📄 Moji dokumenti** (novi modul, *Sustav → Moduli*; izbornik *Moje → Moji dokumenti*, kartica u *Moj profil*, gumb za početnu):
  član sprema **lovačku iskaznicu, dozvolu za lov, oružni list, osiguranje, lovački ispit** i ostalo – **fotografija prednje i stražnje strane** (kamera mobitela) i/ili **upis** (broj, izdavatelj, vrijedi od – do, napomena).
  Oznaka roka: zeleno vrijedi, žuto ističe u 60 dana, crveno istekao.
- **Privatnost:** dokumente i fotografije vide samo član sam i tko u udruzi smije vidjeti tog člana (*Članovi – pregled* za njegovu sekciju) – na kartici člana gumb **📄 Dokumenti**.
  Mijenja ih član ili tko ima *Članovi – uređivanje*. Fotografije se ne mogu otvoriti izravnom poveznicom bez prava.
- Oružni listovi koje upisuje udruga prikazuju se uz dokumente samo za pregled (bez dvostrukog upisa).

## 1.61.0 – 2026-10
- **🌲 Lovišta** (*Sustav → Postavke modula → Lovišta*, ili *Postavke lovišta → Otvori Lovišta*): udruga može imati **više lovišta** – svako sa svojim podacima:
  naziv, broj lovišta (npr. VII/118), vrsta (otvoreno, zajedničko, državno, ograđeno, uzgajalište), lovoovlaštenik, županija, površina (ha – sama iz granice), boja na karti,
  sekcija (ili sve sekcije), **aktivno / neaktivno** (na karti lovišta samo aktivna), napomena.
- **Granica – tri načina:** 📂 **uvoz KML-a** (Google My Maps), 📍 **točku po točku**, 🖍️ **slobodno olovkom** (vucite prstom – pri puštanju se crta izgladi i granica sama zatvori).
  Zatim ✋ *Pomakni točke* i 🗑 *Briši oblik*. Površina i duljine računaju se odmah.
- **Linije** (〰): putevi, prosjeke, staze za šuljanje, prilazi, potoci … s nazivom, vrstom, bojom i crtkano – istim alatima. Na karti lovišta s nazivom pri dodiru, u izborniku slojeva uključivo/isključivo (*Granice lovišta*, *Putevi i linije*).
- **🔒 Zaključaj kartu:** karta stoji dok crtate (ne pomiče se i ne zumira), ponovni dodir je otključava. Olovka zaključava kartu sama.
- **⬇ KML izvoz** granice i linija (natrag u Google My Maps / Google Earth).
- **Nadogradnja:** dosadašnje granice iz uvezenog KML-a automatski postaju prvo lovište (ništa se ne gubi).
- Uređuje *Sustav* ili tko ima pravo *Lovište – lovne naprave* (za lovišta svojih sekcija).
- Biblioteka za crtanje: Leaflet-Geoman (MIT licenca), uključena u program – radi i bez interneta.

## 1.60.0 – 2026-10
- **📥 Karta bez signala** (Karta lovišta, gumb 📥 ispod ◎): satelitska karta lovišta preuzima se na mobitel (granice lovišta, inače sve naprave, s rubom).
  Odabir detalja (srednje / detaljno / najdetaljnije) s brojem pločica i veličinom, tijek preuzimanja, datum spremanja, brisanje. Preporuka: preuzeti preko Wi-Fi-ja.
- **Rad bez signala na karti:** stranica *Karta lovišta* i zadnje stanje lovnih naprava spremaju se na mobitel – u lovištu bez signala otvara se karta s napravama,
  a **GPS položaj ◎** radi i dalje. Žuta traka „📵 Bez signala“ upozorava da zauzimanje i „Odlazim“ trebaju vezu.
- Spremljena karta ostaje i nakon ažuriranja programa. Kod odjave / promjene korisnika spremljena stranica karte se briše (kao i stanice dezinfekcije).

## 1.59.0 – 2026-10
- **🧹 Reset nakon testiranja** (*Sustav → Podaci*): kad udruga završi isprobavanje, administrator jednim potezom briše probne upise –
  **članovi, korisnici i lozinke, uloge, sekcije, funkcije, psi, vozila, oružni listovi, postavke i šifrarnici** (stanice, lovne naprave, vrste akcija, domovi, inventura) **ostaju**.
  - Odabir po dijelovima s brojem upisa: knjiga dezinfekcije, zauzeća i lovački dnevnik, kvarovi, radne akcije i bodovi, odstrel i plan, skupni lov, kalendar, rezervacije domova, poruke i obavijesti, interni oglasi
    (zadano označeno); članarina i dnevnik promjena samo ako se posebno označe.
  - Potvrda riječju **RESET** i vlastitom lozinkom. **Prije brisanja automatski se radi sigurnosna kopija baze** (vraća se u *Sustav → Sigurnosne kopije*). Brojevi (npr. skupnog lova) kreću ispočetka.

## 1.58.0 – 2026-10
- **🦌 Odstrel** (novi modul, *Sustav → Moduli*, zadano uključen) – izbornik *Lovište → 🦌 Odstrel*:
  - **Upis odstrela:** datum i vrijeme, vrsta (s prijedlozima), spol / kategorija prema vrsti (npr. Vepar, Krmača, Nazime, Prase), dob, **broj markice** (upisuje se), masa, trofej (CIC),
    lovac (član sekcije ili **gost**), lovna naprava s karte ili opis mjesta, oružje, kalibar, udaljenost, napomena, fotografija. *Spremi i upiši još jedan* za više grla.
    Ista markica dvaput u lovnoj godini → upozorenje.
  - **Popis** po lovnoj godini (1.4.–31.3.) i sekciji, filtar po vrsti, traženje po markici ili lovcu, **sortiranje po markici** ili datumu, zbroj po vrstama, **PDF**.
  - **📋 Plan odstrela** po sekciji i lovnoj godini: vrsta ukupno i po kategorijama (npr. Divlja svinja 10, od toga Vepar 3) – **planirano / ostvareno / preostalo**, prekoračenje crveno;
    *Preuzmi iz prošle godine*; **PDF plana**.
  - **Strogo po sekcijama.** Nova prava (*Sustav → Uloge i prava*): **Odstrel – pregled** i **Odstrel – upis** – svaka udruga sama odlučuje tko upisuje (npr. lovnik).
    Nadogradnja ih daje ulogama sa svim pravima i ulogama „Lovnik…“; *pregled* i ulogama s pravom Lovački dnevnik.
  - Član bez tih prava vidi **„Moj odstrel“** – samo svoja grla.
  - Na početnoj se može uključiti gumb **🦌 Odstrel** (*Sustav → Početna stranica*).

## 1.57.0 – 2026-10
- **🌤️ Vrijeme i Mjesec** (novi modul, *Sustav → Moduli*, zadano uključen; treba modul Lovište) – blok **„🌤️ Vrijeme u lovištu“** na početnoj i na vrhu stranice **🌲 Danas u lovištu**:
  - **Svjetlo dana:** zora, izlazak i zalazak Sunca, sumrak, dužina dana – za vaše lovište.
  - **Mjesec:** mijena i osvijetljenost s kratkim savjetom (svijetla / tamna noć).
  - **Temperatura i kiša** (vjerojatnost u sljedećih 6 sati).
  - **🎯 Kad u lov?** – danas i sutra, jutro (zora – 2 h nakon izlaska) i večer (2 h prije zalaska – sumrak): ✅ Dobro / 🤏 Slabije / ❌ Loše s razlogom (vjetar, kiša, pun mjesec, „vedri se nakon kiše“) i preporuka najboljeg termina.
  - Sunce i Mjesec računa mobitel sam – **rade i bez interneta**. Prognoza: Open-Meteo (kao kod vjetra).
  - Za nove instalacije blok je uključen za članove i uloge; ako ste već mijenjali početnu, uključite ga u *Sustav → Početna stranica*.

## 1.56.0 – 2026-10
- **💨 Vjetar** (novi modul, *Sustav → Moduli*, zadano uključen; treba modul Lovište):
  - **Idealni smjerovi vjetra za svaku napravu:** *Karta lovišta → ✏️ Uredi naprave →* naprava *→ ✏️ Uredi* – kompas s 8 strana (S, SI, I, JI, J, JZ, Z, SZ): odakle smije puhati da miris ne ide prema divljači.
  - **Karta lovišta:** gore lijevo trenutni vjetar (smjer, km/h). Naprave s upisanim smjerovima imaju oznaku **✓** (vjetar povoljan) ili **✗** (nepovoljan); u prozorčiću naprave „Vjetar sada iz SI, 12 km/h – povoljan ✓“.
  - **Početna – blok „💨 Vjetar – kompas“:** kompas sa strelicom, Beaufort, udari, temperatura, kamo ide vaš miris, stabilnost smjera, vjetar za sljedećih 12 sati i **čeke za ovaj vjetar** (dodir otvara napravu na karti).
    Za nove instalacije uključen za članove i uloge; ako ste već mijenjali početnu, uključite ga u *Sustav → Početna stranica*.
  - Prognoza: **Open-Meteo** (besplatno, bez registracije). Dohvaća je mobitel izravno za središte lovišta, sprema 15 min; bez interneta pokazuje zadnju prognozu.

## 1.55.0 – 2026-10
- **🎨 Tamna tema:** cijela aplikacija može biti svijetla ili tamna – tamna štedi bateriju i ne zasljepljuje na čeki u sumrak.
  - **⚙️ Sustav → 🎨 Izgled:** zadana tema za sve korisnike – *Svijetla*, *Tamna* ili *Kao uređaj* (prati postavku mobitela/računala). Zadano: svijetla.
  - **Svaki korisnik sam:** gumb **🌙 / ☀️** u gornjoj traci, ili **Moj profil → 🎨 Izgled na ovom uređaju** (Zadano udruge / Svijetla / Tamna / Kao uređaj). Odabir vrijedi na tom uređaju.
  - Ispis je uvijek svijetli; članska iskaznica, QR i potpisi ostaju na bijeloj podlozi.

## 1.54.1 – 2026-10
- **Izbornik – Dezinfekcija:** *Stanice i QR* je postavka i nalazi se samo u **⚙️ Sustav → Postavke modula** (više ne u izborniku lijevo).
  Tko nema pristup Sustavu (npr. lovočuvar s pravom „stanice/QR“), i dalje ga vidi u izborniku Dezinfekcija.

## 1.54.0 – 2026-10
- **Dezinfekcija – lovac iz udruge svojim autom:** kod „+ Lovac iz udruge“ sada se upisuje i **njegova reg. oznaka** (prazno = vaše vozilo, kao prije).
  Lovočuvar/ovlašteni vidi spremljene oznake tog lovca kao gumbe za odabir. U knjizi i PDF-u svaki lovac stoji sa svojim vozilom.

## 1.53.1 – 2026-10
- **Mobitel – gornja traka:** *Odjava* je sada skroz lijevo, dalje od gumba izbornika ☰ – manje slučajnih odjava.

## 1.53.0 – 2026-10
- **Početna – gumbi za sve stavke izbornika:** u *Sustav → Početna stranica* uz postojeće blokove sada se može odabrati gumb za svaku stavku izbornika lijevo
  (Kalendar, Lovački domovi, Moj profil, Moja iskaznica, Imenik, Poruke, Oglasnik, Članovi, Članarina, Radne akcije, Inventura, Knjiga dezinfekcije, Naknadni upis, Sustav, Upute …).
  Gumb se pokazuje samo ako osoba ima to pravo i modul je uključen – kao u izborniku; označeno za ulogu bez prava jednostavno se ne vidi. Zadano nije ništa novo uključeno.

## 1.52.1 – 2026-10
- **Naknadni upis – sken iskaznice:** ispravljena provjera iskaznice (prije je uvijek javljalo „Provjera nije uspjela“).
  Nakon skeniranja sve je ispunjeno: član, **datum i vrijeme skeniranja**, reg. oznaka (jedno vozilo – samo; više vozila – odabir gumbom).
- **„Zašto naknadno?“ više nije obavezno.**

## 1.52.0 – 2026-10
- **Dezinfekcija – naknadni upis:** uz odabir člana gumb **📷 Iskaznica** – skenirate člansku iskaznicu (QR) i član se odabere sam (s reg. oznakom). Odabir s popisa ostaje kao i prije.

## 1.51.1 – 2026-10
- **Kvarovi:** gumbi *Riješeni* i *Svi* (i odabir sekcije) sada rade – prije su uvijek ostajali na *Otvoreni*.

## 1.51.0 – 2026-10
- **Završeni skupni lovovi:** gumb **📝 Dodatni zapisnik** s tekstom (umjesto same ikone) i novi gumb **📧 Pošalji**.
- **📧 Slanje e-poštom – popis i zapisnik zajedno:** odaberete što šaljete (📋 popis sudionika, 📝 dodatni zapisnik) – oba PDF-a u jednoj poruci, primatelji s popisa ili upisana e-adresa.
  Gumb i na završenom lovu i na stranici zapisnika.

## 1.50.0 – 2026-10
- **Broj po sekciji = sekcija voditelja:** lov dobiva broj samo u sekciji onoga tko ga je pokrenuo – i kad je lov za sve sekcije (npr. *0001 (Lovna jedinica Bušetina)*), ne više broj u svakoj sekciji.
- **📝 Dodatni zapisnik – upis u aplikaciji:** nakon lova voditelj upisuje odstrijeljenu divljač (datum, vrsta s prijedlozima, spol, dob, grla/kljunova, napomena – broj markice), može i naknadno; izmjena i brisanje reda.
  **PDF** sadrži upisane redove (prazni redovi na kraju po želji u *Sustav → Skupni lov – PDF*, zadano 0).

## 1.49.0 – 2026-10
- **Broj skupnog lova po sekciji** (*Sustav → Skupni lov – PDF → Broj po sekciji*): svaka sekcija broji svoje lovove u lovnoj godini (0001, 0002 …) – za udruge u kojima svaka sekcija ima svoje dopuštenje i sekcije love istog dana.
  U PDF-u npr. *0003 (Sjever)*; zajednički lov više sekcija dobiva broj u svakoj: *0002 (Sjever), 0002 (Jug)*. Bez rupa nakon brisanja, kao i do sada. Zadano isključeno (jedan niz za cijelu udrugu).

## 1.48.0 – 2026-10
- **Broj skupnog lova:** svaki lov ima redni broj u lovnoj godini (od 1. travnja: 0001, 0002 …). Obrišete li lov, brojevi se slažu bez rupe. Broj se vidi u popisu završenih lovova.
- **Novi PDF popisa sudionika:** naslov *„Popis sudionika skupnog lova po Dopuštenju za lov broj: 0001“*, ispod *„Ovlaštenik prava lova:“* + voditelj koji je pokrenuo lov.
  Bez retka s datumom/vremenom/organizatorom i bez „Ukupno …“. Zadano u tablici samo Rb., Prezime i ime, Broj lovačke iskaznice.
- **📝 Dodatni zapisnik o obavljenom lovu** (nakon završetka lova): isti broj kao lov, prazna tablica za upis – RB, Datum izlučenja, Vrsta divljači, Spol, Dob, Grla / kljunova, Napomena – broj markice.
- **Sustav → 🎯 Skupni lov – PDF:** svaka udruga podešava naslove, stupce popisa (iskaznica, oružni list, upis, potpis), stupce i broj redaka zapisnika te potpis voditelja – s probnim ispisom.

## 1.47.0 – 2026-10
- **Skupni lov iz kalendara – bez dvostrukog poziva:** termin vrste *Skupni lov* na dan lova ima gumb **🎯 Počni skupni lov** (voditelj s pravom Skupni lov).
  - Lov se veže uz termin: naziv, mjesto i sekcije preuzimaju se iz termina.
  - **Novi poziv se ne šalje** – vrijede odgovori iz kalendara (*Dolazim / Ne dolazim*); tko je potvrdio dolazak dobiva samo obavijest „lov je počeo – otvorite kartu“.
  - U terminu za vrijeme lova: **🗺️ Karta lova** i **📋 Popis**.
- **🎯 Skupni lov:** na vrhu „📅 Danas u kalendaru“ s gumbom **Počni lov**; *Pokreni i pošalji poziv* ostaje pod **➕ Novi skupni lov (nije u kalendaru)** za lov koji nije upisan.

## 1.46.3 – 2026-10
- **Sigurnosno upozorenje (PHP2Shell):** administratori na početnoj vide upozorenje ako poslužitelj radi na ranjivoj verziji PHP-a (CVE-2026-109618, CVE-2026-109619) – s objašnjenjem da PHP mora ažurirati hosting i tablicom što privremeno ne radi kad hosting blokira učitavanje datoteka (403). Upozorenje nestaje samo nakon ažuriranja PHP-a.

## 1.46.2 – 2026-10
- **Lovište strogo po sekcijama:** Karta lovišta, Danas u lovištu, Kvarovi i Lovački dnevnik sada gledaju samo uloge koje daju pravo za lovište (nadzor, uređivanje, dnevnik) i njihove sekcije.
  Prije je bilo koja uloga „za sve sekcije“ (npr. Blagajnik) otvarala lovište svih sekcija – lovočuvar Bukovice vidio je i tko sjedi u Bušetini.
- Brisanje zauzeća i obavijesti lovišta također samo u sekcijama gdje uloga daje to pravo.

## 1.46.1 – 2026-10
- **Iskaznice (PDF A4, 10 po stranici):** razmak između iskaznica – 6 mm lijevo/desno i 3,5 mm gore/dolje – lakše rezanje nakon ispisa (prije su se dodirivale).

## 1.46.0 – 2026-10
- **Početna – novi gumbi** (odabir u *Sustav → Početna stranica*):
  - **📢 Pošalji obavijest** – ravno na novu obavijest s push porukom; vidi ga samo tko ima pravo *Obavijesti*,
  - **📢 Obavijesti** – s brojem nepročitanih obavijesti udruge.
- Kod zadanih postavki *Pošalji obavijest* je uključen za sve uloge (vidi ga samo tko ima pravo).

## 1.45.1 – 2026-10
- **Početna – gumb 🌲 Danas u lovištu:** broj na gumbu je sada **koliko ih trenutno sjedi u lovištu** – isti za sve (prije samo nepročitane obavijesti za nadzor, pa ga lovočuvar bez tog prava nije vidio).
  Tko ima pravo *Lovište – nadzor*, ispod vidi i *🔔 n novo*.

## 1.45.0 – 2026-10
- **⚙️ Sustav → 🏠 Početna stranica:** administrator za svaku ulogu (i za članove bez uloge) kvačicama bira što se vidi na početnoj, a strelicama redoslijed.
  Tko ima više uloga, vidi blokove svih svojih uloga. **👁 pregled** – početna kako je vidi odabrana uloga.
- Blokovi: Skeniraj iskaznice, Skeniraj QR, Karta lovišta, Danas u lovištu (gumb ili **popis odmah na početnoj**), Skupni lov, Mobilna stanica, Kvarovi,
  Uprava, Termini, Moje radne akcije, Moja članarina, Brojke, Rođendani, Iskaznice ističu, Prečaci.
- Blok se i dalje pokazuje samo s pravom na te podatke i kad je modul uključen. Zadane postavke su kao dosad (*Vrati zadano*).

## 1.44.0 – 2026-10
- **Kalendar:** vrsta termina „Zajednički lov“ sada se zove **„Skupni lov“** (postojeći termini preimenovani).
- **Lovni gost uz termin lova:** nakon *✔ Dolazim* kvačica **„Vodim lovnog gosta“** (jedan po članu): ime, prezime, broj lovačke iskaznice i oružni list (mogu se dopuniti na lovu), e-pošta.
  - gost dobiva **QR pozivnicu** e-poštom (PDF), ili je član spremi kao sliku i pošalje (Viber/WhatsApp),
  - QR vrijedi **samo za tu pozivnicu i samo na dan termina**; uklanjanjem gosta ili „Ne dolazim“ prestaje vrijediti,
  - voditelj lova u terminu vidi popis gostiju, tko ih je pozvao i koji podaci nedostaju.
- **Popis skupnog lova:** skeniranjem pozivnice gost je odmah na popisu (*gost – pozvao …*); ako nedostaju podaci, upišu se na licu mjesta – i gost se samo potpiše.

## 1.43.0 – 2026-10
- **Skupni lov – voditelj lova automatski je na popisu sudionika** (oznaka *voditelj lova*).
- **PDF popisa e-poštom:** kad lov završi (ručno ili automatski u 23:00), popis se sam šalje voditelju lova; ako nema upisanu e-poštu – administratoru.
  Na stranici popisa novo **📧 Pošalji PDF**: voditelj, organizatori i lovočuvari sekcija lova te administratori (s e-poštom) + bilo koja druga adresa.
- Izbornik *Moje* novim redom: Moje radne akcije · Moj profil · Moja iskaznica · ostalo.

## 1.42.0 – 2026-10
- **Skupni lov – potpis članova na popisu:**
  - član koji ima spremljen potpis (*Moj profil* ili spremljen ranije) – potpis je **automatski** na PDF-u,
  - član bez potpisa – nakon skeniranja iskaznice odmah se otvori polje: **potpiše se prstom** na mobitelu voditelja lova,
  - kvačica *„spremi moj potpis za sljedeći put“* (zadano uključeno) – idući put ne treba ponovno; član bez korisničkog računa ima ga spremljenog kod sebe u evidenciji.
- Potpisivanje se može odgoditi („kasnije“) – na popisu ostaje gumb ✍ kod svakog bez potpisa.
- Članovi → član: vidi se spremljeni potpis i može se obrisati.

## 1.41.0 – 2026-10
- **Skupni lov – ručni upis gosta:** uz ime sada obavezno **broj lovačke iskaznice** i **oružni list**, a gost se **potpiše prstom** na mobitelu voditelja lova.
  Sve troje je na PDF-u popisa (potpis u stupcu *Potpis*). Bez potpisa gost se može dodati samo uz potvrdu.
- Potpis gosta koristi se samo za PDF popisa i briše se zajedno s upisom ili lovom.

## 1.40.0 – 2026-10
- **✍ Osobni potpis:** *Moj profil → ✍ Moj potpis* – svaki funkcionar potpiše se prstom (ili fotografija potpisa) i sam ga može obrisati; nitko drugi ga ne postavlja.
  Potpis se automatski stavlja na PDF-ove gdje je osoba potpisnik:
  - **popis sudionika skupnog lova** – voditelj lova, kad je lov završen,
  - **knjiga dezinfekcije i izjava za inspekciju** – odgovorna osoba sekcije i predsjednik (prepoznaju se po imenu i prezimenu člana).
- Dosadašnji potpisi iz *Dezinfekcija → Postavke* automatski se prenose u profil osobe; tamo se sada vidi samo stanje (✓ ili ⚠ što nedostaje).
- *Moj profil* prikazuje i **moje oružne listove** (upisuje udruga).

## 1.39.0 – 2026-10
- **Oružni listovi kod člana:** Članovi → član → kartica *Podaci* → **Oružni listovi** – neograničeno upisa (broj + oružje, opis nije obavezan).
- **Popis sudionika skupnog lova (PDF):** maknuti stupci *Sekcija* i *Vrijeme*; novi stupci **Broj lovačke iskaznice** i **Oružni list** (svi brojevi člana). Gost je označen uz ime.
- Na dnu PDF-a sada piše ime lovnika / voditelja lova (tko je pokrenuo lov).

## 1.38.0 – 2026-10
- **📷 Mobilna stanica – upis skeniranjem članskih iskaznica** (uz suglasnost lovne inspekcije):
  - lovočuvar nakon aktivacije odabere najviše **5 ovlaštenih** (uključujući sebe – automatski je prvi); šesti nije moguć,
  - ovlašteni skeniraju iskaznice: gore jednom odaberu **⬇ DOLAZAK / ⬆ ODLAZAK**, kamera ostaje otvorena,
  - **vozilo:** jedno → upisuje se samo, više → odabir, nijedno → bez vozila,
  - u knjizi i PDF-u: **„skenirao: ime (iskaznica)“**; upis samo kod stanice (lokacija mobitela koji skenira) i samo dok je stanica aktivna,
  - radi i bez signala (upis čeka na mobitelu), gosti i dalje preko člana; na stalnim stanicama ovog načina nema.
- Ovlašteni dobiju push obavijest i veliki gumb **🪪 Skeniraj iskaznice** na početnoj dok je stanica aktivna.
- Izjava za inspekciju i upute dopunjene.

## 1.37.1 – 2026-10
- **„Grupni lov“ je sada „Skupni lov“** – u izborniku, pozivima, push obavijestima, pravima, modulima, popisu sudionika, PDF-u i uputama.
  Podaci i poveznice ostaju isti.

## 1.37.0 – 2026-10
- **Ista početna za sve:** lovnik, lovočuvar, tajnik, blagajnik i ostali s pravima gore imaju iste velike gumbe kao lovci (📷 Skeniraj QR, 🗺️ Karta lovišta, 🌲 Danas u lovištu),
  ispod je odjeljak **🗂️ Uprava** (termini, brojke, rođendani, iskaznice), a na dnu traka Imenik · Iskaznica · Poruke · Moj profil.
- Glavni admin zadržava dosadašnji pregled uprave.
- Mobitel: dugi naziv uloge u zaglavlju više ne širi stranicu.

## 1.36.2 – 2026-10
- **← Početna** i na stranicama Karta lovišta, Danas u lovištu i Kvarovi na napravama.

## 1.36.1 – 2026-10
- **Ispravak – Imenik:** lovci bez posebnih prava sada vide fotografije drugih članova (prije prazna slika / „slika nije pronađena“).
  Fotografije ostaju vidljive samo prijavljenima, i samo za aktivne članove.
- Upute: Popusti više nisu na početnoj (samo u izborniku).

## 1.36.0 – 2026-10
- **Nova početna za lovce:** na vrhu veliki gumbi za ono što je u lovu važno – **📷 Skeniraj QR (dezinfekcija)**, **🗺️ Karta lovišta** i **🌲 Danas u lovištu**.
- S početne su maknuti *Popusti u trgovinama* i *Oglasnik lovaca* (i dalje su u izborniku lijevo).
- Donja traka prečaca: Imenik · Iskaznica · Poruke (s brojem nepročitanih) · Moj profil. *Vodiči pasa* su samo u izborniku.
- **← Početna** gumb na stranicama Moja iskaznica, Moj profil, Poruke, Imenik i ostalim stranicama člana.

## 1.35.1 – 2026-10
- **Tražilice isključene:** Google, Bing i ostale tražilice više ne prikazuju vašu evidenciju u rezultatima pretrage (ni stranicu za prijavu, ni PDF-ove, slike ili kalendar).
  Samo javna demo verzija ostaje vidljiva. Iznimka po želji: `'dopusti_trazilice' => true` u `config.php`.

## 1.35.0 – 2026-10
- **🔒 Šifrirane sigurnosne kopije:** novo u *Sustav → Sigurnosne kopije* – **lozinka kopije**. Svaka kopija koja se preuzima (kompletna i kopija baze) šifrirana je njome (AES-256 ZIP, otvara se i 7-Zipom).
  Bez lozinke datoteka je neupotrebljiva – i kad netko dođe do nje. Na poslužitelju se lozinka ne čuva. **Zaboravljena lozinka = kopija se ne može otvoriti.**
- Vraćanje kopije (i pri prvom pokretanju) traži lozinku kopije; starije nešifrirane kopije i dalje se mogu vratiti.
- Postavljanje i promjena lozinke kopije traži potvrdu vašom lozinkom za prijavu; sve se bilježi u dnevnik.
- **🔏 Potpisana ažuriranja:** program se ažurira samo na izdanje koje je autor potpisao (Ed25519). Lažni program se odbija – i kad bi netko preuzeo GitHub račun.
  Nepotpisano izdanje se prikazuje, ali se ne instalira dok ga autor ne potpiše.

## 1.34.2 – 2026-10
- **Sigurnija nadogradnja baze:** kad nakon ažuriranja više članova istovremeno otvori aplikaciju, nadogradnju baze sada radi samo jedan – ostali kratko pričekaju.
  Prije se moglo dogoditi da netko dobije stranicu „Došlo je do greške“ (*duplicate column*).
- Ako nadogradnja baze ne uspije, sve se vraća na staro (baza ostaje ispravna na prethodnoj verziji), a u `podaci/greske.log` piše s koje na koju verziju i zašto.

## 1.34.1 – 2026-10
- Popusti u trgovinama u demo verziji (primjeri trgovina, bez Oglasnika lovaca).

## 1.34.0 – 2026-10
- **🏷️ Popusti u trgovinama – zajednički za sve udruge** (kao Oglasnik lovaca): trgovine su na poslužitelju Oglasnika, pa lovci **svih udruga** vide iste popuste.
  Dodir na 🏷️ Popusti otvara Oglasnik bez lozinke (isti ključ udruge), s **vašom članskom iskaznicom** (ime, sekcija, fotografija) – na blagajni pokažete zaslon sa satom koji teče.
- Trgovine upisuje udruga: tko ima pravo **„Popusti u trgovinama – uređivanje“** dobiva **+ Nova trgovina** i bira **tko vidi** – sve udruge ili samo naša. Svoje trgovine uređuje i briše samo udruga; administrator Oglasnika može skriti neprimjerene.
- Radi i kad je Oglasnik u udruzi isključen – udruga samo mora biti **registrirana i odobrena** (Sustav → Oglasnik lovaca). Iskaznica se u Oglasniku čuva samo u sesiji (12 sati), nikad u bazi.
- Lokalni popis trgovina iz 1.33 više se ne koristi.

## 1.33.1 – 2026-10
- **🏷️ Popusti – novo pravo „Popusti u trgovinama – uređivanje“** (Sustav → Uloge i prava, stupac *Popusti trgovine*): trgovine dodaje i uređuje samo tko ima to pravo.
  Ostali članovi / lovci vide samo **važeće ponude** (uključene i neistekle). Glavni admin pravo dobiva automatski; ostalim ulogama ga dodijelite po potrebi.

## 1.33.0 – 2026-10
- **🏷️ Popusti u trgovinama** (novi modul): trgovine koje članovima udruge daju popust – administrator ih dodaje jednu po jednu (**+ Nova trgovina**:
  naziv, popust, uvjeti, način, kontakt osoba, telefon, e-mail, web, adresa, logo, „vrijedi do“).
- Član otvori **Moje → 🏷️ Popusti u trgovinama** (ili karticu na početnoj), dodirne trgovinu i na zaslonu dobije **svoju člansku iskaznicu** s uputom:
  **🛒 pokazati na blagajni** ili **📞 javiti se prodavaču** (poziv, WhatsApp, Viber, e-pošta). Adresa vodi u navigaciju.
- Ispod iskaznice traka **✓ Aktivni član** s datumom i **satom koji teče** – prodavač vidi da to nije snimka zaslona. Popust vrijedi samo za aktivne članove.
- Isključene ili istekle trgovine članovi ne vide (administrator ih vidi sivo). Redoslijed strelicama. Uključuje se u **Sustav → Moduli**; postavke i u Sustav → Postavke modula.

## 1.32.0 – 2026-10
- **✉️ Rođendanska čestitka e-poštom:** na početnoj uz svaki rođendan (sljedećih 14 dana) gumb ✉️ – jednim klikom (uz potvrdu) šalje čestitku članu.
  Kad je pošalje admin, lovočuvar ili bilo tko ovlašten, **svi** koji vide rođendane umjesto gumba vide **✓ Poslano** (tko i kada – zadržite miš / dodir), pa nitko ne šalje dvaput.
  Gumb se prikazuje samo ako član ima e-mail i slanje e-pošte je podešeno. Tekst: Sustav → Predlošci poruka → Rođendanska čestitka (oznaka `{godine}`).

## 1.31.0 – 2026-10
- **🐕 Što pas radi** (umjesto samo „radi krvni trag“): kod svakog psa kvačice – 🩸 potraga (krvni trag), 📯 pogon (gonič), 🐇 lov na sitnu divljač,
  🪶 traženje i markiranje (ptičar), 🌿 pretraživanje i izgon iz šikare, 🦡 rad u jazbini, 🦆 donošenje (aport). Postojeći psi za potragu zadržavaju kvačicu.
- **Bodovi za rad psa:** svaki rad je vlastita vrsta radne akcije („Rad psa: …“) – bodove određuje admin u Sustav → Vrste radnih akcija.
  Član prijavljuje u **Moje radne akcije** uz odabir psa; nude se samo radovi koje njegov pas radi, a pas se bira sam. Odobravanje kao i ostali bodovi.
- **Vodiči pasa:** filtar po radu (npr. samo goniči ili samo psi za jazbinu), oznake radova uz svakog psa.

## 1.30.2 – 2026-10
- **🦌 Logo programa Lovačka Evidencija** (odvojeno od loga udruge): dno izbornika (s verzijom, vodi na Pomoć), ispod prijave, stranica Pomoć i kontakt, banner na GitHubu.
- Udruge **bez vlastitog loga** dobivaju logo programa kao ikonu aplikacije na početnom zaslonu i u kartici preglednika. Logo udruge ostaje gdje je bio (izbornik gore, PDF-ovi, iskaznice, ikona aplikacije).

## 1.30.1 – 2026-10
- **← Sustav** gumb na svim stranicama Sustava (Podaci o udruzi, Korisnici, Uloge, Kopije …) i na postavkama modula otvorenim iz Sustava (vrste radnih akcija, postavke lovišta, stanice dezinfekcije).

## 1.30.0 – 2026-10
- **⚙️ Sustav na jednom mjestu:** u izborniku umjesto duge liste samo **⚙️ Sustav** – otvara pregled u **karticama** po skupinama
  (Udruga · Korisnici i prava · Komunikacija · Postavke modula · Podaci). Broj na čekanju (korisnici, nova verzija) vidi se na kartici i u izborniku.
- **🧩 Moduli** (Sustav → Moduli): udruga uključuje samo ono što koristi – Radne akcije, Članarina, Kalendar, Knjiga dezinfekcije, Lovište, Grupni lov,
  Članske iskaznice, Lovački domovi, Obavijesti, Oglasnik, Inventura, Lovački psi, Statut i pravilnici.
  Isključen modul nestaje iz **izbornika, početne stranice, uputa i push obavijesti** svih korisnika, njegove stranice nisu dostupne, a prava tog modula ne vrijede
  (u Ulogama i pravima stupci se skrivaju – dodijeljena prava ostaju sačuvana). **Podaci se ne brišu** – ponovnim uključivanjem sve je opet tu.
- Zadano je sve uključeno – postojeće instalacije rade kao i prije. Module mijenja samo administrator za sve sekcije; promjena se bilježi u dnevnik.

## 1.29.2 – 2026-10
- **Grupni lov – karta:** u popisu desno samo **tko je u lovu** (dolazi, na karti, SOS) i tko je **📋 na popisu sudionika** (skeniran / ručno upisan).
  Ostali pozvani (bez odgovora, ne dolaze, otišli) su skriveni pod **„Ostali pozvani (N)“** – otvara se dodirom.
- Gumbi **📋 Popis sudionika**, **🏁 Lov završen** i **🚶 Odlazim** sada su **iznad** popisa (ne treba listati do dna kod 60 lovaca); 📞 u istom retku kao ime.
- Brojač **📋 na popisu** uz dolazi / ne dolazi / bez odgovora.

## 1.29.1 – 2026-10
- **🗑 Brisanje završenih grupnih lovova**: gumb uz svaki završeni lov, u detalju lova, i **Obriši sve završene** (s popisima sudionika).
  Briše samo tko upravlja tim lovom; lov u tijeku se ne može obrisati. Prije brisanja program sam napravi kopiju baze.

## 1.29.0 – 2026-10
- **🪪 Članska iskaznica s QR kodom** (format bankovne kartice 85,6 × 54 mm): logo i naziv udruge, fotografija, ime i prezime, sekcija, „član od“ i QR kod – sve na jednoj strani.
  U QR kodu nema imena ni OIB-a, samo slučajni ključ. Iskaznica **vrijedi dok je član aktivan** (arhiviran / obrisan → ne vrijedi), bez godine isteka.
- **Ispis (PDF):** Članovi → član → **🪪 Iskaznica**; svi članovi kartice/sekcije (**🪪 Iskaznice** na popisu) ili odabrani (kvačice → 🪪 Iskaznice). **10 na A4** za običan pisač (izrezati, ispis na 100 %)
  ili **85,6 × 54 mm po stranici** za pisač kartica / PVC kartice.
- **Nova iskaznica** (izgubljena / ukradena): novi ključ, stara odmah prestaje vrijediti – i u aplikaciji.
- **Moje → 🪪 Moja iskaznica**: ista iskaznica u aplikaciji, s **velikim QR-om** za skeniranje; stranica radi i **bez signala** (spremljena na mobitelu, briše se pri odjavi / promjeni korisnika).
- **📋 Grupni lov – popis sudionika**: organizator / Lovnik skenira iskaznice (kamera ostaje otvorena, zvučni signal, fotografija i ime) i **popis se slaže sam** – bez traženja po popisu.
  Radi **bez signala** dok je stranica otvorena: članovi sekcija lova prepoznaju se odmah, upisi čekaju na mobitelu i šalju se sami; iskaznice članova drugih sekcija provjerava poslužitelj kad bude signala.
  Dvostruko skeniranje ne stvara dvostruki upis; nevažeća iskaznica (stara, arhiviran član) se odbija. **✍ Ručni upis** člana ili **gosta**, uklanjanje s popisa, **📄 PDF** (abecedno, rubrika za potpis).
  Više lovnika može skenirati istodobno. Popis ostaje i nakon završetka lova (Završeni grupni lovovi → 📋 Popis).
- Popis je **samo popis** – nije povezan s odgovorima na poziv, bodovima, dezinfekcijom ni ničim drugim.

## 1.28.1 – 2026-10
- **📖 Upute za korištenje** ažurirane na trenutno stanje: zajednički **Oglasnik lovaca** (ulaz bez lozinke, interno/javno, moderacija, pravila za oružje),
  **push obavijesti** – popis vrsta prema vašim pravima (i Lovački domovi), poziv na lov i SOS se ne isključuju; **grupni lov** – upute vidi svaki član lovišta i prije lova (stavka u izborniku i dalje samo dok traje lov);
  radne akcije koje upiše uprava i bodovi opreme iz postavki; Sustav: Oglasnik lovaca, nova prava, push. Nova česta pitanja (push ne stiže, nema Grupnog lova, rezervacija na čekanju).

## 1.28.0 – 2026-10
- **🏡 Lovački domovi** (novo u izborniku): domovi za **cijelu udrugu** ili za **sekciju** (sekcije i ovdje odvojene – dom sekcije vide i rezerviraju samo njezini članovi).
  Naziv, fotografija, opis, kapacitet, tko ima ključ, **položaj na karti** (dodir na kartu, povlačenje pina ili **📍 Moja pozicija**) i gumb **🧭 Navigacija** za dolazak. Karta svih domova s granicama lovišta.
- **Kalendar zauzeća** po domu (mjesec): slobodno / na čekanju / zauzeto / događaj udruge. Dodir na slobodan dan otvara rezervaciju.
- **Rezervacija uvijek za cijele dane**, najviše **12 mjeseci unaprijed**. Dvostruka rezervacija nije moguća (i kod istodobnog slanja).
- **Privatno** (član): zahtjev ide **na čekanje** – upravitelj doma **odobrava / odbija** (s napomenom), član dobiva push obavijest. Dok čeka, isti dani su blokirani.
- **Događaj udruge** (upravitelj): vrijedi odmah i **ima prednost** – privatne rezervacije u tom razdoblju program nabroji i nakon potvrde otkazuje, članovi dobivaju obavijest s razlogom.
- **Privatnost**: ostali vide samo „Zauzeto od – do · ime“, bez razloga; napomenu vidi samo upravitelj doma.
- **Moje rezervacije** s otkazivanjem; upravitelj vidi **zahtjeve na čekanju** (broj u izborniku), **PDF plan zauzeća**.
- Novo pravo **Lovački domovi – upravljanje** (Sustav → Uloge i prava; zadano Glavni admin, Predsjednik / Tajnik, Domar). Dom cijele udruge uređuje samo uloga za sve sekcije.
- Nova vrsta push obavijesti **🏡 Lovački domovi** (može se isključiti).

## 1.27.2 – 2026-10
- **Ispravka:** u Sustav → Uloge i prava nedostajao je stupac **Grupni lov – organizacija** – pravo se sada može dodijeliti svakoj ulozi.

## 1.27.1 – 2026-10
- **SOS u grupnom lovu stiže svima koji su u lovu**: organizatoru, svakome s pravom „Grupni lov – organizacija“ za tu sekciju (npr. **Lovnik na licu mjesta**) i svim sudionicima.
  Svaki sudionik vidi crvenu SOS traku s telefonom i može dodirnuti **🚑 Idem pomoći** – lovac dobiva „Pomoć stiže“, ostali vide tko je krenuo.
- Gubitak / povratak signala stiže svim organizatorima lova (ne samo onome tko ga je pokrenuo).

## 1.27.0 – 2026-10
- **🎯 Grupni lov – karta uživo i SOS**: pokreće ga samo tko ima novo pravo **Grupni lov – organizacija** (Sustav → Uloge i prava; zadano uprava i Lovnik), za sekcije u svom opsegu.
- Svi lovci odabranih sekcija odmah dobivaju **push poziv** (ostaje dok se ne dodirne) i **prozor u aplikaciji**: **✔ Dolazim / ✖ Ne dolazim**.
  Na Androidu gumbi rade izravno iz obavijesti („Ne dolazim“ i bez otvaranja aplikacije), na iPhoneu dodir otvara poziv.
- **Karta lova**: tko dolazi, šalje svoj položaj dok je karta otvorena (zaslon ostaje upaljen); sudionici se vide na karti s granicama lovišta.
  Statusi: uživo · zaslon ugašen · **izgubljen signal** (nema javljanja 3 min – zadnji poznati položaj, obavijest organizatoru i kad se signal vrati).
- **🆘 SOS** (dvostruki dodir): obavijest organizatoru s položajem i telefonom, crvena traka i treptava oznaka na karti; **Preuzimam** → lovac dobiva „Pomoć stiže“.
  Bez signala SOS čeka i šalje se sam; gumbi 112 i telefon organizatora. Traka „Grupni lov u tijeku – Karta / SOS“ na svim stranicama.
- Organizator vidi tko dolazi / ne dolazi / nije odgovorio, telefone i navigaciju do lovca. **🏁 Lov završen** ili automatski u **23:00** – tada se brišu svi položaji
  (ostaje samo tko je sudjelovao), sudionici dobivaju obavijest.
- **Kad nema aktivnog lova, lovci ne vide ništa** – ni stavku u izborniku. Jedan aktivni lov po sekciji.
- Push: obavijesti mogu imati gumbe i vibraciju; SOS i poziv na lov ne mogu se isključiti.

## 1.26.2 – 2026-10
- **Bodove radnih akcija određuju samo postavke**: u Novoj radnoj akciji (grupni unos) osnovni bodovi i bodovi dodatne opreme više se ne mogu upisivati – prikazuju se iz
  Sustav → Vrste radnih akcija (mijenja ih samo admin). Poslužitelj uvijek računa bodove iz postavki, i kad bi netko poslao druge.
- **Odobravanje**: bodove ručno mijenja samo admin (pravo Sustav). Ostali odobravatelji (npr. Domar) odobravaju bodove iz postavki (osnovni + oprema);
  akciju bez vrste („druga akcija“) boduje admin.

## 1.26.1 – 2026-10
- **Ispravka: grupni unos radne akcije** (Udruga → Nova radna akcija, i upis iz kalendara) više se ne odobrava sam – ide **na odobrenje** s predloženim bodovima (osnovni + oprema),
  kao i prijave članova. Bodovi vrijede tek nakon „Odobri“ (Radne akcije → Na čekanju); tko odobrava dobiva push obavijest.
- Na čekanju se vidi tko je akciju upisao. Član vidi predložene bodove („?“) i ne može mijenjati ni brisati akciju koju je upisala uprava.

## 1.26.0 – 2026-10
- **🧰 Dodatna oprema kod radnih akcija**: u Sustav → Vrste radnih akcija uz svaku vrstu upisujete opremu s dodatnim bodovima (npr. **Košnja**: Traktor +5, Trimer +2, Motorna pila +3).
- **Nova radna akcija** (grupni unos): kad vrsta ima opremu, uz svakog označenog člana kvačicama odaberete što je donio (i/ili). Bodovi se zbrajaju **sami** (osnovni + oprema),
  a bodove opreme za tu akciju možete **ručno** promijeniti. Gumb pokazuje ukupne bodove, uz člana njegov zbroj.
- **Moje radne akcije**: član kod prijave označi opremu koju je donio; kod odobravanja se predlaže osnovno + oprema (admin i dalje može promijeniti).
- Oprema se vidi uz akciju (Radne akcije, Moje radne akcije, kartica člana, izvještaj, PDF i CSV). Uz akciju se sprema naziv i bodovi opreme u tom trenutku – kasnija promjena bodova ne mijenja stare akcije.

## 1.25.0 – 2026-10
- **🔔 Push obavijesti na mobitel** (Moje → Push obavijesti): stižu i kad je aplikacija zatvorena – besplatno, preko push servisa preglednika (bez SMS-a, bez dodatne aplikacije).
  Android (Chrome) odmah, iPhone od iOS 16.4 kad je aplikacija na početnom zaslonu. Gumb **Pošalji probnu**, popis vlastitih uređaja.
- Što stiže: **nova poruka** (članovi i oglasnik), **obavijest udruge**, **radna akcija** odobrena / odbijena (član) i nova prijava na odobrenje (tko odobrava),
  **kvar na lovnoj napravi** (lovočuvar, lovne naprave) i **zauzimanje čeka** (nadzor lovišta) – uvijek samo za sekcije u opsegu.
- Svaki korisnik sam isključuje vrste koje ne želi. Vlastite radnje ne šalju obavijest samome sebi.
- Traka „Obavijesti na mobitel – Uključi“ jednom se ponudi u aplikaciji. Odjava isključuje obavijesti na tom uređaju; istekle pretplate brišu se same.
- Tehnički: Web Push s VAPID ključevima koji nastaju sami pri prvoj upotrebi (u bazi, dio sigurnosne kopije); šifriranje aes128gcm samo s PHP OpenSSL – bez novih biblioteka.
  Slanje paralelno nakon odgovora pregledniku. Na hostingu koji blokira odlazne veze push ne radi.

## 1.24.0 – 2026-10
- **🔧 Kvarovi na lovnim napravama**: na karti lovišta dodir na napravu → **Prijavi kvar** (opis, po želji fotografija s mobitela).
  Oznaka **„Nesigurno – ne sjedati“**: naprava dobiva crveni ⚠ na karti, a prije zauzimanja program upozorava.
- Naprave s otvorenim kvarom imaju 🔧 na karti; u prozoru naprave vidi se što je prijavljeno, tko i kada.
- **Lovište → Kvarovi na napravama**: popis za sve lovce sekcije (sekcije i ovdje odvojene) – Otvoreni / Riješeni / Svi, nova prijava i bez karte.
  Lovočuvar ili tko uređuje naprave: **Preuzimam** (svi vide tko radi na tome), **Riješeno** s napomenom, **Ponovno otvori**, **Obriši**.
  Prijavitelj može svoju prijavu označiti riješenom ili je povući dok nije preuzeta.
- Nova prijava, preuzimanje i rješavanje stižu kao obavijest 🔔 (nadzor lovišta); broj otvorenih kvarova u izborniku.
- **PDF popis kvarova** s praznim stupcem „Popravljeno (datum, potpis)“ – za obilazak ili radnu akciju.

## 1.23.0 – 2026-10
- **📖 Upute za korištenje** (izbornik Program): kratke upute za svaki dio programa – prvi koraci, Moje, Kalendar, upis dezinfekcije, Lovište, Imenik/Poruke/Oglasnik, česta pitanja.
  Svatko vidi samo upute za ono što smije koristiti (član ne vidi upute za upravu i Sustav). Pretraga po uputama (radi i bez č/ć/š/ž/đ).

## 1.22.0 – 2026-10
- **📢 Obavijesti s obaveznom potvrdom čitanja** (Članovi → Obavijesti, i na vrhu stranice Poruke).
  Tko ima novo pravo **„Obavijesti – slanje“** piše obavijest **svim članovima**, odabranim **sekcijama**, članovima s određenom **funkcijom** ili **odabranim članovima** – uvijek samo u svom opsegu sekcija.
- **Obavezna potvrda**: članu se obavijest pri sljedećem otvaranju aplikacije prikaže preko cijelog zaslona, dok jednom ne dodirne **„Pročitao/la sam“** (s vremenom potvrde). Bez obavezne potvrde obavijest se samo označi kao pročitana kad je član otvori.
- Pošiljatelj vidi **tko je potvrdio i kada** (npr. 34 / 52), a članove bez aplikacije označi kao **„usmeno obaviješten“** (s datumom i imenom tko je upisao). **PDF s potvrdama** kao dokaz.
- Obavijest ostaje trajno pod Obavijesti; pošiljatelj je može obrisati.
- Novo pravo dobivaju uloge sa svim pravima, „Predsjednik / Tajnik“ i „Admin sekcije“; ostalima se dodjeljuje u Sustav → Uloge i prava.

## 1.21.4 – 2026-10
- **Fotografije se smanjuju već na mobitelu/računalu prije slanja** (fotografija člana i Moj profil 1200 px, radne akcije i oglasi 1600 px, papirnate liste i dokumenti 2400 px) – umjesto 5–15 MB šalje se nekoliko stotina kB, brže i na slabom signalu.
- Zaštita poslužitelja: slika veća od 60 megapiksela se odbija; ako poslužitelj nema GD za smanjivanje, original se sprema samo do 4 MB. Fotografija člana se na poslužitelju i dalje sprema najviše 800 px.

## 1.21.3 – 2026-10
- Početna: bez gumba Inventura (ostaje u izborniku Udruga → Inventura).

## 1.21.2 – 2026-10
- Pomoć i kontakt: podrška razvoju samo gumbom PayPal (bez prikaza adrese).

## 1.21.1 – 2026-10
- Pomoć i kontakt: **☕ Podržite razvoj programa** – dobrovoljna podrška autoru putem PayPala. Sve funkcije i dalje rade bez plaćanja.

## 1.21.0 – 2026-10
- **Inventura po sekcijama**: mape su grupirane – „Cijela udruga“, zatim svaka sekcija – sa zbrojem stavki, manjka i viška po skupini.
  Gumbi **Sve / Cijela udruga / sekcija** prikazuju samo odabranu skupinu (vidljive su samo sekcije u opsegu korisnika).
- **Ispis po izboru**: PDF za popis ili rezultat za sve, za cijelu udrugu, za jednu sekciju (gumb u zaglavlju skupine), za jednu mapu
  ili za **kvačicom odabrane mape**. U zaglavlju PDF-a piše opseg (npr. „Sekcija Jug“).
- Nova mapa predlaže sekciju koja je odabrana; „← natrag“ iz mape vodi u njezinu sekciju; premještanje ▲▼ unutar skupine.

## 1.20.1 – 2026-10
- **Inventura: dva prava** (Sustav → Uloge i prava): **„Inventura – pregled“** (vidi mape, treba/stvarno, manjak/višak, ispisuje PDF – bez izmjena)
  i **„Inventura – uređivanje“** (mape, stavke, upis količina, nova inventura). Tko je do sada imao „Inventura“, zadržava i uređivanje.

## 1.20.0 – 2026-10
- **📦 Inventura** (izbornik Udruga → Inventura i gumb na početnoj): mape (npr. Lovački dom, Hranilišta, Oprema) – svaka za cijelu udrugu ili za jednu sekciju (sekcije i ovdje odvojene).
  U mapi tablica stavki: naziv, jedinica, **Treba** i **Stvarno**; „+ Dodaj stavku“ / „−“ uklanja redak, Enter u zadnjem retku dodaje novi.
- **Razlika** na kraju retka: **crveno manjak** (−), **zeleno višak** (+) ili ✓ kad se slaže; na popisu mapa broj stavki s manjkom / viškom.
- **PDF za popis**: „Treba“ ispunjeno, stupac „Stvarno“ i napomena prazni za upis rukom, potpis i datum – za jednu mapu ili sve. **PDF s rezultatom** s manjkom i viškom.
- „Nova inventura“ isprazni stupac „Stvarno“; „Preuzmi Stvarno kao Treba“ nakon završenog popisa.
- Novo pravo **„Inventura“** – dobivaju ga uloge sa svim pravima i Domar; ostalima se dodjeljuje u Sustav → Uloge i prava.

## 1.19.1 – 2026-10
- Izbornik: ikona uz svaku stavku; „Statut i pravilnici“ na kraju skupine Članovi (ispod oglasa).

## 1.19.0 – 2026-10
- **📜 Statut i pravilnici** (Članovi → Statut i pravilnici): statut, pravilnici, odluke… za sve članove – samo za čitanje.
  Administrator ih uređuje u Sustav → Statut i pravilnici: upisani tekst (podnaslovi s #), PDF i/ili fotografije papira (više odjednom), redoslijed, skrivanje.
- PDF-ovi dokumenata ulaze u kompletnu sigurnosnu kopiju i vraćaju se iz nje.

## 1.18.2 – 2026-10
- Oglasnik lovaca: prijava i zahtjev za registraciju rade i kad Oglasnik stoji na besplatnom hostingu sa zaštitom od botova (podaci idu u #dio adrese, ne obrascem s druge domene).
- Oglasnik se otvara u novoj kartici.

## 1.18.1 – 2026-10
- Oglasnik lovaca: povratne poveznice uvijek preko javne HTTPS adrese (ispravka za Home Assistant add-on iza Tailscale Funnela – „Zahtjev nije poslan“).

## 1.18.0 – 2026-10
- **🛒 Oglasnik lovaca – zajednički oglasnik svih udruga** (Sustav → Oglasnik lovaca).
  Udruga se jednim klikom prijavi u zajednički Oglasnik (zahtjev s ključem udruge, administrator Oglasnika ga odobri).
  Članovi ulaze iz izbornika **Oglasnik** bez posebne lozinke (jednokratna potpisana prijava, Ed25519); privatni ključ ostaje samo u bazi udruge, šifriran.
- Administrator udruge određuje pravila za svoje članove: objava **samo za udrugu** ili **i za sve udruge**, vide li se oglasi drugih udruga,
  smije li se broj telefona prikazati drugim udrugama, javni oglasi odmah ili **nakon odobrenja**.
- Novo pravo **„Oglasnik – moderacija“** (odobravanje javnih oglasa, skrivanje oglasa, prijave). Uloge sa svim pravima ga dobivaju automatski.
- **Zadano isključeno** – dok udruga Oglasnik ne uključi, sve radi kao prije (interni oglasnik udruge). Dosadašnji oglasi ostaju pod „Interni oglasi“.
- Potrebno PHP proširenje **sodium** (uobičajeno uključeno).

## 1.17.0 – 2026-10
- **🐕 Vodiči pasa** (izbornik Članovi i gumb na početnoj, za sve lovce): popis članova s lovačkim psom – pas, pasmina, godina, 🩸 potraga, ispiti.
  Filtar „samo psi za potragu (krvni trag)“, pretraga po imenu/psu/pasmini i sekciji.
  **Kontakt prema dijeljenju člana**: telefon (Nazovi / WhatsApp / Viber) samo ako ga je član sam podijelio u „Moj profil“, inače **💬 Poruka u aplikaciji**.
- Kod svakog psa kvačica „prikaži u popisu Vodiči pasa“ (zadano uključeno) – vlasnik psa može ga sakriti.

## 1.16.4 – 2026-10
- **Lovački pas: broj rodne knjige, boja, dlaka i datum oštenja** (umjesto „rođen“); broj čipa je sada posebno polje. Za boju i dlaku nude se uobičajene vrijednosti, može se upisati i drugo.

## 1.16.3 – 2026-10
- Primjeri u poljima (placeholderi) više ne sadrže nazive jedne udruge – program je neutralan za svaku udrugu.

## 1.16.2 – 2026-10
- **Sustav → Podaci o udruzi: adresa, poštanski broj i mjesto, OIB udruge** (OIB se provjerava). Ispisuju se u zaglavlju knjige dezinfekcije, izjave za inspekciju,
  lovačkog dnevnika i izvještaja, na stranici za inspekciju; adresa je i zadana adresa primatelja na uplatnici.
  Svaka udruga u svojoj instalaciji ima svoj naziv, adresu, logo, stanice, lovište i potpise – ništa od toga nije upisano u program.

## 1.16.1 – 2026-10
- **Oglasnik – jednaka veličina slika**: glavna slika oglasa uvijek je u istom okviru 4:3 (cijela slika vidljiva, prazni rub ispunjen zamućenom slikom) – uspravne i široke fotografije više ne mijenjaju veličinu stranice.
  Na popisu oglasa sve sličice imaju istu veličinu i na starijim preglednicima.

## 1.16.0 – 2026-10
- **Gmail čarobnjak** (Sustav → E-pošta i adresa → „Gmail čarobnjak“): korak po korak – Gmail adresa, poveznica na Googleovu „Potvrdu u 2 koraka“, poveznica na „Lozinke aplikacija“ (otvara se izravno za upisani račun),
  lijepljenje lozinke aplikacije (razmaci se sami brišu), **probna poruka prije spremanja** – postavke se spremaju samo ako slanje uspije.
- **Razumljive greške pri slanju e-pošte**: umjesto „Could not authenticate“ program kaže što je krivo (npr. „Google traži lozinku aplikacije, ne običnu lozinku“, kriva lozinka, nema veze s poslužiteljem).

## 1.15.3 – 2026-10
- Tekst kvačice psa: „radi potragu (krvni trag)“.

## 1.15.2 – 2026-10
- **Lovački pas: kvačica „🩸 radi potragu (krvni trag)“** – za svaku pasminu (npr. njemački oštrodlaki ptičar / Drahthaar). Kod novog psa predlaže se sama prema pasmini, uvijek se može promijeniti.
  Pri prijavi „Potraga s psom“ mogu se odabrati samo psi s tom kvačicom. Postojeći psi je imaju uključenu.

## 1.15.1 – 2026-10
- **Moj profil → Moji lovački psi**: svaki član sam upisuje, uređuje i briše svoje pse i njihove ispite (isto kao na kartici člana). Bodovi i dalje idu na odobrenje.

## 1.15.0 – 2026-10
- **Psi na zajedničkom lovu**: nova oznaka vrste termina „lov sa psima“ (Sustav → Kalendar – vrste; zadano za „Zajednički lov“ i novu vrstu „Grupni lov na sitnu divljač“, postojeće vrste s „lov“ u nazivu označene su automatski).
  Član pri „✔ Dolazim“ kvačicom označi psa (ili više njih) kojeg dovodi – svi na terminu vide tko dolazi s psom i koliko pasa dolazi.
  Dan nakon lova za svakog prijavljenog psa automatski nastaje radna akcija **„Pas na lovu“ na čekanju** (psa koji ipak nije bio – odbiti).
- **Potraga s psom (krvni trag)**: nova vrsta radne akcije koju član sam prijavljuje u „Moje radne akcije“ uz odabir psa.
- **Bodovna lista za pse** (kartica Lovački psi): posjedovanje psa (1.1.), pas na lovu, potraga s psom – bodovi se mijenjaju u Vrste radnih akcija. Sve ide na odobrenje kao ostali bodovi.
- Radne akcije su povezane sa psom: na kartici psa „Bodovi psa“ (povijest), u odobravanju i kod člana uz akciju piše ime psa.

## 1.14.0 – 2026-10
- **Članovi → kartica „Lovački psi“**: jedan ili više pasa po članu – ime, pasmina, spol, datum rođenja, broj čipa/rodovnika, napomena i **ispiti** (naziv, datum, ocjena/nagrada).
  **Godišnji bodovi za psa**: 1. siječnja program za svakog aktivnog psa sam upisuje radnu akciju „Lovački pas“ **na čekanju** – odobrava se kao i ostale radne akcije
  (standardni bodovi u Sustav → Vrste radnih akcija). Za psa dodanog tijekom godine gumb „Upiši bodove za ovu godinu“. Član svoje pse vidi u „Moj profil“.
- **Članovi → Podaci: vozila (registarske oznake)** za knjigu dezinfekcije – pregled, dodavanje, brisanje i zadano vozilo i za članove koji to ne rade sami (pravo „Članovi – uređivanje“ ili „Dezinfekcija – upis“).

## 1.13.0 – 2026-10
- **Uplatnice HUB-3A s 2D kodom za članarinu** – za svaku otvorenu ratu (ili jednokratno plaćanje) prema planu plaćanja. Platitelj (ime i adresa člana) popunjava se sam, iznos je preostali iznos rate.
  - **Sustav → Uplatnica (IBAN)**: primatelj plaćanja (IBAN s provjerom, naziv, adresa), model, poziv na broj i opis (predlošci `{GODINA}`, `{CLAN}`, `{OIB}`, `{RATA}`, `{RATA_OPIS}`), pregled primjera.
  - **Član vidi samo svoju uplatnicu**: gumb „🧾 Moja uplatnica“ na početnoj (i u izborniku Moje). Veliki 2D kod za skeniranje u mobilnom bankarstvu ili pokazivanje na šalteru, gumb za ispis (PDF). Može se isključiti.
  - **Članarina → odabir članova** (pojedinačno, više ili „označi sve“): **Pošalji odabranima** – s e-mailom dobivaju PDF u privitku, ostalima jednim klikom WhatsApp / Viber / SMS s poveznicom; **Ispis odabranih (PDF)** – 3 uplatnice po A4 s linijom za rezanje.
  - Na kartici člana: „🧾 Uplatnica“ i „✉ Pošalji“.
- **Dvofaktorska prijava (Google Authenticator)** – obavezna za administratore (pravo „Sustav“), ostali članovi se i dalje prijavljuju samo lozinkom.
  Postavljanje skeniranjem QR koda, 8 rezervnih kodova za slučaj bez mobitela; drugi administrator može poništiti 2FA (kartica člana → Pristup i uloge).
  Isključivanje u nuždi: `'dvofaktorska' => false` u `config.php`.

## 1.12.3 – 2026-10
- Ispravak: upozorenje „baza je javno dostupna“ više se ne pojavljuje lažno na hostinzima sa zaštitnom stranicom (npr. InfinityFree) – provjerava se stvarni sadržaj baze.

## 1.12.2 – 2026-10
- Priprema za posebne instalacije (proširenja izvan programa); program se za korisnike ne mijenja.

## 1.11.1 – 2026-10
- **Izvještaj radnih akcija → Pošalji e-poštom**: umjesto ručnog upisa adrese **odabir primatelja s popisa** (adresa iz podataka člana).
  Član uvijek dobiva **samo svoje** radne akcije i bodove za odabrano razdoblje – bez obzira na to što je na zaslonu.
  Cijeli izvještaj može se poslati samo upravi: korisniku koji ima pravo „Izvještaji i poruke“ i vidi sve sekcije iz izvještaja (provjerava i poslužitelj).
  Prije slanja potvrda s imenom primatelja i sadržajem; slanje se bilježi u dnevnik promjena.

## 1.11.0 – 2026-10
- **Rad bez signala – nova stranica „📷 Skeniraj QR stanice“** (izbornik, i ispod upisa na stanici). Bez interneta se aplikacija otvara upravo na njoj (umjesto „Nema interneta“):
  skener QR oznake **unutar aplikacije** (kamera), upisi koji čekaju slanje s gumbom „Pošalji sada“ i popis stanica spremljenih na mobitelu.
  Na iPhoneu skeniranje iz aplikacije rješava problem da kamera mobitela otvara Safari (koji ima odvojenu memoriju od aplikacije).
  Prepoznaje i QR oznake ispisane s drugom adresom programa (npr. stalna adresa za QR). Stanica koja nije spremljena → jasna poruka.
- **Slanje u pozadini** (Android / Chrome): upisi spremljeni bez signala šalju se kad se signal vrati i kad je aplikacija zatvorena. iPhone to ne podržava – tamo se šalju čim se aplikacija otvori.
  Isti upis se ne može upisati dvaput (stranica i pozadina šalju jedan po jedan, poslužitelj prepoznaje već poslane).
- **Promjena korisnika na istom mobitelu**: spremljene stranice stanica (ime, vozila) brišu se pri odjavi i kad se prijavi drugi korisnik.
  Upis koji je bez signala spremio jedan korisnik ne može poslati drugi – čeka na mobitelu dok se onaj prvi ne prijavi.
- **Sat mobitela**: upozorenje na stanici i na „Skeniraj“ kad sat mobitela odstupa više od 5 minuta (upisi bez signala koriste sat mobitela);
  odstupanje se bilježi u dnevnik promjena uz izvanmrežni upis.
- Stranice za rad bez signala ponovno se spremaju na mobitel i nakon ažuriranja programa (ne čeka se 6 sati).

## 1.10.0 – 2026-10
- **Ažuriranje programa iz aplikacije** (Sustav → Ažuriranje programa, samo glavni admin): program sam provjerava GitHub (najviše svakih 6 sati) i glavnom adminu pokazuje traku „Dostupna je nova verzija“.
  Jedan klik: **sigurnosna kopija baze** + kopija trenutnog programa → preuzimanje izdanja s GitHuba → zamjena programskih datoteka. `podaci/` i `config.php` se ne diraju, baza se sama proširuje.
  Ako zamjena ne uspije, stari program se automatski vraća. U Home Assistant add-onu ažuriranje ide preko Supervisora (cijeli add-on).
  Isključivanje: `'azuriranje' => false` u `config.php`; drugi izvor: `'azuriranje_repo' => 'korisnik/repo'`.

## 1.9.5 – 2026-10
- Ispravak upozorenja iz 1.9.3: adrese `*.ts.net` (Tailscale Funnel) su javne i više se ne označavaju kao privatne; upozorenje ostaje za LAN / Tailscale IP i kad poveznica vodi na drugu adresu od otvorene.

## 1.9.4 – 2026-10
- **Lovački dnevnik samo uz pravo**: novo pravo „Lovište – lovački dnevnik“ (Sustav → Uloge i prava). Bez njega dnevnik se ne prikazuje ni u izborniku ni na karti lovišta,
  a stranica je zabranjena. Opseg: uloga „sve sekcije“ vidi sve, ostali svoje sekcije. Pri nadogradnji pravo automatski dobivaju uloge sa svim pravima i uloge s „Lovište – nadzor“.

## 1.9.3 – 2026-10
- Ispravak: gumb **„Trenutna adresa“** (Sustav → Postavke → Javna adresa) upisuje adresu kojom je aplikacija stvarno otvorena, a ne staru spremljenu.
- Pozivnica i samoprijava: upozorenje ako poveznica vodi na privatnu adresu (lokalna mreža, Tailscale `*.ts.net`) ili na drugu adresu od one kojom je aplikacija otvorena – s poveznicom na postavku.

## 1.9.2 – 2026-10
- Izjava za inspekciju: pravni temelj za **elektronički oblik** evidencije – čl. 46. Uredbe (EU) br. 910/2014 (eIDAS); Naredba ne propisuje oblik evidencije.

## 1.9.1 – 2026-10
- Knjiga dezinfekcije – PDF i za vrlo velika razdoblja (npr. cijela lovna godina s tisućama upisa): do 1200 upisa jedan PDF, inače automatski
  **ZIP s PDF-om po mjesecima** (puni mjeseci u dijelovima). Vrijedi za upravu, inspekciju i slanje e-poštom. Manja potrošnja memorije pri izradi PDF-a.
- Ispravak: pregled i PDF više nisu ograničeni na 5000 upisa.

## 1.9.0 – 2026-10
- Kompletna kopija (ZIP) sadrži i mapu **PDF-za-inspekciju**: cijela knjiga dezinfekcije (stalne i mobilne stanice) po mjesecima s papirnatim listama i potpisima,
  te izjava udruge – gotovi PDF-ovi za slučaj da program ne radi. Vrlo puni mjeseci dijele se u više PDF-ova.

## 1.8.9 – 2026-10
- Granice lovišta (KML) spremaju se u bazu – sada su u svakoj sigurnosnoj kopiji (dnevnoj i kompletnoj) i vraćaju se na novom poslužitelju. Postojeće granice prebacuju se automatski.

## 1.8.8 – 2026-10
- Dezinfekcija → Stanice i QR: **Početak rada – brisanje probnih upisa** (samo glavni admin, potvrda „OBRIŠI“): briše sve upise dezinfekcije (i poništene), mobilne akcije
  i fotografije papirnatih lista, po želji i probna zauzeća čeka i lovački dnevnik. Stanice, QR, potpisi i postavke ostaju; prije brisanja automatska sigurnosna kopija.
  Izjava za inspekciju navodi datum od kojeg se evidencija vodi elektronički.

## 1.8.7 – 2026-10
- Izjava za inspekciju: bez broja verzije programa (kopija programa = verzija koju udruga koristi); **potpisi automatski** – odgovorne osobe svih sekcija i **predsjednik udruge**
  (ime iz funkcija člana ili ručno, potpis u Dezinfekcija → Stanice i QR). Gumb „Izjava za inspekciju“ u knjizi dezinfekcije.

## 1.8.6 – 2026-10
- Mobilna stanica (skupni lov): pri zatvaranju odgovorna osoba potvrđuje da su svi bez upisanog odlaska prošli **organiziranu dezinfekciju pri odlasku**
  (vozila redom na izlazu) i upisuje tko je dezinfekciju provodio. Za svakoga se upisuje ODLAZAK s tom oznakom – vidljivo u knjizi, PDF-u i za inspekciju; zapisuje se u dnevnik.
  Dolazak i dalje upisuje svatko sam. Izjava za inspekciju to opisuje.

## 1.8.5 – 2026-10
- Inspekcija: **izjava udruge o vođenju evidencije dezinfekcije** (pravni temelj – Naredba NN 107/2026, način upisa, zaštita od naknadnih promjena, provjera programa) jednostavnim jezikom – pregled, ispis i PDF s potpisom odgovorne osobe.
  Pravni temelj se mijenja u Dezinfekcija → Stanice i QR kad izađe nova Naredba.
- Inspekcija: **kopija programa (ZIP)** točno instalirane verzije, bez podataka udruge – za provjeru. Svako preuzimanje zapisuje se u dnevnik.

## 1.8.4 – 2026-10
- Lovački dnevnik: dolazak bez upisanog odlaska prikazuje se samo s vremenom dolaska (bez „?“ i „bez odlaska“).

## 1.8.3 – 2026-10
- Lovački dnevnik: dezinfekcija s razlogom „Lov“ bez zauzete čeke upisuje se kao **Lov – pirš / obilazak (bez čeke)**. Ako se vremenski preklapa sa zauzećem čeke istog lovca, upisuje se samo čeka (ništa dvaput).

## 1.8.2 – 2026-10
- Lovački dnevnik: izvoz u **PDF** umjesto Excela (CSV).

## 1.8.1 – 2026-10
- Lovački dnevnik: uz lov (zauzeće naprava) automatski i **ostali dolasci u lovište iz knjige dezinfekcije** (radna akcija, hranjenje, obilazak…) – od dolaska do odlaska.
  Dezinfekcija s razlogom „Lov“ se ne upisuje dvaput. Novi izbornik **Aktivnost** (Lov, Radna akcija, Hranjenje…), brojač po aktivnosti.

## 1.8.0 – 2026-10
- Novo: **Lovište** – karta lovišta s lovnim napravama, zauzimanje čeka i lovački dnevnik.
  - **Granice lovišta** iz KML/KMZ datoteke (Google My Maps, Google Earth) – Lovište → Postavke lovišta. Ostaju samo na vašem poslužitelju.
  - **Lovne naprave** (broj, naziv, vrsta, sekcija, fotografija, napomena): dodirom na kartu, „📍 Nova na mojoj poziciji“ na licu mjesta ili
    **uvozom više fotografija odjednom** – položaj se čita iz fotografije (GPS u EXIF-u). Pin se može povući; naprave bez položaja postavljaju se kasnije.
  - **Zauzimanje**: dodir na napravu → „Zauzmi – sjedim ovdje“; ostalima je crvena. Vrijedi do „Odlazim“, najkasnije do 03:00 sljedećeg jutra (podesivo).
    Za gosta se zauzima druga naprava („Zauzmi za gosta“) – gost sjedi sam, odgovoran je član. Prelaskom na drugu napravu stara se oslobađa.
  - **Sekcije su strogo odvojene**: član vidi i zauzima samo naprave svoje sekcije; uloge sa „svim sekcijama“ vide sve.
  - **Obavijesti** lovočuvarima sekcije (🔔 u zaglavlju) pri zauzimanju, oslobađanju i brisanju; „Danas u lovištu“ – tko sjedi gdje.
  - **Lovački dnevnik** – automatski: tko, kada, gdje (gosti se ne upisuju); razdoblja, ispis, CSV.
  - Nova prava „Lovište – lovne naprave“ i „Lovište – nadzor“ (brisanje zauzeća, dnevnik sekcije); uloga Lovočuvar ih dobiva automatski.
  - **Vrste naprava sa sličicama** (Postavke lovišta): svaka udruga uređuje svoje vrste i učitava vlastite sličice (više odjednom, naziv = ime datoteke).
    Kod nove naprave vrsta se bira dodirom na sličicu; na karti je sličica u krugu čija boja pokazuje stanje. Vrste koje se ne zauzimaju (kamera, hranilište…) su sive, samo za informaciju.
  - Satelitska, obična i topografska karta (Leaflet, uključen u program).
- Dezinfekcija: QR oznaka za ispis više nema web adresu na dnu (ni u zaglavlju/podnožju preglednika).

## 1.7.3 – 2026-10
- Dezinfekcija: nepreciznost GPS-a uzima se u obzir najviše 1000 m izvan radijusa (prije do 2 km) – dalje od toga upis se odbija.

## 1.7.1 – 2026-10
- Dezinfekcija: **odgovorna osoba i potpis po sekciji**. PDF jedne sekcije nosi potpis te sekcije, PDF svih sekcija potpise svih.

## 1.7.0 – 2026-10
- Dezinfekcija: **potpis odgovorne osobe** – jednom prstom/mišem ili fotografija potpisa (Stanice i QR); automatski na svakom PDF-u evidencije uz napomenu „elektronički generirano“. Kod promjene odgovorne osobe potpis se briše.

## 1.6.0 – 2026-10
- Dezinfekcija: **papirnate liste** za lovce bez mobitela – fotografija liste (više stranica) po stanici i razdoblju, prilaže se knjizi, PDF-u (dodatne stranice), e-pošti i pregledu za inspekciju. Učitava lovočuvar (pravo „naknadni upis“).

## 1.5.0 – 2026-10
- Dezinfekcija: **upis za drugog lovca na stanici** (stariji lovci bez mobitela). Novo pravo „Dezinfekcija – upis za druge na stanici“ (uloga Lovočuvar, dodjeljivo u Uloge i prava).
  Upis se radi na samoj stanici (lokacija mobitela, trenutno vrijeme) i u knjizi izgleda kao upis na licu mjesta, s napomenom tko je upisao. Reg. oznaka se može spremiti u profil tog lovca.

## 1.4.1 – 2026-10
- Knjiga dezinfekcije: glavni admin može **trajno obrisati poništene upise** (npr. probne) – uz „prikaži poništene“, zapisuje se u dnevnik.

## 1.4.0 – 2026-10
- **Mobilna dezinfekcijska stanica** (skupni lov): svaka sekcija ima jednu QR oznaku mobilne stanice koja se koristi za svaki lov.
  Ovlaštena osoba (novo pravo „Dezinfekcija – mobilna stanica“, uloga Lovočuvar) na licu mjesta dodirne „📍 Aktiviraj ovdje“ –
  položaj mobitela postaje središte stanice, vrijedi do zadanog vremena ili zatvaranja. Prikaz uživo: tko je na lovu, tko je otišao.
  Premještanje, produženje, zatvaranje. Mobilne stanice imaju zasebnu knjigu (kartica „Mobilne stanice“), PDF i pregled za inspekciju.
- **Rad bez signala**: stranice stanica se spremaju na mobitel. Bez interneta se upis sprema na mobitelu (vrijeme mobitela + lokacija)
  i šalje automatski čim ima signala; u knjizi je označen „offline“ s vremenom primitka. Odbijeni upisi (predaleko, stanica nije bila aktivna) prikazuju se članu.
- Naknadni upis i za akcije mobilne stanice.

## 1.3.0 – 2026-10
- Novo: **Knjiga dezinfekcije (ASK)** – dolasci i odlasci u lovište preko QR oznake na dezinfekcijskoj stanici.
  - Svaka sekcija ima svoju stanicu (koordinate, radijus, sredstvo); koordinate ručno ili „📍 Ovdje sam“ na samoj stanici.
  - Član skenira QR → provjera lokacije (GPS, radijus podesiv, zadano 100 m) → DOLAZAK / ODLAZAK, razlog, reg. oznaka iz profila,
    do 3 lovca iz udruge i gosti u istom upisu. Vrijeme je uvijek vrijeme poslužitelja. Daleko od stanice upis nije moguć;
    nepouzdana lokacija ili bez lokacije – upis ide, ali je označen.
  - Pregled po sekcijama ili sve, razdoblja (dan, mjesec, godina, lovna godina, od–do), „trenutno u lovištu“, ispis, PDF (A4 položeno), e-pošta.
  - Naknadni upis i poništavanje – uvijek s razlogom, vidljivo u knjizi.
  - **Inspekcija**: vlastita QR oznaka + stalna lozinka (6–8 znakova, Dezinfekcija → Stanice i QR); pregled, ispis, PDF, slanje na svoj e-mail; svaki pristup u dnevniku.
  - Nova prava „Dezinfekcija – pregled / naknadni upis / postavke“ i uloga „Lovočuvar“ (Uloge i prava).
- Moj profil: **moja vozila** (reg. oznake).
- Izbornik: skupine se mogu sklopiti, cijeli izbornik se na računalu može sakriti (☰).

## 1.2.3 – 2026-10
- Novo: **Pomoć i kontakt** u izborniku – prijava greške, prijedlog ili pitanje autoru e-poštom (verzija programa se automatski dodaje), poveznice na GitHub.

## 1.2.2 – 2026-10
- Poruke (članovi i oglasnik): vlastitu poruku možete obrisati (nestaje i kod sugovornika); cijeli razgovor možete obrisati za sebe (sugovornik ga i dalje vidi; kad ga obrišu oba, poruke se trajno brišu).

## 1.2.1 – 2026-10
- Članarina: postojećim zaduženjima bez plana jednim klikom dodijeliti plan plaćanja (npr. nakon nadogradnje).

## 1.2.0 – 2026-10
- **Kalendar** termina: cijela udruga ili odabrane sekcije (ostale sekcije ih ne vide), vrste s bojama (Sustav → Kalendar – vrste),
  „dolazim / ne dolazim“ (nije obavezno), popis dolazaka, navigacija (Google Maps), dodaj u kalendar (.ics), osobna pretplata za kalendar mobitela,
  dijeljenje na WhatsApp/Viber, nakon radne akcije upis akcije jednim klikom za sve koji su potvrdili dolazak. Novo pravo „Kalendar – uređivanje“.
- **Članarina u ratama**: planovi plaćanja po godini (jednokratno ili 1–12 rata s vlastitim iznosom i datumom), plan po članu i prilagodba samo za jednog člana,
  uplate se raspoređuju redom po ratama, filtar „Dospjela rata“, predložak „Podsjetnik – dospjela rata“.
- **Članova početna stranica** (mobitel): termini, radne akcije, oglasnik, članarina u jednoj liniji s diskretnim podsjetnikom kad rata dospije.
- **Imenik** cijele udruge: član sam odlučuje što dijeli (mobitel, fiksni, e-mail, mjesto, adresa); uprava vidi sve; novo pravo „Imenik – vidi sve kontakte“.
- **Poruke među članovima** unutar aplikacije (ne samo u oglasniku).
- **Viber** uz WhatsApp (uključivanje u Sustav → Predlošci poruka).
- **Dodaj na početni zaslon** (Android i iPhone): gumb, upute, vlastita ikona (ili logo udruge).
- Baza: nove tablice se dodaju automatski (PRAGMA user_version); .NET verzija ih zanemaruje.

## 1.1.0 – 2026-10
- **Samoprijava**: jedna zajednička poveznica za sve članove (npr. za WhatsApp grupu). Svatko upiše ime, mobitel, korisničko ime i lozinku;
  administrator u *Sustav → Samoprijava* prijavu poveže s postojećim članom (prijedlozi po imenu/mobitelu) ili upiše novog člana, pa odobri pristup.
  Poveznica se može u svakom trenutku zamijeniti novom ili isključiti. Zaštita: zamka za robote, najviše 5 pokušaja na sat, najviše 30 prijava na čekanju.

## 1.0.0 – 2026-10
Prva PHP verzija (prijenos s ASP.NET / Blazor verzije „Evidencija 2.0“).

- Članovi: sekcije (odvojene), arhiva, trajno brisanje, fotografije, funkcije (s jedinicom), pretraga
- Radne akcije: grupni unos (Domar), prijave članova s fotografijom, odobravanje i bodovi
- Izvještaj: razdoblja (i lovna godina 1.4.–31.3.), grupiranje, sortiranje, ispis, PDF, CSV, e-pošta, izvadak svakom članu
- Članarina: skupno zaduženje, uplate, počasni članovi oslobođeni, prijateljska info članu
- Pristup: pozivnica → član sam postavlja lozinku → administrator odobrava; uloge po sekcijama
- Poruke članu: WhatsApp, SMS, e-pošta, kopiranje; uređivi predlošci
- Oglasnik (kupujem/prodajem) sa slikama i porukama među članovima
- Uvoz iz Google kontakata s prepoznavanjem sličnih imena (č/ć/š/ž/đ), provjera i spajanje duplikata
- Sigurnosne kopije: dnevna kopija baze, kompletna kopija (ZIP), vraćanje
- Baza je ista kao u Windows / Home Assistant verziji – podaci se mogu seliti u oba smjera
