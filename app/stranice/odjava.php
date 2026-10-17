<?php
if (je_post()) {
    // ovaj uređaj više ne prima obavijesti za odjavljenog korisnika
    if (!empty($_SESSION['push_endpoint'])) {
        push_odjavi((string) $_SESSION['push_endpoint']);
    }
    odjavi();
}
preusmjeri('prijava');
