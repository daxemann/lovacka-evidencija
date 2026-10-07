-- Shema identična .NET verziji (EF Core migracije) – baze su međusobno zamjenjive.
CREATE TABLE "__EFMigrationsHistory" (
    "MigrationId" TEXT NOT NULL CONSTRAINT "PK___EFMigrationsHistory" PRIMARY KEY,
    "ProductVersion" TEXT NOT NULL
);
CREATE TABLE "Dnevnik" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Dnevnik" PRIMARY KEY AUTOINCREMENT,
    "Vrijeme" TEXT NOT NULL,
    "KorisnikId" INTEGER NULL,
    "Korisnik" TEXT NULL,
    "Radnja" TEXT NOT NULL,
    "Entitet" TEXT NULL,
    "EntitetId" INTEGER NULL,
    "Detalji" TEXT NULL
);
CREATE TABLE "Funkcije" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Funkcije" PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "Redoslijed" INTEGER NOT NULL
, "OslobodjenClanarine" INTEGER NOT NULL DEFAULT 0);
CREATE TABLE "Postavke" (
    "Kljuc" TEXT NOT NULL CONSTRAINT "PK_Postavke" PRIMARY KEY,
    "Vrijednost" TEXT NULL
);
CREATE TABLE "ResetiLozinki" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_ResetiLozinki" PRIMARY KEY AUTOINCREMENT,
    "KorisnikId" INTEGER NOT NULL,
    "TokenHash" TEXT NOT NULL,
    "VrijediDo" TEXT NOT NULL,
    "Iskoristen" INTEGER NOT NULL
);
CREATE TABLE "Sekcije" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Sekcije" PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "Aktivna" INTEGER NOT NULL,
    "Redoslijed" INTEGER NOT NULL
);
CREATE TABLE "Uloge" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Uloge" PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "Opis" TEXT NULL,
    "Prava" INTEGER NOT NULL,
    "Sustavna" INTEGER NOT NULL
