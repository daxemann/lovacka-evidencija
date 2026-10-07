<?php
// Polling za obavijesti o novim porukama (zamjena za "uživo" obavijesti iz .NET verzije).
$k = korisnik();
session_write_close();
$n = broj_neprocitanih($k['Id']);
$z = null;
if ($n > 0) {
    $p = red('SELECT p.Tekst, p.RazgovorId, o.Naslov, k.KorisnickoIme, k.PrikaznoIme, c.Ime, c.Prezime FROM PorukeRazgovora p
              JOIN Razgovori r ON r.Id=p.RazgovorId JOIN Oglasi o ON o.Id=r.OglasId
              JOIN Korisnici k ON k.Id=p.PosiljateljId LEFT JOIN Clanovi c ON c.Id=k.ClanId
              WHERE p.Procitano=0 AND p.PosiljateljId<>? AND (r.KupacId=? OR o.KorisnikId=?) ORDER BY p.Id DESC LIMIT 1', [$k['Id'], $k['Id'], $k['Id']]);
    if ($p) {
        $od = $p['Ime'] ? trim($p['Ime'] . ' ' . $p['Prezime']) : ($p['PrikaznoIme'] ?: $p['KorisnickoIme']);
        $z = ['naslov' => 'Nova poruka – ' . $od, 'tekst' => $p['Naslov'] . ': ' . mb_strimwidth($p['Tekst'], 0, 90, '…'),
              'link' => url('poruke/razgovor', ['id' => $p['RazgovorId']]), 'razgovor' => (int) $p['RazgovorId']];
    }
}
json(['n' => $n, 'zadnja' => $z]);
