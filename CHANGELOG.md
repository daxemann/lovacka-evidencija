# Promjene

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
