<?php
/** @var array $korisnik */
?>
<h1 class="h3 mb-3">Administracija</h1>

<p>
    Prijavljeni ste kao <strong><?= e($korisnik['ime'] . ' ' . $korisnik['prezime']) ?></strong>
    (<?= e($korisnik['uloga_naziv']) ?>).
</p>

<div class="alert alert-info">
    Ova stranica dostupna je samo ulozi <code>admin</code>. Ostali dobivaju odgovor 403.
    Upravljanje vijestima, kategorijama i korisnicima dodaje se u fazi 4, a dodjela zadataka u fazi 6.
</div>
