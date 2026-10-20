// EvidencijaPotpis – signiert Releases der Lovačka Evidencija (Ed25519).
// Der private Schlüssel bleibt auf diesem PC (Windows DPAPI). Die App prüft die Signatur vor jedem Update.
// Algorithmus muss exakt app/lib/azuriranje.php → azuriranje_manifest() entsprechen.

using System.IO.Compression;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.RegularExpressions;
using Org.BouncyCastle.Crypto.Parameters;
using Org.BouncyCastle.Crypto.Signers;

namespace EvidencijaPotpis;

internal static class Program
{
    const string Repo = "daxemann/lovacka-evidencija";
    static readonly Regex PotpisRegex = new(@"<!--\s*potpis-evidencije:\s*([A-Za-z0-9+/=]{80,100})\s*-->", RegexOptions.Compiled);
    static readonly string Ordner = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData), "EvidencijaPotpis");
    static string SchluesselDatei => Path.Combine(Ordner, "privat.key");
    static string TokenDatei => Path.Combine(Ordner, "github.token");

    static int Main(string[] args)
    {
        Console.OutputEncoding = Encoding.UTF8;
        try
        {
            if (args.Length == 3 && args[0] == "--manifest")
            {
                var m = Manifest(File.ReadAllBytes(args[1]), args[2]);
                Console.Write(Encoding.UTF8.GetString(m));
                return 0;
            }
            if (args.Length == 4 && args[0] == "--test-signieren")
            {
                var seed = Convert.FromBase64String(args[1]);
                var m = Manifest(File.ReadAllBytes(args[2]), args[3]);
                Console.WriteLine(Convert.ToBase64String(OeffentlicherSchluessel(seed)));
                Console.WriteLine(Convert.ToBase64String(Signieren(seed, m)));
                return 0;
            }
            return Menue().GetAwaiter().GetResult();
        }
        catch (Exception e)
        {
            Fehler("Fehler: " + e.Message);
            Warten();
            return 1;
        }
    }

    // ---------------- Menü ----------------

    static async Task<int> Menue()
    {
        Console.Title = "Lovačka Evidencija – Release signieren";
        Directory.CreateDirectory(Ordner);
        while (true)
        {
            Console.Clear();
            Titel("Lovačka Evidencija – Release signieren");
            var seed = SchluesselLaden();
            if (seed == null)
            {
                Console.WriteLine("Auf diesem PC ist noch kein Signierschlüssel.\n");
                Console.WriteLine("  [1] Neuen Schlüssel erzeugen (nur beim allerersten Mal!)");
                Console.WriteLine("  [2] Schlüssel aus Sicherung wiederherstellen (neuer PC)");
                Console.WriteLine("  [0] Ende");
                switch (Frage("\nAuswahl: "))
                {
                    case "1": SchluesselErzeugen(); break;
                    case "2": SchluesselWiederherstellen(); break;
                    case "0": return 0;
                }
                continue;
            }
            Console.WriteLine("Öffentlicher Schlüssel: " + Convert.ToBase64String(OeffentlicherSchluessel(seed)) + "\n");
            Console.WriteLine("  [1] Neuestes Release signieren");
            Console.WriteLine("  [2] Schlüsselsicherung erstellen (USB-Stick)");
            Console.WriteLine("  [3] GitHub-Token ändern");
            Console.WriteLine("  [0] Ende");
            switch (Frage("\nAuswahl: "))
            {
                case "1": await ReleaseSignieren(seed); Warten(); break;
                case "2": SicherungErstellen(seed); Warten(); break;
                case "3": TokenAbfragen(); break;
                case "0": return 0;
            }
        }
    }

    // ---------------- Release signieren ----------------

    static async Task ReleaseSignieren(byte[] seed)
    {
        var token = TokenLaden() ?? TokenAbfragen();
        using var http = Http(token);

        Console.WriteLine("\nLade Releases von GitHub …");
        var releases = JsonNode.Parse(await Get(http, $"https://api.github.com/repos/{Repo}/releases?per_page=20"))!.AsArray()
            .Where(r => r!["draft"]!.GetValue<bool>() == false && r["prerelease"]!.GetValue<bool>() == false).ToList();
        if (releases.Count == 0) { Fehler("Keine Releases gefunden."); return; }

        var neu = releases[0]!;
        var tag = neu["tag_name"]!.GetValue<string>();
        var body = neu["body"]?.GetValue<string>() ?? "";
        if (PotpisRegex.IsMatch(body))
        {
            Ok($"Das neueste Release {tag} ist bereits signiert. Nichts zu tun.");
            return;
        }

        // Vorheriges signiertes Release als Vergleichsbasis
        var basis = releases.Skip(1).FirstOrDefault(r => PotpisRegex.IsMatch(r!["body"]?.GetValue<string>() ?? ""))
                    ?? releases.Skip(1).FirstOrDefault();
        var basisTag = basis?["tag_name"]!.GetValue<string>();

        Titel($"Release {tag}  ·  {DateTime.Parse(neu["published_at"]!.GetValue<string>()).ToLocalTime():dd.MM.yyyy HH:mm}");
        if (basisTag != null)
        {
            var cmp = JsonNode.Parse(await Get(http, $"https://api.github.com/repos/{Repo}/compare/{basisTag}...{tag}"))!;
            var commits = cmp["commits"]!.AsArray();
            Console.WriteLine($"Änderungen seit {basisTag} ({commits.Count} Commits, {cmp["files"]?.AsArray().Count ?? 0} Dateien):\n");
            foreach (var c in commits.TakeLast(40))
            {
                var msg = c!["commit"]!["message"]!.GetValue<string>().Split('\n')[0];
                var wer = c["commit"]!["author"]!["name"]!.GetValue<string>();
                var wann = DateTime.Parse(c["commit"]!["author"]!["date"]!.GetValue<string>()).ToLocalTime();
                Console.WriteLine($"  {wann:dd.MM. HH:mm}  {Kurz(msg, 70)}  ({wer})");
            }
        }
        Console.WriteLine("\nRelease-Notizen (Anfang):");
        foreach (var z in body.Split('\n').Take(12)) Console.WriteLine("  " + Kurz(z.TrimEnd(), 100));

        Console.ForegroundColor = ConsoleColor.Yellow;
        Console.WriteLine("\nNur signieren, wenn du diese Änderungen kennst (z. B. mit Claude gemacht hast).");
        Console.WriteLine("Unbekannte Commits/Uhrzeiten = NICHT signieren – dann könnte jemand dein GitHub-Konto nutzen.");
        Console.ResetColor();
        if (!JaNein($"\n{tag} jetzt signieren? (j/n): ")) { Console.WriteLine("Abgebrochen."); return; }

        Console.WriteLine("Lade Programm-ZIP …");
        var zip = await GetBytes(http, $"https://api.github.com/repos/{Repo}/zipball/{tag}");
        var version = tag.TrimStart('v', 'V');
        var manifest = Manifest(zip, version);
        var signatur = Signieren(seed, manifest);
        var sigB64 = Convert.ToBase64String(signatur);

        var neuerBody = PotpisRegex.Replace(body, "").TrimEnd() + $"\n\n<!-- potpis-evidencije: {sigB64} -->\n";
        var patch = new StringContent(JsonSerializer.Serialize(new { body = neuerBody }), Encoding.UTF8, "application/json");
        var resp = await http.PatchAsync($"https://api.github.com/repos/{Repo}/releases/{neu["id"]!.GetValue<long>()}", patch);
        if (!resp.IsSuccessStatusCode)
            throw new Exception($"GitHub hat das Speichern abgelehnt ({(int)resp.StatusCode}). Token prüfen (Recht: Contents – Read and write).");

        // Kontrolle: Signatur von GitHub zurücklesen und prüfen
        var kontrolle = JsonNode.Parse(await Get(http, $"https://api.github.com/repos/{Repo}/releases/tags/{tag}"))!;
        var m = PotpisRegex.Match(kontrolle["body"]?.GetValue<string>() ?? "");
        if (!m.Success || !Pruefen(OeffentlicherSchluessel(seed), manifest, Convert.FromBase64String(m.Groups[1].Value)))
            throw new Exception("Kontrolle fehlgeschlagen – Signatur auf GitHub stimmt nicht.");
        Ok($"\n✓ {tag} ist signiert. Die Vereine können jetzt aktualisieren.");
    }

    // ---------------- Manifest (identisch zu azuriranje_manifest in PHP) ----------------

    internal static byte[] Manifest(byte[] zipBytes, string version)
    {
        using var zip = new ZipArchive(new MemoryStream(zipBytes), ZipArchiveMode.Read);
        if (zip.Entries.Count == 0) throw new Exception("ZIP ist leer.");
        var erster = zip.Entries[0].FullName;
        var prefix = erster.Contains('/') ? erster[..(erster.IndexOf('/') + 1)] : "";
        var zeilen = new List<(byte[] Rel, string Hash)>();
        string? osnova = null;
        foreach (var e in zip.Entries)
        {
            if (e.FullName.EndsWith('/')) continue;
            if (!e.FullName.StartsWith(prefix, StringComparison.Ordinal)) throw new Exception("ZIP enthält Datei außerhalb des Programms: " + e.FullName);
            using var s = e.Open();
            using var ms = new MemoryStream();
            s.CopyTo(ms);
            var inhalt = ms.ToArray();
            var rel = e.FullName[prefix.Length..];
            if (rel == "app/osnova.php") osnova = Encoding.UTF8.GetString(inhalt);
            zeilen.Add((Encoding.UTF8.GetBytes(rel), Convert.ToHexString(SHA256.HashData(inhalt)).ToLowerInvariant()));
        }
        var vm = osnova == null ? null : Regex.Match(osnova, @"const VERZIJA = '([^']+)';");
        if (vm == null || !vm.Success) throw new Exception("ZIP enthält keine Lovačka Evidencija.");
        if (vm.Groups[1].Value != version) throw new Exception($"Version im ZIP ({vm.Groups[1].Value}) passt nicht zum Release ({version}).");

        zeilen.Sort((a, b) => ByteVergleich(a.Rel, b.Rel));
        var sb = new MemoryStream();
        void Schreib(string t) { var b = Encoding.UTF8.GetBytes(t); sb.Write(b); }
        Schreib("lovacka-evidencija\n" + version + "\n");
        foreach (var z in zeilen) { Schreib(z.Hash + " "); sb.Write(z.Rel); Schreib("\n"); }
        return sb.ToArray();
    }

    static int ByteVergleich(byte[] a, byte[] b)
    {
        var n = Math.Min(a.Length, b.Length);
        for (var i = 0; i < n; i++) if (a[i] != b[i]) return a[i].CompareTo(b[i]);
        return a.Length.CompareTo(b.Length);
    }

    // ---------------- Ed25519 ----------------

    internal static byte[] OeffentlicherSchluessel(byte[] seed) => new Ed25519PrivateKeyParameters(seed, 0).GeneratePublicKey().GetEncoded();

    internal static byte[] Signieren(byte[] seed, byte[] nachricht)
    {
        var s = new Ed25519Signer();
        s.Init(true, new Ed25519PrivateKeyParameters(seed, 0));
        s.BlockUpdate(nachricht, 0, nachricht.Length);
        return s.GenerateSignature();
    }

    static bool Pruefen(byte[] pub, byte[] nachricht, byte[] sig)
    {
        var s = new Ed25519Signer();
        s.Init(false, new Ed25519PublicKeyParameters(pub, 0));
        s.BlockUpdate(nachricht, 0, nachricht.Length);
        return s.VerifySignature(sig);
    }

    // ---------------- Schlüssel ----------------

    static byte[]? SchluesselLaden()
    {
        if (!File.Exists(SchluesselDatei)) return null;
        return Entschuetzen(Convert.FromBase64String(File.ReadAllText(SchluesselDatei).Trim()));
    }

    static void SchluesselSpeichern(byte[] seed) => File.WriteAllText(SchluesselDatei, Convert.ToBase64String(Schuetzen(seed)));

    static void SchluesselErzeugen()
    {
        Console.ForegroundColor = ConsoleColor.Yellow;
        Console.WriteLine("\nACHTUNG: Nur beim allerersten Mal! Ein neuer Schlüssel passt nicht zu bereits");
        Console.WriteLine("installierten Evidencijas mit altem Schlüssel.");
        Console.ResetColor();
        if (!JaNein("Neuen Schlüssel erzeugen? (j/n): ")) return;
        var seed = RandomNumberGenerator.GetBytes(32);
        SchluesselSpeichern(seed);
        var pub = Convert.ToBase64String(OeffentlicherSchluessel(seed));
        var datei = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "evidencija-oeffentlicher-schluessel.txt");
        try { File.WriteAllText(datei, pub + Environment.NewLine); } catch { datei = "(Desktop nicht beschreibbar)"; }
        Ok("\n✓ Schlüssel erzeugt.");
        Console.WriteLine("\nÖffentlicher Schlüssel (darf jeder sehen – an Claude schicken):\n");
        Console.ForegroundColor = ConsoleColor.Cyan; Console.WriteLine("  " + pub + "\n"); Console.ResetColor();
        Console.WriteLine("Gespeichert auch auf dem Desktop: " + datei);
        Console.WriteLine("\nJetzt sofort eine Sicherung auf USB-Stick machen – ohne Sicherung ist der");
        Console.WriteLine("Schlüssel bei PC-Defekt weg und die Vereine bekommen keine Updates mehr automatisch.");
        Warten();
        SicherungErstellen(seed);
        Warten();
    }

    static void SicherungErstellen(byte[] seed)
    {
        Console.WriteLine("\nSicherung wird mit einem eigenen Passwort verschlüsselt (min. 12 Zeichen).");
        var pw = PasswortNeu();
        var salt = RandomNumberGenerator.GetBytes(16);
        var nonce = RandomNumberGenerator.GetBytes(12);
        var key = Rfc2898DeriveBytes.Pbkdf2(pw, salt, 600_000, HashAlgorithmName.SHA256, 32);
        var ct = new byte[seed.Length];
        var tag = new byte[16];
        using (var aes = new AesGcm(key, 16)) aes.Encrypt(nonce, seed, ct, tag);
        var inhalt = "EVP1\n" + Convert.ToBase64String(salt.Concat(nonce).Concat(tag).Concat(ct).ToArray()) + "\n";
        var ziel = Frage("Speicherort (z. B. E:\\  für USB-Stick, leer = Desktop): ");
        if (string.IsNullOrWhiteSpace(ziel)) ziel = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
        var datei = Path.Combine(ziel.Trim('"'), "EvidencijaPotpis-Schluesselsicherung.txt");
        File.WriteAllText(datei, inhalt);
        Ok("✓ Sicherung gespeichert: " + datei);
        Console.WriteLine("Stick + Passwort getrennt aufbewahren. Kopie vom Desktop danach löschen.");
    }

    static void SchluesselWiederherstellen()
    {
        var datei = Frage("\nPfad zur Datei EvidencijaPotpis-Schluesselsicherung.txt: ").Trim('"');
        var zeilen = File.ReadAllLines(datei);
        if (zeilen.Length < 2 || zeilen[0] != "EVP1") throw new Exception("Keine gültige Schlüsselsicherung.");
        var b = Convert.FromBase64String(zeilen[1]);
        var pw = PasswortLesen("Passwort der Sicherung: ");
        var key = Rfc2898DeriveBytes.Pbkdf2(pw, b[..16], 600_000, HashAlgorithmName.SHA256, 32);
        var seed = new byte[32];
        try { using var aes = new AesGcm(key, 16); aes.Decrypt(b[16..28], b[44..], b[28..44], seed); }
        catch (CryptographicException) { throw new Exception("Falsches Passwort."); }
        SchluesselSpeichern(seed);
        Ok("✓ Schlüssel wiederhergestellt. Öffentlicher Schlüssel: " + Convert.ToBase64String(OeffentlicherSchluessel(seed)));
        Warten();
    }

    // ---------------- GitHub-Token ----------------

    static string? TokenLaden() => File.Exists(TokenDatei) ? Encoding.UTF8.GetString(Entschuetzen(Convert.FromBase64String(File.ReadAllText(TokenDatei).Trim()))) : null;

    static string TokenAbfragen()
    {
        Console.WriteLine("\nGitHub-Token wird gebraucht, um die Signatur ins Release zu schreiben.");
        Console.WriteLine("Erstellen: github.com → Settings → Developer settings → Fine-grained tokens → Generate new token");
        Console.WriteLine($"  Repository access: Only select repositories → {Repo}");
        Console.WriteLine("  Permissions → Contents: Read and write   (sonst nichts)");
        var t = PasswortLesen("Token einfügen: ").Trim();
        if (t.Length < 20) throw new Exception("Token zu kurz.");
        File.WriteAllText(TokenDatei, Convert.ToBase64String(Schuetzen(Encoding.UTF8.GetBytes(t))));
        Ok("✓ Token gespeichert (nur für diesen Windows-Benutzer lesbar).");
        return t;
    }

    // ---------------- Hilfen ----------------

    static byte[] Schuetzen(byte[] d) => OperatingSystem.IsWindows() ? ProtectedData.Protect(d, null, DataProtectionScope.CurrentUser) : d;
    static byte[] Entschuetzen(byte[] d) => OperatingSystem.IsWindows() ? ProtectedData.Unprotect(d, null, DataProtectionScope.CurrentUser) : d;

    static HttpClient Http(string token)
    {
        var h = new HttpClient(new HttpClientHandler { AllowAutoRedirect = true }) { Timeout = TimeSpan.FromMinutes(5) };
        h.DefaultRequestHeaders.UserAgent.ParseAdd("EvidencijaPotpis/1.0");
        h.DefaultRequestHeaders.Accept.ParseAdd("application/vnd.github+json");
        h.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", token);
        h.DefaultRequestHeaders.Add("X-GitHub-Api-Version", "2022-11-28");
        return h;
    }

    static async Task<string> Get(HttpClient h, string url)
    {
        var r = await h.GetAsync(url);
        if (!r.IsSuccessStatusCode) throw new Exception($"GitHub antwortet {(int)r.StatusCode} für {url}");
        return await r.Content.ReadAsStringAsync();
    }

    static async Task<byte[]> GetBytes(HttpClient h, string url)
    {
        var r = await h.GetAsync(url);
        if (!r.IsSuccessStatusCode) throw new Exception($"Download fehlgeschlagen ({(int)r.StatusCode}).");
        return await r.Content.ReadAsByteArrayAsync();
    }

    static string PasswortNeu()
    {
        while (true)
        {
            var a = PasswortLesen("Passwort: ");
            if (a.Length < 12) { Fehler("Mindestens 12 Zeichen."); continue; }
            if (PasswortLesen("Wiederholen: ") != a) { Fehler("Passwörter verschieden."); continue; }
            return a;
        }
    }

    static string PasswortLesen(string text)
    {
        Console.Write(text);
        if (Console.IsInputRedirected) return Console.ReadLine() ?? "";
        var sb = new StringBuilder();
        while (true)
        {
            var k = Console.ReadKey(true);
            if (k.Key == ConsoleKey.Enter) break;
            if (k.Key == ConsoleKey.Backspace) { if (sb.Length > 0) { sb.Length--; Console.Write("\b \b"); } continue; }
            if (!char.IsControl(k.KeyChar)) { sb.Append(k.KeyChar); Console.Write('*'); }
        }
        Console.WriteLine();
        return sb.ToString();
    }

    static string Frage(string t) { Console.Write(t); return Console.ReadLine()?.Trim() ?? ""; }
    static bool JaNein(string t) => Frage(t).StartsWith("j", StringComparison.OrdinalIgnoreCase);
    static void Warten() { if (!Console.IsInputRedirected) { Console.Write("\nWeiter mit Enter …"); Console.ReadLine(); } }
    static string Kurz(string s, int n) => s.Length <= n ? s : s[..(n - 1)] + "…";
    static void Titel(string t) { Console.ForegroundColor = ConsoleColor.Green; Console.WriteLine(t + "\n" + new string('─', Math.Min(t.Length, 78)) + "\n"); Console.ResetColor(); }
    static void Ok(string t) { Console.ForegroundColor = ConsoleColor.Green; Console.WriteLine(t); Console.ResetColor(); }
    static void Fehler(string t) { Console.ForegroundColor = ConsoleColor.Red; Console.WriteLine(t); Console.ResetColor(); }
}
