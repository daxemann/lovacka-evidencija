import sys, json
W=sys.argv[1]
URL="https://evidencija.freedev.app/"
DEMO="https://evidencija.freedev.app/evidencija/"
AUTOR="https://evidencija.freedev.app/evidencija/index.php?p=demo-autor"
GH="https://github.com/daxemann/lovacka-evidencija"
def glava(naslov, opis, put, dodatno=""):
    return f'''<!doctype html>
<html lang="hr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{naslov}</title>
<meta name="description" content="{opis}">
<link rel="canonical" href="{URL}{put}">
<meta property="og:type" content="website">
<meta property="og:title" content="{naslov}">
<meta property="og:description" content="{opis}">
<meta property="og:url" content="{URL}{put}">
<meta property="og:image" content="{URL}static/img/d-pocetna.jpg">
<meta property="og:locale" content="hr_HR">
<meta name="theme-color" content="#1B3416">
<link rel="icon" type="image/png" href="static/img/favicon.png">
<link rel="apple-touch-icon" href="static/img/ikona-180.png">
<link rel="preload" href="static/fonts/archivo-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="static/stil.css">
{dodatno}</head>
<body>
<header>
<nav aria-label="Glavni izbornik">
<a class="logo" href="./"><img src="static/img/logo.svg" alt="" width="40" height="40"><span>Lovačka evidencija</span></a>
<div class="izbornik">
<a href="./#iznutra">Kako izgleda</a>
<a href="./#mogucnosti">Mogućnosti</a>
<a href="./#pomoc">Pomoć pri instalaciji</a>
<a href="./#pitanja">Pitanja</a>
<a class="gumb zlatni" href="{DEMO}">Isprobaj demo</a>
</div>
</nav>
</header>
<main>
'''
PODNOZJE=f'''</main>
<footer>
<div class="okvir">
<div class="lijevo"><img src="static/img/logo.svg" alt="" width="28" height="28"><span>Lovačka evidencija · od lovca za lovce · privatni, nekomercijalni projekt</span></div>
<div class="veze">
<a href="{AUTOR}">Kontakt</a>
<a href="impresum.html">Impresum</a>
<a href="privatnost.html">Zaštita podataka</a>
<a href="{GH}">GitHub</a>
</div>
</div>
</footer>
'''
desk=[("d-pocetna","Početna uprave"),("d-clanarina","Članarina"),("d-revir","Karta lovišta"),("d-dezinfekcija","Knjiga dezinfekcije"),("d-kalendar","Kalendar")]
mob=[("m-pocetna","Početna člana"),("m-uplatnica","Uplatnica"),("m-revir","Karta lovišta"),("l-dezinfekcija_mobilna","Mobilna stanica"),("m-kalendar","Kalendar")]
def slike(lista, w, h, prva_eager=True):
    return "\n".join(f'<img src="static/img/{f}.jpg" alt="{a}" width="{w}" height="{h}"{" class=\"vidljiv\"" if i==0 else ""} loading="{"eager" if i==0 and prva_eager else "lazy"}" decoding="async">' for i,(f,a) in enumerate(lista))
