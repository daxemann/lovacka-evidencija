<?php
/**
 * Lovačka evidencija – jezgra (konfiguracija, baza, pomoćne funkcije).
 * Učitava se iz index.php; sve ostalo ide preko ove datoteke.
 */
declare(strict_types=1);

const VERZIJA = '1.62.3';
const KONTAKT_EMAIL = 'daxemann@googlemail.com';
const PODRSKA_PAYPAL = 'darko.bakic@freenet.de'; // dobrovoljna podrška razvoju (Pomoć i kontakt)
const PROJEKT_URL = 'https://github.com/daxemann/lovacka-evidencija';
const KORIJEN = __DIR__ . '/..';

// ---------- Prava (bit-zastavice, iste kao u .NET verziji) ----------
const P_CLANOVI_CITAJ = 1;
const P_CLANOVI_UREDI = 2;
const P_CLANARINA_CITAJ = 4;
const P_CLANARINA_UREDI = 8;
const P_AKCIJE_CITAJ = 16;
const P_AKCIJE_ODOBRI = 32;
const P_IZVJESTAJI = 64;
const P_SUSTAV = 128;
const P_KALENDAR = 256;
const P_IMENIK_SVI = 512;
const P_DEZ_PREGLED = 1024;
const P_DEZ_UREDI = 2048;
const P_DEZ_POSTAVKE = 4096;
const P_DEZ_MOBILNA = 8192;
const P_DEZ_ZA_DRUGE = 16384;
const P_REVIR_UREDI = 32768;
const P_REVIR_NADZOR = 65536;
const P_REVIR_DNEVNIK = 131072;
const P_OGLASNIK_MOD = 262144;
const P_INVENTURA = 524288;
const P_INVENTURA_UREDI = 1048576;
const P_OBAVIJESTI = 2097152;
const P_GRUPNI_LOV = 4194304;
const P_DOMOVI = 8388608;
const P_POPUSTI = 16777216;
const P_ODSTREL_CITAJ = 33554432;
const P_ODSTREL = 67108864;
const P_OSNOVNA = 255; // prava iz .NET verzije
const P_SVE = 134217727;

const PRAVA_OPIS = [
    P_CLANOVI_CITAJ => ['Članovi – pregled', 'vidi popis članova i njihove podatke'],
    P_CLANOVI_UREDI => ['Članovi – uređivanje', 'dodaje i uređuje članove, sekcije članova, funkcije, fotografije, arhivira'],
    P_CLANARINA_CITAJ => ['Članarina – pregled', 'vidi članarinu i uplate'],
    P_CLANARINA_UREDI => ['Članarina – uređivanje', 'zadužuje članarinu i upisuje uplate'],
    P_AKCIJE_CITAJ => ['Radne akcije – pregled', 'vidi radne akcije svih članova'],
    P_AKCIJE_ODOBRI => ['Radne akcije – unos i odobravanje', 'upisuje radne akcije i bodove (i za grupu), odobrava i odbija prijave članova'],
    P_IZVJESTAJI => ['Izvještaji i poruke', 'izvještaji, ispis, PDF, Excel, slanje poruka'],
    P_SUSTAV => ['Sustav', 'pristup članova (pozivnice, odobrenje, uloge), sekcije, uloge, uvoz, duplikati, sigurnosne kopije, dnevnik'],
    P_KALENDAR => ['Kalendar – uređivanje', 'dodaje, mijenja i briše termine u kalendaru (za sekcije u svom opsegu)'],
    P_IMENIK_SVI => ['Imenik – vidi sve kontakte', 'u imeniku vidi telefon, e-mail i adresu svih članova, i kad ih član nije podijelio'],
    P_DEZ_PREGLED => ['Dezinfekcija – pregled', 'vidi knjigu dezinfekcije (dolasci i odlasci) za stanice svojih sekcija, ispis, PDF, slanje e-poštom'],
    P_DEZ_UREDI => ['Dezinfekcija – naknadni upis', 'naknadno upisuje i poništava upise (uvijek s razlogom, vidljivo u knjizi)'],
    P_DEZ_POSTAVKE => ['Dezinfekcija – postavke', 'stanice (koordinate, radijus, sredstvo), QR oznake, razlozi dolaska, lozinka za inspekciju'],
    P_DEZ_ZA_DRUGE => ['Dezinfekcija – upis za druge na stanici', 'na samoj stanici (lokacija mobitela, trenutno vrijeme) upisuje lovca koji nema mobitel – izgleda kao upis na licu mjesta, s napomenom tko je upisao'],
    P_DEZ_MOBILNA => ['Dezinfekcija – mobilna stanica', 'aktivira mobilnu dezinfekcijsku stanicu na licu mjesta (npr. skupni lov) i prati tko je došao i otišao'],
    P_REVIR_UREDI => ['Lovište – lovne naprave', 'dodaje, premješta i uređuje lovne naprave (čeke) na karti lovišta za sekcije u svom opsegu'],
    P_REVIR_NADZOR => ['Lovište – nadzor', 'prima obavijesti o zauzimanju lovnih naprava u svojim sekcijama i smije obrisati zauzeće'],
    P_OGLASNIK_MOD => ['Oglasnik – moderacija', 'u zajedničkom Oglasniku lovaca odobrava javne oglase članova udruge, skriva neprimjerene oglase i obrađuje prijave'],
    P_INVENTURA => ['Inventura – pregled', 'vidi mape inventure (svoje sekcije i cijele udruge), stanje „treba“ / „stvarno“, manjak i višak, ispisuje PDF listu za popis – bez izmjena'],
    P_INVENTURA_UREDI => ['Inventura – uređivanje', 'dodaje i briše mape i stavke, upisuje „treba“ i „stvarno“, nova inventura, preuzimanje stanja'],
    P_OBAVIJESTI => ['Obavijesti – slanje', 'šalje obavijesti članovima svojih sekcija (svima, sekciji, funkciji ili odabranima), po želji s obaveznom potvrdom „Pročitao/la sam“; vidi tko je potvrdio, bilježi usmeno obaviještene, PDF'],
    P_GRUPNI_LOV => ['Skupni lov – organizacija', 'pokreće skupni lov za sekcije u svom opsegu (poziv svima na mobitel), vidi uživo položaj sudionika na karti, prima SOS i upozorenje o gubitku signala, završava lov'],
    P_DOMOVI => ['Lovački domovi – upravljanje', 'dodaje i uređuje lovačke domove (položaj, fotografija) za sekcije u svom opsegu, odobrava i odbija privatne rezervacije, upisuje događaje udruge (imaju prednost), otkazuje rezervacije'],
    P_POPUSTI => ['Popusti u trgovinama – uređivanje', 'dodaje, mijenja i briše trgovine s popustom za članove (popust, uvjeti, kontakt, logo, rok); članovi bez ovog prava vide samo važeće ponude'],
    P_ODSTREL_CITAJ => ['Odstrel – pregled', 'vidi odstrel i plan odstrela (ostvarenje) za sekcije u svom opsegu, PDF; bez ovog prava član vidi samo svoj odstrel'],
    P_ODSTREL => ['Odstrel – upis', 'upisuje, mijenja i briše odstrel (s brojem markice) i plan odstrela za sekcije u svom opsegu'],
    P_REVIR_DNEVNIK => ['Lovište – lovački dnevnik', 'vidi lovački dnevnik (tko, kada, gdje) za sekcije u svom opsegu, ispis i PDF; bez ovog prava dnevnik se ne prikazuje'],
];

// Statusi (enum vrijednosti kao u .NET verziji)
const CLAN_AKTIVAN = 0, CLAN_ARHIVIRAN = 1;
const AKCIJA_CEKA = 0, AKCIJA_ODOBRENO = 1, AKCIJA_ODBIJENO = 2;

// ---------- Konfiguracija ----------
function cfg(string $kljuc, mixed $zadano = null): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [];
        $dat = KORIJEN . '/config.php';
        if (is_file($dat)) {
            $c = (array) require $dat;
        }
    }
    return $c[$kljuc] ?? $zadano;
}

function podaci(string $pod = ''): string
{
    static $mapa = null;
    if ($mapa === null) {
        $m = (string) cfg('podaci', KORIJEN . '/podaci');
        if (!preg_match('#^(/|[A-Za-z]:)#', $m)) {
            $m = KORIJEN . '/' . $m;
        }
        $mapa = rtrim($m, '/\\');
        foreach (['', '/foto', '/kopije'] as $d) {
            if (!is_dir($mapa . $d)) {
                @mkdir($mapa . $d, 0775, true);
            }
        }
        $ht = $mapa . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
        }
    }
    return $pod === '' ? $mapa : $mapa . '/' . ltrim($pod, '/');
}

