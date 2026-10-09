# Promjene

## 1.7.2 – 2026-10
- Dezinfekcija: nepreciznost GPS-a uzima se u obzir najviše 150 m izvan radijusa (prije do 2 km) – dalje od toga upis se odbija.

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