tabovi=[("d-pocetna","Početna uprave","Na prvi pogled: članovi, otvorene članarine, radne akcije na čekanju."),
("d-clanovi","Članovi","Svi podaci, fotografije i funkcije – po sekcijama, s pretragom."),
("d-clanarina","Članarina u ratama","Tko je platio, tko kasni – HUB-3 uplatnice jednim klikom."),
("d-dezinfekcija","Knjiga dezinfekcije","Svi upisi sa stanica, PDF za lovnog inspektora."),
("d-revir","Karta lovišta","Granice, čeke i tko trenutno sjedi gdje."),
("d-revir_dnevnik","Lovački dnevnik","Tko je bio u lovištu, kada i koliko dugo."),
("d-kalendar","Kalendar","Termini udruge i sekcija, tko dolazi."),
("d-domovi","Lovački domovi","Rezervacije po danima, bez dvostrukih termina.")]
tab_html="\n".join(f'<button type="button" class="tab" role="tab" id="tab-{i}" aria-controls="iznutra-prikaz" aria-selected="{"true" if i==0 else "false"}"><b>{n}</b><span>{o}</span><i class="traka"></i></button>' for i,(f,n,o) in enumerate(tabovi))
tab_slike="\n".join(f'<img src="static/img/{f}.jpg" alt="{n}" width="1600" height="1000"{" class=\"vidljiv\"" if i==0 else ""} decoding="async">' for i,(f,n,o) in enumerate(tabovi))
moguc=[("Članovi","Osobni i lovački podaci, fotografija, funkcije, iskaznica i ispit, arhiva bivših članova, uvoz iz Google kontakata."),
("Sekcije strogo odvojene","Svaka lovna jedinica vidi samo sebe. Uprava vidi sve."),
("Članarina i HUB-3 uplatnice","Jednokratno ili 1–12 rata. Član vidi samo svoju uplatnicu – ispis ili kod na mobitelu za banku."),
("Radne akcije i bodovi","Članovi prijavljuju sami s fotografijom, uprava odobrava. Izvještaji po lovnoj godini."),
("Knjiga dezinfekcije (ASK)","QR oznaka na stanici, provjera GPS-a, dolazak/odlazak, mobilna stanica za skupni lov. Pristup za lovnog inspektora."),
("Lovište i čeke","Karta lovišta, zauzimanje čeke jednim dodirom, automatsko oslobađanje u 03:00, prijava kvara s fotografijom."),
("Lovački dnevnik","Tko je bio gdje i kada – po sekcijama, s filtrom po vrsti posjeta."),
("Grupni lov – karta uživo i SOS","Poziv svima na mobitel, položaj sudionika na karti, SOS organizatoru. Popis sudionika skeniranjem iskaznica."),
("Članska iskaznica s QR kodom","U formatu bankovne kartice za ispis i u aplikaciji – radi i bez signala."),
("Kalendar i lovački domovi","Termini s „dolazim / ne dolazim“, rezervacija domova po danima bez dvostrukih rezervacija."),
("Push obavijesti","Na mobitel i kad je aplikacija zatvorena – Android i iPhone. Obavijesti s potvrdom čitanja."),
("Psi, inventura, oglasnik","Bodovi za pse, popis opreme, statut i pravilnici, oglasnik za članove, poruke preko WhatsAppa ili Vibera.")]
moguc_html="\n".join(f'<div><h3>{n}</h3><p>{o}</p></div>' for n,o in moguc)
pitanja=[("Je li stvarno besplatno? Gdje je kvaka?","Nema kvake. Privatni, nekomercijalni projekt. Softver je i ostat će besplatan – bez pretplate, bez naplate po članu, bez plaćenih dodataka. I pomoć pri postavljanju je besplatna."),
("Tko vidi podatke naše udruge?","Samo vi. Aplikacija radi na vašem serveru, podaci se nikamo ne šalju. Autor nema pristup. Vaša evidencija se ne pojavljuje ni na Googleu."),
("Nismo informatičari. Možemo li to sami?","Ako znate učitati datoteke preko FTP-a – da. A ako ne: javite se, pomoći ću vam osobno."),
("Treba li aplikacija iz Google Playa ili App Storea?","Ne. Na mobitelu u pregledniku odaberite „Dodaj na početni zaslon“ – radi na Androidu i iPhoneu."),
("Što ako stariji članovi nemaju pametni telefon?","Nema problema. Uprava upisuje za njih, a za knjigu dezinfekcije postoje papirnate liste za ispis koje se kasnije unesu u aplikaciju.")]
pit_html="\n".join(f'<details><summary>{q}</summary><p>{a}</p></details>' for q,a in pitanja)
ld={"@context":"https://schema.org","@graph":[
 {"@type":"SoftwareApplication","name":"Lovačka evidencija","applicationCategory":"BusinessApplication","operatingSystem":"Web, Android, iOS","inLanguage":"hr",
  "description":"Besplatna aplikacija za lovačke udruge: evidencija članova, članarina s HUB-3 uplatnicama, knjiga dezinfekcije, lovište i čeke, lovački dnevnik.",
  "offers":{"@type":"Offer","price":"0","priceCurrency":"EUR"},"url":URL,"screenshot":URL+"static/img/d-pocetna.jpg"},
 {"@type":"FAQPage","mainEntity":[{"@type":"Question","name":q,"acceptedAnswer":{"@type":"Answer","text":a}} for q,a in pitanja]}]}