/** Smiju li tražilice (Google, Bing) prikazivati ovu instalaciju? Samo demo; prave udruge ne – ne žele biti pronađene. */
function dopusti_trazilice(): bool
{
    return (bool) cfg('dopusti_trazilice', je_demo());
}
/** Posebna instalacija (proširenje u app/lib, nije dio programa na GitHubu). Bez proširenja uvijek false. */
function je_demo(): bool
{
    return function_exists('demo_aktivan') && demo_aktivan();
}

// ---------- Baza ----------
function db(bool $novo = false): PDO
{
    static $pdo = null;
    if ($novo) {
        $pdo = null;
    }
    if ($pdo) {
        return $pdo;
    }
    $dat = podaci('evidencija.db');
    $nova = !is_file($dat) || filesize($dat) === 0;
    $pdo = new PDO('sqlite:' . $dat, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    try {
        $pdo->exec('PRAGMA journal_mode = WAL;');
    } catch (Throwable) {
        // neki hostinzi ne dopuštaju WAL – radi i bez njega
    }
    if ($nova) {
        $pdo->exec((string) file_get_contents(__DIR__ . '/shema.sql'));
    }
    nadogradi_bazu($pdo);
    $pdo->sqliteCreateFunction('kljuc', fn($s) => kljuc((string) $s), 1);
    return $pdo;
}

/**
 * Dodatne tablice PHP verzije (PRAGMA user_version). .NET verzija ih ne poznaje i zanemaruje,
 * pa baza i dalje radi u oba smjera.
 */
const SHEMA_PHP = 41;
function nadogradi_bazu(PDO $pdo): void
{
    if ((int) $pdo->query('PRAGMA user_version')->fetchColumn() >= SHEMA_PHP) {
        return;
    }
    // Odmah zaključati bazu za pisanje (BEGIN IMMEDIATE) i verziju pročitati ponovno unutar zaključavanja:
    // ako nakon ažuriranja dva korisnika istovremeno otvore aplikaciju, drugi čeka (busy_timeout) i zatim
    // vidi da je baza već nadograđena – inače bi ponovio ALTER TABLE i dobio „duplicate column“.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($v < SHEMA_PHP) {
            nadogradi_bazu_koraci($pdo, $v);
            $pdo->exec('PRAGMA user_version = ' . SHEMA_PHP);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        // sve ili ništa: baza ostaje na staroj verziji, sljedeći poziv pokušava ponovno
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable) {
        }
        throw new RuntimeException('Nadogradnja baze (verzija ' . ($v ?? '?') . ' → ' . SHEMA_PHP . ') nije uspjela: ' . $e->getMessage(), 0, $e);
    }
}

/** Koraci nadogradnje od verzije $v do SHEMA_PHP. Poziva se samo iz nadogradi_bazu(), unutar transakcije. */
function nadogradi_bazu_koraci(PDO $pdo, int $v): void
{
    if ($v < 1) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "Dogadjaji" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Naslov" TEXT NOT NULL,
    "Vrsta" TEXT NOT NULL,
    "Pocetak" TEXT NOT NULL,
    "Kraj" TEXT NULL,
    "CijeliDan" INTEGER NOT NULL DEFAULT 0,
    "Mjesto" TEXT NULL,
    "Opis" TEXT NULL,
    "ZaSve" INTEGER NOT NULL DEFAULT 1,
    "KreiraoId" INTEGER NULL,
    "KreiraoIme" TEXT NULL,
    "Kreirano" TEXT NOT NULL,
    "Azurirano" TEXT NULL,
    "AkcijeUpisane" TEXT NULL
);
CREATE INDEX IF NOT EXISTS "IX_Dogadjaji_Pocetak" ON "Dogadjaji" ("Pocetak");
CREATE TABLE IF NOT EXISTS "DogadjajSekcije" (
    "DogadjajId" INTEGER NOT NULL REFERENCES "Dogadjaji" ("Id") ON DELETE CASCADE,
    "SekcijaId" INTEGER NOT NULL REFERENCES "Sekcije" ("Id") ON DELETE CASCADE,
    PRIMARY KEY ("DogadjajId", "SekcijaId")
);
CREATE TABLE IF NOT EXISTS "DogadjajOdgovori" (
    "DogadjajId" INTEGER NOT NULL REFERENCES "Dogadjaji" ("Id") ON DELETE CASCADE,
    "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Dolazi" INTEGER NOT NULL,
    "Vrijeme" TEXT NOT NULL,
    PRIMARY KEY ("DogadjajId", "ClanId")
);
CREATE TABLE IF NOT EXISTS "ClanarinaRate" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ClanarinaId" INTEGER NOT NULL REFERENCES "Clanarine" ("Id") ON DELETE CASCADE,
    "RedniBroj" INTEGER NOT NULL,
    "Iznos" TEXT NOT NULL,
    "Dospijece" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "IX_ClanarinaRate_ClanarinaId" ON "ClanarinaRate" ("ClanarinaId");
CREATE TABLE IF NOT EXISTS "ClanVidljivost" (
    "ClanId" INTEGER NOT NULL PRIMARY KEY REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Mobitel" INTEGER NOT NULL DEFAULT 0,
    "Fiksni" INTEGER NOT NULL DEFAULT 0,
    "Email" INTEGER NOT NULL DEFAULT 0,
    "Mjesto" INTEGER NOT NULL DEFAULT 0,
    "Adresa" INTEGER NOT NULL DEFAULT 0,
    "Azurirano" TEXT NULL
);
CREATE TABLE IF NOT EXISTS "Razgovori2" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "KorisnikA" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    "KorisnikB" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    "Kreirano" TEXT NOT NULL,
    "ZadnjaPoruka" TEXT NOT NULL,
    UNIQUE ("KorisnikA", "KorisnikB")
);
CREATE TABLE IF NOT EXISTS "Poruke2" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "RazgovorId" INTEGER NOT NULL REFERENCES "Razgovori2" ("Id") ON DELETE CASCADE,
    "PosiljateljId" INTEGER NOT NULL,
    "Tekst" TEXT NOT NULL,
    "Vrijeme" TEXT NOT NULL,
    "Procitano" INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS "IX_Poruke2_RazgovorId" ON "Poruke2" ("RazgovorId");
UPDATE "Uloge" SET "Prava" = "Prava" | 768 WHERE ("Prava" & 255) = 255;
UPDATE "Uloge" SET "Prava" = "Prava" | 256 WHERE "Naziv" LIKE 'Predsjednik%' OR "Naziv" LIKE 'Lovnik%' OR "Naziv" = 'Domar';
SQL);
    }
    if ($v < 2) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS "SkriveniRazgovori" ("Vrsta" TEXT NOT NULL, "RazgovorId" INTEGER NOT NULL, "KorisnikId" INTEGER NOT NULL,
            "DoPorukeId" INTEGER NOT NULL, PRIMARY KEY ("Vrsta", "RazgovorId", "KorisnikId"))');
    }
    if ($v < 3) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "DezStanice" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
    "Vrsta" TEXT NOT NULL DEFAULT 'F',
    "Lat" REAL NULL,
    "Lon" REAL NULL,
    "Radijus" INTEGER NOT NULL DEFAULT 100,
    "Sredstvo" TEXT NULL,
    "Aktivna" INTEGER NOT NULL DEFAULT 1,
    "Token" TEXT NOT NULL,
    "Kreirano" TEXT NOT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "IX_DezStanice_Token" ON "DezStanice" ("Token");
CREATE TABLE IF NOT EXISTS "DezRazlozi" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "Aktivan" INTEGER NOT NULL DEFAULT 1,
    "Redoslijed" INTEGER NOT NULL DEFAULT 0,
    "Slobodno" INTEGER NOT NULL DEFAULT 0
);
INSERT INTO "DezRazlozi" ("Naziv", "Redoslijed", "Slobodno") VALUES ('Lov', 1, 0), ('Radna akcija', 2, 0), ('Obilazak / kontrola', 3, 0), ('Hranjenje', 4, 0), ('Ostalo', 9, 1);
CREATE TABLE IF NOT EXISTS "DezUpisi" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "StanicaId" INTEGER NOT NULL REFERENCES "DezStanice" ("Id"),
    "SekcijaId" INTEGER NULL,
    "Vrijeme" TEXT NOT NULL,
    "Smjer" TEXT NOT NULL,
    "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
    "Ime" TEXT NOT NULL,
    "Prezime" TEXT NOT NULL,
    "Gost" INTEGER NOT NULL DEFAULT 0,
    "Oznaka" TEXT NULL,
    "Razlog" TEXT NULL,
    "Vozilo" INTEGER NOT NULL DEFAULT 1,
    "Obuca" INTEGER NOT NULL DEFAULT 1,
    "Oprema" INTEGER NOT NULL DEFAULT 1,
    "UpisaoKorisnikId" INTEGER NULL,
    "UpisaoIme" TEXT NULL,
    "PozvaoIme" TEXT NULL,
    "Lat" REAL NULL,
    "Lon" REAL NULL,
    "Tocnost" REAL NULL,
    "Udaljenost" REAL NULL,
    "Lokacija" INTEGER NOT NULL DEFAULT 0,
    "Naknadno" INTEGER NOT NULL DEFAULT 0,
    "NaknadnoRazlog" TEXT NULL,
    "Ponisteno" INTEGER NOT NULL DEFAULT 0,
    "PonistenoRazlog" TEXT NULL,
    "Grupa" TEXT NOT NULL,
    "Kreirano" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "IX_DezUpisi_Vrijeme" ON "DezUpisi" ("Vrijeme");