, "SveSekcije" INTEGER NOT NULL DEFAULT 0);
CREATE TABLE "VrsteAkcija" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_VrsteAkcija" PRIMARY KEY AUTOINCREMENT,
    "Naziv" TEXT NOT NULL,
    "StandardniBodovi" TEXT NOT NULL,
    "Aktivna" INTEGER NOT NULL
);
CREATE TABLE "Clanovi" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Clanovi" PRIMARY KEY AUTOINCREMENT,
    "Ime" TEXT NOT NULL,
    "Prezime" TEXT NOT NULL,
    "Nadimak" TEXT NULL,
    "SekcijaId" INTEGER NULL,
    "Ulica" TEXT NULL,
    "KucniBroj" TEXT NULL,
    "PostanskiBroj" TEXT NULL,
    "Mjesto" TEXT NULL,
    "FiksniTelefon" TEXT NULL,
    "MobilniTelefon" TEXT NULL,
    "Email" TEXT NULL,
    "Oib" TEXT NULL,
    "DatumRodjenja" TEXT NULL,
    "MjestoRodjenja" TEXT NULL,
    "BrojIskaznice" TEXT NULL,
    "IskaznicaVrijediDo" TEXT NULL,
    "IspitDatum" TEXT NULL,
    "IspitMjesto" TEXT NULL,
    "BrojDiplome" TEXT NULL,
    "IzdavacDiplome" TEXT NULL,
    "LovnaJedinica" TEXT NULL,
    "ClanOd" TEXT NULL,
    "Odlikovanja" TEXT NULL,
    "Skolovanja" TEXT NULL,
    "Napomena" TEXT NULL,
    "Status" INTEGER NOT NULL,
    "DatumIzlaska" TEXT NULL,
    "RazlogIzlaska" TEXT NULL,
    "FotoDatoteka" TEXT NULL,
    "Kreirano" TEXT NOT NULL,
    "Azurirano" TEXT NOT NULL, "UvozOznaka" TEXT NULL,
    CONSTRAINT "FK_Clanovi_Sekcije_SekcijaId" FOREIGN KEY ("SekcijaId") REFERENCES "Sekcije" ("Id") ON DELETE SET NULL
);
CREATE TABLE "Clanarine" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Clanarine" PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL,
    "Godina" INTEGER NOT NULL,
    "Iznos" TEXT NOT NULL,
    "Valuta" TEXT NOT NULL,
    "Napomena" TEXT NULL,
    CONSTRAINT "FK_Clanarine_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE CASCADE
);
CREATE TABLE "Korisnici" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Korisnici" PRIMARY KEY AUTOINCREMENT,
    "KorisnickoIme" TEXT NOT NULL,
    "LozinkaHash" TEXT NOT NULL,
    "ClanId" INTEGER NULL,
    "PrikaznoIme" TEXT NULL,
    "Aktivan" INTEGER NOT NULL,
    "MoraPromijenitiLozinku" INTEGER NOT NULL,
    "Kreirano" TEXT NOT NULL,
    "ZadnjaPrijava" TEXT NULL,
    "NeuspjelePrijave" INTEGER NOT NULL,
    "ZakljucanDo" TEXT NULL,
    "SigurnosniZig" TEXT NOT NULL, "Odobren" INTEGER NOT NULL DEFAULT 1, "OdobrenDatum" TEXT NULL, "OdobrioIme" TEXT NULL, "SuglasnostDatum" TEXT NULL,
    CONSTRAINT "FK_Korisnici_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE SET NULL
);
CREATE TABLE "RadneAkcije" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_RadneAkcije" PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL,
    "Datum" TEXT NOT NULL,
    "VrstaAkcijeId" INTEGER NULL,
    "VrstaSlobodno" TEXT NULL,
    "Sati" TEXT NULL,
    "Opis" TEXT NULL,
    "Status" INTEGER NOT NULL,
    "Bodovi" TEXT NULL,
    "RazlogOdbijanja" TEXT NULL,
    "FotoDatoteka" TEXT NULL,
    "UnioKorisnikId" INTEGER NULL,
    "Kreirano" TEXT NOT NULL,
    "OdlucioKorisnikId" INTEGER NULL,
    "OdlucioIme" TEXT NULL,
    "Odluceno" TEXT NULL,
    CONSTRAINT "FK_RadneAkcije_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_RadneAkcije_VrsteAkcija_VrstaAkcijeId" FOREIGN KEY ("VrstaAkcijeId") REFERENCES "VrsteAkcija" ("Id") ON DELETE SET NULL
);
CREATE TABLE "Uplate" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Uplate" PRIMARY KEY AUTOINCREMENT,
    "ClanarinaId" INTEGER NOT NULL,
    "Datum" TEXT NOT NULL,
    "Iznos" TEXT NOT NULL,
    "Napomena" TEXT NULL,
    CONSTRAINT "FK_Uplate_Clanarine_ClanarinaId" FOREIGN KEY ("ClanarinaId") REFERENCES "Clanarine" ("Id") ON DELETE CASCADE
);
CREATE TABLE "KorisnikUloge" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_KorisnikUloge" PRIMARY KEY AUTOINCREMENT,
    "KorisnikId" INTEGER NOT NULL,
    "UlogaId" INTEGER NOT NULL,
    "SekcijaId" INTEGER NULL,
    CONSTRAINT "FK_KorisnikUloge_Korisnici_KorisnikId" FOREIGN KEY ("KorisnikId") REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_KorisnikUloge_Sekcije_SekcijaId" FOREIGN KEY ("SekcijaId") REFERENCES "Sekcije" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_KorisnikUloge_Uloge_UlogaId" FOREIGN KEY ("UlogaId") REFERENCES "Uloge" ("Id") ON DELETE CASCADE
);
CREATE TABLE "Pozivnice" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Pozivnice" PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL,
    "TokenHash" TEXT NOT NULL,
    "Kreirano" TEXT NOT NULL,
    "VrijediDo" TEXT NOT NULL,
    "KreiraoIme" TEXT NULL,
    "Iskoristena" TEXT NULL,
    "Opozvana" INTEGER NOT NULL,
    CONSTRAINT "FK_Pozivnice_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE CASCADE
);
CREATE TABLE "PlaniraneUloge" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_PlaniraneUloge" PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL,
    "UlogaId" INTEGER NOT NULL,
    "SekcijaId" INTEGER NULL,
    CONSTRAINT "FK_PlaniraneUloge_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_PlaniraneUloge_Sekcije_SekcijaId" FOREIGN KEY ("SekcijaId") REFERENCES "Sekcije" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_PlaniraneUloge_Uloge_UlogaId" FOREIGN KEY ("UlogaId") REFERENCES "Uloge" ("Id") ON DELETE CASCADE
);
CREATE TABLE "ClanFunkcije" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_ClanFunkcije" PRIMARY KEY AUTOINCREMENT,
    "ClanId" INTEGER NOT NULL,
    "Do" TEXT NULL,
    "FunkcijaId" INTEGER NOT NULL,
    "Od" TEXT NULL,
    "SekcijaId" INTEGER NULL,
    CONSTRAINT "FK_ClanFunkcije_Clanovi_ClanId" FOREIGN KEY ("ClanId") REFERENCES "Clanovi" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_ClanFunkcije_Funkcije_FunkcijaId" FOREIGN KEY ("FunkcijaId") REFERENCES "Funkcije" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_ClanFunkcije_Sekcije_SekcijaId" FOREIGN KEY ("SekcijaId") REFERENCES "Sekcije" ("Id") ON DELETE SET NULL
);
CREATE TABLE "Oglasi" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Oglasi" PRIMARY KEY AUTOINCREMENT,
    "KorisnikId" INTEGER NOT NULL,
    "Kategorija" INTEGER NOT NULL,
    "Naslov" TEXT NOT NULL,
    "Opis" TEXT NULL,
    "Cijena" TEXT NULL,
    "PoDogovoru" INTEGER NOT NULL,
    "Stanje" INTEGER NOT NULL,
    "Mjesto" TEXT NULL,
    "PrikaziTelefon" INTEGER NOT NULL,
    "Status" INTEGER NOT NULL,
    "Pregleda" INTEGER NOT NULL,
    "Kreirano" TEXT NOT NULL,
    "Azurirano" TEXT NOT NULL,
    CONSTRAINT "FK_Oglasi_Korisnici_KorisnikId" FOREIGN KEY ("KorisnikId") REFERENCES "Korisnici" ("Id") ON DELETE CASCADE
);
CREATE TABLE "OglasSlike" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_OglasSlike" PRIMARY KEY AUTOINCREMENT,
    "OglasId" INTEGER NOT NULL,
    "Datoteka" TEXT NOT NULL,
    "Redoslijed" INTEGER NOT NULL,
    CONSTRAINT "FK_OglasSlike_Oglasi_OglasId" FOREIGN KEY ("OglasId") REFERENCES "Oglasi" ("Id") ON DELETE CASCADE
);
CREATE TABLE "Razgovori" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_Razgovori" PRIMARY KEY AUTOINCREMENT,
    "OglasId" INTEGER NOT NULL,
    "KupacId" INTEGER NOT NULL,
    "Kreirano" TEXT NOT NULL,
    "ZadnjaPoruka" TEXT NOT NULL,
    CONSTRAINT "FK_Razgovori_Korisnici_KupacId" FOREIGN KEY ("KupacId") REFERENCES "Korisnici" ("Id") ON DELETE CASCADE,
    CONSTRAINT "FK_Razgovori_Oglasi_OglasId" FOREIGN KEY ("OglasId") REFERENCES "Oglasi" ("Id") ON DELETE CASCADE
);
CREATE TABLE "PorukeRazgovora" (
    "Id" INTEGER NOT NULL CONSTRAINT "PK_PorukeRazgovora" PRIMARY KEY AUTOINCREMENT,
    "RazgovorId" INTEGER NOT NULL,
    "PosiljateljId" INTEGER NOT NULL,
    "Tekst" TEXT NOT NULL,
    "Vrijeme" TEXT NOT NULL,
    "Procitano" INTEGER NOT NULL,
    CONSTRAINT "FK_PorukeRazgovora_Razgovori_RazgovorId" FOREIGN KEY ("RazgovorId") REFERENCES "Razgovori" ("Id") ON DELETE CASCADE
);
CREATE UNIQUE INDEX "IX_Clanarine_ClanId_Godina" ON "Clanarine" ("ClanId", "Godina");
CREATE INDEX "IX_Clanovi_Prezime_Ime" ON "Clanovi" ("Prezime", "Ime");
CREATE INDEX "IX_Clanovi_SekcijaId" ON "Clanovi" ("SekcijaId");
CREATE INDEX "IX_Dnevnik_Vrijeme" ON "Dnevnik" ("Vrijeme");
CREATE UNIQUE INDEX "IX_Korisnici_ClanId" ON "Korisnici" ("ClanId");
CREATE UNIQUE INDEX "IX_Korisnici_KorisnickoIme" ON "Korisnici" ("KorisnickoIme");
CREATE INDEX "IX_KorisnikUloge_KorisnikId" ON "KorisnikUloge" ("KorisnikId");
CREATE INDEX "IX_KorisnikUloge_SekcijaId" ON "KorisnikUloge" ("SekcijaId");
CREATE INDEX "IX_KorisnikUloge_UlogaId" ON "KorisnikUloge" ("UlogaId");
CREATE INDEX "IX_RadneAkcije_ClanId" ON "RadneAkcije" ("ClanId");
CREATE INDEX "IX_RadneAkcije_Status_Datum" ON "RadneAkcije" ("Status", "Datum");
CREATE INDEX "IX_RadneAkcije_VrstaAkcijeId" ON "RadneAkcije" ("VrstaAkcijeId");
CREATE UNIQUE INDEX "IX_Sekcije_Naziv" ON "Sekcije" ("Naziv");
CREATE INDEX "IX_Uplate_ClanarinaId" ON "Uplate" ("ClanarinaId");
CREATE INDEX "IX_Pozivnice_ClanId" ON "Pozivnice" ("ClanId");
CREATE UNIQUE INDEX "IX_Pozivnice_TokenHash" ON "Pozivnice" ("TokenHash");
CREATE INDEX "IX_PlaniraneUloge_ClanId" ON "PlaniraneUloge" ("ClanId");
CREATE INDEX "IX_PlaniraneUloge_SekcijaId" ON "PlaniraneUloge" ("SekcijaId");
CREATE INDEX "IX_PlaniraneUloge_UlogaId" ON "PlaniraneUloge" ("UlogaId");
CREATE INDEX "IX_ClanFunkcije_ClanId" ON "ClanFunkcije" ("ClanId");
CREATE INDEX "IX_ClanFunkcije_FunkcijaId" ON "ClanFunkcije" ("FunkcijaId");
CREATE INDEX "IX_ClanFunkcije_SekcijaId" ON "ClanFunkcije" ("SekcijaId");
CREATE INDEX "IX_Oglasi_KorisnikId" ON "Oglasi" ("KorisnikId");
CREATE INDEX "IX_Oglasi_Status_Kategorija" ON "Oglasi" ("Status", "Kategorija");
CREATE INDEX "IX_OglasSlike_OglasId" ON "OglasSlike" ("OglasId");
CREATE INDEX "IX_PorukeRazgovora_RazgovorId_Procitano" ON "PorukeRazgovora" ("RazgovorId", "Procitano");
CREATE INDEX "IX_Razgovori_KupacId" ON "Razgovori" ("KupacId");
CREATE UNIQUE INDEX "IX_Razgovori_OglasId_KupacId" ON "Razgovori" ("OglasId", "KupacId");
INSERT INTO "Funkcije" VALUES (1,'Predsjednik',1,0);
INSERT INTO "Funkcije" VALUES (2,'Dopredsjednik',2,0);
INSERT INTO "Funkcije" VALUES (3,'Tajnik',3,0);
INSERT INTO "Funkcije" VALUES (4,'Blagajnik',4,0);
INSERT INTO "Funkcije" VALUES (5,'Upravni odbor',5,0);
INSERT INTO "Funkcije" VALUES (6,'Nadzorni odbor',6,0);
INSERT INTO "Funkcije" VALUES (7,'Lovnik',7,0);
INSERT INTO "Funkcije" VALUES (8,'Pomoćni lovnik',8,0);
INSERT INTO "Funkcije" VALUES (9,'Lovočuvar',9,0);
INSERT INTO "Funkcije" VALUES (10,'Počasni član',10,1);
INSERT INTO "VrsteAkcija" VALUES (1,'Košnja','5.0',1);
INSERT INTO "VrsteAkcija" VALUES (2,'Hranilište / prihrana','5.0',1);
INSERT INTO "VrsteAkcija" VALUES (3,'Izgradnja / popravak čeke','8.0',1);
INSERT INTO "VrsteAkcija" VALUES (4,'Lovačka kuća','5.0',1);
INSERT INTO "VrsteAkcija" VALUES (5,'Sadnja','5.0',1);
INSERT INTO "VrsteAkcija" VALUES (6,'Ostalo','0.0',1);
INSERT INTO "Uloge" VALUES (1,'Glavni admin','Sva prava, uključujući korisnike, uloge i sekcije',255,1,1);
INSERT INTO "Uloge" VALUES (2,'Predsjednik / Tajnik',NULL,119,0,0);
INSERT INTO "Uloge" VALUES (3,'Blagajnik',NULL,77,0,1);
INSERT INTO "Uloge" VALUES (4,'Lovnik / Voditelj radova',NULL,113,0,0);
INSERT INTO "Uloge" VALUES (5,'Admin sekcije','Dodijeliti s ograničenjem na sekciju',119,0,0);
INSERT INTO "Uloge" VALUES (6,'Nadzorni odbor',NULL,85,0,0);
INSERT INTO "Uloge" VALUES (7,'Domar','Vodi radne akcije: unosi akcije i bodove za sve članove, odobrava prijave',112,0,0);
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006090848_Pocetna','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006095238_Pozivnice','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006102442_UlogaDomar','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006103310_PocasniClan','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006104339_UvozOznaka','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006135632_SekcijeOdvojene','9.0.20');
INSERT INTO "__EFMigrationsHistory" VALUES ('20261006214631_Oglasnik','9.0.20');
