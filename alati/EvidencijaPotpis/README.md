# EvidencijaPotpis

Signiert Releases der Lovačka Evidencija mit Ed25519. Die App installiert nur Updates mit gültiger Signatur.
Der private Schlüssel liegt nur auf dem PC des Autors (Windows DPAPI) und auf einer passwortgeschützten Sicherung – nie auf GitHub.

## Erstes Mal
1. `EvidencijaPotpis.exe` starten (Windows-Warnung „Unbekannter Herausgeber“ → *Weitere Informationen* → *Trotzdem ausführen*).
2. **[1] Neuen Schlüssel erzeugen** – öffentlicher Schlüssel erscheint und liegt als Datei auf dem Desktop.
3. Sofort **Sicherung auf USB-Stick** mit eigenem Passwort (≥ 12 Zeichen). Stick und Passwort getrennt aufbewahren.
4. Öffentlichen Schlüssel in `app/lib/azuriranje.php` → `AZURIRANJE_JAVNI_KLJUC` eintragen (lassen).

## Jedes Release
1. Release entsteht wie bisher automatisch auf GitHub.
2. Tool starten → **[1] Neuestes Release signieren**.
3. Commit-Liste prüfen: nur signieren, wenn du die Änderungen kennst.
4. Signatur landet als unsichtbarer Kommentar in den Release-Notizen; die Vereine können aktualisieren.

Beim ersten Signieren fragt das Tool nach einem GitHub-Token (fine-grained, nur dieses Repo, *Contents: Read and write*).

## Neuer PC
Tool starten → **[2] Schlüssel aus Sicherung wiederherstellen** → Datei vom Stick + Passwort.

## Selbst bauen
```
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true -p:EnableCompressionInSingleFile=true
```
Der Manifest-Algorithmus muss exakt `azuriranje_manifest()` in `app/lib/azuriranje.php` entsprechen.
