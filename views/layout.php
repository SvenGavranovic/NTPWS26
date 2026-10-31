<?php
/**
 * @var string $content
 * @var string $title
 */

use App\Core\Auth;
use App\Core\Session;

$korisnik = Auth::user();
$flash = Session::pullFlash();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Zagreb Prijave') ?> · Zagreb Prijave</title>
    <meta name="description" content="Prijava komunalnih problema, radovi na infrastrukturi i stanje u prometu u Zagrebu.">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/grb-zagreb.svg')) ?>">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <?php if (!empty($karta)): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H"
              crossorigin="anonymous">
    <?php endif; ?>
    <?php if (!empty($urednik)): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css"
              integrity="sha384-ecIckRi4QlKYya/FQUbBUjS4qp65jF/J87Guw5uzTbO1C1Jfa/6kYmd6dXUF6D7i"
              crossorigin="anonymous">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<a class="visually-hidden-focusable" href="#sadrzaj">Preskoči na sadržaj</a>

<nav class="navbar navbar-expand-lg navbar-dark bg-zagreb shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset('img/grb-zagreb.svg')) ?>" alt="" class="brand-grb">
            Zagreb Prijave
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#glavniIzbornik"
                aria-controls="glavniIzbornik" aria-expanded="false" aria-label="Izbornik">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="glavniIzbornik">
            <ul class="navbar-nav me-auto">
                <?= nav_link('/', 'Početna') ?>
                <?= nav_link('/vijesti', 'Vijesti') ?>
                <?= nav_link('/karta', 'Karta') ?>
                <?= nav_link('/o-projektu', 'O projektu') ?>
                <?php if ($korisnik): ?>
                    <?= nav_link('/prijave/moje', 'Moje prijave') ?>
                <?php endif; ?>
                <?php if (Auth::hasRole('admin')): ?>
                    <?= nav_link('/admin', 'Administracija') ?>
                <?php elseif (Auth::hasRole('urednik')): ?>
                    <?= nav_link('/admin/vijesti', 'Moje vijesti') ?>
                <?php elseif (Auth::hasRole('radnik')): ?>
                    <?= nav_link('/radnik/zadaci', 'Moji zadaci') ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav align-items-lg-center gap-lg-2">
                <?php if ($korisnik): ?>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm text-zagreb fw-semibold" href="<?= e(url('/prijave/nova')) ?>">+ Prijavi problem</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('/racun/profil')) ?>">
                            <?= e($korisnik['ime'] . ' ' . $korisnik['prezime']) ?>
                            <span class="badge text-bg-light ms-1"><?= e($korisnik['uloga_naziv']) ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?= e(url('/racun/odjava')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-light btn-sm">Odjava</button>
                        </form>
                    </li>
                <?php else: ?>
                    <?= nav_link('/racun/prijava', 'Prijava') ?>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm text-zagreb fw-semibold" href="<?= e(url('/racun/registracija')) ?>">Registracija</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main id="sadrzaj" class="flex-grow-1">
    <?php if ($flash): ?>
        <div class="container pt-4">
            <?php foreach ($flash as $poruka): ?>
                <div class="alert alert-<?= e($poruka['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($poruka['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zatvori"></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($punaSirina)): ?>
        <?= $content ?>
    <?php else: ?>
        <div class="container py-4"><?= $content ?></div>
    <?php endif; ?>
</main>

<footer class="footer border-top mt-auto">
    <div class="container py-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-7 d-flex gap-3 align-items-start">
                <img src="<?= e(asset('img/grb-zagreb.svg')) ?>" alt="" class="footer-grb">
                <div class="small text-body-secondary">
                    <strong class="text-body">Zagreb Prijave</strong> · studentski projekt, kolegij
                    Napredno web projektiranje web servisa.<br>
                    <strong>Neslužbena aplikacija</strong> – nije povezana s Gradom Zagrebom niti s gradskim službama.
                    Prijave poslane ovdje ne stižu nadležnim službama.
                </div>
            </div>
            <div class="col-md-5 small text-md-end">
                <a href="<?= e(url('/vijesti')) ?>" class="link-secondary me-3">Vijesti</a>
                <a href="<?= e(url('/o-projektu')) ?>" class="link-secondary me-3">O projektu</a>
                <br class="d-none d-md-inline">
                <span class="text-body-secondary">
                    Grb: <a class="link-secondary" href="https://commons.wikimedia.org/wiki/File:Coat_of_arms_of_Zagreb.svg"
                            rel="noopener" target="_blank">Wikimedia Commons</a>, javno vlasništvo
                </span>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($karta)): ?>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH"
            crossorigin="anonymous"></script>
    <script src="<?= e(asset('js/odabir-lokacije.js')) ?>"></script>
    <script src="<?= e(asset('js/karta.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($vijestiUzivo)): ?>
    <script src="<?= e(asset('js/vijesti.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($kanban)): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"
            integrity="sha384-DgmC6Xe2bSN2WjTDXzWYbUbxyhNP+NNkGDR/g78pCXV7E7rcVTGxVg0uIVCUUcBc"
            crossorigin="anonymous"></script>
    <script src="<?= e(asset('js/kanban.js')) ?>"></script>
<?php endif; ?>
<?php if (!empty($urednik)): ?>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"
            integrity="sha384-utBUCeG4SYaCm4m7GQZYr8Hy8Fpy3V4KGjBZaf4WTKOcwhCYpt/0PfeEe3HNlwx8"
            crossorigin="anonymous"></script>
    <script src="<?= e(asset('js/urednik.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