CREATE INDEX IF NOT EXISTS "IX_DezUpisi_StanicaId" ON "DezUpisi" ("StanicaId");
CREATE INDEX IF NOT EXISTS "IX_DezUpisi_ClanId" ON "DezUpisi" ("ClanId");
CREATE INDEX IF NOT EXISTS "IX_DezUpisi_Grupa" ON "DezUpisi" ("Grupa");
CREATE TABLE IF NOT EXISTS "ClanVozila" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Oznaka" TEXT NOT NULL,
    "Zadano" INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS "IX_ClanVozila_ClanId" ON "ClanVozila" ("ClanId");
UPDATE "Uloge" SET "Prava" = "Prava" | 7168 WHERE ("Prava" & 1023) = 1023;
INSERT INTO "Uloge" ("Naziv", "Opis", "Prava", "Sustavna", "SveSekcije")
    SELECT 'Lovočuvar', 'Knjiga dezinfekcije: pregled, naknadni upis, stanice i QR oznake', 7168, 0, 0
    WHERE NOT EXISTS (SELECT 1 FROM "Uloge" WHERE "Naziv" = 'Lovočuvar');
SQL);
    }
    if ($v < 4) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "DezAktivacije" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "StanicaId" INTEGER NOT NULL REFERENCES "DezStanice" ("Id") ON DELETE CASCADE,
    "Naziv" TEXT NOT NULL,
    "Razlog" TEXT NULL,
    "Lat" REAL NOT NULL,
    "Lon" REAL NOT NULL,
    "Tocnost" REAL NULL,
    "Od" TEXT NOT NULL,
    "Do" TEXT NOT NULL,
    "AktiviraoId" INTEGER NULL,
    "AktiviraoIme" TEXT NULL,
    "Zatvoreno" TEXT NULL,
    "ZatvorioIme" TEXT NULL
);
CREATE INDEX IF NOT EXISTS "IX_DezAktivacije_StanicaId_Od" ON "DezAktivacije" ("StanicaId", "Od");
ALTER TABLE "DezUpisi" ADD COLUMN "AktivacijaId" INTEGER NULL;
ALTER TABLE "DezUpisi" ADD COLUMN "Izvanmrezno" INTEGER NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS "IX_DezUpisi_AktivacijaId" ON "DezUpisi" ("AktivacijaId");
UPDATE "Uloge" SET "Prava" = "Prava" | 8192 WHERE ("Prava" & 8191) = 8191 OR "Naziv" = 'Lovočuvar';
SQL);
    }
    if ($v < 5) {
        $pdo->exec("UPDATE \"Uloge\" SET \"Prava\" = \"Prava\" | 16384 WHERE (\"Prava\" & 16383) = 16383 OR \"Naziv\" = 'Lovočuvar'");
    }
    if ($v < 6) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS "DezListe" ("Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            "StanicaId" INTEGER NOT NULL REFERENCES "DezStanice" ("Id") ON DELETE CASCADE, "Od" TEXT NOT NULL, "Do" TEXT NOT NULL,
            "Datoteka" TEXT NOT NULL, "Napomena" TEXT NULL, "UcitaoIme" TEXT NULL, "Kreirano" TEXT NOT NULL);
            CREATE INDEX IF NOT EXISTS "IX_DezListe_Od_Do" ON "DezListe" ("Od", "Do");');
    }
    if ($v < 7) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "RevirNaprave" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Broj" TEXT NULL,
    "Naziv" TEXT NOT NULL,
    "Vrsta" TEXT NULL,
    "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
    "Lat" REAL NULL,
    "Lon" REAL NULL,
    "Foto" TEXT NULL,
    "Napomena" TEXT NULL,
    "Aktivna" INTEGER NOT NULL DEFAULT 1,
    "KreiraoIme" TEXT NULL,
    "Kreirano" TEXT NOT NULL,
    "Azurirano" TEXT NULL
);
CREATE INDEX IF NOT EXISTS "IX_RevirNaprave_SekcijaId" ON "RevirNaprave" ("SekcijaId");
CREATE TABLE IF NOT EXISTS "RevirZauzeca" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "NapravaId" INTEGER NOT NULL REFERENCES "RevirNaprave" ("Id") ON DELETE CASCADE,
    "SekcijaId" INTEGER NULL,
    "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
    "KorisnikId" INTEGER NULL,
    "Ime" TEXT NOT NULL,
    "Gost" TEXT NULL,
    "Od" TEXT NOT NULL,
    "VrijediDo" TEXT NOT NULL,
    "Kraj" TEXT NULL,
    "KrajNacin" TEXT NULL,
    "ObrisaoIme" TEXT NULL
);
CREATE UNIQUE INDEX IF NOT EXISTS "IX_RevirZauzeca_Aktivno" ON "RevirZauzeca" ("NapravaId") WHERE "Kraj" IS NULL;
CREATE INDEX IF NOT EXISTS "IX_RevirZauzeca_ClanId" ON "RevirZauzeca" ("ClanId");
CREATE TABLE IF NOT EXISTS "LovackiDnevnik" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ZauzeceId" INTEGER NULL,
    "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
    "Ime" TEXT NOT NULL,
    "SekcijaId" INTEGER NULL,
    "NapravaId" INTEGER NULL,
    "Naprava" TEXT NOT NULL,
    "Od" TEXT NOT NULL,
    "Do" TEXT NOT NULL,
    "Automatski" INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS "IX_LovackiDnevnik_Od" ON "LovackiDnevnik" ("Od");
CREATE INDEX IF NOT EXISTS "IX_LovackiDnevnik_ClanId" ON "LovackiDnevnik" ("ClanId");
CREATE TABLE IF NOT EXISTS "RevirObavijesti" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "SekcijaId" INTEGER NULL,
    "Vrijeme" TEXT NOT NULL,
    "Vrsta" TEXT NOT NULL,
    "Tekst" TEXT NOT NULL,
    "KorisnikId" INTEGER NULL
);
CREATE INDEX IF NOT EXISTS "IX_RevirObavijesti_SekcijaId" ON "RevirObavijesti" ("SekcijaId", "Id");
CREATE TABLE IF NOT EXISTS "RevirProcitano" (
    "KorisnikId" INTEGER NOT NULL PRIMARY KEY,
    "DoId" INTEGER NOT NULL DEFAULT 0
);
UPDATE "Uloge" SET "Prava" = "Prava" | 98304 WHERE ("Prava" & 32767) = 32767 OR "Naziv" = 'Lovočuvar';
SQL);
    }
    if ($v < 8) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "RevirVrste" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "Ikona" TEXT NULL,
    "Zauzimanje" INTEGER NOT NULL DEFAULT 1,
    "Redoslijed" INTEGER NOT NULL DEFAULT 0
);
INSERT INTO "RevirVrste" ("Naziv", "Zauzimanje", "Redoslijed") VALUES
    ('Čeka', 1, 1), ('Visoka čeka', 1, 2), ('Zatvorena čeka', 1, 3), ('Zaklon', 1, 4), ('Hranilište', 0, 5), ('Solište', 0, 6), ('Ostalo', 0, 9);
