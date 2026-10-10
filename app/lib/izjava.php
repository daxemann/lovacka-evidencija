<?php
/**
 * Izjava udruge o vođenju evidencije dezinfekcije (za inspekciju): pravni temelj, način upisa,
 * zaštita od naknadnih promjena, provjera programa. Jednostavnim jezikom, pregled i PDF.
 * Kopija programa (ZIP) točno ove verzije, bez podataka udruge.
 */
declare(strict_types=1);

const IZJAVA_PRAVNI_TEMELJ = 'Naredba o mjerama kontrole za suzbijanje afričke svinjske kuge u Republici Hrvatskoj (Narodne novine br. 107/2026) – '
    . 'članak 1. točka 18., članak 2. stavak 12. te Dodatak I. točka 1. podtočke c) i d) i točka 2. podtočke a) i b): '
    . 'lovoovlaštenik provodi dezinfekciju obuće, opreme i vozila prije i nakon lova te vodi evidenciju o lovcima koji borave u lovištu '
    . 'i o provedbi dezinfekcije vozila, obuće i opreme.';

function izjava_pravni_temelj(): string
{
    return postavka('Dez.PravniTemelj', IZJAVA_PRAVNI_TEMELJ);
}

/** Sadržaj izjave kao HTML (isti tekst za stranicu i PDF). */
function izjava_html(array $stanice): string
{
    $loviste = postavka('Dez.Loviste', '');
    $o = '<h1>Izjava o vođenju evidencije dezinfekcije</h1>'
        . '<div class="pod">' . e(udruga_naziv()) . ($loviste !== '' ? ' · ' . e($loviste) : '') . '</div>';

    $o .= '<h2>1. Zašto vodimo ovu evidenciju</h2>'
        . '<p>' . nl2br(e(izjava_pravni_temelj())) . '</p>'
        . '<p>Udruga ovu evidenciju vodi <b>elektronički, umjesto papirnate bilježnice</b>. Sadržaj je isti: tko je bio u lovištu, kada je došao i otišao, '
        . 'kojim vozilom i što je dezinficirano.</p>';

    $o .= '<h2>2. Kako lovci upisuju</h2><ul>'
        . '<li>Na svakoj dezinfekcijskoj stanici nalazi se <b>QR oznaka</b>.</li>'
        . '<li>Lovac nakon dezinfekcije skenira QR oznaku mobitelom i odabere <b>DOLAZAK</b> ili <b>ODLAZAK</b>. Upisuju se ime i prezime, registarska oznaka vozila, '
        . 'razlog dolaska i što je dezinficirano (vozilo, obuća, oprema). Gosti i suputnici upisuju se zajedno s lovcem.</li>'
        . '<li>Lovci bez mobitela upisuju se na <b>papirnatu listu</b> na stanici. Lista se fotografira i prilaže evidenciji. '
        . 'Lovočuvar može takvog lovca upisati i mobitelom na samoj stanici – kod upisa piše tko ga je upisao.</li>'
        . '<li><b>Skupni lov (mobilna stanica):</b> pri dolasku svaki lovac upisuje se sam. Pri odlasku dežurna ekipa na izlazu redom dezinficira sva vozila (kao kontrolna točka), '
        . 'jer bi inače svi čekali satima. Pri zatvaranju stanice odgovorna osoba to potvrđuje, a za svakoga tko nije sam upisao odlazak upisuje se odlazak s oznakom '
        . '<b>„organizirana dezinfekcija pri odlasku“</b> i imenima osoba koje su dezinfekciju provele.</li>'
        . '<li>Ako lovac pri odlasku ne upiše odlazak, odlaska u evidenciji nema. <b>Program ništa ne dopisuje sam</b> (osim potvrđene organizirane dezinfekcije na mobilnoj stanici).</li>'
        . '</ul>';

    $o .= '<h2>3. Kako je evidencija zaštićena od naknadnih promjena</h2><ul>'
        . '<li><b>Vrijeme upisa</b> određuje računalo udruge (poslužitelj) u trenutku upisa, a ne mobitel lovca. Lovac ga ne može promijeniti. '
        . 'Ako na stanici nema signala, upis se spremi na mobitelu i pošalje čim signal postoji – takav upis je označen „offline“ i vidi se kada je primljen.</li>'
        . '<li><b>Mjesto upisa</b>: mobitel pri upisu šalje svoj položaj (GPS). Upis je moguć samo kod stanice – dalje od nje program ga odbija. '
        . 'Ako položaj nije pouzdan, upis je u evidenciji označen.</li>'
        . '<li><b>Upisi se ne mogu mijenjati.</b> Program nema mogućnost izmjene upisa. Pogrešan upis može se samo <b>poništiti, uz obavezan razlog</b>. '
        . 'Poništeni upis ostaje vidljiv u evidenciji, s razlogom, imenom osobe koja ga je poništila i vremenom.</li>'
        . '<li><b>Naknadni upisi</b> (npr. prijepis s papirnate liste) jasno su označeni kao „naknadno“, s razlogom i imenom osobe koja je upisala.</li>'
        . '<li>Svaka takva radnja – poništenje, naknadni upis, svaki pristup inspekcije i svako preuzimanje PDF-a – <b>zapisuje se u dnevnik promjena</b> programa.</li>'
        . '<li>Jedina iznimka: glavni administrator može trajno ukloniti <b>već poništene</b> upise (npr. probne upise pri uvođenju programa). I to se zapisuje u dnevnik promjena.</li>'
        . '</ul>';

    $o .= '<h2>4. Provjera programa</h2>'
        . '<p>Program „Lovačka evidencija“ je <b>otvorenog koda</b>: cijeli program je javno objavljen na adresi <b>' . e(PROJEKT_URL) . '</b> '
        . 'i svatko ga može pregledati. Udruga koristi verziju <b>' . e(VERZIJA) . '</b>.</p>'
        . '<p>Na zahtjev inspekcije udruga daje <b>kopiju programa točno ove verzije</b> (bez osobnih podataka članova) kako bi je mogla provjeriti stručna osoba. '
        . 'Kopija se može preuzeti i izravno na stranici za inspekciju („Kopija programa“).</p>';

    $o .= dez_potpis_pdf_html($stanice);
    return $o;
}

