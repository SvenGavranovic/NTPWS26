<?php
/** @var array $broj */

$kartice = [
    ['/admin/vijesti', 'Vijesti', $broj['objavljeno'] . ' objavljeno · ' . $broj['nacrt'] . ' u nacrtu', 'Sve vijesti, objava i povlačenje'],
    ['/admin/kategorije', 'Kategorije', $broj['kategorije'] . ' kategorija', 'Dodavanje, uređivanje i brisanje praznih kategorija'],
    ['/admin/korisnici', 'Korisnici', $broj['aktivni'] . ' aktivnih · ' . $broj['neaktivni'] . ' deaktiviranih', 'Uloge te aktivacija i deaktivacija računa'],
];
?>
<h1 class="h2 mb-3">Administracija</h1>
<?php require __DIR__ . '/_nav.php'; ?>

<div class="row g-4">
    <?php foreach ($kartice as [$putanja, $naslov, $brojevi, $opis]): ?>
        <div class="col-md-4">
            <div class="card h-100 vijest-kartica">
                <div class="card-body">
                    <h2 class="h5 card-title">
                        <a href="<?= e(url($putanja)) ?>" class="stretched-link text-body text-decoration-none"><?= e($naslov) ?></a>
                    </h2>
                    <p class="fw-semibold text-zagreb mb-1"><?= e($brojevi) ?></p>
                    <p class="card-text text-body-secondary small mb-0"><?= e($opis) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<p class="text-body-secondary small mt-4 mb-0">
    Dodjela zadataka radnicima, hitnost i provjera završetka dodaju se u fazi 6, a radovi na infrastrukturi u fazi 8.
</p>