ld_html='<script type="application/ld+json">'+json.dumps(ld,ensure_ascii=False)+'</script>\n'
index=glava("Lovačka evidencija – besplatni program za lovačke udruge",
 "Besplatna aplikacija za lovačke udruge i društva: evidencija članova, članarina s HUB-3 uplatnicama, knjiga dezinfekcije, čeke i lovački dnevnik. Od lovca za lovce.","",ld_html)+f'''
<section class="tamna hero" id="vrh">
<div class="okvir red sredina">
<div style="flex:1 1 420px;min-width:0">
<span class="znacka">Od lovca za lovce · besplatno</span>
<h1>Cijela lovačka udruga u jednoj aplikaciji.</h1>
<p class="tekst">Članovi, članarina s HUB-3 uplatnicama, knjiga dezinfekcije, čeke i lovački dnevnik – na računalu za upravu, na mobitelu za svakog lovca. Bez pretplate, bez naplate po članu. Pri postavljanju vam osobno pomažem.</p>
<div class="gumbi">
<a class="gumb zlatni" href="{DEMO}">Isprobajte demo – odmah</a>
<a class="gumb obrub" href="#pomoc">Besplatna pomoć pri instalaciji</a>
</div>
<p class="napomena">Demo bez registracije: izmišljena udruga, prijava jednim klikom.</p>
</div>
<div class="prikaz" aria-label="Prikaz aplikacije">
<div class="preglednik"><div class="tockice"><i></i><i></i><i></i></div>
<div class="zaslon racunalo" data-izmjena="racunalo">
{slike(desk,1600,1000)}
</div></div>
<div class="mobitel lebdi"><div class="zaslon" data-izmjena="mobitel">
{slike(mob,780,1688)}
</div></div>
<div class="oznaka" id="oznaka" aria-live="off">Početna uprave · Početna člana</div>
</div>
</div>
</section>

<section class="sekcija">
<div class="okvir red">
<div class="stup kartica"><div class="crta"></div><h2>Besplatno. Zauvijek.</h2><p>Nema pretplate, nema licence po članu, nema skrivenih troškova. Privatni, nekomercijalni projekt – iz lovačkog drugarstva.</p></div>
<div class="stup kartica"><div class="crta"></div><h2>Vaši podaci ostaju vaši</h2><p>Aplikacija radi na vašem webhostingu. Nitko drugi – ni autor – nema pristup podacima udruge. Kopije su šifrirane, ažuriranja potpisana.</p></div>
<div class="stup kartica"><div class="crta"></div><h2>Radi i u šumi</h2><p>Na mobitelu se otvara kao aplikacija, bez Google Playa i App Storea. Upis radi i bez signala – šalje se kad se vrati internet.</p></div>
</div>
</section>

<section class="sekcija svijetla" id="iznutra">
<div class="okvir">
<p class="nadnaslov">Pogledajte iznutra</p>
<h2 class="veliki" style="margin-bottom:36px">Ovako izgleda kod uprave.</h2>
<div class="red" style="gap:32px;align-items:flex-start">
<div class="tabovi" role="tablist" aria-label="Dijelovi aplikacije" aria-orientation="vertical">
{tab_html}
</div>
<div class="velikiprikaz"><div class="preglednik"><div class="zaslon" id="iznutra-prikaz" role="tabpanel" data-izmjena="iznutra">
{tab_slike}
</div></div></div>
</div>
</div>
</section>

<section class="sekcija">
<div class="okvir red sredina">
<div style="flex:1 1 420px;min-width:0">
<p class="nadnaslov">Za svakog lovca</p>
<h2 class="veliki">Na mobitelu jednostavno – i za one koji ne vole tehniku.</h2>
<p class="uvod" style="margin-bottom:20px">Lovac otvori aplikaciju i odmah vidi što ga zanima: sljedeće termine, svoje bodove, svoju uplatnicu i kartu lovišta. Zauzimanje čeke ili prijava radne akcije – jednim dodirom.</p>
<ul class="popis">
<li>Članska iskaznica s QR kodom – uvijek u džepu</li>
<li>Uplatnica s kodom za mobilno bankarstvo</li>
<li>Knjiga dezinfekcije: skeniraj QR na stanici i gotovo</li>
<li>Upis radi i bez signala u lovištu</li>
</ul>
</div>
<div class="dva">
<div class="mobitel lebdi"><img src="static/img/m-revir.jpg" alt="Karta lovišta na mobitelu" width="780" height="1688" loading="lazy"></div>
<div class="mobitel lebdi2"><img src="static/img/m-moja-iskaznica.jpg" alt="Članska iskaznica s QR kodom" width="780" height="1688" loading="lazy"></div>
</div>
</div>
</section>

<section class="sekcija svijetla" id="mogucnosti">
<div class="okvir">
<p class="nadnaslov">Što sve može</p>
<h2 class="veliki">Sve što udruga vodi na papiru i u tablicama – na jednom mjestu.</h2>
<p class="uvod">Svaka udruga uključuje samo module koje treba. Lovci vide jednostavan izbornik, uprava ima sve.</p>
<div class="mreza">
{moguc_html}
</div>
</div>
</section>

<section class="tamna">
<div class="autor">
<div class="krug"><img src="static/img/logo.svg" alt="" width="72" height="72"></div>
<div style="flex:1 1 520px;min-width:0">
<p class="nadnaslov">Od lovca za lovce</p>
<blockquote>„Evidencija se u lovačkim društvima često vodi na papiru, u bilježnicama i raznim tablicama. Želio sam napraviti alat koji je jednostavan, pregledan i radi na mobitelu – i za članove i za upravu.“</blockquote>
<p style="margin:0 0 20px">Iza aplikacije ne stoji firma, nego lovac i član lovačke udruge. Aplikaciju razvija u slobodno vrijeme i dijeli je besplatno – iz lovačkog drugarstva.</p>
<a href="{AUTOR}">Tko stoji iza aplikacije i zašto je besplatna →</a>
</div>
</div>
</section>

<section class="sekcija" id="pomoc">
<div class="okvir">
<p class="nadnaslov">Vlastita evidencija za vašu udrugu</p>
<h2 class="veliki">Postavljanje? Pomažem osobno – besplatno.</h2>
<p class="uvod">Ne morate biti informatičar. Javite se – zajedno ćemo postaviti evidenciju na vaš webhosting, preko telefona ili poruka, korak po korak. Ili sami u tri koraka:</p>
<ol class="koraci">
<li><div class="broj">1</div><h3>Preuzmite</h3><p>Zadnju verziju s GitHuba – ili samo jednu datoteku <code>instaliraj.php</code>.</p></li>
<li><div class="broj">2</div><h3>Učitajte</h3><p>Preko FTP-a na bilo koji PHP webhosting. MySQL baza nije potrebna.</p></li>
<li><div class="broj">3</div><h3>Pokrenite</h3><p>Upišite naziv udruge, sekcije i administratora. Nove verzije jednim klikom.</p></li>
</ol>
<div class="gumbi">
<a class="gumb zeleni" href="{AUTOR}">Javite se za pomoć</a>
<a href="{GH}" style="font-weight:600;padding:14px 8px">Upute i preuzimanje na GitHubu →</a>
</div>
</div>
</section>

<section class="svijetla" id="demo">
<div class="demo">
<h2>Isprobajte sami – za dvije minute.</h2>
<p class="uvod" style="margin:0 auto 28px;font-size:19px">Demo udruga s izmišljenim članovima. Odaberite ulogu i kliknite se kroz sve. Ništa ne možete pokvariti – svake noći sve se vraća na početno stanje.</p>
<div class="uloge">
<div><b>Glavni administrator</b><span>sve: članovi, članarina, lovište, postavke</span></div>
<div><b>Tajnik</b><span>članovi, članarina, akcije, izvještaji</span></div>
<div><b>Lovočuvar</b><span>lovište, dnevnik, dezinfekcija svoje sekcije</span></div>
<div><b>Član</b><span>što vidi običan lovac na mobitelu</span></div>
</div>
<a class="gumb zeleni" href="{DEMO}">Otvori demo</a>
<p style="color:var(--siva);font-size:15px;margin:16px 0 0">Radi na računalu i mobitelu. Bez registracije, bez e-pošte.</p>
</div>
</section>

<section class="sekcija" id="pitanja">
<div class="okvir" style="max-width:860px">
<h2 class="veliki" style="margin-bottom:28px">Česta pitanja</h2>
{pit_html}
</div>
</section>
'''+PODNOZJE+'<script src="static/web.js" defer></script>\n</body>\n</html>\n'
open(W+"/index.html","w").write(index)