function izjava_pdf(array $stanice): string
{
    $logo = '';
    if (ima_logo()) {
        $l = podaci('foto/' . basename((string) postavka('Udruga.Logo')));
        $logo = '<img src="data:' . (str_ends_with($l, '.png') ? 'image/png' : 'image/jpeg') . ';base64,' . base64_encode((string) file_get_contents($l)) . '" style="height:46px;float:left;margin-right:10px">';
    }
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 12mm 14mm 15mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.8pt; color: #222; line-height: 1.28; }
        .zag { border-bottom: 2px solid #3d6b2f; padding-bottom: 6px; margin-bottom: 10px; overflow: hidden; }
        .zag .n { font-size: 13pt; font-weight: bold; color: #2f5d23; }
        h1 { font-size: 14pt; margin: 4px 0 2px; } .pod { color: #555; margin-bottom: 8px; }
        h2 { font-size: 10.5pt; margin: 8px 0 3px; color: #2f5d23; }
        ul { margin: 0 0 0 0; padding-left: 16px; } li { margin-bottom: 2px; } p { margin: 0 0 4px; }
        .mala { font-size: 7.5pt; color: #777; margin-top: 10px; }
        .podnozje { position: fixed; bottom: -9mm; left: 0; right: 0; font-size: 7pt; color: #888; }
    </style></head><body>
    <div class="podnozje">' . e(udruga_naziv()) . ' · izjava o vođenju evidencije dezinfekcije · izdano ' . date('d.m.Y.') . ' iz programa „Lovačka evidencija“ ' . e(VERZIJA) . '</div>
    <div class="zag">' . $logo . '<div class="n">' . e(udruga_naziv()) . '</div></div>' . izjava_html($stanice) . '</body></html>';
    $opt = new Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('tempDir', sys_get_temp_dir());
    $opt->set('fontCache', podaci());
    $pdf = new Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->getCanvas()->page_text(520, 815, 'str. {PAGE_NUM}/{PAGE_COUNT}', null, 7, [0.5, 0.5, 0.5]);
    return (string) $pdf->output();
}

/**
 * Kopija programa (ZIP) – svi programski dijelovi ove instalacije, bez mape s podacima, konfiguracije i ključeva.
 * Sprema se jednom po verziji u podaci/ i ponovno koristi. Vraća put do datoteke ili null (ZIP nije podržan).
 */
function program_zip(): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $cilj = podaci('program-' . VERZIJA . '.zip');
    if (is_file($cilj) && filesize($cilj) > 0) {
        return $cilj;
    }
    $korijen = realpath(KORIJEN);
    $podaci = realpath(podaci());
    $tmp = $cilj . '.tmp';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return null;
    }
    $preskoci = ['.git', 'podaci', 'config.php', 'izdanje'];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($korijen, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $dat) {
        $put = $dat->getPathname();
        $rel = ltrim(str_replace('\\', '/', substr($put, strlen($korijen))), '/');
        $prvi = explode('/', $rel)[0];
        if (in_array($prvi, $preskoci, true) || ($podaci && str_starts_with($put, $podaci)) || !$dat->isFile()) {
            continue;
        }
        $zip->addFile($put, 'lovacka-evidencija-' . VERZIJA . '/' . $rel);
    }
    $zip->setArchiveComment('Lovačka evidencija ' . VERZIJA . ' – ' . PROJEKT_URL . ' – kopija programa bez podataka udruge');
    $zip->close();
    @rename($tmp, $cilj);
    foreach (glob(podaci('program-*.zip')) ?: [] as $stari) {
        if ($stari !== $cilj) {
            @unlink($stari);
        }
    }
    return is_file($cilj) ? $cilj : null;
}
