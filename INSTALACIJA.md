# Instalacija – korak po korak

## 1. Što trebate
- webhosting s **PHP 8.1+** (cPanel, Plesk, DirectAdmin… – gotovo svaki hrvatski i njemački hosting)
- FTP pristup (podaci od hostinga) i program **FileZilla** (besplatan)
- (neobavezno) vlastitu domenu ili poddomenu, npr. `evidencija.lu-primjer.hr`, i HTTPS (Let's Encrypt – na većini hostinga jedan klik)

Besplatni webspace često **nema** potrebna PHP proširenja ili ne dopušta slanje e-pošte – za udrugu je bolji mali plaćeni paket (nekoliko eura mjesečno).

## 2. Učitavanje
1. Preuzmite `lovacka-evidencija-vX.Y.Z.zip` (Releases na GitHubu) i raspakirajte na računalu.
2. FileZilla → spojite se na hosting → otvorite `public_html` (ili `htdocs`, `www`).
3. Napravite mapu, npr. `evidencija`, i u nju povucite **sve** datoteke i mape iz raspakiranog ZIP-a (`index.php`, `app`, `assets`, `podaci`, `vendor`, `.htaccess` …).
   - u FileZilli uključite prikaz skrivenih datoteka (*Poslužitelj → Prikaži skrivene datoteke*) da se vidi `.htaccess`
4. Desni klik na mapu `podaci` → *Prava datoteke* → 775 (ako hosting tako traži; inače ostavite).

## 3. Prvo pokretanje
Otvorite `https://vasa-domena.hr/evidencija/`:
- **Puni naziv udruge**, **kratki naziv** (izbornik), **sekcije / lovne jedinice** (jedna po retku)
- **glavni administrator**: ime, korisničko ime, lozinka (najmanje 8 znakova, slova i brojke)

Selite postojeću evidenciju (Windows / Home Assistant / drugi poslužitelj)? Na istoj stranici odaberite **Vrati iz kompletne kopije** i učitajte ZIP.

## 4. Postavke nakon prijave
- *Sustav → Podaci o udruzi*: logo (PNG), podnaslov
- *Sustav → E-pošta i adresa*: javna adresa (npr. `https://vasa-domena.hr/evidencija/`) i SMTP za e-poštu
  - podatke za SMTP daje hosting (npr. `mail.vasa-domena.hr`, port 587) ili koristite Gmail (lozinka aplikacije)
  - „Pošalji probnu poruku“ provjeri da sve radi
- *Sustav → Uvoz (Google kontakti)*: contacts.google.com → oznaka → Izvezi → Google CSV
- *Sustav → Uloge i prava*, *Funkcije*, *Vrste radnih akcija* – po želji

## 5. Članovi dobivaju pristup
Članovi → član → **Pristup i uloge** → *Kreiraj pozivnicu* → pošaljite WhatsAppom/SMS-om/e-poštom.
Član otvori poveznicu, postavi lozinku, vi kliknete **Odobri pristup**. Gotovo.
Na mobitelu: u pregledniku *Dodaj na početni zaslon* – izgleda kao aplikacija.

## 6. Sigurnosne kopije
- automatski jednom dnevno kopija baze u `podaci/kopije` (zadnjih 30)
- **redovito** (npr. jednom mjesečno) *Sustav → Sigurnosne kopije → Preuzmi kompletnu kopiju (ZIP)* i spremite na sigurno mjesto – ZIP sadrži osobne podatke, čuvajte ga povjerljivo

## 7. Nadogradnja na novu verziju
1. Napravite kompletnu kopiju (ZIP).
2. Učitajte nove datoteke preko starih – **osim mape `podaci`** (i vlastitog `config.php`).
3. Gotovo – podaci ostaju.

## Problemi
| Poruka | Rješenje |
|---|---|
| „Potreban je PHP 8.1 ili noviji“ | u upravljačkoj ploči hostinga odaberite noviju verziju PHP-a |
| „Nedostaje PHP proširenje: pdo_sqlite“ | uključite ga u postavkama PHP-a ili pitajte podršku hostinga |
| „Došlo je do greške“ | pogledajte `podaci/greske.log` (preko FTP-a) |
| Fotografija se ne učitava | prevelika datoteka – hosting ograničava `upload_max_filesize` |
| Crveno upozorenje „baza je javno dostupna“ | poslužitelj je nginx – vidi README, odjeljak „Zaštita podataka“ |