MAIL='<img class="mail" src="static/img/kontakt.png" alt="E-mail adresa (slika)" width="256" height="22">'
imp=glava("Impresum – Lovačka evidencija","Impresum web stranice Lovačka evidencija.","impresum.html")+f'''
<div class="pravno">
<section id="hr" lang="hr">
<h1>Impresum</h1>
<p>Podaci prema Zakonu o elektroničkoj trgovini.</p>
<h2>Odgovorna osoba</h2>
<p>Darko Bakić<br>Privatna osoba – nekomercijalni projekt, bez obrta i tvrtke.</p>
<h2>Kontakt</h2>
<p>E-pošta: {MAIL}</p>
<h2>O projektu</h2>
<p>Lovačka evidencija je besplatni program za lovačke udruge. Ova stranica samo predstavlja program; ne prodaje se ništa i ne prikupljaju se plaćanja. Demo verzija sadrži isključivo izmišljene podatke.</p>
<p>Lovačke udruge koje koriste program same su odgovorne za svoju instalaciju i za podatke svojih članova.</p>
<h2>Odgovornost za sadržaj i poveznice</h2>
<p>Sadržaj je izrađen pažljivo, ali bez jamstva za potpunost i točnost. Za sadržaj vanjskih stranica na koje vode poveznice (npr. GitHub) odgovorni su isključivo njihovi vlasnici.</p>
</section>
</div>
'''+PODNOZJE+'</body>\n</html>\n'
open(W+"/impresum.html","w").write(imp)