ALTER TABLE "RevirNaprave" ADD COLUMN "VrstaId" INTEGER NULL REFERENCES "RevirVrste" ("Id") ON DELETE SET NULL;
UPDATE "RevirNaprave" SET "VrstaId" = (SELECT v."Id" FROM "RevirVrste" v WHERE v."Naziv" = "RevirNaprave"."Vrsta");
SQL);
    }
    if ($v < 9) {
        $pdo->exec('ALTER TABLE "DezUpisi" ADD COLUMN "Organizirano" INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE "DezUpisi" ADD COLUMN "OrganiziranoProveli" TEXT NULL;');
    }
    if ($v < 10) {
        // novo pravo „Lovački dnevnik“: dobivaju ga uloge sa svim pravima i dosadašnji nadzor lovišta
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 131072 WHERE ("Prava" & 131071) = 131071 OR ("Prava" & 65536) = 65536;');
    }
    if ($v < 11) {
        // dvofaktorska prijava (TOTP, npr. Google Authenticator)
        $pdo->exec('ALTER TABLE "Korisnici" ADD COLUMN "TotpTajna" TEXT NULL;
            ALTER TABLE "Korisnici" ADD COLUMN "TotpRezervni" TEXT NULL;
            ALTER TABLE "Korisnici" ADD COLUMN "TotpZadnji" INTEGER NOT NULL DEFAULT 0;');
    }
    if ($v < 12) {
        // lovački psi članova; godišnji bodovi za psa (1.1.) kao radna akcija na čekanju
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS "LovniPsi" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    "Ime" TEXT NOT NULL,
    "Pasmina" TEXT NULL,
    "Spol" TEXT NULL,
    "DatumRodjenja" TEXT NULL,
    "Cip" TEXT NULL,
    "Napomena" TEXT NULL,
    "Aktivan" INTEGER NOT NULL DEFAULT 1,
    "Bodovanje" INTEGER NOT NULL DEFAULT 1,
    "Kreirano" TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS "IX_LovniPsi_ClanId" ON "LovniPsi" ("ClanId");
CREATE TABLE IF NOT EXISTS "PsiIspiti" (
    "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "PasId" INTEGER NOT NULL REFERENCES "LovniPsi" ("Id") ON DELETE CASCADE,
    "Naziv" TEXT NOT NULL,
    "Datum" TEXT NULL,
    "Ocjena" TEXT NULL
);
CREATE TABLE IF NOT EXISTS "PsiBodovi" (
    "PasId" INTEGER NOT NULL REFERENCES "LovniPsi" ("Id") ON DELETE CASCADE,
    "Godina" INTEGER NOT NULL,
    "AkcijaId" INTEGER NULL,
    PRIMARY KEY ("PasId", "Godina")
);
SQL);
    }
    if ($v < 13) {
        // psi na zajedničkom lovu (prijava „dolazim s psom“) i veza radne akcije sa psom
        $pdo->exec('ALTER TABLE "DogadjajOdgovori" ADD COLUMN "PsiIds" TEXT NULL;
            ALTER TABLE "RadneAkcije" ADD COLUMN "PasId" INTEGER NULL;
            ALTER TABLE "Dogadjaji" ADD COLUMN "PsiBodovano" TEXT NULL;');
        // vrste termina koje su lov: dolazak s psom
        $st = $pdo->prepare('SELECT Vrijednost FROM Postavke WHERE Kljuc=?');
        $st->execute(['Kalendar.Vrste']);
        $vr = json_decode((string) $st->fetchColumn(), true);
        if (is_array($vr) && $vr) {
            foreach ($vr as &$x) {
                $kl = kljuc($x['naziv'] ?? '');
                $x['psi'] = str_contains($kl, 'lov') && !str_contains($kl, 'radn') && !str_contains($kl, 'sastan');
            }
            unset($x);
            $pdo->prepare('UPDATE Postavke SET Vrijednost=? WHERE Kljuc=?')->execute([json_encode($vr, JSON_UNESCAPED_UNICODE), 'Kalendar.Vrste']);
        }
        // termini koji su već prošli se ne boduju naknadno
        $pdo->exec("UPDATE \"Dogadjaji\" SET \"PsiBodovano\" = datetime('now') WHERE substr(COALESCE(\"Kraj\", \"Pocetak\"),1,10) < date('now')");
    }
    if ($v < 14) {
        // pas osposobljen za potragu (krvni trag) – postojeći psi zadržavaju mogućnost
        $pdo->exec('ALTER TABLE "LovniPsi" ADD COLUMN "Potraga" INTEGER NOT NULL DEFAULT 0; UPDATE "LovniPsi" SET "Potraga" = 1;');
    }
    if ($v < 15) {
        // lovački pas: broj rodne knjige, boja, dlaka (datum oštenja = DatumRodjenja)
        $pdo->exec('ALTER TABLE "LovniPsi" ADD COLUMN "RodnaKnjiga" TEXT NULL;
            ALTER TABLE "LovniPsi" ADD COLUMN "Boja" TEXT NULL;
            ALTER TABLE "LovniPsi" ADD COLUMN "Dlaka" TEXT NULL;');
    }
    if ($v < 16) {
        // pas u popisu „Vodiči pasa“ (vlasnik može isključiti)
        $pdo->exec('ALTER TABLE "LovniPsi" ADD COLUMN "UPopisu" INTEGER NOT NULL DEFAULT 1;');
    }
    if ($v < 17) {
        // novo pravo „Oglasnik – moderacija“: dobivaju ga uloge sa svim dosadašnjim pravima
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 262144 WHERE ("Prava" & 262143) = 262143;');
    }
    if ($v < 18) {
        // Statut i pravilnici: dokumenti udruge (tekst + PDF / slike), članovi ih samo čitaju
        $pdo->exec('CREATE TABLE IF NOT EXISTS "Dokumenti" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naslov" TEXT NOT NULL,
                "Opis" TEXT NULL,
                "Tekst" TEXT NULL,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0,
                "Objavljeno" INTEGER NOT NULL DEFAULT 1,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS "DokumentDatoteke" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "DokumentId" INTEGER NOT NULL REFERENCES "Dokumenti" ("Id") ON DELETE CASCADE,
                "Datoteka" TEXT NOT NULL,
                "Vrsta" TEXT NOT NULL,
                "Naziv" TEXT NOT NULL,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0);
            CREATE INDEX IF NOT EXISTS "IX_DokumentDatoteke_DokumentId" ON "DokumentDatoteke" ("DokumentId");');
    }
    if ($v < 19) {
        // Inventura: mape (npr. lovački dom, sekcija) sa stavkama „treba“ / „stvarno“
        $pdo->exec('CREATE TABLE IF NOT EXISTS "InvMape" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naziv" TEXT NOT NULL,
                "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL,
                "AzuriraoIme" TEXT NULL);
            CREATE TABLE IF NOT EXISTS "InvStavke" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "MapaId" INTEGER NOT NULL REFERENCES "InvMape" ("Id") ON DELETE CASCADE,
                "Naziv" TEXT NOT NULL,
                "Jedinica" TEXT NULL,
                "Treba" REAL NULL,
                "Stvarno" REAL NULL,
                "Napomena" TEXT NULL,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0);
            CREATE INDEX IF NOT EXISTS "IX_InvStavke_MapaId" ON "InvStavke" ("MapaId");
            UPDATE "Uloge" SET "Prava" = "Prava" | 524288 WHERE ("Prava" & 524287) = 524287 OR "Naziv" = \'Domar\';');
    }
    if ($v < 20) {
        // Inventura razdvojena na pregled i uređivanje: tko je do sada imao „Inventura“, dobiva i uređivanje
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 1048576 WHERE ("Prava" & 524288) = 524288;');
    }
    if ($v < 21) {
        // Obavijesti članovima s obaveznom potvrdom čitanja (jednom, „Pročitao/la sam“)
        $pdo->exec('CREATE TABLE IF NOT EXISTS "Obavijesti" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naslov" TEXT NOT NULL,
                "Tekst" TEXT NOT NULL,
                "Obavezno" INTEGER NOT NULL DEFAULT 1,
                "Primatelji" TEXT NULL,
                "Sekcije" TEXT NULL,
                "KreiraoId" INTEGER NULL,
                "KreiraoIme" TEXT NULL,
                "Kreirano" TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS "ObavijestPrimatelji" (
                "ObavijestId" INTEGER NOT NULL REFERENCES "Obavijesti" ("Id") ON DELETE CASCADE,
                "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
                "Potvrdjeno" TEXT NULL,
                "Nacin" TEXT NULL,
                "Biljezio" TEXT NULL,
                PRIMARY KEY ("ObavijestId", "ClanId"));
            CREATE INDEX IF NOT EXISTS "IX_ObavijestPrimatelji_ClanId" ON "ObavijestPrimatelji" ("ClanId");
            UPDATE "Uloge" SET "Prava" = "Prava" | 2097152 WHERE ("Prava" & 2097151) = 2097151 OR "Naziv" LIKE \'Predsjednik%\' OR "Naziv" = \'Admin sekcije\';');
    }
    if ($v < 22) {
        // Kvarovi na lovnim napravama: prijava s karte, popis za lovočuvare / domare
        $pdo->exec('CREATE TABLE IF NOT EXISTS "RevirKvarovi" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "NapravaId" INTEGER NULL REFERENCES "RevirNaprave" ("Id") ON DELETE SET NULL,
                "Naprava" TEXT NOT NULL,
                "SekcijaId" INTEGER NULL,
                "Opis" TEXT NOT NULL,
                "Nesigurno" INTEGER NOT NULL DEFAULT 0,
                "Foto" TEXT NULL,
                "Status" TEXT NOT NULL DEFAULT \'otvoreno\',
                "PrijavioClanId" INTEGER NULL,
                "PrijavioKorisnikId" INTEGER NULL,
                "PrijavioIme" TEXT NOT NULL,
                "Prijavljeno" TEXT NOT NULL,
                "PreuzeoIme" TEXT NULL,
                "Preuzeto" TEXT NULL,
                "RijesioIme" TEXT NULL,
                "Rijeseno" TEXT NULL,
                "Napomena" TEXT NULL);
            CREATE INDEX IF NOT EXISTS "IX_RevirKvarovi_Status" ON "RevirKvarovi" ("Status", "SekcijaId");
            CREATE INDEX IF NOT EXISTS "IX_RevirKvarovi_NapravaId" ON "RevirKvarovi" ("NapravaId");');
    }
    if ($v < 23) {
        // Push obavijesti (Web Push): pretplate po uređaju i vrste koje je korisnik isključio
        $pdo->exec('CREATE TABLE IF NOT EXISTS "PushPretplate" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "KorisnikId" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
                "Endpoint" TEXT NOT NULL UNIQUE,
                "P256dh" TEXT NOT NULL,
                "Auth" TEXT NOT NULL,
                "Uredjaj" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                "ZadnjeSlanje" TEXT NULL,
                "Greske" INTEGER NOT NULL DEFAULT 0);
            CREATE INDEX IF NOT EXISTS "IX_PushPretplate_KorisnikId" ON "PushPretplate" ("KorisnikId");
            CREATE TABLE IF NOT EXISTS "PushIskljuceno" (
                "KorisnikId" INTEGER NOT NULL REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
                "Vrsta" TEXT NOT NULL,
                PRIMARY KEY ("KorisnikId", "Vrsta"));');
    }
    if ($v < 24) {
        // Dodatna oprema uz vrstu radne akcije (npr. Košnja: traktor, trimer, motorna pila) s dodatnim bodovima
        $pdo->exec('CREATE TABLE IF NOT EXISTS "VrstaOprema" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "VrstaAkcijeId" INTEGER NOT NULL REFERENCES "VrsteAkcija" ("Id") ON DELETE CASCADE,
                "Naziv" TEXT NOT NULL,
                "Bodovi" TEXT NOT NULL DEFAULT \'0\',
                "Redoslijed" INTEGER NOT NULL DEFAULT 0);
            CREATE INDEX IF NOT EXISTS "IX_VrstaOprema_Vrsta" ON "VrstaOprema" ("VrstaAkcijeId");
            ALTER TABLE "RadneAkcije" ADD COLUMN "Oprema" TEXT NULL;');
    }
    if ($v < 25) {
        // Skupni lov: poziv lovcima (dolazim / ne dolazim), položaj sudionika uživo, SOS organizatoru.
        // Položaji se brišu kad lov završi – ostaje samo tko je sudjelovao.
        $pdo->exec('CREATE TABLE IF NOT EXISTS "GrupniLov" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naziv" TEXT NOT NULL,
                "Mjesto" TEXT NULL,
                "Napomena" TEXT NULL,
                "Sekcije" TEXT NULL,
                "Pocetak" TEXT NOT NULL,
                "Kraj" TEXT NOT NULL,
                "Zavrseno" TEXT NULL,
                "ZavrsioIme" TEXT NULL,
                "KreiraoKorisnikId" INTEGER NULL,
                "KreiraoClanId" INTEGER NULL,
                "KreiraoIme" TEXT NOT NULL,
                "Kreirano" TEXT NOT NULL);
            CREATE INDEX IF NOT EXISTS "IX_GrupniLov_Zavrseno" ON "GrupniLov" ("Zavrseno", "Kraj");
            CREATE TABLE IF NOT EXISTS "GrupniLovSudionici" (
                "LovId" INTEGER NOT NULL REFERENCES "GrupniLov" ("Id") ON DELETE CASCADE,
                "KorisnikId" INTEGER NOT NULL,
                "ClanId" INTEGER NULL,
                "Ime" TEXT NOT NULL,
                "SekcijaId" INTEGER NULL,
                "Token" TEXT NOT NULL,
                "Odgovor" INTEGER NULL,
                "Odgovoreno" TEXT NULL,
                "Otisao" TEXT NULL,
                "Lat" REAL NULL,
                "Lon" REAL NULL,
                "Tocnost" REAL NULL,
                "Polozaj" TEXT NULL,
                "Skriveno" TEXT NULL,
                "SignalJavljen" TEXT NULL,
                "SosOd" TEXT NULL,
                "SosLat" REAL NULL,
                "SosLon" REAL NULL,
                "SosPreuzeoIme" TEXT NULL,
                "SosPreuzeto" TEXT NULL,
                PRIMARY KEY ("LovId", "KorisnikId"));
            CREATE INDEX IF NOT EXISTS "IX_GrupniLovSudionici_Korisnik" ON "GrupniLovSudionici" ("KorisnikId");
            CREATE UNIQUE INDEX IF NOT EXISTS "IX_GrupniLovSudionici_Token" ON "GrupniLovSudionici" ("Token");
            UPDATE "Uloge" SET "Prava" = "Prava" | 4194304 WHERE ("Prava" & 4194303) = 4194303 OR "Naziv" LIKE \'Lovnik%\';');
    }
    if ($v < 26) {
        // Lovački domovi: dom za cijelu udrugu ili sekciju, rezervacije po danima (privatno na odobrenje, događaj udruge ima prednost)
        $pdo->exec('CREATE TABLE IF NOT EXISTS "LovackiDomovi" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naziv" TEXT NOT NULL,
                "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
                "Opis" TEXT NULL,
                "Kapacitet" INTEGER NULL,
                "Kontakt" TEXT NULL,
                "Lat" REAL NULL,
                "Lon" REAL NULL,
                "Foto" TEXT NULL,
                "Aktivan" INTEGER NOT NULL DEFAULT 1,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL);
            CREATE TABLE IF NOT EXISTS "DomRezervacije" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "DomId" INTEGER NOT NULL REFERENCES "LovackiDomovi" ("Id") ON DELETE CASCADE,
                "Od" TEXT NOT NULL,
                "Do" TEXT NOT NULL,
                "Vrsta" TEXT NOT NULL,
                "Status" TEXT NOT NULL,
                "Naslov" TEXT NULL,
                "Napomena" TEXT NULL,
                "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
                "KorisnikId" INTEGER NULL,
                "Ime" TEXT NOT NULL,
                "Kreirano" TEXT NOT NULL,
                "ObradioIme" TEXT NULL,
                "Obradjeno" TEXT NULL,
                "Razlog" TEXT NULL);
            CREATE INDEX IF NOT EXISTS "IX_DomRezervacije_Dom" ON "DomRezervacije" ("DomId", "Od", "Do");
            CREATE INDEX IF NOT EXISTS "IX_DomRezervacije_Status" ON "DomRezervacije" ("Status");
            UPDATE "Uloge" SET "Prava" = "Prava" | 8388608 WHERE ("Prava" & 8388607) = 8388607 OR "Naziv" = \'Domar\' OR "Naziv" LIKE \'Predsjednik%\';');
    }
    if ($v < 27) {
        // Članska iskaznica s QR kodom (stalni slučajni ključ po članu) i popis sudionika skupnog lova (lovnik skenira iskaznice).
        // Ključ služi SAMO za popis sudionika – nije povezan s dezinfekcijom, bodovima ni ničim drugim.
        $pdo->exec('CREATE TABLE IF NOT EXISTS "ClanIskaznice" (
                "ClanId" INTEGER NOT NULL PRIMARY KEY REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
                "Token" TEXT NOT NULL,
                "Kreirano" TEXT NOT NULL);
            CREATE UNIQUE INDEX IF NOT EXISTS "IX_ClanIskaznice_Token" ON "ClanIskaznice" ("Token");
            CREATE TABLE IF NOT EXISTS "GrupniLovPopis" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "LovId" INTEGER NOT NULL REFERENCES "GrupniLov" ("Id") ON DELETE CASCADE,
                "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
                "Ime" TEXT NOT NULL,
                "Sekcija" TEXT NULL,
                "Gost" INTEGER NOT NULL DEFAULT 0,
                "Nacin" TEXT NOT NULL,
                "Vrijeme" TEXT NOT NULL,
                "Upisao" TEXT NULL,
                "KlijentId" TEXT NULL,
                "Kreirano" TEXT NOT NULL);
            CREATE INDEX IF NOT EXISTS "IX_GrupniLovPopis_Lov" ON "GrupniLovPopis" ("LovId", "Vrijeme");
            CREATE UNIQUE INDEX IF NOT EXISTS "IX_GrupniLovPopis_Clan" ON "GrupniLovPopis" ("LovId", "ClanId") WHERE "ClanId" IS NOT NULL;');
    }
    if ($v < 28) {
        // Što pas radi (više vrsta rada: potraga, pogon, sitna divljač, ptičarenje, izgon, jazbina, donošenje) – za bodove i Vodiče pasa
        $pdo->exec('ALTER TABLE "LovniPsi" ADD COLUMN "Radovi" TEXT NULL; UPDATE "LovniPsi" SET "Radovi" = \'potraga\' WHERE "Potraga" = 1;');
    }
    if ($v < 29) {
        // rođendanske čestitke e-poštom – tko je i kada poslao (jednom po članu i godini, vidljivo svima koji vide rođendane)
        $pdo->exec('CREATE TABLE IF NOT EXISTS "RodjendanCestitke" (
                "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
                "Godina" INTEGER NOT NULL,
                "KorisnikId" INTEGER NULL,
                "Poslao" TEXT NULL,
                "Vrijeme" TEXT NOT NULL,
                PRIMARY KEY ("ClanId", "Godina"));');
    }
    if ($v < 30) {
        // Popusti u trgovinama: trgovine koje daju popust članovima (član pokazuje iskaznicu na blagajni ili se javlja prodavaču)
        $pdo->exec('CREATE TABLE IF NOT EXISTS "Popusti" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naziv" TEXT NOT NULL,
                "Popust" TEXT NOT NULL,
                "Uvjeti" TEXT NULL,
                "Nacin" TEXT NOT NULL DEFAULT \'blagajna\',
                "KontaktOsoba" TEXT NULL,
                "Telefon" TEXT NULL,
                "Email" TEXT NULL,
                "Web" TEXT NULL,
                "Adresa" TEXT NULL,
                "Logo" TEXT NULL,
                "VrijediDo" TEXT NULL,
                "Aktivno" INTEGER NOT NULL DEFAULT 1,
                "Redoslijed" INTEGER NOT NULL DEFAULT 0,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL);');
    }
    if ($v < 31) {
        // pravo „Popusti u trgovinama – uređivanje“: dobivaju ga uloge koje već imaju sva prava (Glavni admin)
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 16777216 WHERE ("Prava" & 16777215) = 16777215;');
    }
    if ($v < 32) {
        // Mobilna stanica: najviše 5 ovlaštenih (uključujući lovočuvara) smije upisivati skeniranjem članskih iskaznica
        $pdo->exec('CREATE TABLE IF NOT EXISTS "DezSkeneri" (
                "AktivacijaId" INTEGER NOT NULL,
                "KorisnikId" INTEGER NOT NULL,
                "Ime" TEXT NOT NULL,
                "DodaoIme" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                PRIMARY KEY ("AktivacijaId", "KorisnikId"))');
        $pdo->exec('ALTER TABLE "DezUpisi" ADD COLUMN "Skenirano" INTEGER NOT NULL DEFAULT 0');
    }
    if ($v < 33) {
        // Oružni listovi člana (jedan član može imati više oružja – svako ima svoj oružni list)
        $pdo->exec('CREATE TABLE IF NOT EXISTS "ClanOruzniListovi" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
                "Broj" TEXT NOT NULL,
                "Oruzje" TEXT NULL,
                "Kreirano" TEXT NOT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_ClanOruzniListovi_ClanId" ON "ClanOruzniListovi" ("ClanId")');
    }
    if ($v < 34) {
        // Skupni lov – gost na popisu: broj lovačke iskaznice, oružni list i potpis na mobitelu (za potpun PDF)
        $pdo->exec('ALTER TABLE "GrupniLovPopis" ADD COLUMN "GostIskaznica" TEXT NULL');
        $pdo->exec('ALTER TABLE "GrupniLovPopis" ADD COLUMN "GostOruzni" TEXT NULL');
        $pdo->exec('ALTER TABLE "GrupniLovPopis" ADD COLUMN "Potpis" TEXT NULL');
    }
    if ($v < 35) {
        // lovni gost člana na terminu lova (QR pozivnica vrijedi samo za taj dan) + veza na popis skupnog lova
        $pdo->exec('CREATE TABLE IF NOT EXISTS "DogadjajGosti" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "DogadjajId" INTEGER NOT NULL REFERENCES "Dogadjaji" ("Id") ON DELETE CASCADE,
                "ClanId" INTEGER NOT NULL,
                "Ime" TEXT NOT NULL,
                "Prezime" TEXT NOT NULL,
                "BrojIskaznice" TEXT NULL,
                "OruzniList" TEXT NULL,
                "Email" TEXT NULL,
                "Token" TEXT NOT NULL UNIQUE,
                "Poslano" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                UNIQUE ("DogadjajId", "ClanId"))');
        $pdo->exec('ALTER TABLE "GrupniLovPopis" ADD COLUMN "GostId" INTEGER NULL');
        $pdo->exec('ALTER TABLE "GrupniLovPopis" ADD COLUMN "PozvaoIme" TEXT NULL');
        // vrsta termina „Zajednički lov“ → „Skupni lov“
        $st = $pdo->prepare('SELECT Vrijednost FROM Postavke WHERE Kljuc=?');
        $st->execute(['Kalendar.Vrste']);
        $vr = json_decode((string) $st->fetchColumn(), true);
        if (is_array($vr) && $vr) {
            foreach ($vr as &$x) {
                if (($x['naziv'] ?? '') === 'Zajednički lov') {
                    $x['naziv'] = 'Skupni lov';
                }
            }
            unset($x);
            $pdo->prepare('UPDATE Postavke SET Vrijednost=? WHERE Kljuc=?')->execute([json_encode($vr, JSON_UNESCAPED_UNICODE), 'Kalendar.Vrste']);
        }
        $pdo->exec("UPDATE \"Dogadjaji\" SET \"Vrsta\" = 'Skupni lov' WHERE \"Vrsta\" = 'Zajednički lov'");
    }
    if ($v < 36) {
        // skupni lov pokrenut iz termina u kalendaru (bez novog poziva – vrijede odgovori iz kalendara)
        $pdo->exec('ALTER TABLE "GrupniLov" ADD COLUMN "DogadjajId" INTEGER NULL');
    }
    if ($v < 37) {
        // broj lova po sekciji: sekcija organizatora (tko je pokrenuo lov) + dodatni zapisnik (upis odstrijeljene divljači)
        $pdo->exec('ALTER TABLE "GrupniLov" ADD COLUMN "SekcijaBroja" INTEGER NULL');
        $pdo->exec('UPDATE "GrupniLov" SET "SekcijaBroja" = COALESCE((SELECT c."SekcijaId" FROM "Clanovi" c WHERE c."Id" = "GrupniLov"."KreiraoClanId"),
                CASE WHEN instr(COALESCE("Sekcije", \'\'), \',\') = 0 AND COALESCE("Sekcije", \'\') NOT IN (\'\', \'0\') THEN CAST("Sekcije" AS INTEGER) END)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS "GrupniLovZapisnik" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "LovId" INTEGER NOT NULL REFERENCES "GrupniLov" ("Id") ON DELETE CASCADE,
                "Podaci" TEXT NOT NULL,
                "Upisao" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_GrupniLovZapisnik_Lov" ON "GrupniLovZapisnik" ("LovId")');
    }
    if ($v < 38) {
        // idealni smjerovi vjetra za lovnu napravu (iz kojih strana smije puhati), npr. „SI,I“
        $pdo->exec('ALTER TABLE "RevirNaprave" ADD COLUMN "Vjetar" TEXT NULL');
    }
    if ($v < 39) {
        // Odstrel (s brojem markice) i plan odstrela po sekciji i lovnoj godini
        $pdo->exec('CREATE TABLE IF NOT EXISTS "Odstrel" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
                "LovnaGodina" INTEGER NOT NULL,
                "Datum" TEXT NOT NULL,
                "Vrijeme" TEXT NULL,
                "Vrsta" TEXT NOT NULL,
                "Spol" TEXT NULL,
                "Dob" TEXT NULL,
                "Masa" REAL NULL,
                "Markica" TEXT NULL,
                "ClanId" INTEGER NULL REFERENCES "Clanovi" ("Id") ON DELETE SET NULL,
                "Lovac" TEXT NOT NULL,
                "Gost" INTEGER NOT NULL DEFAULT 0,
                "NapravaId" INTEGER NULL REFERENCES "RevirNaprave" ("Id") ON DELETE SET NULL,
                "Mjesto" TEXT NULL,
                "Oruzje" TEXT NULL,
                "Kalibar" TEXT NULL,
                "Udaljenost" INTEGER NULL,
                "Trofej" REAL NULL,
                "Napomena" TEXT NULL,
                "Foto" TEXT NULL,
                "Upisao" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_Odstrel_Godina" ON "Odstrel" ("LovnaGodina", "SekcijaId")');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_Odstrel_Clan" ON "Odstrel" ("ClanId")');
        $pdo->exec('CREATE TABLE IF NOT EXISTS "OdstrelPlan" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE CASCADE,
                "LovnaGodina" INTEGER NOT NULL,
                "Vrsta" TEXT NOT NULL,
                "Kategorija" TEXT NOT NULL DEFAULT \'\',
                "Broj" INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_OdstrelPlan_Godina" ON "OdstrelPlan" ("LovnaGodina", "SekcijaId")');
        // prava: tko ima sva dosadašnja prava (glavni admin i sl.) dobiva oba; lovnik i tko vidi lovački dnevnik – pregled
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 100663296 WHERE ("Prava" & 33554431) = 33554431 OR "Naziv" LIKE \'Lovnik%\'');
        $pdo->exec('UPDATE "Uloge" SET "Prava" = "Prava" | 33554432 WHERE ("Prava" & 131072) = 131072');
    }
    if ($v < 40) {
        // više lovišta: podaci lovišta, granica (poligoni) i linije (putevi, prosjeke…) kao JSON; sekcija NULL = za sve sekcije
        $pdo->exec('CREATE TABLE IF NOT EXISTS "Lovista" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "Naziv" TEXT NOT NULL,
                "Broj" TEXT NULL,
                "Vrsta" TEXT NULL,
                "Ovlastenik" TEXT NULL,
                "Zupanija" TEXT NULL,
                "Povrsina" REAL NULL,
                "Boja" TEXT NOT NULL DEFAULT \'#ffd400\',
                "SekcijaId" INTEGER NULL REFERENCES "Sekcije" ("Id") ON DELETE SET NULL,
                "Aktivno" INTEGER NOT NULL DEFAULT 1,
                "Granica" TEXT NULL,
                "Linije" TEXT NULL,
                "Napomena" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL)');
        // dosadašnje granice (jedan KML za cijelu udrugu) → prvo lovište; linije iz KML-a → linije lovišta
        $g = $pdo->query('SELECT Vrijednost FROM Postavke WHERE Kljuc=\'Revir.Granice\'')->fetchColumn();
        $j = $g ? json_decode((string) $g, true) : null;
        if (is_array($j) && !empty($j['oblici'])) {
            $pol = [];
            $lin = [];
            foreach ($j['oblici'] as $o) {
                if (($o['tip'] ?? '') === 'poligon') {
                    $pol[] = $o['koord'];
                } else {
                    $lin[] = ['naziv' => (string) ($o['ime'] ?? ''), 'vrsta' => 'Granica', 'boja' => '#ffd400', 'crtkano' => 0, 'koord' => $o['koord']];
                }
            }
            $naz = (string) ($pdo->query('SELECT Vrijednost FROM Postavke WHERE Kljuc=\'Udruga.Kratko\'')->fetchColumn() ?: 'Lovište');
            $pdo->prepare('INSERT INTO "Lovista" ("Naziv", "Granica", "Linije", "Napomena", "Kreirano") VALUES (?, ?, ?, ?, ?)')
                ->execute([$naz, $pol ? json_encode($pol) : null, $lin ? json_encode($lin, JSON_UNESCAPED_UNICODE) : null,
                    'Preuzeto iz uvezenog KML-a' . (!empty($j['datoteka']) ? ' (' . $j['datoteka'] . ')' : ''), date('Y-m-d H:i:s')]);
        }
    }
    if ($v < 41) {
        // Moji dokumenti: lovačka iskaznica, dozvole, oružni list, osiguranje … – fotografija (prednja/stražnja) i/ili upis s rokom
        $pdo->exec('CREATE TABLE IF NOT EXISTS "ClanDokumenti" (
                "Id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
                "ClanId" INTEGER NOT NULL REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
                "Vrsta" TEXT NOT NULL,
                "Naziv" TEXT NULL,
                "Broj" TEXT NULL,
                "Izdavatelj" TEXT NULL,
                "VrijediOd" TEXT NULL,
                "VrijediDo" TEXT NULL,
                "Napomena" TEXT NULL,
                "Foto1" TEXT NULL,
                "Foto2" TEXT NULL,
                "Kreirano" TEXT NOT NULL,
                "Azurirano" TEXT NULL)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS "IX_ClanDokumenti_Clan" ON "ClanDokumenti" ("ClanId")');
    }
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function red(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}
function redovi(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}
function vrijednost(string $sql, array $p = []): mixed
{
    $v = q($sql, $p)->fetchColumn();
    return $v === false ? null : $v;
}
function umetni(string $tablica, array $polja): int
{
    $k = array_keys($polja);
    $sql = 'INSERT INTO "' . $tablica . '" ("' . implode('","', $k) . '") VALUES (' . implode(',', array_fill(0, count($k), '?')) . ')';
    q($sql, array_values($polja));
    return (int) db()->lastInsertId();
}
function azuriraj(string $tablica, int $id, array $polja): void
{
    $set = implode(',', array_map(fn($k) => '"' . $k . '"=?', array_keys($polja)));
    q('UPDATE "' . $tablica . '" SET ' . $set . ' WHERE Id=?', [...array_values($polja), $id]);
}
function transakcija(callable $fn): mixed
{
    db()->beginTransaction();
    try {
        $r = $fn();
        db()->commit();
        return $r;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function sada(): string
{
    return date('Y-m-d H:i:s');
}
function danas(): string
{
    return date('Y-m-d');
}

// ---------- Postavke (tablica Postavke) ----------
function postavka(string $k, ?string $zadano = null): ?string
{
    static $sve = null;
    if ($sve === null || $k === '__osvjezi') {
        $sve = [];
        foreach (redovi('SELECT Kljuc, Vrijednost FROM Postavke') as $r) {
            $sve[$r['Kljuc']] = $r['Vrijednost'];
        }
        if ($k === '__osvjezi') {
            return null;
        }
    }
    $v = $sve[$k] ?? null;
    return ($v === null || $v === '') ? $zadano : $v;
}
function spremi_postavku(string $k, ?string $v): void
{
    if ($v === null || $v === '') {
        q('DELETE FROM Postavke WHERE Kljuc=?', [$k]);
    } else {
        q('INSERT INTO Postavke (Kljuc, Vrijednost) VALUES (?, ?) ON CONFLICT(Kljuc) DO UPDATE SET Vrijednost=excluded.Vrijednost', [$k, $v]);
    }
    postavka('__osvjezi');
}

// ---------- Udruga ----------
function udruga_naziv(): string
{
    return postavka('Udruga.Naziv', 'Lovačka udruga');
}
/** Adresa i OIB udruge u jednom retku (zaglavlja PDF-ova, izjava, uplatnice). */
function udruga_adresa(bool $saOib = true): string
{
    $d = array_filter([
        trim((string) postavka('Udruga.Adresa', '')),
        trim((string) postavka('Udruga.Mjesto', '')),
        $saOib && postavka('Udruga.Oib') ? 'OIB ' . postavka('Udruga.Oib') : '',
    ]);
    return implode(', ', $d);
}
function udruga_kratko(): string
{
    return postavka('Udruga.Kratko', 'Evidencija');
}
// ---------- Izgled (tema) ----------
const TEME = ['svijetla', 'tamna', 'uredaj'];
const TEME_NAZIVI = ['svijetla' => 'Svijetla', 'tamna' => 'Tamna', 'uredaj' => 'Kao uređaj'];
/** Zadana tema udruge (Sustav → Izgled). Korisnik je može promijeniti za sebe (localStorage „ev-tema“). */
function tema_zadana(): string
{
    $t = (string) postavka('Izgled.Tema', 'svijetla');
    return in_array($t, TEME, true) ? $t : 'svijetla';
}
/** <script> u <head>: postavi data-bs-theme prije iscrtavanja (bez bljeska svijetle stranice). */
function tema_skripta(): string
{
    return '<script>(function(){var z=' . json_encode(tema_zadana()) . ',t=null;try{t=localStorage.getItem("ev-tema")}catch(x){}'
        . 'if(t!=="svijetla"&&t!=="tamna"&&t!=="uredaj")t=z;window.EV_TEMA_ZADANA=z;'
        . 'if(t==="uredaj")t=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"tamna":"svijetla";'
        . 'document.documentElement.setAttribute("data-bs-theme",t==="tamna"?"dark":"light");})();</script>';
}

function udruga_podnaslov(): string
{
    return postavka('Udruga.Podnaslov', 'Evidencija članova');
}
function ima_logo(): bool
{
    $l = postavka('Udruga.Logo');
    return $l && is_file(podaci('foto/' . basename($l)));
}

// ---------- Tekst ----------
function e(mixed $s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function bez_dijakritika(?string $s): string
{
    if ($s === null || $s === '') {
        return '';
    }
    $s = strtr($s, ['č' => 'c', 'ć' => 'c', 'Č' => 'C', 'Ć' => 'C', 'š' => 's', 'Š' => 'S', 'ž' => 'z', 'Ž' => 'Z', 'đ' => 'd', 'Đ' => 'D']);
    if (class_exists('Normalizer')) {
        $s = (string) Normalizer::normalize($s, Normalizer::FORM_D);
        $s = (string) preg_replace('/\p{Mn}+/u', '', $s);
    }
    return $s;
}
function kljuc(?string $s): string
{
    return mb_strtolower(trim(bez_dijakritika($s)));
}
function ispravan_oib(?string $oib): bool
{
    if (!$oib || !preg_match('/^\d{11}$/', $oib)) {
        return false;
    }
    $a = 10;
    for ($i = 0; $i < 10; $i++) {
        $a = ($a + (int) $oib[$i]) % 10;
        if ($a === 0) {
            $a = 10;
        }
        $a = ($a * 2) % 11;
    }
    $k = 11 - $a;
    if ($k === 10) {
        $k = 0;
    }
    return $k === (int) $oib[10];
}
function broj(mixed $v, int $dec = 1): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $f = (float) $v;
    $s = number_format($f, $dec, ',', '.');
    if ($dec > 0 && str_contains($s, ',')) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s;
}
function novac(mixed $v): string
{
    return number_format((float) $v, 2, ',', '.');
}
/** Pretvara unos "6,5" / "6.5" u broj ili null. */
function u_broj(mixed $s): ?float
{
    $s = trim(str_replace([' ', "\u{a0}"], '', (string) $s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    return is_numeric($s) ? (float) $s : null;
}
/** Decimalni zapis kao u EF Core (TEXT, invariant). */
function dec(?float $v): ?string
{
    if ($v === null) {
        return null;
    }
    $s = rtrim(rtrim(sprintf('%.4F', $v), '0'), '.');
    return str_contains($s, '.') ? $s : $s . '.0';
}
function datum(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime(substr($d, 0, 19));
    return $t ? date('d.m.Y.', $t) : '';
}
function datum_vrijeme(?string $d): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime(substr($d, 0, 19));
    return $t ? date('d.m.Y. H:i', $t) : '';
}
/** Datum iz <input type=date> → 'Y-m-d' ili null. */
function u_datum(?string $s): ?string
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}$/', $s)) {
        return $s . '-01';
    }
    if (preg_match('/^(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d', $t) : null;
}
function puno_ime(?array $c): string
{
    return $c ? trim(($c['Ime'] ?? '') . ' ' . ($c['Prezime'] ?? '')) : '';
}
function prezime_ime(?array $c): string
{
    return $c ? trim(($c['Prezime'] ?? '') . ' ' . ($c['Ime'] ?? '')) : '';
}
function adresa(array $c): string
{
    $d = array_filter([trim(($c['Ulica'] ?? '') . ' ' . ($c['KucniBroj'] ?? '')), trim(($c['PostanskiBroj'] ?? '') . ' ' . ($c['Mjesto'] ?? ''))]);
    return implode(', ', $d);
}
function inicijali(?array $c): string
{
    if (!$c) {
        return '?';
    }
    return mb_strtoupper(mb_substr((string) ($c['Ime'] ?? ''), 0, 1) . mb_substr((string) ($c['Prezime'] ?? ''), 0, 1)) ?: '?';
}

// ---------- URL, preusmjeravanje, poruke ----------
function bazni_put(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/') . '/';
    }
    return $b;
}
/** url('clanovi/uredi', ['id'=>5]) → index.php?p=clanovi/uredi&id=5 */
function url(string $str = '', array $par = []): string
{
    $q = $str === '' ? $par : ['p' => $str] + $par;
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return bazni_put() . ($q ? 'index.php?' . str_replace('%2F', '/', http_build_query($q)) : '');
}
function asset(string $dat): string
{
    $put = KORIJEN . '/' . $dat;
    return bazni_put() . $dat . (is_file($put) ? '?v=' . filemtime($put) : '');
}
/** Javna apsolutna adresa (za poveznice u porukama). */
function javna_adresa(): string
{
    $a = postavka('Server.JavnaAdresa') ?: cfg('javna_adresa');
    if ($a) {
        return rtrim((string) $a, '/') . '/';
    }
    return trenutna_adresa();
}
/** Adresa kojom je aplikacija upravo otvorena (bez obzira na postavku). */
function trenutna_adresa(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . bazni_put();
}
/** Je li adresa dostupna samo lokalno / u privatnoj mreži (LAN, Tailscale IP 100.64–127.x)? *.ts.net s Funnelom je javna. */
function adresa_privatna(string $adr): bool
{
    $h = strtolower((string) parse_url($adr, PHP_URL_HOST));
    return $h === '' || $h === 'localhost' || str_ends_with($h, '.local')
        || preg_match('#^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.)#', $h) === 1;
}
/** Upozorenje ispod poveznice (pozivnica, samoprijava) ako ona neće raditi članovima ili vodi na drugu adresu. */
function napomena_javne_adrese(): string
{
    $j = javna_adresa();
    $t = trenutna_adresa();
    $put = '<a href="' . e(url('sustav/postavke')) . '">Sustav → Postavke → Javna adresa</a>';
    if (adresa_privatna($j)) {
        $tekst = 'Poveznica vodi na <b>' . e($j) . '</b> – ta adresa radi samo na ovom računalu / u privatnoj mreži, članovima neće raditi. Promijenite u ' . $put . '.';
    } elseif (parse_url($j, PHP_URL_HOST) !== parse_url($t, PHP_URL_HOST) && !adresa_privatna($t)) {
        $tekst = 'Poveznica vodi na <b>' . e($j) . '</b>, a aplikaciju ste otvorili preko <b>' . e($t) . '</b>. Ako to nije ispravno, promijenite u ' . $put . '.';
    } else {
        return '';
    }
    return '<p class="small text-warning-emphasis mt-2 mb-0">⚠ ' . $tekst . '</p>';
}
function apsolutni_url(string $str, array $par = []): string
{
    $q = ['p' => $str] + $par;
    return javna_adresa() . 'index.php?' . str_replace('%2F', '/', http_build_query($q));
}
function preusmjeri(string $str = '', array $par = []): never
{
    header('Location: ' . url($str, $par));
    exit;
}
function poruka(string $tekst, string $vrsta = 'success'): void
{
    $_SESSION['poruke'][] = [$vrsta, $tekst];
}
function poruke(): array
{
    $p = $_SESSION['poruke'] ?? [];
    unset($_SESSION['poruke']);
    return $p;
}
function je_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
function ul(string $k, mixed $zadano = ''): mixed
{
    return $_POST[$k] ?? $_GET[$k] ?? $zadano;
}
function ul_str(string $k): string
{
    return trim((string) ul($k, ''));
}
function ul_null(string $k): ?string
{
    $v = trim((string) ($_POST[$k] ?? ''));
    return $v === '' ? null : $v;
}
function ul_int(string $k): ?int
{
    $v = ul($k, null);
    return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}
function csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function provjeri_csrf(): void
{
    if (je_post() && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
        http_response_code(400);
        exit('Sigurnosna provjera nije uspjela (istekla stranica). Vratite se i pokušajte ponovno.');
    }
}

// ---------- Prikaz ----------
/** Učitava predložak iz app/predlosci s varijablama. */
function predlozak(string $ime, array $var = []): string
{
    extract($var, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/predlosci/' . $ime . '.php';
    return (string) ob_get_clean();
}
/** Ispisuje stranicu u glavnom okviru. $sadrzaj je HTML. */
function stranica(string $naslov, string $sadrzaj, string $okvir = 'okvir'): never
{
    echo predlozak($okvir, ['naslov' => $naslov, 'sadrzaj' => $sadrzaj]);
    exit;
}
function zabranjeno(): never
{
    http_response_code(403);
    stranica('Zabranjeno', '<div class="alert alert-warning mt-3">Nemate pravo pristupa ovoj stranici.</div>');
}
function nije_pronadjeno(string $sto = 'Stranica'): never
{
    http_response_code(404);
    stranica('Nije pronađeno', '<div class="alert alert-secondary mt-3">' . e($sto) . ' nije pronađen(a).</div>');
}
function json(mixed $podaci): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($podaci, JSON_UNESCAPED_UNICODE);
    exit;
}
function sel(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}
function chk(mixed $v): string
{
    return $v ? ' checked' : '';
}

// ---------- Dnevnik ----------
function dnevnik(string $radnja, ?string $entitet = null, ?int $entitetId = null, ?string $detalji = null, ?array $tko = null): void
{
    $k = $tko ?? korisnik();
    umetni('Dnevnik', [
        'Vrijeme' => sada(),
        'KorisnikId' => $k['Id'] ?? null,
        'Korisnik' => $k['KorisnickoIme'] ?? null,
        'Radnja' => $radnja,
        'Entitet' => $entitet,
        'EntitetId' => $entitetId,
        'Detalji' => $detalji !== null ? mb_substr($detalji, 0, 2000) : null,
    ]);
}

// ---------- Učitavanje ostalih modula ----------
foreach (glob(__DIR__ . '/lib/*.php') as $__dat) {
    require_once $__dat;
}
unset($__dat);
