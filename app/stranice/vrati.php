<?php
// Vraćanje kompletne kopije pri prvom pokretanju (dok još nema korisnika).
if (!je_post() || vrijednost('SELECT 1 FROM Korisnici LIMIT 1')) {
    preusmjeri('prijava');
}
$f = $_FILES['kopija'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
    poruka('Odaberite ZIP datoteku kompletne kopije.' . upload_greska($f), 'danger');
    preusmjeri('postavljanje');
}
try {
    vrati_kompletnu_kopiju($f['tmp_name']);
} catch (Throwable $e) {
    poruka('Vraćanje nije uspjelo: ' . e($e->getMessage()), 'danger');
    preusmjeri('postavljanje');
}
preusmjeri('prijava', ['vraceno' => 1]);