priv=glava("Zaštita podataka – Lovačka evidencija","Izjava o zaštiti osobnih podataka web stranice Lovačka evidencija.","privatnost.html")+f'''
<div class="pravno">
<section id="hr" lang="hr">
<h1>Zaštita osobnih podataka</h1>
<p>Stanje: listopad 2026.</p>
<h2>1. Voditelj obrade</h2>
<p>Darko Bakić (privatna osoba), e-pošta: {MAIL}</p>
<h2>2. Ukratko</h2>
<p>Ova stranica nema analitiku, praćenje, oglase ni društvene mreže. Fontovi i slike učitavaju se s ovog poslužitelja, ne s Googlea ili drugih servisa. Ne morate se registrirati.</p>
<h2>3. Posjet stranici – zapisi poslužitelja</h2>
<p>Stranica se nalazi na besplatnom webhostingu InfinityFree (pružatelj usluge iFastNet Ltd.). Pri svakom posjetu poslužitelj tehnički obrađuje vašu IP adresu, vrijeme, traženu stranicu i podatke o pregledniku, kako bi stranica mogla biti prikazana i zaštićena od zlouporabe. Pravna osnova: legitimni interes (čl. 6. st. 1. t. f GDPR-a). Pružatelj hostinga može podatke obrađivati i izvan EU/EGP-a; detalji su u njegovoj izjavi o privatnosti.</p>
<h2>4. Kolačić „__test“</h2>
<p>Hosting postavlja tehnički kolačić <code>__test</code> koji provjerava da je posjetitelj stvarni preglednik, a ne automatski robot. Nužan je za rad stranice i ne služi praćenju.</p>
<h2>5. Demo verzija</h2>
<p>Demo sadrži samo izmišljenu udrugu i izmišljene osobe. Pri prijavi u demo postavlja se kolačić sesije (nužan za prijavu). Sve što upišete svake se noći briše. Molimo ne upisujte stvarne osobne podatke. E-pošta iz dema se ne šalje.</p>
<h2>6. Kontakt e-poštom</h2>
<p>Ako mi pišete, vašu adresu i poruku koristim samo za odgovor i pomoć pri postavljanju. Ne prosljeđujem ih nikome i brišem ih kad više nisu potrebne. Pravna osnova: čl. 6. st. 1. t. b i f GDPR-a.</p>
<h2>7. Vanjske poveznice</h2>
<p>Poveznica na GitHub vodi na stranicu drugog pružatelja (GitHub Inc.). Tamo vrijede njegova pravila o privatnosti.</p>
<h2>8. Vaša prava</h2>
<p>Imate pravo na pristup, ispravak, brisanje, ograničenje obrade, prigovor i prenosivost podataka (čl. 15.–21. GDPR-a). Javite se e-poštom. Možete podnijeti i pritužbu nadzornom tijelu, u Hrvatskoj Agenciji za zaštitu osobnih podataka (AZOP), www.azop.hr.</p>
<h2>9. Vaša udruga i vaši podaci</h2>
<p>Kad udruga koristi Lovačku evidenciju na svom poslužitelju, voditelj obrade podataka članova je ta udruga – ne autor programa. Autor nema pristup tim podacima.</p>
</section>
</div>
'''+PODNOZJE+'</body>\n</html>\n'
open(W+"/privatnost.html","w").write(priv)
open(W+"/robots.txt","w").write("User-agent: *\nAllow: /\nSitemap: "+URL+"sitemap.xml\n")
open(W+"/sitemap.xml","w").write(f'''<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc>{URL}</loc><changefreq>monthly</changefreq><priority>1.0</priority></url>
<url><loc>{DEMO}</loc><changefreq>monthly</changefreq><priority>0.8</priority></url>
<url><loc>{URL}impresum.html</loc><changefreq>yearly</changefreq><priority>0.2</priority></url>
<url><loc>{URL}privatnost.html</loc><changefreq>yearly</changefreq><priority>0.2</priority></url>
</urlset>
''')
print("ok")
